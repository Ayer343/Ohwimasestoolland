{{--
|--------------------------------------------------------------------------
| Developer Payment Provider View
|--------------------------------------------------------------------------
|
| - Layout is always `layouts.dev` (guarded by middleware anyway).
| - Provider tabs / forms / cards / webhooks are all driven by `$providers`.
| - All mutating actions are gated by @can('manage-developer-payments').
| - Client-side JS uses the same route names as the controller methods.
|
--}}

@php
    use App\Support\PaymentProviderRegistry;

    $user         = auth()->user();
    $isDeveloper  = $user && ($user->hasRole('developer') || $user->type == 5);
    $canBill      = (bool) ($environment['can_bill'] ?? env('DEVELOPER_PAYMENT_CAN_BILL', true));
    $accessEnabled = (bool) ($environment['access_enabled'] ?? env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true));
    $canManage    = $user && $user->can('manage-developer-payments');
    $canWrite     = $canManage && $accessEnabled;

    // Fall back to the first provider if $activeProvider isn't set.
    $activeProvider = $activeProvider ?? (array_key_first($providers) ?: 'paystack');

    // Derived maps used repeatedly below.
    $configuredProviders = [];
    $enabledProviders    = [];
    foreach ($providers as $key => $p) {
        $configuredProviders[$key] = (bool) ($p['developer_config']['is_configured'] ?? false);
        $enabledProviders[$key]    = (bool) ($p['developer_config']['enabled'] ?? false);
    }
@endphp

@extends('layouts.dev')

@section('title', 'Developer Payment Configuration')

@section('content')
<div class="space-y-6 animate-fadeInUp">

    {{-- ============================================================== --}}
    {{-- HEADER --}}
    {{-- ============================================================== --}}
    <div class="card overflow-hidden">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    💳 Developer Payment Configuration
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure your own payment credentials to bill admins for app usage
                    <span class="developer-badge ml-2">DEVELOPER MODE</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-4 lg:mt-0">
                <button id="refreshStatus"
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                </button>

                @if($canWrite)
                    <button id="clearDeveloperCache"
                            class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-broom mr-2"></i> Clear Cache
                    </button>
                @endif

                @if($canBill && $canWrite)
                    <a href="#billingSection"
                       class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-receipt mr-2"></i> Bill Admin
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- DEVELOPER BANNER --}}
    {{-- ============================================================== --}}
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm"
         style="border-color: rgba(var(--info-rgb), 0.3); background: linear-gradient(135deg, rgba(var(--info-rgb), 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-12 w-12 items-center justify-center rounded-full"
                     style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.2), rgba(118, 75, 162, 0.2));">
                    <i class="fas fa-code text-xl" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--info);">
                    🔧 Developer Configuration Mode
                </h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    Configure your <strong>OWN payment credentials</strong> to bill admins for app usage.
                    <span class="block mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span class="px-2 py-0.5 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            ✅ Your Credentials
                        </span>
                        — separate from system-wide payment credentials
                    </span>
                </p>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- FLASH MESSAGES --}}
    {{-- ============================================================== --}}
    @foreach(['success' => ['✅ Success!', 'fa-check-circle', 'success'],
              'error'   => ['❌ Error!',   'fa-exclamation-circle', 'danger'],
              'warning' => ['⚠️ Warning!', 'fa-exclamation-triangle', 'warning']] as $key => [$title, $icon, $colorVar])
        @if(session($key))
            <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm js-auto-dismiss"
                 style="border-color: rgba(var(--{{ $colorVar }}-rgb), 0.2); background-color: rgba(var(--{{ $colorVar }}-rgb), 0.05);">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full"
                             style="background-color: rgba(var(--{{ $colorVar }}-rgb), 0.1);">
                            <i class="fas {{ $icon }} text-lg" style="color: var(--{{ $colorVar }});"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h4 class="font-semibold" style="color: var(--{{ $colorVar }});">{{ $title }}</h4>
                        <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session($key) }}</p>
                    </div>
                    <button type="button" class="ml-4 text-gray-400 hover:text-gray-600 js-dismiss">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        @endif
    @endforeach

    {{-- ============================================================== --}}
    {{-- ACCESS DISABLED NOTICE --}}
    {{-- ============================================================== --}}
    @unless($accessEnabled)
        <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm"
             style="border-color: rgba(var(--warning-rgb), 0.3); background-color: rgba(var(--warning-rgb), 0.05);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-lock text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h4 class="font-semibold" style="color: var(--warning);">🔒 Read-Only Mode</h4>
                    <p class="mt-1 text-sm" style="color: var(--text-primary);">
                        Developer payment configuration has been disabled by the super admin.
                        You can view your current settings but cannot make changes.
                    </p>
                </div>
            </div>
        </div>
    @endunless

    {{-- ============================================================== --}}
    {{-- ENVIRONMENT STATUS BANNER (JS-driven) --}}
    {{-- ============================================================== --}}
    <div id="environmentStatus" class="hidden relative overflow-hidden rounded-xl border p-4 shadow-sm">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div id="environmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full">
                    <i class="fas fa-server text-lg"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 id="environmentStatusTitle" class="font-semibold"></h4>
                <p id="environmentStatusText" class="mt-1 text-sm" style="color: var(--text-primary);"></p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600"
                    onclick="this.parentElement.parentElement.classList.add('hidden')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- CREDENTIALS FORM CARD --}}
    {{-- ============================================================== --}}
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--info-rgb), 0.05));">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--info);"></i>
                        Your Payment Credentials
                    </h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                        Select a provider and enter your API credentials
                    </p>
                </div>
                <div class="flex items-center space-x-2 mt-2 lg:mt-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                          style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        <span class="mr-1.5 h-2 w-2 rounded-full" style="background-color: var(--info);"></span>
                        Your Credentials
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- Provider tabs --}}
            <div class="flex flex-wrap gap-2 mb-6 border-b pb-4" style="border-color: var(--border-color);"
                 id="providerTabs" role="tablist">
                @foreach($providers as $key => $p)
                    @php
                        $isActive     = $key === $activeProvider;
                        $isConfigured = $configuredProviders[$key] ?? false;
                        $isEnabled    = $enabledProviders[$key] ?? false;
                        $statusIcon   = ($isConfigured && $isEnabled) ? '✅'
                                      : ($isConfigured ? '⏸️' : '🔴');
                        $statusColor  = ($isConfigured && $isEnabled) ? 'var(--success)'
                                      : ($isConfigured ? 'var(--warning)' : 'var(--text-secondary)');
                    @endphp
                    <button type="button"
                            class="provider-tab px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $isActive ? 'active' : '' }}"
                            data-provider="{{ $key }}"
                            id="{{ $key }}-tab"
                            role="tab"
                            aria-selected="{{ $isActive ? 'true' : 'false' }}"
                            style="{{ $isActive
                                ? 'background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);'
                                : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                        <i class="fas {{ $p['icon'] }} mr-2"></i>
                        {{ $p['name'] }}
                        <span class="ml-1 text-xs" style="color: {{ $statusColor }};">{{ $statusIcon }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Provider forms --}}
            @foreach($providers as $key => $p)
                @php $isActive = $key === $activeProvider; @endphp
                <div class="provider-form-container {{ $isActive ? '' : 'hidden' }}"
                     id="providerForm-{{ $key }}"
                     role="tabpanel">

                    <form method="POST"
                          action="{{ route('developer.payment-providers.configure') }}"
                          data-provider="{{ $key }}"
                          class="provider-form">
                        @csrf
                        <input type="hidden" name="provider" value="{{ $key }}">

                        <div class="space-y-6">
                            {{-- Dynamic credential fields from $p['fields'] --}}
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                @foreach($p['fields'] as $fieldName => $fieldLabel)
                                    @php
                                        // Map field → env var
                                        $envKey = 'DEVELOPER_' . strtoupper($key) . '_' . strtoupper($fieldName);
                                        $value  = old($fieldName, env($envKey, ''));

                                        // Secret fields render as password inputs with an eye toggle.
                                        $isSecret = in_array($fieldName, [
                                            'secret_key', 'api_key', 'client_secret',
                                            'encryption_key',
                                        ], true);

                                        // ✅ FIXED: inline placeholder map replaces
                                        // the previous $this->fieldPlaceholder() call.
                                        // Blade has no $this in scope, so calling the
                                        // controller method directly would 500.
                                        $placeholders = [
                                            'paystack' => [
                                                'secret_key' => 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                                'public_key' => 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                            ],
                                            'expresspay' => [
                                                'merchant_id' => 'Enter your Merchant ID',
                                                'api_key'     => 'Enter your API Key',
                                                'environment' => 'sandbox',
                                            ],
                                            'flutterwave' => [
                                                'public_key'     => 'FLWPUBK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                                'secret_key'     => 'FLWSECK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                                'encryption_key' => 'Optional encryption key',
                                            ],
                                            'hubtel' => [
                                                'client_id'        => 'Enter your Client ID',
                                                'client_secret'    => 'Enter your Client Secret',
                                                'merchant_account' => 'Optional merchant account',
                                            ],
                                        ];

                                        $placeholder = $placeholders[$key][$fieldName]
                                            ?? ('Enter ' . $fieldLabel);
                                    @endphp

                                    <div>
                                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $fieldLabel }} <span style="color: #dc3545;">*</span>
                                        </label>

                                        <div class="{{ $isSecret ? 'relative' : '' }}">
                                            <input type="{{ $isSecret ? 'password' : 'text' }}"
                                                   name="{{ $fieldName }}"
                                                   value="{{ $value }}"
                                                   class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="{{ $placeholder }}"
                                                   @if($canWrite) @else disabled @endif
                                                   required>

                                            @if($isSecret)
                                                <button type="button"
                                                        onclick="toggleVisibility(this)"
                                                        class="absolute right-3 top-3 hover:opacity-80"
                                                        style="color: var(--text-secondary);">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Enabled toggle --}}
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Billing Status
                                </label>
                                <div class="space-y-3">
                                    <label class="flex cursor-pointer items-center">
                                        <div class="relative">
                                            <input type="checkbox"
                                                   name="enabled"
                                                   value="1"
                                                   class="sr-only provider-toggle"
                                                   data-provider="{{ $key }}"
                                                   @checked($enabledProviders[$key] ?? false)
                                                   onchange="onProviderEnabledChange(this)"
                                                   @if(! $canWrite) disabled @endif>
                                            <div class="toggle-bg block h-8 w-14 rounded-full transition-colors"></div>
                                            <div class="toggle-dot absolute left-1 top-1 h-6 w-6 rounded-full transition-all transform"></div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="font-medium toggle-label" style="color: var(--text-primary);">
                                                {{ ($enabledProviders[$key] ?? false) ? 'Enabled' : 'Disabled' }}
                                            </div>
                                            <div class="text-xs toggle-text" style="color: var(--text-secondary);">
                                                {{ ($enabledProviders[$key] ?? false) ? 'You can bill admins' : 'Billing is disabled' }}
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4"
                                 style="border-color: var(--border-color);">
                                <div class="flex space-x-2">
                                    @if($canWrite)
                                        <button type="button"
                                                class="test-dev-btn text-sm font-medium hover:opacity-80 flex items-center px-3 py-1.5 rounded"
                                                data-provider="{{ $key }}"
                                                onclick="testDeveloperConnection('{{ $key }}', this)"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-vial mr-1"></i>
                                            Test Credentials
                                        </button>
                                        <button type="button"
                                                class="text-sm font-medium hover:opacity-80 flex items-center px-3 py-1.5 rounded"
                                                onclick="clearProviderForm('{{ $key }}')"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-undo mr-1"></i>
                                            Clear
                                        </button>
                                    @endif
                                </div>

                                @if($canWrite)
                                    <button type="submit"
                                            class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                                            style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                                        <i class="fas fa-save mr-2"></i>
                                        Save {{ $p['name'] }} Credentials
                                    </button>
                                @else
                                    <button type="button" disabled
                                            class="rounded-lg px-6 py-2.5 text-sm font-medium cursor-not-allowed opacity-60"
                                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                                        <i class="fas fa-lock mr-2"></i> Locked
                                    </button>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- PROVIDER STATUS CARDS --}}
    {{-- ============================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="providerStatusCards">
        @foreach($providers as $key => $p)
            @php
                $isConfigured = $configuredProviders[$key] ?? false;
                $isEnabled    = $enabledProviders[$key] ?? false;
                $systemEnabled = (bool) ($p['status']['enabled'] ?? false);

                $badgeBg    = ($isConfigured && $isEnabled) ? 'rgba(var(--success-rgb), 0.2)'
                            : ($isConfigured ? 'rgba(var(--warning-rgb), 0.2)' : 'rgba(var(--danger-rgb), 0.2)');
                $badgeColor = ($isConfigured && $isEnabled) ? 'var(--success)'
                            : ($isConfigured ? 'var(--warning)' : 'var(--danger)');
                $badgeText  = ($isConfigured && $isEnabled) ? '✅ Active'
                            : ($isConfigured ? '⏸️ Disabled' : '❌ Not Configured');
            @endphp

            <div class="card overflow-hidden" id="provider-{{ $key }}" data-provider="{{ $key }}">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full"
                                 style="background-color: {{ $p['color'] }}1A;">
                                <i class="fas {{ $p['icon'] }} text-xl" style="color: {{ $p['color'] }};"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $p['name'] }}
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="status-badge px-2 py-0.5 text-xs rounded"
                                          style="background-color: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                        {{ $badgeText }}
                                    </span>
                                    <span class="px-2 py-0.5 text-xs rounded"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-user-cog mr-1"></i> Your Config
                                    </span>
                                </div>
                            </div>
                        </div>
                        @if($canWrite)
                            <div class="flex gap-2">
                                <button class="test-dev-connection px-3 py-1.5 text-xs rounded transition-colors hover:opacity-80"
                                        data-provider="{{ $key }}"
                                        style="background-color: {{ $p['color'] }}1A; color: {{ $p['color'] }}; border: 1px solid {{ $p['color'] }}4D;">
                                    <i class="fas fa-vial mr-1"></i> Test Yours
                                </button>
                                <button class="test-system-connection px-3 py-1.5 text-xs rounded transition-colors hover:opacity-80"
                                        data-provider="{{ $key }}"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    <i class="fas fa-vial mr-1"></i> Test System
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                            <p class="text-xs" style="color: var(--text-secondary);">System Status</p>
                            <p class="text-sm font-medium mt-0.5"
                               style="color: {{ $systemEnabled ? 'var(--success)' : 'var(--text-secondary)' }};">
                                {{ $systemEnabled ? '✅ Enabled' : '❌ Disabled' }}
                            </p>
                        </div>
                        <div class="p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                            <p class="text-xs" style="color: var(--text-secondary);">Your Status</p>
                            <p class="text-sm font-medium mt-0.5" style="color: {{ $badgeColor }};">
                                {{ $badgeText }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============================================================== --}}
    {{-- BILLING SECTION --}}
    {{-- ============================================================== --}}
    <div id="billingSection" class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--success-rgb), 0.05));">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-receipt mr-2" style="color: var(--success);"></i>
                Bill Admin for App Usage
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Use your configured credentials to bill the admin for app usage
            </p>
        </div>
        <div class="p-6">
            @if(! $canBill)
                <div class="p-4 rounded-lg text-center"
                     style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-ban text-2xl" style="color: var(--danger);"></i>
                    <p class="mt-2 font-medium" style="color: var(--text-primary);">Billing is disabled by the system administrator</p>
                </div>
            @elseif(! array_filter($configuredProviders))
                <div class="p-4 rounded-lg text-center"
                     style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                    <p class="mt-2 font-medium" style="color: var(--text-primary);">Please configure at least one provider first</p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Enter your API credentials above to enable billing</p>
                </div>
            @else
                <form id="billingForm" method="POST" action="{{ route('developer.payment-providers.bill-admin') }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Amount (GHS) <span style="color: #dc3545;">*</span>
                            </label>
                            <input type="number"
                                   name="amount"
                                   id="billingAmount"
                                   class="w-full rounded-lg border px-4 py-2.5 focus:ring-2 focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="100.00"
                                   step="0.01"
                                   min="1"
                                   max="100000"
                                   @if(! $canWrite) disabled @endif
                                   required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Admin Email <span style="color: #dc3545;">*</span>
                            </label>
                            <input type="email"
                                   name="email"
                                   id="billingEmail"
                                   class="w-full rounded-lg border px-4 py-2.5 focus:ring-2 focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="admin@example.com"
                                   @if(! $canWrite) disabled @endif
                                   required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Description <span style="color: #dc3545;">*</span>
                            </label>
                            <input type="text"
                                   name="description"
                                   id="billingDescription"
                                   class="w-full rounded-lg border px-4 py-2.5 focus:ring-2 focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="App usage fee - {{ date('F Y') }}"
                                   maxlength="255"
                                   @if(! $canWrite) disabled @endif
                                   required>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        @if($canWrite)
                            <button type="submit"
                                    id="billingSubmitBtn"
                                    class="rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                                    style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Send Invoice
                            </button>
                        @else
                            <button type="button" disabled
                                    class="rounded-lg px-6 py-2.5 text-sm font-medium cursor-not-allowed opacity-60"
                                    style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                                <i class="fas fa-lock mr-2"></i> Locked
                            </button>
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- WEBHOOK URLS --}}
    {{-- ============================================================== --}}
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-link mr-2" style="color: var(--info);"></i> Webhook URLs
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">Copy these URLs for your provider dashboards</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($providers as $key => $p)
                    <div class="flex items-center justify-between p-3 rounded-lg border"
                         style="border-color: var(--border-color);">
                        <div class="min-w-0 flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $p['name'] }}</span>
                            <code id="webhookUrl-{{ $key }}"
                                  class="block text-xs mt-1 break-all"
                                  style="color: var(--text-secondary);">{{ url("/api/payments/{$key}/webhook") }}</code>
                        </div>
                        <button type="button"
                                onclick="copyWebhookUrl('{{ $key }}')"
                                class="ml-3 px-3 py-1 text-xs rounded hover:opacity-80 whitespace-nowrap"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    // ---------------------------------------------------
    // Runtime config
    // ---------------------------------------------------
    const ROUTES = {
        configure:        @json(route('developer.payment-providers.configure')),
        testDeveloper:    @json(route('developer.payment-providers.test-developer-connection')),
        testConnection:   @json(route('developer.payment-providers.test-connection')),
        status:           @json(route('developer.payment-providers.status')),
        immediateStatus:  @json(route('developer.payment-providers.immediate-status')),
        billAdmin:        @json(route('developer.payment-providers.bill-admin')),
        clearCache:       @json(route('developer.payment-providers.clear-cache')),
        verifyEnv:        @json(route('developer.payment-providers.verify-environment')),
    };

    const PROVIDERS         = @json($providers);
    const CAN_WRITE         = @json($canWrite);
    const CAN_BILL          = @json($canBill);
    const ACTIVE_PROVIDER   = @json($activeProvider);
    const CSRF              = @json(csrf_token());

    let isSubmitting = false;

    // ---------------------------------------------------
    // fetchJSON
    // ---------------------------------------------------
    async function fetchJSON(url, options = {}) {
        options.credentials = 'same-origin';
        options.headers = Object.assign(
            {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            options.headers || {}
        );

        const response = await fetch(url, options);
        let text = await response.text();

        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.substring(1);
        }
        text = text.trim();

        let data = null;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            console.error('JSON parse error. Preview:', text.substring(0, 200));
            throw new Error('Invalid JSON response');
        }

        if (!response.ok) {
            const err = new Error(data?.message || `HTTP ${response.status}`);
            err.status = response.status;
            err.data = data;
            throw err;
        }

        return data;
    }

    // ---------------------------------------------------
    // Toast
    // ---------------------------------------------------
    function showToast(message, type = 'info') {
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());

        const toast = document.createElement('div');
        toast.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full';

        const styles = {
            success: { icon: 'fa-check-circle',       bg: 'var(--success)' },
            error:   { icon: 'fa-exclamation-circle', bg: 'var(--danger)'  },
            warning: { icon: 'fa-exclamation-triangle', bg: 'var(--warning)' },
            info:    { icon: 'fa-info-circle',        bg: 'var(--info)'    },
        };
        const s = styles[type] || styles.info;

        toast.style.backgroundColor = s.bg;
        toast.innerHTML = `<div class="flex items-center"><i class="fas ${s.icon} mr-2"></i><span></span></div>`;
        toast.querySelector('span').textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        });

        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Expose to inline onclick handlers
    window.showToast = showToast;

    // ---------------------------------------------------
    // Provider name helper
    // ---------------------------------------------------
    function providerName(key) {
        return PROVIDERS[key]?.name || key;
    }

    // ---------------------------------------------------
    // Tab switching
    // ---------------------------------------------------
    const tabs  = document.querySelectorAll('.provider-tab');
    const forms = document.querySelectorAll('.provider-form-container');

    function switchTab(target) {
        tabs.forEach(t => {
            t.style.backgroundColor = 'var(--bg-secondary)';
            t.style.color           = 'var(--text-primary)';
            t.style.borderColor     = 'var(--border-color)';
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });

        const activeTab = document.querySelector(`.provider-tab[data-provider="${target}"]`);
        if (activeTab) {
            activeTab.style.backgroundColor = 'rgba(var(--info-rgb), 0.1)';
            activeTab.style.color           = 'var(--info)';
            activeTab.style.borderColor     = 'rgba(var(--info-rgb), 0.3)';
            activeTab.classList.add('active');
            activeTab.setAttribute('aria-selected', 'true');
        }

        forms.forEach(form => {
            form.classList.add('hidden');
            if (form.id === 'providerForm-' + target) {
                form.classList.remove('hidden');
            }
        });

        window.history.replaceState(null, '', '#' + target);
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.provider));
    });

    // ---------------------------------------------------
    // Toggle visibility
    // ---------------------------------------------------
    window.toggleVisibility = function (button) {
        const input = button.closest('.relative')?.querySelector('input');
        if (!input) return;
        const icon = button.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    };

    // ---------------------------------------------------
    // Toggle enabled state
    // ---------------------------------------------------
    window.onProviderEnabledChange = function (toggle) {
        const wrapper = toggle.closest('.relative');
        const toggleDot = wrapper.querySelector('.toggle-dot');
        const toggleBg  = wrapper.querySelector('.toggle-bg');
        const container = toggle.closest('.space-y-3');
        const label     = container.querySelector('.toggle-label');
        const text      = container.querySelector('.toggle-text');

        if (toggle.checked) {
            toggleDot.style.transform = 'translateX(1.5rem)';
            toggleBg.style.backgroundColor = '#28a745';
            label.textContent = 'Enabled';
            text.textContent  = 'You can bill admins';
        } else {
            toggleDot.style.transform = 'translateX(0)';
            toggleBg.style.backgroundColor = 'var(--border-color)';
            label.textContent = 'Disabled';
            text.textContent  = 'Billing is disabled';
        }
    };

    // ---------------------------------------------------
    // Test developer credentials
    // ---------------------------------------------------
    window.testDeveloperConnection = async function (provider, btn) {
        if (!CAN_WRITE) {
            showToast('You do not have permission to test credentials.', 'warning');
            return;
        }

        const button = btn || document.querySelector(`.test-dev-btn[data-provider="${provider}"]`)
                            || document.querySelector(`.test-dev-connection[data-provider="${provider}"]`);
        if (!button) return;

        const original = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        button.disabled = true;

        try {
            const data = await fetchJSON(ROUTES.testDeveloper, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider }),
            });
            showToast(data.message, data.success ? 'success' : 'error');
        } catch (err) {
            showToast(`Test failed: ${err.message}`, 'error');
        } finally {
            button.innerHTML = original;
            button.disabled = false;
        }
    };

    // ---------------------------------------------------
    // Test system credentials
    // ---------------------------------------------------
    window.testSystemConnection = async function (provider, btn) {
        if (!CAN_WRITE) {
            showToast('You do not have permission to test connections.', 'warning');
            return;
        }

        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        btn.disabled = true;

        try {
            const data = await fetchJSON(ROUTES.testConnection, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider, use_developer: false }),
            });
            showToast(`System: ${data.message}`, data.success ? 'success' : 'error');
        } catch (err) {
            showToast(`System test failed: ${err.message}`, 'error');
        } finally {
            btn.innerHTML = original;
            btn.disabled = false;
        }
    };

    // ---------------------------------------------------
    // Clear provider form
    // ---------------------------------------------------
    window.clearProviderForm = function (provider) {
        if (!confirm(`Clear all form fields for ${providerName(provider)}?`)) return;

        const form = document.querySelector(`#providerForm-${provider} form`);
        if (!form) return;

        form.querySelectorAll('input[type="text"], input[type="password"], input[type="number"]')
            .forEach(input => input.value = '');

        const toggle = form.querySelector('.provider-toggle');
        if (toggle) {
            toggle.checked = false;
            onProviderEnabledChange(toggle);
        }

        showToast(`Form cleared for ${providerName(provider)}`, 'info');
    };

    // ---------------------------------------------------
    // Copy webhook URL
    // ---------------------------------------------------
    window.copyWebhookUrl = function (provider) {
        const el = document.getElementById('webhookUrl-' + provider);
        if (!el) return;

        const url = el.textContent.trim();
        const done = () => showToast(`${providerName(provider)} webhook URL copied`, 'success');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done).catch(() => fallbackCopy(url, done));
        } else {
            fallbackCopy(url, done);
        }
    };

    function fallbackCopy(text, done) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        done();
    }

    // ---------------------------------------------------
    // Credentials form submit
    // ---------------------------------------------------
    document.querySelectorAll('.provider-form').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!CAN_WRITE) {
                showToast('You do not have permission to modify credentials.', 'warning');
                return;
            }
            if (isSubmitting) return;

            const submitBtn = form.querySelector('button[type="submit"]');
            const original  = submitBtn?.innerHTML || '';
            isSubmitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            }

            try {
                const data = await fetchJSON(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (data.success) {
                    showToast(data.message, 'success');
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 1200);
                    } else {
                        setTimeout(() => window.location.reload(), 1200);
                    }
                } else if (data.errors) {
                    const messages = Object.values(data.errors).flat().join(', ');
                    showToast(`Validation failed: ${messages}`, 'error');
                } else {
                    showToast(data.message || 'Save failed', 'error');
                }
            } catch (err) {
                const serverErrors = err?.data?.errors
                    ? Object.values(err.data.errors).flat().join(', ')
                    : null;
                showToast(serverErrors
                    ? `Validation failed: ${serverErrors}`
                    : `Error: ${err.message}`, 'error');
            } finally {
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.innerHTML = original;
                    submitBtn.disabled = false;
                }
            }
        });
    });

    // ---------------------------------------------------
    // Billing form submit
    // ---------------------------------------------------
    const billingForm = document.getElementById('billingForm');
    if (billingForm) {
        billingForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!CAN_WRITE || !CAN_BILL) {
                showToast('Billing is not available.', 'warning');
                return;
            }

            const submitBtn = document.getElementById('billingSubmitBtn');
            const original  = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

            try {
                const data = await fetchJSON(billingForm.action, {
                    method: 'POST',
                    body: new FormData(billingForm),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (data.success) {
                    showToast(data.message, 'success');
                    if (data.redirect_url) {
                        window.open(data.redirect_url, '_blank', 'noopener');
                    }
                    billingForm.reset();
                } else if (data.errors) {
                    const messages = Object.values(data.errors).flat().join(', ');
                    showToast(`Validation failed: ${messages}`, 'error');
                } else {
                    showToast(data.message || 'Billing failed', 'error');
                }
            } catch (err) {
                const serverErrors = err?.data?.errors
                    ? Object.values(err.data.errors).flat().join(', ')
                    : null;
                showToast(serverErrors
                    ? `Validation failed: ${serverErrors}`
                    : `Error: ${err.message}`, 'error');
            } finally {
                submitBtn.innerHTML = original;
                submitBtn.disabled = false;
            }
        });
    }

    // ---------------------------------------------------
    // Test buttons (delegated)
    // ---------------------------------------------------
    document.querySelectorAll('.test-system-connection').forEach(btn => {
        btn.addEventListener('click', () => testSystemConnection(btn.dataset.provider, btn));
    });
    document.querySelectorAll('.test-dev-connection').forEach(btn => {
        btn.addEventListener('click', () => testDeveloperConnection(btn.dataset.provider, btn));
    });

    // ---------------------------------------------------
    // Refresh status button
    // ---------------------------------------------------
    const refreshBtn = document.getElementById('refreshStatus');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            const original = refreshBtn.innerHTML;
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';

            try {
                const data = await fetchJSON(ROUTES.status, { method: 'GET' });
                if (data.success) {
                    showToast('Status refreshed successfully', 'success');
                    Object.entries(data.data || {}).forEach(([key, status]) => {
                        updateStatusCard(key, status);
                    });
                } else {
                    showToast(data.message || 'Refresh failed', 'error');
                }
            } catch (err) {
                showToast(`Failed to refresh: ${err.message}`, 'error');
            } finally {
                refreshBtn.innerHTML = original;
                refreshBtn.disabled = false;
            }
        });
    }

    // ---------------------------------------------------
    // Clear cache button
    // ---------------------------------------------------
    const clearBtn = document.getElementById('clearDeveloperCache');
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            if (!confirm('Clear all cached provider states?')) return;

            const original = clearBtn.innerHTML;
            clearBtn.disabled = true;
            clearBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Clearing...';

            try {
                const data = await fetchJSON(ROUTES.clearCache, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ scope: 'provider-states' }),
                });
                showToast(data.message || 'Cache cleared', data.success ? 'success' : 'error');
            } catch (err) {
                showToast(`Failed: ${err.message}`, 'error');
            } finally {
                clearBtn.innerHTML = original;
                clearBtn.disabled = false;
            }
        });
    }

    // ---------------------------------------------------
    // Status card update
    // ---------------------------------------------------
    function updateStatusCard(provider, status) {
        const card = document.querySelector(`#provider-${provider}`);
        if (!card) return;

        const badge = card.querySelector('.status-badge');
        if (!badge) return;

        const isConfigured = !!status.developer_configured;
        const isEnabled    = !!status.developer_config?.enabled;

        if (isConfigured && isEnabled) {
            badge.textContent = '✅ Active';
            badge.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
            badge.style.color = 'var(--success)';
        } else if (isConfigured) {
            badge.textContent = '⏸️ Disabled';
            badge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.2)';
            badge.style.color = 'var(--warning)';
        } else {
            badge.textContent = '❌ Not Configured';
            badge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
            badge.style.color = 'var(--danger)';
        }
    }

    // ---------------------------------------------------
    // Dismissible flash messages
    // ---------------------------------------------------
    document.querySelectorAll('.js-dismiss').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.js-auto-dismiss')?.remove());
    });
    document.querySelectorAll('.js-auto-dismiss').forEach(el => {
        setTimeout(() => el.remove(), 5000);
    });

    // ---------------------------------------------------
    // Bootstrap
    // ---------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        // If URL has a #provider hash, prefer that tab over server-side default.
        const hash = window.location.hash.replace('#', '');
        if (hash && PROVIDERS[hash]) {
            switchTab(hash);
        }

        // Initialise toggle visual states.
        document.querySelectorAll('.provider-toggle').forEach(toggle => {
            onProviderEnabledChange(toggle);
        });
    });

    // If DOMContentLoaded already fired, initialise immediately.
    if (document.readyState !== 'loading') {
        document.querySelectorAll('.provider-toggle').forEach(t => onProviderEnabledChange(t));
    }
})();
</script>

<style>
/* ============================================================ */
/* DEVELOPER STYLES                                              */
/* ============================================================ */

.card {
    border-radius: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s, box-shadow 0.3s;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    overflow: hidden;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.developer-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    display: inline-block;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.6s ease-out forwards; }

.toast-notification {
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    border-radius: 0.75rem;
}

.status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    transition: all 0.3s;
}

.toggle-bg {
    background-color: var(--border-color);
    transition: background-color 0.3s ease;
}
.toggle-dot {
    background-color: white;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
}

input:focus, select:focus {
    outline: none;
    border-color: #007bff !important;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.provider-tab {
    cursor: pointer;
    transition: all 0.3s ease;
}
.provider-tab:hover { transform: translateY(-1px); }

code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    word-break: break-all;
}

@media (max-width: 768px) {
    .grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .toast-notification {
        min-width: 280px;
        max-width: 320px;
        left: 50%;
        transform: translateX(-50%) translateY(-100%);
        right: auto;
    }
}
</style>
@endsection