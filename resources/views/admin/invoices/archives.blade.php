@extends('layouts.app')

@section('title', 'Invoice Archives')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-archive text-2xl mr-3" style="color: var(--info);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Invoice Archives</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">View and manage archived invoices (audit trail)</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.trash') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i> Trash
                </a>
                <a href="{{ route('invoices.index') }}" class="btn-primary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
                @if($archives->total() > 0)
                    <button type="button" onclick="openExportModal()" class="btn-success flex items-center">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                    @if(auth()->user()->isSuperAdmin())
                        <button type="button" onclick="openCleanupModal()" class="btn-warning flex items-center">
                            <i class="fas fa-broom mr-2"></i> Cleanup by Age
                        </button>
                        <button type="button" onclick="openSelectionCleanupModal()" class="btn-danger flex items-center">
                            <i class="fas fa-check-double mr-2"></i> Cleanup Selected
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Archive Info Card -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="font-semibold" style="color: var(--text-primary);">About Invoice Archives</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Archived invoices are permanent audit records of deleted invoices. They cannot be restored but provide a complete history 
                    of all invoice deletions for compliance and auditing purposes. Archives are retained according to your data retention policy.
                </p>
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-archive mr-1"></i> Total Archives: 
                        <strong style="color: var(--info);">{{ number_format($statistics['total_archives']) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-money-bill-wave mr-1"></i> Total Amount: 
                        <strong style="color: var(--info);">{{ $system_settings->formatAmount($statistics['total_amount']) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-alt mr-1"></i> Oldest Archive: 
                        <strong style="color: var(--info);">{{ $statistics['oldest_date'] ?? 'N/A' }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-week mr-1"></i> Newest Archive: 
                        <strong style="color: var(--info);">{{ $statistics['newest_date'] ?? 'N/A' }}</strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Archives</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ number_format($statistics['total_archives']) }}</div>
                </div>
                <i class="fas fa-archive text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Manual Archives</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ number_format($statistics['manual_archives']) }}</div>
                </div>
                <i class="fas fa-user-edit text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Year-End Archives</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ number_format($statistics['year_end_archives']) }}</div>
                </div>
                <i class="fas fa-calendar-alt text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--secondary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Post-Payment</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-secondary);">{{ number_format($statistics['post_payment_archives']) }}</div>
                </div>
                <i class="fas fa-credit-card text-2xl opacity-70" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Penalties</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ $system_settings->formatAmount($statistics['total_penalties']) }}</div>
                </div>
                <i class="fas fa-exclamation-circle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('invoices.archives') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
                <div>
                    <input type="text" name="invoice_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Invoice #..." value="{{ request('invoice_number') }}">
                </div>
                <div>
                    <input type="text" name="property_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Property..." value="{{ request('property_name') }}">
                </div>
                <div>
                    <input type="text" name="landlord_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Landlord..." value="{{ request('landlord_name') }}">
                </div>
                <div>
                    <select name="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Periods</option>
                        @foreach($periods as $period)
                            <option value="{{ $period }}" {{ request('period') == $period ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($period . '-01')->format('F Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <select name="archive_type" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Types</option>
                        <option value="manual" {{ request('archive_type') == 'manual' ? 'selected' : '' }}>Manual Deletion</option>
                        <option value="year_end" {{ request('archive_type') == 'year_end' ? 'selected' : '' }}>Year-End Archive</option>
                        <option value="post_payment" {{ request('archive_type') == 'post_payment' ? 'selected' : '' }}>Post-Payment Archive</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('invoices.archives') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Deleted From</label>
                    <input type="date" name="deleted_from" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('deleted_from') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Deleted To</label>
                    <input type="date" name="deleted_to" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('deleted_to') }}">
                </div>
            </div>
        </form>
    </div>

    <!-- Selection Actions Bar -->
    <div id="selectionActions" class="hidden card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <span class="text-sm font-medium" style="color: var(--text-primary);">
                    <span id="selectedCount">0</span> archive(s) selected
                </span>
                <span class="text-sm ml-2" style="color: var(--text-secondary);">
                    Total: <span id="selectedTotal">{{ $system_settings->formatAmount(0) }}</span>
                </span>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="openSelectionCleanupModal()" class="px-3 py-2 rounded text-sm flex items-center" style="background-color: var(--danger); color: white;">
                    <i class="fas fa-trash-alt mr-1"></i> Cleanup Selected
                </button>
                <button type="button" onclick="clearSelection()" class="px-3 py-2 rounded text-sm" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-times mr-1"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Archives Table Card -->
    <div class="card p-6">
        <!-- Results Count and Selection Info -->
        <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing <span id="showingFrom">{{ $archives->firstItem() ?? 0 }}</span> to <span id="showingTo">{{ $archives->lastItem() ?? 0 }}</span> of <span id="showingTotal">{{ $archives->total() }}</span> results
                </p>
                <div class="flex items-center">
                    <input type="checkbox" id="selectAllCheckbox" class="mr-2">
                    <label for="selectAllCheckbox" class="text-sm" style="color: var(--text-secondary);">Select All</label>
                </div>
            </div>
            
            @if(request()->hasAny(['invoice_number', 'property_name', 'landlord_name', 'period', 'status', 'archive_type', 'deleted_from', 'deleted_to']))
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                @if(request('invoice_number'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Invoice: {{ request('invoice_number') }}
                </span>
                @endif
                @if(request('property_name'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Property: {{ request('property_name') }}
                </span>
                @endif
                @if(request('status'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Status: {{ ucfirst(request('status')) }}
                </span>
                @endif
                @if(request('archive_type'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Type: {{ str_replace('_', ' ', ucfirst(request('archive_type'))) }}
                </span>
                @endif
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary); width: 40px;">
                            <input type="checkbox" id="selectAllInvoices" class="row-select-all">
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Landlord</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Archive Type</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Deleted At</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Age</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Deleted By</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="archivesTableBody">
                    @forelse($archives as $archive)
                    @php
                        $ageYears = $archive->deleted_at->diffInYears(now());
                        $ageColor = $ageYears >= 7 ? 'danger' : ($ageYears >= 5 ? 'warning' : ($ageYears >= 3 ? 'info' : 'secondary'));
                    @endphp
                    <tr class="border-b hover:bg-opacity-5" style="border-color: var(--border-color);" 
                        data-archive-id="{{ $archive->id }}"
                        data-invoice-number="{{ $archive->invoice_number }}"
                        data-amount="{{ $archive->total_amount }}"
                        data-age-years="{{ $ageYears }}">
                        
                        <td class="p-3">
                            <input type="checkbox" class="archive-checkbox" value="{{ $archive->id }}" 
                                   data-amount="{{ $archive->total_amount }}"
                                   data-age="{{ $ageYears }}">
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                @if($archive->is_bulk_payment)
                                    <i class="fas fa-layer-group mr-1 text-xs" style="color: var(--info);"></i>
                                @endif
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $archive->invoice_number }}
                                </span>
                            </div>
                            @if($archive->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $archive->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->property_name }}
                            </p>
                            @if($archive->property)
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    ID: #{{ $archive->property_id }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->landlord_name }}
                            </p>
                            @if($archive->landlord_id)
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    ID: #{{ $archive->landlord_id }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->month_name }}
                            </p>
                            @if($archive->covers_periods && count($archive->covers_periods) > 0)
                                <p class="text-xs" style="color: var(--info);">
                                    <i class="fas fa-layer-group mr-1"></i> Covers {{ count($archive->covers_periods) }} months
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div>
                                <p class="font-medium" style="color: var(--success);">
                                    {{ $system_settings->formatAmount($archive->total_amount) }}
                                </p>
                                @if($archive->penalty_amount > 0)
                                <p class="text-xs" style="color: var(--danger);">
                                    +{{ $system_settings->formatAmount($archive->penalty_amount) }} penalty
                                </p>
                                @endif
                            </div>
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                    'partial' => 'info',
                                    'cancelled' => 'secondary'
                                ];
                                $statusColor = $statusColors[$archive->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                {{ ucfirst($archive->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            @php
                                $typeColors = [
                                    'manual' => 'secondary',
                                    'year_end' => 'warning',
                                    'post_payment' => 'info'
                                ];
                                $typeColor = $typeColors[$archive->archive_type] ?? 'secondary';
                                $typeLabels = [
                                    'manual' => 'Manual',
                                    'year_end' => 'Year-End',
                                    'post_payment' => 'Post-Payment'
                                ];
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $typeColor }}-rgb), 0.2); color: var(--{{ $typeColor }});">
                                <i class="fas fa-{{ $archive->archive_type == 'year_end' ? 'calendar-alt' : ($archive->archive_type == 'post_payment' ? 'credit-card' : 'user-edit') }} mr-1"></i>
                                {{ $typeLabels[$archive->archive_type] ?? ucfirst($archive->archive_type) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->deleted_at->format('M d, Y H:i') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $archive->deleted_at->diffForHumans() }}
                            </p>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $ageColor }}-rgb), 0.2); color: var(--{{ $ageColor }});">
                                {{ $ageYears }} year(s)
                            </span>
                            @if($ageYears >= 7)
                                <p class="text-xs mt-1" style="color: var(--danger);">Eligible for cleanup</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->deleted_by_name }}
                            </p>
                            @if($archive->deletion_reason)
                                <p class="text-xs" style="color: var(--text-secondary);" title="{{ $archive->deletion_reason }}">
                                    {{ Str::limit($archive->deletion_reason, 30) }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <button type="button" 
                                        onclick="viewArchiveDetails({{ $archive->id }})"
                                        class="p-2 rounded view-btn" 
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" 
                                        onclick="downloadArchivePDF({{ $archive->id }})"
                                        class="p-2 rounded download-btn" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Download PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                @if(auth()->user()->isSuperAdmin())
                                    <button type="button" 
                                            onclick="openSingleArchiveCleanup({{ $archive->id }}, '{{ $archive->invoice_number }}')"
                                            class="p-2 rounded delete-btn" 
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                            title="Cleanup">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-archive text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No archived invoices found</p>
                                <p class="text-sm">No invoices have been archived yet. Deleted invoices will appear here.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($archives->hasPages())
        <div class="flex justify-center mt-6">
            {{ $archives->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Archive Details Modal -->
<div id="archiveDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Archive Details</h3>
                <button type="button" onclick="closeArchiveDetailsModal()" class="text-2xl" style="color: var(--text-secondary);">&times;</button>
            </div>
            <div id="archiveDetailsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeArchiveDetailsModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Export Archives</h3>
            <form id="exportForm" method="GET" action="{{ route('invoices.archives.export-all') }}">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Export Format</label>
                        <select name="format" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="csv">CSV (Excel Compatible)</option>
                            <option value="json">JSON</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Apply Current Filters</label>
                        <div class="flex items-center">
                            <input type="checkbox" name="use_filters" id="use_filters" class="mr-2" value="1" checked>
                            <label for="use_filters" class="text-sm" style="color: var(--text-secondary);">Export only filtered results</label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Age Filter</label>
                        <select name="min_age" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Ages</option>
                            <option value="1">Older than 1 year</option>
                            <option value="2">Older than 2 years</option>
                            <option value="3">Older than 3 years</option>
                            <option value="5">Older than 5 years</option>
                            <option value="7">Older than 7 years</option>
                            <option value="10">Older than 10 years</option>
                        </select>
                    </div>
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        Note: Large exports may take a few moments to process.
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeExportModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cleanup Modal (Age-based) -->
@if(auth()->user()->isSuperAdmin())
<div id="cleanupModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Archive Cleanup by Age</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Permanently delete archived invoices older than the selected number of years. This action cannot be undone.
            </p>
            <form id="cleanupForm" method="POST" action="{{ route('invoices.archives.perform-cleanup') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Delete archives older than (years)</label>
                        <select name="years" id="cleanupYears" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="1">1 year</option>
                            <option value="2">2 years</option>
                            <option value="3">3 years</option>
                            <option value="5">5 years</option>
                            <option value="7" selected>7 years</option>
                            <option value="10">10 years</option>
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="exported" id="exported" class="mr-2" required>
                            <label for="exported" class="text-sm" style="color: var(--text-secondary);">
                                I have exported the data before deletion
                            </label>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="confirm" id="confirmCleanup" class="mr-2" required>
                            <label for="confirmCleanup" class="text-sm" style="color: var(--text-secondary);">
                                I understand that this action is irreversible
                            </label>
                        </div>
                    </div>
                    <div id="previewInfo" class="hidden p-3 rounded text-sm" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span id="previewText"></span>
                    </div>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Warning:</strong> This will permanently delete archived records. Ensure you have exported any data you need to retain.
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeCleanupModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" id="cleanupSubmitBtn" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Cleanup Archives
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Selection Cleanup Modal (Same as age-based but for selected items) -->
<div id="selectionCleanupModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Cleanup Selected Archives</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Permanently delete the selected archived invoices. This action cannot be undone.
            </p>
            <form id="selectionCleanupForm">
                @csrf
                <div class="space-y-4">
                    <div id="selectionPreviewInfo" class="p-3 rounded text-sm" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span id="selectionPreviewText">Loading selected items...</span>
                    </div>
                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="selection_exported" id="selectionExported" class="mr-2" required>
                            <label for="selectionExported" class="text-sm" style="color: var(--text-secondary);">
                                I have exported the data before deletion
                            </label>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="selection_confirm" id="selectionConfirm" class="mr-2" required>
                            <label for="selectionConfirm" class="text-sm" style="color: var(--text-secondary);">
                                I understand that this action is irreversible
                            </label>
                        </div>
                    </div>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Warning:</strong> This will permanently delete the selected archive records. Ensure you have exported any data you need to retain.
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeSelectionCleanupModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" id="selectionCleanupSubmitBtn" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Cleanup Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 flex flex-col items-center">
        <i class="fas fa-spinner fa-spin text-3xl mb-3" style="color: var(--primary);"></i>
        <p class="text-sm" style="color: var(--text-primary);">Processing...</p>
    </div>
</div>

@endsection

@section('scripts')
<script>
// CSRF Token setup
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
let selectedArchiveIds = [];

// Initialize checkbox handling
document.addEventListener('DOMContentLoaded', function() {
    initializeCheckboxes();
});

function initializeCheckboxes() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const archiveCheckboxes = document.querySelectorAll('.archive-checkbox');
    const selectionActions = document.getElementById('selectionActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedTotalSpan = document.getElementById('selectedTotal');

    // Function to update selection summary
    function updateSelectionSummary() {
        const checkedCheckboxes = document.querySelectorAll('.archive-checkbox:checked');
        const checkedCount = checkedCheckboxes.length;
        
        if (selectedCountSpan) selectedCountSpan.textContent = checkedCount;
        
        // Calculate total amount
        let totalAmount = 0;
        checkedCheckboxes.forEach(checkbox => {
            const amount = parseFloat(checkbox.dataset.amount) || 0;
            totalAmount += amount;
        });
        
        if (selectedTotalSpan) selectedTotalSpan.textContent = formatAmount(totalAmount);
        
        // Show/hide selection actions
        if (selectionActions) {
            if (checkedCount > 0) {
                selectionActions.classList.remove('hidden');
            } else {
                selectionActions.classList.add('hidden');
            }
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === archiveCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < archiveCheckboxes.length;
        }
        
        // Store selected IDs
        selectedArchiveIds = Array.from(checkedCheckboxes).map(cb => cb.value);
    }

    // Select All checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            archiveCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionSummary();
        });
    }

    // Individual checkboxes
    archiveCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionSummary);
    });

    // Initial update
    updateSelectionSummary();
}

function formatAmount(amount) {
    const currencySymbol = '{{ $system_settings->currency_symbol ?? "₵" }}';
    const decimalPlaces = {{ $system_settings->decimal_places ?? 2 }};
    const formattedAmount = parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: decimalPlaces,
        maximumFractionDigits: decimalPlaces
    });
    return currencySymbol + formattedAmount;
}

function clearSelection() {
    document.querySelectorAll('.archive-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    
    const selectionActions = document.getElementById('selectionActions');
    if (selectionActions) selectionActions.classList.add('hidden');
    
    selectedArchiveIds = [];
}

// ==================== AGE-BASED CLEANUP ====================

function openCleanupModal() {
    const modal = document.getElementById('cleanupModal');
    if (modal) modal.classList.remove('hidden');
    previewCleanup();
}

function closeCleanupModal() {
    const modal = document.getElementById('cleanupModal');
    if (modal) modal.classList.add('hidden');
}

function previewCleanup() {
    const yearsSelect = document.getElementById('cleanupYears');
    const years = yearsSelect ? yearsSelect.value : 7;
    const previewInfo = document.getElementById('previewInfo');
    const previewText = document.getElementById('previewText');
    
    if (!previewInfo || !previewText) return;
    
    previewInfo.classList.remove('hidden');
    previewText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading preview...';
    
    fetch(`/invoices/archives/preview-cleanup?years=${years}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.count === 0) {
                    previewText.innerHTML = `<i class="fas fa-info-circle"></i> No archives older than ${years} years found.`;
                    previewInfo.style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
                } else {
                    previewText.innerHTML = `<i class="fas fa-trash-alt"></i> This will permanently delete ${data.count} archive records totaling ${data.formatted_amount}.`;
                    previewInfo.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                }
            } else {
                previewText.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Failed to preview: ${data.message}`;
                previewInfo.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            }
        })
        .catch(error => {
            console.error('Preview error:', error);
            previewText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to preview cleanup. Please try again.';
            previewInfo.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
        });
}

// ==================== SELECTION-BASED CLEANUP ====================

function openSelectionCleanupModal() {
    if (selectedArchiveIds.length === 0) {
        alert('Please select at least one archive to cleanup.');
        return;
    }
    
    // Calculate total amount of selected items
    let totalAmount = 0;
    document.querySelectorAll('.archive-checkbox:checked').forEach(checkbox => {
        totalAmount += parseFloat(checkbox.dataset.amount) || 0;
    });
    
    // Update preview
    const selectionPreviewText = document.getElementById('selectionPreviewText');
    if (selectionPreviewText) {
        selectionPreviewText.innerHTML = `
            <i class="fas fa-check-circle mr-2"></i> 
            You have selected ${selectedArchiveIds.length} archive(s) totaling ${formatAmount(totalAmount)}.<br>
            <strong class="mt-2 block">This will permanently delete these records.</strong>
        `;
    }
    
    const modal = document.getElementById('selectionCleanupModal');
    if (modal) modal.classList.remove('hidden');
}

function closeSelectionCleanupModal() {
    const modal = document.getElementById('selectionCleanupModal');
    if (modal) modal.classList.add('hidden');
    
    // Reset form
    const selectionExported = document.getElementById('selectionExported');
    const selectionConfirm = document.getElementById('selectionConfirm');
    if (selectionExported) selectionExported.checked = false;
    if (selectionConfirm) selectionConfirm.checked = false;
}

// Handle selection cleanup form submission
document.getElementById('selectionCleanupForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const selectionExported = document.getElementById('selectionExported');
    const selectionConfirm = document.getElementById('selectionConfirm');
    
    if (!selectionExported?.checked) {
        alert('Please confirm that you have exported the data before deletion.');
        return;
    }
    
    if (!selectionConfirm?.checked) {
        alert('Please confirm that you understand this action is irreversible.');
        return;
    }
    
    if (selectedArchiveIds.length === 0) {
        alert('No archives selected for cleanup.');
        return;
    }
    
    // Calculate total amount
    let totalAmount = 0;
    document.querySelectorAll('.archive-checkbox:checked').forEach(checkbox => {
        totalAmount += parseFloat(checkbox.dataset.amount) || 0;
    });
    
    const confirmMessage = `⚠️ WARNING: This will permanently delete ${selectedArchiveIds.length} archive record(s) totaling ${formatAmount(totalAmount)}.\n\nThis action cannot be undone.\n\nType "DELETE ARCHIVES" to confirm.`;
    const userInput = prompt(confirmMessage);
    
    if (userInput === 'DELETE ARCHIVES') {
        performSelectionCleanup(selectedArchiveIds);
    }
});

function performSelectionCleanup(archiveIds) {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.remove('hidden');
    
    // Use the same cleanup endpoint but with archive_ids
    fetch('/invoices/archives/perform-cleanup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            archive_ids: archiveIds,
            confirm: true,
            exported: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
        
        if (data.success) {
            alert(`✅ Success: ${data.message}\n\nRecords deleted: ${data.records_deleted}\nTotal amount: ${data.formatted_amount}`);
            closeSelectionCleanupModal();
            window.location.reload();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    })
    .catch(error => {
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
        console.error('Cleanup error:', error);
        alert('Failed to perform cleanup: ' + error.message);
    });
}

function openSingleArchiveCleanup(archiveId, invoiceNumber) {
    const confirmMessage = `⚠️ WARNING: This will permanently delete archive record for invoice ${invoiceNumber}.\n\nThis action cannot be undone.\n\nType "DELETE" to confirm.`;
    const userInput = prompt(confirmMessage);
    
    if (userInput === 'DELETE') {
        performSelectionCleanup([archiveId]);
    }
}

// ==================== AGE-BASED CLEANUP FORM ====================

document.getElementById('cleanupForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const years = formData.get('years');
    const confirmCheckbox = document.getElementById('confirmCleanup');
    const exportedCheckbox = document.getElementById('exported');
    
    if (!confirmCheckbox?.checked) {
        alert('Please confirm that you understand this action is irreversible.');
        return;
    }
    
    if (!exportedCheckbox?.checked) {
        alert('Please confirm that you have exported the data before deletion.');
        return;
    }
    
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.remove('hidden');
    
    fetch('/invoices/archives/perform-cleanup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            years: parseInt(years),
            confirm: true,
            exported: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
        
        if (data.success) {
            alert(`✅ Success: ${data.message}\n\nRecords deleted: ${data.records_deleted}\nTotal amount: ${data.formatted_amount}`);
            closeCleanupModal();
            window.location.reload();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    })
    .catch(error => {
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
        console.error('Cleanup error:', error);
        alert('Failed to perform cleanup: ' + error.message);
    });
});

// Auto-refresh preview when years selection changes
document.getElementById('cleanupYears')?.addEventListener('change', function() {
    previewCleanup();
});

// ==================== OTHER FUNCTIONS ====================

// View archive details
function viewArchiveDetails(archiveId) {
    const modal = document.getElementById('archiveDetailsModal');
    const content = document.getElementById('archiveDetailsContent');
    
    if (modal) modal.classList.remove('hidden');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
        </div>
    `;
    
    fetch(`/invoices/archives/${archiveId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const archive = data.archive;
                const currencySymbol = data.currency_symbol || '₵';
                
                content.innerHTML = `
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Invoice Number</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.invoice_number}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Original Invoice ID</p>
                                <p class="font-semibold" style="color: var(--text-primary);">#${archive.id}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Period</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.month_name}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Due Date</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.due_date}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 p-3 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Property</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.property_name}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Landlord</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.landlord_name}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Amount</p>
                                <p class="font-semibold" style="color: var(--success);">${currencySymbol}${archive.amount}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Penalty</p>
                                <p class="font-semibold" style="color: var(--danger);">${currencySymbol}${archive.penalty_amount || 0}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Total</p>
                                <p class="font-semibold" style="color: var(--success);">${archive.formatted_total_amount}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 p-3 rounded" style="background-color: rgba(var(--danger-rgb), 0.05);">
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Deleted At</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.formatted_deleted_at}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Deleted By</p>
                                <p class="font-semibold" style="color: var(--text-primary);">${archive.deleted_by_name}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Deletion Reason</p>
                                <p class="text-sm" style="color: var(--text-primary);">${archive.deletion_reason || 'No reason provided'}</p>
                            </div>
                        </div>
                        
                        <div class="text-xs" style="color: var(--text-secondary);">
                            <p>Archive ID: ${archive.id}</p>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-3xl mb-3" style="color: var(--danger);"></i>
                        <p class="text-sm" style="color: var(--danger);">Failed to load archive details</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-3xl mb-3" style="color: var(--danger);"></i>
                    <p class="text-sm" style="color: var(--danger);">Error loading archive details</p>
                </div>
            `;
        });
}

function closeArchiveDetailsModal() {
    const modal = document.getElementById('archiveDetailsModal');
    if (modal) modal.classList.add('hidden');
}

function downloadArchivePDF(archiveId) {
    window.open(`/invoices/archives/${archiveId}/pdf`, '_blank');
}

function openExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) modal.classList.remove('hidden');
}

function closeExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) modal.classList.add('hidden');
}

// Handle export form with filters
document.getElementById('exportForm')?.addEventListener('submit', function(e) {
    const useFilters = document.getElementById('use_filters')?.checked;
    const url = new URL(this.action);
    
    if (useFilters) {
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.forEach((value, key) => {
            url.searchParams.append(key, value);
        });
    }
    
    const minAge = document.querySelector('[name="min_age"]')?.value;
    if (minAge) {
        url.searchParams.set('min_age', minAge);
    }
    
    const format = document.querySelector('[name="format"]')?.value;
    url.searchParams.set('format', format);
    
    this.action = url.toString();
});

// Auto-hide messages after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(el => {
        el.style.display = 'none';
    });
}, 5000);

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeArchiveDetailsModal();
        closeExportModal();
        closeCleanupModal();
        closeSelectionCleanupModal();
    }
});

// Close modals when clicking outside
document.getElementById('archiveDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeArchiveDetailsModal();
});
document.getElementById('exportModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});
document.getElementById('cleanupModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCleanupModal();
});
document.getElementById('selectionCleanupModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSelectionCleanupModal();
});
</script>

<style>
.btn-primary, .btn-secondary, .btn-success, .btn-warning, .btn-danger {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}
.btn-primary { background-color: var(--primary); color: white; }
.btn-secondary { background-color: var(--secondary); color: white; }
.btn-success { background-color: var(--success); color: white; }
.btn-warning { background-color: var(--warning); color: white; }
.btn-danger { background-color: var(--danger); color: white; }
.btn-primary:hover, .btn-secondary:hover, .btn-success:hover, .btn-warning:hover, .btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}
#selectionActions, #previewInfo, #selectionPreviewInfo { transition: all 0.3s ease; }
tbody tr:hover { background-color: rgba(var(--primary-rgb), 0.05); }
input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
.dark .bg-green-100 { background-color: rgba(16, 185, 129, 0.2) !important; color: #10b981 !important; }
.dark .bg-red-100 { background-color: rgba(239, 68, 68, 0.2) !important; color: #ef4444 !important; }
.dark .bg-yellow-100 { background-color: rgba(234, 179, 8, 0.2) !important; color: #eab308 !important; }
</style>
@endsection