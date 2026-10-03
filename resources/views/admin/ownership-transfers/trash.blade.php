{{-- admin/ownership-transfers/trash.blade.php --}}
@php
    // Only admins can access this page
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $layout = 'layouts.app';
    $routePrefix = 'admin.ownership-transfers';
    
    // Create dynamic page title
    $pageTitle = 'Trashed Ownership Transfers';
    
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
    $totalTrashed = $trashStats['total_trashed'] ?? 0;
    $regularTransfersTrashed = $trashStats['regular_transfers'] ?? 0;
    $reversalRecordsTrashed = $trashStats['reversal_records'] ?? 0;
    $orphanedReversals = $trashStats['orphaned_reversals'] ?? 0;
    $byStatus = $trashStats['by_status'] ?? [];
    $byDate = $trashStats['by_date'] ?? [];
    $byUser = $trashStats['by_user'] ?? [];
    $storageUsed = $trashStats['storage_used_mb'] ?? 0;
    $reversalStorageUsed = $trashStats['reversal_storage_used_mb'] ?? 0;
    
    // Get current filter
    $currentStatus = request('status');
    $currentType = request('type', 'all');
    $currentDeletedBy = request('deleted_by');
    $currentDateFrom = request('date_from');
    $currentDateTo = request('date_to');
    $currentSearch = request('search');
    
    // Status labels
    $statuses = \App\Models\PropertyOwnershipTransfer::getStatuses();
    
    // Type options
    $typeOptions = [
        'all' => 'All Records',
        'regular' => 'Regular Transfers Only',
        'reversal' => 'Reversal Records Only',
        'reversal_with_original' => 'Reversals with Original',
        'orphaned_reversals' => 'Orphaned Reversals',
    ];
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
                         style="background: linear-gradient(135deg, var(--danger) 0%, var(--warning) 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> 
                        Trashed Ownership Transfer Requests
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Review and manage soft-deleted transfer requests</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-trash mr-1"></i>
                        <span id="totalTrashedCount">{{ number_format($totalTrashed) }}</span> deleted record{{ $totalTrashed != 1 ? 's' : '' }} total
                        <span class="mx-1">•</span>
                        <i class="fas fa-exchange-alt mr-1" style="color: var(--success);"></i>
                        <span>{{ number_format($regularTransfersTrashed) }} regular transfers</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-undo-alt mr-1" style="color: var(--warning);"></i>
                        <span>{{ number_format($reversalRecordsTrashed) }} reversal records</span>
                        @if($orphanedReversals > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-exclamation-triangle mr-1" style="color: var(--warning);"></i>
                        <span class="text-warning">{{ $orphanedReversals }} orphaned reversals</span>
                        @endif
                        @if(($byDate['older_than_90_days'] ?? 0) > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-exclamation-triangle mr-1" style="color: var(--warning);"></i>
                        <span>{{ $byDate['older_than_90_days'] }} older than 90 days</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-database mr-1"></i> Storage: {{ number_format($storageUsed, 2) }} MB
                    @if($reversalStorageUsed > 0)
                    <span class="text-xs ml-1">({{ number_format($reversalStorageUsed, 2) }} MB reversals)</span>
                    @endif
                </div>
                <a href="{{ route('admin.ownership-transfers.reversal-trash-dashboard') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-1"></i> Reversal Trash Stats
                    @if($reversalRecordsTrashed > 0)
                    <span class="ml-1 px-1.5 py-0.5 bg-yellow-500 bg-opacity-20 rounded-full text-xs">{{ $reversalRecordsTrashed }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.ownership-transfers.archive') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-archive mr-1"></i> View Archive
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
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Deleted</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalTrashed) }}</p>
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
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($regularTransfersTrashed) }}</p>
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
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($reversalRecordsTrashed) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending (at deletion)</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($byStatus['pending'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-times-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Rejected (at deletion)</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($byStatus['rejected'] ?? 0) }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-calendar-week text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">> 30 Days in Trash</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($byDate['older_than_30_days'] ?? 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Warning Banner for Old Trashed Records -->
    @if(($byDate['older_than_90_days'] ?? 0) > 0)
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Note:</strong> <span id="oldRecordsCount">{{ $byDate['older_than_90_days'] }}</span> record(s) have been in trash for more than 90 days.
                    These can be permanently deleted to free up storage space.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Warning Banner for Orphaned Reversals -->
    @if($orphanedReversals > 0)
    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 mb-6 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-link-broken text-orange-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-orange-700">
                    <strong>Orphaned Reversal Records:</strong> <span id="orphanedReversalsCount">{{ $orphanedReversals }}</span> reversal record(s) are missing their original transfer.
                    These can be safely deleted to clean up the database.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters and Table Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--danger);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route('admin.ownership-transfers.trash') }}" class="space-y-4" id="filterForm">
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
                                <i class="fas fa-tag mr-1"></i> Status at Deletion
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
                                <i class="fas fa-user mr-1"></i> Deleted By
                            </label>
                            <select name="deleted_by" class="index-custom-dropdown w-full">
                                <option value="">All Users</option>
                                @foreach($deleters as $deleter)
                                    <option value="{{ $deleter['id'] }}" {{ $currentDeletedBy == $deleter['id'] ? 'selected' : '' }}>
                                        {{ $deleter['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Deleted Date From
                            </label>
                            <input type="date" 
                                   name="date_from" 
                                   value="{{ $currentDateFrom }}" 
                                   class="index-custom-input w-full">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Deleted Date To
                            </label>
                            <input type="date" 
                                   name="date_to" 
                                   value="{{ $currentDateTo }}" 
                                   class="index-custom-input w-full">
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route('admin.ownership-transfers.trash') }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <button type="button" onclick="previewEmptyTrash()" class="block w-full text-center btn-warning px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-eye mr-2"></i> Preview Empty Trash
                                </button>
                                
                                <button type="button" onclick="showEmptyTrashModal()" class="block w-full text-center btn-danger px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Trash Summary -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--danger);"></i> Trash Summary
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i> Time in Trash
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Last 7 days</span>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ number_format($byDate['last_7_days'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Last 30 days</span>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ number_format($byDate['last_30_days'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Last 90 days</span>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ number_format($byDate['last_90_days'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Older than 90 days</span>
                                    <span class="text-sm font-medium text-danger">{{ number_format($byDate['older_than_90_days'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pt-3 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                <i class="fas fa-tag mr-1"></i> By Status at Deletion
                            </h4>
                            <div class="space-y-2">
                                @foreach($statuses as $key => $label)
                                    @php $count = $byStatus[$key] ?? 0; @endphp
                                    @if($count > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ number_format($count) }}</span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="pt-3 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                <i class="fas fa-exchange-alt mr-1"></i> Record Types
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Regular Transfers</span>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ number_format($regularTransfersTrashed) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Reversal Records</span>
                                    <span class="text-sm font-medium" style="color: var(--warning);">{{ number_format($reversalRecordsTrashed) }}</span>
                                </div>
                                @if($orphanedReversals > 0)
                                <div class="flex justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">Orphaned Reversals</span>
                                    <span class="text-sm font-medium text-danger">{{ number_format($orphanedReversals) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        @if(count($byUser) > 0)
<div class="pt-3 border-t" style="border-color: var(--border-color);">
    <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
        <i class="fas fa-users mr-1"></i> Deleted By
    </h4>
    <div class="space-y-2">
        @foreach($byUser as $user)
        <div class="flex justify-between items-center">
            <span class="text-sm" style="color: var(--text-primary);">
                <i class="fas fa-user-circle mr-2" style="color: var(--info);"></i>
                {{ $user['name'] ?? 'Unknown User' }}
            </span>
        </div>
        @endforeach
    </div>
</div>
@endif
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
                            Deleted Transfer Requests (<span id="totalEntries">{{ $transfers->total() }}</span>)
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);" id="paginationInfo">
                            Showing <span id="firstItem">{{ $transfers->firstItem() }}</span> to <span id="lastItem">{{ $transfers->lastItem() }}</span> of <span id="totalItems">{{ $transfers->total() }}</span> entries
                            @if($currentStatus)
                                <span class="ml-2 px-2 py-1 text-xs rounded-full badge-warning">
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
                        @if($transfers->count() > 0)
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
                                    <button type="button" onclick="showBulkForceDeleteModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-trash-alt text-red-500 mr-2"></i> Bulk Permanent Delete
                                    </button>
                                    @if($reversalRecordsTrashed > 0)
                                    <hr class="my-1" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showBulkDeleteOrphanedReversalsModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-link-broken text-orange-500 mr-2"></i> Delete Orphaned Reversals
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <a href="{{ route('admin.ownership-transfers.trash.export', ['csv']) . '?' . http_build_query(request()->all()) }}" 
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Export
                        </a>
                    </div>
                </div>

                @if($transfers->isEmpty())
                    <div class="text-center py-12" id="emptyState">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <i class="fas fa-trash-alt text-2xl" style="color: var(--danger);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No trashed transfer requests found
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['search', 'status', 'deleted_by', 'date_from', 'date_to', 'type']))
                                No trashed transfer requests match your search criteria.
                            @else
                                The trash is empty. Deleted transfers will appear here.
                            @endif
                        </p>
                        @if(!request()->hasAny(['search', 'status', 'deleted_by', 'date_from', 'date_to', 'type']))
                        <div class="flex justify-center space-x-3">
                            <a href="{{ route('admin.ownership-transfers.reversal-trash-dashboard') }}" class="btn-warning px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-undo-alt mr-2"></i> Reversal Trash Stats
                            </a>
                            <a href="{{ route('admin.ownership-transfers.archive') }}" class="btn-info px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-archive mr-2"></i> View Archive
                            </a>
                            <a href="{{ route('admin.ownership-transfers.index') }}" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-exchange-alt mr-2"></i> View Active Transfers
                            </a>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full" id="transfersTable">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded border-gray-300" style="width: 18px; height: 18px;">
                                    </th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property / Reference</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Current Owner</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">New Owner</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted At</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="transfersTableBody">
                                @foreach($transfers as $transfer)
                                @php
                                    $daysInTrash = $transfer->deleted_at ? $transfer->deleted_at->diffInDays(now()) : 0;
                                    $deletedByInfo = $transfer->metadata['soft_deleted']['deleted_by_name'] ?? null;
                                    $isReversalRecord = $transfer->isReversalRecord();
                                    $originalTransferId = $transfer->original_transfer_id ?? $transfer->metadata['original_transfer_id'] ?? null;
                                    $statusColors = [
                                        'pending' => ['bg' => 'warning', 'icon' => 'clock'],
                                        'approved' => ['bg' => 'success', 'icon' => 'check-circle'],
                                        'rejected' => ['bg' => 'danger', 'icon' => 'times-circle'],
                                        'completed' => ['bg' => 'info', 'icon' => 'check-double'],
                                        'cancelled' => ['bg' => 'secondary', 'icon' => 'ban'],
                                    ];
                                    $statusConfig = $statusColors[$transfer->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                                @endphp
                                <tr data-transfer-id="{{ $transfer->id }}" 
                                    data-status="{{ $transfer->status }}"
                                    data-days-in-trash="{{ $daysInTrash }}"
                                    data-property-name="{{ $transfer->property->property_name ?? 'N/A' }}"
                                    data-is-reversal="{{ $isReversalRecord ? 'true' : 'false' }}">
                                    <td class="p-3 text-center">
                                        <input type="checkbox" class="transfer-checkbox" value="{{ $transfer->id }}" onclick="updateBulkActions()">
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-3">
                                                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                                    <i class="fas {{ $isReversalRecord ? 'fa-undo-alt' : 'fa-building' }}" style="color: var(--danger);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium property-name" style="color: var(--text-primary);">
                                                    {{ $transfer->property->property_name ?? 'N/A' }}
                                                    @if($isReversalRecord)
                                                    <span class="ml-2 text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-undo-alt mr-0.5 text-xs"></i> Reversal
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="text-xs mt-1 font-mono" style="color: var(--text-secondary);">
                                                    <i class="fas fa-hashtag mr-1 text-xs"></i> Ref: {{ $transfer->document_reference }}
                                                </div>
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-file-alt mr-1 text-xs"></i> {{ $transfer->document_type_label }}
                                                </div>
                                                @if($originalTransferId)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-link mr-1 text-xs"></i> Original Transfer: #{{ $originalTransferId }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 mr-2">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                     style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                                    <i class="fas fa-user-tie text-xs" style="color: var(--secondary);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm current-owner" style="color: var(--text-primary);">
                                                    {{ $transfer->currentLandlord->name ?? 'N/A' }}
                                                </div>
                                                @if($transfer->currentLandlord && $transfer->currentLandlord->email)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    {{ $transfer->currentLandlord->email }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 mr-2">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                     style="background-color: rgba(var(--info-rgb), 0.1);">
                                                    <i class="fas fa-user-plus text-xs" style="color: var(--info);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm new-owner" style="color: var(--text-primary);">
                                                    {{ $transfer->new_owner_name }}
                                                </div>
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-phone mr-1 text-xs"></i> {{ $transfer->new_owner_phone ?? 'N/A' }}
                                                </div>
                                                @if($transfer->new_owner_email)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    {{ $transfer->new_owner_email }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $transfer->deleted_at ? $transfer->deleted_at->format('M j, Y H:i') : 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ $transfer->deleted_at ? $transfer->deleted_at->diffForHumans() : 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar-day mr-1"></i>
                                            {{ $daysInTrash }} day{{ $daysInTrash != 1 ? 's' : '' }} in trash
                                        </div>
                                        @if($deletedByInfo)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-user mr-1"></i>
                                            by {{ $deletedByInfo }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-{{ $statusConfig['bg'] }}">
                                            <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                            {{ $statuses[$transfer->status] ?? $transfer->status }}
                                        </span>
                                        @if($isReversalRecord && $transfer->reversal_status)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Reversal Status: {{ ucfirst($transfer->reversal_status) }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center space-x-2">
                                            <a href="{{ route('admin.ownership-transfers.show', $transfer->id) }}" 
                                               class="action-btn view" data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($transfer->canBeRestored())
                                            <button type="button" 
                                                    onclick="singleRestore('{{ $transfer->id }}')"
                                                    class="action-btn restore" 
                                                    data-tooltip="{{ $isReversalRecord ? 'Restore Reversal Record' : 'Restore Transfer' }}">
                                                <i class="fas fa-trash-restore"></i>
                                            </button>
                                            @endif
                                            
                                            @if($transfer->canBePermanentlyDeleted())
                                            <button type="button" 
                                                    onclick="singleForceDelete('{{ $transfer->id }}', {{ $isReversalRecord ? 'true' : 'false' }})"
                                                    class="action-btn delete" 
                                                    data-tooltip="{{ $isReversalRecord ? 'Permanently Delete Reversal Record' : 'Permanently Delete Transfer' }}">
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
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);" id="paginationText">
                            Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} entries
                        </div>
                        <div class="pagination" id="paginationLinks">
                            {{ $transfers->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
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
                    <i class="fas fa-trash-restore mr-2" style="color: var(--success);"></i> Bulk Restore Records
                </h3>
                <button type="button" onclick="hideBulkRestoreModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkRestoreForm" method="POST" action="{{ route('admin.ownership-transfers.trash.bulk-restore') }}">
                @csrf
                <div class="modal-body">
                    <div id="selectedTransfersList" class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected records:</p>
                        <div id="selectedTransfers" class="max-h-40 overflow-y-auto space-y-1"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="restore_originals" value="1" id="restoreOriginalsCheckbox" class="mr-2 w-4 h-4">
                            <span class="text-sm" style="color: var(--text-primary);">Also restore original transfers for reversal records</span>
                        </label>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> When restoring reversal records, also restore their original transfers if they are in trash.
                        </p>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-green-500 mr-2"></i>
                            <p class="text-sm text-green-700">
                                This will restore all selected records from trash. They will become active again.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkRestoreTransferIdsContainer"></div>
                    <button type="button" onclick="hideBulkRestoreModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-restore mr-2"></i> Restore Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Force Delete Modal -->
<div id="bulkForceDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkForceDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Bulk Permanent Delete
                </h3>
                <button type="button" onclick="hideBulkForceDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="bulkSelectedTransfersList" class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">Selected records:</p>
                    <div id="bulkSelectedTransfers" class="max-h-40 overflow-y-auto space-y-1"></div>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="deleteOriginalsCheckbox" class="mr-2 w-4 h-4">
                        <span class="text-sm" style="color: var(--text-primary);">Also delete original transfers for reversal records</span>
                    </label>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> When deleting reversal records, also delete their original transfers if they are in trash.
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Deletion Reason (Optional)
                    </label>
                    <textarea id="bulkDeletionReason" rows="3" class="index-custom-textarea w-full" placeholder="Why are these being permanently deleted?"></textarea>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                        <p class="text-sm text-red-700">
                            <strong>Warning:</strong> This action is irreversible. All selected records and their associated documents will be permanently deleted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="bulkForceDeleteTransferIdsContainer"></div>
                <button type="button" onclick="hideBulkForceDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="submitBulkForceDelete()" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideEmptyTrashModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Empty Trash
                </h3>
                <button type="button" onclick="hideEmptyTrashModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent" class="mb-4"></div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-1"></i> Which records to delete?
                    </label>
                    <select id="emptyTrashTypeFilter" class="index-custom-dropdown w-full mb-3">
                        <option value="all">All records (Regular + Reversal)</option>
                        <option value="regular">Regular transfers only</option>
                        <option value="reversal">Reversal records only</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Type <span class="text-danger font-bold">"DELETE_ALL"</span> to confirm
                    </label>
                    <input type="text" 
                           id="emptyTrashConfirmation" 
                           class="index-custom-input w-full" 
                           placeholder="DELETE_ALL"
                           autocomplete="off">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                        This action cannot be undone. Please type DELETE_ALL to confirm.
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Optional: Only delete older than (days)
                    </label>
                    <select id="emptyTrashOlderThanDays" class="index-custom-dropdown w-full">
                        <option value="">All trashed records</option>
                        <option value="7">7 days</option>
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                        <option value="180">180 days</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Deletion Reason (Optional)
                    </label>
                    <textarea id="emptyTrashDeletionReason" rows="2" class="index-custom-textarea" placeholder="Why are you emptying the trash?"></textarea>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                        <p class="text-sm text-red-700">
                            <strong>Warning:</strong> This will permanently delete all selected records in the trash. This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideEmptyTrashModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="submitEmptyTrashFixed()" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let selectedTransferIds = [];

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
    
    // Handle bulk restore form
    const bulkRestoreForm = document.getElementById('bulkRestoreForm');
    if (bulkRestoreForm) {
        bulkRestoreForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkRestore();
        });
    }
});

// ============================================
// SINGLE RESTORE
// ============================================
function singleRestore(transferId) {
    if (!confirm('Are you sure you want to restore this record from trash?')) {
        return;
    }
    
    showNotification('info', 'Processing...');
    
    fetch(`/admin/ownership-transfers/trash/restore/${transferId}`, {
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
// SINGLE FORCE DELETE
// ============================================
function singleForceDelete(transferId, isReversal = false) {
    const warningMessage = isReversal 
        ? '⚠️ WARNING: Are you sure you want to permanently delete this reversal record? This action cannot be undone!'
        : '⚠️ WARNING: Are you sure you want to permanently delete this transfer? This action cannot be undone!';
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    showNotification('info', 'Processing...');
    
    fetch(`/admin/ownership-transfers/trash/force-delete/${transferId}`, {
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
// BULK RESTORE
// ============================================
function submitBulkRestore() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'No records selected.');
        return;
    }
    
    const restoreOriginals = document.getElementById('restoreOriginalsCheckbox')?.checked ? 1 : 0;
    const submitButton = document.querySelector('#bulkRestoreForm button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    fetch('{{ route("admin.ownership-transfers.trash.bulk-restore") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            transfer_ids: selectedTransferIds,
            restore_originals: restoreOriginals
        })
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

// ============================================
// BULK FORCE DELETE
// ============================================
function submitBulkForceDelete() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'No records selected.');
        return;
    }
    
    const deletionReason = document.getElementById('bulkDeletionReason')?.value || '';
    const deleteOriginals = document.getElementById('deleteOriginalsCheckbox')?.checked ? 1 : 0;
    
    const hasReversals = Array.from(document.querySelectorAll('.transfer-checkbox:checked')).some(cb => {
        const row = cb.closest('tr');
        return row && row.getAttribute('data-is-reversal') === 'true';
    });
    
    const warningMessage = hasReversals
        ? `⚠️ WARNING: Are you sure you want to permanently delete ${selectedTransferIds.length} record(s) (including reversal records)? This action cannot be undone!`
        : `⚠️ WARNING: Are you sure you want to permanently delete ${selectedTransferIds.length} transfer(s)? This action cannot be undone!`;
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    const submitButton = document.querySelector('#bulkForceDeleteModal .btn-danger');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    fetch('{{ route("admin.ownership-transfers.trash.bulk-force-delete") }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            transfer_ids: selectedTransferIds,
            deletion_reason: deletionReason,
            delete_originals: deleteOriginals
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideBulkForceDeleteModal();
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
// EMPTY TRASH
// ============================================
function submitEmptyTrashFixed() {
    const confirmation = document.getElementById('emptyTrashConfirmation')?.value?.trim() || '';
    const olderThanDays = document.getElementById('emptyTrashOlderThanDays')?.value || '';
    const deletionReason = document.getElementById('emptyTrashDeletionReason')?.value || '';
    const typeFilter = document.getElementById('emptyTrashTypeFilter')?.value || 'all';
    
    // Validate confirmation
    if (confirmation !== 'DELETE_ALL') {
        showNotification('error', 'Please type "DELETE_ALL" to confirm emptying the trash.');
        document.getElementById('emptyTrashConfirmation')?.focus();
        return;
    }
    
    let warningMessage = '⚠️ WARNING: Are you sure you want to permanently delete ';
    if (typeFilter === 'regular') {
        warningMessage += 'ALL regular transfers in the trash';
    } else if (typeFilter === 'reversal') {
        warningMessage += 'ALL reversal records in the trash';
    } else {
        warningMessage += 'ALL records in the trash';
    }
    warningMessage += '? This action cannot be undone!';
    
    if (!confirm(warningMessage)) {
        return;
    }
    
    const submitButton = document.querySelector('#emptyTrashModal .btn-danger');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const requestData = {
        confirmation: confirmation,
        include_regular: typeFilter === 'all' || typeFilter === 'regular',
        include_reversals: typeFilter === 'all' || typeFilter === 'reversal'
    };
    if (olderThanDays) {
        requestData.older_than_days = parseInt(olderThanDays);
    }
    if (deletionReason) {
        requestData.deletion_reason = deletionReason;
    }
    
    fetch('{{ route("admin.ownership-transfers.trash.empty") }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideEmptyTrashModal();
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
// UTILITY FUNCTIONS
// ============================================
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.transfer-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.transfer-checkbox:checked');
    selectedTransferIds = Array.from(checkboxes).map(cb => cb.value);
    
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    if (bulkActionsBtn) {
        if (selectedTransferIds.length > 0) {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${selectedTransferIds.length} Selected <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        } else {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> Bulk Actions <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        }
    }
}

function showBulkRestoreModal() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'Please select at least one record to restore.');
        return;
    }
    
    const modal = document.getElementById('bulkRestoreModal');
    const selectedTransfersDiv = document.getElementById('selectedTransfers');
    const restoreOriginalsCheckbox = document.getElementById('restoreOriginalsCheckbox');
    
    // Check if any selected records are reversal records
    const hasReversals = Array.from(document.querySelectorAll('.transfer-checkbox:checked')).some(cb => {
        const row = cb.closest('tr');
        return row && row.getAttribute('data-is-reversal') === 'true';
    });
    
    if (restoreOriginalsCheckbox) {
        restoreOriginalsCheckbox.checked = hasReversals;
        restoreOriginalsCheckbox.disabled = !hasReversals;
    }
    
    selectedTransfersDiv.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row) {
            const propertyName = row.querySelector('.property-name')?.textContent || 'Unknown Property';
            const isReversal = row.getAttribute('data-is-reversal') === 'true';
            const div = document.createElement('div');
            div.className = 'text-sm py-1 flex items-center justify-between';
            div.innerHTML = `
                <span><i class="fas ${isReversal ? 'fa-undo-alt' : 'fa-building'} mr-2 text-gray-400"></i> ${escapeHtml(propertyName)}</span>
                ${isReversal ? '<span class="text-xs text-warning"><i class="fas fa-undo-alt mr-1"></i> Reversal</span>' : ''}
            `;
            selectedTransfersDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkRestoreTransferIdsContainer');
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

function hideBulkRestoreModal() {
    const modal = document.getElementById('bulkRestoreModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkForceDeleteModal() {
    if (selectedTransferIds.length === 0) {
        showNotification('error', 'Please select at least one record to delete.');
        return;
    }
    
    const modal = document.getElementById('bulkForceDeleteModal');
    const selectedTransfersDiv = document.getElementById('bulkSelectedTransfers');
    const deleteOriginalsCheckbox = document.getElementById('deleteOriginalsCheckbox');
    
    // Check if any selected records are reversal records
    const hasReversals = Array.from(document.querySelectorAll('.transfer-checkbox:checked')).some(cb => {
        const row = cb.closest('tr');
        return row && row.getAttribute('data-is-reversal') === 'true';
    });
    
    if (deleteOriginalsCheckbox) {
        deleteOriginalsCheckbox.checked = hasReversals;
        deleteOriginalsCheckbox.disabled = !hasReversals;
    }
    
    selectedTransfersDiv.innerHTML = '';
    selectedTransferIds.forEach(id => {
        const row = document.querySelector(`tr[data-transfer-id="${id}"]`);
        if (row) {
            const propertyName = row.querySelector('.property-name')?.textContent || 'Unknown Property';
            const isReversal = row.getAttribute('data-is-reversal') === 'true';
            const div = document.createElement('div');
            div.className = 'text-sm py-1 flex items-center justify-between';
            div.innerHTML = `
                <span><i class="fas ${isReversal ? 'fa-undo-alt' : 'fa-building'} mr-2 text-gray-400"></i> ${escapeHtml(propertyName)}</span>
                ${isReversal ? '<span class="text-xs text-warning"><i class="fas fa-undo-alt mr-1"></i> Reversal</span>' : ''}
            `;
            selectedTransfersDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkForceDeleteTransferIdsContainer');
    container.innerHTML = '';
    selectedTransferIds.forEach(id => {
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

function hideBulkForceDeleteModal() {
    const modal = document.getElementById('bulkForceDeleteModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showEmptyTrashModal() {
    document.getElementById('emptyTrashConfirmation').value = '';
    document.getElementById('emptyTrashOlderThanDays').value = '';
    document.getElementById('emptyTrashDeletionReason').value = '';
    document.getElementById('emptyTrashTypeFilter').value = 'all';
    
    const modal = document.getElementById('emptyTrashModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        document.getElementById('emptyTrashConfirmation')?.focus();
    }, 100);
}

function hideEmptyTrashModal() {
    const modal = document.getElementById('emptyTrashModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function previewEmptyTrash() {
    showNotification('info', 'Loading preview...');
    
    fetch('{{ route("admin.ownership-transfers.trash.preview") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const previewDiv = document.getElementById('previewContent');
                previewDiv.innerHTML = `
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold mb-2">Preview (first 50 records):</h4>
                        <div class="max-h-60 overflow-y-auto">
                            ${data.preview.map(item => `
                                <div class="text-sm py-1 border-b border-gray-200">
                                    <i class="fas ${item.is_reversal ? 'fa-undo-alt' : 'fa-building'} mr-2 text-gray-400"></i>
                                    ${escapeHtml(item.property_name)} - 
                                    <span class="text-gray-500">Deleted ${item.days_in_trash} days ago</span>
                                    ${item.is_reversal ? '<span class="ml-2 text-xs text-warning">(Reversal)</span>' : ''}
                                </div>
                            `).join('')}
                        </div>
                        ${data.total_count > 50 ? `<p class="text-sm text-gray-500 mt-2">... and ${data.total_count - 50} more records</p>` : ''}
                        <p class="text-sm font-semibold mt-3">Total: ${data.total_count} records, Storage: ${data.storage_usage_mb} MB</p>
                    </div>
                `;
                showEmptyTrashModal();
            } else {
                showNotification('error', data.message || 'Failed to load preview');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Failed to load preview');
        });
}

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
/* Add restore button style */
.action-btn.restore {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.restore:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-1px);
}

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

.transfer-checkbox, #selectAll {
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