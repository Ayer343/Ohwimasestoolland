@extends('layouts.tenant')

@section('title', 'Payment Confirmation')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Success Header Card -->
    @if($payment->status === 'completed')
    <div class="card p-6 text-center" style="background: linear-gradient(135deg, rgba(var(--success-rgb), 0.1) 0%, rgba(var(--success-rgb), 0.05) 100%); border: 2px solid var(--success);">
        <div class="flex flex-col items-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" style="background-color: rgba(var(--success-rgb), 0.2);">
                <i class="fas fa-check-circle text-4xl" style="color: var(--success);"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2" style="color: var(--success);">Payment Successful!</h2>
            <p class="text-lg" style="color: var(--text-primary);">Your payment has been processed successfully.</p>
            <p class="text-sm mt-2" style="color: var(--text-secondary);">Transaction ID: <span class="font-mono">{{ $payment->transaction_id }}</span></p>
        </div>
    </div>
    @elseif($payment->status === 'pending')
    <div class="card p-6 text-center" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.1) 0%, rgba(var(--warning-rgb), 0.05) 100%); border: 2px solid var(--warning);">
        <div class="flex flex-col items-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" style="background-color: rgba(var(--warning-rgb), 0.2);">
                <i class="fas fa-clock text-4xl" style="color: var(--warning);"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2" style="color: var(--warning);">Payment Pending</h2>
            <p class="text-lg" style="color: var(--text-primary);">Your payment is being processed.</p>
            <p class="text-sm mt-2" style="color: var(--text-secondary);">Transaction ID: <span class="font-mono">{{ $payment->transaction_id }}</span></p>
        </div>
    </div>
    @elseif($payment->status === 'processing')
    <div class="card p-6 text-center" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.1) 0%, rgba(var(--info-rgb), 0.05) 100%); border: 2px solid var(--info);">
        <div class="flex flex-col items-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" style="background-color: rgba(var(--info-rgb), 0.2);">
                <i class="fas fa-spinner fa-spin text-4xl" style="color: var(--info);"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2" style="color: var(--info);">Processing Payment</h2>
            <p class="text-lg" style="color: var(--text-primary);">Your payment is being verified.</p>
            <p class="text-sm mt-2" style="color: var(--text-secondary);">Transaction ID: <span class="font-mono">{{ $payment->transaction_id }}</span></p>
        </div>
    </div>
    @elseif($payment->status === 'failed')
    <div class="card p-6 text-center" style="background: linear-gradient(135deg, rgba(var(--danger-rgb), 0.1) 0%, rgba(var(--danger-rgb), 0.05) 100%); border: 2px solid var(--danger);">
        <div class="flex flex-col items-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" style="background-color: rgba(var(--danger-rgb), 0.2);">
                <i class="fas fa-exclamation-triangle text-4xl" style="color: var(--danger);"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2" style="color: var(--danger);">Payment Failed</h2>
            <p class="text-lg" style="color: var(--text-primary);">Your payment could not be processed.</p>
            <p class="text-sm mt-2" style="color: var(--text-secondary);">Transaction ID: <span class="font-mono">{{ $payment->transaction_id }}</span></p>
        </div>
    </div>
    @endif

    <!-- Payment Summary Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Summary</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Transaction ID</span>
                    <span class="text-sm font-mono" style="color: var(--text-primary);">{{ $payment->transaction_id }}</span>
                </div>
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Reference Number</span>
                    <span class="text-sm font-mono" style="color: var(--text-primary);">{{ $payment->transaction_reference ?? 'N/A' }}</span>
                </div>
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Payment Date</span>
                    <span class="text-sm" style="color: var(--text-primary);">
                        {{ $payment->payment_date ? $payment->payment_date->format('F d, Y h:i A') : ($payment->created_at ? $payment->created_at->format('F d, Y h:i A') : 'N/A') }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Payment Method</span>
                    <span class="text-sm" style="color: var(--text-primary);">
                        @php
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
                        @endphp
                        <span class="inline-flex items-center">
                            <i class="fas {{ $providerIcon }} mr-1" style="color: {{ $providerColor }};"></i>
                            {{ $providerDisplay }}
                        </span>
                    </span>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Amount Paid</span>
                    <span class="text-xl font-bold" style="color: var(--success);">{{ $settings->formatAmount($payment->amount) }}</span>
                </div>
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Status</span>
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
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm" 
                          style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                        <i class="fas fa-{{ $statusIcon }} mr-2"></i>
                        {{ ucfirst($payment->status) }}
                    </span>
                </div>
                
                @if($payment->metadata && isset($payment->metadata['excess_payment']))
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Excess Payment</span>
                    <span class="text-sm" style="color: var(--info);">
                        {{ $settings->formatAmount($payment->metadata['excess_payment']['amount']) }}
                        <span class="text-xs ml-1">(Credited)</span>
                    </span>
                </div>
                @endif
                
                @if($payment->description)
                <div class="flex justify-between items-start pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Description</span>
                    <span class="text-sm text-right" style="color: var(--text-primary);">{{ $payment->description }}</span>
                </div>
                @endif

                <!-- Contact Information (for mobile money providers) -->
                @if(in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']) && $payment->phone_number)
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Phone Number</span>
                    <span class="text-sm font-mono" style="color: var(--text-primary);">{{ $payment->phone_number }}</span>
                </div>
                @endif
                
                @if($payment->payment_provider === 'paystack' && $payment->email)
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Email</span>
                    <span class="text-sm" style="color: var(--text-primary);">{{ $payment->email }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Invoice Details Card -->
    @if($payment->invoice)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Invoice Details</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Invoice Number</span>
                    <span class="text-sm font-mono" style="color: var(--text-primary);">#{{ $payment->invoice->invoice_number }}</span>
                </div>
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Period</span>
                    <span class="text-sm" style="color: var(--text-primary);">
                        {{ \Carbon\Carbon::parse($payment->invoice->period . '-01')->format('F Y') }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Due Date</span>
                    <span class="text-sm" style="color: var(--text-primary);">{{ $payment->invoice->due_date->format('F d, Y') }}</span>
                </div>
            </div>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Property Unit</span>
                    <span class="text-sm text-right" style="color: var(--text-primary);">
                        @if($payment->invoice->propertyUnit && $payment->invoice->propertyUnit->property)
                            {{ $payment->invoice->propertyUnit->property->property_name }} - Unit {{ $payment->invoice->propertyUnit->unit_number }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>
                
                @if($payment->invoice->penalty_amount > 0)
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Penalty Applied</span>
                    <span class="text-sm" style="color: var(--danger);">{{ $settings->formatAmount($payment->invoice->penalty_amount) }}</span>
                </div>
                @endif
                
                <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Invoice Status</span>
                    @php
                        $invoiceStatusColors = [
                            'paid' => 'success',
                            'pending' => 'warning',
                            'overdue' => 'danger',
                            'cancelled' => 'secondary'
                        ];
                        $invoiceStatusColor = $invoiceStatusColors[$payment->invoice->status] ?? 'secondary';
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm" 
                          style="background-color: rgba(var(--{{ $invoiceStatusColor }}-rgb), 0.2); color: var(--{{ $invoiceStatusColor }});">
                        <i class="fas fa-{{ $payment->invoice->status === 'paid' ? 'check-circle' : ($payment->invoice->status === 'overdue' ? 'exclamation-circle' : 'clock') }} mr-2"></i>
                        {{ ucfirst($payment->invoice->status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Payment Instructions Card (for pending payments) -->
    @if($payment->status === 'pending' && isset($instructions) && $instructions)
    <div class="card p-6" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-3 text-xl mt-1" style="color: var(--info);"></i>
            <div class="flex-1">
                <h3 class="font-semibold mb-2" style="color: var(--info);">Payment Instructions</h3>
                <div class="prose prose-sm" style="color: var(--text-secondary);">
                    {!! $instructions !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Provider-Specific Instructions for Mobile Money (ExpressPay, Hubtel, Flutterwave) -->
    @if(in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']) && $payment->status === 'pending')
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Complete Your Payment</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Step 1: Check Your Phone</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        You should receive a payment request on your mobile money wallet shortly.
                    </p>
                    <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-mobile-alt mr-1"></i> 
                            Check your phone for a prompt to authorize the payment of 
                            <strong>{{ $settings->formatAmount($payment->amount) }}</strong>
                        </p>
                    </div>
                </div>
                
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Step 2: Enter Your PIN</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        When prompted, enter your mobile money PIN to authorize the transaction.
                    </p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Step 3: Enter Verification Code</h4>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        After authorizing, you'll receive a confirmation SMS with a verification code.
                    </p>
                    
                    <form action="{{ route('tenant.payments.verify.submit') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="transaction_id" value="{{ $payment->transaction_id }}">
                        <input type="hidden" name="provider" value="{{ $payment->payment_provider }}">
                        
                        <div>
                            <label for="verification_code" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                Verification Code
                            </label>
                            <input type="text" name="verification_code" id="verification_code" 
                                   class="w-full p-2 border rounded text-center text-2xl tracking-wider font-mono focus:outline-none focus:ring-2 focus:ring-primary" 
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="000000" maxlength="6" required>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Enter the 6-digit code sent to your phone
                            </p>
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-2 rounded flex items-center justify-center" style="background-color: var(--primary); color: white;">
                            <i class="fas fa-check-circle mr-2"></i> Verify Payment
                        </button>
                    </form>
                </div>
                
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--warning);">
                        <i class="fas fa-clock mr-1"></i>
                        Verification code expires in <span id="countdown" class="font-bold">10:00</span> minutes
                    </p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        Didn't receive the code? 
                        <button onclick="resendCode()" class="text-primary hover:underline" style="color: var(--primary);">Resend Code</button>
                    </p>
                </div>

                <!-- Provider-specific additional info -->
                @if($payment->payment_provider === 'expresspay')
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--info);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                        ExpressPay will send a push notification to your registered mobile money number.
                    </p>
                </div>
                @endif

                @if($payment->payment_provider === 'hubtel')
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--info);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                        Hubtel will send a payment request via SMS or USSD prompt to your phone.
                    </p>
                </div>
                @endif

                @if($payment->payment_provider === 'flutterwave')
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--info);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                        Flutterwave will process your payment through their secure gateway.
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Paystack-specific instructions -->
    @if($payment->payment_provider === 'paystack' && $payment->status === 'pending')
    <div class="card p-6" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-credit-card mr-3 text-xl mt-1" style="color: var(--info);"></i>
            <div class="flex-1">
                <h3 class="font-semibold mb-2" style="color: var(--info);">Paystack Payment Status</h3>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Your Paystack payment is being processed. You will receive a confirmation email at 
                    <strong>{{ $payment->email ?? 'your email' }}</strong> once the payment is completed.
                </p>
                <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-spinner fa-spin mr-1" style="color: var(--info);"></i>
                        Payment verification may take a few moments. The page will refresh automatically when confirmed.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-center gap-4">
        @if($payment->status === 'completed')
            @if($payment->invoice)
            <a href="{{ route('tenant.invoices.export-pdf', $payment->invoice->id) }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: var(--primary); color: white;">
                <i class="fas fa-download mr-2"></i> Download Receipt
            </a>
            @endif
            
            <a href="{{ route('tenant.invoices.my-invoices') }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                <i class="fas fa-file-invoice mr-2"></i> View All Invoices
            </a>
        @elseif($payment->status === 'pending')
            @if(in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']))
            <a href="{{ route('tenant.payments.verify', $payment->transaction_id) }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: var(--warning); color: white;">
                <i class="fas fa-check-double mr-2"></i> Verify Payment
            </a>
            @endif
            
            <button onclick="cancelPayment()" 
                    class="px-6 py-3 rounded flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                <i class="fas fa-times-circle mr-2"></i> Cancel Payment
            </button>
        @elseif($payment->status === 'failed')
            <a href="{{ route('tenant.payments.create', ['invoice_id' => $payment->invoice->id]) }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: var(--primary); color: white;">
                <i class="fas fa-redo-alt mr-2"></i> Retry Payment
            </a>
            
            <a href="{{ route('tenant.invoices.my-invoices') }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                <i class="fas fa-file-invoice mr-2"></i> View Invoices
            </a>
        @else
            <a href="{{ route('tenant.invoices.my-invoices') }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: var(--primary); color: white;">
                <i class="fas fa-file-invoice mr-2"></i> View Invoices
            </a>
            
            <a href="{{ route('tenant.payments.history') }}" 
               class="px-6 py-3 rounded flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                <i class="fas fa-history mr-2"></i> View Payment History
            </a>
        @endif
    </div>

    <!-- Help Section -->
    <div class="card p-6" style="background-color: rgba(var(--info-rgb), 0.03);">
        <div class="flex flex-col md:flex-row justify-between items-start gap-4">
            <div class="flex items-start">
                <i class="fas fa-headset text-xl mr-3" style="color: var(--info);"></i>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Need Help?</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        If you have any questions about your payment, please contact our support team.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-4">
                <a href="mailto:{{ $settings->system_email ?? 'support@example.com' }}" 
                   class="flex items-center text-sm" style="color: var(--primary);">
                    <i class="fas fa-envelope mr-2"></i> {{ $settings->system_email ?? 'support@example.com' }}
                </a>
                @if($settings->system_phone)
                <a href="tel:{{ $settings->system_phone }}" 
                   class="flex items-center text-sm" style="color: var(--primary);">
                    <i class="fas fa-phone mr-2"></i> {{ $settings->system_phone }}
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Countdown timer for verification code
let countdownInterval;
const countdownElement = document.getElementById('countdown');

function startCountdown(minutes = 10) {
    let time = minutes * 60;
    
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    
    countdownInterval = setInterval(() => {
        const minutesLeft = Math.floor(time / 60);
        const secondsLeft = time % 60;
        
        if (countdownElement) {
            countdownElement.textContent = `${minutesLeft.toString().padStart(2, '0')}:${secondsLeft.toString().padStart(2, '0')}`;
        }
        
        if (time <= 0) {
            clearInterval(countdownInterval);
            if (countdownElement) {
                countdownElement.textContent = 'Expired';
                countdownElement.style.color = 'var(--danger)';
            }
        }
        
        time--;
    }, 1000);
}

// Start countdown if verification section is visible
@if(in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']) && $payment->status === 'pending')
    startCountdown(10);
@endif

// Resend verification code
function resendCode() {
    const transactionId = '{{ $payment->transaction_id }}';
    const provider = '{{ $payment->payment_provider }}';
    
    fetch(`/tenant/payments/${transactionId}/resend-code`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ provider: provider })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showNotification('Verification code resent successfully!', 'success');
            // Restart countdown
            startCountdown(10);
        } else {
            showNotification(data.message || 'Failed to resend code. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Failed to resend code. Please try again.', 'error');
    });
}

// Cancel payment
function cancelPayment() {
    if (confirm('Are you sure you want to cancel this payment? This action cannot be undone.')) {
        const transactionId = '{{ $payment->transaction_id }}';
        
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
                window.location.href = '{{ route("tenant.payments.history") }}';
            } else {
                showNotification(data.message || 'Failed to cancel payment', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to cancel payment. Please try again.', 'error');
        });
    }
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg animate-slide-in';
    
    const bgColor = type === 'success' ? 'rgba(var(--success-rgb), 0.9)' : 'rgba(var(--danger-rgb), 0.9)';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    notification.style.backgroundColor = bgColor;
    notification.style.color = 'white';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-3 text-xl"></i>
            <div>
                <p class="font-medium">${type === 'success' ? 'Success' : 'Error'}</p>
                <p class="text-sm mt-1">${message}</p>
            </div>
            <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Auto-format verification code input
const verificationInput = document.getElementById('verification_code');
if (verificationInput) {
    verificationInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
    
    verificationInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.closest('form').submit();
        }
    });
}

// Auto-refresh payment status every 30 seconds for pending payments
@if($payment->status === 'pending')
    let refreshInterval = setInterval(() => {
        fetch(`/tenant/payments/{{ $payment->transaction_id }}/status`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status === 'completed') {
                    clearInterval(refreshInterval);
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error checking status:', error));
    }, 30000);
    
    // Clear interval on page unload
    window.addEventListener('beforeunload', () => {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
@endif

// Print functionality
function printReceipt() {
    window.print();
}

// Add print button if payment is completed
@if($payment->status === 'completed')
    const actionButtons = document.querySelector('.flex.flex-col.sm\\:flex-row.justify-center.gap-4');
    if (actionButtons) {
        const printButton = document.createElement('button');
        printButton.className = 'px-6 py-3 rounded flex items-center justify-center no-print';
        printButton.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
        printButton.style.color = 'var(--text-secondary)';
        printButton.innerHTML = '<i class="fas fa-print mr-2"></i> Print Receipt';
        printButton.onclick = printReceipt;
        actionButtons.appendChild(printButton);
    }
@endif
</script>

<style>
/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #ddd;
        margin-bottom: 20px;
        box-shadow: none;
    }
    
    .card-header {
        background: #f8f9fa;
    }
    
    button, a.btn, .action-buttons {
        display: none !important;
    }
    
    .flex.justify-center {
        display: none !important;
    }
    
    @page {
        margin: 2cm;
    }
    
    .text-2xl {
        font-size: 18pt !important;
    }
    
    .text-lg {
        font-size: 14pt !important;
    }
}

/* Slide-in animation for notifications */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}

/* Focus styles for verification input */
#verification_code:focus {
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.3);
}

/* Loading spinner for button */
button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-col.sm\\:flex-row {
        flex-direction: column;
    }
    
    .w-20.h-20 {
        width: 60px;
        height: 60px;
    }
    
    .text-2xl {
        font-size: 1.25rem;
    }
}
</style>
@endsection