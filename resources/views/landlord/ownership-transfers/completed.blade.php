{{-- landlord/ownership-transfers/completed.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\User;

    // Landlord specific page
    $isLandlord = auth()->user()->isLandlord();
    $layout = 'layouts.landlord';
    $routePrefix = 'landlord.ownership-transfers';
    
    // Create dynamic page title
    $pageTitle = 'Completed Ownership Transfers';
    
    // Check if there are any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $userId = auth()->id();
    
    // Get statistics for completed transfers
    $totalCompleted = $transfers->total();
    
    // Calculate statistics using constants
    $transferredFromMe = PropertyOwnershipTransfer::where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->count();
    $transferredToMe = PropertyOwnershipTransfer::where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->count();
    
    // Get total value of transfers
    $totalValueSent = PropertyOwnershipTransfer::where('current_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->sum('sale_amount');
    $totalValueReceived = PropertyOwnershipTransfer::where('new_landlord_id', $userId)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->sum('sale_amount');
    
    // Get current year statistics
    $currentYear = date('Y');
    $completedThisYear = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where(function($query) use ($userId) {
            $query->where('current_landlord_id', $userId)
                  ->orWhere('new_landlord_id', $userId);
        })
        ->whereYear('completed_at', $currentYear)
        ->count();
    
    // Get average completion time
    $avgCompletionDays = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->where(function($query) use ($userId) {
            $query->where('current_landlord_id', $userId)
                  ->orWhere('new_landlord_id', $userId);
        })
        ->whereNotNull('created_at')
        ->whereNotNull('completed_at')
        ->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(DAY, created_at, completed_at)')) ?? 0;
    
    // Status options (only completed for this page)
    $statuses = [PropertyOwnershipTransfer::STATUS_COMPLETED => 'Completed'];
    
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
                         style="background: linear-gradient(135deg, var(--success) 0%, var(--info) 100%); color: white; font-weight: 600; border-color: var(--success);">
                        <i class="fas fa-check-double text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-check-double mr-2" style="color: var(--success);"></i> 
                        Completed Ownership Transfers
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View all successfully completed property ownership transfers</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalCompleted) }} completed transfer{{ $totalCompleted != 1 ? 's' : '' }}</span>
                        @if($completedThisYear > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar-star mr-1" style="color: var(--warning);"></i>
                        <span>{{ $completedThisYear }} in {{ $currentYear }}</span>
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
                    <a href="{{ route('landlord.ownership-history') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-history mr-1"></i> Full History
                    </a>
                    <a href="{{ route('landlord.ownership-transfers.index') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-exchange-alt mr-1"></i> Active Transfers
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
        <!-- Total Completed Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total Completed</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalCompleted) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-double text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transferred From Me Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Properties I Transferred</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($transferredFromMe) }}</p>
                        @if($totalValueSent > 0)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Total Value: GHS {{ number_format($totalValueSent, 2) }}</p>
                        @endif
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-arrow-right text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transferred To Me Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Properties I Received</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($transferredToMe) }}</p>
                        @if($totalValueReceived > 0)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Total Value: GHS {{ number_format($totalValueReceived, 2) }}</p>
                        @endif
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-arrow-left text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Completion Card -->
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Avg Completion Time</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ round($avgCompletionDays) }} <span class="text-sm font-normal">days</span></p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-hourglass-half text-lg" style="color: var(--secondary);"></i>
                    </div>
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
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Completed Transfers
                    </h3>
                    
                    <form method="GET" action="{{ route('landlord.ownership-transfers.completed') }}" class="space-y-4" id="filterForm">
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
                                       placeholder="Property, owner name, reference..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
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
                                <i class="fas fa-calendar mr-1"></i> Completion Date
                            </label>
                            <div class="space-y-2">
                                <input type="date" 
                                       name="start_date" 
                                       value="{{ request('start_date') }}" 
                                       class="index-custom-input w-full"
                                       placeholder="From Date">
                                <input type="date" 
                                       name="end_date" 
                                       value="{{ request('end_date') }}" 
                                       class="index-custom-input w-full"
                                       placeholder="To Date">
                            </div>
                        </div>

                        <!-- Sort Options -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort mr-1"></i> Sort By
                            </label>
                            <select name="sort" class="index-custom-dropdown w-full">
                                <option value="completed_at_desc" {{ request('sort', 'completed_at_desc') == 'completed_at_desc' ? 'selected' : '' }}>
                                    Recent Completions First
                                </option>
                                <option value="completed_at_asc" {{ request('sort') == 'completed_at_asc' ? 'selected' : '' }}>
                                    Oldest Completions First
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
                                <option value="sale_amount_asc" {{ request('sort') == 'sale_amount_asc' ? 'selected' : '' }}>
                                    Lowest Amount
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
                                
                                <a href="{{ route('landlord.ownership-transfers.completed') }}" 
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
            
            <!-- Completion Insights -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Completion Insights
                    </h3>
                    <div class="space-y-4">
                        @if($totalCompleted > 0)
                            @php
                                $sentPercentage = $totalCompleted > 0 ? round(($transferredFromMe / $totalCompleted) * 100, 1) : 0;
                                $receivedPercentage = $totalCompleted > 0 ? round(($transferredToMe / $totalCompleted) * 100, 1) : 0;
                            @endphp
                            <div>
                                <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-arrow-right-arrow-left mr-1"></i> Sent vs Received
                                </p>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-xs">
                                        <span style="color: var(--text-primary);">Sent ({{ $transferredFromMe }})</span>
                                        <span style="color: var(--text-primary);">{{ $sentPercentage }}%</span>
                                    </div>
                                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full" style="width: {{ $sentPercentage }}%; background-color: var(--primary);"></div>
                                    </div>
                                    
                                    <div class="flex justify-between text-xs mt-2">
                                        <span style="color: var(--text-primary);">Received ({{ $transferredToMe }})</span>
                                        <span style="color: var(--text-primary);">{{ $receivedPercentage }}%</span>
                                    </div>
                                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full" style="width: {{ $receivedPercentage }}%; background-color: var(--info);"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-2 border-t" style="border-color: var(--border-color);">
                                <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-trophy mr-1"></i> Your Transfer Activity
                                </p>
                                <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $transferredFromMe + $transferredToMe }} Total
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $transferredFromMe }} sent • {{ $transferredToMe }} received
                                </p>
                            </div>
                            
                            @if($totalValueSent + $totalValueReceived > 0)
                            <div class="pt-2 border-t" style="border-color: var(--border-color);">
                                <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-chart-line mr-1"></i> Total Value
                                </p>
                                <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                    GHS {{ number_format($totalValueSent + $totalValueReceived, 2) }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Sent: GHS {{ number_format($totalValueSent, 2) }} • Received: GHS {{ number_format($totalValueReceived, 2) }}
                                </p>
                            </div>
                            @endif
                        @else
                            <div class="text-center py-4">
                                <i class="fas fa-chart-pie text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Complete your first transfer to see insights
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Transfers Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <!-- Table Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Completed Transfers
                            @if(request('transfer_type'))
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                    {{ request('transfer_type') == 'sent' ? 'Properties I Transferred' : 'Properties I Received' }}
                                </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} completed transfers
                        </p>
                    </div>
                </div>

                @if($transfers->isEmpty())
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-check-double text-2xl" style="color: var(--success);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No completed transfers found
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['search', 'transfer_type', 'start_date', 'end_date']))
                                No completed transfers match your search criteria.
                            @else
                                You don't have any successfully completed property transfers yet.
                            @endif
                        </p>
                        @if(!request()->hasAny(['search', 'transfer_type', 'start_date', 'end_date']))
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
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Completion Date</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Amount</th>
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
                                            @if($transfer->processing_time_days)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-hourglass-half mr-1"></i> {{ $transfer->processing_time_days }} days
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
                                                {{ $transfer->completed_at ? $transfer->completed_at->format('M j, Y') : ($transfer->updated_at ? $transfer->updated_at->format('M j, Y') : 'N/A') }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $transfer->completed_at ? $transfer->completed_at->diffForHumans() : ($transfer->updated_at ? $transfer->updated_at->diffForHumans() : 'N/A') }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar-alt mr-1"></i> Transfer: {{ $transfer->transfer_date ? $transfer->transfer_date->format('M j, Y') : 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            @if($transfer->sale_amount)
                                                <div class="font-medium" style="color: var(--primary);">
                                                    GHS {{ number_format($transfer->sale_amount, 2) }}
                                                </div>
                                            @else
                                                <div class="text-sm" style="color: var(--text-secondary);">N/A</div>
                                            @endif
                                            @if($isSent && $transfer->sale_amount)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Sold for {{ $transfer->formatted_sale_amount }}
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
                                                
                                                @if($transfer->certificate_url)
                                                    <a href="{{ route($certificateRoute, [$transfer->property_id, $transfer->id]) }}" 
                                                       class="action-btn assign" 
                                                       data-tooltip="Download Certificate">
                                                        <i class="fas fa-certificate"></i>
                                                    </a>
                                                @endif
                                            </div>
                                         </td>
                                    </table>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} completed transfers
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

function updateSort(value) {
    const [sort, direction] = value.split('_');
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    url.searchParams.set('direction', direction);
    window.location.href = url.toString();
}

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