@extends('layouts.tenant')

@section('title', 'Invoice Details')

@section('content')
<div class="grid grid-cols-1 gap-6">
    <!-- Header & Navigation -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <a href="{{ route('tenant.invoices.index') }}" class="mr-4 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Invoice Details</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">View and manage your invoice</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tenant.invoices.print', $tenantInvoice->id) }}" 
                   class="btn-secondary flex items-center" target="_blank">
                    <i class="fas fa-print mr-2"></i> Print
                </a>
                <a href="{{ route('tenant.invoices.download', $tenantInvoice->id) }}" 
                   class="btn-secondary flex items-center">
                    <i class="fas fa-download mr-2"></i> Download PDF
                </a>
                @if($tenantInvoice->status === 'pending')
                    <a href="{{ route('tenant.payments.make', ['invoice_id' => $tenantInvoice->id]) }}" 
                       class="btn-primary flex items-center">
                        <i class="fas fa-credit-card mr-2"></i> Pay Now
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Alert -->
    @if($tenantInvoice->status === 'overdue')
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-xl mr-3"></i>
            <div>
                <p class="font-bold">Payment Overdue!</p>
                <p class="text-sm">This invoice is overdue by {{ $tenantInvoice->days_overdue }} days. 
                   @if($tenantInvoice->penalty_amount > 0)
                       A penalty of {{ $system_settings->formatAmount($tenantInvoice->penalty_amount) }} has been applied.
                   @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    @if($tenantInvoice->status === 'pending' && $tenantInvoice->within_grace_period)
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded" role="alert">
        <div class="flex items-center">
            <i class="fas fa-hourglass-half text-xl mr-3"></i>
            <div>
                <p class="font-bold">Grace Period Active!</p>
                <p class="text-sm">You have {{ $tenantInvoice->days_in_grace }} day{{ $tenantInvoice->days_in_grace != 1 ? 's' : '' }} left to pay without penalty.
                   Payment due by {{ $tenantInvoice->grace_period_end->format('M d, Y') }}.</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Invoice Main Card -->
    <div class="card p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column - Invoice Info -->
            <div>
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Invoice Information</h3>
                        <span class="px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: rgba(var(--{{ $tenantInvoice->status_color }}-rgb), 0.2); color: var(--{{ $tenantInvoice->status_color }});">
                            <i class="{{ $tenantInvoice->status_icon }} mr-1"></i>
                            {{ ucfirst($tenantInvoice->status) }}
                        </span>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Invoice Number</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->invoice_number }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Period</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->month_name }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Issue Date</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Due Date</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->due_date->format('M d, Y') }}</span>
                        </div>
                        @if($tenantInvoice->status === 'paid' && $tenantInvoice->payment_date)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Payment Date</span>
                            <span class="font-medium text-success">{{ $tenantInvoice->payment_date->format('M d, Y') }}</span>
                        </div>
                        @endif
                        @if($tenantInvoice->payment_method)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Payment Method</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $tenantInvoice->payment_method)) }}</span>
                        </div>
                        @endif
                        @if($tenantInvoice->payment_reference)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Transaction Reference</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->payment_reference }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Property Details</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Property</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->propertyUnit->property->property_name ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Unit Number</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->propertyUnit->unit_number ?? 'N/A' }}</span>
                        </div>
                        @if($tenantInvoice->propertyUnit->property->address)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Address</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $tenantInvoice->propertyUnit->property->address }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column - Amount Breakdown -->
            <div>
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Amount Breakdown</h3>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Community Development Dues</span>
                        <span class="font-medium" style="color: var(--text-primary);">{{ $system_settings->formatAmount($tenantInvoice->community_dues) }}</span>
                    </div>
                    @if($tenantInvoice->additional_charges > 0)
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Additional Charges</span>
                        <span class="font-medium text-warning">{{ $system_settings->formatAmount($tenantInvoice->additional_charges) }}</span>
                    </div>
                    @endif
                    @if($tenantInvoice->penalty_amount > 0)
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Late Payment Penalty</span>
                        <span class="font-medium text-danger">{{ $system_settings->formatAmount($tenantInvoice->penalty_amount) }}</span>
                    </div>
                    @endif
                    @if($tenantInvoice->discount > 0)
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Discount</span>
                        <span class="font-medium text-success">-{{ $system_settings->formatAmount($tenantInvoice->discount) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between py-3 border-t-2 border-b-2" style="border-color: var(--border-color);">
                        <span class="text-base font-semibold" style="color: var(--text-primary);">Total Amount</span>
                        <span class="text-xl font-bold" style="color: var(--primary);">{{ $system_settings->formatAmount($tenantInvoice->total_amount) }}</span>
                    </div>
                    @if($tenantInvoice->paid_amount > 0)
                    <div class="flex justify-between py-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Amount Paid</span>
                        <span class="font-medium text-success">{{ $system_settings->formatAmount($tenantInvoice->paid_amount) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Balance Due</span>
                        <span class="font-medium text-danger">{{ $system_settings->formatAmount($tenantInvoice->balance) }}</span>
                    </div>
                    @endif
                </div>

                @if($tenantInvoice->status === 'pending')
                <div class="mt-6 p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Payment Information</span>
                    </div>
                    @if($tenantInvoice->within_grace_period)
                    <p class="text-xs" style="color: var(--warning);">
                        <i class="fas fa-hourglass-start mr-1"></i>
                        Grace period: {{ $tenantInvoice->days_in_grace }} day{{ $tenantInvoice->days_in_grace != 1 ? 's' : '' }} remaining
                    </p>
                    @elseif($tenantInvoice->after_grace_period)
                    <p class="text-xs text-danger mb-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Grace period has ended. Late payment penalty of {{ $tenant_late_payment_percentage }}% applies.
                    </p>
                    @endif
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-check mr-1"></i>
                        Please make payment by {{ $tenantInvoice->due_date->format('M d, Y') }} to avoid penalties.
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- Description Section -->
        @if($tenantInvoice->description)
        <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
            <h4 class="text-sm font-semibold mb-2" style="color: var(--text-primary);">Description</h4>
            <p class="text-sm" style="color: var(--text-secondary);">{{ $tenantInvoice->description }}</p>
        </div>
        @endif

        <!-- Notes Section -->
        @if($tenantInvoice->notes)
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
            <h4 class="text-sm font-semibold mb-2" style="color: var(--text-primary);">Notes</h4>
            <p class="text-sm" style="color: var(--text-secondary);">{{ $tenantInvoice->notes }}</p>
        </div>
        @endif
    </div>

    <!-- Payment Instructions Card -->
    @if($tenantInvoice->status === 'pending' && !empty($paymentInstructions))
    <div class="card p-6">
        <h3 class="font-medium mb-4" style="color: var(--text-primary);">Payment Instructions</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if(isset($paymentInstructions['general']))
            <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                <div class="flex items-center mb-3">
                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                    <h4 class="font-medium" style="color: var(--text-primary);">Important Information</h4>
                </div>
                <p class="text-sm" style="color: var(--text-secondary);">{{ $paymentInstructions['general'] }}</p>
            </div>
            @endif
            
            @if(isset($paymentInstructions['mobile_money']))
            <div class="p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                <div class="flex items-center mb-3">
                    <i class="fas fa-mobile-alt mr-2" style="color: var(--success);"></i>
                    <h4 class="font-medium" style="color: var(--text-primary);">Mobile Money Payment</h4>
                </div>
                <div class="space-y-2 text-sm" style="color: var(--text-secondary);">
                    <p><strong>Provider:</strong> {{ ucfirst($paymentInstructions['mobile_money']['provider']) }}</p>
                    <p><strong>Number:</strong> {{ $paymentInstructions['mobile_money']['number'] }}</p>
                    <p><strong>Account Name:</strong> {{ $paymentInstructions['mobile_money']['name'] }}</p>
                    @if(isset($paymentInstructions['mobile_money']['network']))
                    <p><strong>Network:</strong> {{ strtoupper($paymentInstructions['mobile_money']['network']) }}</p>
                    @endif
                </div>
            </div>
            @endif
            
            @if(isset($paymentInstructions['bank']))
            <div class="p-4 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                <div class="flex items-center mb-3">
                    <i class="fas fa-university mr-2" style="color: var(--primary);"></i>
                    <h4 class="font-medium" style="color: var(--text-primary);">Bank Transfer</h4>
                </div>
                <div class="space-y-2 text-sm" style="color: var(--text-secondary);">
                    <p><strong>Bank:</strong> {{ $paymentInstructions['bank']['bank_name'] }}</p>
                    <p><strong>Account Number:</strong> {{ $paymentInstructions['bank']['account_number'] }}</p>
                    <p><strong>Account Name:</strong> {{ $paymentInstructions['bank']['account_name'] }}</p>
                    @if(isset($paymentInstructions['bank']['branch']))
                    <p><strong>Branch:</strong> {{ $paymentInstructions['bank']['branch'] }}</p>
                    @endif
                </div>
            </div>
            @endif
        </div>
        
        @if(isset($paymentInstructions['custom_message']))
        <div class="mt-4 p-4 rounded text-center" style="background-color: rgba(var(--warning-rgb), 0.05);">
            <p class="text-sm italic" style="color: var(--text-secondary);">"{{ $paymentInstructions['custom_message'] }}"</p>
        </div>
        @endif
    </div>
    @endif

    <!-- Payment History Card (if payments exist) -->
    @if($tenantInvoice->payments && $tenantInvoice->payments->count() > 0)
    <div class="card p-6">
        <h3 class="font-medium mb-4" style="color: var(--text-primary);">Payment History</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-2 text-sm font-medium" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left p-2 text-sm font-medium" style="color: var(--text-secondary);">Method</th>
                        <th class="text-left p-2 text-sm font-medium" style="color: var(--text-secondary);">Reference</th>
                        <th class="text-right p-2 text-sm font-medium" style="color: var(--text-secondary);">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tenantInvoice->payments as $payment)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-2 text-sm" style="color: var(--text-primary);">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                        <td class="p-2 text-sm" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td class="p-2 text-sm" style="color: var(--text-primary);">{{ $payment->transaction_reference ?? 'N/A' }}</td>
                        <td class="p-2 text-sm text-right font-medium" style="color: var(--success);">{{ $system_settings->formatAmount($payment->amount) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Need Help Card -->
    <div class="card p-6 text-center">
        <i class="fas fa-question-circle text-3xl mb-3" style="color: var(--primary);"></i>
        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Need Help?</h4>
        <p class="text-sm mb-4" style="color: var(--text-secondary);">
            If you have any questions about this invoice, please contact our support team.
        </p>
        <a href="{{ route('tenant.support') }}" class="btn-secondary inline-flex items-center">
            <i class="fas fa-envelope mr-2"></i> Contact Support
        </a>
    </div>
</div>

<!-- Request Receipt Modal -->
<div id="requestReceiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Request Payment Receipt</h3>
                <button type="button" onclick="closeReceiptModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="requestReceiptForm" method="POST" action="{{ route('tenant.invoices.request-receipt', $tenantInvoice->id) }}">
                @csrf
                <input type="hidden" name="invoice_id" value="{{ $tenantInvoice->id }}">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Your Email</label>
                        <input type="email" name="email" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ auth()->user()->email }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Additional Message (Optional)</label>
                        <textarea name="message" rows="3" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Enter any additional message..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeReceiptModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--primary);">
                        Request Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
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
});

function openReceiptModal() {
    const modal = document.getElementById('requestReceiptModal');
    modal.classList.remove('hidden');
}

function closeReceiptModal() {
    const modal = document.getElementById('requestReceiptModal');
    modal.classList.add('hidden');
    const form = document.getElementById('requestReceiptForm');
    if (form) form.reset();
}

// Close modal when clicking outside
document.getElementById('requestReceiptModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceiptModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('requestReceiptModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeReceiptModal();
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
    display: inline-flex;
    align-items: center;
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
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.btn-secondary:hover {
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
#requestReceiptModal {
    transition: opacity 0.3s ease;
}

#requestReceiptModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#requestReceiptModal:not(.hidden) {
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    .btn-primary,
    .btn-secondary {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}

/* Print styles */
@media print {
    .btn-primary,
    .btn-secondary,
    .btn-info,
    .card .flex .flex-wrap,
    #requestReceiptModal,
    .fixed,
    button,
    a[href*="pay"],
    a[href*="print"],
    a[href*="download"] {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    body {
        background: white;
        padding: 20px;
    }
}
</style>
@endsection