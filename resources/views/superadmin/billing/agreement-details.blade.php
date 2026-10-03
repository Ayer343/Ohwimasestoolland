{{-- resources/views/superadmin/billing/agreement-details.blade.php --}}
@extends('layouts.app')

@php
    $pageTitle = 'Agreement Details - ' . ($agreement->agreement_number ?? '');

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    if (!function_exists('getStatusColor')) {
        function getStatusColor($status) {
            $colors = [
                'active'     => 'success',
                'pending'    => 'warning',
                'completed'  => 'info',
                'terminated' => 'danger',
                'superseded' => 'secondary',
                'cancelled'  => 'secondary',
            ];
            return $colors[$status] ?? 'secondary';
        }
    }

    if (!function_exists('getPaymentStatusColor')) {
        function getPaymentStatusColor($status) {
            $colors = [
                'paid'    => 'success',
                'partial' => 'warning',
                'unpaid'  => 'danger',
                'overdue' => 'danger',
            ];
            return $colors[$status] ?? 'secondary';
        }
    }

    if (!function_exists('getSignatureTypeIcon')) {
        function getSignatureTypeIcon($signatureFormat) {
            $icons = [
                'typed'  => 'fas fa-keyboard',
                'draw'   => 'fas fa-paint-brush',
                'upload' => 'fas fa-upload',
            ];
            return $icons[$signatureFormat] ?? 'fas fa-signature';
        }
    }

    if (!function_exists('getSignatureTypeDisplay')) {
        function getSignatureTypeDisplay($signatureFormat) {
            $names = [
                'typed'  => 'Typed Signature',
                'draw'   => 'Drawn Signature',
                'upload' => 'Uploaded Signature',
            ];
            return $names[$signatureFormat] ?? 'Digital Signature';
        }
    }

    if (!function_exists('getPaymentMethodIcon')) {
        function getPaymentMethodIcon($method) {
            return match ($method) {
                'bank_transfer'                                  => 'fas fa-university',
                'mobile_money', 'mtn', 'telecel', 'airteltigo'   => 'fas fa-mobile-alt',
                'cash'                                           => 'fas fa-money-bill-wave',
                'check'                                          => 'fas fa-file-invoice',
                default                                          => 'fas fa-credit-card',
            };
        }
    }

    // Parse metadata
    $metadata = $agreement->metadata
        ? (is_array($agreement->metadata) ? $agreement->metadata : json_decode($agreement->metadata, true))
        : [];

    // ================================================================
    // Payment Method Display
    // ================================================================
    $paymentMethod      = $agreement->payment_method ?? 'bank_transfer';
    $paymentMethodLabel = '';
    $paymentDetails     = [];

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

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;

    // Signature ID validation
    $signatureId = session('signature_id');
    $hasValidSignatureId = !empty($signatureId) && is_numeric($signatureId);

    // ================================================================
    // Signature status calculation
    // ================================================================
    $developerSignatureExists  = false;
    $superAdminSignatureExists = false;

    if (isset($signatures) && $signatures instanceof \Illuminate\Support\Collection && $signatures->count() > 0) {
        $developerSignatureExists = $signatures->contains(function ($sig) {
            return ($sig['user_type'] ?? '') === 'Developer'
                || ($sig['signature_type'] ?? '') === 'developer';
        });

        $superAdminSignatureExists = $signatures->contains(function ($sig) {
            return ($sig['user_type'] ?? '') === 'Super Admin'
                || ($sig['signature_type'] ?? '') === 'super_admin';
        });
    }

    $bothPartiesSigned = ($signatureStatus['all_signed'] ?? false) ||
                         ($developerSignatureExists && $superAdminSignatureExists);

    $effectiveStatus    = $agreement->status;
    $showPendingWarning = false;
    $statusMessage      = '';

    if ($bothPartiesSigned && $agreement->status !== 'active') {
        $showPendingWarning = true;
        $statusMessage = 'Both parties have signed but the agreement status is pending. Please contact support to activate this agreement.';
    } elseif ($agreement->status == 'active') {
        $statusMessage = 'This agreement is active and binding. Both parties have signed.';
    } elseif ($agreement->status == 'pending') {
        if ($developerSignatureExists && !$superAdminSignatureExists) {
            $statusMessage = 'Developer has signed. Waiting for your signature to activate.';
        } elseif (!$developerSignatureExists && $superAdminSignatureExists) {
            $statusMessage = 'You have signed. Waiting for developer signature to activate.';
        } else {
            $statusMessage = 'This agreement is pending signatures from both parties.';
        }
    } elseif ($agreement->status == 'completed') {
        $statusMessage = 'This agreement has been completed.';
    } elseif ($agreement->status == 'terminated') {
        $statusMessage = 'This agreement has been terminated.';
    } else {
        $statusMessage = 'This agreement is ' . $agreement->status;
    }

    $canSign = ($agreement->status == 'pending' && !$superAdminSignatureExists);
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
                        Agreement Details
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag"></i>
                        <span class="font-mono">{{ $agreement->agreement_number }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-user-shield"></i>
                        <span>Super Admin: {{ auth()->user()->name }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-code"></i>
                        <span>Developer: {{ $agreement->developerSetting->developer_name ?? 'Developer' }}</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2 flex-wrap gap-2">
                    <a href="{{ route('superadmin.billing.agreements-list') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Back to List
                    </a>
                    <a href="{{ route('superadmin.billing.dashboard') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chart-line mr-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation Card --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
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

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>

                <a href="{{ route('superadmin.billing.payment-history') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-history mr-1"></i> Payment History
                </a>

                @if($canSign)
                <a href="{{ route('superadmin.billing.view-agreement-signing', $agreement->id) }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium text-white"
                   style="background-color: var(--warning); border-color: var(--warning);">
                    <i class="fas fa-signature mr-1"></i> Sign Now
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Agreement Status Banner --}}
    <div class="card p-6" style="border-left: 4px solid var(--{{ $showPendingWarning ? 'danger' : getStatusColor($effectiveStatus) }});">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--{{ $showPendingWarning ? 'danger' : getStatusColor($effectiveStatus) }}-rgb), 0.1); color: var(--{{ $showPendingWarning ? 'danger' : getStatusColor($effectiveStatus) }});">
                        @if($showPendingWarning)
                            <i class="fas fa-exclamation-triangle text-lg"></i>
                        @elseif($effectiveStatus == 'active')
                            <i class="fas fa-check-circle text-lg"></i>
                        @elseif($effectiveStatus == 'pending')
                            <i class="fas fa-clock text-lg"></i>
                        @elseif($effectiveStatus == 'completed')
                            <i class="fas fa-flag-checkered text-lg"></i>
                        @else
                            <i class="fas fa-times-circle text-lg"></i>
                        @endif
                    </div>
                </div>
                <div>
                    @if($showPendingWarning)
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Agreement Status:
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ml-2"
                                  style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-exclamation-triangle mr-1" style="font-size: 0.5rem;"></i>
                                SIGNATURE MISMATCH
                            </span>
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $statusMessage }}
                        </p>
                    @else
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Agreement Status:
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ml-2 badge-{{ getStatusColor($effectiveStatus) }}">
                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                {{ ucfirst($effectiveStatus) }}
                            </span>
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> {{ $statusMessage }}
                        </p>
                    @endif

                    @if($bothPartiesSigned && $agreement->status != 'active')
                    <div class="mt-2 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            <span style="color: var(--text-primary);">Both parties have signed this agreement.</span>
                            <span class="ml-2 text-xs" style="color: var(--text-secondary);">Status should be "active" - Contact support if this persists.</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if($canSign)
                <a href="{{ route('superadmin.billing.view-agreement-signing', $agreement->id) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-signature mr-2"></i> Sign Agreement
                </a>
                @endif

                @if($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path))
                <a href="{{ route('superadmin.billing.download-signed-agreement', $agreement->id) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-download mr-2"></i> Download Signed PDF
                </a>
                @endif

                @if($bothPartiesSigned)
                <button type="button" onclick="showSignatureVerification()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-shield-alt mr-2"></i> Verify Signatures
                </button>
                @endif

                @if($hasValidSignatureId && $superAdminSignatureExists)
                <a href="{{ route('superadmin.billing.download-signature', $signatureId) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-download mr-2"></i> Download Your Signature
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Agreement Details Card --}}
        <div class="card lg:col-span-2">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Agreement Information
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Basic Information --}}
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
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Billing Frequency</label>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $agreement->billing_frequency == 'one_time' ? 'warning' : ($agreement->billing_frequency == 'yearly' ? 'info' : 'primary') }}">
                                {{ ucfirst($agreement->billing_frequency) }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Start Date</label>
                            <div style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($agreement->start_date)->format('F j, Y') }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Created Date</label>
                            <div style="color: var(--text-primary);">{{ $agreement->created_at->format('F j, Y') }}</div>
                        </div>
                    </div>

                    {{-- Financial Information --}}
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Agreed Amount</label>
                            <div class="text-xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Amount Paid</label>
                            <div class="text-xl font-bold" style="color: {{ $agreement->amount_received >= $agreement->amount ? 'var(--success)' : 'var(--warning)' }};">
                                {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Balance Due</label>
                            <div class="text-xl font-bold" style="color: {{ $agreement->amount - $agreement->amount_received > 0 ? 'var(--danger)' : 'var(--success)' }};">
                                {{ formatCurrency($agreement->amount - $agreement->amount_received, $agreement->currency) }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Payment Status</label>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-medium badge-{{ getPaymentStatusColor($agreement->payment_status) }}">
                                <i class="fas fa-{{ $agreement->payment_status == 'paid' ? 'check' : ($agreement->payment_status == 'partial' ? 'exclamation' : 'times') }} mr-1"></i>
                                {{ ucfirst($agreement->payment_status) }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Payment Method Section --}}
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
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
                                    Check must be payable to "{{ $paymentDetails['Payable To'] ?? 'Developer' }}".
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Notes Section --}}
                @if($agreement->notes)
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Additional Notes</label>
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); color: var(--text-primary);">
                        {{ $agreement->notes }}
                    </div>
                </div>
                @endif

                {{-- Metadata Section --}}
                @if(!empty($metadata) && count($metadata) > 0)
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Additional Details</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($metadata as $key => $value)
                            @if(!in_array($key, ['agreement_number', 'start_date', 'payment_mobile_number', 'payment_account_name', 'payment_account_number', 'payment_bank_name', 'payment_bank_branch', 'payment_method']))
                            <div>
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                <span class="text-sm ml-1" style="color: var(--text-primary);">{{ is_array($value) ? json_encode($value) : $value }}</span>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Developer Information Card --}}
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-code mr-2" style="color: var(--primary);"></i> Developer Information
                </h3>
            </div>
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="mr-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-code text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">{{ $agreement->developerSetting->developer_name ?? 'Developer' }}</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $agreement->developerSetting->developer_company ?? 'Development Company' }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @if($agreement->developerSetting->developer_email ?? false)
                    <div class="flex items-center">
                        <i class="fas fa-envelope mr-3" style="color: var(--text-secondary); width: 20px;"></i>
                        <a href="mailto:{{ $agreement->developerSetting->developer_email }}"
                           class="text-sm" style="color: var(--primary);">{{ $agreement->developerSetting->developer_email }}</a>
                    </div>
                    @endif

                    @if($agreement->developerSetting->developer_phone ?? false)
                    <div class="flex items-center">
                        <i class="fas fa-phone mr-3" style="color: var(--text-secondary); width: 20px;"></i>
                        <a href="tel:{{ $agreement->developerSetting->developer_phone }}"
                           class="text-sm" style="color: var(--text-primary);">{{ $agreement->developerSetting->developer_phone }}</a>
                    </div>
                    @endif

                    @if($agreement->developerSetting->developer_address ?? false)
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt mr-3 mt-1" style="color: var(--text-secondary); width: 20px;"></i>
                        <span class="text-sm flex-1" style="color: var(--text-primary);">{{ $agreement->developerSetting->developer_address }}</span>
                    </div>
                    @endif

                    @if($agreement->developerSetting->created_at ?? false)
                    <div class="flex items-center">
                        <i class="fas fa-calendar-plus mr-3" style="color: var(--text-secondary); width: 20px;"></i>
                        <span class="text-sm" style="color: var(--text-primary);">
                            Working since {{ \Carbon\Carbon::parse($agreement->developerSetting->created_at)->format('M Y') }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Signatures Section --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-signature mr-2" style="color: var(--primary);"></i> Digital Signatures
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                <i class="fas fa-shield-alt mr-1"></i> All signatures are digitally hashed and timestamped for verification
            </p>
        </div>
        <div class="p-6">
            @if(isset($signatures) && $signatures->count() > 0)
            <div class="mb-4">
                <div class="flex flex-wrap gap-2 mb-4">
                    @if($bothPartiesSigned)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                        <i class="fas fa-check-circle mr-1"></i> All Parties Signed
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                        <i class="fas fa-shield-alt mr-1"></i> Digitally Verified
                    </span>
                    @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-warning">
                        <i class="fas fa-clock mr-1"></i> Pending Signatures
                    </span>
                    @endif

                    @if($developerSignatureExists)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-primary">
                        <i class="fas fa-code mr-1"></i> Developer Signed
                    </span>
                    @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-secondary">
                        <i class="fas fa-code mr-1"></i> Developer Pending
                    </span>
                    @endif

                    @if($superAdminSignatureExists)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                        <i class="fas fa-user-shield mr-1"></i> You Have Signed
                    </span>
                    @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-warning">
                        <i class="fas fa-user-shield mr-1"></i> Your Signature Pending
                    </span>
                    @endif

                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-info">
                        <i class="fas fa-hashtag mr-1"></i> SHA-256 Hashed
                    </span>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between text-sm mb-1" style="color: var(--text-secondary);">
                        <span>Signature Progress</span>
                        <span>
                            @if($bothPartiesSigned)
                                2/2 Complete ✓
                            @else
                                {{ ($developerSignatureExists ? 1 : 0) + ($superAdminSignatureExists ? 1 : 0) }}/2
                                @if(($developerSignatureExists && !$superAdminSignatureExists))
                                    - Waiting for Your Signature
                                @elseif((!$developerSignatureExists && $superAdminSignatureExists))
                                    - Waiting for Developer Signature
                                @endif
                            @endif
                        </span>
                    </div>
                    <div class="w-full rounded-full h-2.5" style="background-color: rgba(var(--secondary-rgb), 0.15);">
                        @php
                            $signatureCount  = ($developerSignatureExists ? 1 : 0) + ($superAdminSignatureExists ? 1 : 0);
                            $progressPercent = ($signatureCount / 2) * 100;
                        @endphp
                        <div class="h-2.5 rounded-full transition-all duration-500" style="width: {{ $progressPercent }}%; background-color: var(--primary);"></div>
                    </div>
                    @if($bothPartiesSigned && $agreement->signed_agreement_pdf_path)
                    <div class="mt-3 text-center">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                            <i class="fas fa-check-circle mr-1"></i> Agreement Complete - Both Parties Have Signed
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- =========================================================
                 SIGNATURE CARDS — with fixed display priority:
                 1. Stored file image
                 2. Base64 data URI from signature_data
                 3. Typed name in signature font
                 4. Generic placeholder
                 ========================================================= --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($signatures as $signature)
                @php
                    $signatureHasId       = !empty($signature['id']);
                    $isDeveloperSignature = ($signature['user_type'] ?? '') === 'Developer'
                                            || ($signature['signature_type'] ?? '') === 'developer';

                    $signatureUserType = $signature['user_type']
                                         ?? ($isDeveloperSignature ? 'Developer' : 'Super Admin');
                    $signatureColor    = $isDeveloperSignature ? 'primary' : 'success';

                    $signatureName     = $signature['signature_name']  ?? 'Signed';
                    $signatureDate     = $signature['signature_date']  ?? now()->format('F j, Y');
                    $signatureFormat   = $signature['signature_format']
                                         ?? ($signature['signature_type'] ?? 'typed');
                    $signaturePath     = $signature['signature_path']  ?? null;
                    $signatureData     = $signature['signature_data']  ?? null;
                    $ipAddress         = $signature['ip_address']      ?? null;

                    // Resolve display priority
                    $hasImageFile = $signaturePath && \Illuminate\Support\Facades\Storage::exists($signaturePath);
                    $hasDataUri   = is_string($signatureData)
                                    && str_starts_with($signatureData, 'data:image');
                    $hasTypedName = !empty($signatureName);
                @endphp
                <div class="border rounded-lg p-4 signature-card" style="border-color: var(--border-color);">
                    {{-- Signature Header --}}
                    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--{{ $signatureColor }}-rgb), 0.1); color: var(--{{ $signatureColor }});">
                                    @if($isDeveloperSignature)
                                    <i class="fas fa-code"></i>
                                    @else
                                    <i class="fas fa-user-shield"></i>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold" style="color: var(--text-primary);">{{ $signature['user_name'] ?? 'Unknown' }}</h4>
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $signatureUserType }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                <i class="{{ getSignatureTypeIcon($signatureFormat) }} mr-1"></i>
                                {{ getSignatureTypeDisplay($signatureFormat) }}
                            </span>
                        </div>
                    </div>

                    {{-- Signature Body --}}
                    <div class="space-y-3">
                        <div class="signature-display-container p-3 rounded-lg"
     style="background-color: var(--card-bg); border: 1px solid var(--border-color); min-height: 80px; display: flex; align-items: center; justify-content: center; overflow: hidden;">

    @php
        $imageSrc = null;

        // Priority 1: signature_data is already a data URI
        if (is_string($signatureData) && str_starts_with($signatureData, 'data:image')) {
            $imageSrc = $signatureData;
        }
        // Priority 2: read the file from the default (local) disk as base64
        elseif ($signaturePath) {
            try {
                if (\Illuminate\Support\Facades\Storage::exists($signaturePath)) {
                    $bytes = \Illuminate\Support\Facades\Storage::get($signaturePath);
                    if ($bytes !== false && strlen($bytes) > 0) {
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';
                        if (str_starts_with($mime, 'image/')) {
                            $imageSrc = 'data:' . $mime . ';base64,' . base64_encode($bytes);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // silently fall through
            }
        }
        // Priority 3: raw base64 in signature_data (no data: prefix)
        if (!$imageSrc && is_string($signatureData) && strlen($signatureData) > 100
                && preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', substr($signatureData, 0, 100))) {
            $decoded = @base64_decode($signatureData, true);
            if ($decoded !== false) {
                $info = @getimagesizefromstring($decoded);
                if ($info !== false) {
                    $imageSrc = 'data:' . $info['mime'] . ';base64,' . $signatureData;
                }
            }
        }
    @endphp

    @if($imageSrc)
        <img src="{{ $imageSrc }}"
             alt="Signature of {{ $signatureName }}"
             class="signature-image"
             style="max-height: 70px; max-width: 100%; object-fit: contain;">

    @elseif(!empty($signatureName))
        <div class="typed-signature-display"
             style="font-family: 'Dancing Script', cursive; font-size: 2rem; color: var(--text-primary); text-align: center; line-height: 1.2;">
            {{ $signatureName }}
        </div>

    @else
        <div class="text-center" style="color: var(--text-secondary);">
            <i class="fas fa-signature text-2xl mb-2"></i>
            <div class="text-sm">Digital Signature</div>
        </div>
    @endif
</div>

                        {{-- Signature Details --}}
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Signed Name:</span>
                                <span style="color: var(--text-primary); font-weight: 500;">{{ $signatureName }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Signature Date:</span>
                                <span style="color: var(--text-primary);">{{ $signatureDate }}</span>
                            </div>
                            @if($ipAddress)
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">IP Address:</span>
                                <span style="color: var(--text-primary); font-size: 0.75rem;">{{ $ipAddress }}</span>
                            </div>
                            @endif
                            @if(!empty($signature['digital_hash']))
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Digital Hash:</span>
                                <span style="color: var(--text-primary); font-size: 0.7rem; font-family: monospace;" title="{{ $signature['digital_hash'] }}">
                                    {{ substr($signature['digital_hash'], 0, 16) }}...
                                </span>
                            </div>
                            @endif
                        </div>

                        {{-- Signature Actions --}}
                        <div class="flex justify-end flex-wrap gap-2 pt-2 border-t" style="border-color: var(--border-color);">
                            @if($hasImageFile)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($signaturePath) }}"
                               target="_blank"
                               class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-external-link-alt mr-1 text-xs"></i> View
                            </a>
                            @endif

                            @if(!$isDeveloperSignature && $signatureHasId)
                            <a href="{{ route('superadmin.billing.download-signature', $signature['id']) }}"
                               class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-download mr-1 text-xs"></i> Download
                            </a>
                            @endif

                            @if($signatureHasId)
                            <button type="button" onclick="verifySignature({{ $signature['id'] }})"
                                    class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-shield-alt mr-1 text-xs"></i> Verify
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @if($bothPartiesSigned)
            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h4 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-file-pdf mr-2" style="color: var(--danger);"></i> Signed Agreement Document
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> This agreement has been digitally signed by all parties
                            @if($agreement->status == 'active')
                                and is legally binding.
                            @else
                                but the status is still pending. Please contact support if this persists.
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path))
                        <a href="{{ route('superadmin.billing.download-signed-agreement', $agreement->id) }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                            <i class="fas fa-download mr-2"></i> Download PDF
                        </a>
                        @endif
                        <button type="button" onclick="showSignatureVerification()"
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-shield-alt mr-2"></i> Verify All
                        </button>
                    </div>
                </div>
            </div>
            @elseif($agreement->status == 'pending')
            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h4 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2" style="color: var(--warning);"></i> Signatures Required
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($developerSignatureExists && !$superAdminSignatureExists)
                                <i class="fas fa-user-check mr-1" style="color: var(--success);"></i> Developer has signed. <strong>Waiting for your signature.</strong>
                            @elseif(!$developerSignatureExists && $superAdminSignatureExists)
                                <i class="fas fa-user-check mr-1" style="color: var(--success);"></i> You have signed. <strong>Waiting for developer signature.</strong>
                            @else
                                <i class="fas fa-clock mr-1" style="color: var(--warning);"></i> <strong>Waiting for both parties to sign.</strong>
                            @endif
                        </p>
                    </div>
                    @if($canSign)
                    <a href="{{ route('superadmin.billing.view-agreement-signing', $agreement->id) }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-signature mr-2"></i> Sign Now
                    </a>
                    @endif
                </div>
            </div>
            @endif

            @else
            <div class="text-center py-8">
                <div class="inline-block mb-4">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-signature text-2xl"></i>
                    </div>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Signatures Yet</h4>
                <p class="text-sm mb-4 max-w-md mx-auto" style="color: var(--text-secondary);">
                    This agreement hasn't been signed yet. Digital signatures will be recorded here once both parties have signed.
                </p>
                @if($canSign)
                <a href="{{ route('superadmin.billing.view-agreement-signing', $agreement->id) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-signature mr-2"></i> Be the First to Sign
                </a>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Payments History --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Payment History
                </h3>
            </div>
        </div>
        <div class="p-6">
            @if(isset($payments) && $payments->count() > 0)
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
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Confirmed By</th>
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
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <code class="text-xs" style="color: var(--text-secondary);">{{ $payment->transaction_reference ?? 'N/A' }}</code>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $paymentStatusClass = match($payment->status) {
                                        'confirmed'            => 'badge-success',
                                        'pending_confirmation' => 'badge-warning',
                                        'cancelled'            => 'badge-danger',
                                        default                => 'badge-secondary',
                                    };
                                    $paymentStatusText = match($payment->status) {
                                        'confirmed'            => 'Confirmed',
                                        'pending_confirmation' => 'Pending',
                                        'cancelled'            => 'Cancelled',
                                        default                => ucfirst($payment->status),
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $paymentStatusClass }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                    {{ $paymentStatusText }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div style="color: var(--text-primary);">{{ $payment->recorded_by_name ?? 'N/A' }}</div>
                                @if($payment->recorded_at)
                                <div class="text-xs" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->recorded_at)->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div style="color: var(--text-primary);">{{ $payment->confirmed_by_name ?? 'Pending' }}</div>
                                @if($payment->confirmed_at)
                                <div class="text-xs" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->confirmed_at)->format('M d, Y') }}</div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Payments</div>
                        <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($payments->where('status', 'confirmed')->sum('amount_paid'), $agreement->currency) }}</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Payments</div>
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ formatCurrency($payments->where('status', 'pending_confirmation')->sum('amount_paid'), $agreement->currency) }}</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Payment Progress</div>
                        <div class="text-2xl font-bold" style="color: var(--success);">
                            @if($agreement->amount > 0)
                                {{ round(($agreement->amount_received / $agreement->amount) * 100, 1) }}%
                            @else
                                0%
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @else
            <div class="text-center py-8">
                <i class="fas fa-history text-4xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Payment History</h4>
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    No payments have been recorded for this agreement yet.
                </p>
                <div class="text-xs text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i> When you make a payment, the developer will record it and it will appear here.
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Verification Modal --}}
<div id="verificationModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeVerificationModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2" style="color: var(--success);"></i> Signature Verification
                </h3>
                <button type="button" onclick="closeVerificationModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="verificationModalContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin text-2xl mb-3" style="color: var(--primary);"></i>
                        <p style="color: var(--text-secondary);">Verifying signature...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeVerificationModal()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Close
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
// ============================================================
// Fetch helper — handles BOM & HTML error responses
// ============================================================
function fetchJSON(url, options = {}) {
    options.headers = {
        ...options.headers,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
    };
    options.credentials = 'same-origin';

    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
                        throw new Error('Server returned an HTML error page');
                    }
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                });
            }
            return response.text();
        })
        .then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.substring(1);
            }
            text = text.trim();
            if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                throw new Error('Server returned HTML instead of JSON');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response');
            }
        });
}

// ============================================================
// Modal helpers
// ============================================================
function closeVerificationModal() {
    const modal = document.getElementById('verificationModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';

        const content = document.getElementById('verificationModalContent');
        if (content) {
            content.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl mb-3" style="color: var(--primary);"></i>
                    <p style="color: var(--text-secondary);">Verifying signature...</p>
                </div>
            `;
        }
    }
}

function openVerificationModal() {
    const modal = document.getElementById('verificationModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

// ============================================================
// Verify single signature
// ============================================================
function verifySignature(signatureId) {
    if (!signatureId || signatureId === '0') {
        showToast('Invalid signature ID', 'error');
        return;
    }

    openVerificationModal();
    showLoading('Verifying signature...');

    fetchJSON(`/superadmin/billing/signatures/${signatureId}/verify`, { method: 'GET' })
        .then(data => {
            hideLoading();
            if (data.success) {
                showVerificationResults(data);
            } else {
                showToast('Signature verification failed', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showToast('Verification failed: ' + error.message, 'error');
        });
}

// ============================================================
// Verify all signatures
// ============================================================
function showSignatureVerification() {
    const signatureIds = @json(collect($signatures ?? [])->pluck('id')->filter(function ($id) {
        return !empty($id) && $id !== '0';
    })->values());

    if (signatureIds.length === 0) {
        showToast('No signatures to verify', 'warning');
        return;
    }

    openVerificationModal();
    showLoading('Verifying all signatures...');

    Promise.all(signatureIds.map(id =>
        fetchJSON(`/superadmin/billing/signatures/${id}/verify`, { method: 'GET' })
    ))
    .then(results => {
        hideLoading();
        showAllVerificationResults(results);
    })
    .catch(error => {
        hideLoading();
        showToast('Verification failed: ' + error.message, 'error');
    });
}

// ============================================================
// Render verification results
// ============================================================
function showVerificationResults(data) {
    const content = document.getElementById('verificationModalContent');

    let html = '';

    if (data.success && data.verification) {
        html = `
            <div class="text-center">
                <i class="fas fa-check-circle text-4xl mb-3" style="color: var(--success);"></i>
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Signature Verified</h4>
                <div class="text-left mt-4 space-y-2" style="color: var(--text-primary);">
                    <div><strong>Signature Name:</strong> ${escapeHtml(data.verification.signature_name)}</div>
                    <div><strong>Date:</strong> ${escapeHtml(data.verification.signature_date)}</div>
                    <div><strong>User:</strong> ${escapeHtml(data.verification.user_name)}</div>
                    <div><strong>Type:</strong> ${escapeHtml(data.verification.signature_type)}</div>
                    <div><strong>Agreement:</strong> ${escapeHtml(data.verification.agreement_number)}</div>
                    <div class="mt-3 p-2 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <div class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-shield-alt mr-2" style="color: var(--success);"></i>
                            Verified at: ${escapeHtml(data.verification.verified_at)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        html = `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-4xl mb-3" style="color: var(--danger);"></i>
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Verification Failed</h4>
                <p style="color: var(--text-secondary);">${escapeHtml(data.message || 'Unable to verify signature')}</p>
            </div>
        `;
    }

    content.innerHTML = html;
}

function showAllVerificationResults(results) {
    const content = document.getElementById('verificationModalContent');

    let html = '<div class="space-y-4">';
    let allValid = true;

    const signatures = @json($signatures ?? []);

    results.forEach((result, index) => {
        const signature = signatures[index] ?? {};
        const status = result.success ? 'success' : 'danger';
        const icon   = result.success ? 'check-circle' : 'exclamation-triangle';

        html += `
            <div class="p-3 rounded-lg border"
                 style="border-color: rgba(var(--${status}-rgb), 0.3); background-color: rgba(var(--${status}-rgb), 0.05);">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-${icon} mr-2" style="color: var(--${status});"></i>
                            ${escapeHtml(signature.user_name || 'Unknown')} (${escapeHtml(signature.user_type || 'Unknown')})
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            ${result.success ? 'Digitally verified' : 'Verification failed'}
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (!result.success) {
            allValid = false;
        }
    });

    html += `
        <div class="mt-4 p-3 rounded-lg border"
             style="border-color: rgba(var(--${allValid ? 'success' : 'warning'}-rgb), 0.3);
                    background-color: rgba(var(--${allValid ? 'success' : 'warning'}-rgb), 0.05);">
            <div class="font-medium flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-${allValid ? 'check-circle' : 'exclamation-triangle'} mr-2"
                   style="color: var(--${allValid ? 'success' : 'warning'});"></i>
                ${allValid ? 'All signatures digitally verified' : 'Some signatures could not be verified'}
            </div>
            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                ${results.length} signature(s) checked
            </div>
        </div>
    `;

    html += '</div>';
    content.innerHTML = html;
}

// ============================================================
// Loading overlay
// ============================================================
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 mb-4"
                 style="border-color: var(--primary); border-top-color: transparent;"></div>
            <div class="text-white font-medium">${escapeHtml(message)}</div>
        </div>
    `;
    overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// ============================================================
// Toast
// ============================================================
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    const bg = type === 'success' ? 'var(--success)' :
               type === 'error'   ? 'var(--danger)'  :
               type === 'warning' ? 'var(--warning)' : 'var(--info)';

    toast.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full';
    toast.style.backgroundColor = `rgba(${getRgbValue(bg)}, 0.95)`;
    toast.style.color = 'white';
    toast.style.borderLeft = `4px solid ${bg}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200 hover:opacity-80';
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

function getRgbValue(color) {
    if (color.startsWith('var(--')) {
        const varName = color.match(/var\(--([^)]+)\)/)[1];
        const rgbVar = getComputedStyle(document.documentElement).getPropertyValue(`--${varName}-rgb`);
        return rgbVar.trim() || '0,0,0';
    }
    return '0,0,0';
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

// ============================================================
// Init
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeVerificationModal();
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('signed') && urlParams.get('signed') === 'true') {
        showToast('Agreement signed successfully!', 'success');
    }

    @if(session('success'))
        showToast('{{ session('success') }}', 'success');
    @endif

    @if(session('error'))
        showToast('{{ session('error') }}', 'error');
    @endif

    @if(session('signature_complete'))
        showToast('Signature submitted successfully!', 'success');
    @endif

    @if($bothPartiesSigned && $agreement->status != 'active')
        showToast('Both parties have signed but the agreement status is still pending. Please contact support.', 'warning');
    @endif
});
</script>

<style>
/* Modal shell */
.modal-container {
    display: flex;
    flex-direction: column;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    animation: modalFadeIn 0.25s ease-out;
}

.modal-header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1.25rem;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(var(--primary-rgb), 0.35) transparent;
}

.modal-body::-webkit-scrollbar { width: 8px; }
.modal-body::-webkit-scrollbar-track { background: transparent; }
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
    flex-shrink: 0;
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
    from { opacity: 0; transform: scale(0.96) translateY(-8px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Signature cards */
.signature-card {
    transition: all 0.3s ease;
}

.signature-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.signature-display-container {
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.signature-image {
    max-height: 70px;
    max-width: 100%;
    object-fit: contain;
}

.typed-signature-display {
    font-family: 'Dancing Script', cursive;
    font-size: 2rem;
    color: var(--text-primary);
    text-align: center;
    line-height: 1.2;
    word-break: break-word;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.signature-card { animation: fadeInUp 0.5s ease forwards; }

/* Table hover — primary tint */
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

/* Badges */
.badge-success  { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning  { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger   { background-color: rgba(var(--danger-rgb),  0.1) !important; color: var(--danger)  !important; border: 1px solid rgba(var(--danger-rgb),  0.3) !important; }
.badge-info     { background-color: rgba(var(--info-rgb),    0.1) !important; color: var(--info)    !important; border: 1px solid rgba(var(--info-rgb),    0.3) !important; }
.badge-primary  { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary{ background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

/* Card */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

/* Responsive */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .typed-signature-display { font-size: 1.5rem; }
    .modal-container { margin: 1rem; max-width: calc(100% - 2rem); max-height: 85vh; }
    .modal-footer { flex-direction: column; }
    .modal-footer button { width: 100%; }
}

@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;500;600;700&display=swap');
</style>
@endsection