{{-- properties/ownership-transfers/show.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\User;

    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $isLandlord = auth()->user()->isLandlord();
    $userId = auth()->id();
    
    // Determine layout based on user role
    if ($isLandlord) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'admin';
    }
    
    // Build dynamic routes based on user role
    $downloadRoute = ($isAdmin || $isSuperAdmin) 
        ? route('admin.ownership-transfers.download-document', $transfer->id)
        : route('properties.ownership-transfers.download', [$transfer->property_id, $transfer->id]);
    
    $certificateRoute = ($isAdmin || $isSuperAdmin) 
        ? route('admin.ownership-transfers.download-certificate', $transfer->id)
        : route('properties.ownership-transfers.certificate', [$transfer->property_id, $transfer->id]);
    
    $pageTitle = 'Ownership Transfer Request: ' . ($transfer->property->property_name ?? 'N/A');
    
    $successMessage = session('success');
    $errorMessage = session('error');
    $invitationResults = session('invitation_results');
    
    // Status colors and icons
    $statusConfigs = [
        PropertyOwnershipTransfer::STATUS_PENDING => ['bg' => 'warning', 'icon' => 'clock', 'label' => 'Pending Review', 'class' => 'badge-warning'],
        PropertyOwnershipTransfer::STATUS_APPROVED => ['bg' => 'success', 'icon' => 'check-circle', 'label' => 'Approved', 'class' => 'badge-success'],
        PropertyOwnershipTransfer::STATUS_REJECTED => ['bg' => 'danger', 'icon' => 'times-circle', 'label' => 'Rejected', 'class' => 'badge-danger'],
        PropertyOwnershipTransfer::STATUS_COMPLETED => ['bg' => 'info', 'icon' => 'check-double', 'label' => 'Completed', 'class' => 'badge-info'],
        PropertyOwnershipTransfer::STATUS_CANCELLED => ['bg' => 'secondary', 'icon' => 'ban', 'label' => 'Cancelled', 'class' => 'badge-secondary'],
    ];
    
    $statusConfig = $statusConfigs[$transfer->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle', 'label' => 'Unknown', 'class' => 'badge-secondary'];
    
    // Check permissions for actions
    $canCancel = $isLandlord && $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $userId === $transfer->current_landlord_id;
    $canApprove = ($isAdmin || $isSuperAdmin) && $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING;
    $canReject = ($isAdmin || $isSuperAdmin) && $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING;
    $canComplete = ($isAdmin || $isSuperAdmin) && $transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED && $transfer->newLandlord && $transfer->newLandlord->status === User::STATUS_ACTIVE;
    $canResubmit = $isLandlord && $transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->can_resubmit && $userId === $transfer->current_landlord_id;
    
    // Determine if user is sender or receiver
    $isSender = $transfer->current_landlord_id === $userId;
    $isReceiver = $transfer->new_landlord_id === $userId;
    
    // Get property type name
    $propertyTypeName = 'N/A';
    if ($transfer->property) {
        if ($transfer->property->relationLoaded('propertyType') && $transfer->property->propertyType) {
            $propertyTypeName = $transfer->property->propertyType->name ?? 'N/A';
        } elseif (!empty($transfer->property->property_type_name)) {
            $propertyTypeName = $transfer->property->property_type_name;
        } elseif (!empty($transfer->property->type)) {
            $propertyTypeName = ucfirst($transfer->property->type);
        }
    }
    
    // Get units information
    $units = $transfer->property->units ?? collect();
    $unitsWithTenants = $units->filter(function($unit) {
        return !is_null($unit->tenant_id);
    });
    $hasTenants = $unitsWithTenants->isNotEmpty();
    
    // Check if SMS is configured
    $smsConfigured = !empty(config('sms.default')) && !empty(config('sms.providers.' . config('sms.default')));
    
    // Timeline steps
    $timelineSteps = [
        ['step' => 1, 'label' => 'Request Submitted', 'date' => $transfer->created_at, 'status' => 'completed'],
        ['step' => 2, 'label' => 'Admin Review', 'date' => $transfer->approved_at ?: ($transfer->rejected_at ?: null), 'status' => $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING ? 'current' : 'completed'],
        ['step' => 3, 'label' => 'New Owner Registration', 'date' => $transfer->newLandlord && $transfer->newLandlord->status === User::STATUS_ACTIVE ? $transfer->newLandlord->email_verified_at : null, 'status' => $transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED ? 'current' : ($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED ? 'completed' : 'pending')],
        ['step' => 4, 'label' => 'Transfer Completed', 'date' => $transfer->completed_at, 'status' => $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED ? 'completed' : 'pending'],
    ];
    
    // Related transfers (for bulk) - using controller passed variable
    $hasRelatedTransfers = isset($relatedTransfers) && !empty($relatedTransfers);
    
    // Get activity logs
    $activityLogs = $transfer->activityLogs ?? collect();
    
    // Check if signature is verified
    $signatureVerified = $transfer->digital_signature_verified;
    $hasDigitalSignature = isset($transfer->metadata['digital_signature']);
    
    // Get transfer readiness report
    $readinessReport = $transfer->getReadinessReport();
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-exchange-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i> 
                        Ownership Transfer Request
                        @if($transfer->is_bulk_transfer)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-layer-group mr-1"></i> Bulk Transfer
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-1"></i>
                        <span>{{ $transfer->property->property_name ?? 'N/A' }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-hashtag mr-1"></i>
                        <span>Ref: {{ $transfer->document_reference }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>Requested: {{ $transfer->created_at ? $transfer->created_at->format('M j, Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-1"></i> 
                    @if($transfer->processing_time_days)
                        Processing: {{ $transfer->processing_time_days }} days
                    @else
                        {{ $transfer->created_at ? $transfer->created_at->diffForHumans() : 'N/A' }}
                    @endif
                </div>
                <a href="{{ route('properties.show', $transfer->property_id) }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Property
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span class="font-medium" style="color: var(--success);">{{ $successMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if($errorMessage)
    <div class="error-message" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span class="font-medium" style="color: var(--danger);">{{ $errorMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Invitation Results Display -->
    @if($invitationResults && ($invitationResults['success'] ?? false))
    <div class="card" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
        <div class="p-4">
            <div class="flex items-start">
                <i class="fas fa-envelope-open-text text-xl mr-3 mt-1" style="color: var(--info);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">Invitation Sent</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        {{ $invitationResults['message'] ?? 'Invitation sent successfully' }}
                    </p>
                    @if(isset($invitationResults['channel_results']))
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach($invitationResults['channel_results'] as $channel => $result)
                            @if($result['success'])
                                <span class="inline-flex items-center text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> {{ ucfirst($channel) }}: ✓ Sent
                                </span>
                            @else
                                <span class="inline-flex items-center text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                    <i class="fas fa-times-circle mr-1"></i> {{ ucfirst($channel) }}: ✗ Failed
                                </span>
                            @endif
                        @endforeach
                    </div>
                    @endif
                    @if(isset($invitationResults['invitation_url']))
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-link mr-1"></i> 
                        Invitation URL: <span class="font-mono break-all">{{ $invitationResults['invitation_url'] }}</span>
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Status Banner -->
    <div class="card border-l-4" style="border-left-color: var(--{{ $statusConfig['bg'] }});">
        <div class="p-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.1);">
                            <i class="fas fa-{{ $statusConfig['icon'] }} text-lg" style="color: var(--{{ $statusConfig['bg'] }});"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Status: {{ $statusConfig['label'] }}
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @switch($transfer->status)
                                @case(PropertyOwnershipTransfer::STATUS_PENDING)
                                    Awaiting admin review and approval
                                    @break
                                @case(PropertyOwnershipTransfer::STATUS_APPROVED)
                                    Approved - Waiting for new owner to accept invitation
                                    @break
                                @case(PropertyOwnershipTransfer::STATUS_REJECTED)
                                    Rejected: {{ $transfer->rejection_reason ?? 'No reason provided' }}
                                    @if($transfer->can_resubmit)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-redo mr-1"></i> Eligible for resubmission after {{ $transfer->can_resubmit_after ? $transfer->can_resubmit_after->format('M j, Y') : 'N/A' }}
                                        </span>
                                    @endif
                                    @break
                                @case(PropertyOwnershipTransfer::STATUS_COMPLETED)
                                    Transfer completed on {{ $transfer->completed_at ? $transfer->completed_at->format('F j, Y') : 'N/A' }}
                                    @break
                                @case(PropertyOwnershipTransfer::STATUS_CANCELLED)
                                    Cancelled by current owner
                                    @break
                            @endswitch
                        </p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2">
                    @if($canCancel)
                        <button type="button" 
                                onclick="showCancelModal()"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-times mr-2"></i> Cancel Request
                        </button>
                    @endif
                    
                    @if($canResubmit)
                        <a href="{{ route('properties.ownership-transfers.resubmit', [$transfer->property_id, $transfer->id]) }}" 
                           class="btn-success px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-redo mr-2"></i> Resubmit Request
                        </a>
                    @endif
                    
                    @if($canApprove)
                        <button type="button" 
                                onclick="showApproveModal()"
                                class="btn-success px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-check mr-2"></i> Approve Transfer
                        </button>
                    @endif
                    
                    @if($canReject)
                        <button type="button" 
                                onclick="showRejectModal()"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-times mr-2"></i> Reject Transfer
                        </button>
                    @endif
                    
                    @if($canComplete)
                        <button type="button" 
                                onclick="confirmCompleteTransfer()"
                                class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-exchange-alt mr-2"></i> Complete Transfer
                        </button>
                    @endif
                    
                    @if($transfer->document_url)
                        <a href="{{ $downloadRoute }}" 
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Download Document
                        </a>
                    @endif
                    
                    @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->certificate_url)
                        <a href="{{ $certificateRoute }}" 
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-certificate mr-2"></i> Download Certificate
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Tenant Transfer Notice (if applicable) -->
    @if($hasTenants && $transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
    <div class="card" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="p-4">
            <div class="flex items-start">
                <i class="fas fa-users mr-3 mt-0.5 text-lg" style="color: var(--warning);"></i>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Tenant Transfer Notice</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        This property has <strong>{{ $unitsWithTenants->count() }} active tenant(s)</strong>. 
                        Upon completion, they will be automatically transferred to the new landlord. 
                        Their tenancy agreements remain valid under the new ownership.
                    </p>
                    <div class="mt-2">
                        <details>
                            <summary class="text-sm cursor-pointer" style="color: var(--primary);">View tenant details</summary>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach($unitsWithTenants as $unit)
                                <li style="color: var(--text-secondary);">
                                    <i class="fas fa-door-open mr-1"></i> Unit {{ $unit->unit_number }}: 
                                    {{ $unit->tenant->name ?? 'Unknown' }} 
                                    ({{ $unit->tenant->phone ?? 'N/A' }})
                                </li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Timeline -->
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-stream mr-2" style="color: var(--primary);"></i> Transfer Timeline
            </h3>
            
            <div class="relative">
                <div class="absolute left-0 md:left-1/2 transform md:-translate-x-1/2 h-full w-0.5" style="background-color: var(--border-color);"></div>
                
                <div class="relative space-y-8">
                    @foreach($timelineSteps as $step)
                        <div class="flex flex-col md:flex-row items-center {{ $loop->iteration % 2 === 0 ? 'md:flex-row-reverse' : '' }}">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center relative z-10
                                @if($step['status'] === 'completed') bg-green-100 dark:bg-green-900/30 border-2 border-green-500
                                @elseif($step['status'] === 'current') bg-blue-100 dark:bg-blue-900/30 border-2 border-blue-500
                                @else bg-gray-100 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600
                                @endif">
                                @if($step['status'] === 'completed')
                                    <i class="fas fa-check text-green-600 dark:text-green-400"></i>
                                @elseif($step['status'] === 'current')
                                    <i class="fas fa-clock text-blue-600 dark:text-blue-400"></i>
                                @else
                                    <i class="fas fa-circle text-gray-400 dark:text-gray-500"></i>
                                @endif
                            </div>
                            
                            <div class="flex-1 {{ $loop->iteration % 2 === 0 ? 'md:text-right md:pr-8' : 'md:pl-8' }} mt-4 md:mt-0">
                                <div class="rounded-lg p-4" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <div>
                                            <h4 class="font-semibold" style="color: var(--text-primary);">{{ $step['label'] }}</h4>
                                            @if($step['date'])
                                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                                    {{ $step['date'] instanceof \Carbon\Carbon ? $step['date']->format('F j, Y \a\t g:i A') : 'N/A' }}
                                                </p>
                                            @endif
                                        </div>
                                        <div>
                                            @if($step['status'] === 'completed')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                                    <i class="fas fa-check mr-1"></i> Completed
                                                </span>
                                            @elseif($step['status'] === 'current')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                                                    <i class="fas fa-clock mr-1"></i> In Progress
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-secondary">
                                                    <i class="fas fa-clock mr-1"></i> Pending
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @switch($step['step'])
                                        @case(1)
                                            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                                Request submitted by {{ $transfer->requestedBy->name ?? 'Current Owner' }}
                                                @if($transfer->is_bulk_transfer)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-layer-group mr-1"></i> Part of bulk transfer
                                                    </span>
                                                @endif
                                            </p>
                                            @break
                                        @case(2)
                                            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                                @if($transfer->approved_at)
                                                    Approved by {{ $transfer->approvedBy->name ?? 'Admin' }} on {{ $transfer->approved_at->format('M j, Y') }}
                                                    @if($transfer->admin_notes)
                                                        <br>Notes: {{ $transfer->admin_notes }}
                                                    @endif
                                                @elseif($transfer->rejected_at)
                                                    Rejected by {{ $transfer->rejectedBy->name ?? 'Admin' }} on {{ $transfer->rejected_at->format('M j, Y') }}
                                                    @if($transfer->rejection_reason)
                                                        <br>Reason: {{ $transfer->rejection_reason }}
                                                    @endif
                                                @else
                                                    Awaiting admin review and approval
                                                @endif
                                            </p>
                                            @break
                                        @case(3)
                                            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                                @if($transfer->newLandlord && $transfer->newLandlord->status === User::STATUS_ACTIVE)
                                                    New owner registered and verified
                                                    @if($transfer->newLandlord->email_verified_at)
                                                        on {{ $transfer->newLandlord->email_verified_at->format('M j, Y') }}
                                                    @endif
                                                @elseif($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
                                                    Invitation sent to {{ $transfer->new_owner_name ?? 'N/A' }}
                                                    <button type="button" 
                                                            onclick="resendInvitation({{ $transfer->id }})"
                                                            class="ml-2 text-xs underline hover:no-underline"
                                                            style="color: var(--primary);">
                                                        <i class="fas fa-paper-plane mr-1"></i> Resend
                                                    </button>
                                                @else
                                                    New owner registration pending
                                                @endif
                                            </p>
                                            @break
                                        @case(4)
                                            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED)
                                                    Ownership successfully transferred
                                                    @if($transfer->completed_at)
                                                        on {{ $transfer->completed_at->format('M j, Y') }}
                                                    @endif
                                                    @if($transfer->completedBy)
                                                        by {{ $transfer->completedBy->name }}
                                                    @endif
                                                @else
                                                    Final transfer completion pending
                                                @endif
                                            </p>
                                            @break
                                    @endswitch
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Property Information -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i> Property Information
                </h3>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 mr-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-home" style="color: var(--primary);"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium" style="color: var(--text-primary);">
                                <a href="{{ route('properties.show', $transfer->property_id) }}" class="hover:text-primary transition-colors">
                                    {{ $transfer->property->property_name ?? 'N/A' }}
                                </a>
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                {{ $transfer->property->registration_pattern ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Address</p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $transfer->property->street_name ?? 'N/A' }}, {{ $transfer->property->zone ?? 'N/A' }}
                            </p>
                        </div>
                        <div>
                             <p class="text-sm" style="color: var(--text-secondary);">Property Type</p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                @php
                                    // Try to get property type from multiple sources
                                    $typeName = 'N/A';
                                    if (isset($transfer->property->propertyType) && $transfer->property->propertyType) {
                                        $typeName = $transfer->property->propertyType->name ?? 'N/A';
                                    } elseif (isset($transfer->property->property_type_name) && $transfer->property->property_type_name) {
                                        $typeName = $transfer->property->property_type_name;
                                    } elseif (isset($transfer->property->type) && $transfer->property->type) {
                                        $typeName = ucfirst($transfer->property->type);
                                    }
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-building mr-1"></i> {{ $typeName }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Total Units</p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $units->count() }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Occupied Units</p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $unitsWithTenants->count() }}
                            </p>
                        </div>
                    </div>
                    
                    @if($transfer->property->house_number || $transfer->property->block_number || $transfer->property->digital_address)
                    <div class="flex flex-wrap gap-2 pt-2">
                        @if($transfer->property->house_number)
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs" 
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-hashtag mr-1"></i> House #{{ $transfer->property->house_number }}
                            </span>
                        @endif
                        @if($transfer->property->block_number)
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs" 
                                  style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                <i class="fas fa-cubes mr-1"></i> Block {{ $transfer->property->block_number }}
                            </span>
                        @endif
                        @if($transfer->property->digital_address)
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs" 
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-map-marker-alt mr-1"></i> {{ $transfer->property->digital_address }}
                            </span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Transfer Information -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i> Transfer Information
                </h3>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Transfer Date</p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $transfer->transfer_date ? $transfer->transfer_date->format('F j, Y') : 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Sale Amount</p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                @if($transfer->sale_amount)
                                    GHS {{ number_format($transfer->sale_amount, 2) }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Document Type</p>
                        <div class="flex items-center mt-1">
                            <span class="px-2 py-1 rounded text-xs font-medium mr-2 badge-secondary">
                                {{ $transfer->document_type_label }}
                            </span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $transfer->document_reference ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                    
                    @if($hasDigitalSignature)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Digital Signature</p>
                        <div class="flex items-center mt-1">
                            @if($signatureVerified)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    <i class="fas fa-check-circle mr-1"></i> Verified
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                                    <i class="fas fa-clock mr-1"></i> Pending Verification
                                </span>
                                @if($isAdmin || $isSuperAdmin)
                                <button type="button" 
                                        onclick="verifySignature({{ $transfer->id }})"
                                        class="ml-2 text-xs underline hover:no-underline"
                                        style="color: var(--primary);">
                                    <i class="fas fa-signature mr-1"></i> Verify Now
                                </button>
                                @endif
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    @if($transfer->reason_for_transfer)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Reason for Transfer</p>
                        <p class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $transfer->reason_for_transfer }}
                        </p>
                    </div>
                    @endif
                    
                    @if($transfer->notes)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Additional Notes</p>
                        <p class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $transfer->notes }}
                        </p>
                    </div>
                    @endif
                    
                    @if($transfer->admin_notes && ($isAdmin || $isSuperAdmin))
                    <div class="pt-2 border-t" style="border-color: var(--border-color);">
                        <p class="text-sm" style="color: var(--text-secondary);">Admin Notes</p>
                        <p class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $transfer->admin_notes }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Current Owner Information -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--secondary);"></i> Current Owner
                    @if($isSender && $isLandlord)
                    <span class="ml-2 text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        You
                    </span>
                    @endif
                </h3>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 mr-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-user-tie" style="color: var(--secondary);"></i>
                            </div>
                        </div>
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ $transfer->currentLandlord->name ?? 'N/A' }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                Current Property Owner
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex items-center text-sm">
                            <i class="fas fa-envelope mr-2 w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $transfer->currentLandlord->email ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <i class="fas fa-phone mr-2 w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $transfer->currentLandlord->phone ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <i class="fas fa-calendar-alt mr-2 w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">Owner since {{ $transfer->property->created_at ? $transfer->property->created_at->format('M Y') : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Owner Information -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-plus mr-2" style="color: var(--info);"></i> New Owner
                    @if($isReceiver && $isLandlord)
                    <span class="ml-2 text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        You
                    </span>
                    @endif
                </h3>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 mr-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--info-rgb), 0.1);">
                                @if($transfer->newLandlord && $transfer->newLandlord->status === User::STATUS_ACTIVE)
                                    <i class="fas fa-user-check" style="color: var(--success);"></i>
                                @else
                                    <i class="fas fa-user-plus" style="color: var(--info);"></i>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ $transfer->new_owner_name ?? 'N/A' }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                @if($transfer->newLandlord && $transfer->newLandlord->status === User::STATUS_ACTIVE)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs badge-success">
                                        <i class="fas fa-check mr-1"></i> Registered
                                    </span>
                                @elseif($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs badge-warning">
                                        <i class="fas fa-clock mr-1"></i> Invitation Sent
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs badge-secondary">
                                        <i class="fas fa-clock mr-1"></i> Pending Registration
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex items-center text-sm">
                            <i class="fas fa-phone mr-2 w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $transfer->new_owner_phone ?? 'N/A' }}</span>
                        </div>
                        @if($transfer->new_owner_email)
                        <div class="flex items-center text-sm">
                            <i class="fas fa-envelope mr-2 w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $transfer->new_owner_email }}</span>
                        </div>
                        @endif
                        @if($transfer->new_owner_address)
                        <div class="flex items-center text-sm">
                            <i class="fas fa-map-marker-alt mr-2 w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $transfer->new_owner_address }}</span>
                        </div>
                        @endif
                    </div>
                    
                    @if($transfer->newLandlord && ($isAdmin || $isSuperAdmin))
                    <div class="pt-3 border-t" style="border-color: var(--border-color);">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">User Account Status:</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                @if($transfer->newLandlord->status === User::STATUS_ACTIVE) badge-success
                                @elseif($transfer->newLandlord->status === User::STATUS_PENDING) badge-warning
                                @else badge-secondary @endif">
                                {{ ucfirst($transfer->newLandlord->status) }}
                            </span>
                        </div>
                        @if($transfer->newLandlord->status === User::STATUS_PENDING)
                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Awaiting new owner to accept invitation and complete registration
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Related Transfers (for Bulk) -->
    @if($hasRelatedTransfers)
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i> Related Transfers
            </h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This transfer is part of a bulk transfer. Below are other properties in the same transfer request:
            </p>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($relatedTransfers as $relatedTransfer)
                        <tr>
                            <td class="p-3">
                                <div class="font-medium" style="color: var(--text-primary);">
                                    {{ $relatedTransfer->property->property_name ?? 'N/A' }}
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $relatedTransfer->property->registration_pattern ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="p-3">
                                @php
                                    $relStatusConfig = $statusConfigs[$relatedTransfer->status] ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'label' => ucfirst($relatedTransfer->status)];
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $relStatusConfig['class'] }}">
                                    <i class="fas fa-{{ $relStatusConfig['icon'] }} mr-1"></i>
                                    {{ $relStatusConfig['label'] }}
                                </span>
                            </td>
                            <td class="p-3">
                                <a href="{{ route('properties.ownership-transfers.show', [$relatedTransfer->property_id, $relatedTransfer->id]) }}" 
                                   class="action-btn view" data-tooltip="View Transfer">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Activity Logs -->
    @if($activityLogs->isNotEmpty())
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: var(--info);"></i> Activity Log
            </h3>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($activityLogs as $log)
                <div class="flex items-start space-x-3 p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-user-circle" style="color: var(--info);"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $log->description }}</span>
                            <span class="text-xs" style="color: var(--text-secondary);">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        @if($log->metadata)
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> 
                            By: {{ $log->user->name ?? 'System' }}
                            @if(isset($log->ip_address))
                            • IP: {{ $log->ip_address }}
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Document Preview -->
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Transfer Document
            </h3>
            
            <div class="document-preview-container rounded-lg p-6 text-center">
                <div class="max-w-md mx-auto">
                    @php
                        $fileExtension = $transfer->document_url ? pathinfo($transfer->document_url, PATHINFO_EXTENSION) : null;
                        $fileIcon = 'fa-file-alt';
                        $fileColor = 'var(--primary)';
                        
                        if ($fileExtension === 'pdf') {
                            $fileIcon = 'fa-file-pdf';
                            $fileColor = '#dc2626';
                        } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $fileIcon = 'fa-file-image';
                            $fileColor = '#10b981';
                        } elseif (in_array($fileExtension, ['doc', 'docx'])) {
                            $fileIcon = 'fa-file-word';
                            $fileColor = '#2b5797';
                        }
                    @endphp
                    
                    <i class="fas {{ $fileIcon }} text-5xl mb-4" style="color: {{ $fileColor }};"></i>
                    <h4 class="font-semibold mb-2" style="color: var(--text-primary);">
                        {{ $transfer->document_type_label }}
                    </h4>
                    <p class="text-sm mb-2" style="color: var(--text-secondary);">
                        Document Reference: {{ $transfer->document_reference }}
                    </p>
                    @if($transfer->document_url)
                        <p class="text-xs mb-4" style="color: var(--text-secondary);">
                            <i class="fas fa-file mr-1"></i> 
                            {{ basename($transfer->document_url) }}
                            <span class="mx-1">•</span>
                            <i class="fas fa-calendar-alt mr-1"></i>
                            Uploaded: {{ $transfer->created_at ? $transfer->created_at->format('M j, Y') : 'N/A' }}
                        </p>
                    @endif
                    
                    <div class="flex items-center justify-center space-x-3">
                        @if($transfer->document_url)
                        <a href="{{ $downloadRoute }}" 
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Download Document
                        </a>
                        @endif
                        
                        @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->certificate_url)
                        <a href="{{ $certificateRoute }}" 
                           class="btn-success px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-certificate mr-2"></i> Download Certificate
                        </a>
                        @endif
                        
                        @if($transfer->document_url && in_array($fileExtension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <button type="button" 
                                    onclick="previewDocument('{{ Storage::url($transfer->document_url) }}', '{{ $fileExtension }}')"
                                    class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-eye mr-2"></i> Preview
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- APPROVE MODAL WITH CHANNEL SELECTION -->
<!-- ============================================ -->
<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Transfer
                </h3>
                <button type="button" onclick="hideApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="approveForm" method="POST" action="{{ route('admin.properties.ownership-transfers.approve', [$transfer->property_id, $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Approve transfer for: <strong>{{ $transfer->property->property_name ?? 'N/A' }}</strong>
                        </p>
                    </div>
                    
                    <!-- Invitation Channel Selection -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-1"></i> Send Invitation To New Owner
                        </label>
                        <div class="space-y-2">
                            <!-- Email Channel -->
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" 
                                   style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="email" checked class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2" style="color: var(--info);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">Email</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="approveEmailDisplay">
                                        {{ $transfer->new_owner_email ?: 'No email address available' }}
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">Recommended</span>
                            </label>
                            
                            <!-- SMS Channel -->
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" 
                                   style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="sms" class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-sms mr-2" style="color: var(--warning);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">SMS</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="approvePhoneDisplay">
                                        {{ $transfer->new_owner_phone ?: 'No phone number available' }}
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">Quick</span>
                            </label>
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Select at least one channel to send invitation
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" 
                                  rows="3" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    @if($hasTenants)
                    <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-users mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This property has {{ $unitsWithTenants->count() }} tenant(s) that will be transferred.
                            </p>
                        </div>
                    </div>
                    @endif
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Approving will send an invitation to the new owner via your selected channels.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideApproveModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve & Send Invitation
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
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Reject Transfer
                </h3>
                <button type="button" onclick="hideRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="rejectForm" method="POST" action="{{ route('admin.properties.ownership-transfers.reject', [$transfer->property_id, $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="rejection_reason" 
                                  rows="3" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Please provide a reason for rejection..."
                                  required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" 
                                  rows="2" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Internal notes..."></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_resubmission" value="1" class="mr-2 rounded">
                            <span class="text-sm" style="color: var(--text-primary);">Allow resubmission after</span>
                            <select name="resubmission_days" class="ml-2 index-custom-dropdown text-sm w-20">
                                <option value="7">7 days</option>
                                <option value="14">14 days</option>
                                <option value="30">30 days</option>
                                <option value="60">60 days</option>
                            </select>
                        </label>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This action will notify the current landlord and cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRejectModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
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
            <form method="POST" action="{{ route('properties.ownership-transfers.cancel', [$transfer->property_id, $transfer->id]) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            Are you sure you want to cancel this ownership transfer request? This action cannot be undone.
                        </p>
                        
                        <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    The admin will be notified of this cancellation.
                                </p>
                            </div>
                        </div>
                        
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Cancellation (Optional)
                        </label>
                        <textarea name="notes" 
                                  rows="3" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Why are you cancelling this request?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideCancelModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Keep Request
                    </button>
                    <button type="submit" 
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Cancel Transfer Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div id="documentPreviewModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-75" onclick="closePreviewModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Document Preview
                </h3>
                <button type="button" onclick="closePreviewModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body p-0 overflow-auto" style="height: calc(90vh - 80px);">
                <div id="previewContent" class="flex items-center justify-center min-h-[500px]">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-pulse text-3xl mb-4" style="color: var(--primary);"></i>
                        <p style="color: var(--text-secondary);">Loading preview...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closePreviewModal()" 
                        class="btn-secondary px-4 py-2 rounded-lg font-medium">
                    Close
                </button>
                <a href="{{ $downloadRoute }}" 
                   class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                    <i class="fas fa-download mr-2"></i> Download
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    
    // Set initial checkbox states for approve modal based on available contact info
    const approveForm = document.getElementById('approveForm');
    if (approveForm) {
        const emailCheckbox = approveForm.querySelector('input[name="channels[]"][value="email"]');
        const smsCheckbox = approveForm.querySelector('input[name="channels[]"][value="sms"]');
        const hasEmail = '{{ $transfer->new_owner_email }}' !== '';
        const hasPhone = '{{ $transfer->new_owner_phone }}' !== '';
        
        if (emailCheckbox) {
            emailCheckbox.checked = hasEmail;
            emailCheckbox.disabled = !hasEmail;
        }
        
        if (smsCheckbox) {
            smsCheckbox.checked = hasPhone;
            smsCheckbox.disabled = !hasPhone;
        }
    }
});

function autoHideMessages() {
    setTimeout(function() {
        var successMessages = document.querySelectorAll('.success-message');
        successMessages.forEach(function(msg) {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
        
        var errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(function(msg) {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
    }, 5000);
}

function showApproveModal() {
    var modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideApproveModal() {
    var modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Intercept approve form submission to validate channel selection
var approveFormElement = document.getElementById('approveForm');
if (approveFormElement) {
    approveFormElement.addEventListener('submit', function(e) {
        var channels = Array.from(this.querySelectorAll('input[name="channels[]"]:checked')).map(function(cb) { return cb.value; });
        
        if (channels.length === 0) {
            e.preventDefault();
            alert('Please select at least one channel (Email or SMS) to send the invitation.');
            return false;
        }
    });
}

function showRejectModal() {
    var modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideRejectModal() {
    var modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showCancelModal() {
    var modal = document.getElementById('cancelModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideCancelModal() {
    var modal = document.getElementById('cancelModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function confirmCompleteTransfer() {
    var propertyName = '{{ addslashes($transfer->property->property_name ?? "this property") }}';
    var unitCount = {{ $units->count() }};
    var tenantCount = {{ $unitsWithTenants->count() }};
    
    var message = 'Complete this transfer for "' + propertyName + '"? This will change property ownership.';
    
    if (unitCount > 0) {
        message += '\n\n📦 ' + unitCount + ' unit(s) will be transferred.';
        if (tenantCount > 0) {
            message += '\n👥 ' + tenantCount + ' tenant(s) will be automatically transferred to the new landlord.';
        }
    }
    
    if (confirm(message)) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("admin.properties.ownership-transfers.complete", [$transfer->property_id, $transfer->id]) }}';
        
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        var confirmInput = document.createElement('input');
        confirmInput.type = 'hidden';
        confirmInput.name = 'confirm_tenant_transfer';
        confirmInput.value = '1';
        form.appendChild(confirmInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function resendInvitation(transferId) {
    if (confirm('Resend invitation to the new owner?')) {
        fetch('/api/ownership-transfers/' + transferId + '/resend-invitation', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                alert('Invitation resent successfully!');
                location.reload();
            } else {
                alert('Failed to resend invitation: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while resending the invitation.');
        });
    }
}

function verifySignature(transferId) {
    fetch('/api/ownership-transfers/' + transferId + '/verify-signature', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success && data.verified) {
            alert('Digital signature verified successfully!');
            location.reload();
        } else {
            alert('Signature verification failed: ' + (data.message || 'Invalid signature'));
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('An error occurred during signature verification.');
    });
}

function previewDocument(url, fileType) {
    var modal = document.getElementById('documentPreviewModal');
    var previewContent = document.getElementById('previewContent');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    previewContent.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-pulse text-3xl mb-4" style="color: var(--primary);"></i><p style="color: var(--text-secondary);">Loading preview...</p></div>';
    
    if (fileType === 'pdf') {
        previewContent.innerHTML = '<iframe src="' + url + '#toolbar=0" class="w-full h-full min-h-[600px]" style="border: none;"></iframe>';
    } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileType)) {
        previewContent.innerHTML = '<div class="flex items-center justify-center p-4"><img src="' + url + '" alt="Document Preview" class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-lg"></div>';
    } else {
        previewContent.innerHTML = '<div class="text-center p-8"><i class="fas fa-file-alt text-5xl mb-4" style="color: var(--text-secondary);"></i><p class="mb-4" style="color: var(--text-primary);">Preview not available for this file type.</p><a href="' + url + '" download class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center"><i class="fas fa-download mr-2"></i> Download to View</a></div>';
    }
}

function closePreviewModal() {
    var modal = document.getElementById('documentPreviewModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Tooltip initialization
document.querySelectorAll('[data-tooltip]').forEach(function(element) {
    element.addEventListener('mouseenter', function(e) {
        var tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = this.getAttribute('data-tooltip');
        tooltip.style.cssText = 'position: absolute; background: var(--text-primary); color: var(--card-bg); padding: 4px 8px; border-radius: 4px; font-size: 12px; z-index: 1000; white-space: nowrap;';
        document.body.appendChild(tooltip);
        
        var rect = this.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        
        this._tooltip = tooltip;
    });
    
    element.addEventListener('mouseleave', function() {
        if (this._tooltip) {
            this._tooltip.remove();
            this._tooltip = null;
        }
    });
});
</script>

<style>
/* Document Preview Container */
.document-preview-container {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

/* Admin Notes Textarea */
.admin-notes-textarea,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.admin-notes-textarea:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
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

.btn-success {
    background-color: var(--success) !important;
    color: white !important;
    border: 1px solid var(--success) !important;
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #198754 !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #dc3545 !important;
    transform: translateY(-1px);
}

/* Badge styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
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

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Action buttons */
.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
}

.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}

.action-btn.view:hover {
    background-color: rgba(var(--info-rgb), 0.2);
    transform: translateY(-1px);
}

/* Modal styles */
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

/* Tooltip */
.tooltip {
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .action-btn {
        padding: 0.25rem 0.5rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
}
</style>
@endsection