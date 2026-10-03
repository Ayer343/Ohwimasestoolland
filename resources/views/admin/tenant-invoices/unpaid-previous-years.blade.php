@extends('layouts.app')

@section('title', 'Unpaid Invoices from Previous Years')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-exclamation-triangle text-2xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Unpaid Invoices from Previous Years</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">These invoices were kept active after year-end archiving and still require payment</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tenant-invoices.year-end-management') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Archive Management
                </a>
                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-info flex items-center">
                    <i class="fas fa-file-invoice mr-2"></i> View All Invoices
                </a>
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Unpaid Invoices</div>
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
        
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">From Year</div>
                    <div class="text-2xl font-semibold" style="color: var(--primary);">{{ $oldestYear ?? 'N/A' }}</div>
                </div>
                <i class="fas fa-calendar-alt text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Affected Tenants</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ number_format($uniqueTenants) }}</div>
                </div>
                <i class="fas fa-users text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
            <div>
                <p class="font-medium" style="color: var(--text-primary);">About These Invoices</p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    These invoices are from previous years that were kept active after year-end archiving because they were unpaid at the time.
                    They remain in the system and tenants can still pay them. Once paid, they will be automatically archived after 
                    the {{ $retentionMonths ?? 3 }}-month retention period.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('admin.tenant-invoices.unpaid-previous-years') }}">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <select name="year" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Years</option>
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="tenant_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Tenants</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->id }}" {{ request('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                {{ $tenant->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
                </div>
                <div>
                    <input type="text" name="search" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search invoice # or tenant..." value="{{ request('search') }}">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('admin.tenant-invoices.unpaid-previous-years') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Unpaid Invoices Table -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Unpaid Invoices</h3>
            <div class="flex items-center space-x-2">
                <button onclick="exportToCSV()" class="px-3 py-1 rounded text-sm" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </button>
                <button onclick="sendBulkReminders()" class="px-3 py-1 rounded text-sm" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                    <i class="fas fa-bell mr-1"></i> Send Reminders
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="p-3 text-left">
                            <input type="checkbox" id="selectAll" class="rounded" style="accent-color: var(--primary);">
                        </th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Tenant</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Original Year</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Balance</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr class="border-b hover:bg-opacity-5" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <input type="checkbox" class="invoice-checkbox rounded" value="{{ $invoice->id }}" style="accent-color: var(--primary);">
                        </td>
                        <td class="p-3">
                            <span class="font-medium" style="color: var(--text-primary);">#{{ $invoice->invoice_number }}</span>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2" style="background-color: rgba(var(--warning-rgb), 0.2);">
                                    <span class="text-sm font-medium" style="color: var(--warning);">{{ substr($invoice->tenant->name ?? 'U', 0, 1) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->tenant->name ?? 'Unknown' }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $invoice->tenant->email ?? 'No email' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->month_name }}</p>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                {{ $invoice->original_year ?? $invoice->created_at->year }}
                            </span>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->due_date->format('M d, Y') }}</p>
                            @if($invoice->isOverdue())
                            <p class="text-xs text-danger flex items-center mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $invoice->due_date->diffForHumans() }}
                            </p>
                            @endif
                            @if($invoice->within_grace_period)
                            <p class="text-xs" style="color: var(--warning);">
                                <i class="fas fa-hourglass-half mr-1"></i> Grace: {{ $invoice->days_in_grace }}d left
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $system_settings->formatAmount($invoice->total_amount) }}</p>
                            @if($invoice->penalty_amount > 0)
                            <p class="text-xs text-danger">+ Penalty: {{ $system_settings->formatAmount($invoice->penalty_amount) }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--danger);">{{ $system_settings->formatAmount($invoice->balance) }}</p>
                            @php
                                $paymentPercentage = $invoice->total_amount > 0 ? round(($invoice->paid_amount / $invoice->total_amount) * 100, 1) : 0;
                            @endphp
                            @if($paymentPercentage > 0)
                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                <div class="bg-success h-1.5 rounded-full" style="width: {{ $paymentPercentage }}%"></div>
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $paymentPercentage }}% paid</p>
                            @endif
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="{{ $invoice->status_icon }} mr-1"></i>
                                {{ ucfirst($invoice->status) }}
                            </span>
                            @if($invoice->penalty_amount > 0)
                            <div class="mt-1">
                                <span class="text-xs" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Penalty applied
                                </span>
                            </div>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex items-center space-x-2">
                                <a href="{{ route('admin.tenant-invoices.show', $invoice->id) }}" 
                                   class="p-2 rounded hover:opacity-80 transition-opacity" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" 
                                        onclick="openMarkAsPaidModal({{ $invoice->id }}, '{{ $invoice->total_amount }}')"
                                        class="p-2 rounded hover:opacity-80 transition-opacity" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Mark as Paid">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" 
                                        onclick="sendReminder({{ $invoice->id }})"
                                        class="p-2 rounded hover:opacity-80 transition-opacity" 
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" 
                                        title="Send Reminder">
                                    <i class="fas fa-bell"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle text-5xl mb-4 opacity-30"></i>
                                <p class="text-lg font-medium mb-2">No unpaid invoices from previous years</p>
                                <p class="text-sm mb-4">All invoices from previous years have been paid or archived.</p>
                                <a href="{{ route('admin.tenant-invoices.year-end-management') }}" class="btn-primary flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Archive Management
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $invoices->firstItem() ?? 0 }} to {{ $invoices->lastItem() ?? 0 }} of {{ $invoices->total() }} results
            </div>
            <div class="flex space-x-2">
                {{ $invoices->appends(request()->query())->links() }}
            </div>
        </div>
        @endif

        <!-- Bulk Actions -->
        @if($invoices->count() > 0)
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <span class="text-sm" style="color: var(--text-secondary);">Selected: <span id="selectedCount">0</span> invoices</span>
                    <button onclick="bulkSendReminders()" id="bulkReminderBtn" class="px-3 py-1 rounded text-sm disabled:opacity-50" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);" disabled>
                        <i class="fas fa-bell mr-1"></i> Send Reminders
                    </button>
                    <button onclick="bulkExport()" id="bulkExportBtn" class="px-3 py-1 rounded text-sm disabled:opacity-50" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);" disabled>
                        <i class="fas fa-download mr-1"></i> Export Selected
                    </button>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Total Selected Amount: <span id="selectedTotalAmount">₵0.00</span>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markAsPaidModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Mark Invoice as Paid</h3>
                <button type="button" onclick="closeMarkAsPaidModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="markAsPaidForm" method="POST">
                @csrf
                <div class="space-y-4">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Note:</strong> Only full payments are accepted. The payment amount must equal the invoice total.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Invoice Total</label>
                        <div class="w-full p-2 border rounded bg-gray-100" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <span id="invoiceTotalDisplay" class="font-bold"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Method</label>
                        <select name="payment_method" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Payment Method</option>
                            <option value="mtn_momo">MTN Mobile Money</option>
                            <option value="telecel_cash">Telecel Cash</option>
                            <option value="airteltigo_cash">AirtelTigo Cash</option>
                            <option value="paystack">Paystack</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Reference</label>
                        <input type="text" name="payment_reference" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="Transaction ID or reference">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Date</label>
                        <input type="date" name="payment_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Notes</label>
                        <textarea name="notes" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Optional notes about payment"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="send_confirmation" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Send payment confirmation to tenant</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeMarkAsPaidModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        <i class="fas fa-check mr-2"></i> Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentInvoiceId = null;
let currentInvoiceTotal = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(() => {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    // Select All functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.invoice-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox listeners
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
});

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    const count = checkboxes.length;
    const selectedCountSpan = document.getElementById('selectedCount');
    const bulkReminderBtn = document.getElementById('bulkReminderBtn');
    const bulkExportBtn = document.getElementById('bulkExportBtn');
    
    if (selectedCountSpan) selectedCountSpan.textContent = count;
    
    if (bulkReminderBtn) bulkReminderBtn.disabled = count === 0;
    if (bulkExportBtn) bulkExportBtn.disabled = count === 0;
    
    // Calculate total amount of selected invoices
    let totalAmount = 0;
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        if (row) {
            const amountCell = row.querySelector('td:nth-child(8) p'); // Balance column
            if (amountCell) {
                const amountText = amountCell.textContent.replace('₵', '').replace(',', '');
                totalAmount += parseFloat(amountText) || 0;
            }
        }
    });
    
    const totalAmountSpan = document.getElementById('selectedTotalAmount');
    if (totalAmountSpan) {
        totalAmountSpan.textContent = '₵' + totalAmount.toFixed(2);
    }
}

function openMarkAsPaidModal(invoiceId, totalAmount) {
    const form = document.getElementById('markAsPaidForm');
    form.action = `/admin/tenant-invoices/${invoiceId}/mark-paid`;
    
    currentInvoiceId = invoiceId;
    currentInvoiceTotal = parseFloat(totalAmount);
    
    document.getElementById('invoiceTotalDisplay').textContent = '₵' + parseFloat(totalAmount).toFixed(2);
    document.getElementById('markAsPaidModal').classList.remove('hidden');
}

function closeMarkAsPaidModal() {
    document.getElementById('markAsPaidModal').classList.add('hidden');
    document.getElementById('markAsPaidForm').reset();
}

function sendReminder(invoiceId) {
    if (confirm('Send payment reminder to tenant?')) {
        fetch(`/admin/tenant-invoices/${invoiceId}/send-reminder`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reminder sent successfully!');
            } else {
                alert('Failed to send reminder: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send reminder. Please try again.');
        });
    }
}

function bulkSendReminders() {
    const selectedIds = Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }
    
    if (confirm(`Send reminders to ${selectedIds.length} tenant(s)?`)) {
        fetch('{{ route("admin.tenant-invoices.bulk-send-reminders") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ invoice_ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Reminders sent to ${data.sent_count} tenant(s). Failed: ${data.failed_count}`);
            } else {
                alert('Failed to send reminders: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send reminders. Please try again.');
        });
    }
}

function bulkExport() {
    const selectedIds = Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }
    
    window.location.href = `{{ route("admin.tenant-invoices.export-selected") }}?ids=${selectedIds.join(',')}`;
}

function exportToCSV() {
    window.location.href = '{{ route("admin.tenant-invoices.export-unpaid") }}' + window.location.search;
}

// Close modal when clicking outside
document.getElementById('markAsPaidModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMarkAsPaidModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('markAsPaidModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeMarkAsPaidModal();
        }
    }
});
</script>

<style>
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

/* Message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.bg-yellow-100 {
    background-color: rgba(254, 249, 195, 0.9);
    border-color: rgba(234, 179, 8, 0.3);
}

/* Modal styles */
#markAsPaidModal {
    transition: opacity 0.3s ease;
}

#markAsPaidModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#markAsPaidModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Dark mode adjustments */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
    color: #ef4444 !important;
}

.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
    border-color: rgba(234, 179, 8, 0.3) !important;
    color: #eab308 !important;
}

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
}

/* Hover effect */
tr:hover td {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Checkbox styling */
input[type="checkbox"] {
    cursor: pointer;
    width: 16px;
    height: 16px;
}
</style>
@endsection