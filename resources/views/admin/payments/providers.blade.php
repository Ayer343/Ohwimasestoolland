{{--
|--------------------------------------------------------------------------
| Payment Provider Configuration
|--------------------------------------------------------------------------
|
| Layout selection is dynamic (developer / admin / default).
| Tab list and webhook URLs are driven by config('payment_providers.providers').
| All mutating actions are guarded by @can('manage-payments').
|
--}}

@php
    use App\Support\PaymentProviderRegistry;

    $user = auth()->user();

    $isDeveloper  = $user && ($user->hasRole('developer') || $user->type == 5);
    $isSuperAdmin = $user && ($user->hasRole('super-admin') || $user->type == 0);
    $isAdmin      = $user && ($user->hasRole('admin') || $user->type == 1);

    $layout = 'layouts.app';
    if ($isDeveloper) {
        $layout = 'layouts.dev';
    }

    $userRole = $isDeveloper ? 'developer'
        : ($isSuperAdmin ? 'super-admin'
        : ($isAdmin ? 'admin' : ($user ? 'user' : 'guest')));

    $canManagePayments = $user && $user->can('manage-payments');
    $billingAllowsWrite = true;
    try {
        $billingAllowsWrite = app(\App\Services\BillingAccessService::class)->canPerformWrite($user);
    } catch (\Throwable $e) {
        // fail open — never block the page because billing lookup failed
    }

    // Build provider list from registry (single source of truth)
    $providerList = [];
    foreach (\App\Support\PaymentProviderRegistry::keys() as $key) {
        $meta = \App\Support\PaymentProviderRegistry::get($key);
        $providerList[$key] = [
            'key'         => $key,
            'name'        => $meta['name'] ?? ucfirst($key),
            'icon'        => $meta['icon'] ?? 'fa-credit-card',
            'color'       => $meta['color'] ?? '#6B7280',
            'description' => $meta['description'] ?? 'Payment gateway',
            'enabled'     => (bool) ($providers[$key]['enabled'] ?? false),
            'configured'  => (bool) ($configurationStatus[$key]['configured'] ?? false),
            'available'   => (bool) ($configurationStatus[$key]['enabled'] ?? false)
                                 && (bool) ($configurationStatus[$key]['configured'] ?? false),
            'environment' => $configurationStatus[$key]['environment'] ?? 'sandbox',
        ];
    }
    $firstProviderKey = array_key_first($providerList) ?: 'expresspay';

    // Build webhook URL list from registry
    $webhookUrls = [];
    foreach (array_keys($providerList) as $key) {
        $webhookUrls[$key] = url("/api/payments/{$key}/webhook");
    }
@endphp

@extends($layout)

@section('title', 'Payment Provider Configuration')

@section('content')
<div class="space-y-6 animate-fadeInUp">

    {{-- ===================================================== --}}
    {{-- HEADER --}}
    {{-- ===================================================== --}}
    <div class="card overflow-hidden">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    Payment Provider Configuration
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure and manage your payment gateway integrations
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-4 lg:mt-0">
                <button id="refreshStatus"
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                </button>

                @can('manage-payments')
                    <button id="verifyEnvironment"
                            class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-check-circle mr-2"></i> Verify Environment
                    </button>

                    @if($billingAllowsWrite)
                        <button id="clearStates"
                                class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-eraser mr-2"></i> Clear States
                        </button>
                    @endif
                @endcan

                <a href="{{ url()->previous() }}"
                   class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    {{-- ===================================================== --}}
    {{-- ROLE-BASED ACCESS BANNER --}}
    {{-- ===================================================== --}}
    @if($userRole === 'developer')
        <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm"
             style="border-color: rgba(var(--info-rgb), 0.2); background-color: rgba(var(--info-rgb), 0.05);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-code text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h4 class="font-semibold" style="color: var(--info);">🔧 Developer Full Access Mode</h4>
                    <p class="mt-1 text-sm" style="color: var(--text-primary);">
                        You have complete access to all payment provider configurations including sensitive API credentials and test modes.
                        <span class="block mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Using developer layout with full debugging capabilities.
                        </span>
                    </p>
                </div>
            </div>
        </div>
    @elseif($userRole === 'super-admin')
        <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm"
             style="border-color: rgba(var(--primary-rgb), 0.3); background-color: rgba(var(--primary-rgb), 0.05);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-crown text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h4 class="font-semibold" style="color: var(--primary);">👑 Super Admin Full Access</h4>
                    <p class="mt-1 text-sm" style="color: var(--text-primary);">
                        You have full administrative access to all payment provider configurations and system settings.
                    </p>
                </div>
            </div>
        </div>
    @elseif($userRole === 'admin')
        <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm"
             style="border-color: rgba(var(--primary-rgb), 0.2); background-color: rgba(var(--primary-rgb), 0.05);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-shield-alt text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h4 class="font-semibold" style="color: var(--primary);">🛡️ Admin Access Mode</h4>
                    <p class="mt-1 text-sm" style="color: var(--text-primary);">
                        You have administrative access to configure payment providers and manage settings.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================================================== --}}
    {{-- BILLING OVERDUE NOTICE --}}
    {{-- ===================================================== --}}
    @unless($billingAllowsWrite)
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
                        System billing is currently overdue. Payment provider configuration is temporarily locked.
                        Settle your account to re-enable changes.
                    </p>
                </div>
            </div>
        </div>
    @endunless

    {{-- ===================================================== --}}
    {{-- FLASH MESSAGES --}}
    {{-- ===================================================== --}}
    @foreach(['success' => ['✅ Success!', 'fa-check-circle', 'success'],
              'error'   => ['❌ Error!',   'fa-exclamation-circle', 'danger'],
              'warning' => ['⚠️ Warning!', 'fa-exclamation-triangle', 'warning']] as $key => [$title, $icon, $colorVar])
        @if(session($key))
            <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in js-auto-dismiss"
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

    {{-- ===================================================== --}}
    {{-- ENVIRONMENT STATUS BANNER (JS-driven) --}}
    {{-- ===================================================== --}}
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

    {{-- ===================================================== --}}
    {{-- MAIN CONTENT CARD --}}
    {{-- ===================================================== --}}
    <div class="card overflow-hidden">

        {{-- Provider Navigation (registry-driven) --}}
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Payment Gateway Configuration</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Select a provider to configure settings</p>
            </div>

            <div class="flex flex-wrap gap-2" id="providerTabs" role="tablist">
                @foreach($providerList as $key => $p)
                    @php $isFirst = $key === $firstProviderKey; @endphp
                    <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors provider-tab {{ $isFirst ? 'active' : '' }}"
                            style="{{ $isFirst
                                ? 'background-color: ' . $p['color'] . '1A; color: ' . $p['color'] . '; border: 1px solid ' . $p['color'] . '4D;'
                                : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}"
                            id="{{ $key }}-tab"
                            data-tab-target="{{ $key }}"
                            data-provider-color="{{ $p['color'] }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $key }}"
                            aria-selected="{{ $isFirst ? 'true' : 'false' }}">
                        <i class="fas {{ $p['icon'] }} mr-2"></i> {{ $p['name'] }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Provider Content --}}
        <div class="tab-content" id="providerTabsContent">
            @foreach($providerList as $key => $p)
                @php $isFirst = $key === $firstProviderKey; @endphp
                <div class="tab-pane {{ $isFirst ? 'active' : 'hidden' }} p-6" id="{{ $key }}" role="tabpanel">
                    <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">{{ $p['name'] }}</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $p['description'] }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="status-badge px-2 py-1 text-xs rounded {{ $p['enabled'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}"
                                      data-provider="{{ $key }}">
                                    {{ $p['enabled'] ? '✓ Enabled' : '✕ Disabled' }}
                                </span>
                                <span class="env-indicator text-xs {{ $p['environment'] === 'production' ? 'text-red-600' : 'text-blue-600' }}"
                                      data-provider="{{ $key }}">
                                    {{ $p['environment'] === 'production' ? '🔒 Production' : '🧪 Sandbox' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Provider-specific form partial. Falls back gracefully if
                         a partial is missing, so the page never hard-fails. --}}
                    @includeIf("admin.payments.providers.{$key}", [
                        'providerKey'   => $key,
                        'providerMeta'  => $p,
                        'providerState' => $providerStates[$key] ?? null,
                        'canWrite'      => $canManagePayments && $billingAllowsWrite,
                    ])
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===================================================== --}}
    {{-- WEBHOOK URLS (registry-driven) --}}
    {{-- ===================================================== --}}
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Webhook URLs</h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Copy these webhook URLs into your payment provider dashboards
            </p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="webhookUrlsContainer">
                @foreach($providerList as $key => $p)
                    <div class="flex items-center justify-between p-3 rounded-lg border"
                         style="border-color: var(--border-color);">
                        <div class="min-w-0 flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $p['name'] }}</span>
                            <code id="webhookUrl-{{ $key }}"
                                  class="block text-xs mt-1 break-all"
                                  style="color: var(--text-secondary);">{{ $webhookUrls[$key] }}</code>
                        </div>
                        <button type="button"
                                onclick="copyWebhookUrl('{{ $key }}')"
                                class="ml-3 px-3 py-1 text-xs rounded hover:opacity-80 whitespace-nowrap"
                                style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
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
// =====================================================
// PAYMENT PROVIDERS PAGE — client script
// =====================================================
(function () {
    'use strict';

    // ---------------------------------------------------
    // Runtime config from Blade
    // ---------------------------------------------------
    const ROUTES = {
        config:          @json(route('admin.payment-providers.config')),
        verifyEnv:       @json(route('admin.payment-providers.verify-environment')),
        immediateStatus: @json(route('admin.payment-providers.immediate-status')),
        testConnection:  @json(route('admin.payment-providers.test-connection')),
        reset:           @json(route('admin.payment-providers.reset')),
        clearStates:     @json(route('admin.payment-providers.clear-states')),
    };

    const PROVIDERS         = @json($providerList);
    const PROVIDER_KEYS     = Object.keys(PROVIDERS);
    const FIRST_PROVIDER    = PROVIDER_KEYS[0] || 'expresspay';

    const CAN_MANAGE_PAYMENTS  = @json($canManagePayments);
    const BILLING_ALLOWS_WRITE = @json($billingAllowsWrite);
    const CAN_WRITE            = CAN_MANAGE_PAYMENTS && BILLING_ALLOWS_WRITE;

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content
              || @json(csrf_token());

    // Request timeout — how long we wait for the server before we declare
    // the request dead. The save path does a full .env write + reload, so
    // 25s is generous but not infinite.
    const REQUEST_TIMEOUT_MS = 25000;

    // ---------------------------------------------------
    // State
    // ---------------------------------------------------
    let currentActiveProvider = FIRST_PROVIDER;
    let isSubmitting          = false;

    // ---------------------------------------------------
    // fetchJSON — hardened against the "Failed to fetch" failure mode
    //
    // The server-side save path used to kill the request mid-response,
    // so the browser saw a closed connection and threw "Failed to fetch".
    // We now distinguish three cases:
    //
    //   1. Network error (server never replied)       → isNetworkError
    //   2. Non-JSON body (HTML error page returned)   → isHtmlResponse
    //   3. Real JSON response                         → normal
    //
    // The caller gets a structured error so it can decide whether the
    // .env file was likely written (and tell the user to reload).
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

        // AbortController for timeout support
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
        options.signal = controller.signal;

        let response;
        try {
            response = await fetch(url, options);
        } catch (networkError) {
            clearTimeout(timeout);

            // AbortController → TimeoutError
            const isAbort = networkError.name === 'AbortError';

            const err = new Error(
                isAbort
                    ? `Request timed out after ${REQUEST_TIMEOUT_MS / 1000}s. The server may still be processing — check storage/logs/laravel.log before retrying.`
                    : 'Network error — the server did not respond. The .env write may have succeeded; reload the page to check.'
            );
            err.isNetworkError = true;
            err.isTimeout      = isAbort;
            err.cause          = networkError;
            throw err;
        }
        clearTimeout(timeout);

        // Read as text so we can inspect what actually came back
        const text = await response.text();

        // Strip UTF-8 BOM if present
        let cleanText = text.charCodeAt(0) === 0xFEFF ? text.substring(1) : text;
        cleanText = cleanText.trim();

        // HTML response? That's Laravel's error page — the request failed
        // before our JSON was produced.
        const looksLikeHtml = cleanText.startsWith('<');

        if (looksLikeHtml) {
            const err = new Error(
                `Server returned an error page (HTTP ${response.status}). ` +
                `This usually means PHP crashed mid-request — check storage/logs/laravel.log.`
            );
            err.status         = response.status;
            err.isHtmlResponse = true;
            err.htmlPreview    = cleanText.substring(0, 300);
            console.error('Non-JSON response from server:', err.htmlPreview);
            throw err;
        }

        // Try to parse JSON
        let data = null;
        try {
            data = cleanText ? JSON.parse(cleanText) : {};
        } catch (e) {
            const err = new Error('Invalid JSON response from server.');
            err.status         = response.status;
            err.isJsonParseError = true;
            err.rawBody        = cleanText.substring(0, 300);
            console.error('JSON parse error. Response preview:', err.rawBody);
            throw err;
        }

        // Non-2xx with parsed JSON → server-side validation or error
        if (!response.ok) {
            const err = new Error(data?.message || `HTTP ${response.status}`);
            err.status = response.status;
            err.data   = data;
            throw err;
        }

        return data;
    }

    // ---------------------------------------------------
    // Toasts
    // ---------------------------------------------------
    function showToast(message, type = 'info', durationMs = 5000) {
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());

        const toast = document.createElement('div');
        toast.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full';

        const styles = {
            success: { icon: 'fa-check-circle',         bg: 'var(--success)' },
            error:   { icon: 'fa-exclamation-circle',   bg: 'var(--danger)'  },
            warning: { icon: 'fa-exclamation-triangle', bg: 'var(--warning)' },
            info:    { icon: 'fa-info-circle',          bg: 'var(--info)'    },
        };
        const s = styles[type] || styles.info;

        toast.style.backgroundColor = s.bg;
        toast.innerHTML = `<div class="flex items-start"><i class="fas ${s.icon} mr-2 mt-1"></i><span class="whitespace-pre-line"></span></div>`;
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
        }, durationMs);
    }

    // ---------------------------------------------------
    // Helpers
    // ---------------------------------------------------
    function providerName(key) {
        return PROVIDERS[key]?.name || key;
    }

    function providerColor(key) {
        return PROVIDERS[key]?.color || '#6B7280';
    }

    function providerIcon(key) {
        return PROVIDERS[key]?.icon || 'fa-credit-card';
    }

    // ---------------------------------------------------
    // Tab switching
    // ---------------------------------------------------
    const tabs     = document.querySelectorAll('[data-tab-target]');
    const tabPanes = document.querySelectorAll('.tab-pane');

    function switchTab(tab) {
        const target = tab.getAttribute('data-tab-target');

        tabs.forEach(t => {
            t.style.backgroundColor = 'var(--bg-secondary)';
            t.style.color           = 'var(--text-primary)';
            t.style.borderColor     = 'var(--border-color)';
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });

        const color = providerColor(target);
        tab.style.backgroundColor = color + '1A';
        tab.style.color           = color;
        tab.style.borderColor     = color + '4D';
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');

        tabPanes.forEach(pane => {
            pane.classList.add('hidden');
            pane.classList.remove('active');
            if (pane.id === target) {
                pane.classList.remove('hidden');
                pane.classList.add('active');
            }
        });

        currentActiveProvider = target;
        window.location.hash = target;

        refreshProviderStatus(target);
    }

    tabs.forEach(tab => tab.addEventListener('click', () => switchTab(tab)));

    // ---------------------------------------------------
    // Load provider config
    // ---------------------------------------------------
    async function loadProviderConfig(provider) {
        try {
            const data = await fetchJSON(`${ROUTES.config}?provider=${encodeURIComponent(provider)}`);
            if (data.success && data.config) {
                updateProviderConfigUI(provider, data.config);
            }
        } catch (err) {
            console.warn('Could not load provider config:', err);
        }
    }

    function updateProviderConfigUI(provider, config) {
        const badge = document.querySelector(`[data-provider="${provider}"] .status-badge`);
        if (badge) {
            badge.textContent = config.enabled ? '✓ Enabled' : '✕ Disabled';
            badge.className = `status-badge px-2 py-1 text-xs rounded ${config.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
        }

        const envIndicator = document.querySelector(`[data-provider="${provider}"] .env-indicator`);
        if (envIndicator && config.environment) {
            const isProd = config.environment === 'production';
            envIndicator.textContent = isProd ? '🔒 Production' : '🧪 Sandbox';
            envIndicator.className = `env-indicator text-xs ${isProd ? 'text-red-600' : 'text-blue-600'}`;
        }

        const toggle = document.querySelector(`[data-provider="${provider}"] input[type="checkbox"][name="enabled"]`);
        if (toggle && config.enabled !== undefined) {
            toggle.checked = !!config.enabled;
            toggle.dispatchEvent(new Event('change'));
        }
    }

    // ---------------------------------------------------
    // Environment status banner
    // ---------------------------------------------------
    function updateEnvironmentStatus(data) {
        const wrap  = document.getElementById('environmentStatus');
        const icon  = document.getElementById('environmentStatusIcon');
        const title = document.getElementById('environmentStatusTitle');
        const text  = document.getElementById('environmentStatusText');
        if (!wrap || !icon || !title || !text) return;

        const provider     = data.provider;
        const isProd       = !!data.is_production;
        const env          = data.current_environment || 'sandbox';
        const actualEnv    = data.actual_environment || env;
        const actualIsProd = data.actual_is_production !== undefined
            ? !!data.actual_is_production
            : isProd;

        const color = providerColor(provider);

        wrap.style.borderColor     = actualIsProd ? 'rgba(var(--success-rgb), 0.3)' : color + '4D';
        wrap.style.backgroundColor = actualIsProd ? 'rgba(var(--success-rgb), 0.05)' : color + '0D';

        icon.style.backgroundColor = actualIsProd ? 'rgba(var(--success-rgb), 0.1)' : color + '1A';
        icon.innerHTML = `<i class="fas ${providerIcon(provider)} text-lg" style="color: ${actualIsProd ? 'var(--success)' : color};"></i>`;

        title.style.color = actualIsProd ? 'var(--success)' : color;
        title.textContent = `${providerName(provider)} Environment`;

        const mismatch = data.actual_environment
            && data.current_environment !== data.actual_environment;

        const recentlyUpdated = data.recently_updated
            ? `<span class="px-2 py-1 text-xs rounded" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);"><i class="fas fa-clock mr-1"></i> Recently Updated</span>`
            : '';

        const mismatchBadge = mismatch
            ? `<div class="mt-2 text-xs" style="color: var(--warning);"><i class="fas fa-exclamation-triangle mr-1"></i> Environment mismatch detected. Refresh the page.</div>`
            : '';

        text.innerHTML = `
            <div class="flex items-center flex-wrap gap-2">
                <span class="font-medium">${actualEnv}</span>
                <span class="px-2 py-1 text-xs rounded"
                      style="background-color: ${actualIsProd ? 'rgba(var(--success-rgb), 0.2)' : 'rgba(var(--primary-rgb), 0.2)'};
                             color: ${actualIsProd ? 'var(--success)' : 'var(--primary)'};">
                    ${actualIsProd ? '🚀 PRODUCTION' : '🧪 SANDBOX'}
                </span>
                ${recentlyUpdated}
            </div>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas ${actualIsProd ? 'fa-exclamation-circle' : 'fa-check-circle'} mr-1"></i>
                ${actualIsProd ? '⚠️ Real transactions will be processed' : '✅ Test transactions only'}
            </div>
            ${actualIsProd ? `<div class="mt-2 text-xs" style="color: var(--danger);"><i class="fas fa-lock mr-1"></i> Production mode — credentials are live</div>` : ''}
            ${mismatchBadge}
        `;

        wrap.classList.remove('hidden');
    }

    // ---------------------------------------------------
    // Verify environment
    // ---------------------------------------------------
    async function verifyCurrentEnvironment() {
        if (!CAN_MANAGE_PAYMENTS) return;

        try {
            const data = await fetchJSON(ROUTES.verifyEnv, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider: currentActiveProvider }),
            });
            if (data.success) {
                updateEnvironmentStatus(data);
                refreshProviderStatus(currentActiveProvider);
            } else {
                throw new Error(data.message || 'Verification failed');
            }
        } catch (err) {
            console.error('Error verifying environment:', err);
            showToast('Failed to verify environment: ' + err.message, 'error');
        }
    }

    // ---------------------------------------------------
    // Refresh provider status
    // ---------------------------------------------------
    async function refreshProviderStatus(provider) {
        try {
            const data = await fetchJSON(ROUTES.immediateStatus, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider }),
            });

            if (data.success && data.data && data.data[provider]) {
                const status = data.data[provider];

                if (status.environment) {
                    const isProd = status.environment === 'production';
                    updateEnvironmentStatus({
                        provider,
                        is_production: isProd,
                        current_environment: status.environment,
                        actual_environment: status.actual_environment || status.environment,
                        actual_is_production: status.actual_is_production !== undefined
                            ? status.actual_is_production
                            : isProd,
                        recently_updated: !!status.recently_updated,
                    });
                }

                updateProviderFormStatus(provider, status);

                document.dispatchEvent(new CustomEvent('providerStatusUpdated', {
                    detail: { provider, status },
                }));
            }
        } catch (err) {
            console.warn('Could not refresh provider status:', err);
        }
    }

    function updateProviderFormStatus(provider, status) {
        const toggle = document.querySelector(`[data-provider="${provider}"] input[type="checkbox"][name="enabled"]`);
        if (toggle && status.enabled !== undefined) {
            toggle.checked = !!status.enabled;
            toggle.dispatchEvent(new Event('change'));
        }

        const badge = document.querySelector(`[data-provider="${provider}"] .status-badge`);
        if (badge) {
            badge.textContent = status.enabled ? '✓ Enabled' : '✕ Disabled';
            badge.className = `status-badge px-2 py-1 text-xs rounded ${status.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
        }

        const configStatus = document.querySelector(`[data-provider="${provider}"] .config-status`);
        if (configStatus) {
            configStatus.textContent = status.configured ? '✓ Configured' : '⚠️ Not Configured';
            configStatus.className = `config-status text-sm ${status.configured ? 'text-green-600' : 'text-yellow-600'}`;
        }

        const envIndicator = document.querySelector(`[data-provider="${provider}"] .env-indicator`);
        if (envIndicator && status.environment) {
            const isProd = status.environment === 'production';
            envIndicator.textContent = isProd ? '🔒 Production' : '🧪 Sandbox';
            envIndicator.className = `env-indicator text-xs ${isProd ? 'text-red-600' : 'text-blue-600'}`;
        }
    }

    // ---------------------------------------------------
    // Test connection
    // ---------------------------------------------------
    async function testConnection(provider, button) {
        if (!CAN_WRITE) {
            showToast('You do not have permission to test connections.', 'warning');
            return;
        }

        const original = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        button.disabled = true;

        try {
            const data = await fetchJSON(ROUTES.testConnection, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider }),
            });

            if (data.success) {
                showToast(`${providerName(provider)}: ${data.message}`, 'success');
                if (data.environment) verifyCurrentEnvironment();
            } else {
                showToast(`${providerName(provider)}: ${data.message}`, 'error');
            }
        } catch (err) {
            console.error('Error testing connection:', err);
            showToast(`Connection test failed: ${err.message}`, 'error');
        } finally {
            button.innerHTML = original;
            button.disabled = false;
        }
    }

    // ---------------------------------------------------
    // Reset configuration
    // ---------------------------------------------------
    async function resetConfiguration(provider, button) {
        if (!CAN_WRITE) {
            showToast('You do not have permission to reset configurations.', 'warning');
            return;
        }

        if (!confirm(`Are you sure you want to reset ${providerName(provider)} configuration? This will disable the provider and clear all settings.`)) {
            return;
        }

        const original = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Resetting...';
        button.disabled = true;

        try {
            const data = await fetchJSON(ROUTES.reset, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider }),
            });

            if (data.success) {
                showToast(data.message || 'Configuration reset', 'success');
                refreshProviderStatus(provider);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.message || 'Reset failed', 'error');
                button.innerHTML = original;
                button.disabled = false;
            }
        } catch (err) {
            console.error('Error resetting configuration:', err);
            showToast('Reset failed: ' + err.message, 'error');
            button.innerHTML = original;
            button.disabled = false;
        }
    }

    // ---------------------------------------------------
    // Clear all states
    // ---------------------------------------------------
    async function clearAllStates() {
        if (!CAN_WRITE) {
            showToast('You do not have permission to clear states.', 'warning');
            return;
        }

        if (!confirm('Clear all cached provider states? This may affect status display.')) {
            return;
        }

        try {
            const data = await fetchJSON(ROUTES.clearStates, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider: 'all' }),
            });

            if (data.success) {
                showToast(data.message || 'States cleared', 'success');
                refreshProviderStatus(currentActiveProvider);
            } else {
                showToast(data.message || 'Failed to clear states', 'error');
            }
        } catch (err) {
            console.error('Error clearing states:', err);
            showToast('Failed to clear states: ' + err.message, 'error');
        }
    }

    // ---------------------------------------------------
    // Submit provider form
    //
    // The save path is the only endpoint that also writes to .env and
    // reloads the environment, so it's the one most likely to time out
    // or die mid-response. The error handling below is tailored for that:
    //
    //   - Timeout / network error → show the "keys may have been written"
    //     message and offer a reload button so the user can verify.
    //   - HTML response → PHP crashed mid-request; point at the log file.
    //   - JSON error → normal validation/server error path.
    // ---------------------------------------------------
    async function submitProviderForm(provider, form) {
        if (isSubmitting) return;

        if (!CAN_WRITE) {
            showToast('You do not have permission to modify payment configuration.', 'warning');
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        const original     = submitButton?.innerHTML || '';

        // Client-side required check — quick feedback, no round-trip
        let hasError = false;
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                hasError = true;
            } else {
                field.style.borderColor = '';
            }
        });

        if (hasError) {
            showToast('Please fill in all required fields', 'warning');
            return;
        }

        isSubmitting = true;
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            submitButton.disabled = true;
        }

        try {
            const data = await fetchJSON(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (data && data.success) {
                showToast(data.message || `${providerName(provider)} configuration updated`, 'success');
                refreshProviderStatus(provider);

                if (data.is_production !== undefined) {
                    updateEnvironmentStatus({
                        provider,
                        is_production: data.is_production,
                        current_environment: data.environment || 'sandbox',
                        recently_updated: true,
                    });
                }

                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1500);
                }
            } else if (data && data.errors) {
                const messages = Object.values(data.errors).flat().join(', ');
                showToast(`Validation failed: ${messages}`, 'error');
            } else if (data) {
                showToast(data.message || 'Failed to update configuration', 'error');
            }
        } catch (err) {
            console.error('Error updating configuration:', err);

            // Case 1 — server never replied (network error, timeout)
            if (err.isNetworkError) {
                showAmbiguousSaveWarning(provider, err);
                return;
            }

            // Case 2 — server replied with an HTML page (PHP crashed)
            if (err.isHtmlResponse) {
                showToast(
                    `⚠️ Server error (HTTP ${err.status}). The .env file may already be updated. ` +
                    `Reload the page to check, then inspect storage/logs/laravel.log for the crash.`,
                    'warning',
                    9000
                );
                return;
            }

            // Case 3 — body wasn't JSON at all
            if (err.isJsonParseError) {
                showToast(
                    `⚠️ Unexpected response from server. The .env file may already be updated. ` +
                    `Reload the page to verify.`,
                    'warning',
                    9000
                );
                return;
            }

            // Case 4 — server returned a JSON error
            const serverMessage = err?.data?.message;
            const validationErrors = err?.data?.errors
                ? Object.values(err.data.errors).flat().join(', ')
                : null;

            showToast(
                validationErrors
                    ? `Validation failed: ${validationErrors}`
                    : `Failed to update configuration: ${serverMessage || err.message}`,
                'error',
                8000
            );
        } finally {
            isSubmitting = false;
            if (submitButton) {
                submitButton.innerHTML = original;
                submitButton.disabled = false;
            }
        }
    }

    // ---------------------------------------------------
    // Ambiguous-save warning
    //
    // Shown when the server never replied (timeout or network error) after
    // the user submitted the save form. The .env write may have succeeded
    // even though the response never arrived. We surface a persistent
    // toast with a "Reload" action so the user doesn't re-submit blindly
    // and overwrite the file.
    // ---------------------------------------------------
    function showAmbiguousSaveWarning(provider, err) {
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());

        const toast = document.createElement('div');
        toast.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white font-medium';
        toast.style.backgroundColor = 'var(--warning)';
        toast.style.maxWidth = '460px';

        toast.innerHTML = `
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle mr-3 mt-1"></i>
                <div class="flex-1">
                    <div class="font-semibold mb-1">Save status unclear</div>
                    <div class="text-sm mb-2" style="line-height:1.4;">
                        ${err.isTimeout
                            ? 'The server took too long to reply. '
                            : 'The connection was lost. '}
                        The <code class="px-1 rounded" style="background:rgba(0,0,0,0.15);">.env</code>
                        file may have already been updated with your credentials. Reload the page
                        to verify before submitting again.
                    </div>
                    <div class="flex gap-2 mt-2">
                        <button type="button" class="js-reload-now px-3 py-1 rounded text-xs font-medium"
                                style="background: rgba(255,255,255,0.25); color: white;">
                            <i class="fas fa-sync-alt mr-1"></i> Reload now
                        </button>
                        <button type="button" class="js-dismiss-ambiguous px-3 py-1 rounded text-xs font-medium"
                                style="background: rgba(255,255,255,0.15); color: white;">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(toast);

        toast.querySelector('.js-reload-now').addEventListener('click', () => window.location.reload());
        toast.querySelector('.js-dismiss-ambiguous').addEventListener('click', () => toast.remove());
    }

    // ---------------------------------------------------
    // Copy webhook URL
    // ---------------------------------------------------
    window.copyWebhookUrl = function (provider) {
        const el = document.getElementById('webhookUrl-' + provider);
        if (!el) return;

        const url  = el.textContent.trim();
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
    // Delegated event listeners
    // ---------------------------------------------------
    document.addEventListener('click', function (e) {
        const testBtn = e.target.closest('.test-connection');
        if (testBtn) {
            const provider = testBtn.getAttribute('data-provider');
            testConnection(provider, testBtn);
            return;
        }

        const resetBtn = e.target.closest('.reset-config');
        if (resetBtn) {
            const provider = resetBtn.getAttribute('data-provider');
            resetConfiguration(provider, resetBtn);
            return;
        }
    });

    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('provider-form')) {
            e.preventDefault();
            const provider = e.target.getAttribute('data-provider');
            submitProviderForm(provider, e.target);
        }
    });

    // Dismissible flash messages
    document.querySelectorAll('.js-dismiss').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.js-auto-dismiss')?.remove());
    });
    document.querySelectorAll('.js-auto-dismiss').forEach(el => {
        setTimeout(() => el.remove(), 5000);
    });

    // ---------------------------------------------------
    // Bootstrap
    // ---------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        const refreshBtn = document.getElementById('refreshStatus');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', async function () {
                const original = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
                this.disabled = true;
                await refreshProviderStatus(currentActiveProvider);
                setTimeout(() => {
                    this.innerHTML = original;
                    this.disabled = false;
                }, 1500);
            });
        }

        const verifyBtn = document.getElementById('verifyEnvironment');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', verifyCurrentEnvironment);
        }

        const clearBtn = document.getElementById('clearStates');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearAllStates);
        }

        // Open the tab named in the URL hash, if any
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            const target = document.querySelector(`[data-tab-target="${hash}"]`);
            if (target) {
                switchTab(target);
                return;
            }
        }

        // Default: load config for the first tab
        loadProviderConfig(currentActiveProvider);

        // One initial status refresh for the active tab
        setTimeout(() => refreshProviderStatus(currentActiveProvider), 800);
    });
})();
</script>

<style>
/* Card styling */
.card {
    border-radius: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s, box-shadow 0.3s;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    backdrop-filter: blur(10px);
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Tabs */
.tab-pane { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Toasts */
.toast-notification {
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    border-radius: 0.75rem;
}
.fade-in { animation: fadeIn 0.5s ease-out; }

/* Required field indicator */
.required::after { content: " *"; color: var(--danger); }

/* Loading spinner */
.fa-spinner { animation: spin 1s linear infinite; }
@keyframes spin {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    #providerTabs { flex-direction: column; gap: 0.5rem; }
    #providerTabs button { width: 100%; justify-content: flex-start; }
    .tab-pane { padding: 1rem !important; }
    .tab-pane .flex { flex-direction: column; align-items: flex-start !important; }
}
@media (max-width: 480px) {
    .flex-col.lg\:flex-row { flex-direction: column; gap: 1rem; }
    .flex-wrap.gap-2 { gap: 0.5rem; }
    .toast-notification {
        min-width: 280px;
        max-width: 320px;
        left: 50%;
        transform: translateX(-50%) translateY(-100%);
        right: auto;
    }
}

/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 3px; }
::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

/* Form validation */
input:invalid, select:invalid, textarea:invalid { border-color: #dc3545 !important; }
input:valid, select:valid, textarea:valid { border-color: var(--border-color) !important; }

/* Code */
code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    word-break: break-all;
}

/* Smooth transitions */
* { transition: color 0.3s, background-color 0.3s, border-color 0.3s; }
</style>
@endsection