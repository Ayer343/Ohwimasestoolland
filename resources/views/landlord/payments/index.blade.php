@extends('layouts.admin')

@section('title', 'Payments Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Payments Management</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Manage all payment transactions across the system</p>
            </div>
            <div class="flex space-x-3">
                <button class="btn-primary flex items-center" onclick="generateRevenueReport()">
                    <i class="fas fa-chart-line mr-2"></i> Revenue Report
                </button>
                <button class="btn-secondary flex items-center" onclick="exportPayments()">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('admin.payments.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           class="w-full p-2 border rounded" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Transaction ID, Landlord, Property...">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded" 
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>Refunded</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Payment Method</label>
                    <select name="payment_provider" class="w-full p-2 border rounded" 
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Methods</option>
                        <option value="expresspay" {{ request('payment_provider') == 'expresspay' ? 'selected' : '' }}>ExpressPay</option>
                        <option value="hubtel" {{ request('payment_provider') == 'hubtel' ? 'selected' : '' }}>Hubtel</option>
                        <option value="paystack" {{ request('payment_provider') == 'paystack' ? 'selected' : '' }}>Paystack</option>
                        <option value="flutterwave" {{ request('payment_provider') == 'flutterwave' ? 'selected' : '' }}>Flutterwave</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Date Range</label>
                    <div class="flex space-x-2">
                        <input type="date" name="start_date" value="{{ request('start_date') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <input type="date" name="end_date" value="{{ request('end_date') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" 
                            style="background-color: var(--primary); color: white; border-color: var(--primary);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('admin.payments.index') }}" class="ml-2 p-2 border rounded flex items-center justify-center" 
                       style="background-color: var(--bg-secondary); color: var(--text-secondary); border-color: var(--border-color);">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Revenue Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="card stat-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Total Revenue</h3>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
            <h2 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $settings->formatAmount($totalRevenue ?? 0) }}</h2>
            <p class="text-sm" style="color: var(--text-secondary);">All time payments</p>
        </div>
        
        <div class="card stat-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">This Month</h3>
                <i class="fas fa-calendar text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
            <h2 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $settings->formatAmount($monthlyRevenue ?? 0) }}</h2>
            <p class="text-sm" style="color: var(--text-secondary);">{{ now()->format('F Y') }}</p>
        </div>
        
        <div class="card stat-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Total Payments</h3>
                <i class="fas fa-receipt text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
            <h2 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $totalPayments ?? 0 }}</h2>
            <p class="text-sm" style="color: var(--text-secondary);">All transactions</p>
        </div>
        
        <div class="card stat-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Pending Payments</h3>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
            <h2 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $pendingPayments ?? 0 }}</h2>
            <p class="text-sm" style="color: var(--text-secondary);">Awaiting confirmation</p>
        </div>
    </div>

    <!-- Payments Table Card -->
    <div class="card p-6">
        <!-- Header with Currency Info -->
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
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Transaction</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Landlord</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Method</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    @php
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
                        
                        $isMobileMoney = in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']);
                    @endphp
                    <tr class="border-b hover:bg-opacity-5 transition-colors" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="mr-3">
                                    <i class="fas {{ $providerIcon }} text-primary"></i>
                                </div>
                                <div>
                                    <p class="font-medium font-mono text-sm" style="color: var(--text-primary);">
                                        {{ Str::limit($payment->transaction_id ?? 'N/A', 12) }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $providerDisplay }}
                                    </p>
                                    @if($payment->transaction_reference)
                                    <p class="text-xs font-mono" style="color: var(--text-secondary);">
                                        Ref: {{ Str::limit($payment->transaction_reference, 10) }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            @if($payment->property)
                                <p class="font-medium" style="color: var(--text-primary);">
                                    {{ $payment->property->property_name ?? $payment->property->name }}
                                </p>
                                @if($payment->property->street_name)
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-road mr-1"></i>{{ $payment->property->street_name }}
                                </p>
                                @endif
                            @else
                                <p class="text-sm" style="color: var(--text-secondary);">N/A</p>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($payment->landlord)
                                <p class="font-medium" style="color: var(--text-primary);">{{ $payment->landlord->name }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $payment->landlord->email }}</p>
                            @else
                                <p class="text-sm" style="color: var(--text-secondary);">N/A</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium text-success">{{ $settings->formatAmount($payment->amount ?? 0) }}</p>
                            @php
                                $metadata = $payment->metadata ?? [];
                                $paymentType = $metadata['payment_type'] ?? 'single';
                            @endphp
                            <p class="text-xs" style="color: var(--text-secondary);">
                                @if($paymentType === 'bulk')
                                    <i class="fas fa-layer-group mr-1"></i> Bulk
                                @elseif($paymentType === 'invoices')
                                    <i class="fas fa-file-invoice mr-1"></i> Invoices
                                @else
                                    <i class="fas fa-file-alt mr-1"></i> Single
                                @endif
                            </p>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $providerColor }}-rgb), 0.2); color: var(--{{ $providerColor }});">
                                <i class="fas {{ $providerIcon }} mr-1"></i>
                                {{ $providerDisplay }}
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
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'failed' => 'danger',
                                    'refunded' => 'secondary',
                                    'cancelled' => 'secondary',
                                    'expired' => 'secondary'
                                ];
                                $statusColor = $statusColors[$payment->status] ?? 'secondary';
                                $statusIcons = [
                                    'completed' => 'check-circle',
                                    'pending' => 'clock',
                                    'processing' => 'sync-alt fa-spin',
                                    'failed' => 'times-circle',
                                    'refunded' => 'undo-alt',
                                    'cancelled' => 'ban',
                                    'expired' => 'hourglass-end'
                                ];
                                $statusIcon = $statusIcons[$payment->status] ?? 'question-circle';
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                  style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="fas fa-{{ $statusIcon }} mr-1"></i>
                                {{ ucfirst($payment->status ?? 'Unknown') }}
                            </span>
                            @if($payment->payment_date)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Paid: {{ $payment->payment_date->format('M d, Y') }}
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $payment->created_at ? $payment->created_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $payment->created_at ? $payment->created_at->format('H:i') : 'N/A' }}
                            </p>
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.payments.show', $payment->id) }}" 
                                   class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                @if($payment->status === 'pending')
                                <form action="{{ route('admin.payments.update-status', $payment->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                            title="Mark as Completed">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                @endif
                                
                                @if($payment->canBeCancelled() && !in_array($payment->payment_provider, ['paystack', 'flutterwave']))
                                <button onclick="cancelPayment('{{ $payment->transaction_id }}')" 
                                        class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                        title="Cancel Payment">
                                    <i class="fas fa-times"></i>
                                </button>
                                @endif
                                
                                @if($payment->status === 'completed' && $payment->invoice)
                                <a href="{{ route('admin.invoices.show', $payment->invoice->id) }}" 
                                   class="p-2 rounded transition-colors hover:bg-opacity-20" 
                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" 
                                   title="View Invoice">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-12 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-receipt text-5xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No payments found</p>
                                <p class="text-sm">No payments match your search criteria.</p>
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
</div>
@endsection

@section('scripts')
<script>
function filterPayments() {
    document.getElementById('filterForm').submit();
}

function generateRevenueReport() {
    fetch('{{ route("admin.payments.revenue-report") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const currencySymbol = '{{ $settings->currency_symbol }}';
                alert(`Revenue Report:\n\nTotal Revenue: ${currencySymbol}${data.report.total_revenue}\nTotal Payments: ${data.report.total_payments}\nAverage Payment: ${currencySymbol}${data.report.average_payment}\n\nBy Provider:\n${data.report.by_provider.map(p => `${p.provider}: ${currencySymbol}${p.total}`).join('\n')}`);
            } else {
                alert('Failed to generate revenue report. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to generate revenue report. Please try again.');
        });
}

function exportPayments() {
    const form = document.getElementById('filterForm');
    const action = form.action;
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    window.open(`${action}/export?${params.toString()}`, '_blank');
}

function cancelPayment(transactionId) {
    if (confirm('Are you sure you want to cancel this payment? This action cannot be undone.')) {
        fetch(`{{ url('/admin/payments') }}/${transactionId}/cancel`, {
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
                location.reload();
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

// Auto-submit on filter changes
document.addEventListener('DOMContentLoaded', function() {
    const selectFilters = document.querySelectorAll('select[name="status"], select[name="payment_provider"]');
    selectFilters.forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // Debounce search input
    let searchTimeout;
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
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

/* Currency display styling */
.currency-display {
    font-family: monospace;
    font-weight: 600;
}

/* Responsive table */
@media (max-width: 768px) {
    .overflow-x-auto {
        font-size: 0.875rem;
    }
    
    .overflow-x-auto th,
    .overflow-x-auto td {
        padding: 0.5rem;
    }
    
    .grid-cols-5 {
        grid-template-columns: 1fr 1fr;
    }
    
    .grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
    
    .flex.space-x-2 {
        flex-direction: column;
        gap: 0.25rem;
    }
}

/* Card hover effects */
.card {
    transition: box-shadow 0.2s ease-in-out;
}

.card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Table row hover */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

/* Action buttons */
.flex.space-x-2 a,
.flex.space-x-2 button {
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
}

.flex.space-x-2 a:hover,
.flex.space-x-2 button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
</style>
@endsection