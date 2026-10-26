{{-- resources/views/landlord/ads/pay.blade.php --}}
@extends('layouts.landlord')

@section('title', 'Complete Payment')

@php
    // ⭐ Defensive fallback — the controller SHOULD pass this, but if it
    //    ever forgets, we self-heal instead of 500-ing.
    if (!isset($systemSettings)) {
        $systemSettings = \App\Models\SystemSetting::getSettings();
    }

    // Collect enabled gateways from the settings row.
    $enabledProviders = [];

    if (method_exists($systemSettings, 'getPaymentMethodsForFrontend')) {
        $enabledProviders = $systemSettings->getPaymentMethodsForFrontend() ?? [];
    } else {
        $providerMeta = [
            'paystack'    => ['name' => 'Paystack',    'icon' => 'fa-credit-card',      'description' => 'Cards, bank transfers & mobile money', 'color' => '#3B82F6'],
            'flutterwave' => ['name' => 'Flutterwave', 'icon' => 'fa-cloud-upload-alt', 'description' => 'Pan-African payment gateway',          'color' => '#F97316'],
            'hubtel'      => ['name' => 'Hubtel',      'icon' => 'fa-phone-alt',        'description' => 'Mobile money collections',             'color' => '#2563EB'],
            'expresspay'  => ['name' => 'ExpressPay',  'icon' => 'fa-credit-card',      'description' => 'Mobile money & online payments',       'color' => '#0066CC'],
        ];

        foreach ($providerMeta as $key => $meta) {
            if (method_exists($systemSettings, 'isPaymentMethodEnabled') && $systemSettings->isPaymentMethodEnabled($key)) {
                $enabledProviders[$key] = $meta;
            }
        }
    }

    $hasProviders     = !empty($enabledProviders);
    $firstProviderKey = $hasProviders ? array_key_first($enabledProviders) : null;

    // Preserve the previously selected gateway across validation failures.
    $selectedGateway = old('gateway', $firstProviderKey);

    $currencySymbol = ($invoice->currency ?? $campaign->currency ?? 'GHS') === 'GHS'
        ? '₵'
        : (($invoice->currency ?? $campaign->currency ?? 'GHS') . ' ');

    $amount = (float) ($invoice->amount ?? 0);

    $unitLabel = $campaign->unit->unit_name
        ?? $campaign->unit->unit_number
        ?? 'Unit #' . $campaign->property_unit_id;

    // ⭐ Detect any error state so we can render a prominent banner
    $hasSessionError = session()->has('error');
    $hasValidationErrors = isset($errors) && $errors->any();
    $hasAnyError = $hasSessionError || $hasValidationErrors;
@endphp

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <div class="card p-6">
        <h1 class="text-xl font-bold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i>
            Complete Payment
        </h1>

        {{-- ============ ERROR BANNERS (CRITICAL — this is why the user was seeing nothing) ============ --}}
        @if($hasSessionError)
            <div class="p-4 rounded-lg mb-4"
                 style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-lg mr-3 mt-0.5" style="color: var(--danger);"></i>
                    <div class="flex-1">
                        <p class="font-semibold mb-1" style="color: var(--danger);">
                            Payment couldn't be initiated
                        </p>
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ session('error') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if(session()->has('info'))
            <div class="p-4 rounded-lg mb-4"
                 style="background: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-lg mr-3 mt-0.5" style="color: var(--info);"></i>
                    <div class="flex-1">
                        <p class="text-sm" style="color: var(--text-primary);">{{ session('info') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if($hasValidationErrors)
            <div class="p-4 rounded-lg mb-4"
                 style="background: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-lg mr-3 mt-0.5" style="color: var(--warning);"></i>
                    <div class="flex-1">
                        <p class="font-semibold mb-2" style="color: var(--warning);">
                            Please fix the following:
                        </p>
                        <ul class="list-disc list-inside text-sm space-y-1" style="color: var(--text-primary);">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============ DEBUG PANEL (only when APP_DEBUG=true) ============ --}}
        @if(config('app.debug') && $hasAnyError)
            <details class="mb-4 rounded-lg p-3 text-xs"
                     style="background: rgba(0,0,0,0.04); border: 1px dashed var(--border-color);">
                <summary class="cursor-pointer font-semibold" style="color: var(--text-secondary);">
                    <i class="fas fa-bug mr-1"></i> Debug info (visible because APP_DEBUG=true)
                </summary>
                <div class="mt-2 space-y-1 font-mono" style="color: var(--text-secondary);">
                    <div><strong>Route:</strong> {{ route('landlord.ads.pay.process', $campaign->uuid) }}</div>
                    <div><strong>Campaign:</strong> {{ $campaign->uuid }}</div>
                    <div><strong>Invoice:</strong> {{ $invoice->invoice_number }} (status: {{ $invoice->status }})</div>
                    <div><strong>Gateway ref on invoice:</strong> {{ $invoice->gateway_reference ?? '(none)' }}</div>
                    <div><strong>Enabled providers:</strong> {{ implode(', ', array_keys($enabledProviders)) ?: '(none)' }}</div>
                    <div><strong>Selected gateway:</strong> {{ $selectedGateway ?? '(none)' }}</div>
                    <div><strong>Session error:</strong> {{ session('error') ?? '(none)' }}</div>
                    <div><strong>Old gateway:</strong> {{ old('gateway') ?? '(none)' }}</div>
                </div>
            </details>
        @endif

        {{-- ============ ORDER SUMMARY ============ --}}
        <div class="mb-6 p-4 rounded-lg" style="background: var(--bg-secondary);">
            <div class="flex justify-between mb-2">
                <span style="color: var(--text-secondary);">Campaign</span>
                <span class="font-medium" style="color: var(--text-primary);">{{ $campaign->plan_name }}</span>
            </div>
            <div class="flex justify-between mb-2">
                <span style="color: var(--text-secondary);">Unit</span>
                <span class="font-medium" style="color: var(--text-primary);">{{ $unitLabel }}</span>
            </div>
            <div class="flex justify-between mb-2">
                <span style="color: var(--text-secondary);">Duration</span>
                <span class="font-medium" style="color: var(--text-primary);">
                    {{ $campaign->duration_months }} month(s)
                </span>
            </div>
            @if($campaign->ends_at ?? false)
                <div class="flex justify-between mb-2">
                    <span style="color: var(--text-secondary);">Campaign ends</span>
                    <span class="font-medium" style="color: var(--text-primary);">
                        {{ $campaign->ends_at->format('M d, Y') }}
                    </span>
                </div>
            @endif
            <div class="flex justify-between pt-3 border-t" style="border-color: var(--border-color);">
                <span class="font-semibold" style="color: var(--text-primary);">Total</span>
                <span class="text-xl font-bold" style="color: var(--primary);">
                    {{ $currencySymbol }}{{ number_format($amount, 2) }}
                </span>
            </div>
        </div>

        {{-- ============ NO PROVIDER CONFIGURED ============ --}}
        @if(!$hasProviders)
            <div class="p-4 rounded-lg mb-6"
                 style="background: rgba(var(--danger-rgb), 0.08); border-left: 4px solid var(--danger);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-xl mr-3 mt-0.5"
                       style="color: var(--danger);"></i>
                    <div>
                        <p class="font-semibold mb-1" style="color: var(--text-primary);">
                            No payment method is currently available
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            The administrator hasn't enabled any payment gateway yet. Your campaign has
                            been saved and will activate as soon as a payment method is configured.
                            Please contact support if this persists.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex justify-between gap-3">
                <a href="{{ route('landlord.ads.show', $campaign->uuid) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Campaign
                </a>
                <a href="{{ route('landlord.ads.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-list mr-2"></i> My Campaigns
                </a>
            </div>
        @else
            {{-- ============ PAYMENT FORM ============ --}}
            <form method="POST"
                  action="{{ route('landlord.ads.pay.process', $campaign->uuid) }}"
                  id="adPaymentForm"
                  novalidate>
                @csrf

                <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-wallet mr-1"></i>
                    Select Payment Method
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                    @foreach($enabledProviders as $key => $meta)
                        @php
                            $isSelected = ($selectedGateway === $key);
                            $name        = $meta['name']        ?? ucfirst($key);
                            $icon        = $meta['icon']        ?? 'fa-credit-card';
                            $description = $meta['description'] ?? '';
                            $color       = $meta['color']       ?? 'var(--primary)';
                        @endphp

                        <label class="flex items-start p-3 rounded-lg border cursor-pointer transition-all hover:shadow-md payment-method-label"
                               style="border-color: {{ $isSelected ? 'var(--primary)' : 'var(--border-color)' }};
                                      background: {{ $isSelected ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-primary)' }};"
                               data-gateway="{{ $key }}">
                            <input type="radio"
                                   name="gateway"
                                   value="{{ $key }}"
                                   @checked($isSelected)
                                   required
                                   class="mt-1 mr-3 flex-shrink-0 gateway-radio"
                                   style="accent-color: var(--primary);">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas {{ $icon }}" style="color: {{ $color }};"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">
                                        {{ $name }}
                                    </span>
                                </div>
                                @if($description)
                                    <p class="text-xs leading-relaxed" style="color: var(--text-secondary);">
                                        {{ $description }}
                                    </p>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>

                <button type="submit"
                        id="adPaymentSubmit"
                        class="w-full py-3 rounded-lg text-sm font-semibold shadow-lg hover:shadow-xl transition-all inline-flex items-center justify-center"
                        style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
                    <span id="adPaymentSubmitLabel">
                        <i class="fas fa-lock mr-2"></i>
                        Pay {{ $currencySymbol }}{{ number_format($amount, 2) }} Securely
                    </span>
                    <span id="adPaymentSubmitSpinner" class="hidden items-center">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Redirecting to gateway…
                    </span>
                </button>

                <p class="text-xs text-center mt-3" style="color: var(--text-secondary);">
                    <i class="fas fa-shield-alt mr-1"></i>
                    You'll be redirected to the payment provider's secure checkout.
                </p>

                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <a href="{{ route('landlord.ads.show', $campaign->uuid) }}"
                       class="text-sm inline-flex items-center"
                       style="color: var(--text-secondary);">
                        <i class="fas fa-arrow-left mr-1"></i> Cancel and go back
                    </a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form      = document.getElementById('adPaymentForm');
    const submitBtn = document.getElementById('adPaymentSubmit');
    const label     = document.getElementById('adPaymentSubmitLabel');
    const spinner   = document.getElementById('adPaymentSubmitSpinner');
    const radios    = document.querySelectorAll('.gateway-radio');
    const labels    = document.querySelectorAll('.payment-method-label');

    if (!form) return;

    // Highlight the selected provider card
    function refreshSelection() {
        labels.forEach(label => {
            const radio = label.querySelector('.gateway-radio');
            const isSelected = radio && radio.checked;
            label.style.borderColor = isSelected ? 'var(--primary)' : 'var(--border-color)';
            label.style.background  = isSelected ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-primary)';
        });
    }

    radios.forEach(radio => radio.addEventListener('change', refreshSelection));
    refreshSelection();

    // On submit: validate, then lock the button and show a spinner
    form.addEventListener('submit', function (e) {
        const selected = form.querySelector('input[name="gateway"]:checked');

        if (!selected) {
            e.preventDefault();
            alert('Please select a payment method.');
            return;
        }

        // Disable the button immediately to prevent double-clicks.
        // If the server responds with a redirect to the gateway, the
        // browser navigates away and this doesn't matter.
        // If the server responds with an error, we re-enable on load.
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.75';
            submitBtn.style.cursor = 'wait';
        }
        if (label)   label.classList.add('hidden');
        if (spinner) {
            spinner.classList.remove('hidden');
            spinner.classList.add('inline-flex');
        }
    });

    // Browser "back" navigation restores the button state
    window.addEventListener('pageshow', function () {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '';
            submitBtn.style.cursor = '';
        }
        if (label)   label.classList.remove('hidden');
        if (spinner) {
            spinner.classList.add('hidden');
            spinner.classList.remove('inline-flex');
        }
    });
});
</script>
@endsection