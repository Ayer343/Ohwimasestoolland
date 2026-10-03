{{-- ============================================ --}}
{{-- DEVELOPER PAYMENT PROVIDER VIEW --}}
{{-- ============================================ --}}
@php
    $user = auth()->user();
    $layout = 'layouts.dev';
    $isDeveloper = $user && ($user->hasRole('developer') || $user->type == 5);
    $canBill = env('DEVELOPER_PAYMENT_CAN_BILL', true);
    
    // Check which providers are configured
    $configuredProviders = [
        'paystack' => !empty(env('DEVELOPER_PAYSTACK_SECRET_KEY')) && !empty(env('DEVELOPER_PAYSTACK_PUBLIC_KEY')),
        'expresspay' => !empty(env('DEVELOPER_EXPRESSPAY_MERCHANT_ID')) && !empty(env('DEVELOPER_EXPRESSPAY_API_KEY')),
        'flutterwave' => !empty(env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY')) && !empty(env('DEVELOPER_FLUTTERWAVE_SECRET_KEY')),
        'hubtel' => !empty(env('DEVELOPER_HUBTEL_CLIENT_ID')) && !empty(env('DEVELOPER_HUBTEL_CLIENT_SECRET'))
    ];
    
    $enabledProviders = [
        'paystack' => env('DEVELOPER_PAYSTACK_ENABLED', false),
        'expresspay' => env('DEVELOPER_EXPRESSPAY_ENABLED', false),
        'flutterwave' => env('DEVELOPER_FLUTTERWAVE_ENABLED', false),
        'hubtel' => env('DEVELOPER_HUBTEL_ENABLED', false)
    ];
    
    $userRole = $isDeveloper ? 'developer' : 'user';
@endphp

@extends($layout)

@section('title', 'Developer Payment Configuration')

@section('content')
<div class="space-y-6 animate-fadeInUp">
    
    <!-- ============================================ -->
    {{-- HEADER SECTION --}}
    {{-- ============================================ --}}
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
                <button id="clearDeveloperCache" 
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-broom mr-2"></i> Clear Cache
                </button>
                @if($canBill)
                <a href="#billingSection" 
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-receipt mr-2"></i> Bill Admin
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    {{-- DEVELOPER ACCESS BANNER --}}
    {{-- ============================================ --}}
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
                        - These are separate from system payment credentials
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    {{-- PROVIDER SELECTION & CREDENTIALS FORM --}}
    {{-- ============================================ --}}
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
            <!-- Provider Selection Tabs -->
            <div class="flex flex-wrap gap-2 mb-6 border-b pb-4" style="border-color: var(--border-color);">
                @foreach(['paystack', 'expresspay', 'flutterwave', 'hubtel'] as $provider)
                @php
                    $providerDisplayNames = [
                        'paystack' => 'Paystack',
                        'expresspay' => 'ExpressPay',
                        'flutterwave' => 'Flutterwave',
                        'hubtel' => 'Hubtel'
                    ];
                    $isConfigured = $configuredProviders[$provider] ?? false;
                    $isEnabled = $enabledProviders[$provider] ?? false;
                @endphp
                <button type="button" 
                        class="provider-tab px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $loop->first ? 'active' : '' }}"
                        data-provider="{{ $provider }}"
                        style="{{ $loop->first ? 'background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                    <i class="fas {{ $provider === 'paystack' ? 'fa-credit-card' : ($provider === 'expresspay' ? 'fa-credit-card' : ($provider === 'flutterwave' ? 'fa-cloud-upload-alt' : 'fa-phone-alt')) }} mr-2"></i>
                    {{ $providerDisplayNames[$provider] }}
                    @if($isConfigured && $isEnabled)
                    <span class="ml-1 text-xs" style="color: var(--success);">✅</span>
                    @elseif($isConfigured && !$isEnabled)
                    <span class="ml-1 text-xs" style="color: var(--warning);">⏸️</span>
                    @else
                    <span class="ml-1 text-xs" style="color: var(--text-secondary);">🔴</span>
                    @endif
                </button>
                @endforeach
            </div>

            <!-- Provider Forms -->
            @foreach(['paystack', 'expresspay', 'flutterwave', 'hubtel'] as $provider)
            @php
                $providerDisplayNames = [
                    'paystack' => 'Paystack',
                    'expresspay' => 'ExpressPay',
                    'flutterwave' => 'Flutterwave',
                    'hubtel' => 'Hubtel'
                ];
                $isActive = $loop->first;
            @endphp
            <div class="provider-form-container {{ $isActive ? '' : 'hidden' }}" id="providerForm-{{ $provider }}">
                <form method="POST" action="{{ route('developer.payment-providers.configure') }}" data-provider="{{ $provider }}">
                    @csrf
                    <input type="hidden" name="provider" value="{{ $provider }}">
                    
                    <div class="space-y-6">
                        <!-- Dynamic Fields Based on Provider -->
                        @if($provider === 'paystack')
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Secret Key <span style="color: #dc3545;">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" 
                                           name="secret_key" 
                                           value="{{ old('secret_key', env('DEVELOPER_PAYSTACK_SECRET_KEY', '')) }}"
                                           class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                           required>
                                    <button type="button" 
                                            onclick="toggleVisibility(this)" 
                                            class="absolute right-3 top-3 hover:opacity-80"
                                            style="color: var(--text-secondary);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Public Key <span style="color: #dc3545;">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" 
                                           name="public_key" 
                                           value="{{ old('public_key', env('DEVELOPER_PAYSTACK_PUBLIC_KEY', '')) }}"
                                           class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                           required>
                                    <button type="button" 
                                            onclick="toggleVisibility(this)" 
                                            class="absolute right-3 top-3 hover:opacity-80"
                                            style="color: var(--text-secondary);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @elseif($provider === 'expresspay')
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Merchant ID <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" 
                                       name="merchant_id" 
                                       value="{{ old('merchant_id', env('DEVELOPER_EXPRESSPAY_MERCHANT_ID', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your Merchant ID"
                                       required>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    API Key <span style="color: #dc3545;">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" 
                                           name="api_key" 
                                           value="{{ old('api_key', env('DEVELOPER_EXPRESSPAY_API_KEY', '')) }}"
                                           class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="Enter your API Key"
                                           required>
                                    <button type="button" 
                                            onclick="toggleVisibility(this)" 
                                            class="absolute right-3 top-3 hover:opacity-80"
                                            style="color: var(--text-secondary);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @elseif($provider === 'flutterwave')
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Public Key <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" 
                                       name="public_key" 
                                       value="{{ old('public_key', env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="FLWPUBK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       required>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Secret Key <span style="color: #dc3545;">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" 
                                           name="secret_key" 
                                           value="{{ old('secret_key', env('DEVELOPER_FLUTTERWAVE_SECRET_KEY', '')) }}"
                                           class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="FLWSECK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                           required>
                                    <button type="button" 
                                            onclick="toggleVisibility(this)" 
                                            class="absolute right-3 top-3 hover:opacity-80"
                                            style="color: var(--text-secondary);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @elseif($provider === 'hubtel')
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Client ID <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" 
                                       name="client_id" 
                                       value="{{ old('client_id', env('DEVELOPER_HUBTEL_CLIENT_ID', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your Client ID"
                                       required>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                    Client Secret <span style="color: #dc3545;">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" 
                                           name="client_secret" 
                                           value="{{ old('client_secret', env('DEVELOPER_HUBTEL_CLIENT_SECRET', '')) }}"
                                           class="w-full rounded-lg border px-4 py-3 font-mono text-sm"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="Enter your Client Secret"
                                           required>
                                    <button type="button" 
                                            onclick="toggleVisibility(this)" 
                                            class="absolute right-3 top-3 hover:opacity-80"
                                            style="color: var(--text-secondary);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Status Toggle -->
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
                                               data-provider="{{ $provider }}"
                                               {{ old('enabled', $enabledProviders[$provider] ?? false) ? 'checked' : '' }}
                                               onchange="onProviderEnabledChange(this)">
                                        <div class="toggle-bg block h-8 w-14 rounded-full transition-colors"></div>
                                        <div class="toggle-dot absolute left-1 top-1 h-6 w-6 rounded-full transition-all transform"></div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="font-medium toggle-label" style="color: var(--text-primary);">
                                            {{ ($enabledProviders[$provider] ?? false) ? 'Enabled' : 'Disabled' }}
                                        </div>
                                        <div class="text-xs toggle-text" style="color: var(--text-secondary);">
                                            {{ ($enabledProviders[$provider] ?? false) ? 'You can bill admins' : 'Billing is disabled' }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4" style="border-color: var(--border-color);">
                            <div class="flex space-x-2">
                                <button type="button" 
                                        onclick="testDeveloperConnection('{{ $provider }}')"
                                        class="test-dev-btn text-sm font-medium hover:opacity-80 flex items-center px-3 py-1.5 rounded"
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-vial mr-1"></i>
                                    Test Credentials
                                </button>
                                <button type="button" 
                                        onclick="clearProviderForm('{{ $provider }}')"
                                        class="text-sm font-medium hover:opacity-80 flex items-center px-3 py-1.5 rounded"
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                    <i class="fas fa-undo mr-1"></i>
                                    Clear
                                </button>
                            </div>
                            <button type="submit" 
                                    class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                                    style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                                <i class="fas fa-save mr-2"></i>
                                Save {{ $providerDisplayNames[$provider] }} Credentials
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endforeach
        </div>
    </div>

    <!-- ============================================ -->
    {{-- PROVIDER STATUS CARDS --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="providerStatusCards">
        @foreach(['paystack', 'expresspay', 'flutterwave', 'hubtel'] as $provider)
        @php
            $providerNames = [
                'paystack' => 'Paystack',
                'expresspay' => 'ExpressPay',
                'flutterwave' => 'Flutterwave',
                'hubtel' => 'Hubtel'
            ];
            $providerColors = [
                'paystack' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'text' => '#3B82F6', 'icon' => 'fa-credit-card'],
                'expresspay' => ['bg' => 'rgba(0, 102, 204, 0.1)', 'text' => '#0066CC', 'icon' => 'fa-credit-card'],
                'flutterwave' => ['bg' => 'rgba(249, 115, 22, 0.1)', 'text' => '#F97316', 'icon' => 'fa-cloud-upload-alt'],
                'hubtel' => ['bg' => 'rgba(37, 99, 235, 0.1)', 'text' => '#2563EB', 'icon' => 'fa-phone-alt']
            ];
            $colors = $providerColors[$provider];
            $name = $providerNames[$provider];
            $isConfigured = $configuredProviders[$provider] ?? false;
            $isEnabled = $enabledProviders[$provider] ?? false;
        @endphp
        <div class="card overflow-hidden" id="provider-{{ $provider }}" data-provider="{{ $provider }}">
            <div class="p-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full" 
                             style="background-color: {{ $colors['bg'] }};">
                            <i class="fas {{ $colors['icon'] }} text-xl" style="color: {{ $colors['text'] }};"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                {{ $name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="status-badge px-2 py-0.5 text-xs rounded" 
                                      style="background-color: {{ $isConfigured && $isEnabled ? 'rgba(var(--success-rgb), 0.2)' : 'rgba(var(--danger-rgb), 0.2)' }}; 
                                             color: {{ $isConfigured && $isEnabled ? 'var(--success)' : 'var(--danger)' }};">
                                    {{ $isConfigured && $isEnabled ? '✅ Active' : ($isConfigured ? '⏸️ Disabled' : '❌ Not Configured') }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-user-cog mr-1"></i> Your Config
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="test-dev-connection px-3 py-1.5 text-xs rounded transition-colors hover:opacity-80" 
                                data-provider="{{ $provider }}"
                                style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }}; border: 1px solid {{ $colors['text'] }};">
                            <i class="fas fa-vial mr-1"></i> Test Yours
                        </button>
                        <button class="test-system-connection px-3 py-1.5 text-xs rounded transition-colors hover:opacity-80" 
                                data-provider="{{ $provider }}"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <i class="fas fa-vial mr-1"></i> Test System
                        </button>
                    </div>
                </div>
                
                <!-- Configuration Status -->
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                        <p class="text-xs" style="color: var(--text-secondary);">System Status</p>
                        <p class="text-sm font-medium mt-0.5" style="color: {{ env(strtoupper($provider) . '_ENABLED', false) ? 'var(--success)' : 'var(--text-secondary)' }};">
                            {{ env(strtoupper($provider) . '_ENABLED', false) ? '✅ Enabled' : '❌ Disabled' }}
                        </p>
                    </div>
                    <div class="p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                        <p class="text-xs" style="color: var(--text-secondary);">Your Status</p>
                        <p class="text-sm font-medium mt-0.5" style="color: {{ $isConfigured && $isEnabled ? 'var(--success)' : ($isConfigured ? 'var(--warning)' : 'var(--danger)') }};">
                            {{ $isConfigured && $isEnabled ? '✅ Active' : ($isConfigured ? '⏸️ Disabled' : '❌ Not Configured') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- ============================================ -->
    {{-- BILLING SECTION --}}
    {{-- ============================================ --}}
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
            @if(!array_filter($configuredProviders))
            <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                <p class="mt-2 font-medium" style="color: var(--text-primary);">Please configure at least one provider first</p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Enter your API credentials above to enable billing</p>
            </div>
            @else
            <form id="billingForm" method="POST" action="{{ route('developer.payment-providers.bill-admin') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Provider <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="provider" 
                                id="billingProvider"
                                class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            @foreach(['paystack', 'expresspay', 'flutterwave', 'hubtel'] as $p)
                            @if($configuredProviders[$p] ?? false)
                            <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                            @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Amount (GHS) <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="number" 
                               name="amount" 
                               id="billingAmount"
                               class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="100.00"
                               step="0.01"
                               min="1"
                               required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Admin Email <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="email" 
                               name="email" 
                               id="billingEmail"
                               class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="admin@example.com"
                               required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Description <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="text" 
                               name="description" 
                               id="billingDescription"
                               class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="App usage fee - {{ date('F Y') }}"
                               required>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" 
                            id="billingSubmitBtn"
                            class="rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                            style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Invoice
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>

    <!-- ============================================ -->
    {{-- WEBHOOK URLS CARD --}}
    {{-- ============================================ --}}
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-link mr-2" style="color: var(--info);"></i> Webhook URLs
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">Copy these URLs for your provider dashboards</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach(['paystack', 'expresspay', 'flutterwave', 'hubtel'] as $provider)
                @php
                    $providerNames = [
                        'paystack' => 'Paystack',
                        'expresspay' => 'ExpressPay',
                        'flutterwave' => 'Flutterwave',
                        'hubtel' => 'Hubtel'
                    ];
                @endphp
                <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $providerNames[$provider] }}</span>
                        <code id="webhookUrl-{{ $provider }}" class="block text-xs mt-1" style="color: var(--text-secondary);">
                            {{ url("/api/payments/{$provider}/webhook") }}
                        </code>
                    </div>
                    <button onclick="copyWebhookUrl('{{ $provider }}')" class="px-3 py-1 text-xs rounded hover:opacity-80" 
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
// ============================================
// HELPER: Fetch JSON with BOM handling
// ============================================
function fetchJSON(url, options = {}) {
    options.headers = {
        ...options.headers,
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
    };
    options.credentials = 'same-origin';
    
    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text(); // Get as text first to handle BOM
        })
        .then(text => {
            // ✅ REMOVE UTF-8 BOM CHARACTER
            if (text.charCodeAt(0) === 0xFEFF || text.substring(0, 1) === '\uFEFF') {
                text = text.substring(1);
            }
            // Trim any whitespace
            text = text.trim();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error. Text:', text.substring(0, 200));
                throw new Error('Invalid JSON response: ' + e.message);
            }
        });
}

// ============================================
// PROVIDER TAB SWITCHING
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.provider-tab');
    const forms = document.querySelectorAll('.provider-form-container');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active from all tabs
            tabs.forEach(t => {
                t.style.backgroundColor = 'var(--bg-secondary)';
                t.style.color = 'var(--text-primary)';
                t.style.borderColor = 'var(--border-color)';
                t.classList.remove('active');
            });
            
            // Activate clicked tab
            const provider = this.dataset.provider;
            this.style.backgroundColor = 'rgba(var(--info-rgb), 0.1)';
            this.style.color = 'var(--info)';
            this.style.borderColor = 'rgba(var(--info-rgb), 0.3)';
            this.classList.add('active');
            
            // Show corresponding form
            forms.forEach(form => {
                form.classList.add('hidden');
                if (form.id === 'providerForm-' + provider) {
                    form.classList.remove('hidden');
                }
            });
        });
    });
    
    // ============================================
    // DEVELOPER CREDENTIALS FORM SUBMISSION
    // ============================================
    const devForms = document.querySelectorAll('.provider-form-container form');
    devForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            
            const formData = new FormData(this);
            
            // ✅ Use fetchJSON with BOM handling
            fetchJSON(this.action, {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.success) {
                    showToast('✅ ' + data.message, 'success');
                    
                    // ✅ REDIRECT AFTER SUCCESS
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    } else {
                        // Reload status if no redirect
                        location.reload();
                    }
                } else {
                    let errorMsg = data.message || 'Failed to save credentials';
                    if (data.errors) {
                        const errors = Object.values(data.errors).flat();
                        errorMsg = errors.join(', ');
                    }
                    showToast('❌ ' + errorMsg, 'error');
                }
            })
            .catch(error => {
                showToast('❌ Error: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    });
});

// ============================================
// TOGGLE VISIBILITY
// ============================================
function toggleVisibility(button) {
    const input = button.closest('.relative').querySelector('input');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// ============================================
// TOGGLE STATE
// ============================================
function onProviderEnabledChange(toggle) {
    const toggleDot = toggle.closest('.relative').querySelector('.toggle-dot');
    const toggleBg = toggle.closest('.relative').querySelector('.toggle-bg');
    const label = toggle.closest('.space-y-3').querySelector('.toggle-label');
    const text = toggle.closest('.space-y-3').querySelector('.toggle-text');
    
    if (toggle.checked) {
        toggleDot.style.transform = 'translateX(1.5rem)';
        toggleBg.style.backgroundColor = '#28a745';
        label.textContent = 'Enabled';
        text.textContent = 'You can bill admins';
    } else {
        toggleDot.style.transform = 'translateX(0)';
        toggleBg.style.backgroundColor = 'var(--border-color)';
        label.textContent = 'Disabled';
        text.textContent = 'Billing is disabled';
    }
}

// ============================================
// TEST DEVELOPER CONNECTION
// ============================================
function testDeveloperConnection(provider) {
    const btn = document.querySelector(`.test-dev-btn[data-provider="${provider}"]`) || 
                document.querySelector(`.test-dev-connection[data-provider="${provider}"]`);
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
    btn.disabled = true;
    
    // ✅ Use fetchJSON with BOM handling
    fetchJSON('{{ route("developer.payment-providers.test-developer-connection") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ provider: provider })
    })
    .then(data => {
        showToast(data.success ? '✅ ' + data.message : '❌ ' + data.message, data.success ? 'success' : 'error');
    })
    .catch(error => {
        showToast('❌ Test failed: ' + error.message, 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ============================================
// CLEAR PROVIDER FORM
// ============================================
function clearProviderForm(provider) {
    if (!confirm('Clear all form fields for this provider?')) return;
    
    const form = document.querySelector(`#providerForm-${provider} form`);
    if (!form) return;
    
    const inputs = form.querySelectorAll('input[type="text"], input[type="password"], input[type="number"]');
    inputs.forEach(input => {
        input.value = '';
    });
    
    // Uncheck toggle
    const toggle = form.querySelector('.provider-toggle');
    if (toggle) {
        toggle.checked = false;
        const toggleDot = toggle.closest('.relative').querySelector('.toggle-dot');
        const toggleBg = toggle.closest('.relative').querySelector('.toggle-bg');
        const label = toggle.closest('.space-y-3').querySelector('.toggle-label');
        const text = toggle.closest('.space-y-3').querySelector('.toggle-text');
        
        toggleDot.style.transform = 'translateX(0)';
        toggleBg.style.backgroundColor = 'var(--border-color)';
        label.textContent = 'Disabled';
        text.textContent = 'Billing is disabled';
    }
    
    showToast('Form cleared for ' + provider, 'info');
}

// ============================================
// COPY WEBHOOK URL
// ============================================
function copyWebhookUrl(provider) {
    const codeElement = document.getElementById('webhookUrl-' + provider);
    if (!codeElement) return;
    
    const url = codeElement.textContent.trim();
    
    navigator.clipboard.writeText(url).then(() => {
        showToast(`${getProviderDisplayName(provider)} webhook URL copied`, 'success');
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast(`${getProviderDisplayName(provider)} webhook URL copied`, 'success');
    });
}

function getProviderDisplayName(key) {
    const names = {
        'paystack': 'Paystack',
        'expresspay': 'ExpressPay',
        'flutterwave': 'Flutterwave',
        'hubtel': 'Hubtel'
    };
    return names[key] || key;
}

// ============================================
// SHOW TOAST
// ============================================
function showToast(message, type = 'info') {
    // Remove existing toasts
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full';
    
    let icon = 'fa-info-circle';
    let bgColor = 'var(--info)';
    
    switch (type) {
        case 'success':
            icon = 'fa-check-circle';
            bgColor = 'var(--success)';
            break;
        case 'error':
            icon = 'fa-exclamation-circle';
            bgColor = 'var(--danger)';
            break;
        case 'warning':
            icon = 'fa-exclamation-triangle';
            bgColor = 'var(--warning)';
            break;
    }
    
    toast.style.backgroundColor = bgColor;
    toast.innerHTML = `<div class="flex items-center"><i class="fas ${icon} mr-2"></i><span>${message}</span></div>`;
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 100);
    
    // Animate out after 5 seconds
    setTimeout(() => {
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// ============================================
// BILLING FORM HANDLER
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const billingForm = document.getElementById('billingForm');
    if (billingForm) {
        billingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('billingSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            const formData = new FormData(this);
            
            // ✅ Use fetchJSON with BOM handling
            fetchJSON(this.action, {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.success) {
                    showToast('✅ ' + data.message, 'success');
                    if (data.redirect_url) {
                        window.open(data.redirect_url, '_blank');
                    }
                    billingForm.reset();
                } else {
                    showToast('❌ ' + (data.message || 'Billing failed'), 'error');
                }
            })
            .catch(error => {
                showToast('❌ Error: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Initialize toggle states
    document.querySelectorAll('.provider-toggle').forEach(toggle => {
        if (toggle.checked) {
            const toggleDot = toggle.closest('.relative').querySelector('.toggle-dot');
            const toggleBg = toggle.closest('.relative').querySelector('.toggle-bg');
            toggleDot.style.transform = 'translateX(1.5rem)';
            toggleBg.style.backgroundColor = '#28a745';
        }
    });
    
    // ============================================
    // TEST SYSTEM CONNECTION (for all providers)
    // ============================================
    document.querySelectorAll('.test-system-connection').forEach(btn => {
        btn.addEventListener('click', function() {
            const provider = this.dataset.provider;
            testSystemConnection(provider, this);
        });
    });
    
    // ============================================
    // TEST DEVELOPER CONNECTION (for all providers)
    // ============================================
    document.querySelectorAll('.test-dev-connection').forEach(btn => {
        btn.addEventListener('click', function() {
            const provider = this.dataset.provider;
            testDeveloperConnection(provider);
        });
    });
});

// ============================================
// TEST SYSTEM CONNECTION
// ============================================
function testSystemConnection(provider, btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
    btn.disabled = true;
    
    // ✅ Use fetchJSON with BOM handling
    fetchJSON('{{ route("developer.payment-providers.test-connection") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ provider: provider })
    })
    .then(data => {
        showToast(data.success ? '✅ System: ' + data.message : '❌ System: ' + data.message, data.success ? 'success' : 'error');
    })
    .catch(error => {
        showToast('❌ System test failed: ' + error.message, 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ============================================
// REFRESH PROVIDER STATUS
// ============================================
function refreshProviderStatus(provider) {
    const btn = document.getElementById('refreshStatus');
    if (btn) {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        btn.disabled = true;
        
        // ✅ Use fetchJSON with BOM handling
        fetchJSON('{{ route("developer.payment-providers.status") }}', {
            method: 'GET'
        })
        .then(data => {
            if (data.success) {
                showToast('Status refreshed successfully', 'success');
                // Update status cards
                if (data.data && data.data[provider]) {
                    updateStatusCard(provider, data.data[provider]);
                }
            }
        })
        .catch(error => {
            showToast('Failed to refresh status: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = original;
            btn.disabled = false;
        });
    }
}

// ============================================
// UPDATE STATUS CARD
// ============================================
function updateStatusCard(provider, status) {
    const card = document.querySelector(`#provider-${provider}`);
    if (!card) return;
    
    const statusBadge = card.querySelector('.status-badge');
    if (statusBadge) {
        const isConfigured = status.developer_configured || false;
        const isEnabled = status.developer_config?.enabled || false;
        
        if (isConfigured && isEnabled) {
            statusBadge.textContent = '✅ Active';
            statusBadge.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
            statusBadge.style.color = 'var(--success)';
        } else if (isConfigured) {
            statusBadge.textContent = '⏸️ Disabled';
            statusBadge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.2)';
            statusBadge.style.color = 'var(--warning)';
        } else {
            statusBadge.textContent = '❌ Not Configured';
            statusBadge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
            statusBadge.style.color = 'var(--danger)';
        }
    }
}
</script>

<style>
/* ============================================ */
/* DEVELOPER STYLES */
/* ============================================ */

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
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeInUp {
    animation: fadeInUp 0.6s ease-out forwards;
}

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

.provider-tab:hover {
    transform: translateY(-1px);
}

code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    word-break: break-all;
}

@media (max-width: 768px) {
    .grid-cols-1.lg\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
    
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