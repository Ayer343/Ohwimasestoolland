@extends('layouts.landlord')

@section('title', 'Payment History')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Payment History</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">View your payment history and receipts</p>
            </div>
            <div>
                <a href="{{ route('properties.my-properties') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Properties
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Amount Paid</p>
                    <p class="text-xl font-bold text-success">
                        {{ $settings->formatAmount($completedPaymentsTotal ?? 0.00) }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Pending</p>
                    <p class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ $pendingCount }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Processing</p>
                    <p class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ $processingCount }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--secondary-rgb), 0.2); color: var(--secondary);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Last Payment</p>
                    <p class="text-xl font-bold" style="color: var(--text-primary);">
                        @if($lastPayment && $lastPayment->payment_date)
                            {{ $lastPayment->payment_date->format('M d') }}
                        @else
                            N/A
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filter Section -->
    <div class="card p-5">
        <form method="GET" action="{{ route('landlord.payments.history') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="form-select w-full" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Property Filter -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Property</label>
                    <select name="property_id" class="form-select w-full" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                {{ $property->property_name ?? 'Property #' . $property->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Payment Provider Filter (Updated for new gateways) -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Method</label>
                    <select name="payment_provider" class="form-select w-full" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Methods</option>
                        @foreach($paymentProviders as $value => $label)
                            <option value="{{ $value }}" {{ request('payment_provider') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Date Range -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Date Range</label>
                    <div class="flex space-x-2">
                        <input type="date" name="start_date" value="{{ request('start_date') }}" 
                               class="form-input w-full" placeholder="From" onchange="document.getElementById('filterForm').submit()">
                        <input type="date" name="end_date" value="{{ request('end_date') }}" 
                               class="form-input w-full" placeholder="To" onchange="document.getElementById('filterForm').submit()">
                    </div>
                </div>
            </div>
            
            <!-- Search and Sort Row -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Search by transaction ID, reference, or property..." 
                               class="form-input w-full pl-10">
                        <i class="fas fa-search absolute left-3 top-3" style="color: var(--text-secondary);"></i>
                    </div>
                </div>
                
                <!-- Sort and Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Sort -->
                    <select name="sort" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="created_at" {{ request('sort') == 'created_at' ? 'selected' : '' }}>Sort by Date</option>
                        <option value="amount" {{ request('sort') == 'amount' ? 'selected' : '' }}>Sort by Amount</option>
                        <option value="payment_date" {{ request('sort') == 'payment_date' ? 'selected' : '' }}>Sort by Payment Date</option>
                    </select>
                    
                    <select name="direction" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="desc" {{ request('direction') == 'desc' ? 'selected' : '' }}>Descending</option>
                        <option value="asc" {{ request('direction') == 'asc' ? 'selected' : '' }}>Ascending</option>
                    </select>
                    
                    <!-- Reset Button -->
                    <a href="{{ route('landlord.payments.history') }}" class="btn-secondary">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Additional Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Average Payment</p>
                <p class="text-lg font-bold text-primary">
                    {{ $settings->formatAmount($averagePayment ?? 0.00) }}
                </p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Overdue Payments</p>
                <p class="text-lg font-bold {{ $overduePayments > 0 ? 'text-danger' : 'text-success' }}">
                    {{ $overduePayments ?? 0 }}
                </p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Expiring Soon</p>
                <p class="text-lg font-bold {{ ($expiringSoonCount ?? 0) > 0 ? 'text-warning' : 'text-success' }}">
                    {{ $expiringSoonCount ?? 0 }}
                </p>
            </div>
        </div>
    </div>

    <!-- Payments Table Card -->
    <div class="card p-6">
        <!-- Table Header -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $payments->firstItem() ?? 0 }} to {{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }} results
                </p>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                Currency: {{ $settings->currency_code }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Transaction ID</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Info</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Method</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $uniqueTransactions = [];
                    @endphp
                    
                    @forelse($payments as $payment)
                        @php
                            // Skip duplicate transaction IDs for non-completed payments
                            if (in_array($payment->transaction_id, $uniqueTransactions) && !$payment->isCompleted()) {
                                continue;
                            }
                            $uniqueTransactions[] = $payment->transaction_id;
                            
                            // Updated provider display for new gateways
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
                            $providerColors = [
                                'expresspay' => 'primary',
                                'hubtel' => 'info',
                                'paystack' => 'success',
                                'flutterwave' => 'warning'
                            ];
                            $providerColor = $providerColors[$payment->payment_provider] ?? 'secondary';
                            
                            // Check if payment is mobile money
                            $isMobileMoney = in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']);
                        @endphp
                        
                        <tr class="border-b hover:bg-gray-50 transition-colors" style="border-color: var(--border-color);">
                            <td class="p-3">
                                <p class="font-medium" style="color: var(--text-primary);">
                                    @if($payment->payment_date)
                                        {{ $payment->payment_date->format('M d, Y') }}
                                    @elseif($payment->created_at)
                                        {{ $payment->created_at->format('M d, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($payment->payment_date)
                                        {{ $payment->payment_date->format('H:i') }}
                                    @elseif($payment->created_at)
                                        {{ $payment->created_at->format('H:i') }}
                                    @else
                                        N/A
                                    @endif
                                </p>
                            </td>
                            <td class="p-3">
                                <p class="font-medium font-mono text-sm" style="color: var(--text-primary);">
                                    {{ Str::limit($payment->transaction_id ?? 'N/A', 12) }}
                                </p>
                                @if($payment->transaction_reference)
                                <p class="text-xs font-mono" style="color: var(--text-secondary);">
                                    Ref: {{ Str::limit($payment->transaction_reference, 10) }}
                                </p>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-home text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        @if($payment->property)
                                            <!-- Property Name -->
                                            @if($payment->property->property_name)
                                                <p class="font-medium text-sm" style="color: var(--text-primary);">
                                                    {{ $payment->property->property_name }}
                                                </p>
                                            @endif
                                            
                                            <!-- House and Block Numbers -->
                                            <div class="flex items-center space-x-2 mt-1">
                                                @if($payment->property->house_number)
                                                    <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        #{{ $payment->property->house_number }}
                                                    </span>
                                                @endif
                                                @if($payment->property->block_number)
                                                    <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                        Block {{ $payment->property->block_number }}
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <!-- Street Name -->
                                            @if($payment->property->street_name)
                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    <i class="fas fa-road mr-1 text-xs"></i>{{ $payment->property->street_name }}
                                                </p>
                                            @endif
                                            
                                            <!-- Location -->
                                            @if($payment->property->zone || $payment->property->section)
                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    <i class="fas fa-map-marker-alt mr-1 text-xs text-red-500"></i>
                                                    @if($payment->property->zone && $payment->property->section)
                                                        {{ $payment->property->zone }} - {{ $payment->property->section }}
                                                    @elseif($payment->property->zone)
                                                        {{ $payment->property->zone }}
                                                    @else
                                                        Location not specified
                                                    @endif
                                                </p>
                                            @endif
                                            
                                            <!-- Digital Address -->
                                            @if($payment->property->digital_address)
                                                <p class="text-xs mt-1">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-1 text-xs"></i> {{ $payment->property->digital_address }}
                                                    </span>
                                                </p>
                                            @endif
                                        @else
                                            <p class="text-sm" style="color: var(--text-secondary);">Property not found</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <p class="font-medium @if($payment->isCompleted()) text-success @elseif($payment->isCancelled() || $payment->isFailed()) text-secondary @else text-warning @endif">
                                    {{ $settings->formatAmount($payment->amount ?? 0.00) }}
                                </p>
                                @php
                                    $metadata = $payment->metadata ?? [];
                                    $paymentType = $metadata['payment_type'] ?? 'single';
                                @endphp
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($paymentType === 'bulk')
                                        <i class="fas fa-layer-group mr-1"></i> Bulk ({{ $metadata['months'] ?? 1 }} months)
                                    @elseif($paymentType === 'invoices')
                                        <i class="fas fa-file-invoice mr-1"></i> Invoices ({{ count($metadata['invoices'] ?? []) }})
                                    @else
                                        <i class="fas fa-file-alt mr-1"></i> Single
                                    @endif
                                </p>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $providerColor }}-rgb), 0.2); color: var(--{{ $providerColor }});">
                                    <i class="fas {{ $providerIcon }} mr-1"></i>
                                    {{ $payment->payment_provider_display ?? $providerDisplay }}
                                </span>
                                @if($isMobileMoney && $payment->phone_number)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-phone mr-1"></i>{{ $payment->phone_number }}
                                </p>
                                @endif
                                @if($payment->payment_provider === 'paystack' && $payment->email)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-envelope mr-1"></i>{{ $payment->email }}
                                </p>
                                @endif
                            </td>
                            <td class="p-3">
                                @php
                                    $statusColors = [
                                        'completed' => ['bg' => 'success', 'icon' => 'check-circle'],
                                        'pending' => ['bg' => 'warning', 'icon' => 'clock'],
                                        'processing' => ['bg' => 'info', 'icon' => 'sync-alt'],
                                        'failed' => ['bg' => 'danger', 'icon' => 'times-circle'],
                                        'refunded' => ['bg' => 'secondary', 'icon' => 'undo'],
                                        'cancelled' => ['bg' => 'secondary', 'icon' => 'ban'],
                                        'expired' => ['bg' => 'secondary', 'icon' => 'hourglass-end']
                                ];
                                $statusConfig = $statusColors[$payment->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.2); color: var(--{{ $statusConfig['bg'] }});">
                                    <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                    {{ $payment->status_display ?? 'Unknown' }}
                                </span>
                                @if($payment->isOverdue())
                                <p class="text-xs text-danger mt-1 flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                </p>
                                @endif
                                @if($payment->isExpiringSoon())
                                <p class="text-xs text-warning mt-1 flex items-center">
                                    <i class="fas fa-clock mr-1"></i> Expiring soon
                                </p>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex space-x-2">
                                    <a href="{{ route('landlord.payments.confirmation', $payment->transaction_id ?? '#') }}" 
                                       class="p-2 rounded hover:shadow transition-all" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    @if($payment->isCompleted())
                                    <button onclick="printReceipt('{{ $payment->id }}')" 
                                            class="p-2 rounded hover:shadow transition-all" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);" title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    @endif
                                    
                                    <!-- Show Cancel button only for active payments (excluding Paystack and Flutterwave) -->
                                    @if($payment->canBeCancelled() && !in_array($payment->payment_provider, ['paystack', 'flutterwave']))
                                    <button onclick="cancelPayment('{{ $payment->transaction_id }}')" 
                                            class="p-2 rounded hover:shadow transition-all" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="Cancel Payment">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    @endif
                                    
                                    <!-- Show Pay button for cancelled payments -->
                                    @if($payment->isCancelled() && $payment->property)
                                    <a href="{{ route('landlord.payments.create', $payment->property_id) }}" 
                                       class="p-2 rounded hover:shadow transition-all" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Make Payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                    @endif
                                    
                                    <!-- Show Retry button for failed payments -->
                                    @if($payment->isFailed() && $payment->property)
                                    <a href="{{ route('landlord.payments.create', $payment->property_id) }}" 
                                       class="p-2 rounded hover:shadow transition-all" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Retry Payment">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                    @endif
                                    
                                    <!-- Show Verify button for pending mobile money payments -->
                                    @if($payment->status === 'pending' && $isMobileMoney)
                                    <a href="{{ route('landlord.payments.verify.form', $payment->transaction_id) }}" 
                                       class="p-2 rounded hover:shadow transition-all" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Verify Payment">
                                        <i class="fas fa-shield-alt"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-receipt text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No payments found</p>
                                <p class="text-sm mb-4">No payments match your search criteria.</p>
                                <a href="{{ route('landlord.payments.history') }}" class="btn-primary flex items-center">
                                    <i class="fas fa-redo mr-2"></i> Clear Filters
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($payments->hasPages())
        <div class="flex justify-center mt-6">
            {{ $payments->withQueryString()->links() }}
        </div>
        @endif
    </div>

    <!-- Monthly Chart Section (Optional) -->
    @if($monthlyTotals->count() > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Trend (Last 6 Months)</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Month</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Number of Payments</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($monthlyTotals as $month)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3">{{ $month->month_name ?? 'N/A' }}</td>
                        <td class="p-3">{{ $month->count ?? 0 }}</td>
                        <td class="p-3 text-success font-medium">{{ $month->formatted_total ?? $settings->formatAmount(0.00) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<!-- Print Receipt Modal -->
<div id="printModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Print Receipt</h3>
        <p class="text-sm mb-6" style="color: var(--text-secondary);">Select receipt format:</p>
        
        <div class="flex space-x-4">
            <button onclick="printSimpleReceipt()" class="btn-primary flex-1">
                <i class="fas fa-receipt mr-2"></i> Simple
            </button>
            <button onclick="printDetailedReceipt()" class="btn-secondary flex-1">
                <i class="fas fa-file-alt mr-2"></i> Detailed
            </button>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button onclick="closePrintModal()" class="text-sm" style="color: var(--text-secondary);">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
let currentPaymentId = null;

function printReceipt(paymentId) {
    currentPaymentId = paymentId;
    document.getElementById('printModal').classList.remove('hidden');
}

function closePrintModal() {
    document.getElementById('printModal').classList.add('hidden');
    currentPaymentId = null;
}

function printSimpleReceipt() {
    if (currentPaymentId) {
        window.open(`/landlord/payments/${currentPaymentId}/receipt/simple`, '_blank');
        closePrintModal();
    }
}

function printDetailedReceipt() {
    if (currentPaymentId) {
        window.open(`/landlord/payments/${currentPaymentId}/receipt/detailed`, '_blank');
        closePrintModal();
    }
}

function cancelPayment(transactionId) {
    if (!transactionId) {
        alert('Invalid transaction ID');
        return;
    }
    
    if (confirm('Are you sure you want to cancel this payment? This action cannot be undone.')) {
        fetch(`/landlord/payments/${transactionId}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment cancelled successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel payment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to cancel payment');
        });
    }
}

// Auto-submit filter form on enter in search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('filterForm').submit();
            }
        });
    }
});

// Close modal when clicking outside
const printModal = document.getElementById('printModal');
if (printModal) {
    printModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closePrintModal();
        }
    });
}

// Auto-hide success and error messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }
});
</script>

<style>
.font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

/* Status badge improvements */
.px-2.py-1.rounded-full {
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.5px;
}

/* Form styling */
.form-select, .form-input {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    color: var(--text-primary);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-select:focus, .form-input:focus {
    border-color: var(--primary);
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

/* Table hover effects */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

/* Property info column styling */
.flex.items-start.space-x-3 {
    min-width: 220px;
}

/* Small badge styling */
.text-xs.px-1\.5.py-0\.5 {
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}

/* Icon consistency in property column */
.fa-home, .fa-road, .fa-map-marker-alt, .fa-check-circle {
    font-size: 0.75em;
}

/* Responsive design */
@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
    
    .grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .flex.space-x-2 {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    table th, table td {
        padding: 0.5rem;
    }
    
    .flex.items-start.space-x-3 {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .flex-shrink-0.w-8.h-8 {
        margin-bottom: 0.25rem;
    }
}

/* Card hover effects */
.card {
    transition: box-shadow 0.2s ease-in-out;
}

.card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Action buttons */
.flex.space-x-2 a, .flex.space-x-2 button {
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
}

.flex.space-x-2 a:hover, .flex.space-x-2 button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Success and error message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

/* Message close button */
.absolute.top-0.bottom-0.right-0.px-4.py-3 {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.absolute.top-0.bottom-0.right-0.px-4.py-3:hover {
    opacity: 1;
}

/* Payment type icons */
.fa-layer-group, .fa-file-invoice, .fa-file-alt {
    font-size: 0.8em;
}
</style>
@endsection