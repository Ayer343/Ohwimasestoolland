<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use App\Models\PropertyUnit;
use App\Models\UserInvitation;
use App\Services\OwnershipTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Notifications\TransferStatusNotification;
use App\Notifications\OwnershipTransferRequested;
use App\Notifications\TransferReversalRequested;
use App\Notifications\TransferReversalApproved;
use App\Notifications\TransferReversalRejected;
use App\Notifications\TransferReversalCompleted;
use App\Notifications\TransferReversalExpired;

class LandlordTransferController extends Controller
{
    protected $transferService;

    public function __construct(OwnershipTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Show the form for creating a new ownership transfer
     */
    public function create(Property $property)
    {
        $user = auth()->user();
        
        // Check if user owns this property
        if ($user->id !== $property->landlord_id) {
            return redirect()->route('properties.show', $property->id)
                ->with('error', 'You can only transfer ownership of properties you own.');
        }
        
        // Check if there's already a pending transfer
        $pendingTransfer = $property->currentOwnershipTransfer;
        if ($pendingTransfer && in_array($pendingTransfer->status, [
            PropertyOwnershipTransfer::STATUS_PENDING,
            PropertyOwnershipTransfer::STATUS_APPROVED
        ])) {
            return redirect()->route('properties.ownership-transfers.show', [$property->id, $pendingTransfer->id])
                ->with('error', 'This property already has a pending or approved transfer request.');
        }
        
        // Get existing landlords for dropdown (active landlords except current user)
        $existingLandlords = User::where('type', User::TYPE_LANDLORD)
            ->where('id', '!=', $user->id)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);
        
        // Get units for tenant warning
        $units = $property->units;
        $unitsWithTenants = $units->filter(function($unit) {
            return !is_null($unit->tenant_id);
        });
        
        // Get related properties for bulk transfer
        $bulkTransferEnabled = config('ownership_transfer.enable_bulk_transfer', false);
        $relatedProperties = $bulkTransferEnabled 
            ? $user->properties()
                ->where('id', '!=', $property->id)
                ->whereDoesntHave('currentOwnershipTransfer', function($query) {
                    $query->whereIn('status', [
                        PropertyOwnershipTransfer::STATUS_PENDING,
                        PropertyOwnershipTransfer::STATUS_APPROVED
                    ]);
                })
                ->select('id', 'property_name', 'registration_pattern', 'street_name', 'zone')
                ->withCount('units')
                ->get()
            : collect();
        
        // Get recent transfers for this property
        $recentTransfers = PropertyOwnershipTransfer::where('property_id', $property->id)
            ->where('status', '!=', PropertyOwnershipTransfer::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        $hasCompletedTransfers = PropertyOwnershipTransfer::where('property_id', $property->id)
            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->exists();
        
        // Generate initial document reference
        $initialDocRef = 'TRANS-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Get config values for the view
        $digitalSignatureEnabled = config('ownership_transfer.require_digital_signature', false);
        $digitalSignatureThreshold = config('ownership_transfer.digital_signature_threshold', 1000000);
        $webhookEnabled = config('ownership_transfer.webhook_enabled', false);
        $maxFileSize = config('ownership_transfer.max_file_size', 5) * 1024 * 1024;
        $expiryDays = config('ownership_transfer.expiry_days', 90);
        
        return view('landlord.ownership-transfers.create', compact(
            'property',
            'existingLandlords',
            'unitsWithTenants',
            'relatedProperties',
            'recentTransfers',
            'hasCompletedTransfers',
            'initialDocRef',
            'digitalSignatureEnabled',
            'digitalSignatureThreshold',
            'webhookEnabled',
            'maxFileSize',
            'expiryDays',
            'bulkTransferEnabled'
        ));
    }

    /**
     * Store a new ownership transfer request
     */
    public function store(Request $request, ?Property $property = null)
    {
        $user = auth()->user();
        
        // If property is not provided, check if it's a bulk transfer
        if (!$property) {
            $propertyIds = $request->input('property_ids', []);
            if (empty($propertyIds)) {
                return redirect()->back()->with('error', 'No property selected for transfer.');
            }
            $property = Property::find($propertyIds[0]);
            if (!$property) {
                return redirect()->back()->with('error', 'Property not found.');
            }
        }
        
        // Validate ownership
        if ($user->id !== $property->landlord_id) {
            return redirect()->back()->with('error', 'You can only transfer properties you own.');
        }
        
        // Check for existing pending transfer
        $pendingTransfer = $property->currentOwnershipTransfer;
        if ($pendingTransfer && in_array($pendingTransfer->status, [
            PropertyOwnershipTransfer::STATUS_PENDING,
            PropertyOwnershipTransfer::STATUS_APPROVED
        ])) {
            return redirect()->back()->with('error', 'This property already has a pending transfer request.');
        }
        
        // Validate the request
        $validator = $this->validateTransferRequest($request, $property);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Handle file upload
        $documentPath = null;
        if ($request->hasFile('transfer_document')) {
            $documentPath = $request->file('transfer_document')->store('ownership-transfers/documents', 'public');
        }
        
        DB::beginTransaction();
        
        try {
            // Determine if using existing landlord or new details
            $isExistingLandlord = $request->has('existing_landlord_id') && !empty($request->existing_landlord_id);
            
            if ($isExistingLandlord) {
                $existingLandlord = User::find($request->existing_landlord_id);
                $newOwnerName = $existingLandlord->name;
                $newOwnerPhone = $existingLandlord->phone;
                $newOwnerEmail = $existingLandlord->email;
                $newOwnerAddress = $existingLandlord->location;
                $newLandlordId = $existingLandlord->id;
            } else {
                $newOwnerName = $request->new_owner_name;
                $newOwnerPhone = $request->new_owner_phone;
                $newOwnerEmail = $request->new_owner_email;
                $newOwnerAddress = $request->new_owner_address;
                $newLandlordId = null;
            }
            
            // Prepare transfer data
            $transferData = [
                'property_id' => $property->id,
                'current_landlord_id' => $user->id,
                'new_landlord_id' => $newLandlordId,
                'new_owner_name' => $newOwnerName,
                'new_owner_phone' => $newOwnerPhone,
                'new_owner_email' => $newOwnerEmail,
                'new_owner_address' => $newOwnerAddress,
                'transfer_date' => $request->transfer_date,
                'sale_amount' => $request->sale_amount,
                'document_type' => $request->document_type,
                'document_reference' => $request->document_reference,
                'document_url' => $documentPath,
                'reason_for_transfer' => $request->reason_for_transfer,
                'notes' => $request->notes,
                'status' => PropertyOwnershipTransfer::STATUS_PENDING,
                'requested_by_id' => $user->id,
                'metadata' => [
                    'is_existing_landlord' => $isExistingLandlord,
                    'existing_landlord_id' => $request->existing_landlord_id,
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'has_tenants' => $request->has_tenants === 'true',
                    'tenant_transfer_confirmed' => $request->has('confirm_tenant_transfer'),
                ]
            ];
            
            // Add digital signature data if applicable
            if ($request->has('apply_digital_signature') && $request->apply_digital_signature) {
                $transferData['requires_digital_signature'] = true;
                $transferData['metadata']['digital_signature'] = [
                    'token' => $request->signature_token,
                    'timestamp' => $request->signature_timestamp,
                    'applied_at' => now()->toISOString()
                ];
            }
            
            // Add webhook URL if provided
            if ($request->filled('webhook_url')) {
                $transferData['webhook_url'] = $request->webhook_url;
            }
            
            // Create the transfer
            $transfer = PropertyOwnershipTransfer::create($transferData);
            
            // Handle bulk transfer (additional properties)
            $bulkTransfer = $request->input('bulk_transfer') === 'true';
            $propertyIds = $request->input('property_ids', []);
            
            if ($bulkTransfer && count($propertyIds) > 1) {
                $transfer->is_bulk_transfer = true;
                $transfer->metadata = array_merge($transfer->metadata ?? [], [
                    'bulk_property_ids' => $propertyIds,
                    'bulk_notes' => $request->bulk_notes,
                    'bulk_transfer_date' => now()->toISOString()
                ]);
                $transfer->save();
                
                // Create additional transfers for other properties
                foreach ($propertyIds as $propertyId) {
                    if ($propertyId == $property->id) continue;
                    
                    $bulkProperty = Property::find($propertyId);
                    if ($bulkProperty && $bulkProperty->landlord_id == $user->id) {
                        $bulkTransferData = $transferData;
                        $bulkTransferData['property_id'] = $propertyId;
                        $bulkTransferData['metadata'] = array_merge($bulkTransferData['metadata'] ?? [], [
                            'parent_transfer_id' => $transfer->id,
                            'is_bulk_child' => true
                        ]);
                        
                        PropertyOwnershipTransfer::create($bulkTransferData);
                    }
                }
            }
            
            // Send notification to admin about new transfer request using OwnershipTransferRequested
            $this->notifyAdminsOfNewTransfer($transfer);
            
            // Send confirmation to current landlord
            $user->notify(new TransferStatusNotification($transfer, 'request_submitted'));
            
            DB::commit();
            
            return redirect()->route('properties.ownership-transfers.show', [$property->id, $transfer->id])
                ->with('success', 'Ownership transfer request submitted successfully. It will be reviewed by an administrator.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create ownership transfer: ' . $e->getMessage());
            
            // Clean up uploaded file if exists
            if ($documentPath && Storage::disk('public')->exists($documentPath)) {
                Storage::disk('public')->delete($documentPath);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to submit transfer request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display a specific ownership transfer - UPDATED with complete timeline including reversal events
     */
    public function show(Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        // Check if user is involved in this transfer
        if ($user->id !== $transfer->current_landlord_id && $user->id !== $transfer->new_landlord_id) {
            abort(403, 'You are not authorized to view this transfer.');
        }
        
        $transfer->load(['property', 'currentLandlord', 'newLandlord', 'approvedBy', 'rejectedBy', 'completedBy']);
        
        $isSender = $transfer->current_landlord_id === $user->id;
        $canResubmit = $isSender && $transfer->canBeResubmitted();
        $canCancel = $isSender && $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING;
        
        // ==============================================
        // UPDATED: Get timeline events with full reversal history
        // ==============================================
        $timeline = $this->getTransferTimeline($transfer);
        
        // ==============================================
        // TRANSFER REVERSAL VARIABLES
        // ==============================================
        $reversalEnabled = config('ownership_transfer.reversal.enabled', true);
        $reversalWindowDays = config('ownership_transfer.reversal.reversal_window_days', 30);
        
        // ✅ FIX: Allow reversal request after rejection or expiration
        $canRequestReversal = $reversalEnabled && 
                              $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && 
                              !$transfer->is_reversed && 
                              $transfer->reversal_status !== 'pending' &&  // Only block pending
                              $isSender;
        
        // Check if within reversal window
        if ($canRequestReversal && $transfer->completed_at) {
            $daysSinceCompletion = $transfer->completed_at->diffInDays(now());
            if ($reversalWindowDays > 0 && $daysSinceCompletion > $reversalWindowDays) {
                $canRequestReversal = false;
            }
        }
        
        // Check if reversal can be cancelled (only if pending and user is sender)
        $canCancelReversal = $transfer->reversal_status === 'pending' && $isSender;
        
        // Check if this is a resubmission after rejection
        $wasPreviouslyRejected = $transfer->reversal_status === 'rejected';
        $wasPreviouslyExpired = $transfer->reversal_status === 'expired';
        $canResubmitReversal = ($wasPreviouslyRejected || $wasPreviouslyExpired) && $isSender && !$transfer->is_reversed;
        
        // Reversal status messages
        $reversalStatusMessages = [
            'pending' => ['class' => 'badge-warning', 'icon' => 'clock', 'message' => 'Your reversal request is pending admin review.'],
            'approved' => ['class' => 'badge-success', 'icon' => 'check-circle', 'message' => 'Your reversal request has been approved. The property is being returned to you.'],
            'rejected' => ['class' => 'badge-danger', 'icon' => 'times-circle', 'message' => 'Your reversal request was rejected.'],
            'completed' => ['class' => 'badge-info', 'icon' => 'check-double', 'message' => 'The transfer has been successfully reversed.'],
            'expired' => ['class' => 'badge-secondary', 'icon' => 'clock', 'message' => 'Your reversal request expired before admin review.'],
        ];
        $reversalStatusInfo = $reversalStatusMessages[$transfer->reversal_status] ?? null;
        
        // Get related transfers (bulk transfer siblings)
        $relatedTransfers = collect();
        if ($transfer->is_bulk_transfer && isset($transfer->metadata['bulk_property_ids'])) {
            $relatedTransfers = PropertyOwnershipTransfer::whereIn('property_id', $transfer->metadata['bulk_property_ids'])
                ->where('id', '!=', $transfer->id)
                ->with('property')
                ->get();
        }
        
        return view('landlord.ownership-transfers.show', compact(
            'transfer',
            'isSender',
            'canResubmit',
            'canCancel',
            'timeline',
            'relatedTransfers',
            'canRequestReversal',
            'canCancelReversal',
            'canResubmitReversal',
            'reversalStatusInfo',
            'reversalWindowDays',
            'wasPreviouslyRejected',
            'wasPreviouslyExpired'
        ));
    }

    /**
     * Cancel a pending ownership transfer
     */
    public function cancel(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        // Only the sender can cancel
        if ($user->id !== $transfer->current_landlord_id) {
            return redirect()->back()->with('error', 'You cannot cancel this transfer request.');
        }
        
        // Only pending transfers can be cancelled
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_PENDING) {
            return redirect()->back()->with('error', 'This transfer request cannot be cancelled.');
        }
        
        DB::beginTransaction();
        
        try {
            $transfer->update([
                'status' => PropertyOwnershipTransfer::STATUS_CANCELLED,
                'metadata' => array_merge($transfer->metadata ?? [], [
                    'cancelled_at' => now()->toISOString(),
                    'cancelled_by' => $user->id,
                    'cancelled_by_name' => $user->name,
                    'cancellation_reason' => $request->notes,
                ])
            ]);
            
            // Notify admin about cancellation
            $this->notifyAdminsOfCancellation($transfer);
            
            DB::commit();
            
            return redirect()->route('landlord.ownership-transfers.index')
                ->with('success', 'Transfer request cancelled successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel transfer: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to cancel transfer request: ' . $e->getMessage());
        }
    }

    /**
     * Download transfer document
     */
    public function downloadDocument(Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        // Check authorization
        if (!$user->isAdmin() && $user->id !== $transfer->current_landlord_id && $user->id !== $transfer->new_landlord_id) {
            abort(403);
        }
        
        if (!$transfer->document_url) {
            abort(404, 'Document not found.');
        }
        
        $path = storage_path('app/public/' . $transfer->document_url);
        
        if (!file_exists($path)) {
            abort(404, 'Document file not found.');
        }
        
        $filename = "transfer_document_{$transfer->document_reference}.pdf";
        
        return response()->download($path, $filename);
    }

    /**
     * Download transfer certificate (landlord version)
     */
    public function downloadCertificate(Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        // Check authorization
        if (!$user->isAdmin() && $user->id !== $transfer->current_landlord_id && $user->id !== $transfer->new_landlord_id) {
            abort(403);
        }
        
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_COMPLETED) {
            abort(404, 'Transfer certificate not available.');
        }
        
        // Try to find certificate
        $certificatePath = $transfer->certificate_url;
        
        if (!$certificatePath || !Storage::exists("public/{$certificatePath}")) {
            $certificatePath = "ownership-transfers/certificates/{$transfer->id}.pdf";
        }
        
        if (!Storage::exists("public/{$certificatePath}")) {
            abort(404, 'Certificate not found.');
        }
        
        $filename = "ownership_transfer_certificate_{$transfer->id}.pdf";
        $path = storage_path('app/public/' . $certificatePath);
        
        return response()->download($path, $filename);
    }

    /**
     * Display all ownership transfers for the current landlord
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $transfers = PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
            ->orWhere('new_landlord_id', $user->id)
            ->with(['property', 'currentLandlord', 'newLandlord'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));
        
        $transfers->getCollection()->transform(function($transfer) use ($user) {
            $isSender = $transfer->current_landlord_id === $user->id;
            $transfer->can_resubmit = $isSender && $transfer->canBeResubmitted();
            return $transfer;
        });
        
        return view('landlord.ownership-transfers.index', compact('transfers'));
    }

    /**
     * Get landlord's ownership transfer history
     */
    public function history(Request $request)
    {
        $user = auth()->user();
        
        if ($request->has('export') || $request->has('format')) {
            return $this->exportHistory($request);
        }
        
        $cacheKey = "landlord_history_{$user->id}_" . md5(serialize($request->except(['page', '_token'])));
        
        $transfers = Cache::remember($cacheKey, 1800, function() use ($user, $request) {
            $query = PropertyOwnershipTransfer::where(function($q) use ($user) {
                    $q->where('current_landlord_id', $user->id)
                      ->orWhere('new_landlord_id', $user->id);
                })
                ->with(['property', 'currentLandlord', 'newLandlord'])
                ->orderBy('updated_at', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('year')) {
                $query->whereYear('created_at', $request->year);
            }
            
            if ($request->filled('transfer_type')) {
                if ($request->transfer_type == 'sent') {
                    $query->where('current_landlord_id', $user->id);
                } elseif ($request->transfer_type == 'received') {
                    $query->where('new_landlord_id', $user->id);
                }
            }
            
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('document_reference', 'LIKE', "%{$search}%")
                      ->orWhere('new_owner_name', 'LIKE', "%{$search}%")
                      ->orWhereHas('property', function($prop) use ($search) {
                          $prop->where('property_name', 'LIKE', "%{$search}%");
                      });
                });
            }

            return $query->paginate($request->get('per_page', 15));
        });
        
        $transfers->getCollection()->transform(function($transfer) use ($user) {
            $isSender = $transfer->current_landlord_id === $user->id;
            $transfer->can_resubmit = $isSender && $transfer->canBeResubmitted();
            return $transfer;
        });

        $stats = $this->getLandlordStatistics($user);
        
        $availableYears = PropertyOwnershipTransfer::where(function($q) use ($user) {
                $q->where('current_landlord_id', $user->id)
                  ->orWhere('new_landlord_id', $user->id);
            })
            ->selectRaw('DISTINCT YEAR(created_at) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return view('landlord.ownership-transfers.history', compact('transfers', 'stats', 'availableYears'));
    }

    /**
     * Display completed transfers
     */
    public function completed(Request $request)
    {
        $user = auth()->user();
        
        $query = PropertyOwnershipTransfer::where(function($q) use ($user) {
                $q->where('current_landlord_id', $user->id)
                  ->orWhere('new_landlord_id', $user->id);
            })
            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->with(['property' => function($q) {
                $q->withTrashed();
            }, 'currentLandlord', 'newLandlord']);
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('property', function($prop) use ($search) {
                    $prop->where('property_name', 'LIKE', "%{$search}%");
                })->orWhere('new_owner_name', 'LIKE', "%{$search}%");
            });
        }
        
        if ($request->filled('transfer_type')) {
            if ($request->transfer_type == 'sent') {
                $query->where('current_landlord_id', $user->id);
            } elseif ($request->transfer_type == 'received') {
                $query->where('new_landlord_id', $user->id);
            }
        }
        
        if ($request->filled('start_date')) {
            $query->whereDate('completed_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('completed_at', '<=', $request->end_date);
        }
        
        $sort = $request->get('sort', 'completed_at');
        $direction = $request->get('direction', 'desc');
        $allowedSorts = ['completed_at', 'updated_at', 'transfer_date', 'created_at'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('completed_at', 'desc');
        }
        
        $transfers = $query->paginate($request->get('per_page', 10));
        
        $transferredFromMe = PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->count();
            
        $transferredToMe = PropertyOwnershipTransfer::where('new_landlord_id', $user->id)
            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->count();
        
        $currentYear = date('Y');
        $completedThisYear = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->where(function($q) use ($user) {
                $q->where('current_landlord_id', $user->id)
                  ->orWhere('new_landlord_id', $user->id);
            })
            ->whereYear('completed_at', $currentYear)
            ->count();
        
        $totalValue = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->where(function($q) use ($user) {
                $q->where('current_landlord_id', $user->id)
                  ->orWhere('new_landlord_id', $user->id);
            })
            ->sum('sale_amount');
        
        return view('landlord.ownership-transfers.completed', compact(
            'transfers', 
            'totalValue',
            'transferredFromMe',
            'transferredToMe',
            'completedThisYear',
            'currentYear'
        ));
    }

    /**
     * Get landlord dashboard
     */
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        
        $stats = [
            'pending' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                        ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)
                        ->count(),
            'approved' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                        ->where('status', PropertyOwnershipTransfer::STATUS_APPROVED)
                        ->count(),
            'completed' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                        ->count(),
            'total_value' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                        ->sum('sale_amount'),
        ];

        $recentActivity = PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
            ->orWhere('new_landlord_id', $user->id)
            ->with('property')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        return view('landlord.ownership-transfers.dashboard', compact('stats', 'recentActivity'));
    }

    /**
     * Export landlord history as CSV
     */
    public function exportHistory(Request $request)
    {
        $user = auth()->user();
        
        $query = PropertyOwnershipTransfer::where(function($q) use ($user) {
            $q->where('current_landlord_id', $user->id)
              ->orWhere('new_landlord_id', $user->id);
        })->with(['property', 'currentLandlord', 'newLandlord']);
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        }
        
        if ($request->filled('transfer_type')) {
            if ($request->transfer_type == 'sent') {
                $query->where('current_landlord_id', $user->id);
            } elseif ($request->transfer_type == 'received') {
                $query->where('new_landlord_id', $user->id);
            }
        }
        
        $transfers = $query->orderBy('created_at', 'desc')->get();
        
        $filename = "my_transfers_" . date('Y-m-d_His') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($transfers, $user) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Transfer ID', 'Document Reference', 'Property Name', 'Transfer Type',
                'Other Party', 'Transfer Date', 'Sale Amount', 'Status', 'Requested Date', 'Completed Date',
                'Reversal Status', 'Is Reversed'
            ]);
            
            foreach ($transfers as $transfer) {
                $isSent = $transfer->current_landlord_id == $user->id;
                $otherParty = $isSent ? ($transfer->newLandlord->name ?? $transfer->new_owner_name) : ($transfer->currentLandlord->name ?? 'N/A');
                
                fputcsv($file, [
                    $transfer->id,
                    $transfer->document_reference,
                    $transfer->property->property_name ?? 'N/A',
                    $isSent ? 'Sent' : 'Received',
                    $otherParty,
                    $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : 'N/A',
                    $transfer->sale_amount ? number_format($transfer->sale_amount, 2) : 'N/A',
                    $transfer->status_label,
                    $transfer->created_at->format('Y-m-d H:i:s'),
                    $transfer->completed_at ? $transfer->completed_at->format('Y-m-d H:i:s') : 'N/A',
                    $transfer->reversal_status ?? 'None',
                    $transfer->is_reversed ? 'Yes' : 'No',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Validate transfer request
     */
    private function validateTransferRequest(Request $request, Property $property)
    {
        $rules = [
            'transfer_date' => 'required|date|after_or_equal:today|before_or_equal:+1 year',
            'document_type' => 'required|string|max:50',
            'document_reference' => 'required|string|max:100|unique:property_ownership_transfers,document_reference',
            'transfer_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . (config('ownership_transfer.max_file_size', 5) * 1024),
            'reason_for_transfer' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'accepted',
        ];
        
        // Add conditional validation based on owner type
        if ($request->has('existing_landlord_id') && !empty($request->existing_landlord_id)) {
            $rules['existing_landlord_id'] = 'exists:users,id';
        } else {
            $rules['new_owner_name'] = 'required|string|max:255';
            $rules['new_owner_phone'] = 'required|string|max:20';
            $rules['new_owner_email'] = 'nullable|email|max:255';
            $rules['new_owner_address'] = 'nullable|string|max:500';
        }
        
        // Add sale amount validation if provided
        if ($request->filled('sale_amount')) {
            $rules['sale_amount'] = 'numeric|min:0|max:999999999.99';
        }
        
        // Add tenant confirmation if property has tenants
        $hasTenants = PropertyUnit::where('property_id', $property->id)->whereNotNull('tenant_id')->exists();
        if ($hasTenants) {
            $rules['confirm_tenant_transfer'] = 'accepted';
        }
        
        return Validator::make($request->all(), $rules);
    }

    /**
     * Get transfer timeline events - UPDATED with full reversal events
     */
    private function getTransferTimeline(PropertyOwnershipTransfer $transfer)
    {
        $timeline = [];
        
        // 1. Request created
        $timeline[] = [
            'event' => 'Transfer Request Submitted',
            'date' => $transfer->created_at,
            'description' => 'Transfer request was submitted for review.',
            'icon' => 'paper-plane',
            'color' => 'primary'
        ];
        
        // 2. Approved
        if ($transfer->approved_at) {
            $timeline[] = [
                'event' => 'Transfer Approved',
                'date' => $transfer->approved_at,
                'description' => 'Transfer request was approved by administrator.' . ($transfer->admin_notes ? ' Notes: ' . $transfer->admin_notes : ''),
                'icon' => 'check-circle',
                'color' => 'success'
            ];
        }
        
        // 3. Rejected
        if ($transfer->rejected_at) {
            $timeline[] = [
                'event' => 'Transfer Rejected',
                'date' => $transfer->rejected_at,
                'description' => 'Transfer request was rejected. Reason: ' . ($transfer->rejection_reason ?? 'Not provided'),
                'icon' => 'times-circle',
                'color' => 'danger'
            ];
        }
        
        // 4. Completed
        if ($transfer->completed_at && !$transfer->is_reversed) {
            $timeline[] = [
                'event' => 'Transfer Completed',
                'date' => $transfer->completed_at,
                'description' => 'Property ownership transfer has been completed successfully.',
                'icon' => 'check-double',
                'color' => 'success'
            ];
        }
        
        // 5. Cancelled
        if ($transfer->status === PropertyOwnershipTransfer::STATUS_CANCELLED && isset($transfer->metadata['cancelled_at'])) {
            $timeline[] = [
                'event' => 'Transfer Cancelled',
                'date' => $transfer->metadata['cancelled_at'],
                'description' => 'Transfer request was cancelled by the sender.',
                'icon' => 'ban',
                'color' => 'secondary'
            ];
        }
        
        // ==============================================
        // REVERSAL EVENTS (NEW - FIXED)
        // ==============================================
        
        // 6. Reversal Requested
        if ($transfer->reversal_requested_at) {
            $timeline[] = [
                'event' => 'Reversal Requested',
                'date' => $transfer->reversal_requested_at,
                'description' => 'You requested a reversal of this transfer. Reason: ' . ($transfer->reversal_reason ?? 'Not provided'),
                'icon' => 'undo-alt',
                'color' => 'warning'
            ];
        }
        
        // 7. Reversal Approved
        if ($transfer->reversal_status === 'approved' && $transfer->reversal_processed_at) {
            $timeline[] = [
                'event' => 'Reversal Approved',
                'date' => $transfer->reversal_processed_at,
                'description' => 'Your reversal request was approved by an administrator.',
                'icon' => 'check-circle',
                'color' => 'success'
            ];
        }
        
        // 8. Reversal Rejected
        if ($transfer->reversal_status === 'rejected' && $transfer->reversal_processed_at) {
            $timeline[] = [
                'event' => 'Reversal Rejected',
                'date' => $transfer->reversal_processed_at,
                'description' => 'Your reversal request was rejected. Reason: ' . ($transfer->reversal_admin_notes ?? 'Not provided'),
                'icon' => 'times-circle',
                'color' => 'danger'
            ];
        }
        
        // 9. Reversal Expired
        if ($transfer->reversal_status === 'expired') {
            $expiredDate = $transfer->reversal_processed_at ?? $transfer->updated_at;
            $timeline[] = [
                'event' => 'Reversal Request Expired',
                'date' => $expiredDate,
                'description' => 'Your reversal request expired before admin review.',
                'icon' => 'clock',
                'color' => 'warning'
            ];
        }
        
        // 10. Reversal Completed (executed)
        if ($transfer->is_reversed && $transfer->reversal_status === 'completed') {
            $completionDate = $transfer->reversal_processed_at ?? $transfer->updated_at;
            $timeline[] = [
                'event' => 'Reversal Completed',
                'date' => $completionDate,
                'description' => 'The transfer has been successfully reversed. Property ownership has been restored to you.',
                'icon' => 'check-double',
                'color' => 'success'
            ];
        }
        
        return collect($timeline)->sortBy('date')->values();
    }

    /**
     * Notify admins about new transfer request using OwnershipTransferRequested notification
     */
    private function notifyAdminsOfNewTransfer(PropertyOwnershipTransfer $transfer)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
        
        foreach ($admins as $admin) {
            try {
                // Using the dedicated OwnershipTransferRequested notification
                $admin->notify(new \App\Notifications\OwnershipTransferRequested($transfer));
            } catch (\Exception $e) {
                Log::warning('Failed to notify admin about new transfer: ' . $e->getMessage());
            }
        }
    }

    /**
     * Notify admins about cancelled transfer
     */
    private function notifyAdminsOfCancellation(PropertyOwnershipTransfer $transfer)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
        
        foreach ($admins as $admin) {
            try {
                $admin->notify(new TransferStatusNotification($transfer, 'cancelled'));
            } catch (\Exception $e) {
                Log::warning('Failed to notify admin about cancelled transfer: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get landlord statistics
     */
    private function getLandlordStatistics(User $user)
    {
        return [
            'total' => PropertyOwnershipTransfer::where(function($q) use ($user) {
                            $q->where('current_landlord_id', $user->id)
                              ->orWhere('new_landlord_id', $user->id);
                        })->count(),
            'pending' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)
                            ->count(),
            'approved' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_APPROVED)
                            ->count(),
            'completed_sent' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                            ->count(),
            'completed_received' => PropertyOwnershipTransfer::where('new_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                            ->count(),
            'rejected' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
                            ->count(),
            'total_value_sent' => PropertyOwnershipTransfer::where('current_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                            ->sum('sale_amount'),
            'total_value_received' => PropertyOwnershipTransfer::where('new_landlord_id', $user->id)
                            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                            ->sum('sale_amount'),
        ];
    }

    /**
     * Request reversal of a completed transfer - UPDATED to allow resubmission after rejection
     */
    public function requestReversal(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        // Authorization checks
        if ($user->id !== $transfer->current_landlord_id) {
            return redirect()->back()->with('error', 'Only the original property owner can request a reversal.');
        }
        
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_COMPLETED) {
            return redirect()->back()->with('error', 'Only completed transfers can be reversed.');
        }
        
        if ($transfer->is_reversed) {
            return redirect()->back()->with('error', 'This transfer has already been reversed.');
        }
        
        // ✅ FIX: Allow new request if previous was rejected or expired
        // Only block if currently pending (waiting for review)
        if ($transfer->reversal_status === 'pending') {
            return redirect()->back()->with('error', 'A reversal request is already pending for this transfer.');
        }
        
        // Check reversal window
        $reversalWindowDays = config('ownership_transfer.reversal.reversal_window_days', 30);
        if ($reversalWindowDays > 0 && $transfer->completed_at) {
            $daysSinceCompletion = $transfer->completed_at->diffInDays(now());
            if ($daysSinceCompletion > $reversalWindowDays) {
                return redirect()->back()->with('error', "The reversal window of {$reversalWindowDays} days has passed. You cannot request a reversal at this time.");
            }
        }
        
        $validator = Validator::make($request->all(), [
            'reversal_reason' => 'required|string|min:10|max:1000',
            'confirm_reversal' => 'required|accepted'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        DB::beginTransaction();
        
        try {
            $isResubmission = in_array($transfer->reversal_status, ['rejected', 'expired']);
            $reversalDeadline = now()->addDays(config('ownership_transfer.reversal.review_deadline_days', 7));
            
            // Prepare metadata with history of previous attempts
            $metadata = $transfer->metadata ?? [];
            $reversalAttempts = $metadata['reversal_attempts'] ?? [];
            
            if ($isResubmission) {
                // Record previous attempt
                $reversalAttempts[] = [
                    'attempt_date' => $transfer->reversal_requested_at ? $transfer->reversal_requested_at->toISOString() : null,
                    'status' => $transfer->reversal_status,
                    'reason' => $transfer->reversal_reason,
                    'admin_notes' => $transfer->reversal_admin_notes,
                    'processed_at' => $transfer->reversal_processed_at ? $transfer->reversal_processed_at->toISOString() : null,
                ];
            }
            
            $transfer->update([
                'reversal_requested_at' => now(),
                'reversal_requested_by' => $user->id,
                'reversal_reason' => $request->reversal_reason,
                'reversal_status' => 'pending',
                'reversal_deadline' => $reversalDeadline,
                'reversal_processed_at' => null,
                'reversal_processed_by' => null,
                'reversal_admin_notes' => null,
                'metadata' => array_merge($metadata, [
                    'reversal_attempts' => $reversalAttempts,
                    'last_reversal_request' => [
                        'requested_at' => now()->toISOString(),
                        'reason' => $request->reversal_reason,
                        'attempt_number' => count($reversalAttempts) + 1,
                        'is_resubmission' => $isResubmission,
                    ],
                    'reversal_request' => [
                        'requested_at' => now()->toISOString(),
                        'requested_by' => $user->id,
                        'requested_by_name' => $user->name,
                        'reason' => $request->reversal_reason,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]
                ])
            ]);
            
            // ==============================================
            // SEND NOTIFICATIONS USING TransferReversalRequested
            // ==============================================
            
            // Notify all admins about the reversal request
            $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new TransferReversalRequested($transfer, $user));
                } catch (\Exception $e) {
                    Log::warning('Failed to notify admin about reversal request: ' . $e->getMessage());
                }
            }
            
            // Notify the new owner (receiver) that a reversal has been requested
            if ($transfer->newLandlord) {
                try {
                    $transfer->newLandlord->notify(new TransferReversalRequested($transfer, $user, 'new_owner'));
                } catch (\Exception $e) {
                    Log::warning('Failed to notify new owner about reversal request: ' . $e->getMessage());
                }
            }
            
            // Notify the requesting landlord (confirmation)
            $user->notify(new TransferReversalRequested($transfer, $user, 'requester'));
            
            DB::commit();
            
            $message = $isResubmission 
                ? 'Your reversal request has been resubmitted successfully. An administrator will review your request within 48 hours.'
                : 'Reversal request submitted successfully. An administrator will review your request within 48 hours.';
            
            return redirect()->route('properties.ownership-transfers.show', [$property->id, $transfer->id])
                ->with('success', $message)
                ->with('reversal_requested', true);
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to request reversal: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to submit reversal request. Please try again or contact support.')
                ->withInput();
        }
    }

    /**
     * Cancel a pending reversal request
     */
    public function cancelReversalRequest(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        if ($user->id !== $transfer->current_landlord_id) {
            return redirect()->back()->with('error', 'Only the original property owner can cancel a reversal request.');
        }
        
        if ($transfer->reversal_status !== 'pending') {
            return redirect()->back()->with('error', 'No pending reversal request found.');
        }
        
        DB::beginTransaction();
        
        try {
            $transfer->update([
                'reversal_requested_at' => null,
                'reversal_requested_by' => null,
                'reversal_reason' => null,
                'reversal_status' => null,
                'reversal_deadline' => null,
                'metadata' => array_merge($transfer->metadata ?? [], [
                    'reversal_request_cancelled' => [
                        'cancelled_at' => now()->toISOString(),
                        'cancelled_by' => $user->id,
                        'cancelled_by_name' => $user->name
                    ]
                ])
            ]);
            
            // Notify admins that the reversal request was cancelled
            $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new TransferStatusNotification($transfer, 'reversal_cancelled'));
                } catch (\Exception $e) {
                    Log::warning('Failed to notify admin about cancellation: ' . $e->getMessage());
                }
            }
            
            DB::commit();
            
            return redirect()->route('properties.ownership-transfers.show', [$property->id, $transfer->id])
                ->with('success', 'Reversal request cancelled successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel reversal request: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cancel reversal request.');
        }
    }

    /**
 * Get the transfer form HTML for AJAX request
 * Returns only the form partial, not the full layout
 * 
 * GET /properties/{property}/ownership-transfer/form
 */
public function getForm(Property $property)
{
    $user = auth()->user();
    
    // Check if user owns this property
    if ($user->id !== $property->landlord_id) {
        return response()->json([
            'success' => false,
            'message' => 'You can only transfer properties you own.'
        ], 403);
    }
    
    // Check if there's already a pending transfer
    $pendingTransfer = $property->currentOwnershipTransfer;
    if ($pendingTransfer && in_array($pendingTransfer->status, [
        PropertyOwnershipTransfer::STATUS_PENDING,
        PropertyOwnershipTransfer::STATUS_APPROVED
    ])) {
        return response()->json([
            'success' => false,
            'message' => 'This property already has a pending or approved transfer request.'
        ], 400);
    }
    
    // Get existing landlords for dropdown (active landlords except current user)
    $existingLandlords = User::where('type', User::TYPE_LANDLORD)
        ->where('id', '!=', $user->id)
        ->where('status', User::STATUS_ACTIVE)
        ->orderBy('name')
        ->get(['id', 'name', 'email', 'phone']);
    
    // Get units for tenant warning
    $units = $property->units;
    $unitsWithTenants = $units->filter(function($unit) {
        return !is_null($unit->tenant_id);
    });
    
    // Generate initial document reference
    $initialDocRef = 'TRANS-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Get config values
    $digitalSignatureEnabled = config('ownership_transfer.require_digital_signature', false);
    $digitalSignatureThreshold = config('ownership_transfer.digital_signature_threshold', 1000000);
    $maxFileSize = config('ownership_transfer.max_file_size', 5) * 1024 * 1024;
    $expiryDays = config('ownership_transfer.expiry_days', 90);
    
    // Return the form partial only (no layout)
    return view('landlord.ownership-transfers._form_ajax', compact(
        'property',
        'existingLandlords',
        'unitsWithTenants',
        'initialDocRef',
        'digitalSignatureEnabled',
        'digitalSignatureThreshold',
        'maxFileSize',
        'expiryDays'
    ));
}

}