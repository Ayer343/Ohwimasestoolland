@extends('layouts.landlord')

@section('title', 'Verify Payment - ' . $payment->transaction_id)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <div class="max-w-md mx-auto">
        <!-- Header Card -->
        <div class="card p-6 text-center mb-6">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-shield-alt text-2xl" style="color: var(--warning);"></i>
            </div>
            <h1 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Verify Payment</h1>
            <p class="text-sm" style="color: var(--text-secondary);">
                Complete verification for your {{ $payment->getProviderDisplayName() }} payment
            </p>
        </div>

        <!-- Payment Summary -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Summary</h2>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Transaction ID:</span>
                    <span class="font-medium font-mono text-sm" style="color: var(--text-primary);">{{ $payment->transaction_id }}</span>
                </div>
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Amount:</span>
                    <span class="font-medium text-success">{{ $settings->formatAmount($payment->amount) }}</span>
                </div>
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Provider:</span>
                    <span class="font-medium" style="color: var(--text-primary);">
                        @php
                            $providerDisplay = match($payment->payment_provider) {
                                'expresspay' => 'ExpressPay',
                                'hubtel' => 'Hubtel',
                                'flutterwave' => 'Flutterwave',
                                default => ucfirst(str_replace('_', ' ', $payment->payment_provider))
                            };
                            $providerIcon = match($payment->payment_provider) {
                                'expresspay' => 'fa-credit-card',
                                'hubtel' => 'fa-phone-alt',
                                'flutterwave' => 'fa-cloud-upload-alt',
                                default => 'fa-wallet'
                            };
                            $providerColor = match($payment->payment_provider) {
                                'expresspay' => '#0066CC',
                                'hubtel' => '#2563EB',
                                'flutterwave' => '#F97316',
                                default => 'var(--primary)'
                            };
                        @endphp
                        <span class="inline-flex items-center">
                            <i class="fas {{ $providerIcon }} mr-2" style="color: {{ $providerColor }};"></i>
                            {{ $providerDisplay }}
                        </span>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Property:</span>
                    <span class="font-medium" style="color: var(--text-primary);">{{ $payment->property->property_name ?? $payment->property->name }}</span>
                </div>
                @if($payment->phone_number)
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Phone Number:</span>
                    <span class="font-medium" style="color: var(--text-primary);">{{ $payment->phone_number }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Currency:</span>
                    <span class="font-medium" style="color: var(--text-primary);">{{ $settings->currency_code }}</span>
                </div>
            </div>
        </div>

        <!-- Verification Form -->
        <div class="card p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Enter Verification Code</h2>
            
            <!-- Provider-specific Instructions (Updated for new gateways) -->
            @switch($payment->payment_provider)
                @case('expresspay')
                    <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                            <span class="font-medium" style="color: var(--primary);">ExpressPay Instructions</span>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Check your phone for an ExpressPay push notification. Enter the 6-digit verification code you received.
                        </p>
                        <div class="mt-2 p-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-mobile-alt mr-1"></i> 
                                You will receive a prompt on your registered mobile money number.
                            </p>
                        </div>
                    </div>
                    @break
                    
                @case('hubtel')
                    <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="font-medium" style="color: var(--info);">Hubtel Instructions</span>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            You should receive a payment request from Hubtel via SMS or USSD prompt. Enter the verification code you received.
                        </p>
                        <div class="mt-2 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-sms mr-1"></i> 
                                Check your SMS messages for the Hubtel payment request.
                            </p>
                        </div>
                    </div>
                    @break
                    
                @case('flutterwave')
                    <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-info-circle mr-2" style="color: var(--warning);"></i>
                            <span class="font-medium" style="color: var(--warning);">Flutterwave Instructions</span>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            You will be redirected to Flutterwave's secure payment page. Complete the payment process and enter the verification code if required.
                        </p>
                        <div class="mt-2 p-2 rounded" style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-globe mr-1"></i> 
                                Flutterwave supports multiple payment methods including cards, mobile money, and bank transfers.
                            </p>
                        </div>
                    </div>
                    @break
                    
                @default
                    <div class="rounded-lg p-4 mb-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="font-medium" style="color: var(--info);">Verification Required</span>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Enter the verification code sent to your phone to complete the payment process.
                        </p>
                    </div>
            @endswitch

            <!-- Countdown Timer for Code Expiry -->
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">Code expires in:</span>
                    </div>
                    <span id="countdown-timer" class="font-mono font-bold" style="color: var(--warning);">10:00</span>
                </div>
            </div>

            <form method="POST" action="{{ route('landlord.payments.verify') }}" id="verification-form">
                @csrf
                
                <input type="hidden" name="transaction_id" value="{{ $payment->transaction_id }}">
                <input type="hidden" name="provider" value="{{ $payment->payment_provider }}">
                
                <!-- Verification Code -->
                <div class="mb-6">
                    <label for="verification_code" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        6-Digit Verification Code *
                    </label>
                    <div class="relative">
                        <input type="text" 
                               class="w-full p-3 border rounded-lg text-center text-xl font-mono tracking-widest" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); letter-spacing: 0.5em;"
                               id="verification_code" 
                               name="verification_code" 
                               maxlength="6"
                               placeholder="••••••"
                               required 
                               autocomplete="off"
                               autofocus>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <button type="button" onclick="clearVerificationCode()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    @error('verification_code')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs" style="color: var(--text-secondary);">
                            Enter the 6-digit code from your phone
                        </span>
                        <button type="button" onclick="resendCode()" class="text-xs text-primary hover:underline" id="resend-btn">
                            Resend Code
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary w-full flex items-center justify-center py-3" id="verify-btn">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span id="verify-text">Verify Payment</span>
                    <span id="verifying-text" class="hidden">
                        <i class="fas fa-spinner fa-spin mr-2"></i> Verifying...
                    </span>
                </button>
            </form>

            <!-- Alternative Actions -->
            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="space-y-3">
                    <a href="{{ route('landlord.payments.confirmation', $payment->transaction_id) }}" 
                       class="btn-secondary w-full flex items-center justify-center py-2">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Payment Status
                    </a>
                    
                    @if(!in_array($payment->payment_provider, ['paystack', 'flutterwave']))
                    <form action="{{ route('landlord.payments.cancel', $payment->transaction_id) }}" method="POST" class="w-full">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="btn-danger w-full flex items-center justify-center py-2"
                                onclick="return confirm('Are you sure you want to cancel this payment?')">
                            <i class="fas fa-times mr-2"></i> Cancel Payment
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card p-6 mt-6" style="background-color: rgba(var(--info-rgb), 0.03);">
            <h3 class="text-md font-semibold mb-3" style="color: var(--text-primary);">
                <i class="fas fa-question-circle mr-2"></i>Need Help?
            </h3>
            <div class="space-y-2 text-sm" style="color: var(--text-secondary);">
                <p><strong>Didn't receive the code?</strong> Check your phone's messages or wait a few moments. You can resend the code if needed.</p>
                <p><strong>Wrong phone number?</strong> You'll need to cancel this payment and start over with the correct number.</p>
                <p><strong>Payment issues?</strong> Contact support if you continue to experience problems.</p>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <strong>Payment Amount:</strong> {{ $settings->formatAmount($payment->amount) }}<br>
                    <strong>Currency:</strong> {{ $settings->currency_code }}<br>
                    <strong>Payment Method:</strong> 
                    @php
                        $providerDisplay = match($payment->payment_provider) {
                            'expresspay' => 'ExpressPay',
                            'hubtel' => 'Hubtel',
                            'flutterwave' => 'Flutterwave',
                            default => ucfirst(str_replace('_', ' ', $payment->payment_provider))
                        };
                    @endphp
                    {{ $providerDisplay }}
                </p>
            </div>
            
            <!-- Provider Contact Info -->
            @switch($payment->payment_provider)
                @case('expresspay')
                    <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-headset mr-1"></i> ExpressPay Support: 
                            <a href="tel:{{ $settings->expresspay_support_number ?? '0240000000' }}" style="color: var(--primary);">
                                {{ $settings->expresspay_support_number ?? '0240000000' }}
                            </a>
                        </p>
                    </div>
                    @break
                @case('hubtel')
                    <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-headset mr-1"></i> Hubtel Support: 
                            <a href="tel:{{ $settings->hubtel_support_number ?? '0240000000' }}" style="color: var(--info);">
                                {{ $settings->hubtel_support_number ?? '0240000000' }}
                            </a>
                        </p>
                    </div>
                    @break
                @case('flutterwave')
                    <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-headset mr-1"></i> Flutterwave Support: 
                            <a href="https://support.flutterwave.com" target="_blank" style="color: var(--warning);">
                                https://support.flutterwave.com
                            </a>
                        </p>
                    </div>
                    @break
            @endswitch
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const verificationCode = document.getElementById('verification_code');
    const verifyBtn = document.getElementById('verify-btn');
    const verifyText = document.getElementById('verify-text');
    const verifyingText = document.getElementById('verifying-text');
    const resendBtn = document.getElementById('resend-btn');
    const countdownElement = document.getElementById('countdown-timer');
    
    // Countdown timer (10 minutes = 600 seconds)
    let countdownTime = 600;
    let countdownInterval;
    
    function startCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        
        countdownInterval = setInterval(() => {
            const minutes = Math.floor(countdownTime / 60);
            const seconds = countdownTime % 60;
            countdownElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            if (countdownTime <= 0) {
                clearInterval(countdownInterval);
                countdownElement.textContent = 'Expired';
                countdownElement.style.color = 'var(--danger)';
                
                // Show warning message
                const warningDiv = document.createElement('div');
                warningDiv.className = 'mt-2 p-3 rounded-lg';
                warningDiv.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                warningDiv.style.border = '1px solid var(--danger)';
                warningDiv.innerHTML = `
                    <p class="text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        The verification code has expired. Please request a new code.
                    </p>
                `;
                countdownElement.parentElement.appendChild(warningDiv);
                
                // Disable verification if expired
                verifyBtn.disabled = true;
                verifyBtn.style.opacity = '0.5';
            }
            
            countdownTime--;
        }, 1000);
    }
    
    // Start countdown
    startCountdown();
    
    // Auto-format verification code
    verificationCode.addEventListener('input', function(e) {
        // Remove non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        
        // Limit to 6 digits
        if (this.value.length > 6) {
            this.value = this.value.slice(0, 6);
        }
        
        // Auto-submit when 6 digits are entered
        if (this.value.length === 6) {
            // Small delay to let user see the complete code
            setTimeout(() => {
                document.getElementById('verification-form').dispatchEvent(new Event('submit'));
            }, 500);
        }
    });
    
    // Form submission handler
    document.getElementById('verification-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        verifyText.classList.add('hidden');
        verifyingText.classList.remove('hidden');
        verifyBtn.disabled = true;
        
        // Validate code length
        if (verificationCode.value.length !== 6) {
            showNotification('Please enter a complete 6-digit code', 'error');
            verifyText.classList.remove('hidden');
            verifyingText.classList.add('hidden');
            verifyBtn.disabled = false;
            return;
        }
        
        // Check if code is expired
        if (countdownTime <= 0) {
            showNotification('The verification code has expired. Please request a new code.', 'error');
            verifyText.classList.remove('hidden');
            verifyingText.classList.add('hidden');
            verifyBtn.disabled = false;
            return;
        }
        
        // Submit the form
        this.submit();
    });
    
    // Resend code functionality
    let resendCooldown = 60; // 60 seconds cooldown
    let resendTimer = null;
    
    function startResendCooldown() {
        resendBtn.disabled = true;
        resendBtn.textContent = `Resend in ${resendCooldown}s`;
        resendBtn.classList.remove('text-primary', 'hover:underline');
        resendBtn.classList.add('text-gray-400');
        
        resendTimer = setInterval(() => {
            resendCooldown--;
            resendBtn.textContent = `Resend in ${resendCooldown}s`;
            
            if (resendCooldown <= 0) {
                clearInterval(resendTimer);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Code';
                resendBtn.classList.remove('text-gray-400');
                resendBtn.classList.add('text-primary', 'hover:underline');
                resendCooldown = 60;
            }
        }, 1000);
    }
    
    // Start cooldown on page load
    startResendCooldown();
    
    function resendCode() {
        if (resendBtn.disabled) return;
        
        // Show loading state
        const originalText = resendBtn.textContent;
        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
        resendBtn.disabled = true;
        
        // Call API to resend code
        const transactionId = '{{ $payment->transaction_id }}';
        const provider = '{{ $payment->payment_provider }}';
        
        fetch(`/landlord/payments/${transactionId}/resend-code`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Verification code sent successfully! Please check your phone.', 'success');
                
                // Reset countdown
                countdownTime = 600;
                startCountdown();
                countdownElement.style.color = 'var(--warning)';
                
                // Remove expired warning if exists
                const expiredWarning = countdownElement.parentElement.querySelector('.text-danger');
                if (expiredWarning) {
                    expiredWarning.remove();
                }
                
                // Enable verification button
                verifyBtn.disabled = false;
                verifyBtn.style.opacity = '1';
                
                startResendCooldown();
            } else {
                showNotification(data.message || 'Failed to resend code. Please try again.', 'error');
                resendBtn.textContent = originalText;
                resendBtn.disabled = false;
                resendBtn.classList.remove('text-gray-400');
                resendBtn.classList.add('text-primary', 'hover:underline');
            }
        })
        .catch(error => {
            console.error('Error resending code:', error);
            showNotification('Error resending code. Please try again.', 'error');
            resendBtn.textContent = originalText;
            resendBtn.disabled = false;
            resendBtn.classList.remove('text-gray-400');
            resendBtn.classList.add('text-primary', 'hover:underline');
        });
    }
    
    function clearVerificationCode() {
        verificationCode.value = '';
        verificationCode.focus();
    }
    
    function showNotification(message, type) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(n => n.remove());
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'notification-toast fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-md';
        notification.style.backgroundColor = type === 'error' ? 'rgba(var(--danger-rgb), 0.95)' : 
                                           type === 'success' ? 'rgba(var(--success-rgb), 0.95)' : 
                                           'rgba(var(--info-rgb), 0.95)';
        notification.style.color = 'white';
        notification.innerHTML = `
            <div class="flex items-start">
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'} mr-3 mt-1"></i>
                <div class="flex-1">
                    <p class="font-medium">${type === 'error' ? 'Error' : 'Success'}</p>
                    <p class="text-sm mt-1">${message}</p>
                </div>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    // Auto-focus on code input
    verificationCode.focus();
    
    // Paste event handling
    verificationCode.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text');
        const numericData = pastedData.replace(/\D/g, '').slice(0, 6);
        this.value = numericData;
        
        // Trigger input event for auto-submit
        this.dispatchEvent(new Event('input'));
    });
    
    // Keyboard shortcut: Escape to cancel
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = '{{ route("landlord.payments.confirmation", $payment->transaction_id) }}';
        }
    });
});
</script>

<style>
/* Custom styles for verification input */
#verification_code {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5em !important;
}

#verification_code::placeholder {
    letter-spacing: 0.5em;
    color: var(--text-secondary);
    opacity: 0.5;
}

/* Hide number input arrows */
#verification_code::-webkit-outer-spin-button,
#verification_code::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

#verification_code {
    -moz-appearance: textfield;
}

/* Currency display styling */
.currency-display {
    font-family: monospace;
    font-weight: 600;
}

.font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

/* Notification animation */
.notification-toast {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Countdown timer styling */
#countdown-timer {
    font-size: 1.1rem;
    font-weight: 700;
    min-width: 50px;
    display: inline-block;
}

/* Responsive improvements */
@media (max-width: 640px) {
    .max-w-md {
        max-width: 100%;
        padding: 0 1rem;
    }
    
    #verification_code {
        font-size: 1.25rem;
        padding: 0.75rem;
        letter-spacing: 0.3em !important;
    }
    
    .notification-toast {
        max-width: 90%;
        right: 5%;
        left: 5%;
    }
}

/* Dark mode support for provider-specific cards */
.dark .bg-yellow-50 {
    background-color: rgba(234, 179, 8, 0.2);
}

.dark .bg-pink-50 {
    background-color: rgba(236, 72, 153, 0.2);
}

.dark .bg-orange-50 {
    background-color: rgba(249, 115, 22, 0.2);
}

.dark .bg-blue-50 {
    background-color: rgba(59, 130, 246, 0.2);
}

/* Focus ring for verification input */
#verification_code:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.3);
    outline: none;
}

/* Button hover effects */
.btn-primary, .btn-secondary, .btn-danger {
    transition: all 0.2s ease;
}

.btn-primary:hover, .btn-secondary:hover, .btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
@endsection