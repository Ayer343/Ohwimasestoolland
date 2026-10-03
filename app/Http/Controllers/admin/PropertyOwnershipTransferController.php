<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use App\Models\PropertyUnit;
use App\Services\OwnershipTransferService;
use App\Services\MultiChannelInvitationService;
use App\Services\DigitalSignatureService;
use App\Services\WebhookService;
use App\Notifications\TransferStatusNotification;
use App\Notifications\OwnershipTransferApproved;
use App\Notifications\OwnershipTransferRejected;
use App\Notifications\OwnershipTransferCompleted;
use App\Notifications\TransferReversalRequested;
use App\Notifications\TransferReversalApproved;
use App\Notifications\TransferReversalRejected;
use App\Notifications\TransferReversalCompleted;
use App\Notifications\TransferReversalExpired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ActivityLog;

class PropertyOwnershipTransferController extends Controller
{
    protected $transferService;
    protected $invitationService;
    protected $digitalSignatureService;
    protected $webhookService;

    public function __construct(
        OwnershipTransferService $transferService,
        MultiChannelInvitationService $invitationService,
        DigitalSignatureService $digitalSignatureService = null,
        WebhookService $webhookService = null
    ) {
        $this->transferService = $transferService;
        $this->invitationService = $invitationService;
        $this->digitalSignatureService = $digitalSignatureService;
        $this->webhookService = $webhookService;
    }

    /**
     * List all ownership transfer requests
     */
    public function index(Request $request)
    {
        $this->authorizeAdminAccess($request);

        $query = $this->buildTransferQuery($request);
        
        if ($request->filled('search')) {
            $query = $this->applyFullTextSearchToQuery($query, $request->search);
        }
        
        $cacheKey = 'ownership_transfers_' . md5(serialize($request->all()));
        $this->storeCacheKey($cacheKey);
        
        $transfers = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 20));
        });

        $stats = Cache::remember('ownership_transfer_stats', 60, function() {
            return $this->getTransferStatistics();
        });
        
        $trashedCount = PropertyOwnershipTransfer::onlyTrashed()->count();
        $filterOptions = $this->getFilterOptions();

        if ($request->has('export')) {
            $exportQuery = $this->buildTransferQuery($request);
            if ($request->filled('search')) {
                $exportQuery = $this->applyFullTextSearchToQuery($exportQuery, $request->search);
            }
            $allTransfers = $exportQuery->get();
            return $this->handleExport($request, $allTransfers);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'transfers' => $transfers,
                'stats' => $stats,
                'trashed_count' => $trashedCount,
                'filter_options' => $filterOptions,
                'export_formats' => ['csv', 'pdf', 'excel'],
            ]);
        }

        return view('admin.ownership-transfers.index', compact('transfers', 'stats', 'filterOptions', 'trashedCount'));
    }

    /**
     * Approve ownership transfer
     */
    public function approve(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return $this->unauthorizedResponse($request, 'Only administrators can approve ownership transfers.');
        }

        if (!$this->canApproveTransfer($transfer, $user)) {
            return $this->errorResponse($request, 'You cannot approve this transfer. Approval rules not met.');
        }

        if (!$transfer->canBeApproved()) {
            return $this->errorResponse($request, 'This transfer request cannot be approved.');
        }

        DB::beginTransaction();

        try {
            $transfer->update([
                'status' => PropertyOwnershipTransfer::STATUS_APPROVED,
                'admin_approved_by_id' => auth()->id(),
                'admin_notes' => $request->admin_notes,
                'approved_at' => now(),
                'approval_workflow_step' => $this->getNextWorkflowStep($transfer)
            ]);

            $this->triggerWebhooks('transfer.approved', $transfer);
            $invitationResult = $this->sendOwnershipTransferInvitation($transfer);
            
            // SEND APPROVAL NOTIFICATIONS
            if ($transfer->currentLandlord) {
                $transfer->currentLandlord->notify(new OwnershipTransferApproved($transfer, 'current_landlord'));
            }
            
            if ($transfer->newLandlord) {
                $transfer->newLandlord->notify(new OwnershipTransferApproved($transfer, 'new_landlord'));
            }
            
            if ($transfer->requested_by_id && $transfer->requested_by_id != $transfer->current_landlord_id) {
                $requester = User::find($transfer->requested_by_id);
                if ($requester) {
                    $requester->notify(new OwnershipTransferApproved($transfer, 'current_landlord'));
                }
            }
            
            $autoCompleted = false;
            if (config('ownership_transfer.auto_complete_on_approval', false)) {
                try {
                    $this->completeTransferAfterApproval($transfer, $request);
                    $autoCompleted = true;
                } catch (\Exception $e) {
                    \Log::warning('Auto-completion failed for transfer ID: ' . $transfer->id);
                }
            }
            
            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $autoCompleted ? 'Ownership transfer approved and completed successfully.' : 'Ownership transfer approved successfully.',
                    'transfer' => $transfer->fresh(),
                    'invitation_result' => $invitationResult,
                    'auto_completed' => $autoCompleted
                ]);
            }

            $message = 'Ownership transfer approved successfully.';
            if ($autoCompleted) {
                $message .= ' Transfer has been automatically completed.';
            }

            return redirect()->back()
                ->with('success', $message)
                ->with('invitation_sent', $invitationResult['success'] ?? false);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to approve ownership transfer: ' . $e->getMessage());
        }
    }

    /**
     * Reject ownership transfer
     */
    public function reject(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return $this->unauthorizedResponse($request, 'Only administrators can reject ownership transfers.');
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
            'allow_resubmission' => 'boolean',
            'resubmission_days' => 'nullable|integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        if (!$transfer->canBeRejected()) {
            return $this->errorResponse($request, 'This transfer request cannot be rejected.');
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'status' => PropertyOwnershipTransfer::STATUS_REJECTED,
                'rejection_reason' => $request->rejection_reason,
                'admin_notes' => $request->admin_notes,
                'rejected_at' => now(),
                'rejected_by_id' => auth()->id()
            ];

            if ($request->has('allow_resubmission') && $request->boolean('allow_resubmission')) {
                $days = (int) ($request->resubmission_days ?? 7);
                $updateData['can_resubmit_after'] = now()->addDays($days);
            }

            $transfer->update($updateData);
            $this->triggerWebhooks('transfer.rejected', $transfer);
            
            // SEND REJECTION NOTIFICATION
            if ($transfer->currentLandlord) {
                $transfer->currentLandlord->notify(new OwnershipTransferRejected($transfer));
            }
            
            if ($transfer->requested_by_id && $transfer->requested_by_id != $transfer->current_landlord_id) {
                $requester = User::find($transfer->requested_by_id);
                if ($requester) {
                    $requester->notify(new OwnershipTransferRejected($transfer));
                }
            }
            
            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ownership transfer rejected successfully.',
                    'transfer' => $transfer->fresh(),
                    'can_resubmit' => isset($updateData['can_resubmit_after'])
                ]);
            }

            return redirect()->back()
                ->with('success', 'Ownership transfer rejected successfully.')
                ->with('resubmission_allowed', isset($updateData['can_resubmit_after']));

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to reject ownership transfer: ' . $e->getMessage());
        }
    }

    /**
     * Complete ownership transfer - Transfer property and all units
     */
    public function complete(Request $request, Property $property, PropertyOwnershipTransfer $transfer)
    {
        if (!auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Only administrators can complete ownership transfers.');
        }

        if (!$transfer->canBeCompleted()) {
            return $this->errorResponse($request, 'This transfer cannot be completed.');
        }

        $readinessReport = $this->getTransferReadinessReport($property, $transfer);
        
        if ($readinessReport['requires_confirmation'] && !$request->has('confirm_tenant_transfer')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => 'This property has active tenants. They will be transferred to the new landlord.',
                    'readiness_report' => $readinessReport
                ], 409);
            }
            
            return redirect()->back()
                ->with('warning', 'This property has active tenants that will transfer to the new owner.')
                ->with('readiness_report', $readinessReport)
                ->with('requires_confirmation', true);
        }

        DB::beginTransaction();

        try {
            $oldLandlordId = $property->landlord_id;
            $oldLandlord = User::find($oldLandlordId);
            $newLandlordId = $transfer->new_landlord_id;
            $newLandlord = User::find($newLandlordId);
            
            if (!$newLandlord || !$oldLandlord) {
                throw new \Exception('Landlord not found.');
            }

            // Transfer property
            $property->update([
                'landlord_id' => $newLandlordId,
                'previous_landlord_id' => $oldLandlordId,
                'ownership_transferred_at' => now(),
                'ownership_transfer_id' => $transfer->id,
                'metadata' => array_merge($property->metadata ?? [], [
                    'last_transfer' => [
                        'from_landlord_id' => $oldLandlordId,
                        'from_landlord_name' => $transfer->currentLandlord->name,
                        'to_landlord_id' => $newLandlordId,
                        'to_landlord_name' => $newLandlord->name,
                        'transfer_date' => now()->toISOString(),
                        'transfer_id' => $transfer->id,
                        'document_reference' => $transfer->document_reference,
                        'tenants_transferred' => $readinessReport['units_with_tenants']
                    ]
                ])
            ]);

            // Transfer property units
            $unitsTransferResult = $this->transferPropertyUnits($property, $oldLandlordId, $newLandlordId, $transfer);

            // Update tenant relationships
            $tenantsAffected = $this->updateTenantRelationships($property, $oldLandlordId, $newLandlordId, $transfer);

            // Update rental agreements
            $agreementsUpdated = $this->updateRentalAgreements($property, $oldLandlordId, $newLandlordId, $transfer);

            // Update transfer record
            $transfer->update([
                'status' => PropertyOwnershipTransfer::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by_id' => auth()->id(),
                'metadata' => array_merge($transfer->metadata ?? [], [
                    'units_transferred' => $unitsTransferResult['units_transferred'] ?? 0,
                    'tenants_affected' => $tenantsAffected,
                    'agreements_updated' => $agreementsUpdated,
                ])
            ]);

            // Update ownership history
            $this->transferService->updatePropertyOwnershipHistory($property, $transfer);
            
            // Update old landlord's last property ownership timestamp
            $oldLandlord->update(['last_property_ownership' => now()]);
            
            // Check and archive old landlord
            $archivalResult = $this->checkAndArchiveOldLandlord($oldLandlord, $transfer);
            
            // Trigger webhooks
            $this->triggerWebhooks('transfer.completed', $transfer);
            
            // Generate transfer certificate
            $certificatePath = $this->generateTransferCertificate($transfer);
            
            // SEND COMPLETION NOTIFICATIONS
            if ($transfer->currentLandlord) {
                $transfer->currentLandlord->notify(new OwnershipTransferCompleted($transfer, 'previous_owner', $certificatePath));
            }
            
            if ($transfer->newLandlord) {
                $transfer->newLandlord->notify(new OwnershipTransferCompleted($transfer, 'new_owner', $certificatePath));
            }
            
            if ($transfer->requested_by_id && $transfer->requested_by_id != $transfer->current_landlord_id) {
                $requester = User::find($transfer->requested_by_id);
                if ($requester) {
                    $requester->notify(new OwnershipTransferCompleted($transfer, 'previous_owner', $certificatePath));
                }
            }
            
            // Clear caches
            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();
            $this->clearPropertyUnitCaches($property->id);

            DB::commit();

            $successMessage = "Property ownership transferred successfully! ";
            $successMessage .= "{$unitsTransferResult['units_transferred']} unit(s) transferred. ";
            
            if ($readinessReport['units_with_tenants'] > 0) {
                $successMessage .= "{$readinessReport['units_with_tenants']} tenant(s) have been transferred. ";
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'property' => $property->fresh()->load('landlord'),
                    'transfer' => $transfer->fresh(),
                    'units_transferred' => $unitsTransferResult,
                    'archival_result' => $archivalResult,
                    'certificate_url' => $certificatePath ? Storage::url($certificatePath) : null,
                ]);
            }

            return redirect()->route('properties.show', $property->id)
                ->with('success', $successMessage)
                ->with('certificate_available', $certificatePath !== null);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to complete ownership transfer: ' . $e->getMessage());
        }
    }

    /**
     * Display a specific ownership transfer (Admin view)
     */
    public function show(Property $property, PropertyOwnershipTransfer $transfer, Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer->load([
            'property',
            'currentLandlord',
            'newLandlord',
            'requestedBy',
            'approvedBy',
            'rejectedBy',
            'completedBy',
            'originalTransfer',
            'reversalTransfer',
            'reversalRequestedBy',
            'reversalProcessedBy'
        ]);
        
        // Get units with tenants for readiness report
        $units = PropertyUnit::where('property_id', $transfer->property_id)->get();
        $unitsWithTenants = $units->filter(function($unit) {
            return !is_null($unit->tenant_id);
        });
        
        // Get related transfers (bulk transfer siblings)
        $relatedTransfers = collect();
        if ($transfer->is_bulk_transfer && isset($transfer->metadata['bulk_property_ids'])) {
            $relatedTransfers = PropertyOwnershipTransfer::whereIn('property_id', $transfer->metadata['bulk_property_ids'])
                ->where('id', '!=', $transfer->id)
                ->with('property')
                ->get();
        }
        
        // Get timeline events
        $timeline = $this->getAdminTransferTimeline($transfer);
        
        // Get readiness report for completion
        $readinessReport = $this->getTransferReadinessReport($transfer->property, $transfer);
        
        // Check permissions for actions
        $canApprove = $transfer->canBeApproved();
        $canReject = $transfer->canBeRejected();
        $canComplete = $transfer->canBeCompleted();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'transfer' => $transfer,
                'readiness_report' => $readinessReport,
                'related_transfers' => $relatedTransfers,
                'timeline' => $timeline,
                'permissions' => [
                    'can_approve' => $canApprove,
                    'can_reject' => $canReject,
                    'can_complete' => $canComplete
                ]
            ]);
        }
        
        return view('admin.ownership-transfers.show', compact(
            'transfer',
            'unitsWithTenants',
            'relatedTransfers',
            'timeline',
            'readinessReport',
            'canApprove',
            'canReject',
            'canComplete'
        ));
    }

    /**
     * Get admin transfer timeline events
     */
    private function getAdminTransferTimeline(PropertyOwnershipTransfer $transfer)
    {
        $timeline = [];
        
        // Request created
        $timeline[] = [
            'event' => 'Transfer Request Submitted',
            'date' => $transfer->created_at,
            'description' => 'Transfer request was submitted by ' . ($transfer->requestedBy->name ?? $transfer->currentLandlord->name ?? 'Unknown'),
            'icon' => 'paper-plane',
            'color' => 'primary'
        ];
        
        // Approved
        if ($transfer->approved_at) {
            $timeline[] = [
                'event' => 'Transfer Approved',
                'date' => $transfer->approved_at,
                'description' => 'Transfer request was approved by ' . ($transfer->approvedBy->name ?? 'Administrator'),
                'icon' => 'check-circle',
                'color' => 'success'
            ];
        }
        
        // Rejected
        if ($transfer->rejected_at) {
            $timeline[] = [
                'event' => 'Transfer Rejected',
                'date' => $transfer->rejected_at,
                'description' => 'Transfer request was rejected by ' . ($transfer->rejectedBy->name ?? 'Administrator') . '. Reason: ' . ($transfer->rejection_reason ?? 'Not provided'),
                'icon' => 'times-circle',
                'color' => 'danger'
            ];
        }
        
        // Completed
        if ($transfer->completed_at) {
            $timeline[] = [
                'event' => 'Transfer Completed',
                'date' => $transfer->completed_at,
                'description' => 'Property ownership transfer was completed by ' . ($transfer->completedBy->name ?? 'Administrator'),
                'icon' => 'check-double',
                'color' => 'success'
            ];
        }
        
        // Cancelled
        if ($transfer->status === PropertyOwnershipTransfer::STATUS_CANCELLED && isset($transfer->metadata['cancelled_at'])) {
            $timeline[] = [
                'event' => 'Transfer Cancelled',
                'date' => $transfer->metadata['cancelled_at'],
                'description' => 'Transfer request was cancelled by the sender',
                'icon' => 'ban',
                'color' => 'secondary'
            ];
        }
        
        // Reversal Requested
        if ($transfer->reversal_requested_at) {
            $timeline[] = [
                'event' => 'Reversal Requested',
                'date' => $transfer->reversal_requested_at,
                'description' => 'Transfer reversal was requested by ' . ($transfer->reversalRequestedBy->name ?? 'Landlord') . '. Reason: ' . ($transfer->reversal_reason ?? 'Not provided'),
                'icon' => 'undo-alt',
                'color' => 'warning'
            ];
        }
        
        // Reversal Approved
        if ($transfer->reversal_status === 'approved' && $transfer->reversal_processed_at) {
            $timeline[] = [
                'event' => 'Reversal Approved',
                'date' => $transfer->reversal_processed_at,
                'description' => 'Reversal request was approved by ' . ($transfer->reversalProcessedBy->name ?? 'Administrator'),
                'icon' => 'check-circle',
                'color' => 'success'
            ];
        }
        
        // Reversal Rejected
        if ($transfer->reversal_status === 'rejected' && $transfer->reversal_processed_at) {
            $timeline[] = [
                'event' => 'Reversal Rejected',
                'date' => $transfer->reversal_processed_at,
                'description' => 'Reversal request was rejected by ' . ($transfer->reversalProcessedBy->name ?? 'Administrator') . '. Reason: ' . ($transfer->reversal_admin_notes ?? 'Not provided'),
                'icon' => 'times-circle',
                'color' => 'danger'
            ];
        }
        
        // Reversal Expired
        if ($transfer->reversal_status === 'expired') {
            $timeline[] = [
                'event' => 'Reversal Request Expired',
                'date' => $transfer->reversal_processed_at ?? $transfer->updated_at,
                'description' => 'Reversal request expired before admin review',
                'icon' => 'clock',
                'color' => 'warning'
            ];
        }
        
        // Reversal Executed (Completed)
        if ($transfer->is_reversed && $transfer->reversal_status === 'completed') {
            $timeline[] = [
                'event' => 'Reversal Completed',
                'date' => $transfer->reversal_processed_at ?? $transfer->updated_at,
                'description' => 'Transfer reversal was executed successfully. Property ownership restored to original owner.',
                'icon' => 'check-double',
                'color' => 'success'
            ];
        }
        
        return collect($timeline)->sortBy('date')->values();
    }

    /**
     * Bulk approve transfers
     */
    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'admin_notes' => 'nullable|string|max:1000',
            'auto_complete' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        $transferIds = $request->transfer_ids;
        $approved = 0;
        $completed = 0;
        $failed = [];
        $autoComplete = $request->boolean('auto_complete', config('ownership_transfer.auto_complete_on_approval', false));

        foreach ($transferIds as $transferId) {
            $transfer = PropertyOwnershipTransfer::find($transferId);
            
            if (!$transfer || !$transfer->canBeApproved()) {
                $failed[] = ['id' => $transferId, 'reason' => 'Cannot be approved'];
                continue;
            }

            try {
                DB::beginTransaction();
                
                $transfer->update([
                    'status' => PropertyOwnershipTransfer::STATUS_APPROVED,
                    'admin_approved_by_id' => auth()->id(),
                    'admin_notes' => $request->admin_notes,
                    'approved_at' => now()
                ]);

                $this->sendOwnershipTransferInvitation($transfer);
                
                // Send approval notifications
                if ($transfer->currentLandlord) {
                    $transfer->currentLandlord->notify(new OwnershipTransferApproved($transfer, 'current_landlord'));
                }
                if ($transfer->newLandlord) {
                    $transfer->newLandlord->notify(new OwnershipTransferApproved($transfer, 'new_landlord'));
                }
                
                $this->triggerWebhooks('transfer.approved', $transfer);
                
                if ($autoComplete) {
                    try {
                        $this->completeTransferAfterApproval($transfer, $request);
                        $completed++;
                    } catch (\Exception $e) {
                        \Log::warning('Auto-completion failed for transfer ID: ' . $transfer->id);
                    }
                }
                
                $this->clearTransferCache($transfer);
                
                DB::commit();
                $approved++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $failed[] = ['id' => $transferId, 'reason' => $e->getMessage()];
            }
        }

        if ($approved > 0) {
            $this->clearAllTransferCaches();
        }

        $message = "Bulk approval completed: {$approved} approved";
        if ($completed > 0) {
            $message .= " ({$completed} auto-completed)";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approved_count' => $approved,
                'completed_count' => $completed,
                'failed' => $failed
            ]);
        }

        return redirect()->route('admin.ownership-transfers.index')
            ->with('success', $message);
    }

    /**
     * Bulk reject transfers
     */
    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'rejection_reason' => 'required|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000',
            'allow_resubmission' => 'boolean',
            'resubmission_days' => 'nullable|integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        $transferIds = $request->transfer_ids;
        $rejected = 0;
        $failed = [];

        foreach ($transferIds as $transferId) {
            $transfer = PropertyOwnershipTransfer::find($transferId);
            
            if (!$transfer || !$transfer->canBeRejected()) {
                $failed[] = ['id' => $transferId, 'reason' => 'Cannot be rejected'];
                continue;
            }

            try {
                DB::beginTransaction();
                
                $updateData = [
                    'status' => PropertyOwnershipTransfer::STATUS_REJECTED,
                    'rejection_reason' => $request->rejection_reason,
                    'admin_notes' => $request->admin_notes,
                    'rejected_at' => now(),
                    'rejected_by_id' => auth()->id()
                ];

                if ($request->has('allow_resubmission') && $request->boolean('allow_resubmission')) {
                    $days = (int) ($request->resubmission_days ?? 7);
                    $updateData['can_resubmit_after'] = now()->addDays($days);
                }

                $transfer->update($updateData);
                $this->triggerWebhooks('transfer.rejected', $transfer);
                
                // Send rejection notification
                if ($transfer->currentLandlord) {
                    $transfer->currentLandlord->notify(new OwnershipTransferRejected($transfer));
                }
                
                $this->clearTransferCache($transfer);
                
                DB::commit();
                $rejected++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $failed[] = ['id' => $transferId, 'reason' => $e->getMessage()];
            }
        }

        if ($rejected > 0) {
            $this->clearAllTransferCaches();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Bulk rejection completed: {$rejected} rejected, " . count($failed) . " failed.",
                'rejected_count' => $rejected,
                'failed' => $failed
            ]);
        }

        return redirect()->route('admin.ownership-transfers.index')
            ->with('success', "Bulk rejection completed: {$rejected} transfers rejected successfully.");
    }

    /**
     * Get transfer readiness report
     */
    private function getTransferReadinessReport(Property $property, PropertyOwnershipTransfer $transfer)
    {
        $units = PropertyUnit::where('property_id', $property->id)->get();
        $unitsWithTenants = $units->filter(function($unit) {
            return !is_null($unit->tenant_id);
        });
        
        return [
            'property_name' => $property->property_name,
            'property_id' => $property->id,
            'has_units' => $units->isNotEmpty(),
            'total_units' => $units->count(),
            'units_with_tenants' => $unitsWithTenants->count(),
            'tenants_will_transfer' => $unitsWithTenants->isNotEmpty(),
            'tenants_list' => $unitsWithTenants->map(function($unit) {
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'tenant_id' => $unit->tenant_id,
                    'tenant_name' => $unit->tenant->name ?? 'Unknown',
                ];
            }),
            'warning' => $unitsWithTenants->isNotEmpty() 
                ? "Note: {$unitsWithTenants->count()} tenant(s) will be transferred to the new landlord."
                : "No tenants will be transferred.",
            'transfer_ready' => true,
            'requires_confirmation' => $unitsWithTenants->isNotEmpty(),
        ];
    }

    /**
     * Transfer property units to new landlord
     */
    private function transferPropertyUnits(Property $property, int $oldLandlordId, int $newLandlordId, PropertyOwnershipTransfer $transfer): array
    {
        $units = PropertyUnit::where('property_id', $property->id)->get();
        
        $result = [
            'total_units' => $units->count(),
            'units_with_tenants' => 0,
            'units_transferred' => 0,
            'transferred_units' => []
        ];
        
        if ($units->isEmpty()) {
            return $result;
        }

        foreach ($units as $unit) {
            $hasTenant = !is_null($unit->tenant_id);
            
            if ($hasTenant) {
                $result['units_with_tenants']++;
            }
            
            $metadata = $unit->metadata ?? [];
            $metadata['ownership_transfer'] = [
                'transferred_at' => now()->toISOString(),
                'from_landlord_id' => $oldLandlordId,
                'to_landlord_id' => $newLandlordId,
                'transfer_id' => $transfer->id,
                'transfer_document_reference' => $transfer->document_reference,
            ];
            
            $ownershipHistory = $metadata['ownership_history'] ?? [];
            $ownershipHistory[] = [
                'landlord_id' => $oldLandlordId,
                'landlord_name' => $transfer->currentLandlord->name ?? 'Unknown',
                'period_start' => $unit->created_at->toISOString(),
                'period_end' => now()->toISOString(),
                'transfer_id' => $transfer->id,
            ];
            $metadata['ownership_history'] = $ownershipHistory;
            
            $unit->update(['metadata' => $metadata]);
            $result['units_transferred']++;
        }

        return $result;
    }

    /**
     * Update tenant relationships
     */
    private function updateTenantRelationships(Property $property, int $oldLandlordId, int $newLandlordId, PropertyOwnershipTransfer $transfer): int
    {
        $units = PropertyUnit::where('property_id', $property->id)
            ->whereNotNull('tenant_id')
            ->get();
        
        if ($units->isEmpty()) {
            return 0;
        }

        foreach ($units as $unit) {
            if ($unit->tenant_id && method_exists($unit->tenant, 'notify')) {
                try {
                    $unit->tenant->notify(new \App\Notifications\PropertyOwnershipChangedNotification($property, $transfer));
                } catch (\Exception $e) {
                    \Log::warning('Failed to notify tenant about ownership change');
                }
            }
        }

        return $units->count();
    }

    /**
     * Update rental agreements
     */
    private function updateRentalAgreements(Property $property, int $oldLandlordId, int $newLandlordId, PropertyOwnershipTransfer $transfer): int
    {
        if (!class_exists(\App\Models\RentalAgreement::class)) {
            return 0;
        }

        $agreements = \App\Models\RentalAgreement::whereHas('unit', function($query) use ($property) {
            $query->where('property_id', $property->id);
        })->whereIn('status', ['active', 'pending'])->get();

        foreach ($agreements as $agreement) {
            $metadata = $agreement->metadata ?? [];
            $metadata['landlord_change'] = [
                'changed_at' => now()->toISOString(),
                'old_landlord_id' => $oldLandlordId,
                'new_landlord_id' => $newLandlordId,
                'transfer_id' => $transfer->id,
            ];
            $agreement->update(['metadata' => $metadata]);
        }

        return $agreements->count();
    }

    /**
     * Check and archive old landlord
     */
    private function checkAndArchiveOldLandlord(User $oldLandlord, PropertyOwnershipTransfer $transfer): array
    {
        if ($oldLandlord->isAdmin() || $oldLandlord->isSuperAdmin()) {
            return ['archived' => false, 'reason' => 'admin_account'];
        }
        
        $remainingProperties = Property::where('landlord_id', $oldLandlord->id)->count();
        
        if ($remainingProperties > 0) {
            return ['archived' => false, 'remaining_properties' => $remainingProperties];
        }
        
        $archiveDelayDays = config('ownership_transfer.archive_old_landlord_days', 30);
        $immediateArchive = config('ownership_transfer.archive_immediately', false);
        
        if ($immediateArchive) {
            $oldLandlord->archive('no_properties_remaining', auth()->id());
            return ['archived' => true, 'reason' => 'immediate_archive'];
        } else {
            $oldLandlord->scheduleDeletion($archiveDelayDays);
            return ['archived' => false, 'scheduled' => true, 'days_until_archive' => $archiveDelayDays];
        }
    }

    /**
     * Generate transfer certificate
     */
    private function generateTransferCertificate(PropertyOwnershipTransfer $transfer, $forceRegenerate = false)
    {
        try {
            if (!$forceRegenerate && $transfer->certificate_url && Storage::exists("public/{$transfer->certificate_url}")) {
                return $transfer->certificate_url;
            }
            
            $systemSettings = SystemSetting::getSettings();
            $units = PropertyUnit::where('property_id', $transfer->property_id)->get();
            $unitsTransferred = $units->count();
            $tenantsTransferred = $units->whereNotNull('tenant_id')->count();
            
            $certificateNumber = 'OTC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            $certificateData = [
                'transfer' => $transfer,
                'certificate_number' => $certificateNumber,
                'issue_date' => now()->format('F j, Y'),
                'property' => $transfer->property,
                'current_owner' => $transfer->currentLandlord,
                'new_owner' => $transfer->newLandlord,
                'units_transferred' => $unitsTransferred,
                'tenants_transferred' => $tenantsTransferred,
                'systemSettings' => $systemSettings,
            ];
            
            $html = view('certificates.ownership-transfer', $certificateData)->render();
            
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'landscape');
            
            $filename = "certificate_{$transfer->id}_{$certificateNumber}.pdf";
            $path = "ownership-transfers/certificates/{$filename}";
            
            Storage::put("public/{$path}", $pdf->output());
            
            $transfer->update([
                'certificate_url' => $path,
                'metadata' => array_merge($transfer->metadata ?? [], [
                    'certificate_generated_at' => now()->toISOString(),
                    'certificate_number' => $certificateNumber,
                ])
            ]);
            
            return $path;
            
        } catch (\Exception $e) {
            \Log::error('Failed to generate transfer certificate: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send ownership transfer invitation
     */
    private function sendOwnershipTransferInvitation(PropertyOwnershipTransfer $transfer): array
    {
        try {
            $newLandlord = $transfer->newLandlord;
            
            if (!$newLandlord) {
                return ['success' => false, 'message' => 'New landlord not found.'];
            }

            $isExistingLandlord = $transfer->metadata['is_existing_landlord'] ?? false;
            
            if ($isExistingLandlord) {
                return ['success' => true, 'message' => 'Existing landlord - no invitation needed.', 'skip_invitation' => true];
            }

            if ($newLandlord->status === User::STATUS_ACTIVE) {
                return ['success' => true, 'message' => 'User already active.'];
            }

            $channelsToSend = ['email'];
            if (!empty($newLandlord->phone)) {
                $channelsToSend[] = 'sms';
            }

            $masterToken = Str::random(64);
            $expiresAt = now()->addDays(7);
            $invitationUrl = route('user.invitations.accept', ['token' => $masterToken]);
            
            $invitation = UserInvitation::create([
                'user_id' => $newLandlord->id,
                'invited_by' => auth()->id(),
                'token' => $masterToken,
                'channels' => $channelsToSend,
                'invitation_type' => 'ownership_transfer',
                'expires_at' => $expiresAt,
                'sent_at' => now(),
                'status' => 'sent',
                'metadata' => ['transfer_id' => $transfer->id]
            ]);
            
            // Send email
            if (in_array('email', $channelsToSend) && !empty($newLandlord->email)) {
                \Mail::send('emails.ownership-transfer-invitation', [
                    'userName' => $newLandlord->name,
                    'invitationLink' => $invitationUrl,
                    'property' => $transfer->property,
                    'transfer' => $transfer,
                ], function($message) use ($newLandlord) {
                    $message->to($newLandlord->email)->subject('Property Ownership Transfer Invitation');
                });
            }
            
            return [
                'success' => true,
                'message' => 'Invitation sent successfully',
                'invitation_id' => $invitation->id,
                'invitation_url' => $invitationUrl
            ];
            
        } catch (\Exception $e) {
            \Log::error('Failed to send invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Complete transfer after approval (auto-completion)
     */
    private function completeTransferAfterApproval(PropertyOwnershipTransfer $transfer, Request $request)
    {
        $property = $transfer->property;
        
        if (!$property) {
            throw new \Exception('Property not found');
        }
        
        $oldLandlordId = $property->landlord_id;
        $newLandlordId = $transfer->new_landlord_id;
        $newLandlord = User::find($newLandlordId);
        
        if (!$newLandlord) {
            throw new \Exception('New landlord not found');
        }
        
        $units = PropertyUnit::where('property_id', $property->id)->get();
        $unitsWithTenants = $units->filter(function($unit) {
            return !is_null($unit->tenant_id);
        });
        
        $property->update([
            'landlord_id' => $newLandlordId,
            'previous_landlord_id' => $oldLandlordId,
            'ownership_transferred_at' => now(),
            'ownership_transfer_id' => $transfer->id,
        ]);

        $this->transferPropertyUnits($property, $oldLandlordId, $newLandlordId, $transfer);
        $this->updateTenantRelationships($property, $oldLandlordId, $newLandlordId, $transfer);
        
        $transfer->update([
            'status' => PropertyOwnershipTransfer::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by_id' => auth()->id(),
        ]);
        
        $this->transferService->updatePropertyOwnershipHistory($property, $transfer);
        $this->triggerWebhooks('transfer.completed', $transfer);
        
        // Generate certificate for auto-completion
        $certificatePath = $this->generateTransferCertificate($transfer);
        
        // Send completion notifications
        if ($transfer->currentLandlord) {
            $transfer->currentLandlord->notify(new OwnershipTransferCompleted($transfer, 'previous_owner', $certificatePath));
        }
        if ($transfer->newLandlord) {
            $transfer->newLandlord->notify(new OwnershipTransferCompleted($transfer, 'new_owner', $certificatePath));
        }
        
        $this->transferService->notifyPartiesOfCompletion($transfer, $certificatePath);
        
        return true;
    }

    /**
     * Dashboard
     */
    public function dashboard(Request $request)
    {
        $stats = $this->getTransferStatistics();
        
        $recentTransfers = PropertyOwnershipTransfer::with(['property', 'currentLandlord'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        $pendingApprovals = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count();
        
        $monthlyTrend = PropertyOwnershipTransfer::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(sale_amount) as total_value')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        return view('admin.ownership-transfers.dashboard', compact(
            'stats', 
            'recentTransfers', 
            'pendingApprovals',
            'monthlyTrend'
        ));
    }

    /**
     * Export transfers as CSV
     */
    public function exportCsv(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = $this->buildTransferQuery($request);
        $transfers = $query->get();
        
        $filename = "ownership_transfers_" . date('Y-m-d_His') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($transfers) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'ID', 'Document Reference', 'Property Name', 'Current Owner', 'New Owner',
                'Transfer Date', 'Sale Amount', 'Status', 'Requested Date', 'Completed Date',
                'Is Reversal Record', 'Reversal Status', 'Is Reversed', 'Reversal Attempts'
            ]);
            
            foreach ($transfers as $transfer) {
                $reversalAttempts = isset($transfer->metadata['reversal_attempts']) ? count($transfer->metadata['reversal_attempts']) : 0;
                
                fputcsv($file, [
                    $transfer->id,
                    $transfer->document_reference,
                    $transfer->property->property_name ?? 'N/A',
                    $transfer->currentLandlord->name ?? 'N/A',
                    $transfer->new_owner_name,
                    $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : 'N/A',
                    $transfer->sale_amount ? number_format($transfer->sale_amount, 2) : 'N/A',
                    $transfer->status_label,
                    $transfer->created_at->format('Y-m-d H:i:s'),
                    $transfer->completed_at ? $transfer->completed_at->format('Y-m-d H:i:s') : 'N/A',
                    $transfer->isReversalRecord() ? 'Yes' : 'No',
                    $transfer->reversal_status ?? 'N/A',
                    $transfer->is_reversed ? 'Yes' : 'No',
                    $reversalAttempts,
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper Methods
     */
    private function buildTransferQuery(Request $request)
    {
        $query = PropertyOwnershipTransfer::with([
            'property' => function($q) {
                $q->select('id', 'property_name', 'registration_pattern');
            },
            'currentLandlord' => function($q) {
                $q->select('id', 'name', 'email');
            },
            'newLandlord' => function($q) {
                $q->select('id', 'name', 'email');
            }
        ])->latest();
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        return $query;
    }

    private function getTransferStatistics()
    {
        return [
            'total' => PropertyOwnershipTransfer::count(),
            'pending' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count(),
            'approved' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_APPROVED)->count(),
            'completed' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->count(),
            'rejected' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_REJECTED)->count(),
            'cancelled' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)->count(),
            'total_value' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->sum('sale_amount'),
            'reversal_pending' => PropertyOwnershipTransfer::where('reversal_status', 'pending')->count(),
            'reversal_completed' => PropertyOwnershipTransfer::where('is_reversed', true)->count(),
            'reversal_rejected' => PropertyOwnershipTransfer::where('reversal_status', 'rejected')->count(),
            'reversal_expired' => PropertyOwnershipTransfer::where('reversal_status', 'expired')->count(),
        ];
    }

    private function getFilterOptions()
    {
        return [
            'statuses' => PropertyOwnershipTransfer::getStatuses(),
            'document_types' => PropertyOwnershipTransfer::getDocumentTypes(),
        ];
    }

    private function canApproveTransfer(PropertyOwnershipTransfer $transfer, User $admin)
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    /**
     * Check if a reversal can be requested (UPDATED to allow after rejection/expiration)
     */
    private function canRequestReversal(PropertyOwnershipTransfer $transfer, User $user)
    {
        $reversalEnabled = config('ownership_transfer.reversal.enabled', true);
        
        if (!$reversalEnabled) return false;
        
        // Only the sender (original owner) can request reversal
        if ($transfer->current_landlord_id !== $user->id) return false;
        
        // Must be completed transfer
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_COMPLETED) return false;
        
        // Cannot reverse if already reversed
        if ($transfer->is_reversed) return false;
        
        // ✅ FIX: Allow new request if previous was rejected or expired
        // Only block if currently pending (waiting for review)
        if ($transfer->reversal_status === 'pending') return false;
        
        // ✅ Allow if rejected, expired, or no previous request
        // No longer blocking on rejected or expired status
        
        // Check reversal window (if configured)
        $reversalWindowDays = config('ownership_transfer.reversal.reversal_window_days', 30);
        if ($reversalWindowDays > 0 && $transfer->completed_at) {
            $daysSinceCompletion = $transfer->completed_at->diffInDays(now());
            if ($daysSinceCompletion > $reversalWindowDays) {
                return false;
            }
        }
        
        return true;
    }

    private function getNextWorkflowStep($transfer)
    {
        $workflow = config('ownership_transfer.workflow', ['approval', 'verification', 'completion']);
        $currentStep = $transfer->approval_workflow_step ?? 'approval';
        $currentIndex = array_search($currentStep, $workflow);
        return $workflow[$currentIndex + 1] ?? 'completed';
    }

    private function storeCacheKey($key)
    {
        $keys = Cache::get('ownership_transfer_cache_keys', []);
        if (!in_array($key, $keys)) {
            $keys[] = $key;
            Cache::put('ownership_transfer_cache_keys', $keys, 3600);
        }
    }

    private function clearTransferCache(PropertyOwnershipTransfer $transfer)
    {
        Cache::forget("transfer_{$transfer->id}");
        Cache::forget('ownership_transfer_stats');
    }

    private function clearAllTransferCaches()
    {
        Cache::forget('ownership_transfer_stats');
        $keys = Cache::get('ownership_transfer_cache_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::put('ownership_transfer_cache_keys', [], 3600);
    }

    private function clearPropertyUnitCaches(int $propertyId): void
    {
        Cache::forget('property_units_' . $propertyId);
        Cache::forget('property_stats_' . $propertyId);
    }

    private function applyFullTextSearchToQuery($query, $searchTerm)
    {
        if (empty($searchTerm)) {
            return $query;
        }

        return $query->where(function($q) use ($searchTerm) {
            $q->where('document_reference', 'LIKE', "%{$searchTerm}%")
              ->orWhere('new_owner_name', 'LIKE', "%{$searchTerm}%")
              ->orWhereHas('property', function($propertyQuery) use ($searchTerm) {
                  $propertyQuery->where('property_name', 'LIKE', "%{$searchTerm}%");
              });
        });
    }

    private function handleExport(Request $request, $transfers)
    {
        return $this->exportCsv($request);
    }

    private function triggerWebhooks($event, $data)
    {
        if (!$this->webhookService) {
            return;
        }
        try {
            $this->webhookService->trigger($event, $data);
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    private function authorizeAdminAccess(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            abort(403, 'Admin privileges required.');
        }
    }

    private function unauthorizedResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        return redirect()->back()->with('error', $message);
    }

    private function validationErrorResponse(Request $request, $validator)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        return redirect()->back()->withErrors($validator)->withInput();
    }

    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 500);
        }
        return redirect()->back()->with('error', $message);
    }

    /**
     * Download transfer document (admin version)
     */
    public function downloadDocument($transferId)
    {
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        $this->authorizeAdminAccess(request());
        
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
     * Download transfer certificate (admin version)
     */
    public function downloadCertificate($transferId)
    {
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        $this->authorizeAdminAccess(request());
        
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_COMPLETED) {
            abort(404, 'Transfer certificate not available.');
        }
        
        // Try to find certificate in different locations
        $certificatePath = $transfer->certificate_url;
        
        if (!$certificatePath || !Storage::exists("public/{$certificatePath}")) {
            $certificatePath = "ownership-transfers/certificates/{$transfer->id}.pdf";
        }
        
        if (!Storage::exists("public/{$certificatePath}")) {
            // Try to generate certificate
            $certificatePath = $this->generateTransferCertificate($transfer);
        }
        
        if (!$certificatePath || !Storage::exists("public/{$certificatePath}")) {
            abort(404, 'Certificate not found. Please complete the transfer first.');
        }
        
        $filename = "ownership_transfer_certificate_{$transfer->id}.pdf";
        $path = storage_path('app/public/' . $certificatePath);
        
        return response()->download($path, $filename);
    }

    /**
     * Soft delete (move to trash) a transfer - UPDATED to allow deletion of transfers with pending reversals
     */
    public function destroy(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        
        // ==============================================
        // UPDATED: Allow deletion for transfers with pending reversals
        // ==============================================
        
        // Allow deletion for:
        // 1. Reversal records (always)
        // 2. Transfers with pending reversal requests (admin cleanup)
        // 3. Completed transfers that have been reversed
        // 4. Non-completed regular transfers (rejected, cancelled, pending, approved)
        
        $hasPendingReversal = $transfer->reversal_status === 'pending' && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED;
        $isReversedCompleted = ($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->is_reversed);
        $isReversalRecord = $transfer->isReversalRecord();
        
        // ✅ FIX: Allow deletion if transfer has pending reversal
        if (!$isReversalRecord && !$isReversedCompleted && !$hasPendingReversal && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED) {
            return $this->errorResponse($request, 'Completed transfers cannot be deleted unless they have been reversed or have a pending reversal request.');
        }
        
        // ✅ If transfer has pending reversal, show warning but allow deletion with confirmation
        if ($hasPendingReversal && !$request->has('confirm_pending_reversal')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => 'This transfer has a pending reversal request. Deleting it will also cancel the pending reversal. Are you sure?',
                    'transfer_id' => $transfer->id,
                    'has_pending_reversal' => true
                ], 409);
            }
            
            return redirect()->back()
                ->with('warning', 'This transfer has a pending reversal request. Deleting it will also cancel the pending reversal.')
                ->with('requires_confirmation', true)
                ->with('transfer_id', $transfer->id);
        }
        
        DB::beginTransaction();
        
        try {
            if (Schema::hasColumn('property_ownership_transfers', 'deleted_by')) {
                $transfer->deleted_by = auth()->id();
            }
            
            $metadata = $transfer->metadata ?? [];
            $metadata['soft_deleted'] = [
                'deleted_at' => now()->toISOString(),
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'status_at_deletion' => $transfer->status,
                'deleted_reason' => $request->input('deletion_reason'),
                'ip_address' => $request->ip(),
                'is_reversal_record' => $isReversalRecord,
                'is_reversed' => $transfer->is_reversed,
                'had_pending_reversal' => $hasPendingReversal,
            ];
            
            // If deletion is cancelling a pending reversal, record that
            if ($hasPendingReversal) {
                $metadata['pending_reversal_cancelled'] = [
                    'cancelled_at' => now()->toISOString(),
                    'cancelled_by' => auth()->id(),
                    'cancelled_by_name' => auth()->user()->name,
                    'original_reversal_reason' => $transfer->reversal_reason,
                    'original_reversal_requested_at' => $transfer->reversal_requested_at ? $transfer->reversal_requested_at->toISOString() : null,
                ];
            }
            
            $transfer->metadata = $metadata;
            $transfer->save();
            
            // If there was a pending reversal, update the reversal status to cancelled
            if ($hasPendingReversal) {
                $transfer->update([
                    'reversal_status' => 'cancelled',
                    'reversal_admin_notes' => 'Cancelled due to transfer deletion: ' . ($request->input('deletion_reason') ?? 'No reason provided'),
                    'reversal_processed_at' => now(),
                    'reversal_processed_by' => auth()->id(),
                ]);
            }
            
            // Perform soft delete - this will trigger cascade to reversal records
            $transfer->delete();
            
            // Clear caches
            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            $recordType = $isReversalRecord ? 'Reversal record' : 'Transfer';
            if ($transfer->is_reversed) {
                $recordType = 'Reversed Transfer';
            }
            if ($hasPendingReversal) {
                $recordType = 'Transfer with pending reversal';
            }
            
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_soft_delete',
                'description' => "Moved {$recordType} #{$transfer->id} ({$transfer->document_reference}) to trash" . ($hasPendingReversal ? " (pending reversal cancelled)" : ""),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'transfer_id' => $transfer->id,
                    'document_reference' => $transfer->document_reference,
                    'status' => $transfer->status,
                    'is_reversal' => $isReversalRecord,
                    'is_reversed' => $transfer->is_reversed,
                    'had_pending_reversal' => $hasPendingReversal,
                ]
            ]);
            
            DB::commit();
            
            $message = $hasPendingReversal 
                ? "{$recordType} moved to trash successfully. The pending reversal request has been cancelled."
                : "{$recordType} moved to trash successfully.";
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'transfer_id' => $transfer->id,
                    'is_reversal' => $isReversalRecord,
                    'is_reversed' => $transfer->is_reversed,
                    'pending_reversal_cancelled' => $hasPendingReversal,
                ]);
            }
            
            return redirect()->back()
                ->with('success', $message)
                ->with('force_refresh', true);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Bulk soft delete (move multiple transfers to trash) - UPDATED for transfers with pending reversals
     */
    public function bulkSoftDelete(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'deletion_reason' => 'nullable|string|max:1000',
            'confirm_pending_reversal' => 'nullable|boolean'
        ]);
        
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
        
        $transferIds = $request->transfer_ids;
        $deleted = 0;
        $cancelledReversals = 0;
        $failed = [];
        $pendingReversalTransfers = [];
        
        // First pass: identify transfers with pending reversals
        foreach ($transferIds as $transferId) {
            $transfer = PropertyOwnershipTransfer::find($transferId);
            if ($transfer && $transfer->reversal_status === 'pending' && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED) {
                $pendingReversalTransfers[] = $transferId;
            }
        }
        
        // If there are pending reversals and not confirmed, ask for confirmation
        if (!empty($pendingReversalTransfers) && !$request->boolean('confirm_pending_reversal', false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => count($pendingReversalTransfers) . ' selected transfer(s) have pending reversal requests. Deleting them will cancel these reversals.',
                    'pending_reversal_ids' => $pendingReversalTransfers,
                    'count' => count($pendingReversalTransfers)
                ], 409);
            }
            
            return redirect()->back()
                ->with('warning', count($pendingReversalTransfers) . ' selected transfer(s) have pending reversal requests. Deleting them will cancel these reversals.')
                ->with('requires_confirmation', true)
                ->with('pending_reversal_ids', $pendingReversalTransfers);
        }
        
        DB::beginTransaction();
        
        try {
            foreach ($transferIds as $transferId) {
                $transfer = PropertyOwnershipTransfer::find($transferId);
                
                if (!$transfer) {
                    $failed[] = ['id' => $transferId, 'reason' => 'Transfer not found'];
                    continue;
                }
                
                $hasPendingReversal = $transfer->reversal_status === 'pending' && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED;
                
                // Skip completed regular transfers without pending reversal
                if (!$transfer->isReversalRecord() && !$hasPendingReversal && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED) {
                    $failed[] = ['id' => $transferId, 'reason' => 'Completed transfers cannot be deleted unless reversed or with pending reversal'];
                    continue;
                }
                
                // Store metadata before soft delete
                $metadata = $transfer->metadata ?? [];
                $metadata['soft_deleted'] = [
                    'deleted_at' => now()->toISOString(),
                    'deleted_by' => auth()->id(),
                    'deleted_by_name' => auth()->user()->name,
                    'status_at_deletion' => $transfer->status,
                    'deleted_reason' => $request->deletion_reason,
                    'ip_address' => $request->ip(),
                    'bulk_deletion' => true,
                    'is_reversal_record' => $transfer->isReversalRecord(),
                    'had_pending_reversal' => $hasPendingReversal,
                ];
                
                // If deletion is cancelling a pending reversal, record that
                if ($hasPendingReversal) {
                    $metadata['pending_reversal_cancelled'] = [
                        'cancelled_at' => now()->toISOString(),
                        'cancelled_by' => auth()->id(),
                        'cancelled_by_name' => auth()->user()->name,
                        'original_reversal_reason' => $transfer->reversal_reason,
                    ];
                    $cancelledReversals++;
                }
                
                $transfer->metadata = $metadata;
                $transfer->save();
                
                // If there was a pending reversal, update the reversal status
                if ($hasPendingReversal) {
                    $transfer->update([
                        'reversal_status' => 'cancelled',
                        'reversal_admin_notes' => 'Cancelled due to bulk deletion',
                        'reversal_processed_at' => now(),
                        'reversal_processed_by' => auth()->id(),
                    ]);
                }
                
                $transfer->delete();
                $deleted++;
            }
            
            DB::commit();
            
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            $message = "{$deleted} record(s) moved to trash successfully.";
            if ($cancelledReversals > 0) {
                $message .= " {$cancelledReversals} pending reversal(s) were cancelled.";
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deleted,
                    'cancelled_reversals' => $cancelledReversals,
                    'failed' => $failed
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.index')
                ->with('success', $message)
                ->with('force_refresh', true);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to move records to trash: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to move records to trash: ' . $e->getMessage());
        }
    }

    /**
     * Export method (alias for exportCsv)
     */
    public function export(Request $request, $format = 'csv')
    {
        $this->authorizeAdminAccess($request);
        
        if ($format !== 'csv') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only CSV export is currently supported.'
                ], 400);
            }
            return redirect()->back()->with('error', 'Only CSV export is currently supported.');
        }
        
        return $this->exportCsv($request);
    }

    /**
     * Search transfers
     */
    public function search(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransfer::query();
        
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('document_reference', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('new_owner_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('property', function($propertyQuery) use ($searchTerm) {
                      $propertyQuery->where('property_name', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }
        
        $results = $query->with(['property', 'currentLandlord', 'newLandlord'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'results' => $results,
                'search_term' => $request->q
            ]);
        }
        
        return view('admin.ownership-transfers.search', compact('results'));
    }

    /**
     * Get accurate counts for dashboard
     */
    public function getAccurateCounts()
    {
        $this->authorizeAdminAccess(request());
        
        return response()->json([
            'total' => PropertyOwnershipTransfer::count(),
            'pending' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count(),
            'approved' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_APPROVED)->count(),
            'completed' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->count(),
            'rejected' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_REJECTED)->count(),
            'cancelled' => PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)->count(),
            'trashed' => PropertyOwnershipTransfer::onlyTrashed()->count(),
            'reversal_records' => PropertyOwnershipTransfer::where('metadata->is_reversal', true)->count(),
        ]);
    }

    /**
     * List all pending reversal requests
     */
    public function reversalRequests(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransfer::where('reversal_status', 'pending')
            ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->where('is_reversed', false)
            ->with(['property', 'currentLandlord', 'newLandlord', 'reversalRequestedBy'])
            ->orderBy('reversal_requested_at', 'desc');
        
        // Filter by property
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        
        // Filter by requesting landlord
        if ($request->filled('landlord_id')) {
            $query->where('reversal_requested_by', $request->landlord_id);
        }
        
        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('reversal_requested_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('reversal_requested_at', '<=', $request->date_to);
        }
        
        $reversalRequests = $query->paginate($request->get('per_page', 20));
        
        $stats = [
            'pending' => PropertyOwnershipTransfer::where('reversal_status', 'pending')->count(),
            'approved' => PropertyOwnershipTransfer::where('reversal_status', 'approved')->count(),
            'rejected' => PropertyOwnershipTransfer::where('reversal_status', 'rejected')->count(),
            'completed' => PropertyOwnershipTransfer::where('reversal_status', 'completed')->count(),
            'expired' => PropertyOwnershipTransfer::where('reversal_status', 'pending')
                ->where('reversal_deadline', '<', now())
                ->count(),
            'cancelled' => PropertyOwnershipTransfer::where('reversal_status', 'cancelled')->count(),
        ];
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'reversal_requests' => $reversalRequests,
                'statistics' => $stats
            ]);
        }
        
        return view('admin.ownership-transfers.reversal-requests', compact('reversalRequests', 'stats'));
    }

    /**
     * Approve a reversal request
     */
    public function approveReversal(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        
        // Validate reversal can be approved
        if ($transfer->reversal_status !== 'pending') {
            return $this->errorResponse($request, 'This reversal request is no longer pending.');
        }
        
        if ($transfer->is_reversed) {
            return $this->errorResponse($request, 'This transfer has already been reversed.');
        }
        
        if ($transfer->reversal_deadline && $transfer->reversal_deadline < now()) {
            return $this->errorResponse($request, 'This reversal request has expired.');
        }
        
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:1000',
            'notify_parties' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        
        DB::beginTransaction();
        
        try {
            // Update reversal status
            $transfer->update([
                'reversal_status' => 'approved',
                'reversal_processed_at' => now(),
                'reversal_processed_by' => auth()->id(),
                'reversal_admin_notes' => $request->admin_notes,
                'metadata' => array_merge($transfer->metadata ?? [], [
                    'reversal_approval' => [
                        'approved_at' => now()->toISOString(),
                        'approved_by' => auth()->id(),
                        'approved_by_name' => auth()->user()->name,
                        'admin_notes' => $request->admin_notes
                    ]
                ])
            ]);
            
            // Execute the reversal
            $reversalResult = $this->executeTransferReversal($transfer, $request);
            
            DB::commit();
            
            // SEND NOTIFICATIONS
            $admin = auth()->user();
            
            // Notify the landlord who requested reversal
            if ($transfer->currentLandlord && $request->boolean('notify_parties', true)) {
                try {
                    $transfer->currentLandlord->notify(new TransferReversalApproved($transfer, $admin));
                } catch (\Exception $e) {
                    Log::warning('Failed to notify requesting landlord about approval: ' . $e->getMessage());
                }
            }
            
            // Notify the wrong landlord (who currently has the property)
            if ($transfer->newLandlord && $request->boolean('notify_parties', true)) {
                try {
                    $transfer->newLandlord->notify(new TransferReversalApproved($transfer, $admin, 'current_owner'));
                } catch (\Exception $e) {
                    Log::warning('Failed to notify current owner about approval: ' . $e->getMessage());
                }
            }
            
            $message = 'Reversal request approved and executed successfully. Property ownership has been restored.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'reversal_result' => $reversalResult,
                    'transfer' => $transfer->fresh()
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.reversal-requests')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve reversal: ' . $e->getMessage());
            
            return $this->errorResponse($request, 'Failed to process reversal: ' . $e->getMessage());
        }
    }

    /**
     * Reject a reversal request - UPDATED with proper notifications
     */
    public function rejectReversal(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        
        if ($transfer->reversal_status !== 'pending') {
            return $this->errorResponse($request, 'This reversal request is no longer pending.');
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
            'notify_landlord' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        
        DB::beginTransaction();
        
        try {
            $transfer->update([
                'reversal_status' => 'rejected',
                'reversal_processed_at' => now(),
                'reversal_processed_by' => auth()->id(),
                'reversal_admin_notes' => $request->rejection_reason,
                'metadata' => array_merge($transfer->metadata ?? [], [
                    'reversal_rejection' => [
                        'rejected_at' => now()->toISOString(),
                        'rejected_by' => auth()->id(),
                        'rejected_by_name' => auth()->user()->name,
                        'rejection_reason' => $request->rejection_reason
                    ]
                ])
            ]);
            
            DB::commit();
            
            // ✅ FIX: Send notification to the landlord who requested reversal
            $admin = auth()->user();
            
            // Notify the landlord who requested reversal (currentLandlord is the one who requested it)
            if ($request->boolean('notify_landlord', true) && $transfer->currentLandlord) {
                try {
                    $transfer->currentLandlord->notify(
                        new \App\Notifications\TransferReversalRejected(
                            $transfer, 
                            $admin, 
                            $request->rejection_reason
                        )
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to notify landlord about reversal rejection: ' . $e->getMessage());
                }
            }
            
            // Also notify the current owner (wrong landlord) that the reversal was rejected
            if ($transfer->newLandlord && $request->boolean('notify_landlord', true)) {
                try {
                    $transfer->newLandlord->notify(
                        new \App\Notifications\TransferReversalRejected(
                            $transfer, 
                            $admin, 
                            $request->rejection_reason, 
                            'current_owner'
                        )
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to notify current owner about reversal rejection: ' . $e->getMessage());
                }
            }
            
            $message = 'Reversal request rejected successfully.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'transfer' => $transfer->fresh()
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.reversal-requests')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to reject reversal request: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to reject reversal request: ' . $e->getMessage());
        }
    }

    /**
     * Execute the actual transfer reversal
     */
    private function executeTransferReversal(PropertyOwnershipTransfer $transfer, Request $request)
    {
        $property = $transfer->property;
        
        if (!$property) {
            throw new \Exception('Property not found.');
        }
        
        $originalLandlordId = $transfer->current_landlord_id;
        $wrongLandlordId = $transfer->new_landlord_id;
        
        $originalLandlord = User::find($originalLandlordId);
        $wrongLandlord = User::find($wrongLandlordId);
        
        if (!$originalLandlord || !$wrongLandlord) {
            throw new \Exception('Landlord records not found.');
        }
        
        // STEP 1: Reverse the property ownership
        $property->update([
            'landlord_id' => $originalLandlordId,
            'previous_landlord_id' => $wrongLandlordId,
            'ownership_transferred_at' => now(),
            'metadata' => array_merge($property->metadata ?? [], [
                'reversal_transfer' => [
                    'reversed_at' => now()->toISOString(),
                    'original_transfer_id' => $transfer->id,
                    'reversed_by' => auth()->id(),
                    'reversed_by_name' => auth()->user()->name,
                    'from_landlord' => $wrongLandlord->name,
                    'to_landlord' => $originalLandlord->name,
                    'reason' => $transfer->reversal_reason,
                    'admin_notes' => $request->admin_notes
                ]
            ])
        ]);
        
        // STEP 2: Reverse property units
        $units = PropertyUnit::where('property_id', $property->id)->get();
        $unitsReversed = 0;
        
        foreach ($units as $unit) {
            $metadata = $unit->metadata ?? [];
            
            $metadata['ownership_reversal'] = [
                'reversed_at' => now()->toISOString(),
                'original_transfer_id' => $transfer->id,
                'from_landlord_id' => $wrongLandlordId,
                'to_landlord_id' => $originalLandlordId,
                'reversed_by' => auth()->id(),
                'reversal_reason' => $transfer->reversal_reason
            ];
            
            $ownershipHistory = $metadata['ownership_history'] ?? [];
            $ownershipHistory[] = [
                'landlord_id' => $wrongLandlordId,
                'landlord_name' => $wrongLandlord->name,
                'period_start' => $transfer->completed_at->toISOString(),
                'period_end' => now()->toISOString(),
                'transfer_id' => $transfer->id,
                'is_reversal' => true
            ];
            $metadata['ownership_history'] = $ownershipHistory;
            
            $unit->update(['metadata' => $metadata]);
            $unitsReversed++;
        }
        
        // STEP 3: Update the transfer record
        $transfer->update([
            'is_reversed' => true,
            'reversal_status' => 'completed',
            'reversal_processed_at' => now(),
            'reversal_processed_by' => auth()->id(),
            'metadata' => array_merge($transfer->metadata ?? [], [
                'reversal_completion' => [
                    'completed_at' => now()->toISOString(),
                    'units_reversed' => $unitsReversed,
                    'reversal_type' => 'full_reversal'
                ]
            ])
        ]);
        
        // STEP 4: Create a reversal record for audit trail
        $reversalTransfer = PropertyOwnershipTransfer::create([
            'property_id' => $property->id,
            'current_landlord_id' => $wrongLandlordId,
            'new_landlord_id' => $originalLandlordId,
            'requested_by_id' => auth()->id(),
            'status' => PropertyOwnershipTransfer::STATUS_COMPLETED,
            'transfer_date' => now(),
            'document_reference' => 'REVERSAL-' . $transfer->document_reference,
            'document_url' => $transfer->document_url,
            'new_owner_name' => $originalLandlord->name,
            'new_owner_phone' => $originalLandlord->phone,
            'new_owner_email' => $originalLandlord->email,
            'reason_for_transfer' => 'REVERSAL: ' . ($transfer->reversal_reason ?? 'Wrong transfer reversal'),
            'notes' => "Reversal of transfer #{$transfer->id}. Original transfer was made to wrong landlord.",
            'metadata' => [
                'is_reversal' => true,
                'original_transfer_id' => $transfer->id,
                'reversal_reason' => $transfer->reversal_reason,
                'reversal_approved_by' => auth()->id(),
                'reversal_approved_by_name' => auth()->user()->name,
                'units_reversed' => $unitsReversed
            ],
            'completed_at' => now(),
            'completed_by_id' => auth()->id()
        ]);
        
        // Link the reversal transfer
        $transfer->update([
            'reversal_transfer_id' => $reversalTransfer->id
        ]);
        
        // SEND NOTIFICATION
        $admin = auth()->user();
        
        // Notify the original landlord (who requested reversal) that reversal is complete
        if ($originalLandlord) {
            try {
                $originalLandlord->notify(new TransferReversalCompleted($transfer, $reversalTransfer, $admin));
            } catch (\Exception $e) {
                Log::warning('Failed to notify original landlord about reversal completion: ' . $e->getMessage());
            }
        }
        
        // Notify the wrong landlord (who lost the property) that reversal is complete
        if ($wrongLandlord) {
            try {
                $wrongLandlord->notify(new TransferReversalCompleted($transfer, $reversalTransfer, $admin, 'current_owner'));
            } catch (\Exception $e) {
                Log::warning('Failed to notify wrong landlord about reversal completion: ' . $e->getMessage());
            }
        }
        
        // STEP 5: Log the activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transfer_reversed',
            'description' => "Property transfer #{$transfer->id} reversed. Property {$property->property_name} returned to {$originalLandlord->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'transfer_id' => $transfer->id,
                'reversal_transfer_id' => $reversalTransfer->id,
                'property_id' => $property->id,
                'original_landlord' => $originalLandlordId,
                'wrong_landlord' => $wrongLandlordId,
                'units_reversed' => $unitsReversed,
                'reason' => $transfer->reversal_reason
            ]
        ]);
        
        return [
            'success' => true,
            'units_reversed' => $unitsReversed,
            'reversal_transfer_id' => $reversalTransfer->id,
            'property_name' => $property->property_name,
            'original_landlord' => $originalLandlord->name,
            'wrong_landlord' => $wrongLandlord->name
        ];
    }

    /**
     * Check for expired reversal requests and handle them
     * This should be called by a scheduled job
     */
    public function checkExpiredReversalRequests()
    {
        $expiredRequests = PropertyOwnershipTransfer::where('reversal_status', 'pending')
            ->where('reversal_deadline', '<', now())
            ->get();
        
        foreach ($expiredRequests as $transfer) {
            DB::beginTransaction();
            
            try {
                $transfer->update([
                    'reversal_status' => 'expired',
                    'reversal_processed_at' => now(),
                    'reversal_processed_by' => null,
                    'metadata' => array_merge($transfer->metadata ?? [], [
                        'reversal_expiry' => [
                            'expired_at' => now()->toISOString(),
                            'deadline' => $transfer->reversal_deadline->toISOString(),
                            'reason' => 'Request expired before admin review'
                        ]
                    ])
                ]);
                
                // SEND NOTIFICATION
                // Notify the landlord who requested reversal that their request expired
                if ($transfer->currentLandlord) {
                    try {
                        $transfer->currentLandlord->notify(new TransferReversalExpired($transfer));
                    } catch (\Exception $e) {
                        Log::warning('Failed to notify landlord about expired request: ' . $e->getMessage());
                    }
                }
                
                // Notify admins that a request expired
                $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new TransferReversalExpired($transfer, true));
                    } catch (\Exception $e) {
                        Log::warning('Failed to notify admin about expired request: ' . $e->getMessage());
                    }
                }
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to process expired reversal request for transfer #' . $transfer->id . ': ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true,
            'processed' => $expiredRequests->count(),
            'message' => "Processed {$expiredRequests->count()} expired reversal requests"
        ]);
    }

    /**
     * Notify parties about reversal
     */
    private function notifyPartiesOfReversal(PropertyOwnershipTransfer $transfer, array $reversalResult)
    {
        // Notify original landlord (owner who requested reversal)
        if ($transfer->currentLandlord) {
            $transfer->currentLandlord->notify(new TransferStatusNotification($transfer, 'reversal_completed_owner'));
        }
        
        // Notify wrong landlord (who had the property briefly)
        if ($transfer->newLandlord) {
            $transfer->newLandlord->notify(new TransferStatusNotification($transfer, 'reversal_completed_receiver'));
        }
    }

    /**
     * Notify admins about new reversal request
     */
    private function notifyAdminsOfReversalRequest(PropertyOwnershipTransfer $transfer, $isResubmission = false)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->get();
        
        foreach ($admins as $admin) {
            try {
                $admin->notify(new TransferStatusNotification($transfer, $isResubmission ? 'reversal_resubmitted' : 'reversal_requested'));
            } catch (\Exception $e) {
                Log::warning('Failed to notify admin about reversal request: ' . $e->getMessage());
            }
        }
    }

    /**
     * Process a reversal (execute the actual reversal)
     */
    public function processReversal(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        
        if ($transfer->reversal_status !== 'approved') {
            return $this->errorResponse($request, 'This reversal request is not approved.');
        }
        
        try {
            $result = $this->executeTransferReversal($transfer, $request);
            
            return response()->json([
                'success' => true,
                'message' => 'Transfer reversal completed successfully.',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($request, 'Failed to process reversal: ' . $e->getMessage());
        }
    }

    /**
     * Manually trigger yearly archive (Admin action)
     */
    public function triggerYearlyArchive(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . now()->year,
            'include_reversals' => 'boolean',
            'dry_run' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        
        $year = $request->input('year', now()->subYear()->year);
        $includeReversals = $request->boolean('include_reversals', true);
        $dryRun = $request->boolean('dry_run', false);
        
        try {
            $archiveService = app(\App\Services\AutomatedArchiveService::class);
            
            $result = $archiveService->archiveYear($year, [
                'include_reversals' => $includeReversals,
                'soft_delete_after_archive' => !$dryRun,
                'force' => true
            ]);
            
            if ($request->expectsJson()) {
                return response()->json($result);
            }
            
            if ($dryRun) {
                return redirect()->back()->with('info', 
                    "DRY RUN: Would archive {$result['archived_count']} records for year {$year}. " .
                    "({$result['regular_count']} regular, {$result['reversal_count']} reversal)"
                );
            }
            
            return redirect()->back()->with('success', $result['message']);
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to trigger archive: ' . $e->getMessage());
        }
    }

}