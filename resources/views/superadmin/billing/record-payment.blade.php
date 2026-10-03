@extends('layouts.app')

@section('title', 'Make Payment')

@php
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div>
                <h1 class="text-2xl font-bold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i> Make Payment
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Pay the full amount for agreement #{{ $agreement->agreement_number }}
                </p>
            </div>
            <div class="flex gap-3 mt-2 md:mt-0">
                <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-1"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Agreement
                </a>
            </div>
        </div>
    </div>

    <!-- Agreement Summary -->
    <div class="card">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Agreement Number</p>
                    <p class="font-semibold" style="color: var(--text-primary);">{{ $agreement->agreement_number }}</p>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Total Amount</p>
                    <p class="font-semibold" style="color: var(--text-primary);">
                        {{ formatCurrency($agreement->amount, $agreement->currency ?? 'GHS') }}
                    </p>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Amount Paid</p>
                    <p class="font-semibold" style="color: var(--success);">
                        {{ formatCurrency($agreement->amount_received, $agreement->currency ?? 'GHS') }}
                    </p>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Remaining Balance</p>
                    <p class="font-semibold" style="color: var(--warning);">
                        {{ formatCurrency($remaining, $agreement->currency ?? 'GHS') }}
                    </p>
                </div>
                @if($agreement->is_primary_for_billing)
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Status</p>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-primary">
                        <i class="fas fa-crown mr-1"></i> Primary Billing Contact
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="card">
        <div class="p-6">
            @if($remaining <= 0)
            <div class="text-center py-8" style="color: var(--text-secondary);">
                <i class="fas fa-check-circle text-4xl mb-3" style="color: var(--success);"></i>
                <p class="text-lg">This agreement is fully paid!</p>
                <p class="text-sm mt-1">No further payments are required.</p>
                <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                   class="mt-4 inline-block px-4 py-2 rounded-lg text-white btn-primary">
                    <i class="fas fa-eye mr-2"></i> View Agreement
                </a>
            </div>
            @else
            <form method="POST" action="{{ route('superadmin.billing.record-payment', $agreement->id) }}" 
                  class="space-y-4" 
                  id="paymentForm"
                  onsubmit="return validatePaymentForm()">
                @csrf

                <!-- ========================================================== -->
                <!-- ✅ PAYMENT AMOUNT - Fixed to remaining balance -->
                <!-- ========================================================== -->
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                        Payment Amount <span class="text-red-500">*</span>
                    </label>
                    <div class="p-4 rounded-lg" 
                         style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold" style="color: var(--text-primary);">
                                {{ formatCurrency($remaining, $agreement->currency ?? 'GHS') }}
                            </span>
                            <span class="text-xs px-2 py-1 rounded-full badge-info">
                                <i class="fas fa-info-circle mr-1"></i> Full Amount Due
                            </span>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            This is the full remaining balance for this agreement.
                        </p>
                    </div>
                    <!-- Hidden input to submit the full amount -->
                    <input type="hidden" name="amount_paid" value="{{ $remaining }}">
                    <input type="hidden" name="payment_date" value="{{ date('Y-m-d') }}">
                    @error('amount_paid')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ========================================================== -->
                <!-- ✅ ONLINE PAYMENT PROVIDERS ONLY -->
                <!-- ========================================================== -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Select Payment Provider <span class="text-red-500">*</span>
                    </label>
                    
                    @if(isset($onlineProviders) && count($onlineProviders) > 0)
                        @php
                            $hasAvailableOnline = false;
                            foreach($onlineProviders as $provider) {
                                if($provider['available'] && $provider['enabled']) {
                                    $hasAvailableOnline = true;
                                    break;
                                }
                            }
                        @endphp

                        @if($hasAvailableOnline)
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                                @foreach($onlineProviders as $key => $provider)
                                    @if($provider['available'] && $provider['enabled'])
                                    <label class="payment-method-card cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1" 
                                           style="border: 2px solid var(--border-color); border-radius: 12px; padding: 16px; background-color: var(--card-bg);"
                                           for="method_{{ $key }}">
                                        <input type="radio" 
                                               name="payment_method" 
                                               id="method_{{ $key }}"
                                               value="{{ $key }}"
                                               class="hidden payment-method-radio"
                                               data-provider="{{ $key }}"
                                               data-provider-name="{{ $provider['name'] }}"
                                               {{ old('payment_method') == $key ? 'checked' : '' }}
                                               required>
                                        
                                        <div class="flex flex-col items-center text-center">
                                            <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                                                 style="background-color: {{ $provider['color'] ?? 'var(--primary)' }}20;">
                                                <i class="fas {{ $provider['icon'] ?? 'fa-credit-card' }} text-2xl" 
                                                   style="color: {{ $provider['color'] ?? 'var(--primary)' }};"></i>
                                            </div>
                                            <p class="font-semibold text-sm" style="color: var(--text-primary);">
                                                {{ $provider['name'] }}
                                            </p>
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $provider['description'] ?? '' }}
                                            </p>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs mt-2" 
                                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-globe mr-1"></i> Online
                                            </span>
                                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center radio-circle mt-2"
                                                 style="border-color: var(--border-color);">
                                                <div class="w-2.5 h-2.5 rounded-full hidden radio-dot"
                                                     style="background-color: var(--primary);"></div>
                                            </div>
                                        </div>
                                    </label>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 rounded-lg text-center" 
                                 style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--warning);"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <strong>No online payment providers are currently configured.</strong>
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Please contact the developer to enable online payment options.
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="p-4 rounded-lg text-center" 
                             style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--warning);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <strong>No online payment providers are available.</strong>
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Please contact the developer to set up online payment options.
                            </p>
                        </div>
                    @endif
                    
                    @error('payment_method')
                        <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ========================================================== -->
                <!-- ✅ TRANSACTION REFERENCE - Hidden for online payments -->
                <!-- ========================================================== -->
                <input type="hidden" name="transaction_reference" value="ONLINE-{{ strtoupper(Str::random(10)) }}">

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                        Notes (Optional)
                    </label>
                    <textarea name="notes" 
                              id="notes"
                              rows="3"
                              class="w-full rounded-lg border px-3 py-2"
                              style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);"
                              placeholder="Any additional notes about this payment">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ========================================================== -->
                <!-- ✅ ONLINE PAYMENT WARNING -->
                <!-- ========================================================== -->
                <div id="online_payment_warning" class="p-4 rounded-lg" 
                     style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-shield-alt mt-1 mr-3" style="color: var(--warning);"></i>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <strong>Online Payment:</strong> You will be redirected to the selected payment provider's secure page to complete the transaction.
                            </p>
                            <div class="flex flex-wrap gap-3 mt-2">
                                <span class="text-xs flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-lock mr-1" style="color: var(--success);"></i> Secured connection
                                </span>
                                <span class="text-xs flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-credit-card mr-1" style="color: var(--info);"></i> Card & mobile money supported
                                </span>
                                <span class="text-xs flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> Instant confirmation
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                    <button type="submit" 
                            id="submitPaymentBtn"
                            class="px-6 py-2 rounded-lg text-white font-medium transition-all duration-200 hover:transform hover:-translate-y-1"
                            style="background-color: var(--success); border: 1px solid var(--success);">
                        <i class="fas fa-credit-card mr-2"></i> Pay Now
                    </button>
                    <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                       class="px-6 py-2 rounded-lg font-medium"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);">
                        Cancel
                    </a>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all payment method radio buttons
    const paymentRadios = document.querySelectorAll('.payment-method-radio');
    const submitBtn = document.getElementById('submitPaymentBtn');
    
    // Function to update UI based on selected payment method
    function updatePaymentMethodUI(selectedRadio) {
        const providerName = selectedRadio.dataset.providerName || 'Provider';
        
        // Update submit button with provider name
        submitBtn.innerHTML = '<i class="fas fa-credit-card mr-2"></i> Pay with ' + providerName;
        
        // Update radio circle styling
        document.querySelectorAll('.payment-method-card').forEach(card => {
            const radio = card.querySelector('.payment-method-radio');
            const circle = card.querySelector('.radio-circle');
            const dot = card.querySelector('.radio-dot');
            
            if (radio && radio.checked) {
                card.style.borderColor = 'var(--primary)';
                card.style.boxShadow = '0 0 0 3px rgba(var(--primary-rgb), 0.1)';
                circle.style.borderColor = 'var(--primary)';
                dot.classList.remove('hidden');
            } else {
                card.style.borderColor = 'var(--border-color)';
                card.style.boxShadow = 'none';
                circle.style.borderColor = 'var(--border-color)';
                dot.classList.add('hidden');
            }
        });
    }
    
    // Add click handlers to payment method cards
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.addEventListener('click', function(e) {
            const radio = this.querySelector('.payment-method-radio');
            if (radio) {
                radio.checked = true;
                // Trigger change event
                const event = new Event('change');
                radio.dispatchEvent(event);
            }
        });
    });
    
    // Add change handler to radio buttons
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                updatePaymentMethodUI(this);
            }
        });
    });
    
    // Check if a payment method is already selected
    const checkedRadio = document.querySelector('.payment-method-radio:checked');
    if (checkedRadio) {
        updatePaymentMethodUI(checkedRadio);
    }
});

// Enhanced validation function
function validatePaymentForm() {
    const paymentMethod = document.querySelector('.payment-method-radio:checked');
    
    if (!paymentMethod) {
        showToast('Please select a payment provider.', 'error');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitPaymentBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Redirecting to payment...';
    
    return true;
}

function formatCurrency(amount) {
    return 'GH₵' + amount.toFixed(2);
}

// Auto-show toast messages from session
document.addEventListener('DOMContentLoaded', function() {
    @if(session('success'))
        showToast('{{ session('success') }}', 'success');
    @endif
    
    @if(session('error'))
        showToast('{{ session('error') }}', 'error');
    @endif
    
    @if(session('warning'))
        showToast('{{ session('warning') }}', 'warning');
    @endif
});
</script>
@endpush

@push('styles')
<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.payment-method-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.payment-method-card:hover {
    border-color: var(--primary) !important;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.1);
    transform: translateY(-2px);
}

.payment-method-card:has(input:checked) {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.radio-circle {
    transition: all 0.2s ease;
}

.radio-dot {
    transition: all 0.2s ease;
}

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush