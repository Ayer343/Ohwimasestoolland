{{-- ============================================ --}}
{{-- DYNAMIC LAYOUT SELECTION --}}
{{-- ============================================ --}}
@php
    $user = auth()->user();

    $layout = 'layouts.app';

    if ($user) {
        $isDeveloper  = $user->hasRole('developer') || $user->type == 5;
        $isAdmin      = $user->hasRole('admin') || $user->type == 1;
        $isSuperAdmin = $user->hasRole('super-admin') || $user->type == 0;

        if ($isDeveloper) {
            $layout = 'layouts.dev';
        } elseif ($isAdmin || $isSuperAdmin) {
            $layout = 'layouts.app';
        }
    }

    $userRole = $user ? (
        $isDeveloper ? 'developer' : (
            $isSuperAdmin ? 'super-admin' : (
                $isAdmin ? 'admin' : 'user'
            )
        )
    ) : 'guest';

    $currentDefaultProvider = config('sms.default');

    // Read-only mode for super-admins: they can view status and the
    // resolved sender ID, but cannot write API credentials or toggle providers.
    $isDeveloper = $isDeveloper ?? false;
    $readOnly    = !$isDeveloper;
@endphp

@extends($layout)

@section('title', 'SMS Provider Configuration')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="mb-4 md:mb-0">
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">SMS Provider Configuration</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Configure and manage your SMS gateway integrations for agent communications</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="refreshStatus" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                </button>
                <button id="verifyEnvironment" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i> Verify Environment
                </button>
                <a href="{{ url()->previous() }}" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ROLE BANNER -->
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
                    You have complete access to all SMS provider configurations including sensitive API credentials and test modes.
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
                    <i class="fas fa-eye text-lg" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--primary);">👑 Super Admin — Read-Only View</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    You can view SMS provider status and the effective sender ID for each provider.
                    To change the <strong>default SMS sender ID</strong>, use the
                    <a href="{{ route('admin.system-settings.edit') }}#general"
                       class="text-blue-500 hover:underline font-medium">
                        System Settings
                    </a>
                    page. Contact a developer for API credential changes.
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
                    You have administrative access to configure SMS providers and manage settings.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- GLOBAL SENDER ID INFO (read-only indicator for super-admins) -->
    @if($readOnly)
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm"
         style="border-color: rgba(var(--info-rgb), 0.2); background-color: rgba(var(--info-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full"
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-comment-dots text-lg" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--info);">Current Global SMS Sender ID</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    @if(!empty($globalSenderId))
                        <span class="font-mono px-2 py-0.5 rounded"
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            {{ $globalSenderId }}
                        </span>
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">
                            Used as fallback when a provider has no specific sender ID.
                        </span>
                    @else
                        <em style="color: var(--text-secondary);">Not set — providers without a specific sender ID will use the system fallback.</em>
                    @endif
                </p>
                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                    <i class="fas fa-lock mr-1"></i>
                    Change this value in
                    <a href="{{ route('admin.system-settings.edit') }}#general"
                       class="text-blue-500 hover:underline">
                        System Settings → Default SMS Sender ID
                    </a>.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- FLASH MESSAGES -->
    @if(session('success'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in"
         style="border-color: rgba(var(--success-rgb), 0.2); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--success);">✅ Success!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600"
                    onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in"
         style="border-color: rgba(var(--danger-rgb), 0.2); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--danger);">❌ Error!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600"
                    onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('warning'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in"
         style="border-color: rgba(var(--warning-rgb), 0.2); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--warning);">⚠️ Warning!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('warning') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600"
                    onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- ENVIRONMENT STATUS BANNER -->
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

    <!-- System Status Overview -->
    <div class="card p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">System Status</h3>
        <div id="systemStatus" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="col-span-full flex justify-center items-center py-8">
                <div class="flex items-center">
                    <i class="fas fa-spinner fa-spin text-xl mr-3" style="color: var(--primary);"></i>
                    <span style="color: var(--text-secondary);">Loading system status...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Provider Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6" id="providerStatusCards">
        <div class="col-span-full flex justify-center items-center py-8">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-xl mr-3" style="color: var(--primary);"></i>
                <span style="color: var(--text-secondary);">Loading SMS provider status...</span>
            </div>
        </div>
    </div>

    <!-- Default Provider Selection -->
    <div class="card p-6 mb-6">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div class="flex-1">
                <h3 class="text-lg font-semibold mb-1" style="color: var(--text-primary);">Default SMS Provider</h3>
                <p class="text-sm" style="color: var(--text-secondary);">
                    @if($readOnly)
                        The current default provider for agent invitations and notifications.
                    @else
                        Select the default SMS provider for agent invitations and notifications
                    @endif
                </p>
            </div>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full lg:w-auto">
                <div class="relative w-full sm:w-64">
                    <select id="defaultProvider" class="form-select w-full p-3 rounded-lg"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); appearance: none; cursor: pointer; padding-right: 40px;"
                            {{ $readOnly ? 'disabled' : '' }}>
                        <option value="">Select Default Provider</option>
                        @foreach(['arkesel', 'twilio', 'africastalking', 'hubtel', 'nalosolutions'] as $provider)
                        <option value="{{ $provider }}" {{ $currentDefaultProvider === $provider ? 'selected' : '' }}>
                            {{ ucfirst($provider == 'africastalking' ? "Africa's Talking" : ($provider == 'nalosolutions' ? 'Nalo Solutions' : $provider)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @if($isDeveloper)
                    <button id="setDefaultProvider" class="btn-modern flex items-center px-4 py-3 whitespace-nowrap">
                        <i class="fas fa-check mr-2"></i> Set Default
                    </button>
                @else
                    <span class="text-xs flex items-center px-3 py-2 rounded-lg"
                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-lock mr-1"></i> Developer only
                    </span>
                @endif
            </div>
        </div>
        <div id="defaultProviderStatus" class="mt-4 text-sm p-3 rounded-lg"
             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2); color: var(--text-secondary);"></div>
    </div>

    <!-- Provider Tabs -->
    <div class="card p-6">
        <div class="mb-6">
            <div class="border-b" style="border-color: var(--border-color);">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="providerTabs" role="tablist">
                    @foreach([
                        'arkesel' => ['icon' => 'fa-comment-alt', 'label' => 'Arkesel SMS'],
                        'twilio' => ['icon' => 'fab fa-twilio', 'label' => 'Twilio'],
                        'africastalking' => ['icon' => 'fa-sms', 'label' => 'Africa\'s Talking'],
                        'hubtel' => ['icon' => 'fa-comments', 'label' => 'Hubtel SMS'],
                        'nalosolutions' => ['icon' => 'fa-mobile-alt', 'label' => 'Nalo Solutions']
                    ] as $provider => $config)
                    <li class="mr-2" role="presentation">
                        <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg transition-all duration-200 {{ $loop->first ? 'active' : '' }} {{ $loop->first ? 'border-blue-500 text-blue-600' : 'border-transparent' }}"
                                id="{{ $provider }}-tab"
                                data-tab-target="{{ $provider }}"
                                type="button"
                                role="tab"
                                aria-controls="{{ $provider }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                style="{{ !$loop->first ? 'color: var(--text-secondary);' : '' }}">
                            <i class="{{ $config['icon'] }} mr-2"></i>{{ $config['label'] }}
                        </button>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="tab-content" id="providerTabsContent">
            <div class="tab-pane active" id="arkesel" role="tabpanel">
                @include('admin.sms.providers.arkesel', ['isDeveloper' => $isDeveloper, 'globalSenderId' => $globalSenderId ?? null])
            </div>
            <div class="tab-pane hidden" id="twilio" role="tabpanel">
                @include('admin.sms.providers.twilio', ['isDeveloper' => $isDeveloper, 'globalSenderId' => $globalSenderId ?? null])
            </div>
            <div class="tab-pane hidden" id="africastalking" role="tabpanel">
                @include('admin.sms.providers.africastalking', ['isDeveloper' => $isDeveloper, 'globalSenderId' => $globalSenderId ?? null])
            </div>
            <div class="tab-pane hidden" id="hubtel" role="tabpanel">
                @include('admin.sms.providers.hubtel', ['isDeveloper' => $isDeveloper, 'globalSenderId' => $globalSenderId ?? null])
            </div>
            <div class="tab-pane hidden" id="nalosolutions" role="tabpanel">
                @include('admin.sms.providers.nalosolutions', ['isDeveloper' => $isDeveloper, 'globalSenderId' => $globalSenderId ?? null])
            </div>
        </div>
    </div>

    <!-- Test SMS Section -->
    @if($isDeveloper)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Test SMS Sending</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Provider</label>
                <div class="relative">
                    <select id="testProvider" class="form-select w-full p-3 rounded-lg"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); appearance: none; cursor: pointer; padding-right: 40px;">
                        <option value="">Select Provider</option>
                        @foreach(['arkesel', 'twilio', 'africastalking', 'hubtel', 'nalosolutions'] as $provider)
                        <option value="{{ $provider }}">
                            {{ $provider === 'africastalking' ? "Africa's Talking" : ($provider === 'nalosolutions' ? 'Nalo Solutions' : ucfirst($provider)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Phone Number</label>
                <input type="text" id="testPhoneNumber" class="form-input w-full p-3 rounded-lg"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="+233XXXXXXXXX" value="+233595652410">
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Format: +233XXXXXXXXX</p>
            </div>
            <div class="form-group">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Message</label>
                <textarea id="testMessage" class="form-textarea w-full p-3 rounded-lg"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                          rows="1" placeholder="Enter test message">Test SMS from Property Registration System - Connection successful!</textarea>
                <p class="text-xs mt-1" style="color: var(--text-secondary);"><span id="charCount">0</span>/160 characters</p>
            </div>
        </div>
        <div class="flex justify-end mt-4">
            <button id="sendTestSMS" class="btn-modern flex items-center px-6 py-3">
                <i class="fas fa-paper-plane mr-2"></i> Send Test SMS
            </button>
        </div>
    </div>
    @else
    <div class="card p-6"
         style="border-left: 4px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-flask mt-1 mr-3 text-xl" style="color: var(--info);"></i>
            <div>
                <h4 class="font-semibold" style="color: var(--info);">Test SMS Sending — Developer Only</h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Sending test SMS messages is restricted to developers. If you need to verify
                    a provider's connection, contact your developer or use the
                    <a href="{{ route('admin.system-settings.edit') }}#notifications"
                       class="text-blue-500 hover:underline">
                        Notification Channels
                    </a>
                    test from the System Settings page.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Usage Statistics -->
    <div class="card p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">SMS Usage Statistics</h3>
            <div class="flex gap-2">
                <button id="refreshStats" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh
                </button>
                @if($isDeveloper)
                    <button id="cleanupLogs" class="btn-secondary flex items-center px-4 py-2 rounded-lg" title="Clean up logs older than 30 days">
                        <i class="fas fa-broom mr-2"></i> Cleanup Logs
                    </button>
                @endif
            </div>
        </div>
        <div id="usageStatistics" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="col-span-full flex justify-center items-center py-8">
                <div class="flex items-center">
                    <i class="fas fa-spinner fa-spin text-xl mr-3" style="color: var(--primary);"></i>
                    <span style="color: var(--text-secondary);">Loading usage statistics...</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ============================================================================
// GLOBAL CONFIG
// ============================================================================
window.isDeveloper = {{ $isDeveloper ? 'true' : 'false' }};

window.smsProviderConfig = {
    routes: {
        status:            '{{ route("admin.sms-providers.status", [], false) }}',
        test:              '{{ route("admin.sms-providers.test-connection", [], false) }}',
        sendTest:          '{{ route("admin.sms-providers.send-test", [], false) }}',
        setDefault:        '{{ route("admin.sms-providers.set-default", [], false) }}',
        reset:             '{{ route("admin.sms-providers.reset", [], false) }}',
        usage:             '{{ route("admin.sms-providers.usage", [], false) }}',
        toggle:            '{{ route("admin.sms-providers.toggle", [], false) }}',
        cleanup:           '{{ route("admin.sms-providers.cleanup-logs", [], false) }}',
        config:            '{{ route("admin.sms-providers.config", [], false) }}',
        verifyEnvironment: '{{ route("admin.sms-providers.verify-environment", [], false) }}',
    },
    csrfToken: '{{ csrf_token() }}',
};

window.currentActiveProvider = 'arkesel';

// ============================================================================
// SHARED FETCH HELPER — auto-retry on 419 and network errors
// ============================================================================
window.smsFetch = async function (url, options = {}, _retried = false) {
    const defaults = {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.smsProviderConfig.csrfToken,
        },
    };

    const merged = {
        ...defaults,
        ...options,
        headers: { ...defaults.headers, ...(options.headers || {}) },
    };

    let response;
    try {
        response = await fetch(url, merged);
    } catch (networkError) {
        console.warn('[SMS] Network failure:', networkError);

        if (!_retried) {
            console.warn('[SMS] Retrying once after 1500ms…');
            await new Promise(r => setTimeout(r, 1500));
            return window.smsFetch(url, options, true);
        }

        throw new Error('Cannot reach the server — check your connection');
    }

    const raw = await response.text();
    const ct  = response.headers.get('content-type') || '';

    console.debug('[SMS] fetch', {
        url,
        method: merged.method,
        status: response.status,
        contentType: ct,
        bodyPreview: raw.slice(0, 200),
        retried: _retried,
    });

    if (response.status === 419 && !_retried && merged.method !== 'GET') {
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (metaToken && metaToken !== window.smsProviderConfig.csrfToken) {
            window.smsProviderConfig.csrfToken = metaToken;
            return window.smsFetch(url, options, true);
        }
        throw new Error('Session expired — please refresh the page (Ctrl + Shift + R)');
    }

    if (response.status === 419) throw new Error('Session expired — please refresh the page');
    if (response.status === 401 || response.status === 403) throw new Error('Not authenticated — please log in again');
    if (response.status >= 500) throw new Error(`Server error ${response.status} — check logs`);

    if (!ct.includes('application/json')) {
        const plain = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(`Server returned ${response.status}: ${plain.slice(0, 150)}`);
    }

    let data;
    try {
        data = JSON.parse(raw);
    } catch (e) {
        throw new Error('Malformed JSON from server');
    }

    if (!response.ok) {
        throw new Error(data.message || `Server error ${response.status}`);
    }

    return data;
};

// ============================================================================
// DOM READY
// ============================================================================
document.addEventListener('DOMContentLoaded', function () {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (metaToken) {
        window.smsProviderConfig.csrfToken = metaToken;
    }

    const savedTab = sessionStorage.getItem('sms-active-tab');
    if (savedTab) {
        sessionStorage.removeItem('sms-active-tab');
    }

    const tabs     = document.querySelectorAll('[data-tab-target]');
    const tabPanes = document.querySelectorAll('.tab-pane');

    function switchTab(tab) {
        const target = tab.getAttribute('data-tab-target');

        tabs.forEach(t => {
            t.classList.remove('active', 'border-blue-500', 'text-blue-600');
            t.classList.add('border-transparent');
            t.style.color = 'var(--text-secondary)';
            t.setAttribute('aria-selected', 'false');
        });
        tab.classList.remove('border-transparent');
        tab.classList.add('active', 'border-blue-500', 'text-blue-600');
        tab.style.color = '';
        tab.setAttribute('aria-selected', 'true');

        tabPanes.forEach(pane => {
            pane.classList.add('hidden');
            pane.classList.remove('active');
            if (pane.id === target) {
                pane.classList.remove('hidden');
                pane.classList.add('active');
            }
        });

        window.currentActiveProvider = target;
        verifyCurrentEnvironment();
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            switchTab(tab);
            window.location.hash = tab.getAttribute('data-tab-target');
        });
    });

    // Character counter
    const testMessage = document.getElementById('testMessage');
    const charCount   = document.getElementById('charCount');
    if (testMessage && charCount) {
        testMessage.addEventListener('input', function () {
            charCount.textContent = this.value.length;
            charCount.style.color = this.value.length > 160 ? 'var(--danger)' : 'var(--text-secondary)';
        });
        charCount.textContent = testMessage.value.length;
    }

    // Initial loads
    loadProviderStatus();
    loadUsageStatistics();
    loadSystemStatus();

    if (savedTab) {
        const tab = document.querySelector(`[data-tab-target="${savedTab}"]`);
        if (tab) switchTab(tab);
    }

    // Buttons
    document.getElementById('refreshStatus')?.addEventListener('click', function () {
        loadProviderStatus();
        loadSystemStatus();
        verifyCurrentEnvironment();
        showToast('Status refreshed successfully', 'success');
    });

    document.getElementById('verifyEnvironment')?.addEventListener('click', verifyCurrentEnvironment);

    document.getElementById('refreshStats')?.addEventListener('click', function () {
        loadUsageStatistics();
        showToast('Statistics refreshed successfully', 'success');
    });

    document.getElementById('cleanupLogs')?.addEventListener('click', function () {
        cleanupOldLogs(this);
    });

    document.getElementById('setDefaultProvider')?.addEventListener('click', function () {
        const provider = document.getElementById('defaultProvider')?.value;
        if (!provider) { showToast('Please select a provider', 'error'); return; }
        setDefaultProvider(provider, this);
    });

    document.addEventListener('click', function (e) {
        const testBtn = e.target.closest('.test-connection');
        if (testBtn) { testConnection(testBtn.getAttribute('data-provider'), testBtn); return; }

        const resetBtn = e.target.closest('.reset-config');
        if (resetBtn) { resetConfiguration(resetBtn.getAttribute('data-provider'), resetBtn); return; }

        const toggleBtn = e.target.closest('.toggle-provider');
        if (toggleBtn) {
            toggleProvider(
                toggleBtn.getAttribute('data-provider'),
                toggleBtn.getAttribute('data-enable') === 'true',
                toggleBtn
            );
        }
    });

    document.getElementById('sendTestSMS')?.addEventListener('click', function () {
        const provider    = document.getElementById('testProvider')?.value;
        const phoneNumber = document.getElementById('testPhoneNumber')?.value;
        const message     = document.getElementById('testMessage')?.value;

        if (!provider)    { showToast('Please select a provider', 'error'); return; }
        if (!phoneNumber) { showToast('Please enter a phone number', 'error'); return; }
        if (!message)     { showToast('Please enter a message', 'error'); return; }
        if (message.length > 160) { showToast('Message cannot exceed 160 characters', 'error'); return; }

        sendTestSMS(provider, phoneNumber, message, this);
    });

    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('provider-form')) {
            e.preventDefault();
            submitProviderForm(e.target.getAttribute('data-provider'), e.target);
        }
    });

    const hash = window.location.hash.substring(1);
    if (hash) {
        const tab = document.querySelector(`[data-tab-target="${hash}"]`);
        if (tab) switchTab(tab);
    }
});

// ============================================================================
// submitProviderForm
// ============================================================================
async function submitProviderForm(provider, form) {
    const button = form.querySelector('button[type="submit"]');
    if (!button) return;

    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    button.disabled = true;

    sessionStorage.setItem('sms-active-tab', window.currentActiveProvider || provider);

    try {
        const data = await window.smsFetch(form.action, {
            method: 'POST',
            body: new FormData(form),
        });

        if (data.success) {
            showToast(
                `✅ ${data.message || getProviderDisplayName(provider) + ' configuration updated!'}`,
                'success'
            );
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(`❌ ${data.message || 'Failed to update configuration'}`, 'error');
            button.innerHTML = original;
            button.disabled = false;
        }
    } catch (error) {
        console.error('[SMS] submitProviderForm failed:', error);
        showToast(`❌ ${error.message}`, 'error');
        button.innerHTML = original;
        button.disabled = false;
    }
}

// ============================================================================
// verifyCurrentEnvironment
// ============================================================================
async function verifyCurrentEnvironment() {
    const provider = window.currentActiveProvider || 'arkesel';
    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.verifyEnvironment, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider }),
        });
        if (data.success) updateEnvironmentStatus(data);
    } catch (error) {
        console.error('[SMS] verifyCurrentEnvironment failed:', error);
        showToast(`❌ Failed to verify environment: ${error.message}`, 'error');
    }
}

// ============================================================================
// updateEnvironmentStatus
// ============================================================================
function updateEnvironmentStatus(data) {
    const box     = document.getElementById('environmentStatus');
    const iconDiv = document.getElementById('environmentStatusIcon');
    const titleEl = document.getElementById('environmentStatusTitle');
    const textEl  = document.getElementById('environmentStatusText');

    if (!box || !iconDiv || !titleEl || !textEl) {
        console.warn('[SMS] Environment banner elements missing from DOM');
        showToast(
            `✅ ${data.provider_name || data.provider} environment: ${data.current_environment}`,
            'info'
        );
        return;
    }

    const isProd = !!data.is_production;
    const env    = data.current_environment || 'N/A';
    const prov   = data.provider_name || getProviderDisplayName(data.provider);

    box.style.borderColor         = isProd ? 'rgba(var(--warning-rgb), 0.3)' : 'rgba(var(--info-rgb), 0.3)';
    box.style.backgroundColor     = isProd ? 'rgba(var(--warning-rgb), 0.05)' : 'rgba(var(--info-rgb), 0.05)';
    iconDiv.style.backgroundColor = isProd ? 'rgba(var(--warning-rgb), 0.1)' : 'rgba(var(--info-rgb), 0.1)';
    iconDiv.innerHTML             = `<i class="fas fa-server text-lg" style="color: ${isProd ? 'var(--warning)' : 'var(--info)'};"></i>`;
    titleEl.style.color           = isProd ? 'var(--warning)' : 'var(--info)';
    titleEl.textContent           = `${prov} Environment`;
    textEl.innerHTML = `
        <div class="flex items-center">
            <span class="font-medium">${env}</span>
            <span class="ml-2 px-2 py-1 text-xs rounded"
                  style="background-color: ${isProd ? 'rgba(var(--warning-rgb), 0.2)' : 'rgba(var(--info-rgb), 0.2)'}; color: ${isProd ? 'var(--warning)' : 'var(--info)'};">
                ${isProd ? '🚀 PRODUCTION' : '🧪 SANDBOX/TEST'}
            </span>
        </div>`;
    box.classList.remove('hidden');
}

// ============================================================================
// loadSystemStatus / updateSystemStatus
// ============================================================================
async function loadSystemStatus() {
    const container = document.getElementById('systemStatus');
    if (!container) return;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.status);
        if (data.success) updateSystemStatus(data.data.system);
        else throw new Error(data.message || 'Failed to load system status');
    } catch (error) {
        console.error('[SMS] loadSystemStatus failed:', error);
        container.innerHTML = `
            <div class="col-span-full text-center py-8">
                <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--danger);"></i>
                <p style="color: var(--danger);">Failed to load system status: ${error.message}</p>
                <button onclick="loadSystemStatus()" class="mt-2 px-4 py-2 rounded-lg"
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-redo mr-1"></i> Retry
                </button>
            </div>`;
    }
}

function updateSystemStatus(s) {
    const container = document.getElementById('systemStatus');
    if (!container || !s) return;

    const colors = { healthy: 'success', degraded: 'warning', offline: 'danger' };
    const icons  = { healthy: 'fa-check-circle', degraded: 'fa-exclamation-triangle', offline: 'fa-times-circle' };
    const c = colors[s.health_status] || 'info';
    const i = icons[s.health_status] || 'fa-info-circle';

    const globalSenderHtml = s.global_sender_id
        ? `<p class="text-sm font-mono font-medium mt-1" style="color: var(--text-primary);">${s.global_sender_id}</p>`
        : `<p class="text-sm italic mt-1" style="color: var(--text-secondary);">Not set</p>`;

    container.innerHTML = `
        <div class="card p-4" style="background-color: rgba(var(--${c}-rgb), 0.05); border-color: var(--${c});">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--${c});">System Health</p>
                    <p class="text-2xl font-bold capitalize" style="color: var(--text-primary);">${s.health_status || 'unknown'}</p>
                </div>
                <i class="fas ${i} text-xl" style="color: var(--${c});"></i>
            </div>
        </div>
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.05); border-color: var(--primary);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--primary);">Total Providers</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">${s.total_providers || 0}</p>
                </div>
                <i class="fas fa-layer-group text-xl" style="color: var(--primary);"></i>
            </div>
        </div>
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.05); border-color: var(--success);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--success);">Ready Providers</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">${s.ready_providers || 0}</p>
                </div>
                <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
            </div>
        </div>
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--info);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--info);">Default Provider</p>
                    <p class="text-lg font-bold capitalize" style="color: var(--text-primary);">${getProviderDisplayName(s.default_provider) || 'None'}</p>
                </div>
                <i class="fas fa-star text-xl" style="color: var(--info);"></i>
            </div>
        </div>
        <div class="card p-4 col-span-full md:col-span-2 lg:col-span-4" style="background-color: rgba(var(--secondary-rgb), 0.05); border-color: var(--secondary);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--secondary);">
                        <i class="fas fa-comment-dots mr-1"></i> Global SMS Sender ID (Admin-Set Fallback)
                    </p>
                    ${globalSenderHtml}
                </div>
                <i class="fas fa-comment-dots text-xl" style="color: var(--secondary);"></i>
            </div>
        </div>`;
}

// ============================================================================
// loadProviderStatus / updateStatusCards
// ============================================================================
async function loadProviderStatus() {
    const container = document.getElementById('providerStatusCards');
    if (!container) return;

    container.innerHTML = `
        <div class="col-span-full flex justify-center items-center py-8">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-xl mr-3" style="color: var(--primary);"></i>
                <span style="color: var(--text-secondary);">Loading SMS provider status...</span>
            </div>
        </div>`;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.status);
        if (data.success) {
            updateStatusCards(data.data.providers);
            updateDefaultProviderStatus(data.data.system);
        } else {
            throw new Error(data.message || 'Failed to load provider status');
        }
    } catch (error) {
        console.error('[SMS] loadProviderStatus failed:', error);
        container.innerHTML = `
            <div class="col-span-full p-6 rounded-lg"
                 style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--danger);"></i>
                    <p class="font-semibold mb-1" style="color: var(--danger);">Failed to load provider status</p>
                    <p class="text-xs mb-3" style="color: var(--text-secondary);">${error.message}</p>
                    <button onclick="loadProviderStatus()" class="px-4 py-2 rounded-lg"
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-redo mr-1"></i> Retry
                    </button>
                </div>
            </div>`;
    }
}

function updateStatusCards(statusData) {
    const container = document.getElementById('providerStatusCards');
    if (!container || !statusData) return;

    const providers = {
        'arkesel':        { name: 'Arkesel SMS',      icon: 'fa-comment-alt', color: 'primary' },
        'twilio':         { name: 'Twilio',           icon: 'fab fa-twilio',  color: 'danger'  },
        'africastalking': { name: "Africa's Talking", icon: 'fa-sms',         color: 'success' },
        'hubtel':         { name: 'Hubtel SMS',       icon: 'fa-comments',    color: 'info'    },
        'nalosolutions':  { name: 'Nalo Solutions',   icon: 'fa-mobile-alt',  color: 'warning' },
    };

    let html = '';
    Object.entries(providers).forEach(([key, p]) => {
        const s        = statusData[key] || { enabled: false, configured: false, missing_configuration: [] };
        const enabled  = !!s.enabled;
        const configured = !!s.configured;

        let text, color, icon, desc;
        if (!enabled)          { text = 'Disabled';                color = 'secondary'; icon = 'fa-times-circle';       desc = 'Provider is disabled'; }
        else if (!configured)  { text = 'Incomplete Configuration'; color = 'warning';   icon = 'fa-exclamation-circle'; desc = `Missing: ${(s.missing_configuration || []).join(', ') || 'Required configuration'}`; }
        else                   { text = 'Active & Connected';       color = 'success';   icon = 'fa-check-circle';       desc = 'Ready to send SMS'; }

        // Sender ID info from the enriched controller payload
        const senderId        = s.sender_id || s.effective_sender_id || null;
        const senderIdSource  = s.sender_id_source || 'fallback';

        let senderBadge = '';
        if (senderId) {
            const sourceLabel = senderIdSource === 'provider'
                ? 'Provider-specific'
                : (senderIdSource === 'global' ? 'Global default' : 'Fallback');
            const sourceColor = senderIdSource === 'provider'
                ? 'var(--primary)'
                : (senderIdSource === 'global' ? 'var(--info)' : 'var(--warning)');
            senderBadge = `
                <div class="text-xs mt-2">
                    <span style="color: var(--text-secondary);">Sender ID:</span>
                    <span class="font-mono font-medium ml-1" style="color: var(--text-primary);">${senderId}</span>
                    <span class="ml-1" style="color: ${sourceColor};">· ${sourceLabel}</span>
                </div>`;
        }

        // Role-aware buttons: developer sees full actions, others see read-only
        let actionButtons = '';
        if (window.isDeveloper) {
            actionButtons = `
                <div class="flex space-x-2">
                    <button class="test-connection text-xs px-3 py-1 rounded ${!enabled ? 'opacity-50 cursor-not-allowed' : ''}"
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                            data-provider="${key}" ${!enabled ? 'disabled' : ''}>
                        <i class="fas fa-plug mr-1"></i> Test
                    </button>
                    <button class="toggle-provider text-xs px-3 py-1 rounded"
                            style="background-color: ${enabled ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)'}; color: ${enabled ? 'var(--danger)' : 'var(--success)'};"
                            data-provider="${key}" data-enable="${!enabled}">
                        <i class="fas ${enabled ? 'fa-toggle-on' : 'fa-toggle-off'} mr-1"></i> ${enabled ? 'Disable' : 'Enable'}
                    </button>
                    <button class="reset-config text-xs px-3 py-1 rounded"
                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                            data-provider="${key}">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                </div>`;
        } else {
            actionButtons = `
                <div class="text-xs flex items-center" style="color: var(--text-secondary);">
                    <i class="fas fa-lock mr-1"></i> Read-only
                </div>`;
        }

        html += `
            <div class="card p-4 status-card ${!enabled ? 'inactive' : !configured ? 'pending' : 'active'}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <i class="${p.icon} text-xl mr-3" style="color: var(--${p.color});"></i>
                        <span class="font-semibold" style="color: var(--text-primary);">${p.name}</span>
                    </div>
                    <i class="fas ${icon} text-lg" style="color: var(--${color});"></i>
                </div>
                <div class="text-sm mb-2" style="color: var(--text-secondary);">
                    <div class="mb-1">Status: <span class="font-medium" style="color: var(--${color});">${text}</span></div>
                    <div class="text-xs">${desc}</div>
                    ${senderBadge}
                </div>
                ${actionButtons}
            </div>`;
    });
    container.innerHTML = html;
}

function updateDefaultProviderStatus(s) {
    const status = document.getElementById('defaultProviderStatus');
    const select = document.getElementById('defaultProvider');
    if (!status) return;

    const sys = s || {};

    if (sys.default_provider) {
        status.innerHTML = `
            <div class="flex items-center" style="color: var(--success);">
                <i class="fas fa-check-circle mr-2"></i>
                <span>Current default provider: <strong>${getProviderDisplayName(sys.default_provider)}</strong></span>
            </div>`;
        if (select) select.value = sys.default_provider;
    } else {
        status.innerHTML = `
            <div class="flex items-center" style="color: var(--warning);">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>No default provider set. System will use first available provider.</span>
            </div>`;
    }
}

// ============================================================================
// loadUsageStatistics / updateUsageStatistics
// ============================================================================
async function loadUsageStatistics() {
    const container = document.getElementById('usageStatistics');
    if (!container) return;

    container.innerHTML = `
        <div class="col-span-full flex justify-center items-center py-8">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-xl mr-3" style="color: var(--primary);"></i>
                <span style="color: var(--text-secondary);">Loading usage statistics...</span>
            </div>
        </div>`;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.usage);
        if (data.success) updateUsageStatistics(data.data);
        else throw new Error(data.message || 'Failed to load usage statistics');
    } catch (error) {
        console.error('[SMS] loadUsageStatistics failed:', error);
        container.innerHTML = `
            <div class="col-span-full text-center py-8">
                <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--danger);"></i>
                <p style="color: var(--danger);">Failed to load usage statistics: ${error.message}</p>
                <button onclick="loadUsageStatistics()" class="mt-2 px-4 py-2 rounded-lg"
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-redo mr-1"></i> Retry
                </button>
            </div>`;
    }
}

function updateUsageStatistics(stats) {
    const container = document.getElementById('usageStatistics');
    if (!container) return;
    const s = stats || {};

    const rate  = s.success_rate || 0;
    const color = rate >= 90 ? 'success' : rate >= 70 ? 'warning' : 'danger';

    container.innerHTML = `
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.05); border-color: var(--primary);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--primary);">Total SMS Sent</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">${s.total_sent || 0}</p>
                </div>
                <i class="fas fa-paper-plane text-xl" style="color: var(--primary);"></i>
            </div>
        </div>
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.05); border-color: var(--success);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--success);">Successful</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">${s.successful || 0}</p>
                </div>
                <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
            </div>
        </div>
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: var(--danger);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--danger);">Failed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">${s.failed || 0}</p>
                </div>
                <i class="fas fa-times-circle text-xl" style="color: var(--danger);"></i>
            </div>
        </div>
        <div class="card p-4" style="background-color: rgba(var(--${color}-rgb), 0.05); border-color: var(--${color});">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--${color});">Success Rate</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">${rate}%</p>
                </div>
                <i class="fas fa-chart-line text-xl" style="color: var(--${color});"></i>
            </div>
        </div>`;
}

// ============================================================================
// Action functions
// ============================================================================
async function testConnection(provider, button) {
    if (!button) return;
    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
    button.disabled = true;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.test, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider }),
        });
        showToast(data.success ? `✅ ${data.message}` : `❌ ${data.message}`, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => loadProviderStatus(), 800);
    } catch (error) {
        console.error('[SMS] testConnection failed:', error);
        showToast(`❌ ${error.message}`, 'error');
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

async function toggleProvider(provider, enable, button) {
    if (!button) return;
    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Updating...';
    button.disabled = true;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.toggle, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider, enable }),
        });
        showToast(data.success ? `✅ ${data.message}` : `❌ ${data.message}`, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => {
            loadProviderStatus();
            loadSystemStatus();
            verifyCurrentEnvironment();
        }, 800);
    } catch (error) {
        console.error('[SMS] toggleProvider failed:', error);
        showToast(`❌ ${error.message}`, 'error');
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

async function sendTestSMS(provider, phoneNumber, message, button) {
    if (!button) return;
    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
    button.disabled = true;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.sendTest, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider, phone_number: phoneNumber, message }),
        });
        showToast(data.success ? `✅ ${data.message}` : `❌ ${data.message}`, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => loadUsageStatistics(), 800);
    } catch (error) {
        console.error('[SMS] sendTestSMS failed:', error);
        showToast(`❌ ${error.message}`, 'error');
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

async function setDefaultProvider(provider, button) {
    if (!button) return;
    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Setting...';
    button.disabled = true;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.setDefault, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider }),
        });
        showToast(data.success ? `✅ ${data.message}` : `❌ ${data.message}`, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => {
            loadSystemStatus();
            updateDefaultProviderStatus(data.system_status || {});
        }, 500);
    } catch (error) {
        console.error('[SMS] setDefaultProvider failed:', error);
        showToast(`❌ ${error.message}`, 'error');
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

async function resetConfiguration(provider, button) {
    if (!confirm(`Reset ${getProviderDisplayName(provider)} configuration? This will disable the provider and clear all settings.`)) return;
    if (!button) return;

    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Resetting...';
    button.disabled = true;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.reset, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider }),
        });
        showToast(data.success ? `✅ ${data.message}` : `❌ ${data.message}`, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 1500);
    } catch (error) {
        console.error('[SMS] resetConfiguration failed:', error);
        showToast(`❌ ${error.message}`, 'error');
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

async function cleanupOldLogs(button) {
    if (!confirm('Clean up SMS logs older than 30 days? This cannot be undone.')) return;
    if (!button) return;

    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Cleaning...';
    button.disabled = true;

    try {
        const data = await window.smsFetch(window.smsProviderConfig.routes.cleanup, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
        });
        showToast(data.success ? `✅ ${data.message}` : `❌ ${data.message}`, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => loadUsageStatistics(), 800);
    } catch (error) {
        console.error('[SMS] cleanupOldLogs failed:', error);
        showToast(`❌ ${error.message}`, 'error');
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

// ============================================================================
// Helpers
// ============================================================================
function getProviderDisplayName(key) {
    return {
        'arkesel':        'Arkesel SMS',
        'twilio':         'Twilio',
        'africastalking': "Africa's Talking",
        'hubtel':         'Hubtel SMS',
        'nalosolutions':  'Nalo Solutions',
    }[key] || key;
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full';
    toast.style.backgroundColor = {
        success: 'var(--success)',
        error:   'var(--danger)',
        warning: 'var(--warning)',
        info:    'var(--info)',
    }[type] || 'var(--info)';

    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle'
                : type === 'error' ? 'fa-exclamation-circle'
                : 'fa-info-circle'
            } mr-2"></i>
            <span>${message}</span>
        </div>`;

    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.remove('translate-x-full'); toast.classList.add('translate-x-0'); }, 100);
    setTimeout(() => {
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.parentNode?.removeChild(toast), 300);
    }, 5000);
}
</script>

<style>
.form-select {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    padding: 12px 16px !important;
    font-size: 14px !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 16px center !important;
    background-size: 20px !important;
    padding-right: 48px !important;
}
[data-theme="dark"] .form-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23a0a0a0' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E") !important;
}
.form-select:focus { outline: none !important; border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important; }
.form-select:hover { border-color: var(--primary) !important; }
.form-select option { background-color: var(--bg-secondary) !important; color: var(--text-primary) !important; padding: 12px 16px !important; font-size: 14px !important; border: none !important; }
.form-select option:hover, .form-select option:focus, .form-select option:checked { background-color: var(--primary) !important; color: white !important; }
.relative .form-select { padding-right: 48px !important; }

.form-input, .form-textarea {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    padding: 12px 16px !important;
    font-size: 14px !important;
    transition: all 0.3s ease !important;
    width: 100% !important;
}
.form-input:focus, .form-textarea:focus { outline: none !important; border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important; }
.form-input:hover, .form-textarea:hover { border-color: var(--primary) !important; }

.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05) !important;
    transition: transform 0.3s, box-shadow 0.3s !important;
    overflow: hidden !important;
}
.card:hover { transform: translateY(-5px) !important; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important; }

.btn-modern {
    background: linear-gradient(to right, var(--primary), var(--secondary)) !important;
    color: white !important;
    border-radius: 10px !important;
    padding: 12px 24px !important;
    font-weight: 500 !important;
    border: none !important;
    box-shadow: 0 4px 6px rgba(114, 103, 240, 0.3) !important;
    transition: all 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
}
.btn-modern:hover { transform: translateY(-2px) !important; box-shadow: 0 8px 15px rgba(114, 103, 240, 0.4) !important; opacity: 0.95 !important; }
.btn-modern:active { transform: translateY(0) !important; }

.btn-secondary {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 10px !important;
    padding: 12px 24px !important;
    font-weight: 500 !important;
    transition: all 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
}
.btn-secondary:hover { background-color: rgba(var(--primary-rgb), 0.1) !important; border-color: var(--primary) !important; transform: translateY(-2px) !important; }

[role="tablist"] button { border-bottom: 2px solid transparent !important; transition: all 0.3s ease !important; color: var(--text-secondary) !important; background: none !important; border: none !important; outline: none !important; cursor: pointer !important; }
[role="tablist"] button.active { color: var(--primary) !important; border-bottom-color: var(--primary) !important; }
[role="tablist"] button:hover:not(.active) { color: var(--text-primary) !important; border-bottom-color: var(--border-color) !important; }

.tab-pane { animation: fadeIn 0.3s ease-in-out !important; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.status-card { transition: all 0.3s ease !important; border-left: 4px solid transparent !important; }
.status-card:hover { transform: translateY(-2px) !important; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important; }
.status-card.inactive { border-left-color: var(--secondary) !important; background-color: rgba(var(--secondary-rgb), 0.05) !important; }
.status-card.pending  { border-left-color: var(--warning) !important;   background-color: rgba(var(--warning-rgb), 0.05) !important; }
.status-card.active   { border-left-color: var(--success) !important;   background-color: rgba(var(--success-rgb), 0.05) !important; }

.fixed.top-4.right-4 { z-index: 9999 !important; }

.fa-spinner { animation: spin 1s linear infinite !important; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.form-select::-webkit-scrollbar { width: 8px !important; background-color: var(--bg-primary) !important; border-radius: 4px !important; }
.form-select::-webkit-scrollbar-thumb { background-color: var(--primary) !important; border-radius: 4px !important; }
.form-select::-webkit-scrollbar-thumb:hover { background-color: var(--secondary) !important; }

@media (max-width: 768px) {
    .form-select, .form-input, .form-textarea { padding: 10px 14px !important; font-size: 13px !important; padding-right: 40px !important; }
    .btn-modern, .btn-secondary { padding: 10px 20px !important; font-size: 13px !important; }
    [role="tablist"] { flex-wrap: nowrap !important; overflow-x: auto !important; white-space: nowrap !important; -webkit-overflow-scrolling: touch !important; }
    [role="tablist"] li { flex-shrink: 0 !important; }
    .grid-cols-1 { grid-template-columns: 1fr !important; }
    .md\:grid-cols-2, .lg\:grid-cols-4 { grid-template-columns: 1fr !important; }
}

::-webkit-scrollbar { width: 10px !important; height: 10px !important; }
::-webkit-scrollbar-track { background: var(--bg-primary) !important; border-radius: 5px !important; }
::-webkit-scrollbar-thumb { background: var(--primary) !important; border-radius: 5px !important; }
::-webkit-scrollbar-thumb:hover { background: var(--secondary) !important; }

::placeholder { color: var(--text-secondary) !important; opacity: 0.7 !important; }
:focus-visible { outline: 2px solid var(--primary) !important; outline-offset: 2px !important; }
button:disabled, select:disabled, input:disabled, textarea:disabled { opacity: 0.5 !important; cursor: not-allowed !important; }
[data-theme="dark"] ::placeholder { color: var(--text-secondary) !important; opacity: 0.5 !important; }
[data-theme="dark"] .form-select, [data-theme="dark"] .form-input, [data-theme="dark"] .form-textarea { background-color: var(--bg-secondary) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
[data-theme="dark"] .form-select:focus, [data-theme="dark"] .form-input:focus, [data-theme="dark"] .form-textarea:focus { box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2) !important; }
</style>
@endsection