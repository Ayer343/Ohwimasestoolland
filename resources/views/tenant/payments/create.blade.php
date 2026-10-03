@extends('layouts.tenant')

@section('title', 'Make Payment')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Make Payment</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Pay your monthly dues and community fees</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('tenant.invoices.my-invoices') }}" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Payment Provider Status Alert -->
    @if(empty($availableMethods))
        <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid var(--danger);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">No Payment Methods Available</h3>
                    <p class="mt-1" style="color: var(--danger);">All payment providers are currently unavailable. Please contact the administrator.</p>
                </div>
            </div>
        </div>
    @else
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
        @if($errors->any())
            <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid var(--danger);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                    <div>
                        <h3 class="font-semibold" style="color: var(--danger);">Please fix the following errors:</h3>
                        <ul class="mt-1 list-disc list-inside" style="color: var(--danger);">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Pre-selected Invoice Notification -->
        @if(isset($preSelectedInvoice) && $preSelectedInvoice)
        <div class="card p-6 mb-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
            <div class="flex items-start">
                <i class="fas fa-info-circle mr-3 text-xl mt-1" style="color: var(--info);"></i>
                <div class="flex-1">
                    <h3 class="font-semibold" style="color: var(--info);">Invoice Pre-selected</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                        You're about to pay for invoice <strong>#{{ $preSelectedInvoice->invoice_number }}</strong>
                        for period <strong>{{ \Carbon\Carbon::parse($preSelectedInvoice->period . '-01')->format('F Y') }}</strong>.
                    </p>
                    <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Amount Due:</span>
                            <span class="text-lg font-semibold" style="color: var(--success);">{{ $settings->formatAmount($preSelectedInvoice->balance) }}</span>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Due Date:</span>
                            <span class="text-sm" style="color: var(--text-primary);">{{ $preSelectedInvoice->due_date->format('M d, Y') }}</span>
                        </div>
                        @if($preSelectedInvoice->isOverdue())
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Status:</span>
                            <span class="text-sm px-2 py-1 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Payment Form Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Details</h3>
            
            <form action="{{ route('tenant.payments.process') }}" method="POST" id="payment-form" novalidate>
                @csrf
                
                <!-- Invoice Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">Select Invoice to Pay</label>
                    
                    @if($outstandingInvoices->count() > 0)
                        <div class="grid grid-cols-1 gap-3">
                            @foreach($outstandingInvoices as $invoice)
                            @php
                                $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                $isSelected = isset($preSelectedInvoice) && $preSelectedInvoice->id == $invoice->id;
                            @endphp
                            <div class="relative">
                                <input type="radio" id="invoice-{{ $invoice->id }}" name="invoice_id" value="{{ $invoice->id }}" 
                                       class="sr-only invoice-radio" 
                                       data-amount="{{ $invoice->balance }}"
                                       data-period="{{ $periodDisplay }}"
                                       data-invoice-number="{{ $invoice->invoice_number }}"
                                       {{ $isSelected ? 'checked' : '' }}
                                       {{ $invoice->status === 'paid' ? 'disabled' : '' }}>
                                <label for="invoice-{{ $invoice->id }}" 
                                       class="flex flex-col md:flex-row md:items-center justify-between p-4 border-2 rounded-lg cursor-pointer invoice-label transition-all duration-200"
                                       style="border-color: var(--border-color);">
                                    <div class="flex items-start space-x-3">
                                        <div class="mt-1">
                                            <i class="fas fa-file-invoice text-lg" style="color: var(--primary);"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold" style="color: var(--text-primary);">
                                                {{ $periodDisplay }}
                                            </p>
                                            <p class="text-sm" style="color: var(--text-secondary);">
                                                Invoice #{{ $invoice->invoice_number }}
                                            </p>
                                            @if($invoice->propertyUnit && $invoice->propertyUnit->property)
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-building mr-1"></i> 
                                                {{ $invoice->propertyUnit->property->property_name }} - Unit {{ $invoice->propertyUnit->unit_number }}
                                            </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-3 md:mt-0 text-right">
                                        <p class="text-lg font-semibold" style="color: var(--success);">
                                            {{ $settings->formatAmount($invoice->balance) }}
                                        </p>
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            Due: {{ $invoice->due_date->format('M d, Y') }}
                                            @if($invoice->isOverdue())
                                                <span class="ml-2" style="color: var(--danger);">
                                                    <i class="fas fa-clock"></i> Overdue
                                                </span>
                                            @endif
                                        </p>
                                        @if($invoice->penalty_amount > 0)
                                        <p class="text-xs mt-1" style="color: var(--danger);">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            +{{ $settings->formatAmount($invoice->penalty_amount) }} penalty
                                        </p>
                                        @endif
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center p-8 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <i class="fas fa-check-circle text-4xl mb-3" style="color: var(--success);"></i>
                            <p class="text-lg font-medium" style="color: var(--text-primary);">No Outstanding Invoices</p>
                            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                You have no pending payments. Thank you for keeping your account up to date!
                            </p>
                            <a href="{{ route('tenant.invoices.my-invoices') }}" class="mt-4 inline-block px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                                <i class="fas fa-eye mr-2"></i> View Invoice History
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Payment Provider Selection -->
                @if($outstandingInvoices->count() > 0)
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Provider</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($availableMethods as $provider => $displayName)
                        <div class="relative">
                            <input type="radio" id="provider-{{ $provider }}" name="payment_provider" value="{{ $provider }}" 
                                   class="sr-only payment-provider-radio" 
                                   {{ $loop->first ? 'checked' : '' }} 
                                   {{ isset($gatewayStatuses[$provider]) && !$gatewayStatuses[$provider]['available'] ? 'disabled' : '' }}>
                            <label for="provider-{{ $provider }}" 
                                   class="flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer payment-provider-label transition-all duration-200 
                                          {{ isset($gatewayStatuses[$provider]) && !$gatewayStatuses[$provider]['available'] ? 'opacity-50 cursor-not-allowed' : '' }}" 
                                   style="border-color: var(--border-color);">
                                <i class="fas {{ $gatewayStatuses[$provider]['icon'] ?? 'fa-wallet' }} text-2xl mb-2" 
                                   style="color: {{ $gatewayStatuses[$provider]['color'] ?? 'var(--primary)' }};" aria-hidden="true"></i>
                                <span class="font-semibold text-center" style="color: var(--text-primary);">{{ $displayName }}</span>
                                @if(isset($gatewayStatuses[$provider]))
                                    <span class="text-xs mt-2 px-2 py-1 rounded-full 
                                        {{ $gatewayStatuses[$provider]['available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $gatewayStatuses[$provider]['available'] ? '✓ Available' : '✗ Unavailable' }}
                                    </span>
                                @endif
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Provider-specific Details -->
                <div id="provider-details" class="mb-6">
                    <!-- Mobile Money Providers (ExpressPay, Hubtel, Flutterwave) -->
                    <div id="mobile-money-details" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="phone_number" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                    Phone Number *
                                </label>
                                <input type="text" name="phone_number" id="phone_number" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" 
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., 0551234567" aria-describedby="phone-help"
                                       value="{{ old('phone_number', auth()->user()->phone ?? '') }}">
                                <p id="phone-help" class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Enter your mobile money number. You will receive a prompt on this number.
                                </p>
                                @error('phone_number')
                                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Currency</p>
                                    <p class="text-lg font-semibold mt-1" style="color: var(--success);">{{ $settings->currency_code ?? 'GHS' }} - {{ $settings->currency_symbol ?? '₵' }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">System default currency</p>
                                </div>
                                <input type="hidden" name="currency" value="{{ $settings->currency_code ?? 'GHS' }}">
                            </div>
                        </div>

                        <!-- Mobile Money Instructions -->
                        <div id="mobile-money-instructions" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                            <h5 class="font-semibold mb-2" style="color: var(--info);" id="mobile-money-instructions-title">Payment Instructions</h5>
                            <div class="text-sm space-y-2" style="color: var(--text-secondary);" id="mobile-money-instructions-content">
                                <!-- Instructions will be loaded dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Paystack Details -->
                    <div id="paystack-details" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                    Email Address *
                                </label>
                                <input type="email" name="email" id="email" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" 
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="your@email.com" aria-describedby="email-help"
                                       value="{{ old('email', auth()->user()->email) }}">
                                <p id="email-help" class="text-xs mt-1" style="color: var(--text-secondary);">Payment receipt will be sent here</p>
                                @error('email')
                                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Currency</p>
                                    <p class="text-lg font-semibold mt-1" style="color: var(--success);">{{ $settings->currency_code ?? 'GHS' }} - {{ $settings->currency_symbol ?? '₵' }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">System default currency</p>
                                </div>
                                <input type="hidden" name="currency" value="{{ $settings->currency_code ?? 'GHS' }}">
                            </div>
                        </div>

                        <!-- Paystack Instructions -->
                        <div id="paystack-instructions" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                            <h5 class="font-semibold mb-2" style="color: var(--info);">Paystack Instructions</h5>
                            <div class="text-sm space-y-2" style="color: var(--text-secondary);">
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will be redirected to Paystack payment page</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Choose your preferred payment method (card, bank, etc.)</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Complete the payment process on the secure Paystack page</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will be redirected back to this site after payment</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment receipt will be sent to your email</p>
                            </div>
                        </div>
                    </div>

                    <!-- Gateway Status Information -->
                    @if(isset($gatewayStatuses))
                    <div id="gateway-status-info" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">Gateway Information</h5>
                        <div id="gateway-status-content" class="text-sm" style="color: var(--text-secondary);">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Payment Amount -->
                <div class="mb-6">
                    <label for="amount" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Amount to Pay ({{ $settings->currency_code ?? 'GHS' }}) *
                    </label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01"
                           class="w-full p-2 border rounded bg-gray-100 cursor-not-allowed" 
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           readonly required aria-describedby="amount-description"
                           value="">
                    <p id="amount-description" class="text-sm mt-1" style="color: var(--text-secondary);">
                        Amount due for selected invoice
                    </p>
                    @error('amount')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Payment Description
                    </label>
                    <input type="text" name="description" id="description" 
                           class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-primary" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Optional payment reference"
                           value="{{ old('description') }}">
                    <p id="description-help" class="text-sm mt-1" style="color: var(--text-secondary);">
                        Optional description for this payment
                    </p>
                    @error('description')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Grace Period Notice -->
                @if(isset($gracePeriodEndDate) && $gracePeriodEndDate)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                    <div class="flex items-start">
                        <i class="fas fa-calendar-alt mr-3 mt-1" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--info);">Grace Period Notice</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Payments made within {{ $settings->tenant_grace_period_days ?? 7 }} days after the due date 
                                will not incur late fees. The grace period ends on 
                                <strong>{{ \Carbon\Carbon::parse($gracePeriodEndDate)->format('M d, Y') }}</strong>.
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded flex items-center transition-all duration-200 hover:opacity-90" 
                            id="submit-button" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> 
                        <span id="submit-text">Process Payment</span>
                        <span id="loading-text" class="hidden">
                            <i class="fas fa-spinner fa-spin mr-2" aria-hidden="true"></i> Processing...
                        </span>
                    </button>
                </div>

                <!-- Payment Confirmation Modal -->
                <div id="payment-confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                    <div class="rounded-lg p-6 max-w-md w-full mx-4" style="background-color: var(--bg-primary); color: var(--text-primary);">
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle text-4xl mb-3" style="color: var(--warning);"></i>
                            <h3 class="text-lg font-semibold">Confirm Payment</h3>
                        </div>
                        <p class="mb-4 text-center" style="color: var(--text-secondary);">
                            You are about to make a payment of 
                            <span id="confirm-amount" class="font-semibold" style="color: var(--success);"></span>.
                        </p>
                        <p class="text-sm text-center mb-6" style="color: var(--text-secondary);">
                            This action cannot be undone.
                        </p>
                        <div class="flex justify-center space-x-3">
                            <button type="button" id="cancel-payment" class="px-4 py-2 rounded transition-all duration-200" 
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                Cancel
                            </button>
                            <button type="button" id="confirm-payment" class="px-4 py-2 rounded transition-all duration-200" 
                                    style="background-color: var(--primary); color: white;">
                                Confirm Payment
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </form>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration status
    const configStatus = @json($configurationStatus ?? []);
    const gatewayStatuses = @json($gatewayStatuses ?? []);
    const settings = @json($settings ?? []);
    const paymentInstructions = @json($paymentInstructions ?? []);
    
    // DOM Elements
    const invoiceRadios = document.querySelectorAll('.invoice-radio');
    const paymentProviderRadios = document.querySelectorAll('.payment-provider-radio');
    const providerLabels = document.querySelectorAll('.payment-provider-label');
    const amountInput = document.getElementById('amount');
    const descriptionInput = document.getElementById('description');
    const submitButton = document.getElementById('submit-button');
    const submitText = document.getElementById('submit-text');
    const loadingText = document.getElementById('loading-text');
    const confirmationModal = document.getElementById('payment-confirmation-modal');
    const confirmAmount = document.getElementById('confirm-amount');
    const cancelPaymentBtn = document.getElementById('cancel-payment');
    const confirmPaymentBtn = document.getElementById('confirm-payment');
    
    // Provider-specific elements
    const mobileMoneyDetails = document.getElementById('mobile-money-details');
    const paystackDetails = document.getElementById('paystack-details');
    const mobileMoneyInstructions = document.getElementById('mobile-money-instructions');
    const paystackInstructions = document.getElementById('paystack-instructions');
    const mobileMoneyInstructionsTitle = document.getElementById('mobile-money-instructions-title');
    const mobileMoneyInstructionsContent = document.getElementById('mobile-money-instructions-content');
    const phoneNumberInput = document.getElementById('phone_number');
    const emailInput = document.getElementById('email');
    const gatewayStatusInfo = document.getElementById('gateway-status-info');
    const gatewayStatusContent = document.getElementById('gateway-status-content');
    
    // Invoice labels styling
    const invoiceLabels = document.querySelectorAll('.invoice-label');
    
    // Helper Functions
    function formatAmount(amount) {
        const formattedAmount = parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: {{ $settings->decimal_places ?? 2 }},
            maximumFractionDigits: {{ $settings->decimal_places ?? 2 }}
        });
        return '{{ $settings->currency_symbol ?? '₵' }}' + formattedAmount;
    }
    
    function formatAmountForInput(amount) {
        return parseFloat(amount).toFixed({{ $settings->decimal_places ?? 2 }});
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function isValidPhoneNumber(phone) {
        const phoneRegex = /^(?:\+233|0)[235]\d{8}$/;
        return phoneRegex.test(phone.replace(/\s+/g, ''));
    }
    
    function getProviderDisplayName(provider) {
        const providers = {
            'expresspay': 'ExpressPay',
            'hubtel': 'Hubtel',
            'paystack': 'Paystack',
            'flutterwave': 'Flutterwave'
        };
        return providers[provider] || provider;
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
    
    function updateSubmitButton(provider) {
        if (!submitText) return;
        
        switch(provider) {
            case 'paystack':
                submitText.textContent = 'Pay with Paystack';
                break;
            case 'expresspay':
                submitText.textContent = 'Pay with ExpressPay';
                break;
            case 'hubtel':
                submitText.textContent = 'Pay with Hubtel';
                break;
            case 'flutterwave':
                submitText.textContent = 'Pay with Flutterwave';
                break;
            default:
                submitText.textContent = 'Process Payment';
        }
    }
    
    function resetSubmitButton() {
        if (submitText) submitText.classList.remove('hidden');
        if (loadingText) loadingText.classList.add('hidden');
        if (submitButton) submitButton.disabled = false;
    }
    
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'card p-6 mb-4';
        errorDiv.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
        errorDiv.style.border = '1px solid var(--danger)';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">Error</h3>
                    <p class="mt-1" style="color: var(--danger);">${message}</p>
                </div>
            </div>
        `;
        
        const formCard = document.querySelector('.card.p-6');
        if (formCard && formCard.parentNode) {
            formCard.parentNode.insertBefore(errorDiv, formCard);
        }
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
        
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function showConfirmationModal() {
        const amount = parseFloat(amountInput.value);
        if (confirmAmount) confirmAmount.textContent = formatAmount(amount);
        if (confirmationModal) confirmationModal.classList.remove('hidden');
    }
    
    function hideConfirmationModal() {
        if (confirmationModal) confirmationModal.classList.add('hidden');
        resetSubmitButton();
    }
    
    // Invoice Selection Handler
    function handleInvoiceSelection() {
        const selectedInvoice = document.querySelector('.invoice-radio:checked');
        
        invoiceLabels.forEach(label => {
            label.style.borderColor = 'var(--border-color)';
            label.style.backgroundColor = 'transparent';
        });
        
        if (selectedInvoice) {
            const selectedLabel = document.querySelector(`label[for="invoice-${selectedInvoice.value}"]`);
            if (selectedLabel) {
                selectedLabel.style.borderColor = 'var(--primary)';
                selectedLabel.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            }
            
            const amount = parseFloat(selectedInvoice.dataset.amount);
            const period = selectedInvoice.dataset.period;
            const invoiceNumber = selectedInvoice.dataset.invoiceNumber;
            
            amountInput.value = formatAmountForInput(amount);
            descriptionInput.value = `Payment for invoice #${invoiceNumber} - ${period}`;
        }
    }
    
    if (invoiceRadios.length > 0) {
        invoiceRadios.forEach(radio => {
            radio.addEventListener('change', handleInvoiceSelection);
        });
        
        if (document.querySelector('.invoice-radio:checked')) {
            handleInvoiceSelection();
        }
    }
    
    // Payment Provider Selection Handler
    function handlePaymentProviderChange() {
        const selectedProvider = document.querySelector('.payment-provider-radio:checked');
        
        providerLabels.forEach(label => {
            label.style.borderColor = 'var(--border-color)';
            label.style.backgroundColor = 'transparent';
        });
        
        if (selectedProvider) {
            const selectedLabel = document.querySelector(`label[for="provider-${selectedProvider.value}"]`);
            if (selectedLabel) {
                selectedLabel.style.borderColor = 'var(--primary)';
                selectedLabel.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            }
            
            const provider = selectedProvider.value;
            
            // Hide all provider details
            if (mobileMoneyDetails) mobileMoneyDetails.classList.add('hidden');
            if (paystackDetails) paystackDetails.classList.add('hidden');
            if (mobileMoneyInstructions) mobileMoneyInstructions.classList.add('hidden');
            if (paystackInstructions) paystackInstructions.classList.add('hidden');
            if (gatewayStatusInfo) gatewayStatusInfo.classList.add('hidden');
            
            // Show selected provider details
            if (['expresspay', 'hubtel', 'flutterwave'].includes(provider)) {
                if (mobileMoneyDetails) mobileMoneyDetails.classList.remove('hidden');
                if (mobileMoneyInstructions) mobileMoneyInstructions.classList.remove('hidden');
                loadMobileMoneyInstructions(provider);
                updateGatewayStatusInfo(provider);
            } else if (provider === 'paystack') {
                if (paystackDetails) paystackDetails.classList.remove('hidden');
                if (paystackInstructions) paystackInstructions.classList.remove('hidden');
                updateGatewayStatusInfo(provider);
            }
            
            updateSubmitButton(provider);
        }
    }
    
    function loadMobileMoneyInstructions(provider) {
        if (paymentInstructions && paymentInstructions[provider]) {
            if (mobileMoneyInstructionsTitle) {
                mobileMoneyInstructionsTitle.textContent = `${getProviderDisplayName(provider)} Instructions`;
            }
            if (mobileMoneyInstructionsContent) {
                mobileMoneyInstructionsContent.innerHTML = paymentInstructions[provider];
            }
        } else if (mobileMoneyInstructionsContent) {
            // Default instructions based on provider
            let instructions = '';
            switch(provider) {
                case 'expresspay':
                    instructions = `
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will receive a prompt on your mobile money number</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Enter your PIN to authorize the payment</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment confirmation will be sent via SMS</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Your invoice will be updated automatically</p>
                    `;
                    break;
                case 'hubtel':
                    instructions = `
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will receive a prompt on your mobile money number</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Enter your PIN to authorize the payment</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment confirmation will be sent via SMS</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Your invoice will be updated automatically</p>
                    `;
                    break;
                case 'flutterwave':
                    instructions = `
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will be redirected to Flutterwave payment page</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Choose your preferred payment method (card, mobile money, bank)</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Complete the payment process on the secure Flutterwave page</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment receipt will be sent to your email</p>
                    `;
                    break;
                default:
                    instructions = `
                        <p><i class="fas fa-info-circle mr-2"></i> Please have your mobile money wallet ready.</p>
                        <p><i class="fas fa-info-circle mr-2"></i> You will receive a prompt to authorize the payment.</p>
                    `;
            }
            mobileMoneyInstructionsContent.innerHTML = instructions;
        }
    }
    
    function updateGatewayStatusInfo(provider) {
        if (!gatewayStatusInfo || !gatewayStatusContent) return;
        
        const status = gatewayStatuses[provider];
        if (!status) return;
        
        gatewayStatusInfo.classList.remove('hidden');
        gatewayStatusContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <span class="text-xs font-medium">Gateway:</span>
                    <span class="text-sm ml-2">${status.name}</span>
                </div>
                <div>
                    <span class="text-xs font-medium">Status:</span>
                    <span class="text-sm ml-2 px-2 py-1 rounded-full ${status.available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${status.available ? '✓ Available' : '✗ Unavailable'}
                    </span>
                </div>
                <div class="md:col-span-2">
                    <span class="text-xs font-medium">Description:</span>
                    <span class="text-sm ml-2">${status.description || 'Payment gateway'}</span>
                </div>
                ${status.color ? `
                <div class="md:col-span-2">
                    <span class="text-xs font-medium">Color:</span>
                    <span class="inline-block ml-2 w-4 h-4 rounded-full" style="background-color: ${status.color};"></span>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    if (paymentProviderRadios.length > 0) {
        paymentProviderRadios.forEach(radio => {
            radio.addEventListener('change', handlePaymentProviderChange);
        });
        
        // Initial load
        handlePaymentProviderChange();
    }
    
    // Form Submission Handler
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (submitText) submitText.classList.add('hidden');
            if (loadingText) loadingText.classList.remove('hidden');
            if (submitButton) submitButton.disabled = true;
            
            // Validate invoice selection
            const selectedInvoice = document.querySelector('.invoice-radio:checked');
            if (!selectedInvoice) {
                showError('Please select an invoice to pay.');
                resetSubmitButton();
                return;
            }
            
            // Validate payment provider
            const paymentProvider = document.querySelector('.payment-provider-radio:checked');
            if (!paymentProvider) {
                showError('Please select a payment provider.');
                resetSubmitButton();
                return;
            }
            
            const providerValue = paymentProvider.value;
            
            // Check if provider is available (enabled in both system settings AND configured)
            if (gatewayStatuses[providerValue] && !gatewayStatuses[providerValue].available) {
                showError('This payment provider is not currently available. Please try another provider.');
                resetSubmitButton();
                return;
            }
            
            // Validate amount
            const amount = parseFloat(amountInput.value);
            if (!amount || amount <= 0) {
                showError('Invalid payment amount.');
                resetSubmitButton();
                return;
            }
            
            // Validate provider-specific fields
            let validationError = null;
            
            if (['expresspay', 'hubtel', 'flutterwave'].includes(providerValue)) {
                const phoneNumber = phoneNumberInput?.value.trim();
                if (!phoneNumber) {
                    validationError = 'Please provide your phone number for mobile money payment.';
                } else if (!isValidPhoneNumber(phoneNumber)) {
                    validationError = 'Please provide a valid Ghana phone number (e.g., 0551234567 or +233551234567).';
                }
            } else if (providerValue === 'paystack') {
                const email = emailInput?.value.trim();
                if (!email) {
                    validationError = 'Please provide your email address for Paystack payment.';
                } else if (!isValidEmail(email)) {
                    validationError = 'Please provide a valid email address.';
                }
            }
            
            if (validationError) {
                showError(validationError);
                resetSubmitButton();
                return;
            }
            
            // Show confirmation for amounts above 1000
            if (amount >= 1000) {
                showConfirmationModal();
            } else {
                this.submit();
            }
        });
    }
    
    // Modal Handlers
    if (cancelPaymentBtn) {
        cancelPaymentBtn.addEventListener('click', hideConfirmationModal);
    }
    
    if (confirmPaymentBtn) {
        confirmPaymentBtn.addEventListener('click', function() {
            hideConfirmationModal();
            document.getElementById('payment-form').submit();
        });
    }
    
    if (confirmationModal) {
        confirmationModal.addEventListener('click', function(e) {
            if (e.target === confirmationModal) {
                hideConfirmationModal();
            }
        });
    }
    
    // Auto-select pre-selected invoice
    @if(isset($preSelectedInvoice) && $preSelectedInvoice)
        const preSelectedRadio = document.querySelector(`.invoice-radio[value="{{ $preSelectedInvoice->id }}"]`);
        if (preSelectedRadio && !preSelectedRadio.checked) {
            preSelectedRadio.checked = true;
            handleInvoiceSelection();
        }
    @endif
    
    // Scroll to first error on page load
    @if($errors->any())
        const firstErrorField = document.querySelector('.text-red-600, [style*="danger"]');
        if (firstErrorField) {
            const inputField = firstErrorField.closest('.grid')?.querySelector('input');
            if (inputField) {
                inputField.focus();
            }
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    @endif
});
</script>

<style>
.invoice-label, .payment-provider-label {
    transition: all 0.2s ease-in-out;
}

.invoice-label:hover, .payment-provider-label:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

button {
    transition: all 0.2s ease-in-out;
}

button:hover {
    transform: translateY(-1px);
}

@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

.fixed.inset-0.bg-black {
    backdrop-filter: blur(4px);
}

input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

input:focus {
    outline: none;
    ring: 2px solid var(--primary);
}

@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .text-right {
        text-align: left;
        margin-top: 0.5rem;
    }
}

/* Dark mode support */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    color: #ef4444 !important;
}

.dark .text-green-800 {
    color: #10b981 !important;
}

.dark .text-red-800 {
    color: #ef4444 !important;
}
</style>
@endsection