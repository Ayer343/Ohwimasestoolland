<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use App\Services\OwnershipTransferService;
use App\Services\DigitalSignatureService;
use App\Notifications\TransferStatusNotification;
use App\Notifications\OwnershipTransferRequested;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Models\ActivityLog;

class TransferResubmissionController extends Controller
{
    protected $transferService;
    protected $digitalSignatureService;

    public function __construct(
        OwnershipTransferService $transferService,
        DigitalSignatureService $digitalSignatureService = null
    ) {
        $this->transferService = $transferService;
        $this->digitalSignatureService = $digitalSignatureService;
    }

    /**
     * Show resubmit form with pre-filled rejected transfer data
     */
    public function resubmit(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $this->authorizeResubmission($transfer);
        
        $user = auth()->user();
        
        if ($user->id !== $transfer->current_landlord_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to resubmit this transfer request.'
                ], 403);
            }
            return redirect()->back()->with('error', 'You are not authorized to resubmit this transfer request.');
        }
        
        if (!$transfer->canBeResubmitted()) {
            $errorMessage = $this->getResubmissionErrorMessage($transfer);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This transfer cannot be resubmitted. ' . $errorMessage
                ], 400);
            }
            return redirect()->back()->with('error', 'This transfer cannot be resubmitted. ' . $errorMessage);
        }
        
        $documentTypes = PropertyOwnershipTransfer::getDocumentTypes();
        $rejectionReason = $transfer->rejection_reason;
        $isExistingLandlord = $transfer->metadata['is_existing_landlord'] ?? false;
        
        // Get existing landlords for dropdown
        $existingLandlords = User::where('type', User::TYPE_LANDLORD)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);
        
        $prefillData = [
            'transfer' => $transfer,
            'property' => $property,
            'documentTypes' => $documentTypes,
            'rejectionReason' => $rejectionReason,
            'isExistingLandlord' => $isExistingLandlord,
            'existingLandlordId' => $isExistingLandlord ? $transfer->new_landlord_id : null,
            'existingLandlords' => $existingLandlords,
            'newOwnerName' => $transfer->new_owner_name,
            'newOwnerPhone' => $transfer->new_owner_phone,
            'newOwnerEmail' => $transfer->new_owner_email,
            'newOwnerAddress' => $transfer->new_owner_address,
            'transferDate' => $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : null,
            'saleAmount' => $transfer->sale_amount,
            'documentType' => $transfer->document_type,
            'documentReference' => $transfer->document_reference,
            'reasonForTransfer' => $transfer->reason_for_transfer,
            'notes' => $transfer->notes,
            'previousDocumentUrl' => $transfer->document_url,
            'canResubmitAfter' => $transfer->can_resubmit_after,
        ];
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $prefillData
            ]);
        }
        
        return view('landlord.ownership-transfers.resubmit', $prefillData);
    }

    /**
     * Process resubmission of a rejected transfer - UPDATE EXISTING TRANSFER
     */
    public function processResubmit(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $this->authorizeResubmission($transfer);
        
        $user = auth()->user();
        
        if ($user->id !== $transfer->current_landlord_id) {
            return $this->errorResponse($request, 'You are not authorized to resubmit this transfer request.');
        }
        
        if (!$transfer->canBeResubmitted()) {
            $errorMessage = $this->getResubmissionErrorMessage($transfer);
            return $this->errorResponse($request, 'This transfer cannot be resubmitted. ' . $errorMessage);
        }
        
        $validator = Validator::make($request->all(), $this->getResubmitValidationRules($request, $transfer));
        
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        DB::beginTransaction();
        
        try {
            // Handle document upload (use existing if no new file uploaded)
            $documentPath = $transfer->document_url;
            if ($request->hasFile('transfer_document')) {
                // Delete old document if exists
                if ($transfer->document_url && Storage::disk('public')->exists($transfer->document_url)) {
                    Storage::disk('public')->delete($transfer->document_url);
                }
                $documentPath = $this->uploadTransferDocument($request);
                if (!$documentPath) {
                    throw new \Exception('Failed to upload new document.');
                }
            }
            
            // Handle digital signature
            $signatureData = null;
            if ($request->has('apply_digital_signature') && $this->digitalSignatureService) {
                $signatureData = $this->applyDigitalSignature($request, $documentPath);
            }
            
            // Handle new landlord (existing or new)
            $newLandlord = null;
            $isExistingLandlord = false;
            
            if ($request->has('existing_landlord_id') && !empty($request->existing_landlord_id)) {
                $newLandlord = User::find($request->existing_landlord_id);
                if ($newLandlord && $newLandlord->isLandlord()) {
                    $isExistingLandlord = true;
                } else {
                    throw new \Exception('Selected existing landlord not found or is not a valid landlord.');
                }
            } else {
                $newLandlord = $this->transferService->findOrCreateNewLandlord([
                    'name' => $request->new_owner_name,
                    'phone' => $request->new_owner_phone,
                    'email' => $request->new_owner_email,
                    'address' => $request->new_owner_address,
                ]);
            }
            
            if (!$newLandlord) {
                throw new \Exception('Failed to create or find new landlord user.');
            }
            
            // Update metadata to track resubmission history
            $metadata = $transfer->metadata ?? [];
            $metadata['resubmitted_at'] = now()->toISOString();
            $metadata['resubmitted_by'] = auth()->id();
            $metadata['resubmission_attempt'] = ($metadata['resubmission_attempt'] ?? 0) + 1;
            $metadata['original_rejection_reason'] = $transfer->rejection_reason;
            $metadata['previous_document_url'] = $transfer->document_url;
            $metadata['previous_transfer_date'] = $transfer->transfer_date;
            $metadata['digital_signature'] = $signatureData;
            $metadata['ip_address'] = $request->ip();
            $metadata['user_agent'] = $request->userAgent();
            $metadata['is_existing_landlord'] = $isExistingLandlord;
            $metadata['existing_landlord_id'] = $isExistingLandlord ? $newLandlord->id : null;
            
            // Check if this is a resubmission from a previous resubmission (chain)
            if (isset($metadata['resubmitted_to'])) {
                $metadata['resubmission_chain'] = array_merge(
                    $metadata['resubmission_chain'] ?? [],
                    [$metadata['resubmitted_to']]
                );
                unset($metadata['resubmitted_to']);
            }
            
            // Update the existing transfer record
            $transfer->update([
                'new_landlord_id' => $newLandlord->id,
                'status' => PropertyOwnershipTransfer::STATUS_PENDING,
                'transfer_date' => $request->transfer_date,
                'sale_amount' => $request->sale_amount,
                'document_type' => $request->document_type,
                'document_reference' => $request->document_reference,
                'document_url' => $documentPath,
                'new_owner_name' => $request->new_owner_name ?? $newLandlord->name,
                'new_owner_phone' => $request->new_owner_phone ?? $newLandlord->phone,
                'new_owner_email' => $request->new_owner_email ?? $newLandlord->email,
                'new_owner_address' => $request->new_owner_address ?? $newLandlord->location,
                'reason_for_transfer' => $request->reason_for_transfer,
                'notes' => $request->notes,
                'rejection_reason' => null, // Clear rejection reason
                'rejected_at' => null, // Clear rejection timestamp
                'rejected_by_id' => null, // Clear rejected by
                'can_resubmit_after' => null, // Clear resubmission eligibility
                'metadata' => $metadata
            ]);
            
            // Log the resubmission activity
            if (class_exists(ActivityLog::class)) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'ownership_transfer_resubmitted',
                    'description' => "Ownership transfer #{$transfer->id} resubmitted for property: {$property->property_name}",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'transfer_id' => $transfer->id,
                        'property_id' => $property->id,
                        'resubmission_attempt' => $metadata['resubmission_attempt'],
                        'previous_rejection_reason' => $metadata['original_rejection_reason'],
                        'is_existing_landlord' => $isExistingLandlord,
                        'new_landlord_id' => $newLandlord->id
                    ]
                ]);
            }
            
            // Trigger webhooks for resubmission
            $this->triggerWebhooks('transfer.resubmitted', $transfer);
            
            // Send notifications
            try {
                // Notify current landlord (sender)
                auth()->user()->notify(new TransferStatusNotification($transfer, 'resubmitted'));
                
                // Notify admins about the resubmission
                $this->notifyAdminsOfResubmission($transfer);
                
                // If new landlord already exists, notify them
                if ($isExistingLandlord && $newLandlord) {
                    $newLandlord->notify(new TransferStatusNotification($transfer, 'resubmitted_receiver'));
                }
            } catch (\Exception $e) {
                // Silent fail - notification issues shouldn't block the process
                \Log::warning('Failed to send resubmission notifications: ' . $e->getMessage());
            }
            
            // Clear all caches
            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();
            
            DB::commit();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer request resubmitted successfully. Awaiting admin approval.',
                    'transfer' => $transfer->fresh()->load(['property', 'currentLandlord', 'newLandlord']),
                    'is_existing_landlord' => $isExistingLandlord,
                    'resubmission_attempt' => $metadata['resubmission_attempt']
                ], 201);
            }
            
            $successMessage = 'Transfer request resubmitted successfully. Awaiting admin approval.';
            if ($isExistingLandlord) {
                $successMessage .= ' The new owner already has an account in the system. No invitation will be sent.';
            }
            
            return redirect()->route('landlord.ownership-transfers.index')
                ->with('success', $successMessage)
                ->with('transfer_id', $transfer->id)
                ->with('resubmit_success', true);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Resubmission failed for transfer ID: ' . $transfer->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resubmit transfer request: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to resubmit transfer request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Preview resubmission data (AJAX endpoint)
     */
    public function previewResubmit(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $this->authorizeResubmission($transfer);
        
        return response()->json([
            'success' => true,
            'transfer' => $transfer,
            'property' => $transfer->property,
            'current_landlord' => $transfer->currentLandlord,
            'new_landlord' => $transfer->newLandlord,
            'new_owner_details' => [
                'name' => $transfer->new_owner_name,
                'phone' => $transfer->new_owner_phone,
                'email' => $transfer->new_owner_email,
                'address' => $transfer->new_owner_address
            ],
            'transfer_details' => [
                'transfer_date' => $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : null,
                'sale_amount' => $transfer->sale_amount,
                'document_type' => $transfer->document_type,
                'document_type_label' => $transfer->document_type_label,
                'document_reference' => $transfer->document_reference,
                'reason_for_transfer' => $transfer->reason_for_transfer,
                'notes' => $transfer->notes
            ],
            'rejection_info' => [
                'rejected_at' => $transfer->rejected_at ? $transfer->rejected_at->format('Y-m-d H:i:s') : null,
                'rejected_by' => $transfer->rejectedBy ? $transfer->rejectedBy->name : null,
                'rejection_reason' => $transfer->rejection_reason
            ],
            'resubmission_info' => [
                'can_resubmit' => $transfer->canBeResubmitted(),
                'resubmit_after' => $transfer->can_resubmit_after ? $transfer->can_resubmit_after->format('Y-m-d') : null,
                'resubmission_attempts' => ($transfer->metadata['resubmission_attempt'] ?? 0),
                'previous_resubmissions' => $transfer->metadata['resubmission_chain'] ?? []
            ],
            'is_existing_landlord' => $transfer->metadata['is_existing_landlord'] ?? false,
            'existing_landlord_id' => $transfer->metadata['existing_landlord_id'] ?? null
        ]);
    }

    /**
     * Check resubmission eligibility (AJAX endpoint)
     */
    public function checkEligibility(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $this->authorizeResubmission($transfer);
        
        return response()->json([
            'success' => true,
            'can_resubmit' => $transfer->canBeResubmitted(),
            'rejection_reason' => $transfer->rejection_reason,
            'resubmit_after' => $transfer->can_resubmit_after ? $transfer->can_resubmit_after->format('Y-m-d') : null,
            'resubmitted_to' => $transfer->metadata['resubmitted_to'] ?? null,
            'original_transfer_id' => $transfer->metadata['resubmitted_from'] ?? null,
            'resubmission_attempts' => ($transfer->metadata['resubmission_attempt'] ?? 0),
            'is_resubmission' => isset($transfer->metadata['resubmitted_from']),
            'resubmission_chain' => $transfer->metadata['resubmission_chain'] ?? []
        ]);
    }

    /**
     * Get validation rules for resubmission
     */
    private function getResubmitValidationRules(Request $request, PropertyOwnershipTransfer $transfer)
    {
        $isExistingLandlord = $request->has('existing_landlord_id') && !empty($request->existing_landlord_id);
        
        $rules = [
            'transfer_date' => 'required|date',
            'sale_amount' => 'nullable|numeric|min:0',
            'document_type' => 'required|in:' . implode(',', array_keys(PropertyOwnershipTransfer::getDocumentTypes())),
            'document_reference' => 'required|string|max:100|unique:property_ownership_transfers,document_reference,' . $transfer->id,
            'reason_for_transfer' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];
        
        if ($request->hasFile('transfer_document')) {
            $rules['transfer_document'] = 'file|mimes:pdf,jpg,jpeg,png|max:' . (config('ownership_transfer.max_file_size', 5) * 1024);
        }
        
        if ($isExistingLandlord) {
            $rules['existing_landlord_id'] = 'required|exists:users,id';
        } else {
            $rules['new_owner_name'] = 'required|string|max:255';
            $rules['new_owner_phone'] = 'required|string|max:20';
            $rules['new_owner_email'] = 'nullable|email|max:255';
            $rules['new_owner_address'] = 'nullable|string|max:500';
        }
        
        if ($request->has('apply_digital_signature')) {
            $rules['signature_token'] = 'required|string';
            $rules['signature_timestamp'] = 'required|date';
        }
        
        return $rules;
    }

    /**
     * Upload transfer document
     */
    private function uploadTransferDocument(Request $request)
    {
        try {
            if (!$request->hasFile('transfer_document')) {
                return null;
            }

            $file = $request->file('transfer_document');
            
            if (!$file->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            $maxSize = config('ownership_transfer.max_file_size', 5) * 1024 * 1024;
            if ($file->getSize() > $maxSize) {
                throw new \Exception('File size exceeds limit');
            }

            $path = $file->store('ownership-transfers/documents', 'public');
            
            if (!$path) {
                throw new \Exception('Storage failed');
            }

            return $path;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Apply digital signature
     */
    private function applyDigitalSignature(Request $request, $documentPath)
    {
        if (!$this->digitalSignatureService) {
            return null;
        }

        return $this->digitalSignatureService->signDocument([
            'document_path' => $documentPath,
            'signer_id' => auth()->id(),
            'signature_token' => $request->signature_token,
            'timestamp' => $request->signature_timestamp,
            'metadata' => [
                'transfer_type' => 'property_ownership_resubmission',
                'signer_role' => 'current_owner'
            ]
        ]);
    }

    /**
     * Notify admins about resubmission
     */
    private function notifyAdminsOfResubmission(PropertyOwnershipTransfer $transfer)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
        
        foreach ($admins as $admin) {
            try {
                $admin->notify(new TransferStatusNotification($transfer, 'resubmitted'));
            } catch (\Exception $e) {
                \Log::warning('Failed to notify admin about resubmission: ' . $e->getMessage());
            }
        }
    }

    /**
     * Trigger webhooks
     */
    private function triggerWebhooks($event, $data)
    {
        try {
            // You can implement webhook logic here if needed
            \Log::info('Webhook triggered', ['event' => $event, 'transfer_id' => $data->id]);
        } catch (\Exception $e) {
            // Silent fail - webhook issues shouldn't block the process
        }
    }

    /**
     * Get resubmission error message
     */
    private function getResubmissionErrorMessage(PropertyOwnershipTransfer $transfer): string
    {
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_REJECTED) {
            return 'Only rejected transfers can be resubmitted.';
        }
        
        if ($transfer->can_resubmit_after && $transfer->can_resubmit_after->isFuture()) {
            return 'Resubmission is available after ' . $transfer->can_resubmit_after->format('F j, Y');
        }
        
        if (isset($transfer->metadata['resubmitted_to'])) {
            $resubmittedToId = $transfer->metadata['resubmitted_to'];
            return 'This transfer has already been resubmitted as request #' . $resubmittedToId;
        }
        
        return 'This transfer cannot be resubmitted.';
    }

    /**
     * Authorize resubmission access
     */
    private function authorizeResubmission(PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        // Only the current landlord (sender) can resubmit
        if ($user->id !== $transfer->current_landlord_id) {
            abort(403, 'You are not authorized to resubmit this transfer request.');
        }
        
        // Only rejected transfers can be resubmitted
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_REJECTED) {
            abort(400, 'Only rejected transfers can be resubmitted.');
        }
    }

    /**
     * Clear transfer cache
     */
    private function clearTransferCache(PropertyOwnershipTransfer $transfer)
    {
        Cache::forget("transfer_{$transfer->id}");
        Cache::forget('ownership_transfer_stats');
    }

    /**
     * Clear all transfer caches
     */
    private function clearAllTransferCaches()
    {
        Cache::forget('ownership_transfer_stats');
        $keys = Cache::get('ownership_transfer_cache_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::put('ownership_transfer_cache_keys', [], 3600);
    }

    /**
     * Error response
     */
    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 500);
        }
        return redirect()->back()->with('error', $message);
    }
}