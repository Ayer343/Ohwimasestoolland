{{-- admin/ownership-transfers/show.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\User;
    use App\Models\SystemSetting;
    
    $pageTitle = "Transfer Details - {$transfer->property->property_name}";
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
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
    
    // Check if existing landlord
    $isExistingLandlord = $transfer->metadata['is_existing_landlord'] ?? false;
    
    // Get readiness report
    $readinessReport = $readinessReport ?? [];
    $hasTenants = ($readinessReport['units_with_tenants'] ?? 0) > 0;
    
    // Check action permissions
    $canApprove = $transfer->canBeApproved();
    $canReject = $transfer->canBeRejected();
    $canComplete = $transfer->canBeCompleted();
    
    // Get related transfers (bulk transfer siblings)
    $relatedTransfers = $relatedTransfers ?? collect();
    
    // Get timeline events with fixes applied
    $timeline = $timeline ?? collect();
    
    // Ensure timeline includes all reversal events by checking directly
    // This is a safety net in case the controller didn't generate all events
    if ($transfer->reversal_status === 'rejected' && $transfer->reversal_processed_at) {
        $hasRejectionEvent = $timeline->contains(function($event) {
            return $event['event'] === 'Reversal Rejected';
        });
        
        if (!$hasRejectionEvent) {
            $rejectionEvent = [
                'event' => 'Reversal Rejected',
                'date' => $transfer->reversal_processed_at,
                'description' => 'Reversal request was rejected by ' . 
                    ($transfer->reversalProcessedBy->name ?? 'Administrator') . 
                    '. Reason: ' . ($transfer->reversal_admin_notes ?? 'Not provided'),
                'icon' => 'times-circle',
                'color' => 'danger'
            ];
            $timeline->push($rejectionEvent);
            $timeline = $timeline->sortBy('date')->values();
        }
    }
    
    if ($transfer->reversal_status === 'expired') {
        $hasExpiredEvent = $timeline->contains(function($event) {
            return $event['event'] === 'Reversal Request Expired';
        });
        
        if (!$hasExpiredEvent) {
            $expiredEvent = [
                'event' => 'Reversal Request Expired',
                'date' => $transfer->reversal_processed_at ?? $transfer->updated_at,
                'description' => 'Reversal request expired before admin review',
                'icon' => 'clock',
                'color' => 'warning'
            ];
            $timeline->push($expiredEvent);
            $timeline = $timeline->sortBy('date')->values();
        }
    }
@endphp

@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
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
                        @if($transfer->is_bulk_transfer)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-layer-group mr-1"></i> Bulk Transfer
                        </span>
                        @endif
                        @if($transfer->is_reversed)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-undo-alt mr-1"></i> Reversed
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
                        <span class="mx-1">•</span>
                        <i class="fas fa-user mr-1"></i>
                        <span>Requested by: {{ $transfer->requestedBy->name ?? $transfer->currentLandlord->name ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('properties.show', $transfer->property_id) }}" 
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-building mr-2"></i> View Property
                </a>
                <a href="{{ route('admin.ownership-transfers.index') }}" 
                   class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Transfers
                </a>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Days Pending Warning -->
    @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $daysPending && $daysPending > 7)
    <div class="card" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="p-4">
            <div class="flex items-center">
                <i class="fas fa-clock text-xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Pending for {{ $daysPending }} days</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This transfer request has been pending for {{ $daysPending }} days. 
                        Please review and take action.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Resubmission Info -->
    @if(isset($transfer->metadata['resubmitted_from']))
    <div class="card" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <div class="p-4">
            <div class="flex items-center">
                <i class="fas fa-redo text-xl mr-3" style="color: var(--info);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">This is a Resubmission</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This transfer was resubmitted from a rejected request.
                        <a href="{{ route('admin.ownership-transfers.show', $transfer->metadata['resubmitted_from']) }}" 
                           class="font-medium hover:underline" style="color: var(--primary);">
                            View original rejected transfer
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(isset($transfer->metadata['resubmitted_to']))
    <div class="card" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
        <div class="p-4">
            <div class="flex items-center">
                <i class="fas fa-share text-xl mr-3" style="color: var(--success);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">This Transfer Has Been Resubmitted</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This rejected transfer was resubmitted as a new request.
                        <a href="{{ route('admin.ownership-transfers.show', $transfer->metadata['resubmitted_to']) }}" 
                           class="font-medium hover:underline" style="color: var(--primary);">
                            View resubmitted transfer
                        </a>
                    </p>
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
                    @if(isset($unitsWithTenants))
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Total Units</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $readinessReport['total_units'] ?? 0 }}</div>
                    </div>
                    <div>
                        <div class="text-sm" style="color: var(--text-secondary);">Units with Tenants</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $readinessReport['units_with_tenants'] ?? 0 }}</div>
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
                            <i class="fas fa-user-tie mr-2" style="color: var(--info);"></i>
                            <h4 class="font-semibold" style="color: var(--text-primary);">Current Owner (Seller)</h4>
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
                            <h4 class="font-semibold" style="color: var(--text-primary);">New Owner (Buyer)</h4>
                            @if($isExistingLandlord)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> Existing User
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
                            @if($isExistingLandlord && $transfer->newLandlord)
                            <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                <div class="text-xs" style="color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> This user already has an account. No invitation needed.
                                </div>
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
                    <a href="{{ route('admin.ownership-transfers.download-document', $transfer->id) }}" 
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-download mr-2"></i> Download Transfer Document
                    </a>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->certificate_url)
                    <a href="{{ route('admin.ownership-transfers.download-certificate', $transfer->id) }}" 
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
            </div>
            @endif

            <!-- Reversal Rejection Reason Card (if reversal was rejected) -->
            @if($transfer->reversal_status === 'rejected' && $transfer->reversal_admin_notes)
            <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--danger);">
                    <i class="fas fa-undo-alt mr-2"></i> Reversal Rejection Reason
                </h3>
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <p style="color: var(--text-primary);">{{ $transfer->reversal_admin_notes }}</p>
                    @if($transfer->reversal_processed_at)
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        Rejected on: {{ $transfer->reversal_processed_at->format('F j, Y \a\t g:i A') }}
                        @if($transfer->reversalProcessedBy)
                            by {{ $transfer->reversalProcessedBy->name }}
                        @endif
                    </p>
                    @endif
                </div>
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
            @if($relatedTransfers->isNotEmpty())
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
                            <a href="{{ route('admin.ownership-transfers.show', $relatedTransfer->id) }}" 
                               class="text-primary hover:underline text-sm">
                                View <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Tenant Transfer Warning Card (for completion) -->
            @if($hasTenants && $transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
            <div class="card p-6" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--warning);">
                    <i class="fas fa-users mr-2"></i> Tenant Transfer Notice
                </h3>
                <div class="space-y-3">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        This property has <strong>{{ $readinessReport['units_with_tenants'] ?? 0 }} active tenant(s)</strong> in 
                        <strong>{{ $readinessReport['units_with_tenants'] ?? 0 }} unit(s)</strong>.
                    </p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Upon transfer completion, all tenants will automatically be transferred to the new landlord. 
                        Their tenancy agreements remain valid and continue under the new ownership.
                    </p>
                    @if(!empty($readinessReport['tenants_list']))
                    <details class="mt-2">
                        <summary class="text-sm cursor-pointer" style="color: var(--primary);">View tenant list</summary>
                        <div class="mt-2 space-y-1">
                            @foreach($readinessReport['tenants_list'] as $tenant)
                            <div class="text-xs" style="color: var(--text-secondary);">
                                • Unit {{ $tenant['unit_number'] }}: {{ $tenant['tenant_name'] }}
                            </div>
                            @endforeach
                        </div>
                    </details>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column - Status & Actions -->
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
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle mr-1"></i>
                                This transfer has been approved. The new owner has been notified.
                            </p>
                        </div>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-check-double mr-1"></i>
                                Transfer completed successfully on {{ $completedDate }}.
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

            <!-- Action Buttons Card -->
            @if($canApprove || $canReject || $canComplete)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Actions
                </h3>
                <div class="space-y-3">
                    @if($canApprove)
                        <button type="button" 
                                onclick="showApproveModal()"
                                class="w-full btn-success px-4 py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                            <i class="fas fa-check-circle mr-2"></i> Approve Transfer
                        </button>
                    @endif
                    
                    @if($canReject)
                        <button type="button" 
                                onclick="showRejectModal()"
                                class="w-full btn-danger px-4 py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                            <i class="fas fa-times-circle mr-2"></i> Reject Transfer
                        </button>
                    @endif
                    
                    @if($canComplete)
                        <button type="button" 
                                onclick="showCompleteModal()"
                                class="w-full btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                            <i class="fas fa-check-double mr-2"></i> Complete Transfer
                        </button>
                    @endif
                </div>
            </div>
            @endif

            <!-- Timeline Card - UPDATED with fixes -->
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
                </div>
            </div>
            @endif

            <!-- Contact Support Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-headset mr-2" style="color: var(--primary);"></i> Need Help?
                </h3>
                <div class="text-sm space-y-3" style="color: var(--text-secondary);">
                    <p>If you have questions about this transfer:</p>
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
                            Please reference transfer ID: <strong>{{ $transfer->id }}</strong> 
                            and document reference: <strong>{{ $transfer->document_reference }}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- MODALS (Approve, Reject, Complete Modals) -->
<!-- ============================================ -->

<!-- Approve Modal -->
<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Transfer Request
                </h3>
                <button type="button" onclick="hideApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="approveForm" method="POST" action="{{ route('admin.properties.ownership-transfers.approve', ['property' => $transfer->property_id, 'transfer' => $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Approve transfer for: <strong>{{ $transfer->property->property_name ?? 'N/A' }}</strong>
                        </p>
                        @if($isExistingLandlord)
                        <div class="mb-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    This transfer is for an <strong>existing landlord</strong>. No invitation will be sent.
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>
                    
                    @if(!$isExistingLandlord)
                    <div id="approveInvitationSection" class="mb-4">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-1"></i> Send Invitation To New Owner
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="email" checked class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2" style="color: var(--info);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">Email</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="approveEmailDisplay">{{ $transfer->new_owner_email ?? 'No email available' }}</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="sms" class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-sms mr-2" style="color: var(--warning);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">SMS</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="approvePhoneDisplay">{{ $transfer->new_owner_phone ?? 'No phone available' }}</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);" id="approvalMessage">
                                @if($isExistingLandlord)
                                    This will approve the transfer request. The new owner already has an account, so no invitation will be sent.
                                @else
                                    This will approve the transfer request. An invitation will be sent to the new owner via your selected channels.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideApproveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Reject Transfer Request
                </h3>
                <button type="button" onclick="hideRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="rejectForm" method="POST" action="{{ route('admin.properties.ownership-transfers.reject', ['property' => $transfer->property_id, 'transfer' => $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="rejection_reason" rows="4" class="index-custom-textarea w-full" placeholder="Please provide a reason for rejecting this transfer request..." required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="allow_resubmission" value="1" id="allowResubmissionCheckbox" class="mr-2 w-4 h-4">
                            <span class="text-sm" style="color: var(--text-primary);">Allow landlord to resubmit this request</span>
                        </label>
                        <div id="resubmissionDaysContainer" class="mt-2 hidden">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Resubmission available after (days)
                            </label>
                            <input type="number" name="resubmission_days" value="7" min="1" max="90" class="index-custom-input w-full">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="2" class="index-custom-textarea w-full" placeholder="Add any internal notes..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">This action will notify the current landlord and cannot be undone.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRejectModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div id="completeModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideCompleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-double mr-2" style="color: var(--primary);"></i> Complete Transfer
                </h3>
                <button type="button" onclick="hideCompleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="completeForm" method="POST" action="{{ route('admin.properties.ownership-transfers.complete', ['property' => $transfer->property_id, 'transfer' => $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    @if($hasTenants)
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-users mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Tenant Transfer Notice</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    This property has <strong>{{ $readinessReport['units_with_tenants'] ?? 0 }} active tenant(s)</strong> that will be transferred to the new landlord.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="confirm_tenant_transfer" value="1" class="mr-2 w-4 h-4" required>
                            <span class="text-sm" style="color: var(--text-primary);">I confirm that I understand tenants will be transferred to the new landlord</span>
                        </label>
                    </div>
                    @endif
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p>Completing this transfer will:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>Change property ownership to the new landlord</li>
                                    <li>Transfer all property units</li>
                                    @if($hasTenants)
                                    <li>Transfer all existing tenants to the new landlord</li>
                                    @endif
                                    <li>Generate a transfer certificate</li>
                                    <li>Notify both parties</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideCompleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check-double mr-2"></i> Complete Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Resubmission days toggle
    const allowResubmissionCheckbox = document.getElementById('allowResubmissionCheckbox');
    const resubmissionDaysContainer = document.getElementById('resubmissionDaysContainer');
    
    if (allowResubmissionCheckbox) {
        allowResubmissionCheckbox.addEventListener('change', function() {
            if (resubmissionDaysContainer) {
                resubmissionDaysContainer.classList.toggle('hidden', !this.checked);
            }
        });
    }
    
    // Form submissions with AJAX
    const approveForm = document.getElementById('approveForm');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitApproveForm(this);
        });
    }
    
    const rejectForm = document.getElementById('rejectForm');
    if (rejectForm) {
        rejectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitRejectForm(this);
        });
    }
    
    const completeForm = document.getElementById('completeForm');
    if (completeForm) {
        completeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitCompleteForm(this);
        });
    }
    
    // Auto-hide notifications after 5 seconds
    setTimeout(() => {
        const container = document.getElementById('notificationContainer');
        if (container) {
            container.innerHTML = '';
        }
    }, 5000);
});

function showApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showCompleteModal() {
    const modal = document.getElementById('completeModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideCompleteModal() {
    const modal = document.getElementById('completeModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function submitApproveForm(form) {
    const isExistingLandlord = {{ $isExistingLandlord ? 'true' : 'false' }};
    
    if (!isExistingLandlord) {
        const channels = Array.from(form.querySelectorAll('input[name="channels[]"]:checked')).map(cb => cb.value);
        if (channels.length === 0) {
            showNotification('error', 'Please select at least one channel (Email or SMS) to send the invitation.');
            return;
        }
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Transfer approved successfully.');
            hideApproveModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

function submitRejectForm(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Transfer rejected successfully.');
            hideRejectModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

function submitCompleteForm(form) {
    const tenantConfirm = form.querySelector('input[name="confirm_tenant_transfer"]');
    if (tenantConfirm && !tenantConfirm.checked) {
        showNotification('error', 'Please confirm that you understand tenants will be transferred to the new landlord.');
        return;
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Transfer completed successfully.');
            hideCompleteModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

function showNotification(type, message) {
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `mb-4 p-4 rounded-lg shadow-lg min-w-[300px] transform transition-all duration-300 translate-x-0`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.95)' : 'rgba(var(--danger-rgb), 0.95)';
    notification.style.border = type === 'success' ? '1px solid var(--success)' : '1px solid var(--danger)';
    notification.style.animation = 'slideIn 0.3s ease';
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2" style="color: white;"></i>
                <span style="color: white;">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
</script>

<style>
.index-custom-input,
.index-custom-dropdown,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}

.index-custom-input:focus,
.index-custom-dropdown:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

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

.btn-success {
    background-color: var(--success) !important;
    color: white !important;
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #218838 !important;
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

/* Timeline specific styles */
.timeline-node {
    transition: transform 0.2s ease;
}

.timeline-node:hover {
    transform: scale(1.1);
}
</style>
@endsection