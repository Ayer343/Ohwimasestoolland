{{-- landlord/ownership-transfers/index.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\Property;
    use App\Models\User;

    // Landlord specific page
    $isLandlord = auth()->user()->isLandlord();
    $layout = 'layouts.landlord';
    $routePrefix = 'landlord.ownership-transfers';
    
    // Create dynamic page title
    $pageTitle = 'My Ownership Transfer Requests';
    
    // Check if there are any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $bulkSummary = session('bulk_summary');
    
    // Build dynamic download routes based on user role
    $isAdmin = auth()->user()->isAdmin() || auth()->user()->isSuperAdmin();
    $downloadRoute = $isAdmin 
        ? 'admin.ownership-transfers.download-document'
        : 'properties.ownership-transfers.download';
    $certificateRoute = $isAdmin 
        ? 'admin.ownership-transfers.download-certificate'
        : 'properties.ownership-transfers.certificate';
    
    // Get statistics for the current landlord
    $userId = auth()->id();
    
    // Base query - exclude reversal audit records
    $baseQuery = PropertyOwnershipTransfer::where(function($q) use ($userId) {
        $q->where('current_landlord_id', $userId)
          ->orWhere('new_landlord_id', $userId);
    })->where(function($q) {
        $q->whereNull('metadata->is_reversal_audit')
          ->orWhere('metadata->is_reversal_audit', '!=', true)
          ->orWhere('metadata->is_reversal', '!=', true);
    });
    
    $totalTransfers = (clone $baseQuery)->count();
    
    // As sent transfers (current landlord)
    $sentPendingCount = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)
        ->count();
    $sentApprovedCount = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_APPROVED)
        ->count();
    $sentRejectedCount = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
        ->count();
    $sentCompletedCount = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->count();
    $sentCancelledCount = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)
        ->count();
    
    // As received transfers (new landlord)
    $receivedPendingCount = (clone $baseQuery)->where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)
        ->count();
    $receivedApprovedCount = (clone $baseQuery)->where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_APPROVED)
        ->count();
    $receivedCompletedCount = (clone $baseQuery)->where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->count();
    $receivedRejectedCount = (clone $baseQuery)->where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
        ->count();
    
    // Combined totals for display
    $pendingCount = $sentPendingCount + $receivedPendingCount;
    $approvedCount = $sentApprovedCount + $receivedApprovedCount;
    $rejectedCount = $sentRejectedCount + $receivedRejectedCount;
    $completedCount = $sentCompletedCount + $receivedCompletedCount;
    $cancelledCount = $sentCancelledCount;
    
    // Get reversed transfers count (original transfers that were reversed)
    $reversedCount = (clone $baseQuery)->where('is_reversed', true)->count();
    
    // Count resubmittable transfers (rejected and eligible for resubmission)
    $resubmittableCount = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
        ->where(function($q) {
            $q->whereNull('can_resubmit_after')
              ->orWhere('can_resubmit_after', '<=', now());
        })
        ->whereNull('metadata->resubmitted_to')
        ->count();
    
    // Total value of completed transfers (as seller) - exclude reversed
    $totalValueSold = (clone $baseQuery)->where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where('is_reversed', false)
        ->sum('sale_amount');
    
    // Total value of completed transfers (as buyer) - exclude reversed
    $totalValueBought = (clone $baseQuery)->where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where('is_reversed', false)
        ->sum('sale_amount');
    
    // Average processing time
    $avgProcessingTime = (clone $baseQuery)->where(function($q) use ($userId) {
            $q->where('current_landlord_id', $userId)
              ->orWhere('new_landlord_id', $userId);
        })
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where('is_reversed', false)
        ->whereNotNull('completed_at')
        ->whereNotNull('created_at')
        ->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(DAY, created_at, completed_at)')) ?? 0;
    
    // Transfer type filter (sent/received/all)
    $transferType = request('transfer_type', 'all');
    
    // Status options for filter
    $statuses = [
        'all' => 'All Transfers',
        PropertyOwnershipTransfer::STATUS_PENDING => 'Pending Review',
        PropertyOwnershipTransfer::STATUS_APPROVED => 'Approved',
        PropertyOwnershipTransfer::STATUS_REJECTED => 'Rejected',
        PropertyOwnershipTransfer::STATUS_COMPLETED => 'Completed',
        PropertyOwnershipTransfer::STATUS_CANCELLED => 'Cancelled',
    ];
    
    // Transfer type options
    $transferTypes = [
        'all' => 'All Transfers',
        'sent' => 'Transfers I Sent',
        'received' => 'Transfers I Received',
    ];
    
    // Get current filter
    $currentStatus = request('status', 'all');
    
    // Check if bulk transfer is enabled
    $bulkTransferEnabled = config('ownership_transfer.enable_bulk_transfer', false);
    
    // Get user's properties for quick transfer
    $userProperties = auth()->user()->properties()
        ->whereDoesntHave('currentOwnershipTransfer', function($query) {
            $query->whereIn('status', [
                PropertyOwnershipTransfer::STATUS_PENDING,
                PropertyOwnershipTransfer::STATUS_APPROVED
            ]);
        })
        ->select('id', 'property_name', 'registration_pattern')
        ->get();
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
                        My Ownership Transfer Requests
                        @if($reversedCount > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-undo-alt mr-1"></i> {{ $reversedCount }} Reversed
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage your property ownership transfer requests</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalTransfers) }} transfer{{ $totalTransfers != 1 ? 's' : '' }} total</span>
                        @if($pendingCount > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-clock mr-1" style="color: var(--warning);"></i>
                        <span>{{ $pendingCount }} pending</span>
                        @endif
                        @if($completedCount > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        <span>{{ $completedCount }} completed</span>
                        @endif
                        @if($resubmittableCount > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-redo mr-1" style="color: var(--info);"></i>
                        <span>{{ $resubmittableCount }} ready to resubmit</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                @if($userProperties->isNotEmpty())
                <a href="#" onclick="showQuickTransferModal()" 
                   class="px-3 py-2 rounded-lg transition-colors inline-flex items-center text-sm font-medium"
                   style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <i class="fas fa-plus mr-2"></i> New Transfer
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Resubmission Success Message -->
    @if(session('resubmit_success'))
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <div>
                    <h4 class="font-semibold" style="color: var(--success);">Transfer Resubmitted Successfully!</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ session('resubmit_success') }}</p>
                </div>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Reversal Success Message -->
    @if(session('reversal_requested'))
    <div class="info-message" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-undo-alt mr-2" style="color: var(--info);"></i>
                <div>
                    <h4 class="font-semibold" style="color: var(--info);">Reversal Request Submitted!</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ session('success') ?? 'Your reversal request has been submitted. An administrator will review it.' }}</p>
                </div>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Bulk Transfer Summary -->
    @if($bulkSummary && ($bulkSummary['successful'] ?? 0) > 0)
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-layer-group mr-2" style="color: var(--success);"></i>
                <div class="flex-1">
                    <h3 class="font-semibold" style="color: var(--text-primary);">Bulk Transfer Summary</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ $bulkSummary['successful'] }} transfer(s) submitted successfully.
                        @if(($bulkSummary['failed_count'] ?? 0) > 0)
                        {{ $bulkSummary['failed_count'] }} property(s) failed.
                        @endif
                    </p>
                    @if(isset($bulkSummary['failed']) && count($bulkSummary['failed']) > 0)
                    <details class="mt-2">
                        <summary class="text-sm cursor-pointer" style="color: var(--warning);">View failed properties</summary>
                        <ul class="mt-2 text-sm space-y-1">
                            @foreach($bulkSummary['failed'] as $failed)
                            <li style="color: var(--danger);">{{ $failed['property_name'] ?? 'Unknown' }}: {{ $failed['reason'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                    @endif
                </div>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Success Messages -->
    @if($successMessage && !session('resubmit_success') && !session('reversal_requested'))
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

    <!-- Error Messages -->
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

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Total Transfers Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total Requests</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalTransfers) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-exchange-alt text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Pending Review</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pendingCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                @if($sentPendingCount > 0)
                <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                    {{ $sentPendingCount }} sent, {{ $receivedPendingCount }} received
                </div>
                @endif
            </div>
        </div>
        
        <!-- Completed Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Completed</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($completedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
                @if($sentCompletedCount > 0 || $receivedCompletedCount > 0)
                <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                    {{ $sentCompletedCount }} sold, {{ $receivedCompletedCount }} bought
                </div>
                @endif
            </div>
        </div>
        
        <!-- Rejected Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Rejected</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($rejectedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-times-circle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                @if($resubmittableCount > 0)
                <div class="mt-2 text-xs" style="color: var(--info);">
                    <i class="fas fa-redo mr-1"></i> {{ $resubmittableCount }} can be resubmitted
                </div>
                @endif
            </div>
        </div>
        
        <!-- Total Value Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total Value</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">GHS {{ number_format($totalValueSold + $totalValueBought, 2) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-chart-line text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
                @if($totalValueSold > 0 || $totalValueBought > 0)
                <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                    Sold: GHS {{ number_format($totalValueSold, 2) }} | Bought: GHS {{ number_format($totalValueBought, 2) }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Filters and Table Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route('landlord.ownership-transfers.index') }}" class="space-y-4" id="filterForm">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ request('search') }}" 
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Property name, reference, new owner..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-arrow-right-arrow-left mr-1"></i> Transfer Type
                            </label>
                            <select name="transfer_type" class="index-custom-dropdown w-full">
                                @foreach($transferTypes as $key => $label)
                                    <option value="{{ $key }}" {{ $transferType == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ $currentStatus == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-undo-alt mr-1"></i> Reversal Status
                            </label>
                            <select name="reversal_filter" class="index-custom-dropdown w-full">
                                <option value="">All Transfers</option>
                                <option value="reversed" {{ request('reversal_filter') == 'reversed' ? 'selected' : '' }}>
                                    Reversed Transfers Only
                                </option>
                                <option value="not_reversed" {{ request('reversal_filter') == 'not_reversed' ? 'selected' : '' }}>
                                    Active Transfers (Not Reversed)
                                </option>
                                <option value="pending_reversal" {{ request('reversal_filter') == 'pending_reversal' ? 'selected' : '' }}>
                                    Pending Reversal Requests
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property
                            </label>
                            <select name="property_id" class="index-custom-dropdown w-full">
                                <option value="">All Properties</option>
                                @foreach(auth()->user()->properties as $property)
                                    <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-redo mr-1"></i> Resubmission Status
                            </label>
                            <select name="resubmit_status" class="index-custom-dropdown w-full">
                                <option value="">All Transfers</option>
                                <option value="eligible" {{ request('resubmit_status') == 'eligible' ? 'selected' : '' }}>
                                    Eligible for Resubmission
                                </option>
                                <option value="resubmitted" {{ request('resubmit_status') == 'resubmitted' ? 'selected' : '' }}>
                                    Already Resubmitted
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Date Range
                            </label>
                            <div class="space-y-2">
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="index-custom-input w-full">
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="index-custom-input w-full">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort mr-1"></i> Sort By
                            </label>
                            <select name="sort" class="index-custom-dropdown w-full">
                                <option value="created_at_desc" {{ request('sort', 'created_at_desc') == 'created_at_desc' ? 'selected' : '' }}>
                                    Newest First
                                </option>
                                <option value="created_at_asc" {{ request('sort') == 'created_at_asc' ? 'selected' : '' }}>
                                    Oldest First
                                </option>
                                <option value="transfer_date_desc" {{ request('sort') == 'transfer_date_desc' ? 'selected' : '' }}>
                                    Transfer Date (Newest)
                                </option>
                                <option value="transfer_date_asc" {{ request('sort') == 'transfer_date_asc' ? 'selected' : '' }}>
                                    Transfer Date (Oldest)
                                </option>
                                <option value="status_asc" {{ request('sort') == 'status_asc' ? 'selected' : '' }}>
                                    Status (A-Z)
                                </option>
                                <option value="sale_amount_desc" {{ request('sort') == 'sale_amount_desc' ? 'selected' : '' }}>
                                    Highest Amount
                                </option>
                                <option value="sale_amount_asc" {{ request('sort') == 'sale_amount_asc' ? 'selected' : '' }}>
                                    Lowest Amount
                                </option>
                            </select>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route('landlord.ownership-transfers.index') }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <a href="{{ route('properties.my-properties') }}" class="block w-full text-center px-3 py-2 rounded-lg font-medium text-white" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                    <i class="fas fa-building mr-2"></i> My Properties
                                </a>
                                
                                @if($resubmittableCount > 0)
                                <a href="{{ route('landlord.ownership-transfers.index', ['status' => PropertyOwnershipTransfer::STATUS_REJECTED, 'resubmit_status' => 'eligible']) }}" class="block w-full text-center px-3 py-2 rounded-lg font-medium" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-redo mr-2"></i> View Resubmittable ({{ $resubmittableCount }})
                                </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Status Summary -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Status Distribution
                    </h3>
                    <div class="space-y-3">
                        @foreach($statuses as $key => $label)
                            @if($key !== 'all')
                                @php
                                    $count = 0;
                                    switch($key) {
                                        case PropertyOwnershipTransfer::STATUS_PENDING: $count = $pendingCount; break;
                                        case PropertyOwnershipTransfer::STATUS_APPROVED: $count = $approvedCount; break;
                                        case PropertyOwnershipTransfer::STATUS_REJECTED: $count = $rejectedCount; break;
                                        case PropertyOwnershipTransfer::STATUS_COMPLETED: $count = $completedCount; break;
                                        case PropertyOwnershipTransfer::STATUS_CANCELLED: $count = $cancelledCount; break;
                                    }
                                    $percentage = $totalTransfers > 0 ? round(($count / $totalTransfers) * 100, 1) : 0;
                                @endphp
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        @switch($key)
                                            @case(PropertyOwnershipTransfer::STATUS_PENDING)
                                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--warning);"></span>
                                                @break
                                            @case(PropertyOwnershipTransfer::STATUS_APPROVED)
                                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--success);"></span>
                                                @break
                                            @case(PropertyOwnershipTransfer::STATUS_REJECTED)
                                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></span>
                                                @break
                                            @case(PropertyOwnershipTransfer::STATUS_COMPLETED)
                                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--info);"></span>
                                                @break
                                            @default
                                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--secondary);"></span>
                                        @endswitch
                                        <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                                        <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $percentage }}%)</span>
                                    </div>
                                </div>
                                @if($key === PropertyOwnershipTransfer::STATUS_REJECTED && $resubmittableCount > 0)
                                <div class="text-xs ml-5 mt-1" style="color: var(--info);">
                                    <i class="fas fa-redo mr-1"></i> {{ $resubmittableCount }} eligible for resubmission
                                </div>
                                @endif
                            @endif
                        @endforeach
                        
                        @if($reversedCount > 0)
                        <div class="flex items-center justify-between pt-1 border-t" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">Reversed</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--danger);">{{ $reversedCount }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                    
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Avg. Processing Time:</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ round($avgProcessingTime) }} days</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export Options -->
            <div class="card mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-download mr-2" style="color: var(--primary);"></i> Export Data
                    </h3>
                    <div class="space-y-2">
                        <a href="{{ route('landlord.ownership-transfers.export', ['format' => 'csv'] + request()->all()) }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                            <i class="fas fa-file-csv mr-2"></i> Export as CSV
                        </a>
                        <a href="{{ route('landlord.ownership-transfers.export', ['format' => 'excel'] + request()->all()) }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                            <i class="fas fa-file-excel mr-2"></i> Export as Excel
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Resubmission Info Card -->
            @if($rejectedCount > 0)
            <div class="card mt-6" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="p-4">
                    <h4 class="font-semibold flex items-center mb-2" style="color: var(--info);">
                        <i class="fas fa-info-circle mr-2"></i> About Resubmission
                    </h4>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        Rejected transfers can be resubmitted with corrections. 
                        The form will be pre-filled with your previous data.
                        @if($resubmittableCount > 0)
                        <br><br>
                        <strong>{{ $resubmittableCount }}</strong> rejected transfer(s) are eligible for resubmission.
                        @endif
                    </p>
                </div>
            </div>
            @endif
            
            <!-- Reversal Info Card -->
            @if($reversedCount > 0)
            <div class="card mt-6" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="p-4">
                    <h4 class="font-semibold flex items-center mb-2" style="color: var(--danger);">
                        <i class="fas fa-undo-alt mr-2"></i> About Reversals
                    </h4>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        Reversed transfers have been restored to the original owner.
                        You can filter to view only reversed transfers using the filter above.
                    </p>
                </div>
            </div>
            @endif
        </div>

        <!-- Transfers Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Transfer Requests
                            @if($transferType !== 'all')
                            <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-info">
                                {{ $transferTypes[$transferType] ?? ucfirst($transferType) }}
                            </span>
                            @endif
                            @if(request('reversal_filter') == 'reversed')
                            <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <i class="fas fa-undo-alt mr-1"></i> Showing Reversed Only
                            </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} entries
                            @if($currentStatus !== 'all')
                            <span class="ml-2 px-2 py-1 text-xs rounded-full badge-primary">
                                {{ $statuses[$currentStatus] ?? $currentStatus }}
                            </span>
                            @endif
                            @if(request('resubmit_status') == 'eligible')
                            <span class="ml-2 px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-redo mr-1"></i> Eligible for Resubmission
                            </span>
                            @endif
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-2 mt-4 md:mt-0">
                        <span class="text-sm" style="color: var(--text-secondary);">Show:</span>
                        <select onchange="updatePerPage(this.value)" class="index-custom-dropdown text-sm py-1 px-2 rounded">
                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        
                        <a href="{{ route('landlord.ownership-transfers.export', ['format' => 'csv'] + request()->all()) }}" class="btn-secondary px-3 py-2 rounded-lg font-medium inline-flex items-center text-sm">
                            <i class="fas fa-download mr-2"></i> Export
                        </a>
                    </div>
                </div>

                @if($transfers->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-exchange-alt text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No ownership transfer requests found
                        </h4>
                        <p class="text-sm mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['search', 'status', 'property_id', 'transfer_type', 'resubmit_status', 'reversal_filter']))
                                No transfer requests match your search criteria.
                            @else
                                You haven't initiated or received any property ownership transfers yet.
                            @endif
                        </p>
                        @if(!request()->hasAny(['search', 'status', 'property_id', 'transfer_type', 'resubmit_status', 'reversal_filter']) && $userProperties->isNotEmpty())
                        <div class="space-x-3">
                            <a href="#" onclick="showQuickTransferModal()" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i> Initiate Transfer
                            </a>
                            <a href="{{ route('properties.my-properties') }}" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-building mr-2"></i> View My Properties
                            </a>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 220px;">
                                        Property / Reference
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                        New Owner
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 120px;">
                                        Transfer Date
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 160px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transfers as $transfer)
                                    @php
                                        $isSender = $transfer->current_landlord_id === $userId;
                                        $isReceiver = $transfer->new_landlord_id === $userId;
                                        $isBulk = $transfer->is_bulk_transfer;
                                        $hasDigitalSignature = isset($transfer->metadata['digital_signature']);
                                        $digitalSignatureVerified = $transfer->digital_signature_verified;
                                        $canResubmit = $transfer->can_resubmit ?? $transfer->canBeResubmitted();
                                        $hasBeenResubmitted = isset($transfer->metadata['resubmitted_to']);
                                        $resubmittedToId = $transfer->metadata['resubmitted_to'] ?? null;
                                        $isResubmission = isset($transfer->metadata['resubmitted_from']);
                                        $originalTransferId = $transfer->metadata['resubmitted_from'] ?? null;
                                        $isReversed = $transfer->is_reversed;
                                        $canRequestReversal = $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && 
                                                              !$isReversed && 
                                                              $transfer->reversal_status !== 'pending' &&
                                                              $isSender;
                                    @endphp
                                    <tr data-transfer-id="{{ $transfer->id }}" data-status="{{ $transfer->status }}" class="{{ $isReversed ? 'opacity-75' : '' }}">
                                        <td class="p-3 align-top">
                                            <div>
                                                <div class="font-semibold text-sm" style="color: var(--text-primary);">
                                                    <a href="{{ route('properties.show', $transfer->property_id) }}" class="hover:text-primary transition-colors">
                                                        {{ $transfer->property->property_name ?? 'N/A' }}
                                                    </a>
                                                    @if($isBulk)
                                                    <span class="ml-2 text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-layer-group mr-1"></i> Bulk
                                                    </span>
                                                    @endif
                                                    @if($hasDigitalSignature)
                                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-signature mr-1"></i> Signed
                                                    </span>
                                                    @endif
                                                    @if($isResubmission)
                                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-redo mr-1"></i> Resubmission
                                                    </span>
                                                    @endif
                                                    @if($isReversed)
                                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                        <i class="fas fa-undo-alt mr-1"></i> REVERSED
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="text-xs mt-1 font-mono" style="color: var(--text-secondary);">
                                                    <i class="fas fa-hashtag mr-1 text-xs"></i> {{ $transfer->document_reference }}
                                                    @if($originalTransferId)
                                                    <span class="ml-1">(from #{{ $originalTransferId }})</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs mt-1 flex items-center gap-2 flex-wrap">
                                                    @if($isSender)
                                                    <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-paper-plane mr-1 text-xs"></i> Sent
                                                    </span>
                                                    @endif
                                                    @if($isReceiver)
                                                    <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-inbox mr-1 text-xs"></i> Received
                                                    </span>
                                                    @endif
                                                    @if($digitalSignatureVerified)
                                                    <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-1 text-xs"></i> Verified
                                                    </span>
                                                    @endif
                                                    @if($hasBeenResubmitted)
                                                    <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-share mr-1 text-xs"></i> Resubmitted to #{{ $resubmittedToId }}
                                                    </span>
                                                    @endif
                                                </div>
                                                @if($isReversed && $transfer->reversal_reason)
                                                <div class="text-xs mt-1" style="color: var(--danger);">
                                                    <i class="fas fa-comment mr-1 text-xs"></i> {{ Str::limit($transfer->reversal_reason, 40) }}
                                                </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="p-3 align-top">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 mr-2 mt-0.5">
                                                    <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                                                        <i class="fas fa-user-plus text-xs" style="color: var(--info);"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                        {{ $transfer->new_owner_name }}
                                                    </div>
                                                    <div class="text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-phone mr-1 text-xs"></i> {{ $transfer->new_owner_phone }}
                                                    </div>
                                                    @if($transfer->new_owner_email)
                                                    <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                        <i class="fas fa-envelope mr-1 text-xs"></i> {{ $transfer->new_owner_email }}
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 align-top">
                                            <div class="text-sm font-medium" style="color: var(--text-primary);">
                                                {{ $transfer->transfer_date ? $transfer->transfer_date->format('M j, Y') : 'N/A' }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-clock mr-1 text-xs"></i> {{ $transfer->created_at->diffForHumans() }}
                                            </div>
                                            @if($transfer->sale_amount)
                                            <div class="text-xs mt-1 font-semibold" style="color: var(--primary);">
                                                GHS {{ number_format($transfer->sale_amount, 2) }}
                                            </div>
                                            @endif
                                            @if($isReversed && $transfer->reversal_processed_at)
                                            <div class="text-xs mt-1" style="color: var(--danger);">
                                                <i class="fas fa-undo-alt mr-1"></i> Reversed: {{ \Carbon\Carbon::parse($transfer->reversal_processed_at)->format('M j, Y') }}
                                            </div>
                                            @endif
                                        </td>
                                        <td class="p-3 align-top">
                                            @php
                                                $statusConfigs = [
                                                    PropertyOwnershipTransfer::STATUS_PENDING => ['class' => 'badge-warning', 'icon' => 'clock', 'text' => 'Pending Review'],
                                                    PropertyOwnershipTransfer::STATUS_APPROVED => ['class' => 'badge-success', 'icon' => 'check-circle', 'text' => 'Approved'],
                                                    PropertyOwnershipTransfer::STATUS_REJECTED => ['class' => 'badge-danger', 'icon' => 'times-circle', 'text' => 'Rejected'],
                                                    PropertyOwnershipTransfer::STATUS_COMPLETED => ['class' => 'badge-info', 'icon' => 'check-double', 'text' => 'Completed'],
                                                    PropertyOwnershipTransfer::STATUS_CANCELLED => ['class' => 'badge-secondary', 'icon' => 'ban', 'text' => 'Cancelled'],
                                                ];
                                                $statusConfig = $statusConfigs[$transfer->status] ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'text' => ucfirst($transfer->status)];
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusConfig['class'] }}">
                                                <i class="fas fa-{{ $statusConfig['icon'] }} mr-1 text-xs"></i>
                                                {{ $statusConfig['text'] }}
                                            </span>
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->rejection_reason)
                                            <div class="text-xs mt-1" style="color: var(--danger);">
                                                <i class="fas fa-comment mr-0.5 text-xs"></i> {{ Str::limit($transfer->rejection_reason, 50) }}
                                            </div>
                                            @endif
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $canResubmit && !$hasBeenResubmitted)
                                            <div class="text-xs mt-1" style="color: var(--success);">
                                                <i class="fas fa-redo mr-0.5 text-xs"></i> Eligible for resubmission
                                            </div>
                                            @endif
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->can_resubmit_after && $transfer->can_resubmit_after->isFuture())
                                            <div class="text-xs mt-1" style="color: var(--warning);">
                                                <i class="fas fa-clock mr-0.5 text-xs"></i> Resubmit available after {{ $transfer->can_resubmit_after->format('M j, Y') }}
                                            </div>
                                            @endif
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $transfer->created_at->diffInDays(now()) > 7)
                                            <div class="text-xs mt-1" style="color: var(--warning);">
                                                <i class="fas fa-exclamation-triangle mr-0.5 text-xs"></i> Awaiting review ({{ $transfer->created_at->diffInDays(now()) }} days)
                                            </div>
                                            @endif
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED && $transfer->created_at->diffInDays(now()) > 14)
                                            <div class="text-xs mt-1" style="color: var(--warning);">
                                                <i class="fas fa-clock mr-0.5 text-xs"></i> Approved but not yet completed
                                            </div>
                                            @endif
                                            @if($transfer->reversal_status === 'pending')
                                            <div class="text-xs mt-1" style="color: var(--warning);">
                                                <i class="fas fa-clock mr-0.5 text-xs"></i> Reversal pending review
                                            </div>
                                            @endif
                                        </td>
                                        <td class="p-3 align-top">
                                            <div class="flex flex-wrap items-center gap-1">
                                                <a href="{{ route('properties.ownership-transfers.show', [$transfer->property_id, $transfer->id]) }}" 
                                                   class="action-btn view" data-tooltip="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING && $isSender)
                                                    <form method="POST" 
                                                          action="{{ route('properties.ownership-transfers.cancel', [$transfer->property_id, $transfer->id]) }}"
                                                          onsubmit="return confirm('Are you sure you want to cancel this transfer request? This action cannot be undone.')"
                                                          class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="action-btn delete" data-tooltip="Cancel Transfer">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED && $transfer->new_owner_email && $isSender)
                                                    <button type="button" 
                                                            onclick="resendNotification('{{ $transfer->id }}', '{{ $transfer->new_owner_email }}')"
                                                            class="action-btn assign" data-tooltip="Resend Notification">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                @endif
                                                
                                                @if($transfer->document_url)
                                                    <a href="{{ route($downloadRoute, [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn edit" data-tooltip="Download Document">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                @endif
                                                
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED)
                                                    <a href="{{ route($certificateRoute, [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn" data-tooltip="Download Certificate"
                                                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-certificate"></i>
                                                    </a>
                                                @endif
                                                
                                                @if($canRequestReversal)
                                                    <button type="button" 
                                                            onclick="showReversalRequestModal('{{ $transfer->id }}', '{{ addslashes($transfer->property->property_name ?? 'N/A') }}', '{{ $transfer->document_reference }}')"
                                                            class="action-btn" data-tooltip="Request Transfer Reversal"
                                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                                                        <i class="fas fa-undo-alt"></i>
                                                    </button>
                                                @endif
                                                
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $canResubmit && $isSender && !$hasBeenResubmitted)
                                                    <a href="{{ route('properties.ownership-transfers.resubmit', [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn" data-tooltip="Resubmit Request"
                                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-redo"></i>
                                                    </a>
                                                @endif
                                                
                                                @if($hasBeenResubmitted && $resubmittedToId && $isSender)
                                                    <a href="{{ route('properties.ownership-transfers.show', [$transfer->property_id, $resubmittedToId]) }}" 
                                                       class="action-btn" data-tooltip="View Resubmitted Transfer"
                                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-share"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} entries
                        </div>
                        <div class="pagination">
                            {{ $transfers->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Quick Transfer Modal -->
<div id="quickTransferModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideQuickTransferModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-plus mr-2" style="color: var(--primary);"></i> Initiate Property Transfer
                </h3>
                <button type="button" onclick="hideQuickTransferModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    Select a property to initiate an ownership transfer:
                </p>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($userProperties as $property)
                    <a href="{{ route('properties.ownership-transfers.create', $property->id) }}" 
                       class="block p-3 rounded-lg transition-colors"
                       style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                        <div class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $property->registration_pattern }}</div>
                    </a>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideQuickTransferModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">
                    Cancel
                </button>
            </div>
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
                    <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i> Resend Transfer Notification
                </h3>
                <button type="button" onclick="hideResendModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="resendForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            This will resend the transfer notification to the new owner at:
                        </p>
                        <p class="font-medium mt-2" style="color: var(--text-primary);" id="newOwnerEmail"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Additional Message (Optional)
                        </label>
                        <textarea name="additional_message" rows="3" class="index-custom-textarea w-full" placeholder="Add a personal message to the new owner..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                The new owner will receive an email with instructions to accept the transfer.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideResendModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Resend Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transfer Reversal Request Modal -->
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
            <form id="reversalRequestForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Request reversal for: <strong id="reversalPropertyName" class="font-semibold"></strong>
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-hashtag mr-1 text-xs"></i> Reference: <span id="reversalDocumentRef"></span>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Reversal <span class="text-danger">*</span>
                        </label>
                        <textarea name="reversal_reason" id="reversal_reason" rows="4" class="index-custom-textarea w-full" placeholder="Please explain why you need to reverse this transfer (e.g., transferred to wrong person, accidental transfer, etc.)" required></textarea>
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
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="confirm_reversal" value="1" id="confirm_reversal" class="mr-2 w-4 h-4" required>
                            <span class="text-sm" style="color: var(--text-primary);">
                                I confirm that I want to request a reversal of this property transfer
                            </span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideReversalRequestModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" id="submitReversalBtn" class="btn-warning px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Reversal Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    
    // Add confirmation for resubmit button click
    document.querySelectorAll('[data-tooltip="Resubmit Request"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('You are about to resubmit a rejected transfer request. The form will be pre-filled with your previous data. Would you like to continue?')) {
                e.preventDefault();
            }
        });
    });
    
    // Setup reversal request form submission
    const reversalForm = document.getElementById('reversalRequestForm');
    if (reversalForm) {
        reversalForm.addEventListener('submit', function(e) {
            const confirmCheckbox = document.getElementById('confirm_reversal');
            if (!confirmCheckbox.checked) {
                e.preventDefault();
                alert('Please confirm that you want to request a reversal by checking the confirmation box.');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
                submitBtn.disabled = true;
            }
        });
    }
});

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.success-message, .error-message, .info-message').forEach(msg => {
            if (msg.style.display !== 'none') msg.style.display = 'none';
        });
    }, 5000);
}

function showQuickTransferModal() {
    const modal = document.getElementById('quickTransferModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideQuickTransferModal() {
    const modal = document.getElementById('quickTransferModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function resendNotification(transferId, email) {
    const modal = document.getElementById('resendModal');
    const form = document.getElementById('resendForm');
    const emailDisplay = document.getElementById('newOwnerEmail');
    
    if (modal && form && emailDisplay) {
        form.action = `/api/ownership-transfers/${transferId}/resend-invitation`;
        emailDisplay.textContent = email;
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

function showReversalRequestModal(transferId, propertyName, documentRef) {
    const modal = document.getElementById('reversalRequestModal');
    const form = document.getElementById('reversalRequestForm');
    const propertyNameSpan = document.getElementById('reversalPropertyName');
    const documentRefSpan = document.getElementById('reversalDocumentRef');
    
    if (modal && form && propertyNameSpan && documentRefSpan) {
        form.action = `/properties/${transferId}/ownership-transfers/${transferId}/request-reversal`;
        propertyNameSpan.textContent = propertyName || 'Unknown Property';
        documentRefSpan.textContent = documentRef || 'N/A';
        
        const textarea = form.querySelector('textarea[name="reversal_reason"]');
        if (textarea) textarea.value = '';
        
        const confirmCheckbox = document.getElementById('confirm_reversal');
        if (confirmCheckbox) confirmCheckbox.checked = false;
        
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

// Tooltip initialization
document.querySelectorAll('[data-tooltip]').forEach(element => {
    element.addEventListener('mouseenter', function(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = this.getAttribute('data-tooltip');
        tooltip.style.cssText = `
            position: absolute;
            background: var(--text-primary);
            color: var(--card-bg);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            white-space: nowrap;
        `;
        document.body.appendChild(tooltip);
        
        const rect = this.getBoundingClientRect();
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
.index-custom-input,
.index-custom-dropdown,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus,
.index-custom-dropdown:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: 1px solid transparent;
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

.action-btn.assign {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.assign:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.edit {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.3);
}

.action-btn.edit:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.delete:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn:hover {
    transform: translateY(-1px);
}

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

.btn-warning {
    background-color: var(--warning) !important;
    color: white !important;
    border: 1px solid var(--warning) !important;
    transition: all 0.2s ease;
}

.btn-warning:hover {
    background-color: #e0a800 !important;
    transform: translateY(-1px);
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

table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}

table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}

table tr:last-child td {
    border-bottom: none;
}

table tr {
    background-color: var(--card-bg) !important;
}

table tr:hover td {
    background-color: var(--bg-secondary) !important;
}

.tooltip {
    pointer-events: none;
}

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