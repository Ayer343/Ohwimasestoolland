{{-- admin/ownership-transfers/archive.blade.php --}}
@php
    // Only admins can access this page
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $layout = 'layouts.app';
    
    // Create dynamic page title
    $pageTitle = 'Archived Ownership Transfers';
    
    // Check if there are any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $bulkSummary = session('bulk_summary');
    $forceRefresh = session('force_refresh', false);
    
    // If force refresh is set, clear the session flag
    if ($forceRefresh) {
        session()->forget('force_refresh');
    }
    
    // Get statistics from the passed data
    $totalArchived = $stats['total_archived'] ?? 0;
    $regularArchived = $stats['regular_count'] ?? ($stats['regular_transfers_archived'] ?? 0);
    $reversalArchived = $stats['reversal_count'] ?? ($stats['reversal_records_archived'] ?? 0);
    $byYear = $stats['by_year'] ?? collect();
    $byStatus = $stats['by_status'] ?? collect();
    $totalValue = $stats['total_value'] ?? 0;
    $oldestArchive = $stats['oldest_archive'] ?? null;
    $newestArchive = $stats['newest_archive'] ?? null;
    
    // Convert to collection if array
    if (is_array($byYear)) {
        $byYear = collect($byYear);
    }
    if (is_array($byStatus)) {
        $byStatus = collect($byStatus);
    }
    
    // Calculate max count for progress bars safely
    $maxYearCount = 0;
    if ($byYear && $byYear->count() > 0) {
        foreach ($byYear as $yearData) {
            $count = is_array($yearData) ? ($yearData['count'] ?? 0) : ($yearData->count ?? 0);
            if ($count > $maxYearCount) {
                $maxYearCount = $count;
            }
        }
    }
    $maxYearCount = $maxYearCount > 0 ? $maxYearCount : 1;
    
    // Get first 10 years for display
    $displayYears = $byYear->take(10);
    
    // Get current filter
    $currentYear = request('year');
    $currentStatus = request('status');
    $currentType = request('type', 'all');
    $currentSearch = request('search');
    
    // Status labels
    $statuses = \App\Models\PropertyOwnershipTransfer::getStatuses();
    
    // Type options
    $typeOptions = [
        'all' => 'All Records',
        'regular' => 'Regular Transfers Only',
        'reversal' => 'Reversal Records Only',
    ];
    
    // Available years from controller
    $availableYears = $availableYears ?? [];
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--info) 0%, var(--primary) 100%); color: white; font-weight: 600; border-color: var(--info);">
                        <i class="fas fa-archive text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-archive mr-2" style="color: var(--info);"></i> 
                        Archived Ownership Transfer Requests
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Historical records of completed, rejected, and cancelled transfers</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-database mr-1"></i>
                        <span id="totalArchivedCount">{{ number_format($totalArchived) }}</span> archived record{{ $totalArchived != 1 ? 's' : '' }} total
                        <span class="mx-1">•</span>
                        <i class="fas fa-exchange-alt mr-1" style="color: var(--success);"></i>
                        <span>{{ number_format($regularArchived) }} regular transfers</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-undo-alt mr-1" style="color: var(--warning);"></i>
                        <span>{{ number_format($reversalArchived) }} reversal records</span>
                        @if($totalValue > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-money-bill-wave mr-1" style="color: var(--success);"></i>
                        <span>₵{{ number_format($totalValue, 2) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar mr-1"></i> 
                    {{ $oldestArchive ? $oldestArchive : 'N/A' }} - {{ $newestArchive ? $newestArchive : 'N/A' }}
                </div>
                <a href="{{ route('admin.ownership-transfers.trash') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-1"></i> View Trash
                </a>
                <a href="{{ route('admin.ownership-transfers.reversal-trash-dashboard') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-1"></i> Reversal Trash Stats
                </a>
                <a href="{{ route('admin.ownership-transfers.index') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-exchange-alt mr-1"></i> Active Transfers
                </a>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Success Messages -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert" id="successMessage">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Messages -->
    @if($errorMessage)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert" id="errorMessage">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-archive text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Archived</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalArchived) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-exchange-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Regular Transfers</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($regularArchived) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-undo-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Reversal Records</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($reversalArchived) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Completed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($byStatus->where('status', 'completed')->first()?->count ?? 0) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-times-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Rejected</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($byStatus->where('status', 'rejected')->first()?->count ?? 0) }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-chart-line text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Value</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">₵{{ number_format($totalValue, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>Archive Information:</strong> This section contains historical transfer records that have been archived for performance optimization.
                    Archived records are read-only but can be restored if needed. Reversal records are marked with a special badge.
                </p>
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
                        <i class="fas fa-filter mr-2" style="color: var(--info);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route('admin.ownership-transfers.archive') }}" class="space-y-4" id="filterForm">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-1"></i> Archive Year
                            </label>
                            <select name="year" class="index-custom-dropdown w-full">
                                <option value="">All Years</option>
                                @foreach($availableYears as $year)
                                    <option value="{{ $year }}" {{ $currentYear == $year ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-layer-group mr-1"></i> Record Type
                            </label>
                            <select name="type" class="index-custom-dropdown w-full">
                                @foreach($typeOptions as $value => $label)
                                    <option value="{{ $value }}" {{ $currentType == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status at Archive
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ $currentStatus == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ $currentSearch }}" 
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Document ref, owner name..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
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
                                
                                <a href="{{ route('admin.ownership-transfers.archive') }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <a href="{{ route('admin.ownership-transfers.archive.export', ['csv']) . '?' . http_build_query(request()->all()) }}" 
                                   class="block w-full text-center btn-info px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-download mr-2"></i> Export Archive
                                </a>
                                
                                <button type="button" onclick="showArchiveStats()" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-chart-bar mr-2"></i> View Statistics
                                </button>
                                
                                @if($totalArchived > 0)
                                <button type="button" onclick="showEmptyArchiveModal()" class="block w-full text-center btn-danger px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-trash-alt mr-2"></i> Empty Archive
                                </button>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Archive Summary by Year -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--info);"></i> Archive by Year
                    </h3>
                    <div class="space-y-3">
                        @if($displayYears && $displayYears->count() > 0)
                            @foreach($displayYears as $yearData)
                                @php
                                    $yearName = is_array($yearData) ? ($yearData['archive_year'] ?? 'N/A') : ($yearData->archive_year ?? 'N/A');
                                    $yearCount = is_array($yearData) ? ($yearData['count'] ?? 0) : ($yearData->count ?? 0);
                                    $percentage = ($yearCount / $maxYearCount) * 100;
                                    $width = min(100, $percentage);
                                @endphp
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span style="color: var(--text-primary);">{{ $yearName }}</span>
                                        <span style="color: var(--text-primary);">{{ number_format($yearCount) }} records</span>
                                    </div>
                                    <div class="w-full rounded-full h-2" style="background-color: rgba(var(--info-rgb), 0.2);">
                                        <div class="rounded-full h-2" style="width: {{ $width }}%; background-color: var(--info);"></div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-4">
                                <p class="text-sm" style="color: var(--text-secondary);">No archive data available</p>
                            </div>
                        @endif
                    </div>
                    
                    @if($byYear && $byYear->count() > 10)
                    <div class="mt-3 text-center">
                        <a href="#" onclick="showAllYears()" class="text-xs" style="color: var(--info);">Show all {{ $byYear->count() }} years...</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Archives Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Archived Transfer Records (<span id="totalEntries">{{ $archives->total() }}</span>)
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);" id="paginationInfo">
                            Showing <span id="firstItem">{{ $archives->firstItem() }}</span> to <span id="lastItem">{{ $archives->lastItem() }}</span> of <span id="totalItems">{{ $archives->total() }}</span> entries
                            @if($currentYear)
                                <span class="ml-2 px-2 py-1 text-xs rounded-full badge-info">
                                    Year: {{ $currentYear }}
                                </span>
                            @endif
                            @if($currentStatus)
                                <span class="ml-2 px-2 py-1 text-xs rounded-full badge-info">
                                    Status: {{ $statuses[$currentStatus] ?? ucfirst($currentStatus) }}
                                </span>
                            @endif
                            @if($currentType && $currentType !== 'all')
                                <span class="ml-2 px-2 py-1 text-xs rounded-full badge-info">
                                    Type: {{ $typeOptions[$currentType] ?? ucfirst($currentType) }}
                                </span>
                            @endif
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        @if($archives->count() > 0)
                        <div class="relative">
                            <button type="button" id="bulkActionsBtn" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-check-double mr-2"></i> Bulk Actions
                                <i class="fas fa-chevron-down ml-2 text-xs"></i>
                            </button>
                            
                            <div id="bulkActionsDropdown" class="absolute right-0 mt-2 w-56 rounded-lg shadow-lg z-10 hidden"
                                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                <div class="py-1">
                                    <button type="button" onclick="showBulkRestoreModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-trash-restore text-green-500 mr-2"></i> Bulk Restore
                                    </button>
                                    <button type="button" onclick="showBulkDeleteModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-trash-alt text-red-500 mr-2"></i> Bulk Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <a href="{{ route('admin.ownership-transfers.archive.export', ['csv']) . '?' . http_build_query(request()->all()) }}" 
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Export
                        </a>
                    </div>
                </div>

                @if($archives->isEmpty())
                    <div class="text-center py-12" id="emptyState">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-archive text-2xl" style="color: var(--info);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No archived transfer records found
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['search', 'year', 'status', 'type']))
                                No archived transfers match your search criteria.
                            @else
                                The archive is empty. Transfers will appear here after yearly archiving or permanent deletion from trash.
                            @endif
                        </p>
                        @if(!request()->hasAny(['search', 'year', 'status', 'type']))
                        <div class="flex justify-center space-x-3">
                            <a href="{{ route('admin.ownership-transfers.trash') }}" class="btn-warning px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-trash-alt mr-2"></i> View Trash
                            </a>
                            <a href="{{ route('admin.ownership-transfers.reversal-trash-dashboard') }}" class="btn-warning px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-undo-alt mr-2"></i> Reversal Trash Stats
                            </a>
                            <a href="{{ route('admin.ownership-transfers.index') }}" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-exchange-alt mr-2"></i> View Active Transfers
                            </a>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full" id="archivesTable">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded border-gray-300" style="width: 18px; height: 18px;">
                                    </th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Year</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property / Reference</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">From → To</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Transfer Date</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Amount</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Archived At</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="archivesTableBody">
                                @foreach($archives as $archive)
                                @php
                                    $isReversalRecord = $archive->isReversalRecord();
                                    $statusColors = [
                                        'pending' => ['bg' => 'warning', 'icon' => 'clock'],
                                        'approved' => ['bg' => 'success', 'icon' => 'check-circle'],
                                        'rejected' => ['bg' => 'danger', 'icon' => 'times-circle'],
                                        'completed' => ['bg' => 'info', 'icon' => 'check-double'],
                                        'cancelled' => ['bg' => 'secondary', 'icon' => 'ban'],
                                    ];
                                    $statusConfig = $statusColors[$archive->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                                @endphp
                                <tr data-archive-id="{{ $archive->id }}" 
                                    data-status="{{ $archive->status }}"
                                    data-year="{{ $archive->archive_year }}"
                                    data-property-name="{{ $archive->property->property_name ?? 'N/A' }}"
                                    data-is-reversal="{{ $isReversalRecord ? 'true' : 'false' }}">
                                    <td class="p-3 text-center">
                                        <input type="checkbox" class="archive-checkbox" value="{{ $archive->id }}" onclick="updateBulkActions()">
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium badge-info">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            {{ $archive->archive_year }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-3">
                                                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                                     style="background-color: rgba(var(--info-rgb), 0.1);">
                                                    <i class="fas {{ $isReversalRecord ? 'fa-undo-alt' : 'fa-building' }}" style="color: var(--info);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium property-name" style="color: var(--text-primary);">
                                                    {{ $archive->property->property_name ?? 'N/A' }}
                                                    @if($isReversalRecord)
                                                    <span class="ml-2 text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-undo-alt mr-0.5 text-xs"></i> Reversal
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="text-xs mt-1 font-mono" style="color: var(--text-secondary);">
                                                    <i class="fas fa-hashtag mr-1 text-xs"></i> Ref: {{ $archive->document_reference }}
                                                </div>
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-file-alt mr-1 text-xs"></i> {{ $archive->document_type_label }}
                                                </div>
                                                @if($archive->original_transfer_id)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-link mr-1 text-xs"></i> Original ID: #{{ $archive->original_transfer_id }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex flex-col">
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-1"
                                                     style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                                    <i class="fas fa-user-tie text-xs" style="color: var(--secondary);"></i>
                                                </div>
                                                <span class="text-sm" style="color: var(--text-primary);">{{ $archive->currentLandlord->name ?? 'N/A' }}</span>
                                            </div>
                                            <div class="flex items-center my-1">
                                                <i class="fas fa-arrow-down text-xs mx-2" style="color: var(--text-secondary);"></i>
                                            </div>
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-1"
                                                     style="background-color: rgba(var(--info-rgb), 0.1);">
                                                    <i class="fas fa-user-plus text-xs" style="color: var(--info);"></i>
                                                </div>
                                                <span class="text-sm" style="color: var(--text-primary);">{{ $archive->new_owner_name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $archive->transfer_date ? $archive->transfer_date->format('M j, Y') : 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i> Requested: {{ $archive->created_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $archive->formatted_sale_amount }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-{{ $statusConfig['bg'] }}">
                                            <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                            {{ $archive->status_label }}
                                        </span>
                                        @if($isReversalRecord && $archive->reversal_status)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Reversal Status: {{ ucfirst($archive->reversal_status) }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $archive->archived_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ $archive->archived_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center space-x-2">
                                            <button type="button" 
                                                    onclick="viewArchiveDetails('{{ $archive->id }}')"
                                                    class="action-btn view" 
                                                    data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <button type="button" 
                                                    onclick="restoreSingleArchive('{{ $archive->id }}', {{ $isReversalRecord ? 'true' : 'false' }})"
                                                    class="action-btn restore" 
                                                    data-tooltip="{{ $isReversalRecord ? 'Restore Reversal Record' : 'Restore to Active' }}">
                                                <i class="fas fa-trash-restore"></i>
                                            </button>
                                            
                                            <button type="button" 
                                                    onclick="deleteSingleArchive('{{ $archive->id }}', {{ $isReversalRecord ? 'true' : 'false' }})"
                                                    class="action-btn delete" 
                                                    data-tooltip="{{ $isReversalRecord ? 'Permanently Delete Reversal Record' : 'Permanently Delete Archive' }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            
                                            @if($archive->certificate_url)
                                            <a href="{{ route('admin.ownership-transfers.archive.certificate', $archive->id) }}" 
                                               class="action-btn download" 
                                               data-tooltip="Download Certificate">
                                                <i class="fas fa-file-pdf"></i>
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
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);" id="paginationText">
                            Showing {{ $archives->firstItem() }} to {{ $archives->lastItem() }} of {{ $archives->total() }} entries
                        </div>
                        <div class="pagination" id="paginationLinks">
                            {{ $archives->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Archive Details Modal -->
<div id="archiveDetailsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideArchiveDetailsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-archive mr-2" style="color: var(--info);"></i> Archive Details
                </h3>
                <button type="button" onclick="hideArchiveDetailsModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body" id="archiveDetailsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--info);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideArchiveDetailsModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Restore Modal -->
<div id="bulkRestoreModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkRestoreModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-restore mr-2" style="color: var(--success);"></i> Bulk Restore from Archive
                </h3>
                <button type="button" onclick="hideBulkRestoreModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="bulkSelectedArchivesList" class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">Selected archives:</p>
                    <div id="bulkSelectedArchives" class="max-h-40 overflow-y-auto space-y-1"></div>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-green-500 mr-2"></i>
                        <p class="text-sm text-green-700">
                            This will restore all selected archived transfers back to the active transfers table.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="bulkRestoreArchiveIdsContainer"></div>
                <button type="button" onclick="hideBulkRestoreModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="submitBulkRestore()" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-restore mr-2"></i> Restore Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Bulk Delete from Archive
                </h3>
                <button type="button" onclick="hideBulkDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="bulkDeleteSelectedList" class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">Selected archives:</p>
                    <div id="bulkDeleteSelected" class="max-h-40 overflow-y-auto space-y-1"></div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Deletion Reason (Optional)
                    </label>
                    <textarea id="bulkDeleteReason" rows="3" class="index-custom-textarea w-full" placeholder="Why are these being permanently deleted?"></textarea>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                        <p class="text-sm text-red-700">
                            <strong>Warning:</strong> This action is irreversible. All selected archived records and their associated documents will be permanently deleted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="bulkDeleteArchiveIdsContainer"></div>
                <button type="button" onclick="hideBulkDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="submitBulkDelete()" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Empty Archive Modal -->
<div id="emptyArchiveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideEmptyArchiveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Empty Archive
                </h3>
                <button type="button" onclick="hideEmptyArchiveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="emptyArchivePreview" class="mb-4">
                    <div class="rounded-lg p-4 mb-4" 
                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                            <p style="color: var(--warning);">
                                <strong>Warning:</strong> This will permanently delete <span id="emptyArchiveCount">{{ number_format($totalArchived) }}</span> archived record(s) from the system.
                            </p>
                        </div>
                    </div>
                    
                    <div class="rounded-lg p-4 mb-4" 
                         style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-chart-pie mr-2" style="color: var(--info);"></i> Archive Summary:
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center py-1">
                                <span style="color: var(--text-secondary);">Total Records:</span>
                                <span class="font-medium" style="color: var(--text-primary);">{{ number_format($totalArchived) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span style="color: var(--text-secondary);">Regular Transfers:</span>
                                <span class="font-medium" style="color: var(--text-primary);">{{ number_format($regularArchived) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span style="color: var(--text-secondary);">Reversal Records:</span>
                                <span class="font-medium" style="color: var(--warning);">{{ number_format($reversalArchived) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-t" style="border-color: var(--border-color);">
                                <span style="color: var(--text-secondary);">Total Value:</span>
                                <span class="font-medium" style="color: var(--success);">₵{{ number_format($totalValue, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-t" style="border-color: var(--border-color);">
                                <span style="color: var(--text-secondary);">Years Range:</span>
                                <span class="font-medium" style="color: var(--text-primary);">{{ $oldestArchive ? $oldestArchive : 'N/A' }} - {{ $newestArchive ? $newestArchive : 'N/A' }}</span>
                            </div>
                            @if($byYear && $byYear->count() > 0)
                            <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                <div class="text-xs" style="color: var(--text-secondary);">Breakdown by year:</div>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($byYear->take(5) as $yearData)
                                        @php $yearName = is_array($yearData) ? ($yearData['archive_year'] ?? 'N/A') : ($yearData->archive_year ?? 'N/A'); @endphp
                                        <span class="inline-block px-2 py-0.5 rounded text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            {{ $yearName }}
                                        </span>
                                    @endforeach
                                    @if($byYear->count() > 5)
                                        <span class="inline-block px-2 py-0.5 rounded text-xs" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                            +{{ $byYear->count() - 5 }} more
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Type <span class="text-danger font-bold">"DELETE_ARCHIVE"</span> to confirm
                    </label>
                    <input type="text" 
                           id="emptyArchiveConfirmation" 
                           class="index-custom-input w-full" 
                           placeholder="DELETE_ARCHIVE"
                           autocomplete="off">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                        This action cannot be undone. Please type DELETE_ARCHIVE to confirm.
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Deletion Reason (Optional)
                    </label>
                    <textarea id="emptyArchiveDeletionReason" rows="2" class="index-custom-textarea" placeholder="Why are you emptying the archive?"></textarea>
                </div>
                
                <div class="rounded-lg p-4" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                        <p class="text-sm" style="color: var(--danger);">
                            <strong>Warning:</strong> This will permanently delete ALL archived transfer records. This action cannot be undone. Associated document files will also be deleted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideEmptyArchiveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="submitEmptyArchive()" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Archive Statistics Modal -->
<div id="archiveStatsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideArchiveStatsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--info);"></i> Archive Statistics
                </h3>
                <button type="button" onclick="hideArchiveStatsModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body" id="archiveStatsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--info);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading statistics...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideArchiveStatsModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
                <a href="{{ route('admin.ownership-transfers.archive.export') }}" class="btn-info px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-download mr-2"></i> Export Report
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let selectedArchiveIds = [];

document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    
    // Handle bulk actions dropdown toggle
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
});

// ============================================
// VIEW ARCHIVE DETAILS
// ============================================
function viewArchiveDetails(archiveId) {
    const modal = document.getElementById('archiveDetailsModal');
    const content = document.getElementById('archiveDetailsContent');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--info);"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
        </div>
    `;
    
    fetch(`/admin/ownership-transfers/archive/preview/${archiveId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const archive = data.archive;
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Archive ID</label>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">#${archive.id}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Archive Year</label>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">${archive.archive_year}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Document Reference</label>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">${escapeHtml(archive.document_reference)}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Status</label>
                            <p><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-${archive.status === 'completed' ? 'success' : (archive.status === 'rejected' ? 'danger' : 'warning')}">${archive.status_label}</span></p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Property Details</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Property Name</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(archive.property?.property_name || 'N/A')}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Registration Pattern</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(archive.property?.registration_pattern || 'N/A')}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Ownership Transfer</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Current Owner</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(archive.current_landlord?.name || 'N/A')}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">New Owner</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(archive.new_owner_name)}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Transfer Date</label>
                                <p class="text-sm" style="color: var(--text-primary);">${archive.transfer_date || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Sale Amount</label>
                                <p class="text-sm" style="color: var(--text-primary);">${archive.formatted_sale_amount}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Archive Information</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Archived At</label>
                                <p class="text-sm" style="color: var(--text-primary);">${new Date(archive.archived_at).toLocaleString()}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Archived By</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(archive.archived_by?.name || 'System')}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Archive Reason</label>
                                <p class="text-sm" style="color: var(--text-primary);">${archive.archive_reason || 'Yearly Cleanup'}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-4xl mb-2" style="color: var(--danger);"></i>
                    <p style="color: var(--text-primary);">Failed to load archive details.</p>
                    <p class="text-sm mt-2" style="color: var(--text-secondary);">${data.message || 'Please try again.'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-circle text-4xl mb-2" style="color: var(--danger);"></i>
                <p style="color: var(--text-primary);">An error occurred while loading details.</p>
            </div>
        `;
    });
}

function hideArchiveDetailsModal() {
    const modal = document.getElementById('archiveDetailsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ============================================
// RESTORE SINGLE ARCHIVE
// ============================================
function restoreSingleArchive(archiveId, isReversal = false) {
    const warningMessage = isReversal 
        ? '⚠️ Are you sure you want to restore this reversal record back to the active transfers table?'
        : '⚠️ Are you sure you want to restore this archived transfer back to active transfers?';
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    showNotification('info', 'Restoring...');
    
    fetch(`/admin/ownership-transfers/archive/restore/${archiveId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

// ============================================
// DELETE SINGLE ARCHIVE
// ============================================
function deleteSingleArchive(archiveId, isReversal = false) {
    const warningMessage = isReversal
        ? '⚠️ WARNING: Are you sure you want to permanently delete this reversal record from the archive? This action cannot be undone!'
        : '⚠️ WARNING: Are you sure you want to permanently delete this archived transfer? This action cannot be undone!';
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    showNotification('info', 'Deleting...');
    
    fetch(`/admin/ownership-transfers/archive/delete/${archiveId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

// ============================================
// BULK ACTIONS
// ============================================
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.archive-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.archive-checkbox:checked');
    selectedArchiveIds = Array.from(checkboxes).map(cb => cb.value);
    
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    if (bulkActionsBtn) {
        if (selectedArchiveIds.length > 0) {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${selectedArchiveIds.length} Selected <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        } else {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> Bulk Actions <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        }
    }
}

function showBulkRestoreModal() {
    if (selectedArchiveIds.length === 0) {
        showNotification('error', 'Please select at least one archive to restore.');
        return;
    }
    
    const modal = document.getElementById('bulkRestoreModal');
    const selectedDiv = document.getElementById('bulkSelectedArchives');
    
    selectedDiv.innerHTML = '';
    selectedArchiveIds.forEach(id => {
        const row = document.querySelector(`tr[data-archive-id="${id}"]`);
        if (row) {
            const propertyName = row.querySelector('.property-name')?.textContent || 'Unknown Property';
            const year = row.querySelector('td:nth-child(2)')?.textContent.trim() || 'Unknown Year';
            const isReversal = row.getAttribute('data-is-reversal') === 'true';
            const div = document.createElement('div');
            div.className = 'text-sm py-1 flex items-center justify-between';
            div.innerHTML = `
                <span><i class="fas ${isReversal ? 'fa-undo-alt' : 'fa-building'} mr-2 text-gray-400"></i> ${escapeHtml(propertyName)} (${year})</span>
                ${isReversal ? '<span class="text-xs text-warning"><i class="fas fa-undo-alt mr-1"></i> Reversal</span>' : ''}
            `;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkRestoreArchiveIdsContainer');
    container.innerHTML = '';
    selectedArchiveIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'archive_ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkRestoreModal() {
    const modal = document.getElementById('bulkRestoreModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitBulkRestore() {
    if (selectedArchiveIds.length === 0) {
        showNotification('error', 'No archives selected.');
        return;
    }
    
    const hasReversals = Array.from(document.querySelectorAll('.archive-checkbox:checked')).some(cb => {
        const row = cb.closest('tr');
        return row && row.getAttribute('data-is-reversal') === 'true';
    });
    
    const warningMessage = hasReversals
        ? `⚠️ Are you sure you want to restore ${selectedArchiveIds.length} archived record(s) (including reversal records) back to active transfers?`
        : `⚠️ Are you sure you want to restore ${selectedArchiveIds.length} archived transfer(s) back to active transfers?`;
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    const submitButton = document.querySelector('#bulkRestoreModal .btn-primary');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    fetch('{{ route("admin.ownership-transfers.archive.bulk-restore") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ archive_ids: selectedArchiveIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideBulkRestoreModal();
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

function showBulkDeleteModal() {
    if (selectedArchiveIds.length === 0) {
        showNotification('error', 'Please select at least one archive to delete.');
        return;
    }
    
    const modal = document.getElementById('bulkDeleteModal');
    const selectedDiv = document.getElementById('bulkDeleteSelected');
    
    selectedDiv.innerHTML = '';
    selectedArchiveIds.forEach(id => {
        const row = document.querySelector(`tr[data-archive-id="${id}"]`);
        if (row) {
            const propertyName = row.querySelector('.property-name')?.textContent || 'Unknown Property';
            const year = row.querySelector('td:nth-child(2)')?.textContent.trim() || 'Unknown Year';
            const isReversal = row.getAttribute('data-is-reversal') === 'true';
            const div = document.createElement('div');
            div.className = 'text-sm py-1 flex items-center justify-between';
            div.innerHTML = `
                <span><i class="fas ${isReversal ? 'fa-undo-alt' : 'fa-building'} mr-2 text-gray-400"></i> ${escapeHtml(propertyName)} (${year})</span>
                ${isReversal ? '<span class="text-xs text-warning"><i class="fas fa-undo-alt mr-1"></i> Reversal</span>' : ''}
            `;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkDeleteArchiveIdsContainer');
    container.innerHTML = '';
    selectedArchiveIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkDeleteModal() {
    const modal = document.getElementById('bulkDeleteModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitBulkDelete() {
    if (selectedArchiveIds.length === 0) {
        showNotification('error', 'No archives selected.');
        return;
    }
    
    const deletionReason = document.getElementById('bulkDeleteReason')?.value || '';
    const hasReversals = Array.from(document.querySelectorAll('.archive-checkbox:checked')).some(cb => {
        const row = cb.closest('tr');
        return row && row.getAttribute('data-is-reversal') === 'true';
    });
    
    const warningMessage = hasReversals
        ? `⚠️ WARNING: Are you sure you want to permanently delete ${selectedArchiveIds.length} archived record(s) (including reversal records)? This action cannot be undone!`
        : `⚠️ WARNING: Are you sure you want to permanently delete ${selectedArchiveIds.length} archived transfer(s)? This action cannot be undone!`;
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    const submitButton = document.querySelector('#bulkDeleteModal .btn-danger');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    fetch('{{ route("admin.ownership-transfers.archive.bulk-delete") }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            archive_ids: selectedArchiveIds,
            deletion_reason: deletionReason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideBulkDeleteModal();
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
// EMPTY ARCHIVE
// ============================================
function showEmptyArchiveModal() {
    const totalRecords = {{ $totalArchived }};
    
    if (totalRecords === 0) {
        showNotification('error', 'Archive is already empty.');
        return;
    }
    
    document.getElementById('emptyArchiveConfirmation').value = '';
    document.getElementById('emptyArchiveDeletionReason').value = '';
    
    const modal = document.getElementById('emptyArchiveModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        document.getElementById('emptyArchiveConfirmation')?.focus();
    }, 100);
}

function hideEmptyArchiveModal() {
    const modal = document.getElementById('emptyArchiveModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitEmptyArchive() {
    const confirmation = document.getElementById('emptyArchiveConfirmation')?.value?.trim() || '';
    const deletionReason = document.getElementById('emptyArchiveDeletionReason')?.value || '';
    const totalRecords = {{ $totalArchived }};
    const regularCount = {{ $regularArchived }};
    const reversalCount = {{ $reversalArchived }};
    
    if (confirmation !== 'DELETE_ARCHIVE') {
        showNotification('error', 'Please type "DELETE_ARCHIVE" to confirm emptying the archive.');
        document.getElementById('emptyArchiveConfirmation')?.focus();
        return;
    }
    
    let warningMessage = `⚠️ WARNING: Are you sure you want to permanently delete ALL ${totalRecords} archived record(s) (${regularCount} regular, ${reversalCount} reversal)? This action cannot be undone!`;
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    const submitButton = document.querySelector('#emptyArchiveModal .btn-danger');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    fetch('{{ route("admin.ownership-transfers.archive.empty") }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            confirmation: confirmation,
            deletion_reason: deletionReason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideEmptyArchiveModal();
            setTimeout(() => window.location.reload(), 1500);
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
// ARCHIVE STATISTICS
// ============================================
function showArchiveStats() {
    const modal = document.getElementById('archiveStatsModal');
    const content = document.getElementById('archiveStatsContent');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--info);"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading statistics...</p>
        </div>
    `;
    
    fetch('{{ route("admin.ownership-transfers.archive.stats") }}', {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.statistics;
            content.innerHTML = `
                <div class="space-y-6">
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--info);">${stats.total_archived.toLocaleString()}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Total Archived</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--success);">${(stats.regular_count || 0).toLocaleString()}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Regular</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--warning);">${(stats.reversal_count || 0).toLocaleString()}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Reversal</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--success);">₵${stats.total_value.toLocaleString()}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Total Value</div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Archives by Year</h4>
                        <div class="space-y-2">
                            ${stats.by_year.map(yearData => `
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span style="color: var(--text-primary);">${yearData.archive_year}</span>
                                        <span style="color: var(--text-primary);">${yearData.count.toLocaleString()} records</span>
                                    </div>
                                    <div class="w-full rounded-full h-2" style="background-color: rgba(var(--info-rgb), 0.2);">
                                        <div class="rounded-full h-2" style="width: ${Math.min(100, (yearData.count / stats.by_year[0]?.count) * 100)}%; background-color: var(--info);"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Archives by Status</h4>
                        <div class="grid grid-cols-2 gap-2">
                            ${stats.by_status.map(statusData => `
                                <div class="flex justify-between p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                                    <span class="text-sm">${statusData.status_label || statusData.status}</span>
                                    <span class="text-sm font-semibold">${statusData.count.toLocaleString()}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        Last updated: ${new Date().toLocaleString()}
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-4xl mb-2" style="color: var(--danger);"></i>
                    <p style="color: var(--text-primary);">Failed to load statistics.</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-circle text-4xl mb-2" style="color: var(--danger);"></i>
                <p style="color: var(--text-primary);">An error occurred while loading statistics.</p>
            </div>
        `;
    });
}

function hideArchiveStatsModal() {
    const modal = document.getElementById('archiveStatsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showAllYears() {
    showNotification('info', 'All years are already displayed in the sidebar.');
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function showNotification(type, message) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `mb-4 p-4 rounded-lg shadow-lg`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : type === 'error' ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--info-rgb), 0.1)';
    notification.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : type === 'error' ? '1px solid rgba(var(--danger-rgb), 0.3)' : '1px solid rgba(var(--info-rgb), 0.3)';
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2" style="color: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--info)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--info)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
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

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('#successMessage, #errorMessage').forEach(msg => {
            if (msg.style.display !== 'none') msg.style.display = 'none';
        });
    }, 5000);
}
</script>

<style>
/* Reuse the same styles from trash blade */
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

.action-btn.restore {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.download {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
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
    background-color: #dc3545 !important;
}

.btn-warning {
    background-color: var(--warning) !important;
    color: white !important;
    border: 1px solid var(--warning) !important;
}

.btn-warning:hover {
    background-color: #e0a800 !important;
}

.btn-info {
    background-color: var(--info) !important;
    color: white !important;
    border: 1px solid var(--info) !important;
}

.btn-info:hover {
    background-color: #138496 !important;
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

.archive-checkbox, #selectAll {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

[data-tooltip] {
    position: relative;
    cursor: pointer;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 4px 8px;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    font-size: 12px;
    border-radius: 4px;
    white-space: nowrap;
    display: none;
    z-index: 10;
}

[data-tooltip]:hover:before {
    display: block;
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