{{-- landlord/ownership-transfers/show.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\SystemSetting;
    
    $pageTitle = "Transfer Details - {$transfer->property->property_name}";
    $isSender = $transfer->current_landlord_id === auth()->id();
    $isReceiver = $transfer->new_landlord_id === auth()->id();
    
    // Status configuration
    $statusConfig = [
        PropertyOwnershipTransfer::STATUS_PENDING => ['class' => 'badge-warning', 'icon' => 'clock', 'label' => 'Pending Review'],
        PropertyOwnershipTransfer::STATUS_APPROVED => ['class' => 'badge-success', 'icon' => 'check-circle', 'label' => 'Approved'],
        PropertyOwnershipTransfer::STATUS_REJECTED => ['class' => 'badge-danger', 'icon' => 'times-circle', 'label' => 'Rejected'],
        PropertyOwnershipTransfer::STATUS_COMPLETED => ['class' => 'badge-info', 'icon' => 'check-double', 'label' => 'Completed'],
        PropertyOwnershipTransfer::STATUS_CANCELLED => ['class' => 'badge-secondary', 'icon' => 'ban', 'label' => 'Cancelled'],
    ];
    $currentStatus = $statusConfig[$transfer->status] ?? $statusConfig[PropertyOwnershipTransfer::STATUS_PENDING];
    
    // Document types
    $documentTypes = PropertyOwnershipTransfer::getDocumentTypes();
    
    // Format dates
    $transferDate = $transfer->transfer_date ? $transfer->transfer_date->format('F j, Y') : 'Not specified';
    $requestedDate = $transfer->created_at->format('F j, Y \a\t g:i A');
    $approvedDate = $transfer->approved_at ? $transfer->approved_at->format('F j, Y \a\t g:i A') : null;
    $rejectedDate = $transfer->rejected_at ? $transfer->rejected_at->format('F j, Y \a\t g:i A') : null;
    $completedDate = $transfer->completed_at ? $transfer->completed_at->format('F j, Y \a\t g:i A') : null;
    
    // Calculate days pending
    $daysPending = null;
    if ($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING) {
        $daysPending = $transfer->created_at->diffInDays(now());
    }
    
    // Get property units with tenants
    $units = $transfer->property->units ?? collect();
    $unitsWithTenants = $units->filter(function($unit) {
        return !is_null($unit->tenant_id);
    });
    
    // Get resubmission info
    $canResubmit = $transfer->canBeResubmitted();
    $resubmittedToId = $transfer->metadata['resubmitted_to'] ?? null;
    $resubmittedFromId = $transfer->metadata['resubmitted_from'] ?? null;
    $originalTransfer = $resubmittedFromId ? PropertyOwnershipTransfer::find($resubmittedFromId) : null;
    $resubmittedTransfer = $resubmittedToId ? PropertyOwnershipTransfer::find($resubmittedToId) : null;
    
    // Get related transfers (bulk transfer siblings)
    $relatedTransfers = collect();
    if ($transfer->is_bulk_transfer && isset($transfer->metadata['bulk_property_ids'])) {
        $relatedTransfers = PropertyOwnershipTransfer::whereIn('property_id', $transfer->metadata['bulk_property_ids'])
            ->where('id', '!=', $transfer->id)
            ->with('property')
            ->get();
    }
    
    // ==============================================
    // UPDATED TIMELINE WITH COMPLETE REVERSAL EVENTS
    // ==============================================
    $timelineArray = [];
    
    // 1. Transfer Request Submitted
    $timelineArray[] = [
        'event' => 'Transfer Request Submitted', 
        'date' => $transfer->created_at, 
        'description' => 'Your transfer request was submitted for review.', 
        'icon' => 'paper-plane', 
        'color' => 'primary'
    ];
    
    // 2. Transfer Approved (if applicable)
    if ($transfer->approved_at) {
        $timelineArray[] = [
            'event' => 'Transfer Approved', 
            'date' => $transfer->approved_at, 
            'description' => 'Your transfer request was approved by an administrator.', 
            'icon' => 'check-circle', 
            'color' => 'success'
        ];
    }
    
    // 3. Transfer Rejected (if applicable)
    if ($transfer->rejected_at) {
        $timelineArray[] = [
            'event' => 'Transfer Rejected', 
            'date' => $transfer->rejected_at, 
            'description' => 'Your transfer request was rejected. Reason: ' . ($transfer->rejection_reason ?? 'Not provided'), 
            'icon' => 'times-circle', 
            'color' => 'danger'
        ];
    }
    
    // 4. Transfer Completed (if applicable)
    if ($transfer->completed_at) {
        $timelineArray[] = [
            'event' => 'Transfer Completed', 
            'date' => $transfer->completed_at, 
            'description' => 'Property ownership has been successfully transferred.', 
            'icon' => 'check-double', 
            'color' => 'success'
        ];
    }
    
    // 5. Transfer Cancelled (if applicable)
    if ($transfer->status === PropertyOwnershipTransfer::STATUS_CANCELLED && isset($transfer->metadata['cancelled_at'])) {
        $timelineArray[] = [
            'event' => 'Transfer Cancelled', 
            'date' => $transfer->metadata['cancelled_at'], 
            'description' => 'Your transfer request was cancelled.', 
            'icon' => 'ban', 
            'color' => 'secondary'
        ];
    }
    
    // ==============================================
    // REVERSAL EVENTS (NEW - FIXED)
    // ==============================================
    
    // 6. Reversal Requested (if applicable)
    if ($transfer->reversal_requested_at) {
        $timelineArray[] = [
            'event' => 'Reversal Requested', 
            'date' => $transfer->reversal_requested_at, 
            'description' => 'You requested a reversal of this transfer. Reason: ' . ($transfer->reversal_reason ?? 'Not provided'), 
            'icon' => 'undo-alt', 
            'color' => 'warning'
        ];
    }
    
    // 7. Reversal Approved (if applicable)
    if ($transfer->reversal_status === 'approved' && $transfer->reversal_processed_at) {
        $timelineArray[] = [
            'event' => 'Reversal Approved', 
            'date' => $transfer->reversal_processed_at, 
            'description' => 'Your reversal request was approved by an administrator.', 
            'icon' => 'check-circle', 
            'color' => 'success'
        ];
    }
    
    // 8. Reversal Rejected (if applicable)
    if ($transfer->reversal_status === 'rejected' && $transfer->reversal_processed_at) {
        $timelineArray[] = [
            'event' => 'Reversal Rejected', 
            'date' => $transfer->reversal_processed_at, 
            'description' => 'Your reversal request was rejected. Reason: ' . ($transfer->reversal_admin_notes ?? 'Not provided'), 
            'icon' => 'times-circle', 
            'color' => 'danger'
        ];
    }
    
    // 9. Reversal Expired (if applicable)
    if ($transfer->reversal_status === 'expired') {
        $expiredDate = $transfer->reversal_processed_at ?? $transfer->updated_at;
        $timelineArray[] = [
            'event' => 'Reversal Request Expired', 
            'date' => $expiredDate, 
            'description' => 'Your reversal request expired before admin review.', 
            'icon' => 'clock', 
            'color' => 'warning'
        ];
    }
    
    // 10. Reversal Completed (if applicable)
    if ($transfer->is_reversed && $transfer->reversal_status === 'completed') {
        $completionDate = $transfer->reversal_processed_at ?? $transfer->updated_at;
        $timelineArray[] = [
            'event' => 'Reversal Completed', 
            'date' => $completionDate, 
            'description' => 'The transfer has been successfully reversed. Property ownership has been returned to you.', 
            'icon' => 'check-double', 
            'color' => 'success'
        ];
    }
    
    // Sort timeline by date
    $timeline = collect($timelineArray)->sortBy('date')->values();
    
    // ==============================================
    // TRANSFER REVERSAL VARIABLES
    // ==============================================
    $reversalEnabled = config('ownership_transfer.reversal.enabled', true);
    $reversalWindowDays = config('ownership_transfer.reversal.reversal_window_days', 30);
    $canRequestReversal = $reversalEnabled && 
                          $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && 
                          !$transfer->is_reversed && 
                          $transfer->reversal_status !== 'pending' &&
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
    
    // Reversal status messages
    $reversalStatusMessages = [
        'pending' => ['class' => 'badge-warning', 'icon' => 'clock', 'message' => 'Your reversal request is pending admin review.'],
        'approved' => ['class' => 'badge-success', 'icon' => 'check-circle', 'message' => 'Your reversal request has been approved. The property is being returned to you.'],
        'rejected' => ['class' => 'badge-danger', 'icon' => 'times-circle', 'message' => 'Your reversal request was rejected.'],
        'completed' => ['class' => 'badge-info', 'icon' => 'check-double', 'message' => 'The transfer has been successfully reversed.'],
        'expired' => ['class' => 'badge-secondary', 'icon' => 'clock', 'message' => 'Your reversal request expired before admin review.'],
    ];
    $reversalStatusInfo = $reversalStatusMessages[$transfer->reversal_status] ?? null;
    
    // Get system settings for support contact
    try {
        $systemSettings = SystemSetting::getSettings();
        $systemEmail = $systemSettings->system_email ?? 'support@example.com';
        $systemPhone = $systemSettings->system_phone ?? '+233 123 456 789';
        $systemPhoneClean = preg_replace('/[^0-9+]/', '', $systemPhone);
    } catch (\Exception $e) {
        $systemEmail = 'support@example.com';
        $systemPhone = '+233 123 456 789';
        $systemPhoneClean = '+233123456789';
    }
@endphp

@extends('layouts.landlord')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    
    <!-- ============================================= -->
    <!-- SUCCESS MESSAGES SECTION -->
    <!-- ============================================= -->
    
    @if(session('success'))
    <div class="alert-success-card" id="successAlert" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 12px;">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--success);">Success!</h4>
                        <p class="text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
                    </div>
                </div>
                <button onclick="document.getElementById('successAlert').style.display='none'" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif
    
    @if(session('error'))
    <div class="alert-error-card" id="errorAlert" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 12px;">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        <i class="fas fa-exclamation-circle text-xl" style="color: var(--danger);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--danger);">Error!</h4>
                        <p class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
                    </div>
                </div>
                <button onclick="document.getElementById('errorAlert').style.display='none'" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif
    
    @if(session('reversal_requested'))
    <div class="alert-info-card" id="reversalAlert" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3); border-radius: 12px;">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        <i class="fas fa-undo-alt text-xl" style="color: var(--info);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--info);">Reversal Request Submitted!</h4>
                        <p class="text-sm" style="color: var(--text-primary);">{{ session('success') ?? 'Your reversal request has been submitted successfully. An administrator will review it within 48 hours.' }}</p>
                    </div>
                </div>
                <button onclick="document.getElementById('reversalAlert').style.display='none'" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif
    
    @if($errors->any())
    <div class="alert-error-card" id="validationAlert" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 12px;">
        <div class="p-4">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <div class="flex-shrink-0 mr-3">
                        <i class="fas fa-exclamation-triangle text-xl" style="color: var(--danger);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--danger);">Validation Errors</h4>
                        <ul class="text-sm mt-1" style="color: var(--text-primary);">
                            @foreach($errors->all() as $error)
                                <li>• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button onclick="document.getElementById('validationAlert').style.display='none'" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-exchange-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i> 
                        Transfer Request Details
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $currentStatus['class'] }}">
                            <i class="fas fa-{{ $currentStatus['icon'] }} mr-1"></i>
                            {{ $currentStatus['label'] }}
                        </span>
                        @if($transfer->is_reversed)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-undo-alt mr-1"></i> REVERSED
                        </span>
                        @endif
                        @if($isSender)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-paper-plane mr-1"></i> You are the sender
                            </span>
                        @endif
                        @if($isReceiver)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-inbox mr-1"></i> You are the receiver
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-1"></i>
                        <span>{{ $transfer->property->property_name ?? 'N/A' }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-hashtag mr-1"></i>
                        <span>{{ $transfer->document_reference }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>Requested: {{ $requestedDate }}</span>
                    </div>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('properties.show', $transfer->property_id) }}" 
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-building mr-2"></i> View Property
                </a>
                <a href="{{ route('landlord.ownership-transfers.index') }}" 
                   class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Transfers
                </a>
            </div>
        </div>
    </div>

    <!-- Days Pending Warning -->
    @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $daysPending && $daysPending > 7)
    <div class="card" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="p-4">
            <div class="flex items-center">
                <i class="fas fa-clock text-xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Awaiting Review ({{ $daysPending }} days)</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Your transfer request has been pending for {{ $daysPending }} days. 
                        If you haven't received an update soon, please contact support.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Resubmission Info -->
    @if($resubmittedFromId && $originalTransfer)
    <div class="card" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <div class="p-4">
            <div class="flex items-center">
                <i class="fas fa-redo text-xl mr-3" style="color: var(--info);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">This is a Resubmission</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This transfer was resubmitted from a rejected request.
                        <a href="{{ route('properties.ownership-transfers.show', [$originalTransfer->property_id, $originalTransfer->id]) }}" 
                           class="font-medium hover:underline" style="color: var(--primary);">
                            View original rejected transfer #{{ $originalTransfer->id }}
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($resubmittedToId && $resubmittedTransfer)
    <div class="card" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
        <div class="p-4">
            <div class="flex items-center">
                <i class="fas fa-share text-xl mr-3" style="color: var(--success);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">This Transfer Has Been Resubmitted</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This rejected transfer was resubmitted as a new request.
                        <a href="{{ route('properties.ownership-transfers.show', [$resubmittedTransfer->property_id, $resubmittedTransfer->id]) }}" 
                           class="font-medium hover:underline" style="color: var(--primary);">
                            View resubmitted transfer #{{ $resubmittedTransfer->id }}
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================= -->
    <!-- TRANSFER REVERSAL SECTION (UPDATED) -->
    <!-- ============================================= -->
    
    <!-- Request Reversal Button (for sender on completed transfers) -->
    @if($canRequestReversal)
    <div class="card" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-undo-alt text-xl" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Transferred to the wrong person?
                    </h3>
                    <p class="text-sm mt-2" style="color: var(--text-secondary);">
                        If you transferred this property to the wrong person or made an accidental transfer,
                        you can request a reversal. This request will be reviewed by an administrator.
                    </p>
                    @if($reversalWindowDays > 0 && $transfer->completed_at)
                    <p class="text-xs mt-2" style="color: var(--info);">
                        <i class="fas fa-clock mr-1"></i>
                        You have {{ max(0, $reversalWindowDays - $transfer->completed_at->diffInDays(now())) }} days left to request a reversal.
                    </p>
                    @endif
                    <div class="mt-4">
                        <button type="button" 
                                onclick="showReversalRequestModal()"
                                class="btn-warning px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-undo-alt mr-2"></i> Request Transfer Reversal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Pending Reversal Request Status -->
    @if($transfer->reversal_status === 'pending')
    <div class="card" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <div class="p-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-clock text-xl" style="color: var(--info);"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold" style="color: var(--info);">Reversal Request Pending</h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Your reversal request has been submitted and is awaiting admin review.
                        </p>
                        @if($transfer->reversal_deadline)
                        <p class="text-xs mt-2" style="color: var(--warning);">
                            <i class="fas fa-hourglass-half mr-1"></i>
                            Deadline for admin review: {{ $transfer->reversal_deadline->format('F j, Y') }}
                        </p>
                        @endif
                        @if($transfer->reversal_reason)
                        <p class="text-sm mt-2" style="color: var(--text-secondary);">
                            <strong>Reason provided:</strong> {{ $transfer->reversal_reason }}
                        </p>
                        @endif
                    </div>
                </div>
                @if($canCancelReversal)
                <button type="button" 
                        onclick="confirmCancelReversal()"
                        class="btn-secondary px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Cancel Request
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Approved Reversal Request Status -->
    @if($transfer->reversal_status === 'approved')
    <div class="card" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.3);">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--success);">Reversal Request Approved</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Your reversal request has been approved. The property is being returned to you.
                        This process may take a few minutes to complete.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Rejected Reversal Request Status -->
    @if($transfer->reversal_status === 'rejected')
    <div class="card" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.3);">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-times-circle text-xl" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">Reversal Request Rejected</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Your reversal request was rejected by the administrator.
                    </p>
                    @if($transfer->reversal_admin_notes)
                    <p class="text-sm mt-2" style="color: var(--text-secondary);">
                        <strong>Admin notes:</strong> {{ $transfer->reversal_admin_notes }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Expired Reversal Request Status -->
    @if($transfer->reversal_status === 'expired')
    <div class="card" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-clock text-xl" style="color: var(--secondary);"></i>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--secondary);">Reversal Request Expired</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Your reversal request expired before an administrator could review it.
                        If you still need to reverse this transfer, please submit a new request.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Reversal Completed Status -->
    @if($transfer->is_reversed)
    <div class="card" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.3);">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-undo-alt text-xl" style="color: var(--success);"></i>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--success);">Transfer Reversed Successfully</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This transfer has been reversed. The property ownership has been returned to the original owner.
                    </p>
                    @if($transfer->reversal_transfer_id)
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-file-alt mr-1"></i>
                        Reversal reference: #{{ $transfer->reversal_transfer_id }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Transfer Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Property Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i> Property Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Property Name</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $transfer->property->property_name ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Registration Number</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $transfer->property->registration_pattern ?? 'N/A' }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="text-sm" style="color: var(--text-secondary);">Address</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $transfer->property->street_name ?? '' }}, {{ $transfer->property->zone ?? '' }}, {{ $transfer->property->city ?? '' }}
                        </div>
                    </div>
                    @if($units && $units->count() > 0)
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Total Units</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $units->count() }}</div>
                    </div>
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Units with Tenants</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $unitsWithTenants->count() }}</div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Transfer Details Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Transfer Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Document Reference</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $transfer->document_reference }}</div>
                    </div>
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Document Type</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $documentTypes[$transfer->document_type] ?? $transfer->document_type }}</div>
                    </div>
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Transfer Date</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $transferDate }}</div>
                    </div>
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Sale Amount</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            @if($transfer->sale_amount)
                                GHS {{ number_format($transfer->sale_amount, 2) }}
                            @else
                                <span class="text-secondary">Not specified</span>
                            @endif
                        </div>
                    </div>
                    @if($transfer->reason_for_transfer)
                    <div class="md:col-span-2">
                        <div class="text-sm" style="color: var(--text-secondary);">Reason for Transfer</div>
                        <div class="mt-1 p-3 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            {{ $transfer->reason_for_transfer }}
                        </div>
                    </div>
                    @endif
                    @if($transfer->notes)
                    <div class="md:col-span-2">
                        <div class="text-sm" style="color: var(--text-secondary);">Additional Notes</div>
                        <div class="mt-1 p-3 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            {{ $transfer->notes }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Owner Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i> Owner Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Current Owner -->
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-user-circle mr-2" style="color: var(--info);"></i>
                            <h4 class="font-semibold" style="color: var(--text-primary);">Current Owner</h4>
                        </div>
                        <div class="space-y-2">
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Name</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->currentLandlord->name ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Email</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->currentLandlord->email ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Phone</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->currentLandlord->phone ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- New Owner -->
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-user-plus mr-2" style="color: var(--success);"></i>
                            <h4 class="font-semibold" style="color: var(--text-primary);">New Owner</h4>
                            @if($transfer->new_landlord_id)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> Registered User
                                </span>
                            @else
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-user-plus mr-1"></i> New Registration
                                </span>
                            @endif
                        </div>
                        <div class="space-y-2">
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Name</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->new_owner_name }}</div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Phone</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->new_owner_phone }}</div>
                            </div>
                            @if($transfer->new_owner_email)
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Email</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->new_owner_email }}</div>
                            </div>
                            @endif
                            @if($transfer->new_owner_address)
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Address</div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $transfer->new_owner_address }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i> Documents
                </h3>
                <div class="flex flex-wrap gap-4">
                    @if($transfer->document_url)
                    <a href="{{ route('properties.ownership-transfers.download', [$transfer->property_id, $transfer->id]) }}" 
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-download mr-2"></i> Download Transfer Document
                    </a>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->certificate_url)
                    <a href="{{ route('properties.ownership-transfers.certificate', [$transfer->property_id, $transfer->id]) }}" 
                       class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-certificate mr-2"></i> Download Transfer Certificate
                    </a>
                    @endif
                </div>
                
                @if(!$transfer->document_url && !($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->certificate_url))
                <p class="text-sm" style="color: var(--text-secondary);">No documents available for download at this time.</p>
                @endif
            </div>

            <!-- Rejection Reason Card (if rejected) -->
            @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->rejection_reason)
            <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--danger);">
                    <i class="fas fa-times-circle mr-2"></i> Rejection Reason
                </h3>
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <p style="color: var(--text-primary);">{{ $transfer->rejection_reason }}</p>
                    @if($transfer->admin_notes)
                    <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                        <strong>Admin Notes:</strong> {{ $transfer->admin_notes }}
                    </p>
                    @endif
                    @if($transfer->rejected_at)
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        Rejected on: {{ $rejectedDate }}
                        @if($transfer->rejectedBy)
                            by {{ $transfer->rejectedBy->name }}
                        @endif
                    </p>
                    @endif
                </div>
                
                @if($canResubmit && $isSender)
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-redo mr-1"></i> You can resubmit this transfer request with corrections.
                            </p>
                            @if($transfer->can_resubmit_after && $transfer->can_resubmit_after->isFuture())
                            <p class="text-xs mt-1" style="color: var(--warning);">
                                Resubmission available after {{ $transfer->can_resubmit_after->format('F j, Y') }}
                            </p>
                            @endif
                        </div>
                        @if(!$transfer->can_resubmit_after || !$transfer->can_resubmit_after->isFuture())
                        <a href="{{ route('properties.ownership-transfers.resubmit', [$transfer->property_id, $transfer->id]) }}" 
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-redo mr-2"></i> Resubmit Request
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif

            <!-- Admin Notes Card (if any) -->
            @if($transfer->admin_notes && $transfer->status !== PropertyOwnershipTransfer::STATUS_REJECTED)
            <div class="card p-6" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sticky-note mr-2" style="color: var(--info);"></i> Admin Notes
                </h3>
                <p style="color: var(--text-primary);">{{ $transfer->admin_notes }}</p>
            </div>
            @endif

            <!-- Bulk Transfer Related Transfers -->
            @if($relatedTransfers && $relatedTransfers->count() > 0)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i> Related Transfers (Bulk Transfer)
                </h3>
                <div class="space-y-3">
                    @foreach($relatedTransfers as $relatedTransfer)
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">{{ $relatedTransfer->property->property_name ?? 'N/A' }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">Ref: {{ $relatedTransfer->document_reference }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-1 text-xs rounded-full {{ $statusConfig[$relatedTransfer->status]['class'] ?? 'badge-secondary' }}">
                                {{ $statusConfig[$relatedTransfer->status]['label'] ?? ucfirst($relatedTransfer->status) }}
                            </span>
                            <a href="{{ route('properties.ownership-transfers.show', [$relatedTransfer->property_id, $relatedTransfer->id]) }}" 
                               class="text-primary hover:underline text-sm">
                                View <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column - Status & Timeline -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i> Transfer Status
                </h3>
                
                <div class="text-center p-4 rounded-lg mb-4 {{ $currentStatus['class'] }}" 
                     style="background-color: rgba({{ $currentStatus['class'] === 'badge-warning' ? 'var(--warning-rgb)' : ($currentStatus['class'] === 'badge-success' ? 'var(--success-rgb)' : ($currentStatus['class'] === 'badge-danger' ? 'var(--danger-rgb)' : 'var(--info-rgb)')) }}, 0.1);">
                    <i class="fas fa-{{ $currentStatus['icon'] }} text-3xl mb-2" style="color: {{ $currentStatus['class'] === 'badge-warning' ? 'var(--warning)' : ($currentStatus['class'] === 'badge-success' ? 'var(--success)' : ($currentStatus['class'] === 'badge-danger' ? 'var(--danger)' : 'var(--info)')) }};"></i>
                    <h4 class="text-xl font-bold" style="color: {{ $currentStatus['class'] === 'badge-warning' ? 'var(--warning)' : ($currentStatus['class'] === 'badge-success' ? 'var(--success)' : ($currentStatus['class'] === 'badge-danger' ? 'var(--danger)' : 'var(--info)')) }};">
                        {{ $currentStatus['label'] }}
                    </h4>
                </div>
                
                <div class="space-y-3">
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING)
                        <div class="flex items-center justify-between text-sm">
                            <span style="color: var(--text-secondary);">Waiting time:</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $daysPending ?? 0 }} days</span>
                        </div>
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your request is being reviewed by an administrator. You will be notified once a decision is made.
                            </p>
                        </div>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle mr-1"></i>
                                Your transfer has been approved. The new owner will receive an invitation to complete the transfer.
                            </p>
                        </div>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && !$transfer->is_reversed)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-check-double mr-1"></i>
                                Transfer completed successfully on {{ $completedDate }}.
                            </p>
                        </div>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-times-circle mr-1"></i>
                                Your transfer request was rejected on {{ $rejectedDate }}.
                            </p>
                        </div>
                    @endif
                    
                    @if($transfer->is_reversed)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--danger);">
                                <i class="fas fa-undo-alt mr-1"></i>
                                This transfer has been reversed. Property ownership has been restored to the original owner.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Timeline Card - UPDATED with full reversal events -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Timeline
                </h3>
                
                @if($timeline->isNotEmpty())
                    <div class="relative pl-4">
                        @foreach($timeline as $index => $event)
                            <div class="relative pb-8 {{ $loop->last ? '' : 'border-l-2' }}" 
                                 style="border-color: var(--border-color); margin-left: 12px;">
                                
                                <!-- Timeline Node -->
                                <div class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full shadow-md" 
                                     style="background-color: {{ $event['color'] === 'primary' ? 'var(--primary)' : 
                                            ($event['color'] === 'success' ? 'var(--success)' : 
                                            ($event['color'] === 'danger' ? 'var(--danger)' : 
                                            ($event['color'] === 'warning' ? 'var(--warning)' : 'var(--info)'))) }}; 
                                            color: white;">
                                    <i class="fas fa-{{ $event['icon'] }} text-xs"></i>
                                </div>
                                
                                <!-- Timeline Content -->
                                <div class="ml-6">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-sm" style="color: var(--text-primary);">
                                            {{ $event['event'] }}
                                        </span>
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            {{ $event['date'] instanceof \Carbon\Carbon ? $event['date']->format('M j, Y g:i A') : \Carbon\Carbon::parse($event['date'])->format('M j, Y g:i A') }}
                                        </span>
                                    </div>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary); line-height: 1.4;">
                                        {!! nl2br(e($event['description'])) !!}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3"
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-history text-xl" style="color: var(--info);"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">No timeline events available for this transfer.</p>
                    </div>
                @endif
            </div>

            <!-- Reversal Information Card (if reversal was requested) -->
            @if($transfer->reversal_requested_at)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--warning);"></i> Reversal Information
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span style="color: var(--text-secondary);">Requested:</span>
                        <span class="font-medium" style="color: var(--text-primary);">
                            {{ $transfer->reversal_requested_at->format('F j, Y \a\t g:i A') }}
                        </span>
                    </div>
                    
                    @if($transfer->reversal_reason)
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Reason:</div>
                        <div class="mt-1 p-2 rounded-lg text-sm" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            {{ $transfer->reversal_reason }}
                        </div>
                    </div>
                    @endif
                    
                    <div class="flex justify-between items-center text-sm">
                        <span style="color: var(--text-secondary);">Status:</span>
                        <span class="px-2 py-1 text-xs rounded-full 
                            @if($transfer->reversal_status === 'pending') badge-warning
                            @elseif($transfer->reversal_status === 'approved') badge-success
                            @elseif($transfer->reversal_status === 'rejected') badge-danger
                            @elseif($transfer->reversal_status === 'completed') badge-info
                            @elseif($transfer->reversal_status === 'expired') badge-secondary
                            @endif">
                            {{ ucfirst($transfer->reversal_status ?? 'N/A') }}
                        </span>
                    </div>
                    
                    @if($transfer->reversal_deadline && $transfer->reversal_status === 'pending')
                    <div class="flex justify-between items-center text-sm">
                        <span style="color: var(--text-secondary);">Decision Deadline:</span>
                        <span class="font-medium" style="color: {{ $transfer->reversal_deadline < now() ? 'var(--danger)' : 'var(--text-primary)' }}">
                            {{ $transfer->reversal_deadline->format('F j, Y \a\t g:i A') }}
                            @if($transfer->reversal_deadline < now())
                                (Expired)
                            @endif
                        </span>
                    </div>
                    @endif
                    
                    @if($transfer->reversal_status === 'rejected' && $transfer->reversal_admin_notes)
                    <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm" style="color: var(--danger);">Rejection Reason:</div>
                        <div class="mt-1 text-sm" style="color: var(--text-secondary);">
                            {{ $transfer->reversal_admin_notes }}
                        </div>
                    </div>
                    @endif
                    
                    @if($transfer->is_reversed && $transfer->reversal_status === 'completed')
                    <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            <span class="text-sm" style="color: var(--success);">Reversal completed successfully</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Action Buttons Card -->
            @if(($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $isSender) || 
                ($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED && $isSender) ||
                ($canResubmit && $isSender))
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Actions
                </h3>
                <div class="space-y-3">
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $isSender)
                        <button type="button" 
                                onclick="showCancelModal()"
                                class="w-full btn-danger px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i> Cancel Transfer Request
                        </button>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED && $isSender && $transfer->new_owner_email)
                        <button type="button" 
                                onclick="resendNotification()"
                                class="w-full btn-info px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                            <i class="fas fa-paper-plane mr-2"></i> Resend Notification to New Owner
                        </button>
                    @endif
                    
                    @if($canResubmit && $isSender && (!$transfer->can_resubmit_after || !$transfer->can_resubmit_after->isFuture()))
                        <a href="{{ route('properties.ownership-transfers.resubmit', [$transfer->property_id, $transfer->id]) }}" 
                           class="w-full btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                            <i class="fas fa-redo mr-2"></i> Resubmit Request
                        </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Support Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-headset mr-2" style="color: var(--primary);"></i> Need Help?
                    </h3>
                    <div class="text-sm space-y-3" style="color: var(--text-secondary);">
                        <p>If you have questions about the transfer process:</p>
                        <ul class="space-y-2">
                            <li class="flex items-center">
                                <i class="fas fa-envelope w-5" style="color: var(--primary);"></i>
                                <a href="mailto:{{ $systemEmail }}" class="ml-2 hover:underline" style="color: var(--primary);">
                                    {{ $systemEmail }}
                                </a>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-phone w-5" style="color: var(--primary);"></i>
                                <a href="tel:{{ $systemPhoneClean }}" class="ml-2 hover:underline" style="color: var(--primary);">
                                    {{ $systemPhone }}
                                </a>
                            </li>
                        </ul>
                        <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="flex items-start">
                                <i class="fas fa-clock mr-2 mt-0.5" style="color: var(--info);"></i>
                                <div>
                                    <p class="text-xs">Support Hours: <strong>Mon-Fri, 9:00 AM - 5:00 PM</strong></p>
                                    <p class="text-xs mt-1">Response time: <strong>Within 24 hours</strong></p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2">
                            <p class="text-xs">
                                Please reference your transfer ID: <strong>{{ $transfer->id }}</strong> 
                                and document reference: <strong>{{ $transfer->document_reference }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Transfer Modal -->
<div id="cancelModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideCancelModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Transfer Request
                </h3>
                <button type="button" onclick="hideCancelModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="cancelForm" method="POST" action="{{ route('properties.ownership-transfers.cancel', [$transfer->property_id, $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-4" style="color: var(--text-secondary);">
                        Are you sure you want to cancel this ownership transfer request? This action cannot be undone.
                    </p>
                    <div class="mb-4">
                        <label for="cancel_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for cancellation (Optional)
                        </label>
                        <textarea name="notes" 
                                  id="cancel_notes"
                                  rows="3"
                                  class="index-custom-textarea w-full"
                                  placeholder="Please provide a reason for cancellation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="flex space-x-3">
                        <button type="button" onclick="hideCancelModal()" 
                                class="btn-secondary px-4 py-2 rounded-lg font-medium">
                            No, Go Back
                        </button>
                        <button type="submit" 
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                            <i class="fas fa-check mr-2"></i> Yes, Cancel Transfer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resend Notification Modal -->
<div id="resendModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideResendModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i> Resend Notification
                </h3>
                <button type="button" onclick="hideResendModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="resendForm" method="POST" action="{{ route('api.ownership-transfers.resend-invitation', $transfer->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            This will resend the transfer notification to the new owner at:
                        </p>
                        <p class="font-medium mt-2" style="color: var(--text-primary);">{{ $transfer->new_owner_email ?? $transfer->new_owner_phone }}</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Additional Message (Optional)
                        </label>
                        <textarea name="additional_message" 
                                  rows="3" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Add a personal message to the new owner..."></textarea>
                    </div>
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                The new owner will receive an email with instructions to accept the transfer.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="flex space-x-3">
                        <button type="button" onclick="hideResendModal()" 
                                class="btn-secondary px-4 py-2 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                            <i class="fas fa-paper-plane mr-2"></i> Send Notification
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- TRANSFER REVERSAL REQUEST MODAL -->
<!-- ============================================= -->
<div id="reversalRequestModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideReversalRequestModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--warning);"></i> Request Transfer Reversal
                </h3>
                <button type="button" onclick="hideReversalRequestModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="reversalRequestForm" method="POST" 
                  action="{{ route('properties.ownership-transfers.request-reversal', [$transfer->property_id, $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Reversal <span class="text-danger">*</span>
                        </label>
                        <textarea name="reversal_reason" 
                                  id="reversal_reason"
                                  rows="4" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Please explain why you need to reverse this transfer (e.g., transferred to wrong person, accidental transfer, etc.)"
                                  required></textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Please provide a detailed explanation. This will be reviewed by an administrator.
                        </p>
                    </div>
                    
                    <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Important Notes:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Reversal requests are reviewed by administrators</li>
                                    <li>The new owner will be notified of this request</li>
                                    <li>If approved, the property will be returned to you</li>
                                    <li>This process typically takes 1-3 business days</li>
                                    <li>Once reversed, you may need to create a new transfer request</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   name="confirm_reversal" 
                                   value="1"
                                   id="confirm_reversal"
                                   class="mr-2 w-4 h-4"
                                   required>
                            <span class="text-sm" style="color: var(--text-primary);">
                                I confirm that I want to request a reversal of this property transfer
                            </span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideReversalRequestModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="submitReversalBtn"
                            class="btn-warning px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Reversal Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Theme-aware textarea styles - adapts to user's theme preference */
.index-custom-textarea {
    width: 100%;
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.25);
}

.index-custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Modal and other component styles */
.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--card-bg);
    z-index: 10;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
    z-index: 10;
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.modal-close-btn:hover {
    background-color: rgba(var(--text-secondary-rgb), 0.1);
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #c82333 !important;
    transform: translateY(-1px);
}

.btn-info {
    background-color: var(--info) !important;
    color: white !important;
    transition: all 0.2s ease;
}

.btn-info:hover {
    background-color: #138496 !important;
    transform: translateY(-1px);
}

.btn-warning {
    background-color: var(--warning) !important;
    color: white !important;
    transition: all 0.2s ease;
}

.btn-warning:hover {
    background-color: #e0a800 !important;
    transform: translateY(-1px);
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Auto-hide alerts after 5 seconds */
.alert-success-card, .alert-error-card, .alert-info-card {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-success-card, .alert-error-card, .alert-info-card');
    alerts.forEach(function(alert) {
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }
    });
}, 5000);

function showCancelModal() {
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideCancelModal() {
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function resendNotification() {
    const modal = document.getElementById('resendModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideResendModal() {
    const modal = document.getElementById('resendModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ==============================================
// TRANSFER REVERSAL FUNCTIONS
// ==============================================
function showReversalRequestModal() {
    const modal = document.getElementById('reversalRequestModal');
    if (modal) {
        // Clear form
        document.getElementById('reversal_reason').value = '';
        document.getElementById('confirm_reversal').checked = false;
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideReversalRequestModal() {
    const modal = document.getElementById('reversalRequestModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function confirmCancelReversal() {
    if (confirm('Are you sure you want to cancel your reversal request? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("properties.ownership-transfers.cancel-reversal", [$transfer->property_id, $transfer->id]) }}';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Handle reversal request form submission with loading state
document.getElementById('reversalRequestForm')?.addEventListener('submit', function(e) {
    const confirmCheckbox = document.getElementById('confirm_reversal');
    if (!confirmCheckbox.checked) {
        e.preventDefault();
        alert('Please confirm that you want to request a reversal by checking the confirmation box.');
        return false;
    }
    
    // Show loading state on submit button
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
        submitBtn.disabled = true;
        
        // Store original content to restore if needed (optional)
        submitBtn.setAttribute('data-original-html', originalHtml);
    }
});

// Handle resend form submission
document.getElementById('resendForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalHtml = submitBtn ? submitBtn.innerHTML : '';
    
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
        submitBtn.disabled = true;
    }
    
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Notification resent successfully!');
            hideResendModal();
        } else {
            alert('Failed to resend notification: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while resending the notification.');
    } finally {
        if (submitBtn) {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
        }
    }
});
</script>
@endsection