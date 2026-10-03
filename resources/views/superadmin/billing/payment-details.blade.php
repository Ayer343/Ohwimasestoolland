{{-- resources/views/superadmin/billing/payment-details.blade.php --}}
@extends('layouts.app')

@php
    $pageTitle = 'Payment Details';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float) $amount, 2);
            }
            return $currency . ' ' . number_format((float) $amount, 2);
        }
    }

    if (!function_exists('getPaymentStatusColor')) {
        function getPaymentStatusColor($status) {
            return match ($status) {
                'confirmed'            => 'success',
                'pending_confirmation' => 'warning',
                'cancelled'            => 'danger',
                'refunded'             => 'info',
                default                => 'secondary',
            };
        }
    }

    if (!function_exists('getPaymentStatusIcon')) {
        function getPaymentStatusIcon($status) {
            return match ($status) {
                'confirmed'            => 'fa-check-circle',
                'pending_confirmation' => 'fa-clock',
                'cancelled'            => 'fa-times-circle',
                'refunded'             => 'fa-undo',
                default                => 'fa-circle',
            };
        }
    }

    if (!function_exists('getPaymentMethodDisplay')) {
        function getPaymentMethodDisplay($method) {
            $methods = [
                'mtn'           => 'MTN Mobile Money',
                'telecel'       => 'Telecel (Vodafone) Cash',
                'airteltigo'    => 'AirtelTigo Money',
                'bank_transfer' => 'Bank Transfer',
                'paystack'      => 'Paystack',
                'expresspay'    => 'ExpressPay',
                'flutterwave'   => 'Flutterwave',
                'hubtel'        => 'Hubtel',
                'cash'          => 'Cash',
                'check'         => 'Check',
                'mobile_money'  => 'Mobile Money',
            ];
            return $methods[$method] ?? ucfirst(str_replace('_', ' ', (string) $method));
        }
    }

    if (!function_exists('getPaymentMethodIcon')) {
        function getPaymentMethodIcon($method) {
            return match ($method) {
                'bank_transfer'                        => 'fas fa-university',
                'mtn', 'telecel', 'airteltigo',
                'mobile_money'                         => 'fas fa-mobile-alt',
                'cash'                                 => 'fas fa-money-bill',
                'check'                                => 'fas fa-file-invoice',
                'paystack', 'expresspay',
                'flutterwave', 'hubtel'                => 'fas fa-credit-card',
                default                                => 'fas fa-money-bill-wave',
            };
        }
    }

    // ---------- Safe accessors ----------
    $agreement   = $payment->agreement ?? null;
    $recordedBy  = $payment->recordedByUser ?? null;
    $confirmedBy = $payment->confirmedByUser ?? null;

    // Parse provider_data safely
    $providerData = null;
    if (!empty($payment->provider_data)) {
        if (is_string($payment->provider_data)) {
            $decoded = json_decode($payment->provider_data, true);
            $providerData = is_array($decoded) ? $decoded : null;
        } elseif (is_array($payment->provider_data)) {
            $providerData = $payment->provider_data;
        }
    }

    $statusClass = getPaymentStatusColor($payment->status ?? 'pending_confirmation');
    $statusIcon  = getPaymentStatusIcon($payment->status ?? 'pending_confirmation');
    $statusText  = ucfirst(str_replace('_', ' ', (string) ($payment->status ?? 'unknown')));

    $currency = $agreement->currency ?? 'GHS';
    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-receipt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-receipt mr-2" style="color: var(--primary);"></i>
                        Payment Details
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag"></i>
                        <span class="font-mono">#{{ $payment->id }}</span>
                        @if($agreement && $agreement->agreement_number)
                            <span class="mx-1">•</span>
                            <i class="fas fa-file-contract"></i>
                            <span class="font-mono">{{ $agreement->agreement_number }}</span>
                        @endif
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar-alt"></i>
                        <span>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('F j, Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <div class="flex items-center space-x-2 flex-wrap gap-2">
                    <a href="{{ route('superadmin.billing.history') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Payment History
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

    {{-- Status Banner --}}
    <div class="card p-6" style="border-left: 4px solid var(--{{ $statusClass }});">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--{{ $statusClass }}-rgb), 0.1); color: var(--{{ $statusClass }});">
                        <i class="fas {{ $statusIcon }} text-lg"></i>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        Payment Status:
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-{{ $statusClass }}">
                            <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                            {{ $statusText }}
                        </span>
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        @if(($payment->status ?? '') === 'confirmed')
                            <i class="fas fa-info-circle mr-1"></i> This payment has been confirmed and applied to the agreement.
                        @elseif(($payment->status ?? '') === 'pending_confirmation')
                            <i class="fas fa-info-circle mr-1"></i> This payment is awaiting confirmation from the developer.
                        @elseif(($payment->status ?? '') === 'cancelled')
                            <i class="fas fa-info-circle mr-1"></i> This payment was cancelled and does not count toward your balance.
                        @elseif(($payment->status ?? '') === 'refunded')
                            <i class="fas fa-info-circle mr-1"></i> This payment was refunded.
                        @endif
                    </p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm" style="color: var(--text-secondary);">Amount Paid</div>
                <div class="text-2xl font-bold" style="color: var(--{{ $statusClass }});">
                    {{ formatCurrency($payment->amount_paid ?? 0, $currency) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Payment Information --}}
        <div class="card lg:col-span-2">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Payment Information
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Payment ID</p>
                        <p class="font-mono font-semibold" style="color: var(--text-primary);">#{{ $payment->id }}</p>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Amount Paid</p>
                        <p class="font-semibold" style="color: var(--text-primary);">
                            {{ formatCurrency($payment->amount_paid ?? 0, $currency) }}
                        </p>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Payment Date</p>
                        <p class="font-semibold" style="color: var(--text-primary);">
                            {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('F j, Y') : 'N/A' }}
                        </p>
                        @if($payment->payment_date)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($payment->payment_date)->diffForHumans() }}
                            </p>
                        @endif
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Payment Method</p>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                            <i class="{{ getPaymentMethodIcon($payment->payment_method ?? '') }} mr-1"></i>
                            {{ getPaymentMethodDisplay($payment->payment_method ?? '') }}
                        </span>
                    </div>

                    <div class="p-4 rounded-lg md:col-span-2" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Transaction Reference</p>
                        @if(!empty($payment->transaction_reference))
                            <div class="flex items-center gap-2">
                                <code class="font-mono text-sm" style="color: var(--text-primary);">{{ $payment->transaction_reference }}</code>
                                <button type="button"
                                        onclick="copyToClipboard('{{ $payment->transaction_reference }}')"
                                        class="text-xs px-2 py-0.5 rounded"
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        @else
                            <p class="text-sm" style="color: var(--text-secondary);">—</p>
                        @endif
                    </div>

                    @if(!empty($payment->notes))
                    <div class="p-4 rounded-lg md:col-span-2" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Notes</p>
                        <p class="text-sm" style="color: var(--text-primary);">{{ $payment->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Agreement Info --}}
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--info);"></i> Linked Agreement
                </h3>
            </div>
            <div class="p-6">
                @if($agreement)
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Agreement #:</span>
                            <span class="font-mono text-sm font-medium" style="color: var(--text-primary);">{{ $agreement->agreement_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Amount:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount ?? 0, $currency) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Received:</span>
                            <span class="text-sm font-medium" style="color: var(--success);">{{ formatCurrency($agreement->amount_received ?? 0, $currency) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Status:</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ ($agreement->status ?? '') === 'active' ? 'success' : (($agreement->status ?? '') === 'pending' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($agreement->status ?? 'unknown') }}
                            </span>
                        </div>
                        @if(!empty($agreement->description))
                        <div class="pt-3 mt-3" style="border-top: 1px solid var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Description</p>
                            <p class="text-sm" style="color: var(--text-primary);">{{ \Illuminate\Support\Str::limit($agreement->description, 120) }}</p>
                        </div>
                        @endif
                    </div>
                    <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-color);">
                        <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}"
                           class="w-full text-center px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-external-link-alt mr-2"></i> View Agreement
                        </a>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-unlink text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p class="text-sm" style="color: var(--text-secondary);">No agreement linked to this payment.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Attribution Row --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Recorded By --}}
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-edit mr-2" style="color: var(--primary);"></i> Recorded By
            </h3>
            @if($recordedBy)
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $recordedBy->name }}</p>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $recordedBy->email }}</p>
                    </div>
                </div>
                @if(!empty($payment->recorded_at))
                    <p class="text-xs mt-3" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        {{ \Carbon\Carbon::parse($payment->recorded_at)->format('F j, Y \a\t H:i A') }}
                    </p>
                @endif
            @else
                <p class="text-sm" style="color: var(--text-secondary);">Not recorded.</p>
                @if(!empty($payment->recorded_at))
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        {{ \Carbon\Carbon::parse($payment->recorded_at)->format('F j, Y \a\t H:i A') }}
                    </p>
                @endif
            @endif
        </div>

        {{-- Confirmed By --}}
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-check mr-2" style="color: var(--success);"></i> Confirmed By
            </h3>
            @if($confirmedBy)
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $confirmedBy->name }}</p>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $confirmedBy->email }}</p>
                    </div>
                </div>
                @if(!empty($payment->confirmed_at))
                    <p class="text-xs mt-3" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        {{ \Carbon\Carbon::parse($payment->confirmed_at)->format('F j, Y \a\t H:i A') }}
                    </p>
                @endif
            @else
                <div class="text-center py-2">
                    <i class="fas fa-hourglass-half text-2xl mb-2" style="color: var(--warning);"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">Awaiting confirmation.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Provider Data --}}
    @if($providerData && count($providerData) > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-server mr-2" style="color: var(--info);"></i> Provider Response
        </h3>
        <div class="overflow-x-auto">
            <table class="provider-table w-full text-sm">
                <tbody>
                    @foreach($providerData as $key => $value)
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <td class="py-2 pr-4 font-medium align-top" style="color: var(--text-secondary); width: 220px;">
                                {{ ucfirst(str_replace('_', ' ', $key)) }}
                            </td>
                            <td class="py-2" style="color: var(--text-primary);">
                                @if(is_array($value))
                                    <pre style="font-size: 0.75rem; white-space: pre-wrap; margin: 0;">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @elseif(is_bool($value))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-{{ $value ? 'success' : 'danger' }}">
                                        {{ $value ? 'true' : 'false' }}
                                    </span>
                                @elseif(is_null($value))
                                    <span style="color: var(--text-secondary);">—</span>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Timestamps --}}
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-clock mr-2" style="color: var(--secondary);"></i> Timeline
        </h3>
        <div class="space-y-3">
            @if(!empty($payment->created_at))
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-plus text-xs"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Payment created</p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($payment->created_at)->format('F j, Y \a\t H:i A') }}
                        ({{ \Carbon\Carbon::parse($payment->created_at)->diffForHumans() }})
                    </p>
                </div>
            </div>
            @endif

            @if(!empty($payment->recorded_at))
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-edit text-xs"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Recorded in system</p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($payment->recorded_at)->format('F j, Y \a\t H:i A') }}
                    </p>
                </div>
            </div>
            @endif

            @if(!empty($payment->confirmed_at))
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-check text-xs"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Payment confirmed</p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($payment->confirmed_at)->format('F j, Y \a\t H:i A') }}
                    </p>
                </div>
            </div>
            @endif

            @if(!empty($payment->updated_at) && $payment->updated_at != $payment->created_at)
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-sync text-xs"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Last updated</p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($payment->updated_at)->format('F j, Y \a\t H:i A') }}
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Actions --}}
    <div class="card p-6">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Payment records are read-only. Contact the developer to update or reverse a payment.
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('superadmin.billing.history') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to History
                </a>
                @if($agreement)
                <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-file-contract mr-2"></i> View Agreement
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text)
        .then(() => showToast('Copied to clipboard!', 'success'))
        .catch(err => showToast('Failed to copy: ' + err.message, 'error'));
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const colors = {
        success: { bg: 'bg-green-100',  text: 'text-green-800',  icon: 'fa-check-circle' },
        error:   { bg: 'bg-red-100',    text: 'text-red-800',    icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: 'fa-info-circle' },
    };
    const cfg = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${cfg.bg} ${cfg.text}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.innerHTML = `<i class="fas ${cfg.icon} mr-2"></i>${message}`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-70';
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
</script>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
}

.badge-success  { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning  { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger   { background-color: rgba(var(--danger-rgb),  0.1) !important; color: var(--danger)  !important; border: 1px solid rgba(var(--danger-rgb),  0.3) !important; }
.badge-info     { background-color: rgba(var(--info-rgb),    0.1) !important; color: var(--info)    !important; border: 1px solid rgba(var(--info-rgb),    0.3) !important; }
.badge-primary  { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary{ background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

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

code {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    background-color: rgba(var(--primary-rgb), 0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.875em;
}

.provider-table tbody tr:last-child {
    border-bottom: none !important;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
    .provider-table td { display: block; width: 100% !important; }
    .provider-table td:first-child { padding-bottom: 0; }
}
</style>
@endsection