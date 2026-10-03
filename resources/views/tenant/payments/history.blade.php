@extends('layouts.tenant')

@section('title', 'Payment History')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Payment History</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Track all your payments and transaction history</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('tenant.invoices.my-invoices') }}" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-file-invoice mr-2"></i> View Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Paid Card -->
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Paid</p>
                    <p class="text-2xl font-bold mt-2" style="color: var(--success);">{{ $settings->formatAmount($totalPaid) }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Lifetime payments</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-credit-card text-xl" style="color: var(--success);"></i>
                </div>
            </div>
        </div>

        <!-- Completed Payments Card -->
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Completed Payments</p>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ $completedCount }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Successfully processed</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>

        <!-- Pending Payments Card -->
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Pending Payments</p>
                    <p class="text-2xl font-bold mt-2" style="color: var(--warning);">{{ $pendingCount }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Awaiting confirmation</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>

        <!-- Failed Payments Card -->
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Failed Payments</p>
                    <p class="text-2xl font-bold mt-2" style="color: var(--danger);">{{ $failedCount }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Requires attention</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-xl" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Filter Payments</h3>
            <button onclick="resetFilters()" class="text-sm px-3 py-1 rounded mt-2 md:mt-0" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                <i class="fas fa-undo-alt mr-1"></i> Reset Filters
            </button>
        </div>
        
        <form method="GET" action="{{ route('tenant.payments.history') }}" id="filter-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Property Unit Filter -->
            <div>
                <label for="property_unit_id" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                    Property Unit
                </label>
                <select name="property_unit_id" id="property_unit_id" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Units</option>
                    @foreach($propertyUnits as $unit)
                        <option value="{{ $unit['id'] }}" {{ request('property_unit_id') == $unit['id'] ? 'selected' : '' }}>
                            {{ $unit['display_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                    Payment Status
                </label>
                <select name="status" id="status" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Start Date Filter -->
            <div>
                <label for="start_date" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                    From Date
                </label>
                <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}" 
                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" 
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
            </div>

            <!-- End Date Filter -->
            <div>
                <label for="end_date" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                    To Date
                </label>
                <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}" 
                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" 
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
            </div>

            <!-- Search -->
            <div class="md:col-span-2 lg:col-span-4">
                <label for="search" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                    Search
                </label>
                <div class="flex gap-2">
                    <input type="text" name="search" id="search" value="{{ request('search') }}" 
                           placeholder="Search by transaction ID, reference, or description..."
                           class="flex-1 p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <button type="submit" class="px-4 py-2 rounded flex items-center" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Success Alert -->
    @if(session('success'))
        <div class="card p-6" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid var(--success);">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--success);">Success!</h3>
                    <p class="mt-1" style="color: var(--success);">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Alert -->
    @if(session('error'))
        <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid var(--danger);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">Error</h3>
                    <p class="mt-1" style="color: var(--danger);">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Payments Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="text-left p-4 font-medium" style="color: var(--text-secondary);">Transaction ID</th>
                        <th class="text-left p-4 font-medium" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left p-4 font-medium" style="color: var(--text-secondary);">Property Unit</th>
                        <th class="text-left p-4 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-right p-4 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-4 font-medium" style="color: var(--text-secondary);">Provider</th>
                        <th class="text-left p-4 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-center p-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php
                            $statusColors = [
                                'completed' => 'success',
                                'pending' => 'warning',
                                'processing' => 'info',
                                'failed' => 'danger',
                                'cancelled' => 'secondary',
                                'refunded' => 'secondary'
                            ];
                            $statusColor = $statusColors[$payment->status] ?? 'secondary';
                            $statusIcons = [
                                'completed' => 'check-circle',
                                'pending' => 'clock',
                                'processing' => 'spinner fa-spin',
                                'failed' => 'exclamation-circle',
                                'cancelled' => 'times-circle',
                                'refunded' => 'undo-alt'
                            ];
                            $statusIcon = $statusIcons[$payment->status] ?? 'circle';
                            
                            // Updated provider display names for new gateways
                            $providerDisplay = match($payment->payment_provider) {
                                'expresspay' => 'ExpressPay',
                                'hubtel' => 'Hubtel',
                                'paystack' => 'Paystack',
                                'flutterwave' => 'Flutterwave',
                                default => ucfirst(str_replace('_', ' ', $payment->payment_provider))
                            };
                            
                            // Updated provider icons for new gateways
                            $providerIcon = match($payment->payment_provider) {
                                'expresspay' => 'fa-credit-card',
                                'hubtel' => 'fa-phone-alt',
                                'paystack' => 'fa-credit-card',
                                'flutterwave' => 'fa-cloud-upload-alt',
                                default => 'fa-wallet'
                            };
                            
                            // Updated provider colors for new gateways
                            $providerColor = match($payment->payment_provider) {
                                'expresspay' => '#0066CC',
                                'hubtel' => '#2563EB',
                                'paystack' => '#3B82F6',
                                'flutterwave' => '#F97316',
                                default => 'var(--primary)'
                            };
                            
                            // Check if payment is mobile money (requires verification)
                            $isMobileMoney = in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']);
                        @endphp
                        <tr class="border-b hover:bg-opacity-5 transition-colors" style="border-color: var(--border-color);">
                            <td class="p-4">
                                <div>
                                    <p class="font-mono text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $payment->transaction_id }}
                                    </p>
                                    @if($payment->transaction_reference)
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Ref: {{ $payment->transaction_reference }}
                                        </p>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4">
                                <div>
                                    <p class="text-sm" style="color: var(--text-primary);">
                                        {{ $payment->created_at ? $payment->created_at->format('M d, Y') : 'N/A' }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $payment->created_at ? $payment->created_at->format('h:i A') : 'N/A' }}
                                    </p>
                                </div>
                            </td>
                            <td class="p-4">
                                @if($payment->invoice && $payment->invoice->propertyUnit)
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $payment->invoice->propertyUnit->property->property_name ?? 'N/A' }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Unit {{ $payment->invoice->propertyUnit->unit_number }}
                                    </p>
                                @else
                                    <p class="text-sm" style="color: var(--text-secondary);">N/A</p>
                                @endif
                            </td>
                            <td class="p-4">
                                @if($payment->invoice)
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ \Carbon\Carbon::parse($payment->invoice->period . '-01')->format('F Y') }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Invoice #{{ $payment->invoice->invoice_number }}
                                    </p>
                                @else
                                    <p class="text-sm" style="color: var(--text-secondary);">N/A</p>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-lg font-semibold" style="color: var(--success);">
                                    {{ $settings->formatAmount($payment->amount) }}
                                </p>
                                @if($payment->metadata && isset($payment->metadata['excess_payment']))
                                    <p class="text-xs mt-1" style="color: var(--info);">
                                        <i class="fas fa-info-circle mr-1"></i> 
                                        Excess: {{ $settings->formatAmount($payment->metadata['excess_payment']['amount']) }}
                                    </p>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas {{ $providerIcon }} mr-1" style="color: {{ $providerColor }};"></i>
                                    {{ $providerDisplay }}
                                </span>
                                @if($payment->phone_number && $isMobileMoney)
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-phone mr-1"></i> {{ $payment->phone_number }}
                                    </p>
                                @endif
                                @if($payment->email && $payment->payment_provider === 'paystack')
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-envelope mr-1"></i> {{ $payment->email }}
                                    </p>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                    <i class="fas fa-{{ $statusIcon }} mr-1"></i>
                                    {{ ucfirst($payment->status) }}
                                </span>
                                @if($payment->payment_date && $payment->status === 'completed')
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Paid: {{ $payment->payment_date->format('M d, Y') }}
                                    </p>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick="viewPaymentDetails('{{ $payment->id }}')" 
                                            class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                            style="color: var(--info);" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    @if($payment->status === 'completed' && $payment->invoice)
                                        <a href="{{ route('tenant.invoices.export-pdf', $payment->invoice->id) }}" 
                                           class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                           style="color: var(--primary);" 
                                           title="Download Receipt">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    @endif
                                    
                                    @if(in_array($payment->status, ['pending', 'processing']) && $isMobileMoney)
                                        <a href="{{ route('tenant.payments.verify', $payment->transaction_id) }}" 
                                           class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                           style="color: var(--warning);" 
                                           title="Verify Payment">
                                            <i class="fas fa-check-double"></i>
                                        </a>
                                    @endif
                                    
                                    @if($payment->status === 'pending')
                                        <button onclick="cancelPayment('{{ $payment->transaction_id }}')" 
                                                class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                                style="color: var(--danger);" 
                                                title="Cancel Payment">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-12 text-center">
                                <i class="fas fa-receipt text-5xl mb-4" style="color: var(--text-secondary);"></i>
                                <p class="text-lg font-medium" style="color: var(--text-primary);">No Payment Records Found</p>
                                <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                    @if(request('search') || request('status') || request('start_date'))
                                        Try adjusting your filters or clear them to see all payments.
                                    @else
                                        You haven't made any payments yet. View your invoices to get started.
                                    @endif
                                </p>
                                @if(!request('search') && !request('status') && !request('start_date'))
                                    <a href="{{ route('tenant.invoices.my-invoices') }}" class="mt-4 inline-block px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                                        <i class="fas fa-file-invoice mr-2"></i> View Invoices
                                    </a>
                                @else
                                    <button onclick="resetFilters()" class="mt-4 inline-block px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                                        <i class="fas fa-undo-alt mr-2"></i> Clear Filters
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($payments->hasPages())
            <div class="p-4 border-t" style="border-color: var(--border-color);">
                {{ $payments->withQueryString()->links() }}
            </div>
        @endif
    </div>

    <!-- Payment Summary Card (for current filters) -->
    @if($payments->count() > 0)
        <div class="card p-6" style="background-color: rgba(var(--info-rgb), 0.05);">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Showing Payments</p>
                    <p class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $payments->total() }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Total records found</p>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Amount (Current View)</p>
                    <p class="text-2xl font-bold mt-1" style="color: var(--success);">{{ $settings->formatAmount($payments->sum('amount')) }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">From {{ $payments->count() }} payment(s)</p>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Export Options</p>
                    <div class="flex space-x-2 mt-2">
                        <button onclick="exportPayments('csv')" class="px-3 py-1 rounded text-sm flex items-center" style="background-color: var(--primary); color: white;">
                            <i class="fas fa-file-csv mr-1"></i> CSV
                        </button>
                        <button onclick="exportPayments('pdf')" class="px-3 py-1 rounded text-sm flex items-center" style="background-color: var(--danger); color: white;">
                            <i class="fas fa-file-pdf mr-1"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Payment Details Modal -->
<div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="rounded-lg p-6 max-w-2xl w-full mx-4" style="background-color: var(--bg-primary); color: var(--text-primary);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">Payment Details</h3>
            <button onclick="closeModal()" class="text-2xl hover:opacity-70" style="color: var(--text-secondary);">&times;</button>
        </div>
        <div id="payment-details-content">
            <!-- Content loaded via AJAX -->
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Loading payment details...</p>
            </div>
        </div>
        <div class="flex justify-end mt-6">
            <button onclick="closeModal()" class="px-4 py-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Reset all filters
function resetFilters() {
    window.location.href = "{{ route('tenant.payments.history') }}";
}

// View payment details via AJAX
function viewPaymentDetails(paymentId) {
    const modal = document.getElementById('payment-modal');
    const content = document.getElementById('payment-details-content');
    
    modal.classList.remove('hidden');
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--primary);"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading payment details...</p>
        </div>
    `;
    
    fetch(`/tenant/payments/${paymentId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const payment = data.payment;
                const settings = data.settings;
                
                content.innerHTML = `
                    <div class="space-y-4">
                        <!-- Transaction Info -->
                        <div class="grid grid-cols-2 gap-4 p-4 rounded" style="background-color: var(--bg-secondary);">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-secondary);">Transaction ID</p>
                                <p class="font-mono text-sm mt-1" style="color: var(--text-primary);">${payment.transaction_id}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-secondary);">Reference</p>
                                <p class="font-mono text-sm mt-1" style="color: var(--text-primary);">${payment.transaction_reference || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-secondary);">Date</p>
                                <p class="mt-1" style="color: var(--text-primary);">${new Date(payment.created_at).toLocaleDateString()}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-secondary);">Time</p>
                                <p class="mt-1" style="color: var(--text-primary);">${new Date(payment.created_at).toLocaleTimeString()}</p>
                            </div>
                        </div>
                        
                        <!-- Amount & Status -->
                        <div class="grid grid-cols-2 gap-4 p-4 rounded" style="background-color: var(--bg-secondary);">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-secondary);">Amount</p>
                                <p class="text-2xl font-bold mt-1" style="color: var(--success);">${settings.currency_symbol}${parseFloat(payment.amount).toFixed(settings.decimal_places || 2)}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-secondary);">Status</p>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" 
                                      style="background-color: rgba(var(--${getStatusColor(payment.status)}-rgb), 0.2); color: var(--${getStatusColor(payment.status)});">
                                    <i class="fas fa-${getStatusIcon(payment.status)} mr-1"></i>
                                    ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="p-4 rounded" style="background-color: var(--bg-secondary);">
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Payment Method</p>
                            <p class="mt-1" style="color: var(--text-primary);">${getProviderDisplayName(payment.payment_provider)}</p>
                            ${payment.payment_date ? `<p class="text-xs mt-1" style="color: var(--text-secondary);">Paid on: ${new Date(payment.payment_date).toLocaleDateString()}</p>` : ''}
                            ${payment.phone_number ? `<p class="text-xs mt-1" style="color: var(--text-secondary);"><i class="fas fa-phone mr-1"></i> ${payment.phone_number}</p>` : ''}
                            ${payment.email ? `<p class="text-xs mt-1" style="color: var(--text-secondary);"><i class="fas fa-envelope mr-1"></i> ${payment.email}</p>` : ''}
                        </div>
                        
                        ${payment.metadata ? `
                        <div class="p-4 rounded" style="background-color: var(--bg-secondary);">
                            <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Additional Information</p>
                            <div class="space-y-2 text-sm">
                                ${payment.metadata.phone_number ? `<p><span class="font-medium">Phone:</span> ${payment.metadata.phone_number}</p>` : ''}
                                ${payment.metadata.email ? `<p><span class="font-medium">Email:</span> ${payment.metadata.email}</p>` : ''}
                                ${payment.metadata.description ? `<p><span class="font-medium">Description:</span> ${payment.metadata.description}</p>` : ''}
                                ${payment.metadata.period ? `<p><span class="font-medium">Period:</span> ${payment.metadata.period}</p>` : ''}
                                ${payment.metadata.invoice_number ? `<p><span class="font-medium">Invoice:</span> #${payment.metadata.invoice_number}</p>` : ''}
                            </div>
                        </div>
                        ` : ''}
                        
                        ${payment.notes ? `
                        <div class="p-4 rounded" style="background-color: var(--bg-secondary);">
                            <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Notes</p>
                            <p class="text-sm" style="color: var(--text-primary);">${payment.notes}</p>
                        </div>
                        ` : ''}
                        
                        ${payment.invoice ? `
                        <div class="p-4 rounded" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Invoice Details</p>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <p><span class="font-medium">Invoice #:</span> ${payment.invoice.invoice_number}</p>
                                <p><span class="font-medium">Period:</span> ${payment.invoice.period}</p>
                                <p><span class="font-medium">Amount:</span> ${settings.currency_symbol}${parseFloat(payment.invoice.amount).toFixed(2)}</p>
                                ${payment.invoice.property_unit ? `<p><span class="font-medium">Property:</span> ${payment.invoice.property_unit.property_name || 'N/A'}</p>` : ''}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl mb-2" style="color: var(--danger);"></i>
                        <p style="color: var(--danger);">${data.message || 'Failed to load payment details'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2" style="color: var(--danger);"></i>
                    <p style="color: var(--danger);">Failed to load payment details. Please try again.</p>
                </div>
            `;
        });
}

// Close modal
function closeModal() {
    document.getElementById('payment-modal').classList.add('hidden');
}

// Cancel payment
function cancelPayment(transactionId) {
    if (confirm('Are you sure you want to cancel this payment? This action cannot be undone.')) {
        fetch(`/tenant/payments/${transactionId}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to cancel payment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to cancel payment. Please try again.');
        });
    }
}

// Export payments
function exportPayments(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('format', format);
    window.location.href = `/tenant/payments/export?${params.toString()}`;
}

// Helper functions
function getStatusColor(status) {
    const colors = {
        'completed': 'success',
        'pending': 'warning',
        'processing': 'info',
        'failed': 'danger',
        'cancelled': 'secondary',
        'refunded': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getStatusIcon(status) {
    const icons = {
        'completed': 'check-circle',
        'pending': 'clock',
        'processing': 'spinner fa-spin',
        'failed': 'exclamation-circle',
        'cancelled': 'times-circle',
        'refunded': 'undo-alt'
    };
    return icons[status] || 'circle';
}

function getProviderDisplayName(provider) {
    const providers = {
        'expresspay': 'ExpressPay',
        'hubtel': 'Hubtel',
        'paystack': 'Paystack',
        'flutterwave': 'Flutterwave'
    };
    return providers[provider] || provider.replace('_', ' ');
}

function getProviderIcon(provider) {
    const icons = {
        'expresspay': 'fa-credit-card',
        'hubtel': 'fa-phone-alt',
        'paystack': 'fa-credit-card',
        'flutterwave': 'fa-cloud-upload-alt'
    };
    return icons[provider] || 'fa-wallet';
}

function getProviderColor(provider) {
    const colors = {
        'expresspay': '#0066CC',
        'hubtel': '#2563EB',
        'paystack': '#3B82F6',
        'flutterwave': '#F97316'
    };
    return colors[provider] || 'var(--primary)';
}

// Auto-submit filter form on select change (for dropdowns only, not date inputs)
document.getElementById('property_unit_id')?.addEventListener('change', function() {
    document.getElementById('filter-form').submit();
});

document.getElementById('status')?.addEventListener('change', function() {
    document.getElementById('filter-form').submit();
});

// Debounce search input
let searchTimeout;
document.getElementById('search')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('filter-form').submit();
    }, 500);
});

// Close modal when clicking outside
document.getElementById('payment-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Handle date range validation
const startDate = document.getElementById('start_date');
const endDate = document.getElementById('end_date');

if (startDate && endDate) {
    startDate.addEventListener('change', function() {
        if (endDate.value && this.value > endDate.value) {
            alert('Start date cannot be after end date.');
            this.value = '';
        }
    });
    
    endDate.addEventListener('change', function() {
        if (startDate.value && this.value < startDate.value) {
            alert('End date cannot be before start date.');
            this.value = '';
        }
    });
}

// Keyboard shortcut: Escape to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<style>
/* Hover effect for table rows */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

/* Custom scrollbar for overflow-x */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Animation for modal */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

#payment-modal .rounded-lg {
    animation: modalFadeIn 0.2s ease-out;
}

/* Print styles */
@media print {
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .no-print {
        display: none !important;
    }
}

/* Mobile responsiveness for action buttons */
@media (max-width: 640px) {
    .flex.items-center.justify-center.space-x-2 {
        flex-direction: column;
        gap: 4px;
    }
}
</style>
@endsection