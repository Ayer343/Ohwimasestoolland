@extends('layouts.app')

@section('title', 'Payment Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Payment Management</h2>
            <div class="flex space-x-2">
                <a href="{{ route('admin.payments.export', request()->all()) }}" class="btn-primary flex items-center">
                    <i class="fas fa-download mr-2"></i> Export CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Revenue</div>
                    <div class="text-2xl font-semibold">{{ $settings->formatAmount($totalRevenue) }}</div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Today's Revenue</div>
                    <div class="text-2xl font-semibold">{{ $settings->formatAmount($todayRevenue) }}</div>
                </div>
                <i class="fas fa-calendar-day text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Monthly Revenue</div>
                    <div class="text-2xl font-semibold">{{ $settings->formatAmount($monthlyRevenue) }}</div>
                </div>
                <i class="fas fa-chart-line text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Payments</div>
                    <div class="text-2xl font-semibold">{{ number_format($totalPayments) }}</div>
                </div>
                <i class="fas fa-receipt text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Pending Payments</div>
                    <div class="text-2xl font-semibold">{{ number_format($pendingPayments) }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70"></i>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2"></i> Filter Payments
        </h3>
        
        <form method="GET" action="{{ route('admin.payments.index') }}">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Landlord</label>
                    <select class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" id="landlord_id" name="landlord_id">
                        <option value="">All Landlords</option>
                        @foreach($landlords as $landlord)
                        <option value="{{ $landlord->id }}" {{ request('landlord_id') == $landlord->id ? 'selected' : '' }}>
                            {{ $landlord->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Property</label>
                    <select class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" id="property_id" name="property_id">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                            {{ $property->property_name ?? $property->name }} - {{ $property->street_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Payment Provider</label>
                    <select class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" id="payment_provider" name="payment_provider">
                        <option value="">All Providers</option>
                        @foreach($paymentProviders as $key => $label)
                        <option value="{{ $key }}" {{ request('payment_provider') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                    <select class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>Refunded</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="partially_refunded" {{ request('status') == 'partially_refunded' ? 'selected' : '' }}>Partially Refunded</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Search</label>
                    <input type="text" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           id="search" name="search" placeholder="Transaction ID, Property Name, Reference..." value="{{ request('search') }}">
                </div>
                
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Start Date</label>
                    <input type="date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           id="start_date" name="start_date" value="{{ request('start_date') }}">
                </div>
                
                <div>
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">End Date</label>
                    <input type="date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           id="end_date" name="end_date" value="{{ request('end_date') }}">
                </div>
            </div>
            
            <div class="flex space-x-2">
                <button type="submit" class="btn-primary flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="{{ route('admin.payments.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Payments Table Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-table mr-2"></i> Payments List
            <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                (Currency: {{ $settings->currency_code }})
            </span>
        </h3>
        
        <div class="mb-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $payments->firstItem() ?? 0 }} to {{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }} results
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Transaction ID</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Landlord</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Details</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Provider</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Reference</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $payment->transaction_id }}</p>
                            @if($payment->invoices->count() > 0)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $payment->invoices->count() }} invoice(s)
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $payment->payment_date ? $payment->payment_date->format('M d, Y H:i') : $payment->created_at->format('M d, Y H:i') }}
                            </p>
                            @if(!$payment->payment_date && $payment->status === 'pending')
                            <p class="text-xs text-warning">Awaiting payment</p>
                            @endif
                            @if($payment->isExpiringSoon())
                            <p class="text-xs text-danger">Expiring soon</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $payment->landlord->name }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">{{ $payment->landlord->email }}</p>
                        </td>
                        <td class="p-3">
                            <div class="property-info">
                                <p class="font-medium text-primary mb-1">
                                    {{ $payment->property->property_name ?? $payment->property->name }}
                                </p>
                                <div class="text-xs space-y-1" style="color: var(--text-secondary);">
                                    @if($payment->property->registration_pattern)
                                    <div class="flex items-center">
                                        <i class="fas fa-hashtag mr-1 text-xs"></i>
                                        <span>{{ $payment->property->registration_pattern }}</span>
                                    </div>
                                    @endif
                                    @if($payment->property->house_number)
                                    <div class="flex items-center">
                                        <i class="fas fa-home mr-1 text-xs"></i>
                                        <span>House: {{ $payment->property->house_number }}</span>
                                    </div>
                                    @endif
                                    @if($payment->property->street_name)
                                    <div class="flex items-center">
                                        <i class="fas fa-road mr-1 text-xs"></i>
                                        <span>{{ $payment->property->street_name }}</span>
                                    </div>
                                    @endif
                                    @if($payment->property->zone)
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt mr-1 text-xs"></i>
                                        <span>{{ $payment->property->zone }}</span>
                                        @if($payment->property->section)
                                        <span class="mx-1">•</span>
                                        <span>{{ $payment->property->section }}</span>
                                        @endif
                                    </div>
                                    @endif
                                    @if($payment->property->digital_address)
                                    <div class="flex items-center">
                                        <i class="fas fa-map-pin mr-1 text-xs"></i>
                                        <span class="font-mono">{{ $payment->property->digital_address }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <p class="font-medium text-success">
                                {{ $settings->formatAmount($payment->amount) }}
                            </p>
                            @php
                                $metadata = $payment->metadata ?? [];
                                $paymentType = $metadata['payment_type'] ?? 'single';
                            @endphp
                            <p class="text-xs" style="color: var(--text-secondary);">
                                @if($paymentType === 'bulk')
                                    Bulk ({{ $metadata['months'] ?? 1 }} months)
                                @elseif($paymentType === 'invoices')
                                    Invoices ({{ count($metadata['invoices'] ?? []) }})
                                @else
                                    Single
                                @endif
                            </p>
                        </td>
                        <td class="p-3">
                            @php
                                // Updated provider colors for new gateways
                                $providerColors = [
                                    'expresspay' => 'primary',
                                    'hubtel' => 'info', 
                                    'paystack' => 'success',
                                    'flutterwave' => 'warning'
                                ];
                                $providerColor = $providerColors[$payment->payment_provider] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $providerColor }}-rgb), 0.2); color: var(--{{ $providerColor }});">
                                {{ $paymentProviders[$payment->payment_provider] ?? $payment->provider_display }}
                            </span>
                            @if($payment->isMobileMoney())
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $payment->phone_number ?? 'N/A' }}
                            </p>
                            @endif
                            @if($payment->isOnline())
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $payment->email ?? 'N/A' }}
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
                                    'partially_refunded' => 'info'
                                ];
                                $statusColor = $statusColors[$payment->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                {{ $payment->status_display }}
                            </span>
                            @if($payment->isOverdue())
                            <p class="text-xs text-danger mt-1">Overdue</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $payment->transaction_reference ?? 'N/A' }}</p>
                            @if($payment->notes)
                            <p class="text-xs truncate max-w-xs" style="color: var(--text-secondary);" title="{{ $payment->notes }}">
                                {{ Str::limit($payment->notes, 30) }}
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.payments.show', $payment->id) }}" 
                                   class="p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin())
                                <button class="p-2 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Change Status"
                                        onclick="openStatusModal('{{ $payment->id }}', '{{ $payment->status }}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endif
                                @if($payment->canBeCancelled())
                                <button class="p-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="Cancel Payment"
                                        onclick="cancelPayment('{{ $payment->id }}')">
                                    <i class="fas fa-times"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-receipt text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No payments found</p>
                                <p class="text-sm">Try adjusting your filters or check back later.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($payments->hasPages())
        <div class="flex justify-center mt-6">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-md">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Update Payment Status</h3>
            <form id="statusForm" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                    <select class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                            name="status" id="statusSelect" required>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="partially_refunded">Partially Refunded</option>
                    </select>
                </div>
                <div id="paymentDateField" class="mb-4 hidden">
                    <label class="block text-sm mb-2" style="color: var(--text-secondary);">Payment Date</label>
                    <input type="datetime-local" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           name="payment_date" id="paymentDateInput">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentStatusSelect = null;

function openStatusModal(paymentId, currentStatus) {
    const modal = document.getElementById('statusModal');
    const form = document.getElementById('statusForm');
    const statusSelect = document.getElementById('statusSelect');
    const paymentDateField = document.getElementById('paymentDateField');
    
    form.action = `/admin/payments/${paymentId}/update-status`;
    statusSelect.value = currentStatus;
    
    // Store reference to clean up listener
    if (currentStatusSelect) {
        currentStatusSelect.removeEventListener('change', handleStatusChange);
    }
    currentStatusSelect = statusSelect;
    statusSelect.addEventListener('change', handleStatusChange);
    
    // Show payment date field when marking as completed
    function handleStatusChange() {
        if (this.value === 'completed') {
            paymentDateField.classList.remove('hidden');
        } else {
            paymentDateField.classList.add('hidden');
        }
    }
    
    // Trigger initial state
    handleStatusChange.call(statusSelect);
    
    // Set current datetime as default for payment date
    const now = new Date();
    const localDateTime = now.toISOString().slice(0, 16);
    document.getElementById('paymentDateInput').value = localDateTime;
    
    modal.classList.remove('hidden');
}

function closeStatusModal() {
    const modal = document.getElementById('statusModal');
    modal.classList.add('hidden');
}

function cancelPayment(paymentId) {
    if (confirm('Are you sure you want to cancel this payment? This action cannot be undone.')) {
        fetch(`/admin/payments/${paymentId}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
            alert('Failed to cancel payment');
        });
    }
}

// Close modal when clicking outside
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeStatusModal();
    }
});

// Property filter dependency
document.addEventListener('DOMContentLoaded', function() {
    // Set current date as default for end date if not set
    if (!document.getElementById('end_date').value) {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('end_date').value = today;
    }
    
    // Set start date to 30 days ago if not set
    if (!document.getElementById('start_date').value) {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        document.getElementById('start_date').value = thirtyDaysAgo.toISOString().split('T')[0];
    }
    
    // Add real-time search if needed
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }

    // Auto-submit when provider or status changes for quick filtering
    const quickFilters = ['payment_provider', 'status'];
    quickFilters.forEach(filterId => {
        const element = document.getElementById(filterId);
        if (element) {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });

    // Update property dropdown when landlord changes
    const landlordSelect = document.getElementById('landlord_id');
    const propertySelect = document.getElementById('property_id');
    
    if (landlordSelect && propertySelect) {
        landlordSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
});
</script>

<style>
/* Additional styling for better visual hierarchy */
.card {
    transition: box-shadow 0.2s ease-in-out;
}

.card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.btn-primary, .btn-secondary {
    transition: all 0.2s ease-in-out;
}

.btn-primary:hover, .btn-secondary:hover {
    transform: translateY(-1px);
}

/* Status badge improvements */
.px-2.py-1.rounded-full {
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.5px;
}

/* Property information styling */
.property-info {
    min-width: 200px;
}

.property-info .text-xs {
    line-height: 1.3;
}

.property-info i {
    width: 12px;
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
        grid-template-columns: repeat(2, 1fr);
    }
    
    .property-info {
        min-width: 150px;
    }
}

/* Mobile money specific styling */
.mobile-money-badge {
    border-left: 3px solid;
}

/* Property name emphasis */
.text-primary {
    font-weight: 600;
}
</style>
@endsection