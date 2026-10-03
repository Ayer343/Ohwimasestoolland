@extends('layouts.app')

@section('title', 'Unpaid Invoices from Previous Years')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-clock text-2xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Unpaid Invoices from Previous Years</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Manage unpaid invoices that were kept active during year-end archiving</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.year-end.management') }}" class="btn-info flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i> Year-End Archive
                </a>
                <a href="{{ route('invoices.archives') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-archive mr-2"></i> Archives
                </a>
                <a href="{{ route('invoices.index') }}" class="btn-primary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
                @if($invoices->total() > 0)
                    <button type="button" onclick="openBulkReminderModal()" class="btn-warning flex items-center">
                        <i class="fas fa-bell mr-2"></i> Bulk Reminders
                    </button>
                    <button type="button" onclick="exportUnpaidInvoices()" class="btn-success flex items-center">
                        <i class="fas fa-download mr-2"></i> Export All
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.05); border-left: 4px solid var(--warning);">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="font-semibold" style="color: var(--text-primary);">About Unpaid Invoices</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    These invoices were kept active during year-end archiving because they were unpaid at that time.
                    They remain active for payment collection and will be archived automatically once paid and after the retention period.
                </p>
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-file-invoice mr-1"></i> Total Unpaid: 
                        <strong style="color: var(--warning);">{{ number_format($invoices->total()) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-money-bill-wave mr-1"></i> Total Outstanding: 
                        <strong style="color: var(--danger);">{{ $system_settings->formatAmount($totalOutstanding) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-1"></i> Unique Properties: 
                        <strong style="color: var(--info);">{{ number_format($uniqueProperties) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-alt mr-1"></i> Oldest Year: 
                        <strong style="color: var(--info);">{{ $oldestYear ?? 'N/A' }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i> Retention Period: 
                        <strong style="color: var(--info);">{{ $retentionMonths }} months</strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Unpaid</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ number_format($invoices->total()) }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Outstanding</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ $system_settings->formatAmount($totalOutstanding) }}</div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Unique Properties</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ number_format($uniqueProperties) }}</div>
                </div>
                <i class="fas fa-building text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Avg. per Invoice</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">
                        {{ $invoices->total() > 0 ? $system_settings->formatAmount($totalOutstanding / $invoices->total()) : $system_settings->formatAmount(0) }}
                    </div>
                </div>
                <i class="fas fa-chart-line text-2xl opacity-70" style="color: var(--success);"></i>
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
        <form method="GET" action="{{ route('invoices.unpaid-previous-years') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <select name="year" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Years</option>
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="property_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                {{ $property->house_number }} {{ $property->street_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                    </select>
                </div>
                <div>
                    <input type="text" name="search" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search invoice #, property..." value="{{ request('search') }}">
                </div>
                <div class="flex space-x-2 col-span-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('invoices.unpaid-previous-years') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Selection Actions Bar -->
    <div id="selectionActions" class="hidden card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <span class="text-sm font-medium" style="color: var(--text-primary);">
                    <span id="selectedCount">0</span> invoice(s) selected
                </span>
                <span class="text-sm ml-2" style="color: var(--text-secondary);">
                    Total: <span id="selectedTotal">{{ $system_settings->formatAmount(0) }}</span>
                </span>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="sendSelectedReminders()" class="px-3 py-2 rounded text-sm flex items-center" style="background-color: var(--warning); color: white;">
                    <i class="fas fa-bell mr-1"></i> Send Reminders
                </button>
                <button type="button" onclick="exportSelectedInvoices()" class="px-3 py-2 rounded text-sm flex items-center" style="background-color: var(--success); color: white;">
                    <i class="fas fa-download mr-1"></i> Export Selected
                </button>
                <button type="button" onclick="clearSelection()" class="px-3 py-2 rounded text-sm" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-times mr-1"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Invoices Table Card -->
    <div class="card p-6">
        <!-- Results Count -->
        <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing <span id="showingFrom">{{ $invoices->firstItem() ?? 0 }}</span> to <span id="showingTo">{{ $invoices->lastItem() ?? 0 }}</span> of <span id="showingTotal">{{ $invoices->total() }}</span> results
                </p>
                <div class="flex items-center">
                    <input type="checkbox" id="selectAllCheckbox" class="mr-2">
                    <label for="selectAllCheckbox" class="text-sm" style="color: var(--text-secondary);">Select All</label>
                </div>
            </div>
            
            @if(request()->hasAny(['year', 'property_id', 'status', 'search']))
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                @if(request('year'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Year: {{ request('year') }}
                </span>
                @endif
                @if(request('property_id'))
                @php
                    $selectedProperty = $properties->firstWhere('id', request('property_id'));
                @endphp
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Property: {{ $selectedProperty ? $selectedProperty->house_number . ' ' . $selectedProperty->street_name : 'N/A' }}
                </span>
                @endif
                @if(request('status'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Status: {{ ucfirst(request('status')) }}
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
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Penalty</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Total</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Original Year</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                    @php
                        $daysOverdue = $invoice->due_date < now() ? $invoice->due_date->diffInDays(now()) : 0;
                        $ageClass = $daysOverdue > 90 ? 'danger' : ($daysOverdue > 30 ? 'warning' : 'secondary');
                    @endphp
                    <tr class="border-b hover:bg-opacity-5" style="border-color: var(--border-color);" 
                        data-invoice-id="{{ $invoice->id }}"
                        data-invoice-number="{{ $invoice->invoice_number }}"
                        data-amount="{{ $invoice->total_amount }}"
                        data-days-overdue="{{ $daysOverdue }}">
                        
                        <td class="p-3">
                            <input type="checkbox" class="invoice-checkbox" value="{{ $invoice->id }}" 
                                   data-amount="{{ $invoice->total_amount }}"
                                   data-days-overdue="{{ $daysOverdue }}">
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                @if($invoice->is_bulk_payment)
                                    <i class="fas fa-layer-group mr-1 text-xs" style="color: var(--info);"></i>
                                @endif
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $invoice->invoice_number }}
                                </span>
                            </div>
                            @if($invoice->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->property->property_name ?? $invoice->property->street_name }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                            </p>
                            @if($invoice->property->block_number)
                                <p class="text-xs" style="color: var(--text-secondary);">Block: {{ $invoice->property->block_number }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->property->landlord->name ?? 'Unknown' }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $invoice->property->landlord->email ?? '' }}
                            </p>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->period ? \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y') : 'N/A' }}
                            </p>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->due_date->format('M d, Y') }}
                            </p>
                            @if($daysOverdue > 0)
                                <p class="text-xs" style="color: var(--danger);">
                                    Overdue by {{ $daysOverdue }} days
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--success);">
                                {{ $system_settings->formatAmount($invoice->amount) }}
                            </p>
                        </td>
                        <td class="p-3">
                            @if($invoice->penalty_amount > 0)
                                <p class="font-medium" style="color: var(--danger);">
                                    {{ $system_settings->formatAmount($invoice->penalty_amount) }}
                                </p>
                            @else
                                <p class="text-sm" style="color: var(--text-secondary);">-</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-bold" style="color: var(--primary);">
                                {{ $system_settings->formatAmount($invoice->total_amount) }}
                            </p>
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                    'partial' => 'info'
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                {{ $invoice->original_year ?? $invoice->created_at->year }}
                            </span>
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <button type="button" 
                                        onclick="viewInvoiceDetails({{ $invoice->id }})"
                                        class="p-2 rounded view-btn" 
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" 
                                        onclick="sendReminder({{ $invoice->id }}, '{{ $invoice->invoice_number }}')"
                                        class="p-2 rounded reminder-btn" 
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" 
                                        title="Send Reminder">
                                    <i class="fas fa-bell"></i>
                                </button>
                                <a href="{{ route('invoices.export-pdf', $invoice->id) }}" 
                                        class="p-2 rounded download-btn" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Download PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No unpaid invoices found</p>
                                <p class="text-sm">All invoices from previous years have been paid or archived.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="flex justify-center mt-6">
            {{ $invoices->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Invoice Details Modal -->
<div id="invoiceDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Invoice Details</h3>
                <button type="button" onclick="closeInvoiceDetailsModal()" class="text-2xl" style="color: var(--text-secondary);">&times;</button>
            </div>
            <div id="invoiceDetailsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeInvoiceDetailsModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reminder Modal -->
<div id="bulkReminderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Send Bulk Reminders</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Send payment reminders to all selected landlords with unpaid invoices.
            </p>
            <div id="bulkReminderPreview" class="mb-4 p-3 rounded text-sm" style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-info-circle mr-2"></i>
                <span id="bulkReminderText">Loading selection...</span>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeBulkReminderModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Cancel
                </button>
                <button type="button" onclick="confirmBulkReminders()" class="px-4 py-2 rounded text-white" style="background-color: var(--warning);">
                    <i class="fas fa-bell mr-2"></i> Send Reminders
                </button>
            </div>
        </div>
    </div>
</div>

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
// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
let selectedInvoiceIds = [];

// Initialize checkbox handling
document.addEventListener('DOMContentLoaded', function() {
    initializeCheckboxes();
});

function initializeCheckboxes() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const selectionActions = document.getElementById('selectionActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedTotalSpan = document.getElementById('selectedTotal');

    // Function to update selection summary
    function updateSelectionSummary() {
        const checkedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
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
            selectAllCheckbox.checked = checkedCount === invoiceCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < invoiceCheckboxes.length;
        }
        
        // Store selected IDs
        selectedInvoiceIds = Array.from(checkedCheckboxes).map(cb => cb.value);
    }

    // Select All checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionSummary();
        });
    }

    // Individual checkboxes
    invoiceCheckboxes.forEach(checkbox => {
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
    document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    
    const selectionActions = document.getElementById('selectionActions');
    if (selectionActions) selectionActions.classList.add('hidden');
    
    selectedInvoiceIds = [];
}

function sendReminder(invoiceId, invoiceNumber) {
    if (confirm(`Send payment reminder for invoice ${invoiceNumber}?`)) {
        const loadingOverlay = document.getElementById('loadingOverlay');
        loadingOverlay.classList.remove('hidden');
        
        fetch(`/invoices/send-reminder/${invoiceId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            loadingOverlay.classList.add('hidden');
            if (data.success) {
                alert(`✅ Reminder sent successfully for invoice ${invoiceNumber}`);
            } else {
                alert(`❌ Failed to send reminder: ${data.message}`);
            }
        })
        .catch(error => {
            loadingOverlay.classList.add('hidden');
            console.error('Error:', error);
            alert('Failed to send reminder. Please try again.');
        });
    }
}

function sendSelectedReminders() {
    if (selectedInvoiceIds.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }
    
    // Update preview
    const totalAmount = selectedInvoiceIds.reduce((sum, id) => {
        const checkbox = document.querySelector(`.invoice-checkbox[value="${id}"]`);
        return sum + (parseFloat(checkbox?.dataset.amount) || 0);
    }, 0);
    
    const previewText = document.getElementById('bulkReminderText');
    previewText.innerHTML = `You are about to send reminders for ${selectedInvoiceIds.length} invoice(s) totaling ${formatAmount(totalAmount)}.`;
    
    openBulkReminderModal();
}

function confirmBulkReminders() {
    closeBulkReminderModal();
    
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.remove('hidden');
    
    fetch('/invoices/bulk-send-reminders', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ invoice_ids: selectedInvoiceIds })
    })
    .then(response => response.json())
    .then(data => {
        loadingOverlay.classList.add('hidden');
        if (data.success) {
            alert(`✅ Reminders sent!\n\nSent: ${data.sent_count}\nFailed: ${data.failed_count}`);
        } else {
            alert(`❌ Failed to send reminders: ${data.message}`);
        }
    })
    .catch(error => {
        loadingOverlay.classList.add('hidden');
        console.error('Error:', error);
        alert('Failed to send reminders. Please try again.');
    });
}

function exportUnpaidInvoices() {
    window.location.href = '{{ route("invoices.export-unpaid") }}' + window.location.search;
}

function exportSelectedInvoices() {
    if (selectedInvoiceIds.length === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("invoices.export-selected") }}';
    form.target = '_blank';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    
    const idsInput = document.createElement('input');
    idsInput.type = 'hidden';
    idsInput.name = 'ids';
    idsInput.value = selectedInvoiceIds.join(',');
    form.appendChild(idsInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function viewInvoiceDetails(invoiceId) {
    const modal = document.getElementById('invoiceDetailsModal');
    const content = document.getElementById('invoiceDetailsContent');
    
    modal.classList.remove('hidden');
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
        </div>
    `;
    
    fetch(`/invoices/${invoiceId}`, {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const invoice = data.invoice;
            const currencySymbol = '{{ $system_settings->currency_symbol ?? "₵" }}';
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Invoice Number</p>
                            <p class="font-semibold" style="color: var(--text-primary);">${invoice.invoice_number}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Period</p>
                            <p class="font-semibold" style="color: var(--text-primary);">${invoice.period_name}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Due Date</p>
                            <p class="font-semibold" style="color: var(--text-primary);">${invoice.due_date}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Status</p>
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--${invoice.status_color}-rgb), 0.2); color: var(--${invoice.status_color});">
                                ${invoice.status}
                            </span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 p-3 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Property</p>
                            <p class="font-semibold" style="color: var(--text-primary);">${invoice.property_name}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">${invoice.property_address}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Landlord</p>
                            <p class="font-semibold" style="color: var(--text-primary);">${invoice.landlord_name}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">${invoice.landlord_email || ''}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Amount</p>
                            <p class="font-semibold" style="color: var(--success);">${currencySymbol}${invoice.amount}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Penalty</p>
                            <p class="font-semibold" style="color: var(--danger);">${currencySymbol}${invoice.penalty_amount || 0}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Total</p>
                            <p class="font-semibold" style="color: var(--primary);">${currencySymbol}${invoice.total_amount}</p>
                        </div>
                    </div>
                    
                    ${invoice.notes ? `
                    <div class="p-3 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Notes</p>
                        <p class="text-sm" style="color: var(--text-primary);">${invoice.notes}</p>
                    </div>
                    ` : ''}
                    
                    <div class="text-xs" style="color: var(--text-secondary);">
                        <p>Original Year: ${invoice.original_year || invoice.created_year}</p>
                        <p>Created: ${invoice.created_at}</p>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-3xl mb-3" style="color: var(--danger);"></i>
                    <p class="text-sm" style="color: var(--danger);">Failed to load invoice details</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-3xl mb-3" style="color: var(--danger);"></i>
                <p class="text-sm" style="color: var(--danger);">Error loading invoice details</p>
            </div>
        `;
    });
}

function closeInvoiceDetailsModal() {
    const modal = document.getElementById('invoiceDetailsModal');
    modal.classList.add('hidden');
}

function openBulkReminderModal() {
    const modal = document.getElementById('bulkReminderModal');
    modal.classList.remove('hidden');
}

function closeBulkReminderModal() {
    const modal = document.getElementById('bulkReminderModal');
    modal.classList.add('hidden');
}

// Auto-hide messages after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(el => {
        el.style.display = 'none';
    });
}, 5000);

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeInvoiceDetailsModal();
        closeBulkReminderModal();
    }
});

// Close modals when clicking outside
document.getElementById('invoiceDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeInvoiceDetailsModal();
    }
});

document.getElementById('bulkReminderModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBulkReminderModal();
    }
});
</script>

<style>
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
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
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
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
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
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-warning {
    background-color: var(--warning);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-warning:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Modal animations */
#invoiceDetailsModal,
#bulkReminderModal {
    transition: opacity 0.3s ease;
}

#invoiceDetailsModal.hidden,
#bulkReminderModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#invoiceDetailsModal:not(.hidden),
#bulkReminderModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Table row hover effect */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
    transition: background-color 0.2s;
}

/* Checkbox styling */
input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Selection actions bar */
#selectionActions {
    transition: all 0.3s ease;
}

/* Dark mode adjustments */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    color: #ef4444 !important;
}

.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
    color: #eab308 !important;
}
</style>
@endsection