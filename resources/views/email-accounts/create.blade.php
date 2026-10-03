{{-- email-accounts/create.blade.php --}}
@php
    $user = auth()->user();
    $isSuperAdmin = $user->isSuperAdmin();
    $isAdmin = $user->isAdmin();
    $isDeveloper = $user->isDeveloper();
    $isLandlord = $user->isLandlord();
    $isTenant = $user->isTenant();

    // Determine route prefix, URL path, and layout based on role
    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath = 'super-admin/email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Link Email Account - Super Admin';
        $roleBadge = 'Super Admin';
        $roleColor = '#8B5CF6';
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath = 'admin/email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Link Email Account - Admin';
        $roleBadge = 'Admin';
        $roleColor = '#3B82F6';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath = 'developer/email-accounts';
        $layout = 'layouts.dev';
        $pageTitle = 'Link Email Account - Developer';
        $roleBadge = 'Developer';
        $roleColor = '#10B981';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath = 'landlord/email-accounts';
        $layout = 'layouts.landlord';
        $pageTitle = 'Link Email Account';
        $roleBadge = 'Landlord';
        $roleColor = '#F59E0B';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath = 'tenant/email-accounts';
        $layout = 'layouts.tenant';
        $pageTitle = 'Link Email Account';
        $roleBadge = 'Tenant';
        $roleColor = '#EF4444';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath = 'email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Link Email Account';
        $roleBadge = 'User';
        $roleColor = '#6B7280';
    }

    $providers = $providers ?? [];
    $backRoute = route($routeNamePrefix . '.index');

    // Provider icons mapping
    $providerIcons = [
        'gmail' => [
            'icon' => 'fab fa-google',
            'color' => '#EA4335',
            'bg' => 'rgba(234, 67, 53, 0.1)',
            'text' => 'Gmail'
        ],
        'outlook' => [
            'icon' => 'fab fa-microsoft',
            'color' => '#0078D4',
            'bg' => 'rgba(0, 120, 212, 0.1)',
            'text' => 'Outlook'
        ],
        'yahoo' => [
            'icon' => 'fab fa-yahoo',
            'color' => '#6001D2',
            'bg' => 'rgba(96, 1, 210, 0.1)',
            'text' => 'Yahoo'
        ],
        'custom' => [
            'icon' => 'fas fa-envelope',
            'color' => '#6B7280',
            'bg' => 'rgba(107, 114, 128, 0.1)',
            'text' => 'Custom'
        ],
    ];

    // Provider features
    $providerFeatures = [
        'gmail' => [
            'features' => ['IMAP & SMTP', 'SSL/TLS', 'App Password Support'],
            'badge' => 'Google'
        ],
        'outlook' => [
            'features' => ['IMAP & SMTP', 'TLS', 'App Password Support'],
            'badge' => 'Microsoft'
        ],
        'yahoo' => [
            'features' => ['IMAP & SMTP', 'SSL/TLS', 'App Password Support'],
            'badge' => 'Yahoo'
        ],
        'custom' => [
            'features' => ['Custom Settings', 'Flexible Config'],
            'badge' => 'Manual'
        ],
    ];
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-link text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-link mr-2" style="color: var(--primary);"></i>
                        Link Email Account
                        <span class="ml-3 text-xs px-2 py-1 rounded-full"
                              style="background-color: {{ $roleColor }}20; color: {{ $roleColor }}; border: 1px solid {{ $roleColor }}40;">
                            {{ $roleBadge }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Connect your email to send and receive messages from your dashboard</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <a href="{{ $backRoute }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Accounts
                </a>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card max-w-4xl mx-auto">
        <div class="p-6">
            <form id="emailAccountForm" method="POST" action="{{ route($routeNamePrefix . '.store') }}" class="space-y-6">
                @csrf

                <!-- Provider Selection -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-cloud mr-1"></i> Select Email Provider
                    </label>

                    <!-- Provider Cards Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($providers as $key => $provider)
                        @php
                            $iconData = $providerIcons[$key] ?? $providerIcons['custom'];
                            $features = $providerFeatures[$key]['features'] ?? ['Email Support'];
                            $badge = $providerFeatures[$key]['badge'] ?? '';
                        @endphp
                        <button type="button"
                                onclick="selectProvider('{{ $key }}')"
                                id="provider-{{ $key }}"
                                class="provider-btn p-4 rounded-xl border-2 text-center transition-all duration-200 hover:scale-105 group"
                                style="border-color: var(--border-color); background-color: var(--card-bg); position: relative; overflow: hidden;">

                            <!-- Provider Badge -->
                            @if($badge)
                            <span class="absolute top-2 right-2 text-[10px] px-2 py-0.5 rounded-full"
                                  style="background-color: {{ $iconData['color'] }}20; color: {{ $iconData['color'] }};">
                                {{ $badge }}
                            </span>
                            @endif

                            <!-- Provider Icon -->
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2 transition-transform duration-200 group-hover:scale-110"
                                 style="background: {{ $iconData['bg'] }};">
                                <i class="{{ $iconData['icon'] }} text-2xl" style="color: {{ $iconData['color'] }};"></i>
                            </div>

                            <!-- Provider Name -->
                            <p class="text-sm font-semibold" style="color: var(--text-primary);">
                                {{ $iconData['text'] }}
                            </p>

                            <!-- Features -->
                            <div class="mt-2 space-y-0.5">
                                @foreach($features as $feature)
                                <p class="text-[10px]" style="color: var(--text-secondary);">
                                    <i class="fas fa-check-circle text-[8px] mr-1" style="color: var(--success);"></i>
                                    {{ $feature }}
                                </p>
                                @endforeach
                            </div>

                            <!-- Selection Indicator -->
                            <div class="absolute bottom-2 left-1/2 transform -translate-x-1/2 opacity-0 transition-opacity duration-200"
                                 id="check-{{ $key }}"
                                 style="color: var(--primary);">
                                <i class="fas fa-check-circle text-lg"></i>
                            </div>
                        </button>
                        @endforeach
                    </div>

                    <input type="hidden" name="provider" id="selectedProvider" value="">

                    <!-- Provider Help Text -->
                    <div id="providerHelp" class="mt-4 p-3 rounded-lg hidden"
                         style="background-color: rgba(var(--info-rgb), 0.08); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--info);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    <span id="providerHelpTitle"></span>
                                </p>
                                <p class="text-sm" style="color: var(--text-secondary);" id="providerHelpText"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email and Password -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-envelope mr-1"></i> Email Address <span class="text-red-500">*</span>
                        </label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email') }}"
                               class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                               style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                               placeholder="your.email@example.com"
                               required>
                        @error('email')
                        <p class="text-xs mt-1 text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-lock mr-1"></i> Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                   style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                   placeholder="Enter your email password"
                                   required>
                            <button type="button"
                                    onclick="togglePassword()"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2"
                                    style="color: var(--text-secondary);">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>

                        <!-- Password Help Text - Dynamic based on provider -->
                        <div id="passwordHelp" class="mt-2 hidden">
                            <div id="passwordHelpGmail" class="hidden text-xs p-2 rounded-lg" style="background-color: rgba(234, 67, 53, 0.08); color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1" style="color: #EA4335;"></i>
                                <span>For Gmail with 2FA enabled, use an <strong>App Password</strong>.
                                <a href="https://support.google.com/accounts/answer/185833" target="_blank"
                                   style="color: #EA4335; text-decoration: underline;">Learn how to generate one</a></span>
                            </div>
                            <div id="passwordHelpOutlook" class="hidden text-xs p-2 rounded-lg" style="background-color: rgba(0, 120, 212, 0.08); color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1" style="color: #0078D4;"></i>
                                <span>For Outlook with 2FA enabled, use an <strong>App Password</strong>.
                                <a href="https://support.microsoft.com/en-us/account-billing/using-app-passwords-with-apps-that-don-t-support-two-step-verification-5896ed9b-4263-e681-128a-a6f2979a7944" target="_blank"
                                   style="color: #0078D4; text-decoration: underline;">Learn how to generate one</a></span>
                            </div>
                            <div id="passwordHelpYahoo" class="hidden text-xs p-2 rounded-lg" style="background-color: rgba(96, 1, 210, 0.08); color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1" style="color: #6001D2;"></i>
                                <span>For Yahoo, you may need to use an <strong>App Password</strong> if 2FA is enabled.
                                <a href="https://help.yahoo.com/kb/generate-manage-third-party-passwords-sln15241.html" target="_blank"
                                   style="color: #6001D2; text-decoration: underline;">Learn how to generate one</a></span>
                            </div>
                            <div id="passwordHelpCustom" class="hidden text-xs p-2 rounded-lg" style="background-color: rgba(107, 114, 128, 0.08); color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1" style="color: #6B7280;"></i>
                                <span>Enter your email account password. If your provider requires an app-specific password, use that instead.</span>
                            </div>
                            <div id="passwordHelpGeneral" class="hidden text-xs p-2 rounded-lg" style="background-color: rgba(59, 130, 246, 0.08); color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1" style="color: #3B82F6;"></i>
                                <span>Enter your email account password. Some providers require an <strong>App Password</strong> if 2FA is enabled.</span>
                            </div>
                        </div>

                        @error('password')
                        <p class="text-xs mt-1 text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Display Name -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-1"></i> Display Name
                    </label>
                    <input type="text"
                           name="display_name"
                           value="{{ old('display_name', auth()->user()->name) }}"
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                           placeholder="Your display name for outgoing emails">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        This name will appear in the "From" field when you send emails.
                    </p>
                </div>

                <!-- Custom Settings (Hidden by default) -->
                <div id="customSettings" class="hidden space-y-4">
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> Custom Server Settings
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                Advanced
                            </span>
                        </h4>

                        <!-- IMAP Settings -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-server mr-1"></i> IMAP Host <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="imap_host"
                                       id="imap_host"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="imap.example.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-plug mr-1"></i> IMAP Port
                                </label>
                                <input type="number"
                                       name="imap_port"
                                       id="imap_port"
                                       value="993"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="993">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-lock mr-1"></i> IMAP Encryption
                                </label>
                                <select name="imap_encryption"
                                        id="imap_encryption"
                                        class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                        style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="ssl">SSL</option>
                                    <option value="tls">TLS</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                        </div>

                        <!-- SMTP Settings -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-server mr-1"></i> SMTP Host <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="smtp_host"
                                       id="smtp_host"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="smtp.example.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-plug mr-1"></i> SMTP Port
                                </label>
                                <input type="number"
                                       name="smtp_port"
                                       id="smtp_port"
                                       value="587"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="587">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-lock mr-1"></i> SMTP Encryption
                                </label>
                                <select name="smtp_encryption"
                                        id="smtp_encryption"
                                        class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 transition duration-200"
                                        style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sync Settings -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-sync mr-1"></i> Sync Frequency
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @php
                            $syncOptions = [
                                'realtime' => ['icon' => 'fa-bolt', 'label' => 'Real-time'],
                                'every_minute' => ['icon' => 'fa-clock', 'label' => 'Every Minute'],
                                'every_five_minutes' => ['icon' => 'fa-clock', 'label' => '5 Minutes'],
                                'every_fifteen_minutes' => ['icon' => 'fa-clock', 'label' => '15 Minutes'],
                                'every_thirty_minutes' => ['icon' => 'fa-clock', 'label' => '30 Minutes'],
                                'hourly' => ['icon' => 'fa-hourglass', 'label' => 'Hourly'],
                                'manual' => ['icon' => 'fa-hand', 'label' => 'Manual'],
                            ];
                        @endphp
                        @foreach($syncOptions as $value => $option)
                        <label class="flex items-center p-2 rounded-lg border cursor-pointer transition duration-200 hover:border-primary"
                               style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <input type="radio"
                                   name="sync_frequency"
                                   value="{{ $value }}"
                                   {{ $value === 'every_five_minutes' ? 'checked' : '' }}
                                   class="mr-2"
                                   style="accent-color: var(--primary);">
                            <span class="text-xs" style="color: var(--text-primary);">
                                <i class="fas {{ $option['icon'] }} mr-1"></i>
                                {{ $option['label'] }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        How often to check for new emails. More frequent sync uses more resources.
                    </p>
                </div>

                <!-- Set as Primary -->
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                    <input type="checkbox"
                           name="set_as_primary"
                           id="set_as_primary"
                           value="1"
                           {{ $emailAccounts->isEmpty() ? 'checked' : '' }}
                           class="w-4 h-4 rounded transition duration-200"
                           style="accent-color: var(--primary);">
                    <label for="set_as_primary" class="ml-2 text-sm" style="color: var(--text-primary);">
                        <i class="fas fa-star mr-1 text-yellow-500"></i>
                        Set as primary email account
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">
                            (This will be your default sending account)
                        </span>
                    </label>
                </div>

                <!-- Verification & Submit Buttons -->
                <div class="flex flex-wrap items-center gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                    <button type="button"
                            onclick="verifyCredentials()"
                            class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition duration-200 hover:scale-105"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-circle mr-2"></i> Verify Credentials
                    </button>
                    <span id="verificationStatus" class="text-sm" style="color: var(--text-secondary);"></span>
                    <div class="flex-1"></div>
                    <a href="{{ $backRoute }}"
                       class="px-4 py-2 rounded-lg font-medium transition duration-200"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-6 py-2 rounded-lg font-medium text-white transition duration-200 hover:scale-105"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-link mr-2"></i> Link Email Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verification Loading Overlay -->
<div id="verifyOverlay" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl p-8 text-center max-w-sm" style="background-color: var(--card-bg);">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-spinner fa-spin text-2xl text-white"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Verifying Credentials</h4>
            <p class="text-sm" style="color: var(--text-secondary);">Please wait while we verify your email credentials...</p>
            <div class="mt-4 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                <div class="h-2 rounded-full animate-pulse" style="width: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const providers       = @json($providers);
const routePrefix     = '{{ $routeNamePrefix }}';
const baseUrl         = '/{{ $urlPath }}';
const verifyUrl       = '{{ route($routeNamePrefix . '.verify') }}';
let selectedProviderKey = null;

// Provider configuration for auto-fill
const providerConfigs = {
    gmail: {
        imap_host: 'imap.gmail.com',
        imap_port: 993,
        imap_encryption: 'ssl',
        smtp_host: 'smtp.gmail.com',
        smtp_port: 465,
        smtp_encryption: 'ssl',
        helpText: 'Gmail uses IMAP and SMTP with SSL/TLS encryption. If you have 2FA enabled, you\'ll need to use an App Password.'
    },
    outlook: {
        imap_host: 'outlook.office365.com',
        imap_port: 993,
        imap_encryption: 'ssl',
        smtp_host: 'smtp.office365.com',
        smtp_port: 587,
        smtp_encryption: 'tls',
        helpText: 'Outlook/Hotmail uses IMAP and SMTP with TLS encryption. If you have 2FA enabled, you\'ll need to use an App Password.'
    },
    yahoo: {
        imap_host: 'imap.mail.yahoo.com',
        imap_port: 993,
        imap_encryption: 'ssl',
        smtp_host: 'smtp.mail.yahoo.com',
        smtp_port: 587,
        smtp_encryption: 'tls',
        helpText: 'Yahoo Mail uses IMAP and SMTP with SSL/TLS encryption. If you have 2FA enabled, you\'ll need to use an App Password.'
    }
};

function selectProvider(providerKey) {
    selectedProviderKey = providerKey;

    // Update UI
    document.querySelectorAll('.provider-btn').forEach(btn => {
        btn.style.borderColor = 'var(--border-color)';
        btn.style.backgroundColor = 'var(--card-bg)';
        const check = btn.querySelector('[id^="check-"]');
        if (check) {
            check.style.opacity = '0';
        }
    });

    const selectedBtn = document.getElementById(`provider-${providerKey}`);
    if (selectedBtn) {
        selectedBtn.style.borderColor = 'var(--primary)';
        selectedBtn.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        const check = document.getElementById(`check-${providerKey}`);
        if (check) {
            check.style.opacity = '1';
        }
    }

    // Set hidden input
    document.getElementById('selectedProvider').value = providerKey;

    // Show/hide custom settings
    const customSettings = document.getElementById('customSettings');
    const imapHost = document.getElementById('imap_host');
    const smtpHost = document.getElementById('smtp_host');

    if (providerKey === 'custom') {
        customSettings.classList.remove('hidden');
        imapHost.required = true;
        smtpHost.required = true;
    } else {
        customSettings.classList.add('hidden');
        imapHost.required = false;
        smtpHost.required = false;

        // Auto-fill settings from config
        const config = providerConfigs[providerKey];
        if (config) {
            document.getElementById('imap_host').value = config.imap_host || '';
            document.getElementById('imap_port').value = config.imap_port || 993;
            document.getElementById('imap_encryption').value = config.imap_encryption || 'ssl';
            document.getElementById('smtp_host').value = config.smtp_host || '';
            document.getElementById('smtp_port').value = config.smtp_port || 587;
            document.getElementById('smtp_encryption').value = config.smtp_encryption || 'tls';
        }
    }

    // Update help text
    updateProviderHelp(providerKey);
    updatePasswordHelp(providerKey);

    // Hide verification status
    document.getElementById('verificationStatus').textContent = '';
}

function updateProviderHelp(providerKey) {
    const helpDiv   = document.getElementById('providerHelp');
    const helpTitle = document.getElementById('providerHelpTitle');
    const helpText  = document.getElementById('providerHelpText');

    const providerNames = {
        'gmail': 'Gmail',
        'outlook': 'Outlook/Hotmail',
        'yahoo': 'Yahoo Mail',
        'custom': 'Custom Provider'
    };

    const providerHelps = {
        'gmail': 'Uses IMAP and SMTP with SSL/TLS encryption. If you have 2FA enabled, use an App Password.',
        'outlook': 'Uses IMAP and SMTP with TLS encryption. If you have 2FA enabled, use an App Password.',
        'yahoo': 'Uses IMAP and SMTP with SSL/TLS encryption. If you have 2FA enabled, use an App Password.',
        'custom': 'Enter your custom email server settings. Contact your email provider for the correct settings.'
    };

    if (providerKey && providerNames[providerKey]) {
        helpDiv.classList.remove('hidden');
        helpTitle.textContent = `📧 ${providerNames[providerKey]} Settings`;
        helpText.textContent = providerHelps[providerKey] || 'Configure your email provider settings.';
    } else {
        helpDiv.classList.add('hidden');
    }
}

function updatePasswordHelp(providerKey) {
    document.querySelectorAll('[id^="passwordHelp"]').forEach(el => {
        el.classList.add('hidden');
    });

    const passwordHelp = document.getElementById('passwordHelp');
    passwordHelp.classList.remove('hidden');

    const helpMap = {
        'gmail': 'passwordHelpGmail',
        'outlook': 'passwordHelpOutlook',
        'yahoo': 'passwordHelpYahoo',
        'custom': 'passwordHelpCustom'
    };

    const helpId = helpMap[providerKey];
    if (helpId) {
        document.getElementById(helpId).classList.remove('hidden');
    } else {
        document.getElementById('passwordHelpGeneral').classList.remove('hidden');
    }
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const icon = document.getElementById('passwordToggleIcon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function verifyCredentials() {
    try {
        const email    = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        let provider   = document.getElementById('selectedProvider').value;

        // Fallback: detect selected provider from the UI if hidden input is empty
        if (!provider) {
            const selectedCard = document.querySelector('.provider-btn[style*="border-color: var(--primary)"]');
            if (selectedCard) {
                const id = selectedCard.id;
                if (id && id.startsWith('provider-')) {
                    provider = id.replace('provider-', '');
                    document.getElementById('selectedProvider').value = provider;
                }
            }

            if (!provider) {
                provider = 'gmail';
                document.getElementById('selectedProvider').value = provider;
                selectProvider('gmail');
                showNotification('Auto-selected Gmail as default provider', 'info');
            }
        }

        if (!email || !password) {
            showNotification('Please enter email and password', 'error');
            return;
        }

        if (provider === 'custom') {
            const imapHost = document.getElementById('imap_host').value;
            const smtpHost = document.getElementById('smtp_host').value;
            if (!imapHost || !smtpHost) {
                showNotification('Please fill in IMAP and SMTP hosts', 'error');
                return;
            }
        }

        const overlay = document.getElementById('verifyOverlay');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', password);
        formData.append('provider', provider);

        if (provider === 'custom') {
            formData.append('imap_host', document.getElementById('imap_host').value);
            formData.append('imap_port', document.getElementById('imap_port').value);
            formData.append('imap_encryption', document.getElementById('imap_encryption').value);
            formData.append('smtp_host', document.getElementById('smtp_host').value);
            formData.append('smtp_port', document.getElementById('smtp_port').value);
            formData.append('smtp_encryption', document.getElementById('smtp_encryption').value);
        } else {
            const providerData = providers[provider];
            if (providerData) {
                formData.append('imap_host', providerData.imap_host);
                formData.append('imap_port', providerData.imap_port);
                formData.append('imap_encryption', providerData.imap_encryption);
                formData.append('smtp_host', providerData.smtp_host);
                formData.append('smtp_port', providerData.smtp_port);
                formData.append('smtp_encryption', providerData.smtp_encryption);
            } else {
                formData.append('imap_host', document.getElementById('imap_host').value || '');
                formData.append('imap_port', document.getElementById('imap_port').value || 993);
                formData.append('imap_encryption', document.getElementById('imap_encryption').value || 'ssl');
                formData.append('smtp_host', document.getElementById('smtp_host').value || '');
                formData.append('smtp_port', document.getElementById('smtp_port').value || 587);
                formData.append('smtp_encryption', document.getElementById('smtp_encryption').value || 'tls');
            }
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

        fetch(verifyUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    const cleanText = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');

                    let errorMessage = `HTTP ${response.status}`;
                    try {
                        const json = JSON.parse(cleanText);
                        if (json.message) errorMessage = json.message;
                        if (json.errors) {
                            const errorDetails = Object.entries(json.errors)
                                .map(([key, msgs]) => `${key}: ${msgs.join(', ')}`)
                                .join('; ');
                            errorMessage += ': ' + errorDetails;
                        }
                    } catch (e) {
                        if (cleanText) errorMessage += ': ' + cleanText.substring(0, 200);
                    }
                    throw new Error(errorMessage);
                });
            }
            return response.text().then(text => {
                const cleanText = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');
                try {
                    return JSON.parse(cleanText);
                } catch (e) {
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            overlay.classList.add('hidden');
            document.body.style.overflow = 'auto';

            const statusEl = document.getElementById('verificationStatus');

            if (data.success) {
                if (data.warning) {
                    statusEl.innerHTML = `
                        <span class="text-yellow-600 font-medium">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Partially Verified
                        </span>
                        <span class="text-xs ml-1" style="color: var(--text-secondary);">
                            (Check the warning for details)
                        </span>
                    `;
                    showNotification(data.warning, 'warning');
                } else {
                    statusEl.innerHTML = `
                        <span class="text-green-600 font-medium">
                            <i class="fas fa-check-circle mr-1"></i>
                            Verified successfully!
                        </span>
                    `;
                    showNotification('Credentials verified successfully!', 'success');
                }
            } else {
                let errorMsg = data.error || 'Verification failed. Please check your credentials.';

                if (errorMsg.toLowerCase().includes('authentication') ||
                    errorMsg.toLowerCase().includes('password') ||
                    errorMsg.toLowerCase().includes('login')) {
                    errorMsg += ' 💡 If you have 2FA enabled, try using an App Password instead.';
                }

                statusEl.innerHTML = `
                    <span class="text-red-600 font-medium">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Verification failed
                    </span>
                `;
                showNotification(errorMsg, 'error');
            }
        })
        .catch(error => {
            console.error('❌ Fetch error:', error);
            overlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
            showNotification('An error occurred during verification: ' + error.message, 'error');
        });
    } catch (error) {
        console.error('❌ Unexpected error:', error);
        document.getElementById('verifyOverlay').classList.add('hidden');
        document.body.style.overflow = 'auto';
        showNotification('An unexpected error occurred: ' + error.message, 'error');
    }
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    const colors = {
        success: { bg: '#22c55e', icon: 'fa-check-circle' },
        error:   { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' },
        info:    { bg: '#3b82f6', icon: 'fa-info-circle' }
    };
    const color = colors[type] || colors.info;

    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white`;
    notification.style.backgroundColor = color.bg;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${color.icon} mr-2 text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }

    .provider-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .provider-btn:active {
        transform: scale(0.95);
    }
`;
document.head.appendChild(style);

// Auto-select Gmail if no provider is selected on page load
document.addEventListener('DOMContentLoaded', function() {
    const providerInput = document.getElementById('selectedProvider');
    if (!providerInput.value) {
        selectProvider('gmail');
    }
});
</script>
@endsection