{{-- resources/views/developer/invoices/pay.blade.php --}}
@extends('layouts.dev')

@section('title', 'Pay Invoice #' . $invoice->invoice_number)

@section('content')
<div class="max-w-2xl mx-auto py-8">
    <!-- Invoice Summary Card -->
    <div class="card p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-file-invoice mr-2"></i> Pay Invoice
        </h2>
        
        <div class="space-y-3">
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Invoice Number</span>
                <span class="font-medium" style="color: var(--text-primary);">#{{ $invoice->invoice_number }}</span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Amount</span>
                <span class="font-bold text-lg" style="color: var(--primary);">
                    {{ $invoice->currency ?? 'GHS' }} {{ number_format($invoice->amount, 2) }}
                </span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Due Date</span>
                <span class="font-medium" style="color: var(--text-primary);">
                    {{ $invoice->due_date ? $invoice->due_date->format('F j, Y') : 'N/A' }}
                </span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Description</span>
                <span class="font-medium" style="color: var(--text-primary);">{{ $invoice->description }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span style="color: var(--text-secondary);">Status</span>
                <span class="px-2 py-1 rounded-full text-sm {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ ucfirst($invoice->status) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Payment Provider Selection -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-credit-card mr-2"></i> Select Payment Method
        </h3>

        <form id="paymentForm" method="POST" action="{{ route('developer.payments.initialize-invoice-payment', $invoice->id) }}">
            @csrf
            
            <div class="space-y-3">
                @foreach($providers as $key => $provider)
                <label class="flex items-center p-4 rounded-lg border cursor-pointer transition-all duration-200 hover:shadow-md provider-option"
                       style="border-color: var(--border-color);"
                       data-provider="{{ $key }}">
                    <input type="radio" 
                           name="provider" 
                           value="{{ $key }}"
                           class="mr-3 w-4 h-4"
                           style="accent-color: {{ $provider['color'] }};">
                    <div class="flex items-center flex-1">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: {{ $provider['color'] }}20;">
                            <i class="fas {{ $provider['icon'] }}" style="color: {{ $provider['color'] }};"></i>
                        </div>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $provider['name'] }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Secure online payment</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs px-2 py-0.5 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Available
                        </span>
                    </div>
                </label>
                @endforeach
            </div>

            <!-- Email Field -->
            <div class="mt-4">
                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-1"></i> Email for Receipt
                </label>
                <input type="email" 
                       name="email" 
                       id="paymentEmail"
                       value="{{ old('email', $invoice->billing_contact_email ?? '') }}"
                       class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Enter email for payment receipt"
                       required>
            </div>

            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-lock mr-2 mt-0.5" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <strong>Secure Payment</strong> — Your payment is encrypted and secure. 
                            You will be redirected to the payment provider to complete the transaction.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <a href="{{ route('developer.billing.dashboard') }}" 
                   class="px-4 py-2 rounded-lg border transition-all duration-200 hover:bg-gray-50"
                   style="border-color: var(--border-color); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel
                </a>
                <button type="submit" 
                        id="payNowBtn"
                        class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                        style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                    <i class="fas fa-lock mr-2"></i> Pay Now
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('paymentForm');
    const payBtn = document.getElementById('payNowBtn');
    const providerOptions = document.querySelectorAll('.provider-option');
    
    // Provider selection highlight
    providerOptions.forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Remove highlight from all
            providerOptions.forEach(opt => {
                opt.style.borderColor = 'var(--border-color)';
                opt.style.backgroundColor = 'var(--bg-secondary)';
            });
            
            // Highlight selected
            this.style.borderColor = this.querySelector('input[type="radio"]').style.accentColor || 'var(--primary)';
            this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        });
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedProvider = document.querySelector('input[name="provider"]:checked');
        if (!selectedProvider) {
            showToast('Please select a payment provider', 'warning');
            return;
        }
        
        const email = document.getElementById('paymentEmail').value;
        if (!email) {
            showToast('Please enter your email address', 'warning');
            return;
        }
        
        // Disable button and show loading
        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect to payment gateway
                if (data.authorization_url) {
                    window.location.href = data.authorization_url;
                } else {
                    showToast('Payment initialized successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || '{{ route("developer.billing.dashboard") }}';
                    }, 2000);
                }
            } else {
                showToast(data.message || 'Payment initialization failed', 'error');
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="fas fa-lock mr-2"></i> Pay Now';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-lock mr-2"></i> Pay Now';
        });
    });
});

function showToast(message, type = 'info') {
    // You can use your existing toast function here
    alert(message); // Simple fallback
}
</script>
@endsection