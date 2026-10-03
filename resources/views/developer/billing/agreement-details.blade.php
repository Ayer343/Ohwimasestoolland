{{-- resources/views/developer/billing/agreement-details.blade.php --}}
@extends('layouts.dev')

@php
    use App\Models\User;

    $pageTitle = 'Billing Agreement Details';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    if (!function_exists('getPaymentMethodName')) {
        function getPaymentMethodName($method, $paymentMethods = []) {
            return $paymentMethods[$method]['name'] ?? ucfirst(str_replace('_', ' ', $method));
        }
    }

    if (!function_exists('getPaymentMethodIcon')) {
        function getPaymentMethodIcon($method) {
            return match ($method) {
                'bank_transfer'                     => 'fas fa-university',
                'mobile_money', 'mtn', 'telecel',
                'airteltigo'                        => 'fas fa-mobile-alt',
                'cash'                              => 'fas fa-money-bill-wave',
                'check'                             => 'fas fa-file-invoice',
                default                             => 'fas fa-credit-card',
            };
        }
    }

    // Payment Method Display
    $paymentMethod      = $agreement->payment_method ?? 'bank_transfer';
    $paymentMethodLabel = '';
    $paymentDetails     = [];
    $metadata = $agreement->metadata
        ? (is_array($agreement->metadata) ? $agreement->metadata : json_decode($agreement->metadata, true))
        : [];

    switch ($paymentMethod) {
        case 'bank_transfer':
            $paymentMethodLabel = 'Bank Transfer';
            $paymentDetails = [
                'Bank Name'      => $agreement->payment_bank_name      ?? ($metadata['payment_bank_name']      ?? 'Not specified'),
                'Account Number' => $agreement->payment_account_number  ?? ($metadata['payment_account_number']  ?? 'Not specified'),
                'Account Name'   => $agreement->payment_account_name    ?? ($metadata['payment_account_name']    ?? 'Not specified'),
                'Bank Branch'    => $agreement->payment_bank_branch     ?? ($metadata['payment_bank_branch']     ?? 'Not specified'),
            ];
            break;

        case 'mobile_money':
        case 'mtn':
        case 'telecel':
        case 'airteltigo':
            $paymentMethodLabel = 'Mobile Money';
            $networkLabels = [
                'mtn'          => 'MTN Mobile Money',
                'telecel'      => 'Telecel (Vodafone) Cash',
                'airteltigo'   => 'AirtelTigo Money',
                'mobile_money' => 'Mobile Money',
            ];
            $paymentDetails = [
                'Network'       => $networkLabels[$paymentMethod] ?? ucfirst($paymentMethod),
                'Mobile Number' => $agreement->payment_mobile_number ?? ($metadata['payment_mobile_number'] ?? 'Not specified'),
                'Account Name'  => $agreement->payment_account_name  ?? ($metadata['payment_account_name']  ?? 'Not specified'),
            ];
            break;

        case 'cash':
            $paymentMethodLabel = 'Cash';
            $paymentDetails = [
                'Payment Type' => 'Cash Payment',
                'Payable To'   => $agreement->payment_account_name ?? ($metadata['payment_account_name'] ?? 'Developer'),
            ];
            break;

        case 'check':
            $paymentMethodLabel = 'Check';
            $paymentDetails = [
                'Payment Type' => 'Check Payment',
                'Payable To'   => $agreement->payment_account_name ?? ($metadata['payment_account_name'] ?? 'Developer'),
            ];
            break;

        default:
            $paymentMethodLabel = ucfirst(str_replace('_', ' ', $paymentMethod));
            $paymentDetails = ['Payment Method' => $paymentMethodLabel];
            break;
    }

    // Signature state
    $developerSigned   = $agreement->signatures ? $agreement->signatures->where('signature_type', 'developer')->isNotEmpty() : false;
    $superAdminSigned  = $agreement->signatures ? $agreement->signatures->where('signature_type', 'super_admin')->isNotEmpty() : false;
    $canSign           = $agreement->status == 'pending' && !$developerSigned;

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    {{-- Header Card --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-file-contract text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i>
                        Billing Agreement Details
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag"></i>
                        <span class="font-mono">{{ $agreement->agreement_number }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-user-shield" style="color: var(--info);"></i>
                        <span>{{ $agreement->superAdmin->name ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2 flex-wrap gap-2">
                    <a href="{{ route('developer.billing.dashboard') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Dashboard
                    </a>
                    <a href="{{ route('developer.billing.agreements-list') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-list mr-1"></i> All Agreements
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation Card --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('developer.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>

                <a href="{{ route('developer.settings.index') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-cog mr-1"></i> Settings
                </a>

                <a href="{{ route('developer.monitoring.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Monitoring
                </a>

                <a href="{{ route('developer.tools.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-tools mr-1"></i> Tools
                </a>
            </div>

            @if(isset($signatureStatus))
            <div class="flex items-center">
                @if($signatureStatus['all_signed'])
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle mr-1"></i> Fully Signed
                    </span>
                @elseif($signatureStatus['developer_signed'])
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-hourglass-half mr-1"></i> Awaiting Super Admin Signature
                    </span>
                @elseif($signatureStatus['super_admin_signed'])
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-hourglass-half mr-1"></i> Awaiting Your Signature
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i> No Signatures Yet
                    </span>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- LEFT COLUMN --}}
        <div class="lg:col-span-2">
            {{-- Basic Info --}}
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Agreement Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Amount:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ formatCurrency($agreement->amount, $agreement->currency) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Frequency:</span>
                            <span class="font-medium capitalize" style="color: var(--text-primary);">{{ $agreement->billing_frequency }}</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Status:</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem; color: {{ $agreement->status == 'active' ? 'var(--success)' : ($agreement->status == 'pending' ? 'var(--warning)' : 'var(--danger)') }};"></i>
                                {{ ucfirst($agreement->status) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Payment Status:</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                                <i class="fas fa-{{ $agreement->payment_status == 'paid' ? 'check' : ($agreement->payment_status == 'partial' ? 'exclamation' : 'times') }} mr-1"></i>
                                {{ ucfirst($agreement->payment_status) }}
                            </span>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Start Date:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($agreement->start_date)->format('M d, Y') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Agreed Date:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $agreement->agreed_at ? \Carbon\Carbon::parse($agreement->agreed_at)->format('M d, Y') : 'Not agreed yet' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Amount Received:</span>
                            <span class="font-medium" style="color: var(--success);">
                                {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                            </span>
                        </div>
                        @if($agreement->amount > 0)
                        <div class="mt-2">
                            <div class="h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); overflow: hidden;">
                                <div class="h-full rounded-full" style="background-color: var(--success); width: {{ ($agreement->amount_received / $agreement->amount) * 100 }}%;"></div>
                            </div>
                            <div class="text-xs mt-1 text-center" style="color: var(--text-secondary);">
                                {{ round(($agreement->amount_received / $agreement->amount) * 100, 1) }}% paid
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-credit-card mr-1"></i> Payment Method Details
                    </label>
                    <div class="border rounded-lg overflow-hidden" style="border-color: var(--border-color);">
                        <div class="p-3" style="background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <i class="{{ getPaymentMethodIcon($paymentMethod) }} text-xl mr-3" style="color: var(--primary);"></i>
                                <div>
                                    <div class="font-semibold" style="color: var(--text-primary);">{{ $paymentMethodLabel }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Payment instructions for this agreement</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 space-y-2">
                            @foreach($paymentDetails as $label => $value)
                            <div class="flex justify-between items-center py-1">
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">{{ $label }}:</span>
                                <span class="text-sm font-mono" style="color: var(--text-primary); font-weight: 500;">{{ $value }}</span>
                            </div>
                            @endforeach

                            @if($paymentMethod == 'bank_transfer')
                            <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Please use the agreement number as reference when making bank transfer.
                                </div>
                            </div>
                            @elseif(in_array($paymentMethod, ['mobile_money', 'mtn', 'telecel', 'airteltigo']))
                            <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Super admin will receive a payment confirmation SMS after successful payment.
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
                                    Check must be payable to "{{ $paymentDetails['Payable To'] ?? 'Developer' }}".
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-color);">
                    <h4 class="text-md font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-2" style="color: var(--info);"></i> Description
                    </h4>
                    <p style="color: var(--text-secondary);">{{ $agreement->description }}</p>
                </div>

                @if($agreement->notes)
                <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-color);">
                    <h4 class="text-md font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-2" style="color: var(--warning);"></i> Notes
                    </h4>
                    <p style="color: var(--text-secondary);">{{ $agreement->notes }}</p>
                </div>
                @endif

                @if($agreement->amount_received > 0)
                <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-color);">
                    <h4 class="text-md font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--success);"></i> Payment Summary
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-sm" style="color: var(--text-secondary);">Total Paid</div>
                            <div class="font-bold text-lg" style="color: var(--success);">{{ formatCurrency($agreement->amount_received, $agreement->currency) }}</div>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <div class="text-sm" style="color: var(--text-secondary);">Outstanding</div>
                            <div class="font-bold text-lg" style="color: var(--warning);">{{ formatCurrency(max(0, $agreement->amount - $agreement->amount_received), $agreement->currency) }}</div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Actions --}}
                <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
                    <div class="flex flex-wrap justify-end gap-3">
                        @if($canSign)
                            <a href="{{ route('developer.billing.view-agreement-for-signing', $agreement->id) }}"
                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                               style="background: linear-gradient(135deg, var(--success) 0%, var(--primary) 100%); border: none; box-shadow: 0 2px 8px rgba(var(--success-rgb), 0.3); transition: all 0.2s ease;">
                                <i class="fas fa-signature mr-2"></i> Sign Agreement
                            </a>
                        @endif

                        @if($agreement->status == 'pending' && $developerSigned && !$superAdminSigned)
                            <button type="button" onclick="showResendSignatureModal()"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-envelope mr-2"></i> Resend Signature Request
                            </button>
                        @endif

                        @if($agreement->status == 'pending' && $agreement->agreement_pdf_path && !$developerSigned)
                            <button type="button" onclick="showSendForSigningModal()"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-paper-plane mr-2"></i> Send for Signature
                            </button>
                        @endif

                        @if($agreement->status == 'pending' && !$agreement->agreement_pdf_path)
                            <button type="button" onclick="generateAgreementPdf({{ $agreement->id }})"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-file-pdf mr-2"></i> Generate PDF
                            </button>
                        @endif

                        @if($agreement->status == 'active')
                            <button type="button" onclick="showUpdateAgreementModal()"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-edit mr-2"></i> Update Agreement
                            </button>

                            @if($agreement->signed_agreement_pdf_path)
                            <button type="button" onclick="showRevokeSignatureModal()"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times-circle mr-2"></i> Revoke Signatures
                            </button>
                            @endif

                            <button type="button" onclick="showTerminateAgreementModal()"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-ban mr-2"></i> Terminate
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Payment History --}}
            @if($payments->isNotEmpty())
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--secondary);"></i> Payment History
                </h3>

                <div class="overflow-x-auto">
                    <table class="table agreement-table w-full">
                        <thead>
                            <tr>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Date</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Method</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Reference</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $payment)
                            <tr>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($payment->amount_paid, $agreement->currency) }}</div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                                        {{ getPaymentMethodName($payment->payment_method, $paymentMethods ?? []) }}
                                    </span>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div style="color: var(--text-secondary);">{{ $payment->transaction_reference ?? 'N/A' }}</div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                                        <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                        {{ ucfirst(str_replace('_', ' ', $payment->status)) }}
                                    </span>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div style="color: var(--text-secondary);">{{ $payment->recorded_by_name ?? 'N/A' }}</div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-between items-center flex-wrap gap-3">
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Total Payments: {{ formatCurrency($payments->sum('amount_paid'), $agreement->currency) }}
                        </p>
                    </div>
                    @if($agreement->status == 'active' && $agreement->amount_received < $agreement->amount)
                    <button type="button" onclick="recordNewPayment()"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-plus mr-2"></i> Record Payment
                    </button>
                    @endif
                </div>
            </div>
            @endif

            {{-- Signature Audit Trail --}}
            @if(isset($signatures) && $signatures->count() > 0)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-signature mr-2" style="color: var(--info);"></i> Signature Audit Trail
                </h3>

                <div class="space-y-3">
                    @foreach($signatures as $signature)
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                        <div class="flex justify-between items-start flex-wrap gap-2">
                            <div>
                                <div class="font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-user mr-1"></i> {{ $signature['user_name'] ?? 'Unknown' }}
                                </div>
                                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-tag mr-1"></i> {{ ucfirst($signature['user_type'] ?? 'Unknown') }}
                                </div>
                                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-pen mr-1"></i> Signed as: {{ $signature['signature_name'] ?? 'N/A' }}
                                </div>
                                <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar mr-1"></i> {{ $signature['signature_date'] ?? 'N/A' }}
                                </div>
                                @if(isset($signature['ip_address']))
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-network-wired mr-1"></i> IP: {{ $signature['ip_address'] }}
                                </div>
                                @endif
                            </div>
                            @if(isset($signature['id']))
                            <button type="button" onclick="verifySignature({{ $signature['id'] }})"
                                    class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-shield-alt mr-1"></i> Verify
                            </button>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-4 pt-4 text-center" style="border-top: 1px solid var(--border-color);">
                    <a href="{{ route('developer.billing.signature-audit', $agreement->id) }}"
                       class="text-sm inline-flex items-center" style="color: var(--primary);">
                        <i class="fas fa-history mr-1"></i> View Full Signature Audit
                    </a>
                </div>
            </div>
            @endif
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="lg:col-span-1">
            {{-- Super Admin Card --}}
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-shield mr-2" style="color: var(--info);"></i> Super Admin Details
                </h3>

                @if($agreement->superAdmin)
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid rgba(var(--secondary-rgb), 0.1);">
                        <div class="text-sm" style="color: var(--text-secondary);">Name:</div>
                        <div class="font-medium" style="color: var(--text-primary);">{{ $agreement->superAdmin->name }}</div>
                    </div>
                    <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid rgba(var(--secondary-rgb), 0.1);">
                        <div class="text-sm" style="color: var(--text-secondary);">Email:</div>
                        <div class="font-medium truncate" style="color: var(--text-primary); max-width: 200px;">{{ $agreement->superAdmin->email }}</div>
                    </div>
                    @if($agreement->superAdmin->phone)
                    <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid rgba(var(--secondary-rgb), 0.1);">
                        <div class="text-sm" style="color: var(--text-secondary);">Phone:</div>
                        <div class="font-medium" style="color: var(--text-primary);">{{ $agreement->superAdmin->phone }}</div>
                    </div>
                    @endif
                    <div class="flex justify-between items-center py-2">
                        <div class="text-sm" style="color: var(--text-secondary);">Agreement Status:</div>
                        <div class="font-medium">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                                {{ ucfirst($agreement->status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-color);">
                    <div class="flex space-x-2 flex-wrap gap-2">
                        <button type="button"
                                onclick="sendReminder({{ $agreement->id }}, '{{ addslashes($agreement->agreement_number) }}', this)"
                                class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-bell mr-1"></i> Send Reminder
                        </button>
                        <button type="button" onclick="contactSuperAdmin({{ $agreement->superAdmin->id }})"
                                class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-envelope mr-1"></i> Contact
                        </button>
                    </div>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="fas fa-user-slash text-3xl mb-2" style="color: var(--text-secondary);"></i>
                    <p style="color: var(--text-secondary);">Super Admin not found</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">This super admin may have been deleted</p>
                </div>
                @endif
            </div>

            {{-- Documents --}}
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-alt mr-2" style="color: var(--warning);"></i> Documents
                </h3>

                <div class="space-y-3">
                    @if($agreement->agreement_pdf_path)
                    <a href="{{ route('developer.billing.generate-agreement-pdf', $agreement->id) }}"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                       style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-file-pdf" style="color: var(--danger);"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm" style="color: var(--text-primary);">Agreement Document</div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">View or download the agreement</div>
                        </div>
                        <i class="fas fa-download" style="color: var(--text-secondary);"></i>
                    </a>
                    @endif

                    @if($agreement->signed_agreement_pdf_path)
                    <a href="{{ route('developer.billing.download-signed-agreement', $agreement->id) }}"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                       style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-file-signature" style="color: var(--success);"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm" style="color: var(--text-primary);">Signed Agreement</div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">Download the fully executed agreement</div>
                        </div>
                        <i class="fas fa-download" style="color: var(--text-secondary);"></i>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Timeline --}}
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-stream mr-2" style="color: var(--secondary);"></i> Agreement Timeline
                </h3>

                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-item-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="timeline-item-content">
                            <div class="timeline-item-title" style="color: var(--text-primary);">Created</div>
                            <div class="timeline-item-date" style="color: var(--text-secondary);">
                                {{ $agreement->created_at->format('M d, Y H:i') }}
                            </div>
                            <div class="timeline-item-description" style="color: var(--text-secondary);">
                                Agreement was created
                            </div>
                        </div>
                    </div>

                    @if($agreement->signing_invitation_sent_at)
                    <div class="timeline-item">
                        <div class="timeline-item-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="timeline-item-content">
                            <div class="timeline-item-title" style="color: var(--text-primary);">Signing Invitation Sent</div>
                            <div class="timeline-item-date" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($agreement->signing_invitation_sent_at)->format('M d, Y H:i') }}
                            </div>
                            <div class="timeline-item-description" style="color: var(--text-secondary);">
                                Signature request sent to Super Admin
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($agreement->signing_completed_at)
                    <div class="timeline-item">
                        <div class="timeline-item-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="timeline-item-content">
                            <div class="timeline-item-title" style="color: var(--text-primary);">Signing Completed</div>
                            <div class="timeline-item-date" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($agreement->signing_completed_at)->format('M d, Y H:i') }}
                            </div>
                            <div class="timeline-item-description" style="color: var(--text-secondary);">
                                All parties have signed the agreement
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($agreement->agreed_at)
                    <div class="timeline-item">
                        <div class="timeline-item-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="timeline-item-content">
                            <div class="timeline-item-title" style="color: var(--text-primary);">Agreed</div>
                            <div class="timeline-item-date" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($agreement->agreed_at)->format('M d, Y H:i') }}
                            </div>
                            <div class="timeline-item-description" style="color: var(--text-secondary);">
                                Agreement was accepted and activated
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($agreement->termination_date)
                    <div class="timeline-item">
                        <div class="timeline-item-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="timeline-item-content">
                            <div class="timeline-item-title" style="color: var(--text-primary);">Terminated</div>
                            <div class="timeline-item-date" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($agreement->termination_date)->format('M d, Y') }}
                            </div>
                            <div class="timeline-item-description" style="color: var(--text-secondary);">
                                {{ $agreement->termination_reason ?? 'Agreement terminated' }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Quick Actions
                </h3>

                <div class="space-y-2">
                    <a href="{{ route('developer.billing.reports', ['agreement_id' => $agreement->id]) }}"
                       class="flex items-center p-3 rounded-lg transition-colors duration-200 group tool-link w-full">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-chart-bar text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Generate Report</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-secondary);"></i>
                    </a>

                    <button type="button" onclick="sendInvoice({{ $agreement->id }})"
                            class="flex items-center p-3 rounded-lg transition-colors duration-200 group tool-link w-full text-left">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-file-invoice text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Send Invoice</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-secondary);"></i>
                    </button>

                    @if($agreement->signed_agreement_pdf_path)
                    <button type="button" onclick="downloadSignedAgreement({{ $agreement->id }})"
                            class="flex items-center p-3 rounded-lg transition-colors duration-200 group tool-link w-full text-left">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-file-signature text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Download Signed Agreement</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-secondary);"></i>
                    </button>
                    @endif

                    @if($agreement->signatures && $agreement->signatures->isNotEmpty())
                    <a href="{{ route('developer.billing.signature-audit', $agreement->id) }}"
                       class="flex items-center p-3 rounded-lg transition-colors duration-200 group tool-link w-full">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-history text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">View Signature Audit</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-secondary);"></i>
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     MODALS — all now use the scrollable layout
     ============================================================ --}}

{{-- Update Agreement Modal --}}
<div id="updateAgreementModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('updateAgreementModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-edit mr-2" style="color: var(--warning);"></i> Update Billing Agreement
                </h3>
                <button type="button" onclick="closeModal('updateAgreementModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="updateAgreementForm" method="POST" action="{{ route('developer.billing.update-agreement', $agreement->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">New Amount *</label>
                            <input type="number" name="amount" value="{{ $agreement->amount }}"
                                   class="w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   min="100" max="100000" step="0.01" required>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Description *</label>
                            <textarea name="description" rows="3" required
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;">{{ $agreement->description }}</textarea>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Notes (Optional)</label>
                            <textarea name="notes" rows="2"
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;">{{ $agreement->notes }}</textarea>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Change Reason *</label>
                            <textarea name="change_reason" rows="2" required
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;"
                                      placeholder="Why are you updating this agreement?"></textarea>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Effective Date *</label>
                            <input type="date" name="effective_date" value="{{ now()->format('Y-m-d') }}"
                                   class="w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   min="{{ now()->format('Y-m-d') }}" required>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="regenerate_pdf" value="1" class="mr-2 rounded" checked>
                                <span class="text-sm" style="color: var(--text-secondary);">Regenerate PDF</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resend_for_signature" value="1" class="mr-2 rounded">
                                <span class="text-sm" style="color: var(--text-secondary);">Resend for Signature</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('updateAgreementModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" form="updateAgreementForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send Update for Approval
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Terminate Modal --}}
<div id="terminateAgreementModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('terminateAgreementModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times mr-2" style="color: var(--danger);"></i> Terminate Billing Agreement
                </h3>
                <button type="button" onclick="closeModal('terminateAgreementModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="terminateAgreementForm" method="POST" action="{{ route('developer.billing.terminate-agreement', $agreement->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Termination Reason *</label>
                            <textarea name="termination_reason" rows="3" required
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;"
                                      placeholder="Why are you terminating this agreement?"></textarea>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Effective Date *</label>
                            <input type="date" name="effective_date" value="{{ now()->format('Y-m-d') }}"
                                   class="w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   min="{{ now()->format('Y-m-d') }}" required>
                        </div>

                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--text-primary);">Warning!</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Terminating this agreement will stop all future billing and notifications.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('terminateAgreementModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" form="terminateAgreementForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger);">
                    <i class="fas fa-times mr-2"></i> Terminate Agreement
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Record Payment Modal --}}
<div id="recordPaymentModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('recordPaymentModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-plus-circle mr-2" style="color: var(--success);"></i> Record New Payment
                </h3>
                <button type="button" onclick="closeModal('recordPaymentModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="recordPaymentForm" method="POST" action="{{ route('developer.billing.record-payment', $agreement->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Amount *</label>
                            <input type="number" name="amount_paid" min="0"
                                   max="{{ $agreement->amount - $agreement->amount_received }}"
                                   step="0.01" required
                                   class="w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                Outstanding: {{ formatCurrency($agreement->amount - $agreement->amount_received, $agreement->currency) }}
                            </p>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Payment Date *</label>
                            <input type="date" name="payment_date" max="{{ now()->format('Y-m-d') }}"
                                   value="{{ now()->format('Y-m-d') }}" required
                                   class="w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Payment Method *</label>
                            <select name="payment_method" required
                                    class="w-full p-3 rounded-lg border"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                @foreach(($paymentMethods ?? []) as $key => $method)
                                <option value="{{ $key }}">{{ $method['name'] ?? ucfirst($key) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Transaction Reference</label>
                            <input type="text" name="transaction_reference"
                                   class="w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Bank reference or transaction ID">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;"
                                      placeholder="Any additional notes about this payment"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('recordPaymentModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" form="recordPaymentForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-check mr-2"></i> Record Payment
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Send for Signing Modal --}}
<div id="sendForSigningModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('sendForSigningModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-2" style="color: var(--info);"></i> Send for Signature
                </h3>
                <button type="button" onclick="closeModal('sendForSigningModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="sendForSigningForm" method="POST" action="{{ route('developer.billing.send-agreement-signing', $agreement->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                This will send a signature request to the super admin via email.
                            </p>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Personal Message (Optional)</label>
                            <textarea name="message" rows="3"
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;"
                                      placeholder="Add a personal message to the super admin..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('sendForSigningModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" form="sendForSigningForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Resend Signature Modal --}}
<div id="resendSignatureModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('resendSignatureModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-2" style="color: var(--info);"></i> Resend Signature Request
                </h3>
                <button type="button" onclick="closeModal('resendSignatureModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="resendSignatureForm" method="POST" action="{{ route('developer.billing.send-agreement-signing', $agreement->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                You have already signed this agreement. This will resend the signature request to the super admin.
                            </p>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Personal Message (Optional)</label>
                            <textarea name="message" id="resendSignatureMessage" rows="3"
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;"
                                      placeholder="Add a personal message to the super admin..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('resendSignatureModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" form="resendSignatureForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Resend Request
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Revoke Signature Modal --}}
<div id="revokeSignatureModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('revokeSignatureModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Revoke Signatures
                </h3>
                <button type="button" onclick="closeModal('revokeSignatureModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="revokeSignatureForm" method="POST" action="{{ route('developer.billing.revoke-signature', $agreement->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--warning);">Important!</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Revoking signatures will reset the agreement to pending status.
                                        All parties will need to sign again.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Revocation Reason</label>
                            <textarea name="reason" rows="2"
                                      class="w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px;"
                                      placeholder="Why are you revoking the signatures?"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('revokeSignatureModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" form="revokeSignatureForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger);">
                    <i class="fas fa-times-circle mr-2"></i> Revoke Signatures
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// ============================================
// HELPER: Fetch JSON with BOM + HTML detection
// ============================================
function fetchJSON(url, options = {}) {
    options.headers = {
        ...options.headers,
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
    };
    options.credentials = 'same-origin';

    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
                        console.error('HTML error response received:', text.substring(0, 200));
                        throw new Error('Server returned an HTML error page. Please check the server logs.');
                    }
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                });
            }
            return response.text();
        })
        .then(text => {
            if (text.charCodeAt(0) === 0xFEFF || text.substring(0, 1) === '\uFEFF') {
                text = text.substring(1);
            }
            text = text.trim();

            if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                console.error('HTML response received:', text.substring(0, 200));
                const errorMatch = text.match(/<title>(.*?)<\/title>/);
                const errorMsg = errorMatch ? errorMatch[1] : 'Server returned HTML instead of JSON';
                throw new Error('Server Error: ' + errorMsg);
            }

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error. Text:', text.substring(0, 200));
                throw new Error('Invalid JSON response: ' + e.message);
            }
        });
}

// ============================================
// MODAL HELPERS
// ============================================
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function showUpdateAgreementModal()       { showModal('updateAgreementModal'); }
function showSendForSigningModal()        { showModal('sendForSigningModal'); }
function showRevokeSignatureModal()       { showModal('revokeSignatureModal'); }

function showTerminateAgreementModal() {
    const modal = document.getElementById('terminateAgreementModal');
    if (!modal) return;
    const dateInput = modal.querySelector('input[name="effective_date"]');
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
    showModal('terminateAgreementModal');
}

function showResendSignatureModal() {
    const input = document.getElementById('resendSignatureMessage');
    if (input) input.value = '';
    showModal('resendSignatureModal');
}

function recordNewPayment() {
    const modal = document.getElementById('recordPaymentModal');
    if (!modal) return;

    const dateInput = modal.querySelector('input[name="payment_date"]');
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    const amountInput = modal.querySelector('input[name="amount_paid"]');
    if (amountInput) amountInput.value = {{ max(0, $agreement->amount - $agreement->amount_received) }};

    showModal('recordPaymentModal');
}

// ============================================
// PDF
// ============================================
function generateAgreementPdf(agreementId) {
    showToast('Generating PDF...', 'info');
    fetchJSON(`/developer/billing/agreement/${agreementId}/generate-pdf`, { method: 'POST' })
        .then(data => {
            if (data.success) {
                showToast('PDF generated successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Failed to generate PDF: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Generate PDF error:', error);
            showToast('Error: ' + error.message, 'error');
        });
}

// ============================================
// SEND REMINDER
// ============================================
function sendReminder(agreementId, agreementNumber, buttonEl) {
    if (!confirm(`Send reminder to the super admin for agreement ${agreementNumber || agreementId}?`)) {
        return;
    }

    const originalHtml = buttonEl ? buttonEl.innerHTML : null;
    if (buttonEl) {
        buttonEl.disabled = true;
        buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
    }

    showToast('Sending reminder...', 'info');

    fetchJSON(`/developer/billing/agreements/${agreementId}/send-reminder`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reminder_type: 'payment' })
    })
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Reminder sent successfully!', 'success');
        } else {
            showToast('Failed to send reminder: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Send reminder error:', error);
        showToast('Error: ' + error.message, 'error');
    })
    .finally(() => {
        if (buttonEl) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;
        }
    });
}

function contactSuperAdmin(superAdminId) {
    showToast('Contact feature coming soon!', 'info');
}

// ============================================
// SEND INVOICE
// ============================================
function sendInvoice(agreementId) {
    if (!confirm('Send invoice to super admin?')) return;

    showToast('Sending invoice...', 'info');
    fetchJSON(`/developer/billing/agreement/${agreementId}/send-invoice`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Invoice sent successfully!', 'success');
        } else {
            showToast('Failed to send invoice: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Send invoice error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

// ============================================
// DOWNLOAD SIGNED AGREEMENT
// ============================================
function downloadSignedAgreement(agreementId) {
    window.location.href = `/developer/billing/agreement/${agreementId}/download-signed`;
}

// ============================================
// VERIFY SIGNATURE
// ============================================
function verifySignature(signatureId) {
    fetchJSON(`/developer/billing/signatures/${signatureId}/verify`, { method: 'GET' })
        .then(data => {
            if (data.success) {
                showToast(
                    'Signature verified! Hash: ' +
                    (data.verification_hash ? data.verification_hash.substring(0, 16) + '...' : 'N/A'),
                    'success'
                );
            } else {
                showToast('Signature verification failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Verify signature error:', error);
            showToast('Error verifying signature: ' + error.message, 'error');
        });
}

// ============================================
// TOAST
// ============================================
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ['updateAgreementModal', 'terminateAgreementModal', 'recordPaymentModal',
             'sendForSigningModal', 'resendSignatureModal', 'revokeSignatureModal']
                .forEach(id => closeModal(id));
        }
    });
});
</script>

<style>
/* =========================================================
   MODAL SHELL — fixed header/footer, scrollable body
   ========================================================= */
.modal-container {
    display: flex;
    flex-direction: column;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;               /* cap the height so it never overwhelms the viewport */
    overflow: hidden;               /* keep rounded corners clean */
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    animation: modalFadeIn 0.25s ease-out;
}

.modal-header {
    flex-shrink: 0;                 /* header never shrinks */
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.modal-body {
    flex: 1 1 auto;                 /* take remaining space */
    overflow-y: auto;               /* THE FIX — scroll only the body */
    overflow-x: hidden;
    padding: 1.25rem;
    -webkit-overflow-scrolling: touch;   /* smooth momentum scrolling on mobile */
    scrollbar-width: thin;
    scrollbar-color: rgba(var(--primary-rgb), 0.35) transparent;
    /* subtle inner shadow at the top to hint content continues above */
    box-shadow: inset 0 8px 6px -6px rgba(0, 0, 0, 0.06);
}

/* WebKit scrollbar styling so it matches the theme */
.modal-body::-webkit-scrollbar {
    width: 8px;
}
.modal-body::-webkit-scrollbar-track {
    background: transparent;
}
.modal-body::-webkit-scrollbar-thumb {
    background-color: rgba(var(--primary-rgb), 0.3);
    border-radius: 4px;
    border: 2px solid transparent;
    background-clip: padding-box;
}
.modal-body::-webkit-scrollbar-thumb:hover {
    background-color: rgba(var(--primary-rgb), 0.5);
}

.modal-footer {
    flex-shrink: 0;                 /* footer never shrinks */
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: background-color 0.2s;
}

.modal-close-btn:hover {
    background-color: rgba(var(--secondary-rgb), 0.1);
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.96) translateY(-8px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* =========================================================
   Table hover — primary tint, no white flash
   ========================================================= */
.agreement-table tbody tr {
    background-color: transparent !important;
    transition: background-color 0.15s ease !important;
}

.agreement-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.04) !important;
}

html.dark .agreement-table tbody tr:hover,
body.dark .agreement-table tbody tr:hover,
body[data-theme="dark"] .agreement-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.08) !important;
}

.agreement-table tbody tr:hover > td {
    border-color: var(--border-color) !important;
}

/* Tool link */
.tool-link {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    transition: all 0.2s ease !important;
    cursor: pointer;
}

.tool-link:hover {
    background-color: var(--card-bg) !important;
    border-color: var(--primary) !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.1);
}

.tool-link:hover span {
    color: var(--primary) !important;
}

.tool-link:hover .fas.fa-chevron-right {
    color: var(--primary) !important;
    transform: translateX(2px);
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 1.5rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.625rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: var(--border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-item:last-child { margin-bottom: 0; }

.timeline-item-icon {
    position: absolute;
    left: -1.5rem;
    top: 0;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--card-bg);
    border: 2px solid var(--border-color);
    z-index: 1;
}

.timeline-item-icon i { font-size: 0.7rem; }

.timeline-item-content {
    background-color: var(--card-bg);
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
}

.timeline-item-title { font-weight: 500; font-size: 0.875rem; }
.timeline-item-date { font-size: 0.75rem; margin-top: 0.25rem; }
.timeline-item-description { font-size: 0.75rem; margin-top: 0.25rem; }

/* Buttons */
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

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }

    .modal-container {
        max-width: 100%;
        margin: 1rem;
        max-height: 85vh;
    }

    .modal-footer {
        flex-direction: column;
    }

    .modal-footer button {
        width: 100%;
        margin-bottom: 0.5rem;
    }

    .timeline { padding-left: 1rem; }
}

@media (max-width: 640px) {
    .modal-container {
        margin: 0.5rem;
        max-height: 80vh;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
}
</style>
@endsection