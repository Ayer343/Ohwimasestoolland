{{-- landlord/ownership-transfers/history.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\User;

    // Landlord specific page
    $isLandlord = auth()->user()->isLandlord();
    $layout = 'layouts.landlord';
    $routePrefix = 'landlord.ownership-transfers';
    $userId = auth()->id();
    
    // Create dynamic page title
    $pageTitle = 'Ownership Transfer History';
    
    // Check if there are any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Get statistics for history (completed, rejected, cancelled)
    $totalHistory = $transfers->total();
    $completedCount = PropertyOwnershipTransfer::where(function($query) use ($userId) {
            $query->where('current_landlord_id', $userId)
                  ->orWhere('new_landlord_id', $userId);
        })
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->count();
    $rejectedCount = PropertyOwnershipTransfer::where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
        ->count();
    $cancelledCount = PropertyOwnershipTransfer::where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)
        ->count();
    
    // Get total value of completed transfers
    $totalValueCompleted = PropertyOwnershipTransfer::where(function($query) use ($userId) {
            $query->where('current_landlord_id', $userId)
                  ->orWhere('new_landlord_id', $userId);
        })
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->sum('sale_amount');
    
    // Calculate success rate
    $totalProcessed = $completedCount + $rejectedCount + $cancelledCount;
    $successRate = $totalProcessed > 0 ? round(($completedCount / $totalProcessed) * 100, 1) : 0;
    
    // Get average completion time for completed transfers
    $avgCompletionDays = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where(function($query) use ($userId) {
            $query->where('current_landlord_id', $userId)
                  ->orWhere('new_landlord_id', $userId);
        })
        ->whereNotNull('created_at')
        ->whereNotNull('completed_at')
        ->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(DAY, created_at, completed_at)')) ?? 0;
    
    // Status options for filter
    $statuses = [
        PropertyOwnershipTransfer::STATUS_COMPLETED => 'Completed',
        PropertyOwnershipTransfer::STATUS_REJECTED => 'Rejected',
        PropertyOwnershipTransfer::STATUS_CANCELLED => 'Cancelled',
    ];
    
    // Get current filter
    $currentStatus = request('status', PropertyOwnershipTransfer::STATUS_COMPLETED);
    
    // Build dynamic download routes based on user role
    $isAdmin = auth()->user()->isAdmin() || auth()->user()->isSuperAdmin();
    $downloadRoute = $isAdmin 
        ? 'admin.ownership-transfers.download-document'
        : 'properties.ownership-transfers.download';
    $certificateRoute = $isAdmin 
        ? 'admin.ownership-transfers.download-certificate'
        : 'properties.ownership-transfers.certificate';
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> 
                        Ownership Transfer History
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View your completed and historical transfer requests</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalHistory) }} historical transfer{{ $totalHistory != 1 ? 's' : '' }}</span>
                        @if($completedCount > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        <span>{{ number_format($completedCount) }} completed</span>
                        @endif
                        @if($avgCompletionDays > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-hourglass-half mr-1" style="color: var(--info);"></i>
                        <span>Avg {{ round($avgCompletionDays) }} days to complete</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex space-x-2 mt-2">
                    <a href="{{ route('landlord.ownership-transfers.index') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-exchange-alt mr-1"></i> Active Transfers
                    </a>
                    <a href="{{ route('landlord.ownership-transfers.completed') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-check-double mr-1"></i> Completed
                    </a>
                </div>
            </div>
        </div>
    </div>

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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Completed Transfers Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Successfully Completed</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($completedCount) }}</p>
                        @if($totalValueCompleted > 0)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Total: GHS {{ number_format($totalValueCompleted, 2) }}</p>
                        @endif
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-double text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rejected Transfers Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Rejected Requests</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($rejectedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-times-circle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cancelled Transfers Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Cancelled Requests</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($cancelledCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-ban text-lg" style="color: var(--secondary);"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Rate Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Success Rate</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $successRate }}%</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $completedCount }}/{{ $totalProcessed }} transfers</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-chart-line text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
                <div class="mt-2 w-full h-1 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                    <div class="h-full rounded-full" style="width: {{ $successRate }}%; background-color: var(--success);"></div>
                </div>
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
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> History Filters
                    </h3>
                    
                    <form method="GET" action="{{ route('landlord.ownership-history') }}" class="space-y-4" id="filterForm">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ request('search') }}" 
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Property, owner, reference..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Status Filter -->
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

                        <!-- Transfer Type -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-exchange-alt mr-1"></i> Transfer Type
                            </label>
                            <select name="transfer_type" class="index-custom-dropdown w-full">
                                <option value="">All Types</option>
                                <option value="sent" {{ request('transfer_type') == 'sent' ? 'selected' : '' }}>
                                    <i class="fas fa-arrow-right mr-1"></i> Properties I Transferred
                                </option>
                                <option value="received" {{ request('transfer_type') == 'received' ? 'selected' : '' }}>
                                    <i class="fas fa-arrow-left mr-1"></i> Properties I Received
                                </option>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Date Range
                            </label>
                            <div class="space-y-2">
                                <input type="date" 
                                       name="start_date" 
                                       value="{{ request('start_date') }}" 
                                       class="index-custom-input w-full"
                                       placeholder="Start Date">
                                <input type="date" 
                                       name="end_date" 
                                       value="{{ request('end_date') }}" 
                                       class="index-custom-input w-full"
                                       placeholder="End Date">
                            </div>
                        </div>

                        <!-- Sort Options -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort mr-1"></i> Sort By
                            </label>
                            <select name="sort" class="index-custom-dropdown w-full">
                                <option value="updated_at_desc" {{ request('sort', 'updated_at_desc') == 'updated_at_desc' ? 'selected' : '' }}>
                                    Recent First
                                </option>
                                <option value="updated_at_asc" {{ request('sort') == 'updated_at_asc' ? 'selected' : '' }}>
                                    Oldest First
                                </option>
                                <option value="transfer_date_desc" {{ request('sort') == 'transfer_date_desc' ? 'selected' : '' }}>
                                    Transfer Date (Newest)
                                </option>
                                <option value="transfer_date_asc" {{ request('sort') == 'transfer_date_asc' ? 'selected' : '' }}>
                                    Transfer Date (Oldest)
                                </option>
                                <option value="sale_amount_desc" {{ request('sort') == 'sale_amount_desc' ? 'selected' : '' }}>
                                    Highest Amount
                                </option>
                            </select>
                        </div>

                        <!-- Per Page -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-list mr-1"></i> Items Per Page
                            </label>
                            <select name="per_page" class="index-custom-dropdown w-full" onchange="this.form.submit()">
                                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10 per page</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 per page</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                            </select>
                        </div>

                        <!-- Quick Actions -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" 
                                        class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route('landlord.ownership-history') }}" 
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <a href="{{ route('landlord.ownership-transfers.export', ['format' => 'excel'] + request()->all()) }}" 
                                   class="block w-full text-center btn-modern px-3 py-2 rounded-lg font-medium text-white"
                                   style="background: linear-gradient(135deg, var(--success) 0%, var(--info) 100%);">
                                    <i class="fas fa-file-excel mr-2"></i> Export to Excel
                                </a>
                                
                                <a href="{{ route('landlord.ownership-transfers.export', ['format' => 'csv'] + request()->all()) }}" 
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-file-csv mr-2"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- History Insights -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i> History Insights
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-percentage mr-1"></i> Success Rate
                            </p>
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                    <div class="bg-success h-2 rounded-full" style="width: {{ $successRate }}%"></div>
                                </div>
                                <span class="ml-2 text-sm font-medium" style="color: var(--text-primary);">{{ $successRate }}%</span>
                            </div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i> Average Processing Time
                            </p>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">{{ round($avgCompletionDays) }} days</p>
                            <p class="text-xs" style="color: var(--text-secondary);">From request to completion</p>
                        </div>
                        
                        <div class="pt-2 border-t" style="border-color: var(--border-color);">
                            <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-chart-pie mr-1"></i> Status Distribution
                            </p>
                            <div class="space-y-2 mt-2">
                                <div class="flex justify-between text-xs">
                                    <span style="color: var(--success);">Completed</span>
                                    <span>{{ $completedCount }} ({{ $totalProcessed > 0 ? round($completedCount / $totalProcessed * 100, 1) : 0 }}%)</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span style="color: var(--danger);">Rejected</span>
                                    <span>{{ $rejectedCount }} ({{ $totalProcessed > 0 ? round($rejectedCount / $totalProcessed * 100, 1) : 0 }}%)</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span style="color: var(--secondary);">Cancelled</span>
                                    <span>{{ $cancelledCount }} ({{ $totalProcessed > 0 ? round($cancelledCount / $totalProcessed * 100, 1) : 0 }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <!-- Table Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Transfer History
                            @if($currentStatus)
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                    {{ $statuses[$currentStatus] ?? ucfirst($currentStatus) }}
                                </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} historical records
                        </p>
                    </div>
                </div>

                @if($transfers->isEmpty())
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-history text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No transfer history found
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['search', 'status', 'transfer_type', 'start_date', 'end_date']))
                                No historical transfer records match your search criteria.
                            @else
                                You don't have any completed or historical transfer records yet.
                            @endif
                        </p>
                        @if(!request()->hasAny(['search', 'status', 'transfer_type', 'start_date', 'end_date']))
                        <div class="space-x-3">
                            <a href="{{ route('landlord.ownership-transfers.index') }}" 
                               class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-exchange-alt mr-2"></i> View Active Transfers
                            </a>
                            <a href="{{ route('properties.my-properties') }}" 
                               class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-building mr-2"></i> My Properties
                            </a>
                        </div>
                        @endif
                    </div>
                @else
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property / Reference</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Transfer Type</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Other Party</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Transfer Date</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status / Outcome</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Processed On</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transfers as $transfer)
                                    @php
                                        $isSent = $transfer->current_landlord_id == $userId;
                                        $isReceived = $transfer->new_landlord_id == $userId;
                                        $transferType = $isSent ? 'sent' : 'received';
                                        $otherParty = $isSent ? $transfer->newLandlord : $transfer->currentLandlord;
                                        $typeColor = $isSent ? 'warning' : 'info';
                                        $typeIcon = $isSent ? 'arrow-right' : 'arrow-left';
                                        $typeLabel = $isSent ? 'You Transferred' : 'You Received';
                                        $hasDigitalSignature = isset($transfer->metadata['digital_signature']);
                                        $signatureVerified = $transfer->digital_signature_verified;
                                        $isBulk = $transfer->is_bulk_transfer;
                                        $processingDays = $transfer->processing_time_days;
                                        
                                        // Determine status config
                                        $statusConfigs = [
                                            PropertyOwnershipTransfer::STATUS_COMPLETED => ['bg' => 'success', 'icon' => 'check-double', 'class' => 'badge-success'],
                                            PropertyOwnershipTransfer::STATUS_REJECTED => ['bg' => 'danger', 'icon' => 'times-circle', 'class' => 'badge-danger'],
                                            PropertyOwnershipTransfer::STATUS_CANCELLED => ['bg' => 'secondary', 'icon' => 'ban', 'class' => 'badge-secondary'],
                                        ];
                                        $statusConfig = $statusConfigs[$transfer->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle', 'class' => 'badge-secondary'];
                                    @endphp
                                    <tr>
                                        <td class="p-3">
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    <a href="{{ route('properties.show', $transfer->property_id) }}" 
                                                       class="hover:text-primary transition-colors">
                                                        {{ $transfer->property->property_name ?? 'N/A' }}
                                                    </a>
                                                    @if($isBulk)
                                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-layer-group mr-1"></i> Bulk
                                                    </span>
                                                    @endif
                                                    @if($hasDigitalSignature && $signatureVerified)
                                                    <span class="ml-1 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    {{ $transfer->document_reference }}
                                                </div>
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    {{ $transfer->property->registration_pattern ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-{{ $typeColor }}">
                                                <i class="fas fa-{{ $typeIcon }} mr-1"></i>
                                                {{ $typeLabel }}
                                            </span>
                                            @if($transfer->sale_amount)
                                            <div class="text-xs mt-1" style="color: var(--primary);">
                                                GHS {{ number_format($transfer->sale_amount, 2) }}
                                            </div>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 mr-2">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                                        <i class="fas fa-user-tie text-xs" style="color: var(--info);"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                        {{ $otherParty->name ?? ($isSent ? $transfer->new_owner_name : 'N/A') }}
                                                    </div>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        {{ $otherParty->email ?? ($isSent ? $transfer->new_owner_email : 'N/A') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <div class="text-sm" style="color: var(--text-primary);">
                                                {{ $transfer->transfer_date ? $transfer->transfer_date->format('M j, Y') : 'N/A' }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Requested: {{ $transfer->created_at ? $transfer->created_at->diffForHumans() : 'N/A' }}
                                            </div>
                                            @if($processingDays)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-hourglass-half mr-1"></i> {{ $processingDays }} days processing
                                            </div>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statusConfig['class'] }}">
                                                <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                                {{ $statuses[$transfer->status] ?? ucfirst($transfer->status) }}
                                            </span>
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->rejection_reason)
                                            <div class="text-xs mt-1 max-w-[200px]" style="color: var(--text-secondary);">
                                                <i class="fas fa-comment mr-1"></i> {{ Str::limit($transfer->rejection_reason, 50) }}
                                            </div>
                                            @endif
                                            @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->can_resubmit)
                                            <div class="text-xs mt-1" style="color: var(--success);">
                                                <i class="fas fa-redo mr-1"></i> Eligible for resubmission
                                            </div>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <div class="text-sm" style="color: var(--text-primary);">
                                                {{ $transfer->updated_at ? $transfer->updated_at->format('M j, Y') : 'N/A' }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $transfer->updated_at ? $transfer->updated_at->diffForHumans() : 'N/A' }}
                                            </div>
                                            @if($transfer->completed_at)
                                            <div class="text-xs mt-1" style="color: var(--success);">
                                                <i class="fas fa-check-circle mr-1"></i> Completed: {{ $transfer->completed_at->format('M j, Y') }}
                                            </div>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('properties.ownership-transfers.show', [$transfer->property_id, $transfer->id]) }}" 
                                                   class="action-btn view" 
                                                   data-tooltip="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                @if($transfer->document_url)
                                                    <a href="{{ route($downloadRoute, [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn edit" 
                                                       data-tooltip="Download Document">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                @endif
                                                
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED && $transfer->certificate_url)
                                                    <a href="{{ route($certificateRoute, [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn assign" 
                                                       data-tooltip="Download Certificate">
                                                        <i class="fas fa-certificate"></i>
                                                    </a>
                                                @endif
                                                
                                                @if($transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->can_resubmit && $isSent)
                                                    <a href="{{ route('properties.ownership-transfers.resubmit', [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn" 
                                                       data-tooltip="Resubmit Request"
                                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-redo"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} historical records
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
});

function autoHideMessages() {
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.success-message');
        successMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
        
        const errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
    }, 5000);
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
/* Reuse styles from other blades */
.index-custom-input,
.index-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus,
.index-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
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

/* Badge styles */
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

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
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

.btn-modern {
    transition: all 0.2s ease;
}

.btn-modern:hover {
    transform: translateY(-1px);
    filter: brightness(105%);
}

/* Table styles */
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

/* Tooltip */
.tooltip {
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .action-btn {
        padding: 0.25rem 0.5rem;
    }
}
</style>
@endsection