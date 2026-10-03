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
                    <p class="text-sm" style="color: var(--text-secondary);">View permanently deleted invoices (audit trail)</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
                <a href="{{ route('admin.tenant-invoices.trash') }}" class="btn-info flex items-center">
                    <i class="fas fa-trash-restore mr-2"></i> Trash
                </a>
                @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.tenant-invoices.archive-cleanup') }}" class="btn-danger flex items-center">
                    <i class="fas fa-broom mr-2"></i> Archive Cleanup
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Info Message -->
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Archive Information:</strong>
                <span class="block sm:inline"> This section shows invoices that have been permanently deleted. These records are kept for audit and compliance purposes. They cannot be restored to active invoices.</span>
                @if(auth()->user()->isSuperAdmin())
                <br><span class="text-xs">Super Admin: Use the "Archive Cleanup" button to permanently delete old records.</span>
                @endif
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

    <!-- Advanced Search & Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('admin.tenant-invoices.archives') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Invoice Number</label>
                    <input type="text" name="invoice_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Enter invoice number..." value="{{ request('invoice_number') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Tenant Name</label>
                    <input type="text" name="tenant_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search by tenant name..." value="{{ request('tenant_name') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Period (Month/Year)</label>
                    <input type="month" name="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('period') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Deleted From Date</label>
                    <input type="date" name="deleted_from" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('deleted_from') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Deleted To Date</label>
                    <input type="date" name="deleted_to" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('deleted_to') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Deleted By</label>
                    <input type="text" name="deleted_by" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search by who deleted..." value="{{ request('deleted_by') }}">
                </div>
            </div>
            
            <div class="flex flex-wrap gap-2 justify-between items-center">
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 border rounded flex items-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                    <a href="{{ route('admin.tenant-invoices.archives') }}" class="px-4 py-2 border rounded flex items-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset Filters
                    </a>
                </div>
                
                <div class="flex gap-2">
                    @if(request()->hasAny(['invoice_number', 'tenant_name', 'period', 'status', 'deleted_from', 'deleted_to', 'deleted_by']))
                    <span class="text-sm px-3 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        <i class="fas fa-filter mr-1"></i> Filters Applied
                    </span>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Export Options Card -->
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <i class="fas fa-download text-xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Export Options</h3>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <!-- Bulk Selection Info -->
                <div id="selectionInfo" class="hidden px-3 py-2 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-check-circle mr-1"></i>
                    <span id="selectedCount">0</span> invoice(s) selected
                </div>
                
                <!-- Export Selected Button -->
                <button id="exportSelectedBtn" 
                        type="button" 
                        onclick="openExportModal(true)"
                        class="btn-success flex items-center space-x-2 hidden">
                    <i class="fas fa-file-export"></i>
                    <span>Export Selected</span>
                </button>
                
                <!-- Export All Button -->
                <button type="button" 
                        onclick="openExportModal(false)"
                        class="btn-secondary flex items-center space-x-2">
                    <i class="fas fa-file-export"></i>
                    <span>Export All</span>
                </button>
            </div>
        </div>
        
        <!-- Export Info Message -->
        <div class="mt-3 text-xs" style="color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-1"></i>
            Use the checkboxes to select specific invoices, or export all filtered results.
        </div>
    </div>

    <!-- Archives Table Card -->
    <div class="card p-6">
        <!-- Results Count -->
        <div class="mb-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing <span class="font-medium">{{ $archives->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $archives->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $archives->total() }}</span> archived invoices
            </p>
            
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Summary:</span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                    Total Archived: {{ $archives->total() }}
                </span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                    Total Amount: {{ $system_settings->formatAmount($archives->sum('total_amount')) }}
                </span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                    Total Penalties: {{ $system_settings->formatAmount($archives->sum('penalty_amount')) }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary); width: 40px;">
                            <input type="checkbox" id="selectAll" class="rounded" style="width: 18px; height: 18px;">
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Tenant</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property / Unit</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Deleted At</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Deleted By</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($archives as $archive)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3 text-center">
                            <input type="checkbox" class="archive-checkbox rounded" data-id="{{ $archive->id }}" style="width: 18px; height: 18px;">
                        </td>
                        <td class="p-3">
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->invoice_number }}
                            </span>
                            @if($archive->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $archive->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center mr-2">
                                    <span class="text-sm font-medium">{{ substr($archive->tenant_name ?? 'U', 0, 1) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $archive->tenant_name ?? 'Unknown' }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">ID: {{ $archive->tenant_id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->propertyUnit->property->property_name ?? 'N/A' }}
                            </p>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Unit: {{ $archive->propertyUnit->unit_number ?? 'N/A' }}
                            </p>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $archive->month_name }}
                            </p>
                        </td>
                        <td class="p-3">
                            <div class="space-y-1">
                                <p class="font-medium" style="color: var(--text-primary);">
                                    {{ $system_settings->formatAmount($archive->total_amount) }}
                                </p>
                                <div class="flex items-center text-xs space-x-2">
                                    <span style="color: var(--text-secondary);">CD: {{ $system_settings->formatAmount($archive->community_dues) }}</span>
                                    @if(($archive->additional_charges ?? 0) > 0)
                                    <span style="color: var(--warning);">+{{ $system_settings->formatAmount($archive->additional_charges) }}</span>
                                    @endif
                                    @if(($archive->penalty_amount ?? 0) > 0)
                                    <span style="color: var(--danger);">+{{ $system_settings->formatAmount($archive->penalty_amount) }} penalty</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                    'cancelled' => 'secondary'
                                ];
                                $statusColor = $statusColors[$archive->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="fas {{ $archive->status === 'paid' ? 'fa-check-circle' : ($archive->status === 'pending' ? 'fa-clock' : 'fa-exclamation-triangle') }} mr-1"></i>
                                {{ ucfirst($archive->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($archive->deleted_at)->format('M d, Y H:i') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($archive->deleted_at)->diffForHumans() }}
                            </p>
                        </td>
                        <td class="p-3">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $archive->deleted_by_name }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">ID: {{ $archive->deleted_by }}</p>
                                @if($archive->deletion_reason)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-comment mr-1"></i> {{ $archive->deletion_reason }}
                                </p>
                                @endif
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center space-x-2">
                                <button type="button" 
                                        onclick="viewArchiveDetails({{ $archive->id }})"
                                        class="p-2 rounded transition-opacity" 
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @if(auth()->user()->isSuperAdmin())
                                <button type="button" 
                                        onclick="exportSingleArchive({{ $archive->id }}, '{{ $archive->invoice_number }}')"
                                        class="p-2 rounded transition-opacity" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Export as PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-archive text-5xl mb-4 opacity-30"></i>
                                <p class="text-lg font-medium mb-2">No archived invoices found</p>
                                <p class="text-sm mb-4">Try adjusting your search filters.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($archives->hasPages())
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm" style="color: var(--text-secondary);">
                Page {{ $archives->currentPage() }} of {{ $archives->lastPage() }}
            </div>
            <div class="flex space-x-2">
                {{ $archives->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Export Format Modal -->
<div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <span id="exportModalTitle">Export Archives</span>
                </h3>
                <button type="button" onclick="closeExportModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <p class="text-sm" style="color: var(--text-secondary);" id="exportModalDescription">
                    Choose your preferred export format.
                </p>
                
                <div class="space-y-3">
                    <!-- PDF Option -->
                    <button onclick="exportArchives('pdf')" 
                            class="w-full p-4 border rounded-lg transition-colors flex items-center justify-between"
                            style="border-color: var(--border-color); background-color: var(--bg-primary);">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file-pdf text-2xl" style="color: var(--danger);"></i>
                            <div class="text-left">
                                <p class="font-medium" style="color: var(--text-primary);">PDF Format</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Portable Document Format - Print-ready, professional reports</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right text-sm" style="color: var(--text-secondary);"></i>
                    </button>
                    
                    <!-- CSV Option -->
                    <button onclick="exportArchives('csv')" 
                            class="w-full p-4 border rounded-lg transition-colors flex items-center justify-between"
                            style="border-color: var(--border-color); background-color: var(--bg-primary);">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file-csv text-2xl" style="color: var(--info);"></i>
                            <div class="text-left">
                                <p class="font-medium" style="color: var(--text-primary);">CSV Format</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Comma Separated Values - Compatible with Excel, Google Sheets</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right text-sm" style="color: var(--text-secondary);"></i>
                    </button>
                    
                    <!-- Excel Option -->
                    <button onclick="exportArchives('excel')" 
                            class="w-full p-4 border rounded-lg transition-colors flex items-center justify-between"
                            style="border-color: var(--border-color); background-color: var(--bg-primary);">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file-excel text-2xl" style="color: var(--success);"></i>
                            <div class="text-left">
                                <p class="font-medium" style="color: var(--text-primary);">Excel Format</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Microsoft Excel (.xlsx) with formatting</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right text-sm" style="color: var(--text-secondary);"></i>
                    </button>
                    
                    <!-- JSON Option -->
                    <button onclick="exportArchives('json')" 
                            class="w-full p-4 border rounded-lg transition-colors flex items-center justify-between"
                            style="border-color: var(--border-color); background-color: var(--bg-primary);">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file-code text-2xl" style="color: var(--warning);"></i>
                            <div class="text-left">
                                <p class="font-medium" style="color: var(--text-primary);">JSON Format</p>
                                <p class="text-xs" style="color: var(--text-secondary);">JavaScript Object Notation - For API integration</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right text-sm" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex justify-end mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="closeExportModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Archive Details Modal -->
<div id="archiveDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="modal-container" style="max-width: 900px; width: 100%; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="card overflow-hidden" style="display: flex; flex-direction: column; max-height: 90vh;">
            <div class="modal-header p-6 sticky top-0 z-10" style="background-color: var(--bg-primary); border-bottom: 1px solid var(--border-color);">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-archive text-xl" style="color: var(--info);"></i>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Archived Invoice Details</h3>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button id="exportSinglePDFBtn" 
                                class="p-2 rounded transition-opacity hidden"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                title="Export as PDF">
                            <i class="fas fa-file-pdf text-xl"></i>
                        </button>
                        <button type="button" onclick="closeArchiveModal()" class="modal-close-btn p-2 rounded transition-opacity" style="color: var(--text-secondary);">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="archiveDetailsContent" class="modal-content p-6 overflow-y-auto" style="flex: 1; overflow-y: auto; scroll-behavior: smooth;">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
                </div>
            </div>
            
            <div class="modal-footer p-6 pt-4 sticky bottom-0 z-10" style="background-color: var(--bg-primary); border-top: 1px solid var(--border-color);">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeArchiveModal()" class="px-4 py-2 border rounded-lg transition-opacity" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal scrollbar styling */
.modal-content {
    scrollbar-width: thin;
    scrollbar-color: var(--primary) var(--border-color);
}

.modal-content::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: var(--border-color);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

/* Modal container animation */
.modal-container {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Content sections spacing */
.modal-content > * + * {
    margin-top: 1rem;
}

/* Remove hover effects from modal content sections */
.modal-content .p-4.rounded-lg {
    transition: none !important;
}

.modal-content .p-4.rounded-lg:hover {
    background-color: var(--bg-secondary) !important;
    transform: none !important;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-success {
    background-color: var(--success);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-success:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.bg-blue-100 {
    background-color: rgba(219, 234, 254, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    table {
        font-size: 0.75rem;
    }
    
    .btn-primary,
    .btn-secondary,
    .btn-success,
    .btn-info,
    .btn-danger {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .modal-container {
        max-height: 95vh;
    }
    
    .modal-content {
        padding: 1rem;
    }
    
    .modal-header, .modal-footer {
        padding: 1rem;
    }
}
</style>

@endsection

@section('scripts')
<script>
let currentArchiveId = null;
let exportSelectedOnly = false;
let selectedArchiveIds = [];

// Checkbox selection handling
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.archive-checkbox');
    const selectionInfo = document.getElementById('selectionInfo');
    const exportSelectedBtn = document.getElementById('exportSelectedBtn');
    
    function updateSelectionUI() {
        const checkedBoxes = document.querySelectorAll('.archive-checkbox:checked');
        const count = checkedBoxes.length;
        selectedArchiveIds = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-id'));
        
        if (count > 0) {
            selectionInfo.classList.remove('hidden');
            exportSelectedBtn.classList.remove('hidden');
            document.getElementById('selectedCount').textContent = count;
        } else {
            selectionInfo.classList.add('hidden');
            exportSelectedBtn.classList.add('hidden');
        }
        
        // Update select all checkbox
        if (selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.archive-checkbox').length;
            selectAllCheckbox.checked = count === totalCheckboxes && totalCheckboxes > 0;
            selectAllCheckbox.indeterminate = count > 0 && count < totalCheckboxes;
        }
    }
    
    // Select All functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.archive-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelectionUI();
        });
    }
    
    // Individual checkbox change
    document.querySelectorAll('.archive-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectionUI);
    });
    
    updateSelectionUI();
});

function openExportModal(selectedOnly = false) {
    exportSelectedOnly = selectedOnly;
    const modal = document.getElementById('exportModal');
    const title = document.getElementById('exportModalTitle');
    const description = document.getElementById('exportModalDescription');
    
    if (selectedOnly) {
        const count = selectedArchiveIds.length;
        title.textContent = `Export Selected Invoices (${count})`;
        description.textContent = `You have selected ${count} invoice(s). Choose your preferred export format.`;
    } else {
        title.textContent = 'Export Archives';
        description.textContent = 'Choose your preferred export format. The export will include all currently filtered results.';
    }
    
    modal.classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
}

function exportArchives(format) {
    let params = new URLSearchParams();
    
    if (exportSelectedOnly && selectedArchiveIds.length > 0) {
        // Export selected invoices
        params.append('format', format);
        params.append('selected_ids', selectedArchiveIds.join(','));
    } else {
        // Export all filtered results
        const filters = {
            format: format,
            invoice_number: document.querySelector('input[name="invoice_number"]')?.value || '',
            tenant_name: document.querySelector('input[name="tenant_name"]')?.value || '',
            period: document.querySelector('input[name="period"]')?.value || '',
            status: document.querySelector('select[name="status"]')?.value || '',
            deleted_from: document.querySelector('input[name="deleted_from"]')?.value || '',
            deleted_to: document.querySelector('input[name="deleted_to"]')?.value || '',
            deleted_by: document.querySelector('input[name="deleted_by"]')?.value || ''
        };
        
        for (const [key, value] of Object.entries(filters)) {
            if (value) params.append(key, value);
        }
    }
    
    closeExportModal();
    
    // Redirect to export endpoint
    window.location.href = `/admin/tenant-invoices/archives/export-all?${params.toString()}`;
}

function exportSingleArchive(archiveId, invoiceNumber) {
    window.location.href = `/admin/tenant-invoices/archives/${archiveId}/export-pdf`;
}

function viewArchiveDetails(archiveId) {
    currentArchiveId = archiveId;
    const modal = document.getElementById('archiveDetailsModal');
    const content = document.getElementById('archiveDetailsContent');
    const exportBtn = document.getElementById('exportSinglePDFBtn');
    
    modal.classList.remove('hidden');
    
    if (exportBtn) {
        exportBtn.classList.remove('hidden');
        exportBtn.onclick = () => exportSingleArchive(archiveId, '');
    }
    
    content.scrollTop = 0;
    
    const styles = getComputedStyle(document.documentElement);
    const primaryColor = styles.getPropertyValue('--primary').trim();
    const successColor = styles.getPropertyValue('--success').trim();
    const warningColor = styles.getPropertyValue('--warning').trim();
    const dangerColor = styles.getPropertyValue('--danger').trim();
    const infoColor = styles.getPropertyValue('--info').trim();
    const textPrimary = styles.getPropertyValue('--text-primary').trim();
    const textSecondary = styles.getPropertyValue('--text-secondary').trim();
    const bgSecondary = styles.getPropertyValue('--bg-secondary').trim();
    const borderColor = styles.getPropertyValue('--border-color').trim();
    
    fetch(`/admin/tenant-invoices/archives/${archiveId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const archive = data.archive;
                const currencySymbol = data.currency_symbol || 'GH₵';
                const currencyPosition = data.currency_position || 'left';
                
                const formatAmount = (amount) => {
                    if (currencyPosition === 'left') {
                        return `${currencySymbol}${parseFloat(amount).toFixed(2)}`;
                    } else {
                        return `${parseFloat(amount).toFixed(2)}${currencySymbol}`;
                    }
                };
                
                content.innerHTML = `
                    <div class="space-y-4">
                        <div class="p-4 rounded-lg" style="background-color: ${bgSecondary}; border: 1px solid ${borderColor};">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-file-invoice mr-2" style="color: ${infoColor};"></i>
                                <h4 class="font-semibold" style="color: ${textPrimary};">Invoice Information</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" style="color: ${textSecondary};">
                                <p><strong style="color: ${textPrimary};">Invoice Number:</strong> ${archive.invoice_number}</p>
                                <p><strong style="color: ${textPrimary};">Period:</strong> ${archive.month_name}</p>
                                <p><strong style="color: ${textPrimary};">Original Issue Date:</strong> ${archive.formatted_original_created_at}</p>
                                <p><strong style="color: ${textPrimary};">Due Date:</strong> ${archive.due_date}</p>
                                <p><strong style="color: ${textPrimary};">Status:</strong> 
                                    <span class="px-2 py-1 rounded-full text-xs" style="background: ${archive.status === 'paid' ? `rgba(${parseInt(successColor.slice(1,3), 16)}, ${parseInt(successColor.slice(3,5), 16)}, ${parseInt(successColor.slice(5,7), 16)}, 0.2)` : archive.status === 'pending' ? `rgba(${parseInt(warningColor.slice(1,3), 16)}, ${parseInt(warningColor.slice(3,5), 16)}, ${parseInt(warningColor.slice(5,7), 16)}, 0.2)` : `rgba(${parseInt(dangerColor.slice(1,3), 16)}, ${parseInt(dangerColor.slice(3,5), 16)}, ${parseInt(dangerColor.slice(5,7), 16)}, 0.2)`}; color: ${archive.status === 'paid' ? successColor : archive.status === 'pending' ? warningColor : dangerColor};">
                                        ${archive.status.toUpperCase()}
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="p-4 rounded-lg" style="background-color: ${bgSecondary}; border: 1px solid ${borderColor};">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-user mr-2" style="color: ${infoColor};"></i>
                                <h4 class="font-semibold" style="color: ${textPrimary};">Tenant Information</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" style="color: ${textSecondary};">
                                <p><strong style="color: ${textPrimary};">Name:</strong> ${archive.tenant_name}</p>
                                <p><strong style="color: ${textPrimary};">Tenant ID:</strong> ${archive.tenant_id}</p>
                                <p><strong style="color: ${textPrimary};">Property:</strong> ${archive.property_name || 'N/A'}</p>
                                <p><strong style="color: ${textPrimary};">Unit:</strong> ${archive.unit_number || 'N/A'}</p>
                            </div>
                        </div>
                        
                        <div class="p-4 rounded-lg" style="background-color: ${bgSecondary}; border: 1px solid ${borderColor};">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-money-bill-wave mr-2" style="color: ${infoColor};"></i>
                                <h4 class="font-semibold" style="color: ${textPrimary};">Amount Breakdown</h4>
                            </div>
                            <div class="space-y-2" style="color: ${textSecondary};">
                                <div class="flex justify-between py-1"><span><strong style="color: ${textPrimary};">Community Dues:</strong></span><span>${formatAmount(archive.community_dues)}</span></div>
                                ${archive.additional_charges > 0 ? `<div class="flex justify-between py-1"><span><strong style="color: ${textPrimary};">Additional Charges:</strong></span><span style="color: ${warningColor};">${formatAmount(archive.additional_charges)}</span></div>` : ''}
                                ${archive.penalty_amount > 0 ? `<div class="flex justify-between py-1"><span><strong style="color: ${textPrimary};">Penalty:</strong></span><span style="color: ${dangerColor};">${formatAmount(archive.penalty_amount)}</span></div>` : ''}
                                <div class="flex justify-between py-2 mt-2 pt-2 border-t" style="border-color: ${borderColor};"><span class="font-bold" style="color: ${textPrimary};">Total Amount:</span><span class="font-bold" style="color: ${primaryColor}; font-size: 1.1rem;">${formatAmount(archive.total_amount)}</span></div>
                                ${archive.paid_amount > 0 ? `<div class="flex justify-between py-1"><span><strong style="color: ${textPrimary};">Amount Paid:</strong></span><span style="color: ${successColor};">${formatAmount(archive.paid_amount)}</span></div>` : ''}
                                ${archive.balance > 0 ? `<div class="flex justify-between py-1"><span><strong style="color: ${textPrimary};">Balance:</strong></span><span style="color: ${dangerColor};">${formatAmount(archive.balance)}</span></div>` : ''}
                            </div>
                        </div>
                        
                        <div class="p-4 rounded-lg" style="background-color: ${bgSecondary}; border: 1px solid ${borderColor};">
                            <div class="flex items-center mb-3"><i class="fas fa-trash-alt mr-2" style="color: ${dangerColor};"></i><h4 class="font-semibold" style="color: ${textPrimary};">Deletion Information</h4></div>
                            <div class="space-y-2" style="color: ${textSecondary};">
                                <p><strong style="color: ${textPrimary};">Deleted At:</strong> ${archive.formatted_deleted_at}</p>
                                <p><strong style="color: ${textPrimary};">Deleted By:</strong> ${archive.deleted_by_name}</p>
                                ${archive.deletion_reason ? `<p><strong style="color: ${textPrimary};">Reason:</strong> ${archive.deletion_reason}</p>` : ''}
                                ${archive.deletion_ip ? `<p><strong style="color: ${textPrimary};">IP Address:</strong> ${archive.deletion_ip}</p>` : ''}
                            </div>
                        </div>
                        
                        ${archive.calculation_details ? `
                        <div class="p-4 rounded-lg" style="background-color: ${bgSecondary}; border: 1px solid ${borderColor};">
                            <div class="flex items-center mb-3"><i class="fas fa-calculator mr-2" style="color: ${infoColor};"></i><h4 class="font-semibold" style="color: ${textPrimary};">Calculation Details</h4></div>
                            <pre class="text-xs overflow-x-auto p-2 rounded" style="background-color: var(--bg-tertiary); color: ${textSecondary}; font-family: monospace; max-height: 200px; overflow-y: auto;">${JSON.stringify(archive.calculation_details, null, 2)}</pre>
                        </div>
                        ` : ''}
                        
                        ${archive.metadata ? `
                        <div class="p-4 rounded-lg" style="background-color: ${bgSecondary}; border: 1px solid ${borderColor};">
                            <div class="flex items-center mb-3"><i class="fas fa-database mr-2" style="color: ${infoColor};"></i><h4 class="font-semibold" style="color: ${textPrimary};">Metadata</h4></div>
                            <pre class="text-xs overflow-x-auto p-2 rounded" style="background-color: var(--bg-tertiary); color: ${textSecondary}; font-family: monospace; max-height: 200px; overflow-y: auto;">${JSON.stringify(archive.metadata, null, 2)}</pre>
                        </div>
                        ` : ''}
                    </div>
                `;
                content.scrollTop = 0;
            } else {
                content.innerHTML = `<div class="text-center py-8"><i class="fas fa-exclamation-circle text-4xl mb-2" style="color: ${dangerColor};"></i><p style="color: ${textSecondary};">Failed to load archive details.</p><p class="text-sm">${data.message || 'Please try again.'}</p></div>`;
            }
        })
        .catch(error => {
            const dangerColor = getComputedStyle(document.documentElement).getPropertyValue('--danger').trim();
            const textSecondary = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim();
            content.innerHTML = `<div class="text-center py-8"><i class="fas fa-exclamation-circle text-4xl mb-2" style="color: ${dangerColor};"></i><p style="color: ${textSecondary};">An error occurred while loading details.</p></div>`;
        });
}

function closeArchiveModal() {
    const modal = document.getElementById('archiveDetailsModal');
    const content = document.getElementById('archiveDetailsContent');
    const exportBtn = document.getElementById('exportSinglePDFBtn');
    
    modal.classList.add('hidden');
    if (exportBtn) exportBtn.classList.add('hidden');
    content.innerHTML = `<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i><p class="mt-2" style="color: var(--text-secondary);">Loading details...</p></div>`;
    currentArchiveId = null;
}

// Close modals when clicking outside
document.getElementById('exportModal')?.addEventListener('click', function(e) { if (e.target === this) closeExportModal(); });
document.getElementById('archiveDetailsModal')?.addEventListener('click', function(e) { if (e.target === this) closeArchiveModal(); });

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const exportModal = document.getElementById('exportModal');
        if (exportModal && !exportModal.classList.contains('hidden')) closeExportModal();
        const archiveModal = document.getElementById('archiveDetailsModal');
        if (archiveModal && !archiveModal.classList.contains('hidden')) closeArchiveModal();
    }
});

// Prevent modal content click from closing modals
document.querySelector('#exportModal .card')?.addEventListener('click', function(e) { e.stopPropagation(); });
document.querySelector('#archiveDetailsModal .card')?.addEventListener('click', function(e) { e.stopPropagation(); });
</script>
@endsection