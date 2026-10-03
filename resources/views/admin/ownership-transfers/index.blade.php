{{-- admin/ownership-transfers/index.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\User;
    use Illuminate\Support\Facades\Storage;

    // Only admins can access this page
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $layout = 'layouts.app';
    $routePrefix = 'admin.ownership-transfers';
    
    // Create dynamic page title
    $pageTitle = 'Ownership Transfer Requests';
    
    // Check if there are any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $bulkSummary = session('bulk_summary');
    $forceRefresh = session('force_refresh');
    $invitationResults = session('invitation_results');
    
    // Get filter counts with proper status constants
    $totalTransfers = PropertyOwnershipTransfer::count();
    $pendingCount = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count();
    $approvedCount = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_APPROVED)->count();
    $rejectedCount = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_REJECTED)->count();
    $completedCount = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->count();
    $cancelledCount = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)->count();
    $trashedCount = PropertyOwnershipTransfer::onlyTrashed()->count();
    
    // Get reversal stats - UPDATED to include rejected and expired counts
    $pendingReversalCount = PropertyOwnershipTransfer::where('reversal_status', 'pending')
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where('is_reversed', false)
        ->count();
    $completedReversalCount = PropertyOwnershipTransfer::where('reversal_status', 'completed')
        ->where('is_reversed', true)
        ->count();
    $rejectedReversalCount = PropertyOwnershipTransfer::where('reversal_status', 'rejected')->count();
    $expiredReversalCount = PropertyOwnershipTransfer::where('reversal_status', 'expired')->count();
    
    // Get current filter
    $currentStatus = request('status');
    
    // Get statistics for dashboard
    $totalValue = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->sum('sale_amount');
    $avgProcessingTime = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->whereNotNull('completed_at')
        ->whereNotNull('created_at')
        ->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(DAY, created_at, completed_at)')) ?? 0;
    
    // Extract statuses from filterOptions
    $statuses = $filterOptions['statuses'] ?? [];
    $documentTypes = $filterOptions['document_types'] ?? [];
    $admins = $filterOptions['admins'] ?? [];
    $landlords = $filterOptions['landlords'] ?? [];
    $properties = $filterOptions['properties'] ?? [];
    
    // Bulk transfer enabled config
    $bulkTransferEnabled = config('ownership_transfer.enable_bulk_transfer', false);
    $digitalSignatureEnabled = config('ownership_transfer.require_digital_signature', false);
    
    // Check if SMS is configured
    $smsConfigured = !empty(config('sms.default')) && !empty(config('sms.providers.' . config('sms.default')));
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
                        Ownership Transfer Requests
                        @if($pendingCount > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-clock mr-1"></i> {{ $pendingCount }} Pending
                        </span>
                        @endif
                        @if($pendingReversalCount > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-undo-alt mr-1"></i> {{ $pendingReversalCount }} Reversal Requests
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Review and manage property ownership transfer requests</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalTransfers) }} transfer{{ $totalTransfers != 1 ? 's' : '' }} total</span>
                        @if($trashedCount > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-trash-alt mr-1" style="color: var(--danger);"></i>
                        <span>{{ $trashedCount }} in trash</span>
                        @endif
                        @if($totalValue > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-line mr-1" style="color: var(--success);"></i>
                        <span>Total Value: GHS {{ number_format($totalValue, 2) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                
                <!-- Reversal Requests Button -->
                @if($pendingReversalCount > 0 || $completedReversalCount > 0 || $rejectedReversalCount > 0 || $expiredReversalCount > 0)
                <a href="{{ route('admin.ownership-transfers.reversal-requests') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-1"></i> Reversal Requests 
                    @if($pendingReversalCount > 0)
                    <span class="ml-1 px-1.5 py-0.5 bg-red-500 bg-opacity-20 rounded-full text-xs">{{ $pendingReversalCount }}</span>
                    @endif
                </a>
                @endif
                
                <a href="{{ route('admin.ownership-transfers.trash') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: {{ $trashedCount > 0 ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)' }}; color: {{ $trashedCount > 0 ? 'var(--danger)' : 'var(--secondary)' }}; border: 1px solid {{ $trashedCount > 0 ? 'rgba(var(--danger-rgb), 0.3)' : 'rgba(var(--secondary-rgb), 0.3)' }};">
                    <i class="fas fa-trash-alt mr-1"></i> Trash @if($trashedCount > 0)({{ $trashedCount }})@endif
                </a>
                
                <a href="{{ route('admin.dashboard') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Success Messages -->
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-8 gap-4">
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
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Approved</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($approvedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Completed</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($completedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-check-double text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
            </div>
        </div>

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
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Avg Processing</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ round($avgProcessingTime) }} <span class="text-sm font-normal">days</span></p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-hourglass-half text-lg" style="color: var(--secondary);"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Reversals Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Pending Reversals</p>
                        <p class="text-2xl font-bold {{ $pendingReversalCount > 0 ? 'text-danger' : '' }}" style="color: {{ $pendingReversalCount > 0 ? 'var(--danger)' : 'var(--text-primary)' }};">
                            {{ number_format($pendingReversalCount) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-undo-alt text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                @if($pendingReversalCount > 0)
                <div class="mt-2">
                    <a href="{{ route('admin.ownership-transfers.reversal-requests') }}" class="text-xs" style="color: var(--danger);">
                        <i class="fas fa-arrow-right mr-1"></i> Review Reversal Requests
                    </a>
                </div>
                @endif
            </div>
        </div>

        <!-- Rejected Reversals Card - NEW -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Rejected/Expired Reversals</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ number_format($rejectedReversalCount + $expiredReversalCount) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-times-circle text-lg" style="color: var(--secondary);"></i>
                    </div>
                </div>
                @if(($rejectedReversalCount + $expiredReversalCount) > 0)
                <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                    {{ $rejectedReversalCount }} rejected, {{ $expiredReversalCount }} expired
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
                    
                    <form method="GET" action="{{ route('admin.ownership-transfers.index') }}" class="space-y-4" id="filterForm">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ request('search') }}" 
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Property, reference, owner..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Status</option>
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-undo-alt mr-1"></i> Reversal Status
                            </label>
                            <select name="reversal_status" class="index-custom-dropdown w-full">
                                <option value="">All Reversal Status</option>
                                <option value="pending" {{ request('reversal_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('reversal_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('reversal_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="completed" {{ request('reversal_status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="expired" {{ request('reversal_status') == 'expired' ? 'selected' : '' }}>Expired</option>
                                <option value="cancelled" {{ request('reversal_status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-file-alt mr-1"></i> Document Type
                            </label>
                            <select name="document_type" class="index-custom-dropdown w-full">
                                <option value="">All Types</option>
                                @foreach($documentTypes as $key => $label)
                                    <option value="{{ $key }}" {{ request('document_type') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property
                            </label>
                            <select name="property_id" class="index-custom-dropdown w-full">
                                <option value="">All Properties</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }} ({{ $property->registration_pattern }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-1"></i> Current Landlord
                            </label>
                            <select name="landlord_id" class="index-custom-dropdown w-full">
                                <option value="">All Landlords</option>
                                @foreach($landlords as $landlord)
                                    <option value="{{ $landlord->id }}" {{ request('landlord_id') == $landlord->id ? 'selected' : '' }}>
                                        {{ $landlord->name }} ({{ $landlord->phone }})
                                    </option>
                                @endforeach
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
                                <i class="fas fa-money-bill-wave mr-1"></i> Sale Amount (GHS)
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="min_amount" value="{{ request('min_amount') }}" class="index-custom-input" placeholder="Min">
                                <input type="number" name="max_amount" value="{{ request('max_amount') }}" class="index-custom-input" placeholder="Max">
                            </div>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route('admin.ownership-transfers.index') }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <!-- Reversal Requests Quick Link -->
                                <a href="{{ route('admin.ownership-transfers.reversal-requests') }}" class="block w-full text-center px-3 py-2 rounded-lg font-medium text-white" style="background-color: var(--danger);">
                                    <i class="fas fa-undo-alt mr-2"></i> Reversal Requests 
                                    @if($pendingReversalCount > 0)
                                    <span class="ml-1 px-2 py-0.5 bg-white bg-opacity-20 rounded-full text-xs">{{ $pendingReversalCount }}</span>
                                    @endif
                                </a>
                                
                                <a href="{{ route('admin.ownership-transfers.trash') }}" class="block w-full text-center px-3 py-2 rounded-lg font-medium" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                    <i class="fas fa-trash-alt mr-2"></i> View Trash @if($trashedCount > 0)<span class="ml-1 px-2 py-0.5 bg-gray-500 bg-opacity-20 rounded-full text-xs">{{ $trashedCount }}</span>@endif
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Status Distribution
                    </h3>
                    <div class="space-y-3">
                        @foreach($statuses as $key => $label)
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
                                            <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--warning);"></span>@break
                                        @case(PropertyOwnershipTransfer::STATUS_APPROVED)
                                            <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--success);"></span>@break
                                        @case(PropertyOwnershipTransfer::STATUS_REJECTED)
                                            <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></span>@break
                                        @case(PropertyOwnershipTransfer::STATUS_COMPLETED)
                                            <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--info);"></span>@break
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
                        @endforeach
                        
                        <!-- Pending Reversals Status Row -->
                        @if($pendingReversalCount > 0)
                        <div class="flex items-center justify-between pt-1">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">Pending Reversals</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--danger);">{{ $pendingReversalCount }}</span>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Rejected Reversals Status Row - NEW -->
                        @if($rejectedReversalCount > 0)
                        <div class="flex items-center justify-between pt-1">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--secondary);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">Rejected Reversals</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $rejectedReversalCount }}</span>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Expired Reversals Status Row - NEW -->
                        @if($expiredReversalCount > 0)
                        <div class="flex items-center justify-between pt-1">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--warning);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">Expired Reversals</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $expiredReversalCount }}</span>
                            </div>
                        </div>
                        @endif
                        
                        <div class="flex items-center justify-between pt-2 border-t" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">In Trash</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $trashedCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfers Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Transfer Requests
                            @if($currentStatus)
                            <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                {{ $statuses[$currentStatus] ?? ucfirst($currentStatus) }}
                            </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} entries
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        <!-- Bulk Actions Dropdown -->
                        @php
                            $hasSelectableTransfers = $transfers->filter(function($t) {
                                return in_array($t->status, [PropertyOwnershipTransfer::STATUS_PENDING, PropertyOwnershipTransfer::STATUS_APPROVED]);
                            })->count() > 0;
                        @endphp
                        
                        @if($hasSelectableTransfers)
                        <div class="relative">
                            <button type="button" id="bulkActionsBtn" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-check-double mr-2"></i> Bulk Actions
                                <i class="fas fa-chevron-down ml-2 text-xs"></i>
                            </button>
                            
                            <div id="bulkActionsDropdown" class="absolute right-0 mt-2 w-64 rounded-lg shadow-lg z-10 hidden" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                <div class="py-1">
                                    <button type="button" onclick="showBulkApproveModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i> Bulk Approve
                                    </button>
                                    <button type="button" onclick="showBulkRejectModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i> Bulk Reject
                                    </button>
                                    <hr class="my-1" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showBulkSoftDeleteModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-trash-alt text-red-500 mr-2"></i> Bulk Move to Trash
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Reversal Requests Button in Action Bar -->
                        <a href="{{ route('admin.ownership-transfers.reversal-requests') }}" 
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-undo-alt mr-2"></i> Reversal Requests
                            @if($pendingReversalCount > 0)
                            <span class="ml-1 px-2 py-0.5 bg-red-500 bg-opacity-20 rounded-full text-xs">{{ $pendingReversalCount }}</span>
                            @endif
                        </a>
                        
                        <div class="flex items-center space-x-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Show:</span>
                            <select onchange="updatePerPage(this.value)" class="index-custom-dropdown text-sm py-1 px-2 rounded">
                                <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        
                        <a href="{{ route('admin.ownership-transfers.export', ['csv']) . '?' . http_build_query(request()->except(['page', '_token'])) }}" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Export
                        </a>
                    </div>
                </div>

                @if($transfers->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-exchange-alt text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No ownership transfer requests found</h4>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1200px]" id="transfersTable">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded" style="width: 18px; height: 18px;">
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 220px;">
                                        Property / Reference
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                        Current Owner
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        New Owner
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 100px;">
                                        Transfer Date
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 110px;">
                                        Amount
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 110px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 160px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="transfersTableBody">
                                @foreach($transfers as $transfer)
                                @php
                                    $isExistingLandlord = $transfer->metadata['is_existing_landlord'] ?? false;
                                    $invitationStatus = $isExistingLandlord ? 'no_invitation_needed' : ($transfer->metadata['invitation'] ?? null);
                                    $canBeBulkSelected = in_array($transfer->status, [PropertyOwnershipTransfer::STATUS_PENDING, PropertyOwnershipTransfer::STATUS_APPROVED]);
                                    
                                    // =============================================
                                    // UPDATED: Allow deletion for transfers with pending reversals
                                    // =============================================
                                    $canBeTrashed = false;
                                    $hasPendingReversal = $transfer->reversal_status === 'pending' && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED;
                                    
                                    if ($transfer->isReversalRecord()) {
                                        // Reversal records can always be moved to trash (audit trail cleanup)
                                        $canBeTrashed = true;
                                    } elseif ($hasPendingReversal) {
                                        // ✅ FIX: Transfers with pending reversals can be trashed (admin cleanup)
                                        $canBeTrashed = true;
                                    } else {
                                        // Regular transfers only for rejected, cancelled, or completed
                                        $canBeTrashed = in_array($transfer->status, [
                                            PropertyOwnershipTransfer::STATUS_REJECTED,
                                            PropertyOwnershipTransfer::STATUS_CANCELLED,
                                            PropertyOwnershipTransfer::STATUS_COMPLETED
                                        ]);
                                    }
                                    
                                    $hasCertificate = $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && (
                                        $transfer->certificate_url || 
                                        Storage::exists("public/ownership-transfers/certificates/{$transfer->id}.pdf") ||
                                        isset($transfer->metadata['certificate_generated_at'])
                                    );
                                    
                                    // UPDATED: Allow reversal request after rejection/expiration
                                    $canRequestReversal = $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && 
                                                          !$transfer->is_reversed && 
                                                          $transfer->reversal_status !== 'pending'; // Allow if rejected or expired
                                @endphp
                                <tr data-transfer-id="{{ $transfer->id }}" 
                                    data-status="{{ $transfer->status }}"
                                    data-property-id="{{ $transfer->property_id }}"
                                    data-property-name="{{ addslashes($transfer->property->property_name ?? 'N/A') }}"
                                    data-document-ref="{{ $transfer->document_reference }}"
                                    data-new-owner-email="{{ $transfer->new_owner_email }}"
                                    data-new-owner-phone="{{ $transfer->new_owner_phone }}"
                                    data-is-existing-landlord="{{ $isExistingLandlord ? 'true' : 'false' }}"
                                    data-can-bulk="{{ $canBeBulkSelected ? 'true' : 'false' }}"
                                    data-has-pending-reversal="{{ $hasPendingReversal ? 'true' : 'false' }}">
                                    
                                    <td class="p-3 text-center align-top">
                                        <input type="checkbox" class="transfer-checkbox" value="{{ $transfer->id }}" 
                                               data-can-bulk="{{ $canBeBulkSelected ? 'true' : 'false' }}"
                                               {{ !$canBeBulkSelected ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' }}
                                               onclick="updateBulkActions()">
                                        @if(!$canBeBulkSelected)
                                        <div class="text-xs text-muted mt-1" style="color: var(--text-secondary);">Not selectable</div>
                                        @endif
                                    </td>
                                    
                                    <!-- COLUMN 1: Property & Reference Details -->
                                    <td class="p-3 align-top">
                                        <div>
                                            <div class="font-semibold text-sm" style="color: var(--text-primary);">
                                                <a href="{{ route('properties.show', $transfer->property_id) }}" class="hover:text-primary transition-colors">
                                                    {{ $transfer->property->property_name ?? 'N/A' }}
                                                </a>
                                            </div>
                                            <div class="text-xs mt-1 font-mono" style="color: var(--text-secondary);">
                                                <i class="fas fa-hashtag mr-1 text-xs"></i> Ref: {{ $transfer->document_reference }}
                                            </div>
                                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                <i class="fas fa-file-alt mr-1 text-xs"></i> {{ $transfer->document_type_label }}
                                            </div>
                                            @if($transfer->property->registration_pattern)
                                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                <i class="fas fa-registered mr-1 text-xs"></i> {{ $transfer->property->registration_pattern }}
                                            </div>
                                            @endif
                                            @if($transfer->is_reversed)
                                            <div class="text-xs mt-1">
                                                <span class="px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-undo-alt mr-0.5 text-xs"></i> Reversed
                                                </span>
                                            </div>
                                            @endif
                                            @if($transfer->isReversalRecord())
                                            <div class="text-xs mt-1">
                                                <span class="px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-exchange-alt mr-0.5 text-xs"></i> Reversal Record
                                                </span>
                                            </div>
                                            @endif
                                            @if($hasPendingReversal)
                                            <div class="text-xs mt-1">
                                                <span class="px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-clock mr-0.5 text-xs"></i> Reversal Pending
                                                </span>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMN 2: Current Owner Details -->
                                    <td class="p-3 align-top">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-2 mt-0.5">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                                    <i class="fas fa-user-tie text-xs" style="color: var(--secondary);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                    {{ $transfer->currentLandlord->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    <i class="fas fa-phone mr-1 text-xs"></i> {{ $transfer->currentLandlord->phone ?? 'N/A' }}
                                                </div>
                                                @if($transfer->currentLandlord && $transfer->currentLandlord->email)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-envelope mr-1 text-xs"></i> {{ $transfer->currentLandlord->email }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMN 3: New Owner Details -->
                                    <td class="p-3 align-top">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-2 mt-0.5">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                                                    <i class="fas fa-user-plus text-xs" style="color: var(--info);"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-sm flex items-center flex-wrap gap-1" style="color: var(--text-primary);">
                                                    {{ $transfer->new_owner_name }}
                                                    @if($isExistingLandlord)
                                                    <span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-0.5 text-xs"></i> Existing
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    <i class="fas fa-phone mr-1 text-xs"></i> {{ $transfer->new_owner_phone ?? 'N/A' }}
                                                </div>
                                                @if($transfer->new_owner_email)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-envelope mr-1 text-xs"></i> {{ $transfer->new_owner_email }}
                                                </div>
                                                @endif
                                                @if($isExistingLandlord && $transfer->newLandlord)
                                                <div class="text-xs mt-0.5" style="color: var(--success);">
                                                    <i class="fas fa-check-circle mr-0.5 text-xs"></i> Registered user
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMN 4: Transfer Date -->
                                    <td class="p-3 align-top">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $transfer->transfer_date ? $transfer->transfer_date->format('M j, Y') : 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1 text-xs"></i> Requested: {{ $transfer->created_at->diffForHumans() }}
                                        </div>
                                        @if($transfer->completed_at)
                                        <div class="text-xs mt-0.5" style="color: var(--success);">
                                            <i class="fas fa-check-circle mr-1 text-xs"></i> Completed: {{ $transfer->completed_at->format('M j, Y') }}
                                        </div>
                                        @endif
                                      </td>
                                    
                                    <!-- COLUMN 5: Sale Amount -->
                                    <td class="p-3 align-top">
                                        @if($transfer->sale_amount)
                                            <div class="font-semibold text-sm" style="color: var(--primary);">
                                                GHS {{ number_format($transfer->sale_amount, 2) }}
                                            </div>
                                        @else
                                            <div class="text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-minus-circle mr-1 text-xs"></i> Not specified
                                            </div>
                                        @endif
                                      </td>
                                    
                                    <!-- COLUMN 6: Status -->
                                    <td class="p-3 align-top">
                                        @php
                                            $statusConfigs = [
                                                PropertyOwnershipTransfer::STATUS_PENDING => ['class' => 'badge-warning', 'icon' => 'clock', 'text' => 'Pending'],
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
                                        @if($isExistingLandlord && $transfer->status === PropertyOwnershipTransfer::STATUS_APPROVED)
                                        <div class="text-xs mt-1" style="color: var(--success);">
                                            <i class="fas fa-check-circle mr-0.5 text-xs"></i> No invitation needed
                                        </div>
                                        @endif
                                        @if($transfer->rejection_reason)
                                        <div class="text-xs mt-1" style="color: var(--danger);">
                                            <i class="fas fa-comment mr-0.5 text-xs"></i> Rejected: {{ Str::limit($transfer->rejection_reason, 40) }}
                                        </div>
                                        @endif
                                        @if($transfer->reversal_status === 'pending')
                                        <div class="text-xs mt-1" style="color: var(--warning);">
                                            <i class="fas fa-clock mr-0.5 text-xs"></i> Reversal Pending Review
                                        </div>
                                        @endif
                                        @if($transfer->reversal_status === 'rejected')
                                        <div class="text-xs mt-1" style="color: var(--danger);">
                                            <i class="fas fa-times-circle mr-0.5 text-xs"></i> Reversal Rejected
                                        </div>
                                        @endif
                                        @if($transfer->reversal_status === 'expired')
                                        <div class="text-xs mt-1" style="color: var(--secondary);">
                                            <i class="fas fa-clock mr-0.5 text-xs"></i> Reversal Expired
                                        </div>
                                        @endif
                                      </td>
                                    
                                    <!-- COLUMN 7: Actions -->
                                    <td class="p-3 align-top">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <a href="{{ route('admin.ownership-transfers.show', $transfer->id) }}" class="action-btn view" data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_PENDING)
                                                <button type="button" 
                                                        onclick="showSingleApproveModal('{{ $transfer->property_id }}', '{{ $transfer->id }}', '{{ addslashes($transfer->property->property_name ?? 'N/A') }}', '{{ addslashes($transfer->new_owner_email) }}', '{{ addslashes($transfer->new_owner_phone) }}', {{ $isExistingLandlord ? 'true' : 'false' }})"
                                                        class="action-btn assign" 
                                                        data-tooltip="{{ $isExistingLandlord ? 'Approve Transfer (No invitation needed)' : 'Approve Transfer' }}">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                
                                                <button type="button" 
                                                        onclick="showRejectModal('{{ $transfer->property_id }}', '{{ $transfer->id }}')"
                                                        class="action-btn delete" 
                                                        data-tooltip="Reject Transfer">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @endif
                                            
                                            <!-- Certificate Icon - Shows for ALL completed transfers -->
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED)
                                                <a href="{{ route('admin.ownership-transfers.download-certificate', $transfer->id) }}" 
                                                   class="action-btn" 
                                                   data-tooltip="Download Certificate" 
                                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                    <i class="fas fa-certificate"></i>
                                                </a>
                                            @endif
                                            
                                            @if($transfer->document_url)
                                                <a href="{{ route('admin.ownership-transfers.download-document', $transfer->id) }}" 
                                                   class="action-btn" 
                                                   data-tooltip="Download Document" 
                                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @endif
                                            
                                            <!-- Request Reversal Button for Completed Transfers (allows resubmission after rejection) -->
                                            @if($canRequestReversal)
                                                <button type="button" 
                                                        onclick="showRequestReversalModal('{{ $transfer->id }}', '{{ addslashes($transfer->property->property_name ?? 'N/A') }}', '{{ $transfer->document_reference }}')"
                                                        class="action-btn" 
                                                        data-tooltip="{{ $transfer->reversal_status === 'rejected' ? 'Resubmit Reversal Request' : 'Request Reversal' }}"
                                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-undo-alt"></i>
                                                </button>
                                            @endif
                                            
                                            <!-- DELETE ICON - Updated to handle pending reversals -->
                                            @if($canBeTrashed)
                                                <button type="button" 
                                                        onclick="showSoftDeleteModal('{{ $transfer->id }}', '{{ addslashes($transfer->property->property_name ?? 'N/A') }}', {{ $hasPendingReversal ? 'true' : 'false' }})"
                                                        class="action-btn trash" 
                                                        data-tooltip="{{ $transfer->isReversalRecord() ? 'Delete Reversal Record' : ($hasPendingReversal ? 'Delete Transfer (will cancel pending reversal)' : 'Move to Trash') }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
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

<!-- ============================================ -->
<!-- MODAL TEMPLATES (Keep all existing modals as they were) -->
<!-- ============================================ -->

<!-- Single Approve Modal -->
<div id="singleApproveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideSingleApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Transfer Request
                </h3>
                <button type="button" onclick="hideSingleApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="singleApproveForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Approve transfer for: <strong id="singleApprovePropertyName" class="font-semibold"></strong>
                        </p>
                        <div id="existingLandlordWarning" class="hidden mb-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    This transfer is for an <strong>existing landlord</strong>. No invitation will be sent.
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="invitationChannelsSection" class="mb-4">
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
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="singleApproveEmailDisplay"></p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">Recommended</span>
                            </label>
                            
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="sms" class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-sms mr-2" style="color: var(--warning);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">SMS</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="singleApprovePhoneDisplay"></p>
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
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);" id="approvalMessage">
                                This will approve the transfer request. The current landlord will be notified, and an invitation will be sent to the new owner via your selected channels.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideSingleApproveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Reject Modal -->
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
            <form id="rejectForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="rejection_reason" rows="4" class="index-custom-textarea w-full" placeholder="Please provide a reason for rejecting this transfer request..." required></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
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

<!-- Single Soft Delete Modal - UPDATED to handle pending reversals -->
<div id="softDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideSoftDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Move to Trash
                </h3>
                <button type="button" onclick="hideSoftDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="softDeleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Are you sure you want to move this transfer request to trash?
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <strong>Property:</strong> <span id="softDeletePropertyName" class="font-medium" style="color: var(--text-primary);"></span>
                        </p>
                    </div>
                    
                    <!-- Pending Reversal Warning -->
                    <div id="pendingReversalWarning" class="hidden mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--warning);">Pending Reversal Request</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    This transfer has a pending reversal request. Moving it to trash will cancel the pending reversal.
                                </p>
                                <input type="hidden" name="confirm_pending_reversal" id="confirmPendingReversal" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for moving to trash (Optional)
                        </label>
                        <textarea name="deletion_reason" rows="3" class="index-custom-textarea w-full" placeholder="Enter reason for moving to trash..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Warning:</p>
                                <p>The transfer will be moved to trash. You can restore it later from the trash section.</p>
                                <p class="mt-2 text-xs">This action will be logged for audit purposes.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideSoftDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Reversal Modal -->
<div id="requestReversalModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRequestReversalModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--danger);"></i> Request Transfer Reversal
                </h3>
                <button type="button" onclick="hideRequestReversalModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="requestReversalForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Request reversal for: <strong id="reversalPropertyName" class="font-semibold"></strong>
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-hashtag mr-1 text-xs"></i> Reference: <span id="reversalDocumentRef"></span>
                        </p>
                        @if(isset($transfer) && $transfer->reversal_status === 'rejected')
                        <div class="mt-2 p-2 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <p class="text-xs" style="color: var(--warning);">
                                <i class="fas fa-info-circle mr-1"></i> 
                                Your previous reversal request was rejected. You can submit a new request with additional information.
                            </p>
                        </div>
                        @endif
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Reversal <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reversal_reason" rows="4" class="index-custom-textarea w-full" placeholder="Please explain why this transfer needs to be reversed (e.g., transferred to wrong person, error in document, etc.)..." required></textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> This reason will be reviewed by an administrator.
                        </p>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Important:</p>
                                <p>Reversing a transfer will:</p>
                                <ul class="list-disc list-inside mt-1 text-xs space-y-0.5">
                                    <li>Restore property ownership to the original landlord</li>
                                    <li>Keep a complete audit trail of the reversal</li>
                                    <li>Notify all parties involved</li>
                                </ul>
                                <p class="mt-2 text-xs">This request requires admin approval before execution.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRequestReversalModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Reversal Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Approve Modal -->
<div id="bulkApproveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Bulk Approve Transfers
                </h3>
                <button type="button" onclick="hideBulkApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkApproveForm" method="POST" action="{{ route('admin.ownership-transfers.bulk-approve') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected transfers:</p>
                        <div id="selectedTransfersList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-1"></i> Send Invitation To New Owners
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="email" checked class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2" style="color: var(--info);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">Email</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Send email invitations to all selected transfers</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);">
                                <input type="checkbox" name="channels[]" value="sms" class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-sms mr-2" style="color: var(--warning);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">SMS</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Send SMS invitations to transfers with phone numbers</p>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs mt-2" id="bulkExistingLandlordNote" style="color: var(--warning); display: none;">
                            <i class="fas fa-info-circle mr-1"></i> Note: Existing landlords will not receive invitations.
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full" placeholder="Add any notes about this bulk approval..."></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="auto_complete" value="1" class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                            <span class="text-sm" style="color: var(--text-primary);">Auto-complete after approval</span>
                        </label>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Automatically complete transfers after approval (if eligible)
                        </p>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This will approve all selected transfer requests. Invitations will be sent via your selected channels (existing landlords will be skipped).
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkApproveTransferIdsContainer"></div>
                    <button type="button" onclick="hideBulkApproveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div id="bulkRejectModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Bulk Reject Transfers
                </h3>
                <button type="button" onclick="hideBulkRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkRejectForm" method="POST" action="{{ route('admin.ownership-transfers.bulk-reject') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected transfers:</p>
                        <div id="bulkSelectedTransfersList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="rejection_reason" rows="3" class="index-custom-textarea w-full" placeholder="Please provide a reason for rejecting these transfers..." required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="allow_resubmission" value="1" id="bulkAllowResubmission" class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                            <span class="text-sm" style="color: var(--text-primary);">Allow resubmission after</span>
                            <input type="number" name="resubmission_days" class="ml-2 index-custom-input w-20 text-center" value="7" min="1" max="90" disabled>
                            <span class="text-sm ml-1" style="color: var(--text-primary);">days</span>
                        </label>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">This action will reject all selected transfer requests and cannot be undone.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkRejectTransferIdsContainer"></div>
                    <button type="button" onclick="hideBulkRejectModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Soft Delete Modal - UPDATED to handle pending reversals -->
<div id="bulkSoftDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkSoftDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Bulk Move to Trash
                </h3>
                <button type="button" onclick="hideBulkSoftDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkSoftDeleteForm" method="POST" action="{{ route('admin.ownership-transfers.bulk-soft-delete') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected transfers:</p>
                        <div id="bulkSoftDeleteTransfersList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div id="bulkPendingReversalWarning" class="hidden mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--warning);">Pending Reversal Requests</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);" id="bulkPendingCountMessage"></p>
                                <input type="hidden" name="confirm_pending_reversal" id="bulkConfirmPendingReversal" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for moving to trash (Optional)
                        </label>
                        <textarea name="deletion_reason" rows="3" class="index-custom-textarea w-full" placeholder="Enter reason for moving these transfers to trash..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Warning:</p>
                                <p>Selected transfers will be moved to trash. You can restore them later from the trash section.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkSoftDeleteTransferIdsContainer"></div>
                    <button type="button" onclick="hideBulkSoftDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let selectedTransferIds = [];
let currentSoftDeleteTransferId = null;
let currentSoftDeletePropertyName = null;
let currentHasPendingReversal = false;

document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    initTooltips();
    
    // Bulk actions dropdown
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    const bulkActionsDropdown = document.getElementById('bulkActionsDropdown');
    
    if (bulkActionsBtn) {
        bulkActionsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bulkActionsDropdown.classList.toggle('hidden');
        });
    }
    
    document.addEventListener('click', function(event) {
        if (bulkActionsDropdown && !bulkActionsDropdown.classList.contains('hidden')) {
            if (!bulkActionsBtn.contains(event.target) && !bulkActionsDropdown.contains(event.target)) {
                bulkActionsDropdown.classList.add('hidden');
            }
        }
    });
    
    // Enable/disable resubmission days in bulk reject
    const bulkAllowResubmit = document.getElementById('bulkAllowResubmission');
    if (bulkAllowResubmit) {
        bulkAllowResubmit.addEventListener('change', function() {
            const daysInput = document.querySelector('#bulkRejectModal input[name="resubmission_days"]');
            if (daysInput) daysInput.disabled = !this.checked;
        });
    }
    
    // Form submissions
    const singleApproveForm = document.getElementById('singleApproveForm');
    if (singleApproveForm) {
        singleApproveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSingleApprove(this);
        });
    }
    
    const requestReversalForm = document.getElementById('requestReversalForm');
    if (requestReversalForm) {
        requestReversalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReversalRequest(this);
        });
    }
    
    const bulkApproveForm = document.getElementById('bulkApproveForm');
    if (bulkApproveForm) {
        bulkApproveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'approve');
        });
    }
    
    const bulkRejectForm = document.getElementById('bulkRejectForm');
    if (bulkRejectForm) {
        bulkRejectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'reject');
        });
    }
    
    const bulkSoftDeleteForm = document.getElementById('bulkSoftDeleteForm');
    if (bulkSoftDeleteForm) {
        bulkSoftDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'soft-delete');
        });
    }
    
    const rejectForm = document.getElementById('rejectForm');
    if (rejectForm) {
        rejectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSingleAction(this, 'reject');
        });
    }
    
    const softDeleteForm = document.getElementById('softDeleteForm');
    if (softDeleteForm) {
        softDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSoftDelete(this);
        });
    }
});

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.transfer-checkbox');
    checkboxes.forEach(checkbox => {
        if (!checkbox.disabled) {
            checkbox.checked = selectAll.checked;
        }
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.transfer-checkbox:checked');
    selectedTransferIds = Array.from(checkboxes)
        .filter(cb => !cb.disabled && cb.getAttribute('data-can-bulk') === 'true')
        .map(cb => cb.value);
    
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    
    if (bulkActionsBtn) {
        if (selectedTransferIds.length > 0) {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${selectedTransferIds.length} Selected <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
            bulkActionsBtn.style.opacity = '1';
            bulkActionsBtn.disabled = false;
        } else {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> Bulk Actions <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        }
    }
}

// ============================================
// SINGLE APPROVE FUNCTIONS (unchanged)
// ============================================
function showSingleApproveModal(propertyId, transferId, propertyName, newOwnerEmail, newOwnerPhone, isExistingLandlord) {
    const modal = document.getElementById('singleApproveModal');
    const form = document.getElementById('singleApproveForm');
    const propertyNameSpan = document.getElementById('singleApprovePropertyName');
    const emailDisplay = document.getElementById('singleApproveEmailDisplay');
    const phoneDisplay = document.getElementById('singleApprovePhoneDisplay');
    const invitationSection = document.getElementById('invitationChannelsSection');
    const existingWarning = document.getElementById('existingLandlordWarning');
    const approvalMessage = document.getElementById('approvalMessage');
    
    form.action = `/admin/properties/${propertyId}/ownership-transfers/${transferId}/approve`;
    propertyNameSpan.textContent = propertyName || 'Unknown Property';
    emailDisplay.textContent = newOwnerEmail || 'No email address available';
    phoneDisplay.textContent = newOwnerPhone || 'No phone number available';
    
    if (isExistingLandlord === true || isExistingLandlord === 'true') {
        invitationSection.style.display = 'none';
        existingWarning.classList.remove('hidden');
        approvalMessage.innerHTML = 'This will approve the transfer request. The new owner already has an account, so no invitation will be sent. The current landlord will be notified.';
    } else {
        invitationSection.style.display = 'block';
        existingWarning.classList.add('hidden');
        approvalMessage.innerHTML = 'This will approve the transfer request. The current landlord will be notified, and an invitation will be sent to the new owner via your selected channels.';
    }
    
    const emailCheckbox = form.querySelector('input[name="channels[]"][value="email"]');
    const smsCheckbox = form.querySelector('input[name="channels[]"][value="sms"]');
    
    if (emailCheckbox) {
        emailCheckbox.checked = !!newOwnerEmail && newOwnerEmail !== '';
        emailCheckbox.disabled = !newOwnerEmail || newOwnerEmail === '';
    }
    
    if (smsCheckbox) {
        smsCheckbox.checked = !!newOwnerPhone && newOwnerPhone !== '';
        smsCheckbox.disabled = !newOwnerPhone || newOwnerPhone === '';
    }
    
    const adminNotes = form.querySelector('textarea[name="admin_notes"]');
    if (adminNotes) adminNotes.value = '';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideSingleApproveModal() {
    const modal = document.getElementById('singleApproveModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function submitSingleApprove(form) {
    const invitationSection = document.getElementById('invitationChannelsSection');
    const isExistingLandlord = invitationSection && invitationSection.style.display === 'none';
    
    let channels = [];
    if (!isExistingLandlord) {
        channels = Array.from(form.querySelectorAll('input[name="channels[]"]:checked')).map(cb => cb.value);
        
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
            let message = data.message || 'Transfer approved successfully.';
            showNotification('success', message);
            hideSingleApproveModal();
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

// ============================================
// REQUEST REVERSAL FUNCTIONS (updated with resubmission message)
// ============================================
function showRequestReversalModal(transferId, propertyName, documentRef) {
    const modal = document.getElementById('requestReversalModal');
    const form = document.getElementById('requestReversalForm');
    const propertyNameSpan = document.getElementById('reversalPropertyName');
    const documentRefSpan = document.getElementById('reversalDocumentRef');
    
    form.action = `/admin/ownership-transfers/${transferId}/request-reversal`;
    propertyNameSpan.textContent = propertyName || 'Unknown Property';
    documentRefSpan.textContent = documentRef || 'N/A';
    
    const textarea = form.querySelector('textarea[name="reversal_reason"]');
    if (textarea) textarea.value = '';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideRequestReversalModal() {
    const modal = document.getElementById('requestReversalModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function submitReversalRequest(form) {
    const reason = form.querySelector('textarea[name="reversal_reason"]').value.trim();
    
    if (!reason) {
        showNotification('error', 'Please provide a reason for the reversal request.');
        return;
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
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
            showNotification('success', data.message || 'Reversal request submitted successfully. An administrator will review it.');
            hideRequestReversalModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred while submitting the reversal request');
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

// ============================================
// REJECT FUNCTIONS (unchanged)
// ============================================
function showRejectModal(propertyId, transferId) {
    const modal = document.getElementById('rejectModal');
    const form = document.getElementById('rejectForm');
    
    form.action = `/admin/properties/${propertyId}/ownership-transfers/${transferId}/reject`;
    form.reset();
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ============================================
// SOFT DELETE FUNCTIONS - UPDATED for pending reversals
// ============================================
function showSoftDeleteModal(transferId, propertyName, hasPendingReversal = false) {
    currentSoftDeleteTransferId = transferId;
    currentSoftDeletePropertyName = propertyName;
    currentHasPendingReversal = hasPendingReversal;
    
    const modal = document.getElementById('softDeleteModal');
    const form = document.getElementById('softDeleteForm');
    const propertyNameSpan = document.getElementById('softDeletePropertyName');
    const pendingWarning = document.getElementById('pendingReversalWarning');
    const confirmPending = document.getElementById('confirmPendingReversal');
    
    form.action = `/admin/ownership-transfers/${transferId}`;
    propertyNameSpan.textContent = propertyName || 'Unknown Property';
    
    // Show warning if transfer has pending reversal
    if (hasPendingReversal === true || hasPendingReversal === 'true') {
        pendingWarning.classList.remove('hidden');
        if (confirmPending) confirmPending.value = '1';
    } else {
        pendingWarning.classList.add('hidden');
        if (confirmPending) confirmPending.value = '0';
    }
    
    const textarea = form.querySelector('textarea[name="deletion_reason"]');
    if (textarea) textarea.value = '';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideSoftDeleteModal() {
    const modal = document.getElementById('softDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentSoftDeleteTransferId = null;
        currentSoftDeletePropertyName = null;
        currentHasPendingReversal = false;
    }
}

function submitSoftDelete(form) {
    if (!currentSoftDeleteTransferId) {
        showNotification('error', 'No transfer selected.');
        return;
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    const isReversalRecord = document.querySelector(`tr[data-transfer-id="${currentSoftDeleteTransferId}"]`)?.getAttribute('data-is-reversal') === 'true';
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'DELETE',
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
            const successMessage = data.message || (isReversalRecord ? 'Reversal record moved to trash successfully.' : 'Transfer moved to trash successfully.');
            showNotification('success', successMessage);
            
            const row = document.querySelector(`tr[data-transfer-id="${currentSoftDeleteTransferId}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.remove();
                    updateTableAfterRemoval();
                    updateCountersAfterSoftDelete(1);
                    
                    const remainingRows = document.querySelectorAll('#transfersTableBody tr').length;
                    if (remainingRows === 0) {
                        showEmptyState();
                    }
                    
                    setTimeout(() => window.location.reload(), 1500);
                }, 300);
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }
            
            hideSoftDeleteModal();
            selectedTransferIds = [];
            updateBulkActions();
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request: ' + error.message);
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// ============================================
// BULK ACTION FUNCTIONS (updated for pending reversals)
// ============================================
function showBulkApproveModal() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'Please select at least one transfer request to approve (only Pending/Approved status transfers can be bulk approved).');
        return;
    }
    
    let hasExistingLandlord = false;
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row && row.getAttribute('data-is-existing-landlord') === 'true') {
            hasExistingLandlord = true;
        }
    });
    
    const modal = document.getElementById('bulkApproveModal');
    const selectedTransfersDiv = document.getElementById('selectedTransfersList');
    const existingNote = document.getElementById('bulkExistingLandlordNote');
    
    selectedTransfersDiv.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row) {
            const propertyName = row.getAttribute('data-property-name') || 'Unknown';
            const isExisting = row.getAttribute('data-is-existing-landlord') === 'true';
            const div = document.createElement('div');
            div.className = 'text-sm py-1 flex items-center justify-between';
            div.innerHTML = `
                <span><i class="fas fa-building mr-2 text-gray-400"></i> ${escapeHtml(propertyName)}</span>
                ${isExisting ? '<span class="text-xs text-success"><i class="fas fa-check-circle mr-1"></i> Existing</span>' : ''}
            `;
            selectedTransfersDiv.appendChild(div);
        }
    });
    
    if (hasExistingLandlord) {
        existingNote.style.display = 'block';
    } else {
        existingNote.style.display = 'none';
    }
    
    const container = document.getElementById('bulkApproveTransferIdsContainer');
    container.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'transfer_ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkApproveModal() {
    const modal = document.getElementById('bulkApproveModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkRejectModal() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'Please select at least one transfer request to reject (only Pending/Approved status transfers can be bulk rejected).');
        return;
    }
    
    const modal = document.getElementById('bulkRejectModal');
    const selectedTransfersDiv = document.getElementById('bulkSelectedTransfersList');
    
    selectedTransfersDiv.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row) {
            const propertyName = row.getAttribute('data-property-name') || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-building mr-2 text-gray-400"></i> ${escapeHtml(propertyName)}`;
            selectedTransfersDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkRejectTransferIdsContainer');
    container.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'transfer_ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const allowResubmit = document.getElementById('bulkAllowResubmission');
    if (allowResubmit) {
        allowResubmit.checked = false;
        const daysInput = document.querySelector('#bulkRejectModal input[name="resubmission_days"]');
        if (daysInput) daysInput.disabled = true;
    }
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkRejectModal() {
    const modal = document.getElementById('bulkRejectModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkSoftDeleteModal() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'Please select at least one transfer request to move to trash.');
        return;
    }
    
    // Check for transfers with pending reversals
    let pendingReversalCount = 0;
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row && row.getAttribute('data-has-pending-reversal') === 'true') {
            pendingReversalCount++;
        }
    });
    
    const modal = document.getElementById('bulkSoftDeleteModal');
    const selectedTransfersDiv = document.getElementById('bulkSoftDeleteTransfersList');
    const bulkPendingWarning = document.getElementById('bulkPendingReversalWarning');
    const bulkPendingMessage = document.getElementById('bulkPendingCountMessage');
    const bulkConfirmPending = document.getElementById('bulkConfirmPendingReversal');
    
    selectedTransfersDiv.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row) {
            const propertyName = row.getAttribute('data-property-name') || 'Unknown';
            const hasPending = row.getAttribute('data-has-pending-reversal') === 'true';
            const div = document.createElement('div');
            div.className = 'text-sm py-1 flex items-center justify-between';
            div.innerHTML = `
                <span><i class="fas fa-building mr-2 text-gray-400"></i> ${escapeHtml(propertyName)}</span>
                ${hasPending ? '<span class="text-xs text-warning"><i class="fas fa-clock mr-1"></i> Pending Reversal</span>' : ''}
            `;
            selectedTransfersDiv.appendChild(div);
        }
    });
    
    // Show warning if there are transfers with pending reversals
    if (pendingReversalCount > 0) {
        bulkPendingWarning.classList.remove('hidden');
        bulkPendingMessage.textContent = `${pendingReversalCount} selected transfer(s) have pending reversal requests. Deleting them will cancel these reversals.`;
        if (bulkConfirmPending) bulkConfirmPending.value = '1';
    } else {
        bulkPendingWarning.classList.add('hidden');
        if (bulkConfirmPending) bulkConfirmPending.value = '0';
    }
    
    const container = document.getElementById('bulkSoftDeleteTransferIdsContainer');
    container.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'transfer_ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkSoftDeleteModal() {
    const modal = document.getElementById('bulkSoftDeleteModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitBulkAction(form, actionType) {
    if (actionType === 'approve') {
        const channels = Array.from(form.querySelectorAll('input[name="channels[]"]:checked')).map(cb => cb.value);
        if (channels.length === 0) {
            showNotification('error', 'Please select at least one channel (Email or SMS) to send invitations.');
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
            showNotification('success', data.message);
            
            if (actionType === 'soft-delete') {
                selectedTransferIds.forEach((id, index) => {
                    const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            row.remove();
                            updateTableAfterRemoval();
                        }, 300);
                    }
                });
                
                updateCountersAfterSoftDelete(selectedTransferIds.length);
                selectedTransferIds = [];
                updateBulkActions();
                
                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                
                setTimeout(() => {
                    const remainingRows = document.querySelectorAll('#transfersTableBody tr').length;
                    if (remainingRows === 0) {
                        showEmptyState();
                    }
                    setTimeout(() => window.location.reload(), 1500);
                }, 500);
            }
            
            if (actionType === 'approve' || actionType === 'reject') {
                setTimeout(() => window.location.reload(), 1500);
            }
            
            if (actionType === 'approve') hideBulkApproveModal();
            if (actionType === 'reject') hideBulkRejectModal();
            if (actionType === 'soft-delete') hideBulkSoftDeleteModal();
            
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

function submitSingleAction(form, actionType) {
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
            showNotification('success', data.message);
            if (actionType === 'reject') hideRejectModal();
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

// ============================================
// HELPER FUNCTIONS (unchanged)
// ============================================

function updateTableAfterRemoval() {
    const remainingRows = document.querySelectorAll('#transfersTableBody tr').length;
    const paginationDiv = document.querySelector('.flex.flex-col.md\\:flex-row.justify-between');
    if (paginationDiv) {
        const textDiv = paginationDiv.querySelector('.text-sm');
        if (textDiv) {
            const currentPage = getCurrentPage();
            const perPage = getPerPage();
            const firstItem = ((currentPage - 1) * perPage) + 1;
            const lastItem = Math.min(currentPage * perPage, remainingRows);
            textDiv.textContent = `Showing ${firstItem} to ${lastItem} of ${remainingRows} entries`;
        }
    }
}

function updateCountersAfterSoftDelete(deletedCount) {
    // Update total transfers count in stats card
    const totalSpan = document.querySelector('.card .text-2xl.font-bold');
    if (totalSpan && totalSpan.closest('.card')?.querySelector('.text-xs')?.textContent?.includes('Total')) {
        let currentTotal = parseInt(totalSpan.textContent.replace(/,/g, ''));
        if (!isNaN(currentTotal)) {
            totalSpan.textContent = (currentTotal - deletedCount).toLocaleString();
        }
    }
    
    // Update trash link count
    const trashLink = document.querySelector('a[href*="ownership-transfers/trash"]');
    if (trashLink) {
        const currentTrashMatch = trashLink.textContent.match(/\((\d+)\)/);
        if (currentTrashMatch) {
            const currentTrash = parseInt(currentTrashMatch[1]);
            const newTrash = currentTrash + deletedCount;
            trashLink.innerHTML = trashLink.innerHTML.replace(/\(\d+\)/, `(${newTrash})`);
        } else {
            trashLink.innerHTML = trashLink.innerHTML.replace('Trash', `Trash (${deletedCount})`);
        }
    }
}

function showEmptyState() {
    const tableContainer = document.querySelector('.overflow-x-auto');
    const paginationDiv = document.querySelector('.flex.flex-col.md\\:flex-row.justify-between');
    const parentDiv = document.querySelector('.card.p-6');
    
    if (tableContainer && parentDiv) {
        tableContainer.style.display = 'none';
        if (paginationDiv) paginationDiv.style.display = 'none';
        
        if (!document.getElementById('emptyState')) {
            const emptyStateHtml = `
                <div class="text-center py-12" id="emptyState">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-exchange-alt text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No ownership transfer requests found</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">All transfers have been moved to trash.</p>
                </div>
            `;
            
            const emptyDiv = document.createElement('div');
            emptyDiv.innerHTML = emptyStateHtml;
            parentDiv.appendChild(emptyDiv);
        }
    }
}

function getCurrentPage() {
    const urlParams = new URLSearchParams(window.location.search);
    return parseInt(urlParams.get('page')) || 1;
}

function getPerPage() {
    const urlParams = new URLSearchParams(window.location.search);
    return parseInt(urlParams.get('per_page')) || 20;
}

function showNotification(type, message) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    const notification = document.createElement('div');
    notification.className = `mb-4 p-4 rounded-lg shadow-lg`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)';
    notification.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : '1px solid rgba(var(--danger-rgb), 0.3)';
    notification.style.animation = 'slideIn 0.3s ease';
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2" style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.success-message, .error-message').forEach(msg => {
            if (msg.style.display !== 'none') msg.style.display = 'none';
        });
    }, 5000);
}

function initTooltips() {
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

.action-btn.assign {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.trash {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.trash:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn:hover {
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
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 50%;
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

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
}

.btn-danger:hover {
    background-color: #c82333 !important;
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

.transfer-checkbox, #selectAll {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.transfer-checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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
@endpush
@endsection