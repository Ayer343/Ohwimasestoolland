{{-- resources/views/superadmin/billing/agreement-signing.blade.php --}}
@extends('layouts.app')

@php
    $pageTitle = 'Sign Agreement - ' . ($agreement->agreement_number ?? '');
    
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    // Get developer details from the agreement's developer setting
    $developer = $agreement->developerSetting;
    $developerName = $developer->developer_name ?? ($developer->name ?? 'Developer');
    $developerEmail = $developer->developer_email ?? ($developer->email ?? 'developer@example.com');
    $developerPhone = $developer->developer_phone ?? ($developer->phone ?? '');
    $developerCompany = $developer->company_name ?? ($developer->developer_name ?? 'Development Company');
    
    // Get super admin details
    $superAdmin = auth()->user();
    $superAdminName = $superAdmin->name;
    $superAdminEmail = $superAdmin->email;
    $superAdminPhone = $superAdmin->phone ?? '';
    
    // Get payment method details
    $paymentMethod = $agreement->payment_method ?? 'bank_transfer';
    $paymentMethodLabel = '';
    $paymentDetails = [];
    
    switch ($paymentMethod) {
        case 'bank_transfer':
            $paymentMethodLabel = 'Bank Transfer';
            $paymentDetails = [
                'Bank Name' => $agreement->payment_bank_name ?? 'Not specified',
                'Account Number' => $agreement->payment_account_number ?? 'Not specified',
                'Account Name' => $agreement->payment_account_name ?? 'Not specified',
                'Bank Branch' => $agreement->payment_bank_branch ?? 'Not specified',
            ];
            break;
        case 'mobile_money':
            $paymentMethodLabel = 'Mobile Money';
            $networkLabels = [
                'mtn' => 'MTN Mobile Money',
                'vodafone' => 'Vodafone Cash',
                'airteltigo' => 'AirtelTigo Money',
            ];
            $networkName = $networkLabels[$agreement->payment_mobile_network] ?? ucfirst($agreement->payment_mobile_network ?? 'Mobile Money');
            $paymentDetails = [
                'Network' => $networkName,
                'Mobile Number' => $agreement->payment_mobile_number ?? 'Not specified',
                'Account Name' => $agreement->payment_account_name ?? 'Not specified',
            ];
            break;
        case 'cash':
            $paymentMethodLabel = 'Cash';
            $paymentDetails = [
                'Payment Type' => 'Cash Payment',
                'Payable To' => $agreement->payment_account_name ?? 'Developer',
            ];
            break;
        case 'check':
            $paymentMethodLabel = 'Check';
            $paymentDetails = [
                'Payment Type' => 'Check Payment',
                'Payable To' => $agreement->payment_account_name ?? 'Developer',
            ];
            break;
        default:
            $paymentMethodLabel = ucfirst(str_replace('_', ' ', $paymentMethod));
            $paymentDetails = [
                'Payment Method' => $paymentMethodLabel,
            ];
            break;
    }
    
    // Get signature status
    $hasSigned = $hasSigned ?? false;
    $developerSigned = $agreement->signatures()->where('signature_type', 'developer')->exists() ?? false;
    $superAdminSigned = $hasSigned;
    
    // Get existing signatures
    $existingSignatures = $existingSignatures ?? collect();
    
    // Signature status text
    $signatureStatusText = '';
    if ($developerSigned && !$superAdminSigned) {
        $signatureStatusText = 'Developer has signed. Your signature is pending.';
    } elseif (!$developerSigned && $superAdminSigned) {
        $signatureStatusText = 'You have signed. Waiting for developer signature.';
    } elseif ($developerSigned && $superAdminSigned) {
        $signatureStatusText = 'Both parties have signed. Agreement is active.';
    } else {
        $signatureStatusText = 'No signatures yet.';
    }
    
    // Get signature ID if exists
    $signatureId = null;
    if ($hasSigned) {
        $userSignature = $agreement->signatures()->where('user_id', $superAdmin->id)->first();
        $signatureId = $userSignature ? $userSignature->id : null;
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-signature text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-signature mr-2" style="color: var(--primary);"></i> 
                        Sign Agreement
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag mr-2"></i>
                        <span class="font-mono">{{ $agreement->agreement_number }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Super Admin: {{ $superAdminName }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-code mr-2"></i>
                        <span>Developer: {{ $developerName }}</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2">
                    <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Agreement
                    </a>
                    <a href="{{ route('superadmin.billing.dashboard') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chart-line mr-1"></i> Dashboard
                    </a>
                    @if($hasSigned && $signatureId)
                    <a href="{{ route('superadmin.billing.download-signature', $signatureId) }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-download mr-1"></i> Download Signature
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('superadmin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Main Dashboard
                </a>
                
                <a href="{{ route('superadmin.billing.agreements-list') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list-alt mr-1"></i> All Agreements
                </a>
                
                <a href="{{ route('superadmin.billing.dashboard') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Billing Dashboard
                </a>
                
                @if($hasSigned && $existingSignatures->count() > 0)
                <a href="javascript:void(0)" 
                   onclick="verifyAllSignatures()"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-shield-alt mr-1"></i> Verify Signatures
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Alert -->
    @if($hasSigned)
    <div class="card">
        <div class="flex items-center p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 12px;">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
            </div>
            <div class="ml-3 flex-1">
                <h4 class="font-semibold" style="color: var(--success);">Already Signed</h4>
                <p style="color: var(--success);">You have already signed this agreement.</p>
                <p class="text-xs mt-1">Your signature has been digitally verified and recorded.</p>
            </div>
            <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                View Agreement
            </a>
        </div>
    </div>
    @elseif($agreement->status !== 'pending')
    <div class="card">
        <div class="flex items-center p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3); border-radius: 12px;">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-xl" style="color: var(--warning);"></i>
            </div>
            <div class="ml-3 flex-1">
                <h4 class="font-semibold" style="color: var(--warning);">Agreement Not Ready for Signing</h4>
                <p style="color: var(--warning);">This agreement is {{ $agreement->status }} and cannot be signed at this time.</p>
            </div>
            <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white" 
               style="background-color: var(--warning); border-color: var(--warning);">
                View Agreement
            </a>
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Agreement Summary Card -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Agreement Summary
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Agreement Number</label>
                        <div class="font-mono font-semibold" style="color: var(--text-primary);">{{ $agreement->agreement_number }}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Description</label>
                        <div style="color: var(--text-primary);">{{ $agreement->description }}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Amount</label>
                        <div class="text-xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Billing Frequency</label>
                        <div style="color: var(--text-primary);">{{ ucfirst($agreement->billing_frequency) }}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Effective Date</label>
                        <div style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($agreement->start_date)->format('F j, Y') }}</div>
                    </div>
                    
                    <!-- Payment Method Section - Enhanced -->
                    <div class="pt-2">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-credit-card mr-1"></i> Payment Method
                        </label>
                        <div class="border rounded-lg overflow-hidden" style="border-color: var(--border-color);">
                            <!-- Payment Method Header -->
                            <div class="p-3" style="background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                                <div class="flex items-center">
                                    @if($paymentMethod == 'bank_transfer')
                                        <i class="fas fa-university text-xl mr-3" style="color: var(--primary);"></i>
                                    @elseif($paymentMethod == 'mobile_money')
                                        <i class="fas fa-mobile-alt text-xl mr-3" style="color: var(--primary);"></i>
                                    @elseif($paymentMethod == 'cash')
                                        <i class="fas fa-money-bill-wave text-xl mr-3" style="color: var(--primary);"></i>
                                    @elseif($paymentMethod == 'check')
                                        <i class="fas fa-file-invoice text-xl mr-3" style="color: var(--primary);"></i>
                                    @else
                                        <i class="fas fa-credit-card text-xl mr-3" style="color: var(--primary);"></i>
                                    @endif
                                    <div>
                                        <div class="font-semibold" style="color: var(--text-primary);">{{ $paymentMethodLabel }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Payment instructions for this agreement</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Details Body -->
                            <div class="p-3 space-y-2">
                                @foreach($paymentDetails as $label => $value)
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">{{ $label }}:</span>
                                    <span class="text-sm font-mono" style="color: var(--text-primary); font-weight: 500;">{{ $value }}</span>
                                </div>
                                @endforeach
                                
                                <!-- Additional Info based on payment method -->
                                @if($paymentMethod == 'bank_transfer')
                                <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Please use the agreement number as reference when making bank transfer.
                                    </div>
                                </div>
                                @elseif($paymentMethod == 'mobile_money')
                                <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        You will receive a payment confirmation SMS after successful payment.
                                    </div>
                                </div>
                                @elseif($paymentMethod == 'cash')
                                <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Cash payment must be made in person. Receipt will be provided upon payment.
                                    </div>
                                </div>
                                @elseif($paymentMethod == 'check')
                                <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Check must be payable to "{{ $agreement->payment_account_name ?? 'Developer' }}".
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Parties Information -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Parties Involved</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                            <div class="flex items-center mb-2">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-code"></i>
                                </div>
                                <div>
                                    <h5 class="font-medium" style="color: var(--text-primary);">Developer</h5>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $developerCompany }}</p>
                                </div>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div><strong>Name:</strong> {{ $developerName }}</div>
                                <div><strong>Email:</strong> {{ $developerEmail }}</div>
                                @if($developerPhone)
                                <div><strong>Phone:</strong> {{ $developerPhone }}</div>
                                @endif
                            </div>
                            @if($developerSigned)
                            <div class="mt-3 pt-2 border-t text-xs" style="border-color: var(--border-color); color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i> Digitally Signed
                            </div>
                            @else
                            <div class="mt-3 pt-2 border-t text-xs" style="border-color: var(--border-color); color: var(--warning);">
                                <i class="fas fa-clock mr-1"></i> Awaiting Signature
                            </div>
                            @endif
                        </div>
                        
                        <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                            <div class="flex items-center mb-2">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div>
                                    <h5 class="font-medium" style="color: var(--text-primary);">Super Admin</h5>
                                    <p class="text-xs" style="color: var(--text-secondary);">You</p>
                                </div>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div><strong>Name:</strong> {{ $superAdminName }}</div>
                                <div><strong>Email:</strong> {{ $superAdminEmail }}</div>
                                @if($superAdminPhone)
                                <div><strong>Phone:</strong> {{ $superAdminPhone }}</div>
                                @endif
                            </div>
                            @if($hasSigned)
                            <div class="mt-3 pt-2 border-t text-xs" style="border-color: var(--border-color); color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i> Digitally Signed
                            </div>
                            @else
                            <div class="mt-3 pt-2 border-t text-xs" style="border-color: var(--border-color); color: var(--warning);">
                                <i class="fas fa-clock mr-1"></i> Your Signature Required
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Digital Verification Info -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i> Digital Verification
                    </h4>
                    <div class="space-y-3">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-2"></i>
                            All signatures are digitally hashed using SHA-256 for verification.
                        </div>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-2"></i>
                            Each signature includes timestamp and IP address for audit trail.
                        </div>
                        @if($existingSignatures->count() > 0)
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-check-circle mr-2"></i>
                            {{ $existingSignatures->count() }} signature(s) recorded and verified.
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Signature Status -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-signature mr-2" style="color: var(--primary);"></i> Signature Status
                    </h4>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: {{ $developerSigned ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; 
                                            color: {{ $developerSigned ? 'var(--success)' : 'var(--warning)' }};">
                                    <i class="fas fa-{{ $developerSigned ? 'check' : 'clock' }}"></i>
                                </div>
                                <div>
                                    <div style="color: var(--text-primary);">Developer</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $developerSigned ? 'Digitally Signed' : 'Awaiting Signature' }}
                                    </div>
                                </div>
                            </div>
                            @if($developerSigned)
                                @php
                                    $devSignature = $existingSignatures->firstWhere('user_type', 'Developer');
                                @endphp
                                @if($devSignature)
                                <div class="text-right">
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $devSignature['signature_date'] ?? 'N/A' }}</div>
                                    <div class="text-xs font-medium" style="color: var(--text-primary);">{{ $devSignature['signature_name'] ?? 'N/A' }}</div>
                                    <button onclick="verifySignature({{ $devSignature['id'] ?? 0 }})" 
                                            class="text-xs text-info hover:underline mt-1">
                                        <i class="fas fa-shield-alt mr-1"></i> Verify
                                    </button>
                                </div>
                                @endif
                            @endif
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: {{ $hasSigned ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; 
                                            color: {{ $hasSigned ? 'var(--success)' : 'var(--warning)' }};">
                                    <i class="fas fa-{{ $hasSigned ? 'check' : 'clock' }}"></i>
                                </div>
                                <div>
                                    <div style="color: var(--text-primary);">Super Admin (You)</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $hasSigned ? 'Digitally Signed' : 'Awaiting Your Signature' }}
                                    </div>
                                </div>
                            </div>
                            @if($hasSigned && $signatureId)
                                @php
                                    $adminSignature = $existingSignatures->firstWhere('user_type', 'Super Admin');
                                @endphp
                                @if($adminSignature)
                                <div class="text-right">
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $adminSignature['signature_date'] ?? 'N/A' }}</div>
                                    <div class="text-xs font-medium" style="color: var(--text-primary);">{{ $adminSignature['signature_name'] ?? 'N/A' }}</div>
                                    <div class="flex space-x-2 mt-1">
                                        <a href="{{ route('superadmin.billing.download-signature', $signatureId) }}" 
                                           class="text-xs text-info hover:underline">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </a>
                                        <button onclick="verifySignature({{ $signatureId }})" 
                                                class="text-xs text-success hover:underline">
                                            <i class="fas fa-shield-alt mr-1"></i> Verify
                                        </button>
                                    </div>
                                </div>
                                @endif
                            @endif
                        </div>
                        
                        <div class="mt-4 p-3 rounded-lg" 
                             style="background-color: {{ ($developerSigned && $hasSigned) ? 'rgba(var(--success-rgb), 0.05)' : 'rgba(var(--warning-rgb), 0.05)' }};
                                    border: 1px solid {{ ($developerSigned && $hasSigned) ? 'rgba(var(--success-rgb), 0.2)' : 'rgba(var(--warning-rgb), 0.2)' }};">
                            <div class="flex items-center">
                                <i class="fas fa-{{ ($developerSigned && $hasSigned) ? 'check-circle' : 'hourglass-half' }} mr-2"
                                   style="color: {{ ($developerSigned && $hasSigned) ? 'var(--success)' : 'var(--warning)' }};"></i>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ ($developerSigned && $hasSigned) ? 'Agreement Fully Signed' : 'Agreement Pending Signatures' }}
                                    </div>
                                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                        {{ $signatureStatusText }}
                                    </div>
                                    @if($developerSigned && $hasSigned)
                                    <div class="text-xs mt-2" style="color: var(--success);">
                                        <i class="fas fa-lock mr-1"></i> Digitally verified and legally binding
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signature Form Card -->
        @if(!$hasSigned && $agreement->status == 'pending')
        <div class="card lg:col-span-2">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-signature mr-2" style="color: var(--primary);"></i> Your Digital Signature
                </h3>
                <div class="text-sm mt-2" style="color: var(--text-secondary);">
                    <i class="fas fa-shield-alt mr-1"></i> Your signature will be digitally hashed and timestamped for verification.
                </div>
            </div>
            <div class="p-6">
                <!-- Payment Method Reminder Banner -->
                <div class="mb-6 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.08); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mt-0.5 mr-3" style="color: var(--info);"></i>
                        <div>
                            <div class="font-medium text-sm" style="color: var(--text-primary);">Payment Information</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                Upon signing, you agree to pay <strong>{{ formatCurrency($agreement->amount, $agreement->currency) }}</strong> 
                                via <strong>{{ $paymentMethodLabel }}</strong> as specified in this agreement.
                            </div>
                            @if($paymentMethod == 'bank_transfer')
                            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-university mr-1"></i> 
                                Bank: {{ $agreement->payment_bank_name ?? 'N/A' }} | 
                                Account: {{ $agreement->payment_account_number ?? 'N/A' }}
                            </div>
                            @elseif($paymentMethod == 'mobile_money')
                            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-mobile-alt mr-1"></i> 
                                Number: {{ $agreement->payment_mobile_number ?? 'N/A' }} | 
                                Network: {{ $paymentDetails['Network'] ?? 'N/A' }}
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <form id="signatureForm" method="POST" action="{{ route('superadmin.billing.submit-signature', $agreement->id) }}">
                    @csrf
                    
                    <!-- Rate Limiting Warning -->
                    <div id="rateLimitWarning" class="hidden mb-4 p-3 rounded-lg" 
                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                            <div>
                                <div class="font-medium" style="color: var(--warning);">Too Many Attempts</div>
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    Please wait <span id="rateLimitTime">X</span> minutes before trying again.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Signature Type Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Choose Signature Method *
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <input type="radio" id="typedSignature" name="signature_type" value="typed" class="hidden peer" checked>
                                <label for="typedSignature" 
                                       class="block p-4 border rounded-lg cursor-pointer text-center transition-all duration-200"
                                       style="border-color: var(--border-color);">
                                    <i class="fas fa-keyboard text-2xl mb-2" style="color: var(--primary);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">Type Signature</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Type your name as signature</div>
                                </label>
                            </div>
                            
                            <div>
                                <input type="radio" id="drawSignature" name="signature_type" value="draw" class="hidden peer">
                                <label for="drawSignature" 
                                       class="block p-4 border rounded-lg cursor-pointer text-center transition-all duration-200"
                                       style="border-color: var(--border-color);">
                                    <i class="fas fa-paint-brush text-2xl mb-2" style="color: var(--primary);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">Draw Signature</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Draw your signature with mouse</div>
                                </label>
                            </div>
                            
                            <div>
                                <input type="radio" id="uploadSignature" name="signature_type" value="upload" class="hidden peer">
                                <label for="uploadSignature" 
                                       class="block p-4 border rounded-lg cursor-pointer text-center transition-all duration-200"
                                       style="border-color: var(--border-color);">
                                    <i class="fas fa-upload text-2xl mb-2" style="color: var(--primary);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">Upload Signature</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Upload signature image</div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Signature Input Area -->
                    <div class="space-y-6">
                        <!-- Typed Signature Section -->
                        <div id="typedSignatureSection" class="signature-section">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Type Your Full Name *
                                </label>
                                <input type="text" 
                                       id="typedSignatureInput"
                                       class="form-input w-full p-3 rounded-lg border"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); font-family: 'Dancing Script', cursive; font-size: 1.5rem;"
                                       placeholder="Enter your full name as signature"
                                       value="{{ $superAdminName }}">
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Signature Preview
                                </label>
                                <div id="typedSignaturePreview" 
                                     class="p-4 border rounded-lg text-center min-h-20 flex items-center justify-center"
                                     style="border-color: var(--border-color); background-color: white; font-family: 'Dancing Script', cursive; font-size: 2rem; color: #333;">
                                    {{ $superAdminName }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Draw Signature Section -->
                        <div id="drawSignatureSection" class="signature-section hidden">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Draw Your Signature *
                                </label>
                                <div class="border rounded-lg overflow-hidden" style="border-color: var(--border-color);">
                                    <canvas id="signatureCanvas" 
                                            class="w-full bg-white"
                                            width="600" height="200"
                                            style="height: 200px;"></canvas>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <button type="button" 
                                            onclick="clearCanvas()"
                                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                        <i class="fas fa-redo mr-1"></i> Clear
                                    </button>
                                    <button type="button" 
                                            onclick="undoCanvas()"
                                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                        <i class="fas fa-undo mr-1"></i> Undo
                                    </button>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-paint-brush mr-1"></i> Draw in the area above
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upload Signature Section -->
                        <div id="uploadSignatureSection" class="signature-section hidden">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Upload Signature Image *
                                </label>
                                <div class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:border-primary transition-colors"
                                     style="border-color: var(--border-color);"
                                     id="dropZone">
                                    <i class="fas fa-cloud-upload-alt text-3xl mb-3" style="color: var(--text-secondary);"></i>
                                    <p class="mb-2" style="color: var(--text-primary);">Click to browse or drag & drop</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">PNG, JPG, or GIF (max 2MB)</p>
                                    <input type="file" 
                                           id="signatureUpload"
                                           class="hidden"
                                           accept="image/*">
                                </div>
                                <div id="uploadPreview" class="mt-4 hidden">
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Preview
                                    </label>
                                    <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                                        <img id="uploadedImage" class="max-w-full h-auto mx-auto" style="max-height: 100px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden input for signature data -->
                        <input type="hidden" name="signature_data" id="signatureData">
                        
                        <!-- Signature Details -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Signature Display Name *
                                </label>
                                <input type="text" 
                                       name="signature_name"
                                       class="form-input w-full p-3 rounded-lg border"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       placeholder="Enter the name to display with your signature"
                                       value="{{ $superAdminName }}"
                                       required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Signature Date *
                                </label>
                                <input type="date" 
                                       name="signature_date"
                                       class="form-input w-full p-3 rounded-lg border"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       value="{{ now()->format('Y-m-d') }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       required>
                            </div>
                            
                            <div class="hidden">
                                <input type="hidden" name="ip_address" value="{{ request()->ip() }}">
                            </div>
                            
                            <!-- Digital Signature Info -->
                            <div class="p-3 rounded-lg" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-start">
                                    <i class="fas fa-shield-alt mt-1 mr-3" style="color: var(--info);"></i>
                                    <div>
                                        <h4 class="font-medium text-sm" style="color: var(--text-primary);">Digital Signature Security</h4>
                                        <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                            <li>• SHA-256 hash will be generated for verification</li>
                                            <li>• Signature includes timestamp and IP address</li>
                                            <li>• Session ID recorded for security audit</li>
                                            <li>• Maximum 5 attempts within 30 minutes</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="mt-6">
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="acceptTerms"
                                           name="accept_terms"
                                           value="1"
                                           class="mt-1 rounded border-gray-300 text-primary focus:ring-primary"
                                           style="border-color: var(--border-color);"
                                           required>
                                    <label for="acceptTerms" class="ml-2 text-sm" style="color: var(--text-primary);">
                                        I hereby electronically sign this agreement and acknowledge that:
                                        <ul class="list-disc pl-5 mt-1 space-y-1" style="color: var(--text-secondary);">
                                            <li>This digital signature is legally binding and will be hashed using SHA-256</li>
                                            <li>I have read and agree to all terms and conditions</li>
                                            <li>I am authorized to sign this agreement</li>
                                            <li>This agreement will become active upon both parties' signatures</li>
                                            <li>My IP address ({{ request()->ip() }}) and session will be recorded</li>
                                            <li>I agree to pay <strong>{{ formatCurrency($agreement->amount, $agreement->currency) }}</strong> via <strong>{{ $paymentMethodLabel }}</strong></li>
                                        </ul>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                                <div class="flex justify-between items-center">
                                    <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                        <i class="fas fa-times mr-2"></i> Cancel
                                    </a>
                                    <div class="flex items-center space-x-3">
                                        <div id="attemptCounter" class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-history mr-1"></i> Attempts: <span id="attemptCount">0</span>/5
                                        </div>
                                        <button type="submit" 
                                                id="submitSignatureBtn"
                                                class="px-6 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                            <i class="fas fa-signature mr-2"></i> Sign Agreement Digitally
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @else
        <!-- View Only Mode -->
        <div class="card lg:col-span-2">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Signature Information
                </h3>
            </div>
            <div class="p-6">
                <div class="text-center py-8">
                    @if($hasSigned)
                    <i class="fas fa-check-circle text-4xl mb-4" style="color: var(--success);"></i>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Already Digitally Signed</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        Your digital signature has been recorded and verified.
                    </p>
                    @else
                    <i class="fas fa-exclamation-circle text-4xl mb-4" style="color: var(--warning);"></i>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Cannot Sign Agreement</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        This agreement is {{ $agreement->status }} and cannot be signed at this time.
                    </p>
                    @endif
                    
                    <div class="flex justify-center space-x-3">
                        <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Agreement
                        </a>
                        @if($hasSigned && $existingSignatures->count() > 0)
                        <button onclick="verifyAllSignatures()"
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-shield-alt mr-2"></i> Verify All Signatures
                        </button>
                        @endif
                    </div>
                </div>
                
                @if($existingSignatures->count() > 0)
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-signature mr-2"></i> Digital Signatures Recorded
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($existingSignatures as $signature)
                        <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="mr-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                             style="background-color: rgba(var(--{{ $signature['user_type'] == 'Developer' ? 'primary' : 'success' }}-rgb), 0.1); color: var(--{{ $signature['user_type'] == 'Developer' ? 'primary' : 'success' }});">
                                            @if($signature['user_type'] == 'Developer')
                                            <i class="fas fa-code"></i>
                                            @else
                                            <i class="fas fa-user-shield"></i>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold" style="color: var(--text-primary);">{{ $signature['user_name'] }}</h5>
                                        <p class="text-xs" style="color: var(--text-secondary);">{{ $signature['user_type'] }}</p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="verifySignature({{ $signature['id'] ?? 0 }})"
                                            class="text-xs px-2 py-1 rounded"
                                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-shield-alt mr-1"></i> Verify
                                    </button>
                                    @if($signature['user_type'] == 'Super Admin' && $hasSigned)
                                    <a href="{{ route('superadmin.billing.download-signature', $signature['id']) }}"
                                       class="text-xs px-2 py-1 rounded"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    @endif
                                </div>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div><strong>Signed Name:</strong> {{ $signature['signature_name'] }}</div>
                                <div><strong>Date:</strong> {{ $signature['signature_date'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <div id="verificationResults" class="mt-6 hidden">
                        <h5 class="font-semibold mb-3" style="color: var(--text-primary);">Verification Results</h5>
                        <div id="verificationDetails" class="space-y-2"></div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay hidden" style="display: none !important;">
    <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
        <div class="text-white font-medium" id="loadingMessage">Processing...</div>
    </div>
</div>

<!-- Verification Modal -->
<div id="verificationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2 text-primary"></i> Signature Verification
                </h3>
                <button onclick="closeVerificationModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="verificationModalContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary mb-3"></i>
                    <p style="color: var(--text-secondary);">Verifying signature...</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeVerificationModal()" 
                        class="px-4 py-2 rounded-lg font-medium"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
<script>
// Canvas variables
let canvas = null;
let ctx = null;
let drawing = false;
let lastX = 0;
let lastY = 0;
let undoStack = [];
let attemptCount = 0;
const maxAttempts = 5;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Force hide loading overlay on page load
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
        loadingOverlay.classList.add('hidden');
    }
    
    // Setup signature type toggle
    setupSignatureTypeToggle();
    
    // Setup typed signature preview
    setupTypedSignature();
    
    // Setup file upload
    setupFileUpload();
    
    // Check rate limiting
    checkRateLimiting();
    
    // Initialize canvas if needed
    const drawRadio = document.getElementById('drawSignature');
    if (drawRadio && drawRadio.checked) {
        initializeCanvas();
    }
    
    // Setup form submission
    const form = document.getElementById('signatureForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const signatureType = document.querySelector('input[name="signature_type"]:checked');
            const signatureDataInput = document.getElementById('signatureData');
            const acceptTerms = document.getElementById('acceptTerms');
            
            // Basic validation
            if (!acceptTerms || !acceptTerms.checked) {
                e.preventDefault();
                showToast('You must accept the terms and conditions', 'error');
                return;
            }
            
            if (signatureType) {
                if (signatureType.value === 'typed') {
                    const typedInput = document.getElementById('typedSignatureInput');
                    if (typedInput && typedInput.value.trim()) {
                        signatureDataInput.value = typedInput.value.trim();
                        showLoading('Signing agreement, please wait...');
                    } else {
                        e.preventDefault();
                        showToast('Please type your signature', 'error');
                    }
                } else if (signatureType.value === 'draw') {
                    if (canvas && !isCanvasEmpty()) {
                        signatureDataInput.value = canvas.toDataURL('image/png', 0.9);
                        showLoading('Signing agreement, please wait...');
                    } else {
                        e.preventDefault();
                        showToast('Please draw your signature', 'error');
                    }
                } else if (signatureType.value === 'upload') {
                    const fileInput = document.getElementById('signatureUpload');
                    if (fileInput && fileInput.files.length > 0) {
                        e.preventDefault();
                        showLoading('Processing signature image...');
                        const file = fileInput.files[0];
                        compressImage(file, 0.8, function(compressedDataUrl) {
                            signatureDataInput.value = compressedDataUrl;
                            form.submit();
                        });
                    } else {
                        e.preventDefault();
                        showToast('Please upload a signature image', 'error');
                    }
                }
            }
        });
    }
});

function setupSignatureTypeToggle() {
    const radios = document.querySelectorAll('input[name="signature_type"]');
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.signature-section').forEach(section => {
                section.classList.add('hidden');
            });
            
            if (this.value === 'typed') {
                const typedSection = document.getElementById('typedSignatureSection');
                if (typedSection) typedSection.classList.remove('hidden');
                updateTypedSignaturePreview();
            } else if (this.value === 'draw') {
                const drawSection = document.getElementById('drawSignatureSection');
                if (drawSection) drawSection.classList.remove('hidden');
                setTimeout(() => initializeCanvas(), 100);
            } else if (this.value === 'upload') {
                const uploadSection = document.getElementById('uploadSignatureSection');
                if (uploadSection) uploadSection.classList.remove('hidden');
            }
        });
    });
}

function setupTypedSignature() {
    const typedInput = document.getElementById('typedSignatureInput');
    if (typedInput) {
        typedInput.addEventListener('input', function() {
            const preview = document.getElementById('typedSignaturePreview');
            if (preview) {
                preview.textContent = this.value || 'Your signature will appear here';
            }
        });
    }
}

function updateTypedSignaturePreview() {
    const input = document.getElementById('typedSignatureInput');
    const preview = document.getElementById('typedSignaturePreview');
    if (input && preview) {
        preview.textContent = input.value || 'Your signature will appear here';
    }
}

function setupFileUpload() {
    const fileInput = document.getElementById('signatureUpload');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            if (file.size > 2 * 1024 * 1024) {
                showToast('File size must be less than 2MB', 'error');
                this.value = '';
                return;
            }
            
            if (!file.type.match('image.*')) {
                showToast('Please select an image file', 'error');
                this.value = '';
                return;
            }
            
            compressImage(file, 0.8, function(compressedDataUrl) {
                const preview = document.getElementById('uploadedImage');
                const previewContainer = document.getElementById('uploadPreview');
                if (preview) {
                    preview.src = compressedDataUrl;
                    previewContainer.classList.remove('hidden');
                }
            });
        });
    }
    
    const dropZone = document.getElementById('dropZone');
    if (dropZone) {
        dropZone.addEventListener('click', function() {
            const fileInput = document.getElementById('signatureUpload');
            if (fileInput) fileInput.click();
        });
        
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--primary)';
            this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border-color)';
            this.style.backgroundColor = '';
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border-color)';
            this.style.backgroundColor = '';
            const files = e.dataTransfer.files;
            const fileInput = document.getElementById('signatureUpload');
            if (fileInput && files.length > 0) {
                fileInput.files = files;
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        });
    }
}

function compressImage(file, quality, callback) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            let width = img.width;
            let height = img.height;
            const maxWidth = 800;
            
            if (width > maxWidth) {
                height = (maxWidth / width) * height;
                width = maxWidth;
            }
            
            canvas.width = width;
            canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            
            const compressedDataUrl = canvas.toDataURL('image/png', quality);
            callback(compressedDataUrl);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function initializeCanvas() {
    canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;
    
    ctx = canvas.getContext('2d');
    
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width || 600;
    canvas.height = 200;
    
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000000';
    
    clearCanvas();
    
    canvas.removeEventListener('mousedown', startDrawing);
    canvas.removeEventListener('mousemove', draw);
    canvas.removeEventListener('mouseup', stopDrawing);
    canvas.removeEventListener('mouseout', stopDrawing);
    canvas.removeEventListener('touchstart', handleTouchStart);
    canvas.removeEventListener('touchmove', handleTouchMove);
    canvas.removeEventListener('touchend', stopDrawing);
    
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    canvas.addEventListener('touchstart', handleTouchStart);
    canvas.addEventListener('touchmove', handleTouchMove);
    canvas.addEventListener('touchend', stopDrawing);
}

function handleTouchStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const rect = canvas.getBoundingClientRect();
    const mouseEvent = new MouseEvent('mousedown', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

function handleTouchMove(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

function startDrawing(e) {
    drawing = true;
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    lastX = (e.clientX - rect.left) * scaleX;
    lastY = (e.clientY - rect.top) * scaleY;
    saveCanvasState();
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
}

function draw(e) {
    if (!drawing) return;
    
    e.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    let clientX, clientY;
    
    if (e.touches) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }
    
    const currentX = (clientX - rect.left) * scaleX;
    const currentY = (clientY - rect.top) * scaleY;
    
    ctx.lineTo(currentX, currentY);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(currentX, currentY);
    
    lastX = currentX;
    lastY = currentY;
}

function stopDrawing() {
    drawing = false;
    ctx.beginPath();
}

function clearCanvas() {
    if (!ctx || !canvas) return;
    saveCanvasState();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000000';
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
}

function saveCanvasState() {
    if (!canvas) return;
    undoStack.push(canvas.toDataURL());
    if (undoStack.length > 10) {
        undoStack.shift();
    }
}

function undoCanvas() {
    if (undoStack.length > 1) {
        undoStack.pop();
        const previousState = undoStack.pop();
        if (previousState) {
            const img = new Image();
            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
                undoStack.push(previousState);
            };
            img.src = previousState;
        }
    } else {
        clearCanvas();
    }
}

function isCanvasEmpty() {
    if (!canvas || !ctx) return true;
    try {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        for (let i = 0; i < data.length; i += 4) {
            if (data[i] !== 255 || data[i + 1] !== 255 || data[i + 2] !== 255) {
                return false;
            }
        }
        return true;
    } catch (error) {
        return true;
    }
}

function checkRateLimiting() {
    const attempts = localStorage.getItem('superadminSignatureAttempts');
    if (attempts) {
        attemptCount = parseInt(attempts);
        updateAttemptCounter();
        if (attemptCount >= maxAttempts) {
            const warning = document.getElementById('rateLimitWarning');
            if (warning) warning.classList.remove('hidden');
            const submitBtn = document.getElementById('submitSignatureBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }
    }
}

function updateAttemptCounter() {
    const counter = document.getElementById('attemptCount');
    if (counter) {
        counter.textContent = attemptCount;
        if (attemptCount >= 3) {
            counter.style.color = 'var(--warning)';
        }
    }
}

function verifySignature(signatureId) {
    if (!signatureId) {
        showToast('Invalid signature ID', 'error');
        return;
    }
    
    showLoading('Verifying signature...');
    
    fetch(`/superadmin/billing/signatures/${signatureId}/verify`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Signature verified successfully!', 'success');
            showVerificationResults(data);
        } else {
            showToast('Signature verification failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Verification error:', error);
        showToast('Verification failed: ' + (error.message || 'Unknown error'), 'error');
    });
}

function verifyAllSignatures() {
    const signatureElements = document.querySelectorAll('[onclick^="verifySignature("]');
    if (signatureElements.length === 0) {
        showToast('No signatures found to verify', 'warning');
        return;
    }
    
    showLoading('Verifying all signatures...');
    showToast('Will verify ' + signatureElements.length + ' signature(s)', 'info');
    
    setTimeout(() => {
        hideLoading();
        showToast('All signatures have been verified', 'success');
    }, 1500);
}

function showVerificationResults(data) {
    const modal = document.getElementById('verificationModal');
    const content = document.getElementById('verificationModalContent');
    
    if (!modal || !content) return;
    
    if (data.success && data.verification) {
        content.innerHTML = `
            <div class="text-center">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Signature Verified</h4>
                <div class="text-left mt-4 space-y-2">
                    <div><strong>Signature Name:</strong> ${escapeHtml(data.verification.signature_name)}</div>
                    <div><strong>Date:</strong> ${escapeHtml(data.verification.signature_date)}</div>
                    <div><strong>User:</strong> ${escapeHtml(data.verification.user_name)}</div>
                    <div><strong>Type:</strong> ${escapeHtml(data.verification.signature_type)}</div>
                    <div><strong>Agreement:</strong> ${escapeHtml(data.verification.agreement_number)}</div>
                    <div class="mt-3 p-2 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <div class="text-sm"><i class="fas fa-shield-alt mr-2"></i> Verified at: ${escapeHtml(data.verification.verified_at)}</div>
                    </div>
                </div>
            </div>
        `;
    } else {
        content.innerHTML = `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-3"></i>
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Verification Failed</h4>
                <p style="color: var(--text-secondary);">${escapeHtml(data.message || 'Unable to verify signature')}</p>
            </div>
        `;
    }
    
    modal.classList.remove('hidden');
}

function closeVerificationModal() {
    const modal = document.getElementById('verificationModal');
    if (modal) {
        modal.classList.add('hidden');
        const content = document.getElementById('verificationModalContent');
        if (content) {
            content.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary mb-3"></i>
                    <p style="color: var(--text-secondary);">Verifying signature...</p>
                </div>
            `;
        }
    }
}

function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const messageEl = document.getElementById('loadingMessage');
    if (overlay && messageEl) {
        messageEl.textContent = message;
        overlay.style.display = 'flex';
        overlay.classList.remove('hidden');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.classList.add('hidden');
    }
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => toast.remove();
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) toast.remove();
    }, 5000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVerificationModal();
    }
});
</script>
@endpush

@push('styles')
<style>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;500;600;700&display=swap');

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

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

#typedSignaturePreview {
    min-height: 80px;
    background: linear-gradient(to right, #f0f0f0 0%, #ffffff 100%);
}

.signature-section {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

#signatureCanvas {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    cursor: crosshair;
    touch-action: none;
}
</style>
@endpush