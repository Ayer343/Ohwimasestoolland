<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Enhanced Professional Logo Section -->
    <div class="logo-section">
        @php
            // Get system settings
            $systemSettings = \App\Models\SystemSetting::getSettings();
            $systemLogo = $systemSettings->system_logo ?? null;
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Developer');
            $systemName = $systemSettings->system_name ?? config('app.name', 'Laravel');

            // FIX: Ensure sidebarUnreadCount is always defined
            if (!isset($sidebarUnreadCount)) {
                $sidebarUnreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
            }

            // ============================================
            // COMMUNICATION STATISTICS
            // ============================================

            // Defaults — ensure all downstream markup is safe even on failure
            $smsProviderConfigured      = false;
            $whatsappProviderConfigured = false;
            $smsSystemStatus            = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
            $smsQuickStatus             = ['system_ready' => false, 'can_send_sms' => false];
            $smsProviders               = [];
            $smsUsageStats              = ['total_sent' => 0, 'successful' => 0, 'failed' => 0, 'success_rate' => 0, 'today' => 0, 'this_month' => 0];
            $totalSmsProviders          = 0;
            $whatsappSystemStatus       = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
            $whatsappQuickStatus        = ['system_ready' => false, 'can_send_whatsapp' => false];
            $whatsappProviders          = [];
            $totalWhatsAppProviders     = 0;
            $pendingSmsCount            = 0;

            // SMS Statistics
            try {
                $smsService = app(\App\Services\SmsService::class);
                $smsSystemStatus = $smsService->getSystemStatus();
                $smsQuickStatus = $smsService->getQuickStatus();
                $smsProviders = $smsService->getAllProvidersWithStatus();
                $smsUsageStats = $smsService->getUsageStatistics();
                $totalSmsProviders = count($smsProviders);

                // Determine whether at least one SMS provider is configured
                $smsProviderConfigured = (bool) (
                    ($smsSystemStatus['configured_providers'] ?? 0) > 0
                    || ($smsSystemStatus['enabled_providers'] ?? 0) > 0
                    || ($smsSystemStatus['can_send_sms'] ?? false)
                    || ($smsSystemStatus['system_ready'] ?? false)
                );
            } catch (\Exception $e) {
                $smsSystemStatus = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
                $smsQuickStatus = ['system_ready' => false, 'can_send_sms' => false];
                $smsProviders = [];
                $smsUsageStats = ['total_sent' => 0, 'successful' => 0, 'failed' => 0];
                $totalSmsProviders = 0;
                $smsProviderConfigured = false;
            }

            try {
                $pendingSmsCount = \App\Models\SmsLog::where('status', 'failed')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count();
            } catch (\Exception $e) {
                $pendingSmsCount = 0;
            }

            // WhatsApp Statistics
            try {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                $whatsappSystemStatus = $whatsappService->getSystemStatus();
                $whatsappQuickStatus = $whatsappService->getQuickStatus();
                $whatsappProviders = $whatsappService->getAllProvidersWithStatus();
                $totalWhatsAppProviders = count($whatsappProviders);

                // Determine whether at least one WhatsApp provider is configured
                $whatsappProviderConfigured = (bool) (
                    ($whatsappSystemStatus['configured'] ?? false)
                    || ($whatsappSystemStatus['enabled'] ?? false)
                    || ($whatsappSystemStatus['configured_providers'] ?? 0) > 0
                    || ($whatsappSystemStatus['enabled_providers'] ?? 0) > 0
                    || ($whatsappSystemStatus['can_send'] ?? false)
                    || ($whatsappSystemStatus['system_ready'] ?? false)
                );
            } catch (\Exception $e) {
                $whatsappSystemStatus = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
                $whatsappQuickStatus = ['system_ready' => false, 'can_send_whatsapp' => false];
                $whatsappProviders = [];
                $totalWhatsAppProviders = 0;
                $whatsappProviderConfigured = false;
            }

            $totalCommunicationProviders = $totalSmsProviders + $totalWhatsAppProviders;

            // Email Statistics
            $user = auth()->user();
            $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
            $totalEmailAccounts = $emailAccounts->count();
            $pendingEmailCount = $emailAccounts->where('status', 'pending')->count();
            $failedEmailCount = $emailAccounts->where('status', 'failed')->count();
            $totalEmailIssues = $pendingEmailCount + $failedEmailCount;

            $totalUnreadEmails = 0;
            foreach ($emailAccounts as $account) {
                $totalUnreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
            }

            // Notification count for sidebar
            $sidebarNotificationCount = $sidebarUnreadCount;
        @endphp

        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogo)
                    <div class="logo-image-container">
                        <img src="{{ Storage::url($systemLogo) }}"
                             alt="{{ $systemName }}"
                             class="logo-image"
                             onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                        <div id="logoFallback" class="logo-fallback" style="display: none;">
                            <i class="fas fa-code"></i>
                        </div>
                    </div>
                @else
                    <div class="logo-default">
                        <i class="fas fa-code"></i>
                    </div>
                @endif
            </div>

            <div class="logo-content">
                <div class="logo-text-container">
                    <div class="logo-shortname" style="color: var(--sidebar-text);" title="{{ $systemName }}">
                        {{ $systemShortName }}
                    </div>
                    @if($systemName && $systemName !== $systemShortName)
                    <div class="logo-fullname" style="color: var(--sidebar-text); opacity: 0.8;">
                        {{ $systemName }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="toggle-sidebar" id="toggleSidebarDesktop">
            <i class="fas fa-chevron-left"></i>
        </div>
    </div>

    <!-- Professional separator -->
    <div class="logo-separator"></div>

    <!-- Compact navigation with minimal gap -->
    <nav class="mt-2">
        <div class="nav-divider">
            <span class="menu-text">MAIN NAVIGATION</span>
        </div>

        <a href="{{ route('developer.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>

        <!-- ============================================ -->
        <!-- 💬 COMMUNICATION SECTION -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">COMMUNICATION</span>
        </div>

        <!-- Email Management Button with Modal -->
        <button id="emailManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('email-accounts.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
            <i class="fas fa-envelope mr-4"></i>
            <span class="nav-text flex-grow">Email Management</span>
            @if($totalEmailIssues > 0)
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $totalEmailIssues > 9 ? '9+' : $totalEmailIssues }}
                </span>
            @elseif($totalUnreadEmails > 0)
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $totalUnreadEmails > 9 ? '9+' : $totalUnreadEmails }}
                </span>
            @else
                <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
            @endif
        </button>

        <!-- SMS Management Button — only when a provider is configured -->
        @if($smsProviderConfigured)
        <button id="smsManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('sms.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
            <i class="fas fa-sms mr-4"></i>
            <span class="nav-text flex-grow">SMS Management</span>
            @if($pendingSmsCount > 0)
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $pendingSmsCount > 9 ? '9+' : $pendingSmsCount }}
                </span>
            @elseif(!($smsQuickStatus['system_ready'] ?? false))
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-[10px]"></i>
                </span>
            @else
                <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
            @endif
        </button>
        @endif

        <!-- WhatsApp Management Button — only when a provider is configured -->
        @if($whatsappProviderConfigured)
        <button id="whatsappManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('whatsapp.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
            <i class="fab fa-whatsapp mr-4"></i>
            <span class="nav-text flex-grow">WhatsApp Management</span>
            @if(!($whatsappQuickStatus['system_ready'] ?? false))
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-[10px]"></i>
                </span>
            @else
                <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
            @endif
        </button>
        @endif

        <!-- Communication Providers (Combined) Button with Modal — always visible so admins can configure when nothing is set up -->
        <button id="communicationManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{
            request()->routeIs('admin.sms-providers.*') ||
            request()->routeIs('admin.whatsapp-providers.*') ? 'active' : ''
        }}" style="color: var(--sidebar-text);">
            <i class="fas fa-plug mr-4"></i>
            <span class="nav-text flex-grow">Communication Providers</span>
            @if($totalCommunicationProviders > 0)
                <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $totalCommunicationProviders }}
                </span>
            @else
                <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
            @endif
        </button>

        <!-- ============================================ -->
        <!-- ⚙️ SETTINGS & MANAGEMENT -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">SYSTEM SETTINGS</span>
        </div>

        <!-- System Settings -->
        <a href="{{ route('developer.settings.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.settings.*') ? 'active' : '' }}">
            <i class="fas fa-cog mr-4"></i>
            <span class="nav-text">System Settings</span>
        </a>

        <!-- Super Admins Management -->
        <a href="{{ route('developer.super-admins.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.super-admins.*') ? 'active' : '' }}">
            <i class="fas fa-users-cog mr-4"></i>
            <span class="nav-text">Super Admins</span>
            @php
                $pendingSuperAdmins = \App\Models\User::where('type', \App\Models\User::TYPE_SUPER_ADMIN)
                    ->where('status', \App\Models\User::STATUS_PENDING)
                    ->where('created_by', auth()->id())
                    ->count();
            @endphp
            @if($pendingSuperAdmins > 0)
                <span class="ml-auto bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                    {{ $pendingSuperAdmins }}
                </span>
            @endif
        </a>

        <!-- Payment Providers -->
        <a href="{{ route('developer.payment-providers.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.payment-providers.*') ? 'active' : '' }}">
            <i class="fas fa-credit-card mr-4"></i>
            <span class="nav-text">Payment Providers</span>
        </a>

        <!-- ============================================ -->
        <!-- 🔔 NOTIFICATIONS -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">NOTIFICATIONS</span>
        </div>

        <a href="{{ route('developer.notifications.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('*.notifications.*') ? 'active' : '' }}" id="sidebarNotificationsLink">
            <i class="fas fa-bell mr-4"></i>
            <span class="nav-text">Notifications</span>
            @if($sidebarNotificationCount > 0)
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $sidebarNotificationCount > 9 ? '9+' : $sidebarNotificationCount }}
                </span>
            @else
                <span class="ml-auto" id="sidebarNotificationBadge" style="display: none;">
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"></span>
                </span>
            @endif
        </a>

        <!-- ============================================ -->
        <!-- 🛠️ SYSTEM TOOLS -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">SYSTEM TOOLS</span>
        </div>

        <a href="{{ route('developer.tools.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.tools.*') ? 'active' : '' }}">
            <i class="fas fa-toolbox mr-4"></i>
            <span class="nav-text">System Tools</span>
        </a>

        <!-- Emergency Mode -->
        <a href="{{ route('developer.emergency.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.emergency.*') ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle mr-4"></i>
            <span class="nav-text">Emergency Mode</span>
            @php
                $activeEmergencies = \App\Models\EmergencyMode::active()->count();
            @endphp
            @if($activeEmergencies > 0)
                <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                    {{ $activeEmergencies }}
                </span>
            @endif
        </a>

        <!-- Maintenance -->
        <a href="{{ route('developer.maintenance.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.maintenance.*') ? 'active' : '' }}">
            <i class="fas fa-tools mr-4"></i>
            <span class="nav-text">Maintenance</span>
            @php
                $activeMaintenance = \App\Models\Maintenance::active()->count();
                $upcomingMaintenance = \App\Models\Maintenance::upcoming()->where('scheduled_start', '<=', now()->addHours(24))->count();
            @endphp
            @if($activeMaintenance > 0 || $upcomingMaintenance > 0)
                <span class="ml-auto bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                    {{ $activeMaintenance + $upcomingMaintenance }}
                </span>
            @endif
        </a>

        <!-- System Health -->
        @if(class_exists('App\Models\SystemHealth'))
        <a href="{{ route('developer.system-health') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.system-health') ? 'active' : '' }}">
            <i class="fas fa-heartbeat mr-4"></i>
            <span class="nav-text">System Health</span>
        </a>
        @endif
    </nav>

    <!-- Settings Button - Compact positioning -->
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
</div>

<!-- ============================================ -->
<!-- 📧 EMAIL MANAGEMENT MODAL -->
<!-- ============================================ -->
<div id="emailManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="email-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1200px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-3" style="color: var(--primary);"></i>
                    Email Account Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage all your email accounts and email-related operations
                </p>
            </div>
            <button id="closeEmailModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            @php
                $user = auth()->user();
                $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
                $totalAccounts = $emailAccounts->count();
                $verifiedAccounts = $emailAccounts->where('status', 'verified')->count();
                $pendingAccounts = $emailAccounts->where('status', 'pending')->count();
                $failedAccounts = $emailAccounts->where('status', 'failed')->count();
                $primaryAccount = $user ? $user->primaryEmailAccount : null;
                $totalEmails = 0;
                $unreadEmails = 0;
                foreach ($emailAccounts as $account) {
                    $totalEmails += $account->emails()->count();
                    $unreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                }
            @endphp

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Accounts</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $verifiedAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Verified</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $pendingAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $failedAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Failed</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalEmails }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Emails</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $unreadEmails }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Unread</div>
                </div>
            </div>

            <!-- Primary Account Info -->
            @if($primaryAccount)
                <div class="mb-6 p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--secondary-rgb), 0.05) 100%); border: 1px solid var(--primary);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-2 h-2 rounded-full bg-green-500 mr-3"></div>
                            <div>
                                <span class="text-sm font-semibold" style="color: var(--text-primary);">Primary Account:</span>
                                <span class="text-sm ml-2" style="color: var(--text-secondary);">{{ $primaryAccount->email }}</span>
                                <span class="text-xs ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                    <i class="fas fa-check-circle mr-1"></i>{{ ucfirst($primaryAccount->status) }}
                                </span>
                            </div>
                        </div>
                        <a href="{{ route('email-accounts.show', $primaryAccount) }}"
                           class="text-sm px-3 py-1 rounded-lg transition-colors duration-200"
                           style="background-color: var(--primary); color: white;"
                           onclick="closeEmailModal()">
                            <i class="fas fa-eye mr-1"></i> View
                        </a>
                    </div>
                </div>
            @endif

            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <a href="{{ route('email-accounts.create') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                            <i class="fas fa-plus text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Link Account</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Add new email</div>
                        </div>
                    </a>

                    <a href="{{ route('email-accounts.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-list text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">All Accounts</div>
                            <div class="text-xs" style="color: var(--text-secondary);">View all linked</div>
                        </div>
                    </a>

                    <a href="{{ route('email-accounts.inbox') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #22c55e, #4ade80);">
                            <i class="fas fa-inbox text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Inbox</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $unreadEmails > 0 ? $unreadEmails . ' unread' : 'All read' }}
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('email-accounts.compose') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-pen text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Compose</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Write new email</div>
                        </div>
                    </a>

                    <a href="{{ route('email-accounts.sent') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                            <i class="fas fa-paper-plane text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Sent</div>
                            <div class="text-xs" style="color: var(--text-secondary);">View sent emails</div>
                        </div>
                    </a>

                    <button onclick="syncAllEmails()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-sync text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Sync All</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Fetch new emails</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Email Accounts List -->
            @if($totalAccounts > 0)
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i>Your Email Accounts
                        <span class="text-xs ml-2 px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            {{ $totalAccounts }}
                        </span>
                    </h4>
                    <div class="space-y-2">
                        @foreach($emailAccounts as $account)
                            @php
                                $accountUnread = $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                                $accountTotal = $account->emails()->count();
                            @endphp
                            <div class="account-item flex items-center justify-between p-3 rounded-lg transition-all duration-200 hover:bg-opacity-10"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                         style="background: linear-gradient(135deg, {{ $account->is_primary ? 'var(--primary)' : '#6b7280' }}, {{ $account->is_primary ? 'var(--secondary)' : '#9ca3af' }});">
                                        <i class="fas fa-envelope text-white text-xs"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                                {{ $account->display_name ?? $account->email }}
                                            </span>
                                            @if($account->is_primary)
                                                <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded-full" style="background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                                    <i class="fas fa-star mr-0.5"></i>Primary
                                                </span>
                                            @endif
                                            <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded-full {{ $account->status === 'verified' ? 'bg-green-100 text-green-700' : ($account->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                                {{ ucfirst($account->status) }}
                                            </span>
                                        </div>
                                        <div class="text-xs truncate" style="color: var(--text-secondary);">
                                            {{ $account->email }}
                                            <span class="mx-1">•</span>
                                            {{ $account->provider }}
                                            @if($accountUnread > 0)
                                                <span class="ml-2 px-1.5 py-0.5 rounded-full text-[10px]" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                                    {{ $accountUnread }} unread
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1 flex-shrink-0 ml-2">
                                    @if(!$account->is_primary && $account->status === 'verified')
                                        <button onclick="setPrimaryAccount('{{ $account->id }}')"
                                                class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                                style="color: var(--text-secondary);"
                                                title="Set as primary">
                                            <i class="fas fa-star text-xs"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('email-accounts.show', $account) }}"
                                       class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                       style="color: var(--text-secondary);"
                                       onclick="closeEmailModal()"
                                       title="View account">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                    <a href="{{ route('email-accounts.edit', $account) }}"
                                       class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                       style="color: var(--text-secondary);"
                                       onclick="closeEmailModal()"
                                       title="Edit account">
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <button onclick="syncAccount('{{ $account->id }}')"
                                            class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                            style="color: var(--text-secondary);"
                                            title="Sync emails">
                                        <i class="fas fa-sync text-xs"></i>
                                    </button>
                                    <button onclick="deleteAccount('{{ $account->id }}', '{{ $account->email }}')"
                                            class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                            style="color: var(--text-secondary);"
                                            title="Delete account">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-envelope-open text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Email Accounts Linked</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">You haven't linked any email accounts yet.</p>
                    <a href="{{ route('email-accounts.create') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
                       style="background-color: var(--primary); color: white;"
                       onclick="closeEmailModal()">
                        <i class="fas fa-plus mr-2"></i>Link Your First Account
                    </a>
                </div>
            @endif
        </div>

        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <a href="{{ route('email-accounts.index') }}"
                   class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                   style="background-color: var(--primary); color: white;"
                   onclick="closeEmailModal()">
                    <i class="fas fa-arrow-right mr-1"></i> Manage All Accounts
                </a>
            </div>
            <button id="cancelEmailModal"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- 📱 SMS MANAGEMENT MODAL — only when a provider is configured -->
<!-- ============================================ -->
@if($smsProviderConfigured)
<div id="smsManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="sms-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1200px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-sms mr-3" style="color: var(--primary);"></i>
                    SMS Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage SMS providers, send messages, and monitor SMS activity
                </p>
            </div>
            <button id="closeSmsModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            <!-- System Status -->
            @if($smsQuickStatus['system_ready'] ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e;">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">SMS System Operational</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $smsSystemStatus['status_message'] ?? 'SMS service is ready to send messages' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">SMS System Not Ready</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $smsSystemStatus['status_message'] ?? 'Please configure an SMS provider' }}</p>
                            <a href="{{ route('admin.sms-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: var(--primary); color: white;" onclick="closeSmsModal()">
                                <i class="fas fa-cog mr-1"></i> Configure Providers
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $smsUsageStats['total_sent'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Sent</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $smsUsageStats['successful'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Successful</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $smsUsageStats['failed'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Failed</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $smsUsageStats['success_rate'] ?? 0 }}%</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Success Rate</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $smsUsageStats['today'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Today</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $smsUsageStats['this_month'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">This Month</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button onclick="openSmsCompose()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                            <i class="fas fa-pen text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Send SMS</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Compose new message</div>
                        </div>
                    </button>

                    <a href="{{ route('sms.logs') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeSmsModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-history text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">View Logs</div>
                            <div class="text-xs" style="color: var(--text-secondary);">SMS history</div>
                        </div>
                    </a>

                    <a href="{{ route('admin.sms-providers.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeSmsModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-cog text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Providers</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Configure SMS providers</div>
                        </div>
                    </a>

                    <button onclick="testSmsConnection()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Test Connection</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Verify SMS provider</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Provider Status -->
            @if(!empty($smsProviders))
                <div class="mb-6">
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Provider Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($smsProviders as $key => $provider)
                            <div class="provider-card p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'var(--success)' : 'var(--border-color)' }};">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 rounded-full mr-2 {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $provider['name'] ?? $key }}</span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'Ready' : (($provider['enabled'] ?? false) ? 'Misconfigured' : 'Disabled') }}
                                    </span>
                                </div>
                                @if(isset($provider['missing_configuration']) && !empty($provider['missing_configuration']))
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Missing: {{ implode(', ', $provider['missing_configuration']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Recent SMS Activity -->
            @php
                $recentSms = [];
                try {
                    $recentSms = \App\Models\SmsLog::orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                } catch (\Exception $e) {
                    $recentSms = [];
                }
            @endphp

            @if($recentSms->isNotEmpty())
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Activity
                    </h4>
                    <div class="space-y-2">
                        @foreach($recentSms as $log)
                            <div class="flex items-center justify-between p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0 {{ $log->status === 'success' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm truncate" style="color: var(--text-primary);">
                                            {{ Str::limit($log->message ?? 'No message', 50) }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <span>{{ $log->provider ?? 'unknown' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ $log->phone_number ?? 'unknown' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center flex-shrink-0 ml-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $log->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ucfirst($log->status ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <button onclick="refreshSmsStatus()" class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-sync mr-1"></i> Refresh Status
                </button>
            </div>
            <button id="cancelSmsModal"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<!-- ============================================ -->
<!-- 💬 WHATSAPP MANAGEMENT MODAL — only when a provider is configured -->
<!-- ============================================ -->
@if($whatsappProviderConfigured)
<div id="whatsappManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="whatsapp-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1200px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fab fa-whatsapp mr-3" style="color: #25D366;"></i>
                    WhatsApp Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage WhatsApp Business API providers, send messages, and monitor activity
                </p>
            </div>
            <button id="closeWhatsAppModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            <!-- System Status -->
            @if($whatsappQuickStatus['system_ready'] ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(37, 211, 102, 0.1); border: 1px solid #25D366;">
                    <div class="flex items-center">
                        <i class="fab fa-whatsapp text-green-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">WhatsApp System Operational</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $whatsappSystemStatus['status_message'] ?? 'WhatsApp service is ready to send messages' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">WhatsApp System Not Ready</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $whatsappSystemStatus['status_message'] ?? 'Please configure a WhatsApp provider' }}</p>
                            <a href="{{ route('admin.whatsapp-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: #25D366; color: white;" onclick="closeWhatsAppModal()">
                                <i class="fab fa-whatsapp mr-1"></i> Configure Providers
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #25D366;">{{ $totalWhatsAppProviders }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Providers</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $totalWhatsAppProviders > 0 ? 1 : 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Active Providers</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $totalWhatsAppProviders > 0 ? 0 : 1 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Pending Setup</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Messages Sent</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <!-- Send Message -->
                    <button onclick="openWhatsAppCompose()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                            <i class="fab fa-whatsapp text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Send Message</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Compose new WhatsApp</div>
                        </div>
                    </button>

                    <!-- View Logs -->
                    <a href="{{ route('developer.whatsapp.logs.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeWhatsAppModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-history text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">View Logs</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Message history</div>
                        </div>
                    </a>

                    <!-- Providers -->
                    <a href="{{ route('admin.whatsapp-providers.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeWhatsAppModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-cog text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Providers</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Configure WhatsApp</div>
                        </div>
                    </a>

                    <!-- Test Connection -->
                    <button onclick="testWhatsAppConnection()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Test Connection</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Verify WhatsApp provider</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Provider Status -->
            @if(!empty($whatsappProviders))
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Provider Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($whatsappProviders as $key => $provider)
                            <div class="provider-card p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'var(--success)' : 'var(--border-color)' }};">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 rounded-full mr-2 {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $provider['name'] ?? $key }}</span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'Ready' : (($provider['enabled'] ?? false) ? 'Misconfigured' : 'Disabled') }}
                                    </span>
                                </div>
                                @if(isset($provider['missing_configuration']) && !empty($provider['missing_configuration']))
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Missing: {{ implode(', ', $provider['missing_configuration']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <button onclick="refreshWhatsAppStatus()" class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                        style="background-color: #25D366; color: white;">
                    <i class="fas fa-sync mr-1"></i> Refresh Status
                </button>
            </div>
            <button id="cancelWhatsAppModal"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<!-- ============================================ -->
<!-- 🔌 COMMUNICATION PROVIDERS MODAL (Combined) -->
<!-- ============================================ -->
<div id="communicationManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="communication-modal"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;">

        <div class="modal-header flex justify-between items-center p-6 border-b"
             style="border-color: var(--border-color);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-plug mr-3" style="color: var(--primary);"></i>
                Communication Providers
            </h3>
            <button id="closeCommunicationModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6">
            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                Manage all communication providers for your system including SMS and WhatsApp.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- SMS Providers Section -->
                <div class="communication-section">
                    <div class="section-header flex items-center mb-4">
                        <div class="section-icon mr-3" style="color: var(--info);">
                            <i class="fas fa-sms text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="section-title" style="color: var(--text-primary);">SMS Providers</h4>
                            <p class="section-subtitle text-xs" style="color: var(--text-secondary);">
                                Configure SMS gateways for text messaging
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a href="{{ route('admin.sms-providers.index') }}"
                           class="communication-option-card group {{ request()->routeIs('admin.sms-providers.*') ? 'active' : '' }}"
                           onclick="closeCommunicationModal()">
                            <div class="communication-icon-container" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.1) 0%, rgba(var(--primary-rgb), 0.1) 100%);">
                                <i class="fas fa-tachometer-alt" style="color: var(--info);"></i>
                            </div>
                            <div class="communication-content">
                                <h4 class="communication-title" style="color: var(--text-primary);">SMS Dashboard</h4>
                                <p class="communication-description" style="color: var(--text-secondary);">
                                    Manage all SMS providers and settings
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        {{ $totalSmsProviders }} providers
                                    </span>
                                    @if($smsProviderConfigured)
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                            <i class="fas fa-check-circle mr-0.5"></i>Active
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="communication-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>

                        <div class="quick-actions-grid">
                            <a href="{{ route('admin.sms-providers.index') }}"
                               class="quick-action-btn-sm"
                               onclick="closeCommunicationModal()">
                                <i class="fas fa-cog mr-2"></i>Configure SMS
                            </a>
                            <a href="{{ route('admin.sms-providers.index') }}#test-connection"
                               class="quick-action-btn-sm"
                               onclick="closeCommunicationModal()">
                                <i class="fas fa-wifi mr-2"></i>Test SMS
                            </a>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Providers Section -->
                <div class="communication-section">
                    <div class="section-header flex items-center mb-4">
                        <div class="section-icon mr-3" style="color: #25D366;">
                            <i class="fab fa-whatsapp text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="section-title" style="color: var(--text-primary);">WhatsApp Providers</h4>
                            <p class="section-subtitle text-xs" style="color: var(--text-secondary);">
                                Configure WhatsApp Business API
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a href="{{ route('admin.whatsapp-providers.index') }}"
                           class="communication-option-card group {{ request()->routeIs('admin.whatsapp-providers.*') ? 'active' : '' }}"
                           onclick="closeCommunicationModal()">
                            <div class="communication-icon-container" style="background: linear-gradient(135deg, rgba(37, 211, 102, 0.1) 0%, rgba(var(--primary-rgb), 0.1) 100%);">
                                <i class="fas fa-tachometer-alt" style="color: #25D366;"></i>
                            </div>
                            <div class="communication-content">
                                <h4 class="communication-title" style="color: var(--text-primary);">WhatsApp Dashboard</h4>
                                <p class="communication-description" style="color: var(--text-secondary);">
                                    Manage all WhatsApp providers and settings
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(37, 211, 102, 0.1); color: #25D366;">
                                        {{ $totalWhatsAppProviders }} providers
                                    </span>
                                    @if($whatsappProviderConfigured)
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                            <i class="fas fa-check-circle mr-0.5"></i>Active
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="communication-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>

                        <div class="quick-actions-grid">
                            <a href="{{ route('admin.whatsapp-providers.index') }}"
                               class="quick-action-btn-sm"
                               onclick="closeCommunicationModal()">
                                <i class="fas fa-cog mr-2"></i>Configure WhatsApp
                            </a>
                            <a href="{{ route('admin.whatsapp-providers.index') }}#test-connection"
                               class="quick-action-btn-sm"
                               onclick="closeCommunicationModal()">
                                <i class="fas fa-wifi mr-2"></i>Test WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Combined Statistics -->
            <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <h4 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2"></i>Communication Statistics
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-sms"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" style="color: var(--text-primary);">{{ $totalSmsProviders }}</div>
                            <div class="stat-label" style="color: var(--text-secondary);">SMS Providers</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(37, 211, 102, 0.1); color: #25D366;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" style="color: var(--text-primary);">{{ $totalWhatsAppProviders }}</div>
                            <div class="stat-label" style="color: var(--text-secondary);">WhatsApp Providers</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" style="color: var(--text-primary);">{{ $totalCommunicationProviders }}</div>
                            <div class="stat-label" style="color: var(--text-secondary);">Total Providers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer p-6 border-t flex justify-end"
             style="border-color: var(--border-color);
                    background-color: var(--bg-secondary);">
            <button id="cancelCommunicationModal"
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Theme Settings Modal -->
<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="theme-modal-compact">
        <div class="theme-modal-header-compact flex justify-between items-center">
            <h3 class="text-base font-semibold">
                <i class="fas fa-palette mr-2"></i>Theme Settings
            </h3>
            <button id="closeThemeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="theme-modal-body-compact">
            <div class="mb-6">
                <h4 class="section-header-compact">Appearance</h4>
                <p class="section-description-compact">Choose how the application looks</p>

                <div class="appearance-grid-compact">
                    <div class="appearance-option-compact relative" data-theme="light">
                        <div class="appearance-icon-compact bg-white border border-gray-300">
                            <i class="fas fa-sun text-yellow-500"></i>
                        </div>
                        <span class="appearance-label-compact">Light</span>
                    </div>

                    <div class="appearance-option-compact relative" data-theme="dark">
                        <div class="appearance-icon-compact bg-gray-800 border border-gray-700">
                            <i class="fas fa-moon text-blue-300"></i>
                        </div>
                        <span class="appearance-label-compact">Dark</span>
                    </div>

                    <div class="appearance-option-compact relative" data-theme="system">
                        <div class="appearance-icon-compact bg-gradient-to-r from-gray-100 to-gray-800 border border-gray-300">
                            <i class="fas fa-desktop text-gray-600"></i>
                        </div>
                        <span class="appearance-label-compact">System</span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h4 class="section-header-compact">Sidebar Theme</h4>
                <p class="section-description-compact">Customize your sidebar appearance</p>

                <div class="theme-grid-compact">
                    <div class="theme-option-compact relative" data-theme="default">
                        <div class="theme-preview-compact bg-gradient-to-br from-purple-500 to-purple-600"></div>
                        <span class="theme-label-compact">Default</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="dark">
                        <div class="theme-preview-compact bg-gradient-to-br from-gray-800 to-gray-900"></div>
                        <span class="theme-label-compact">Dark</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="light">
                        <div class="theme-preview-compact bg-gradient-to-br from-white to-gray-100 border border-gray-200"></div>
                        <span class="theme-label-compact">Light</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="blue">
                        <div class="theme-preview-compact bg-gradient-to-br from-blue-600 to-blue-800"></div>
                        <span class="theme-label-compact">Blue</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="green">
                        <div class="theme-preview-compact bg-gradient-to-br from-green-600 to-green-800"></div>
                        <span class="theme-label-compact">Green</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="theme-modal-footer-compact flex justify-end">
            <button id="closeThemeModalBtn" class="px-3 py-1.5 text-sm font-medium transition-colors duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden pt-20">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl">
        <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-search mr-2"></i>Advanced Search
            </h3>
            <button id="closeSearchModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Enter search term..."
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Category
                </label>
                <select id="searchCategory" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="all">All Categories</option>
                    <option value="users">Users</option>
                    <option value="properties">Properties</option>
                    <option value="payments">Payments</option>
                    <option value="invoices">Invoices</option>
                    <option value="registration-plans">Registration Plans</option>
                    <option value="sms-providers">SMS Providers</option>
                    <option value="whatsapp-providers">WhatsApp Providers</option>
                    <option value="payment-providers">Payment Providers</option>
                    <option value="system-settings">System Settings</option>
                    <option value="email-accounts">Email Accounts</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Filters
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterExactMatch" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Exact Match</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterCaseSensitive" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Case Sensitive</span>
                        </label>
                    </div>
                </div>
            </div>

            <div id="recentSearches" class="hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Recent Searches
                </label>
                <div id="recentSearchesList" class="flex flex-wrap gap-2">
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex justify-between">
            <button id="clearSearch" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                Clear
            </button>
            <div class="flex space-x-3">
                <button id="cancelSearch" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                    Cancel
                </button>
                <button id="performSearch" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notifications Dropdown Panel -->
<div id="notificationsDropdown" class="notifications-dropdown hidden">
    <div class="notifications-header">
        <h3>Notifications</h3>
        <div class="notifications-actions">
            <button id="markAllReadBtn" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                Mark all as read
            </button>
            <button id="clearAllNotificationsBtn" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">
                Clear all
            </button>
        </div>
    </div>
    <div id="notificationsList" class="notifications-list">
        <div class="notifications-loading">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
    </div>
    <div class="notifications-footer">
        <a href="{{ route('developer.notifications.index') }}" class="view-all-link">
            View all notifications →
        </a>
    </div>
</div>

<!-- Main Content Header -->
<div class="content" id="mainContent">
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button id="toggleSidebarMobile" class="mobile-menu-btn mr-4 text-gray-600">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">@yield('title', 'Dashboard')</h2>
        </div>

        <div class="flex items-center space-x-4">
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        <input type="text" id="quickSearchInput" placeholder="Quick search..."
                               class="header-search pl-10 pr-10"
                               style="color: var(--text-primary); background-color: var(--bg-secondary); border: 1px solid transparent;"
                               data-toggle="tooltip" title="Press / to focus">
                        <button id="advancedSearchBtn" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>
                    </div>

                    <button id="mobileSearchBtn" class="md:hidden p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                        <i class="fas fa-search text-gray-600 dark:text-gray-300"></i>
                    </button>
                </div>
            </div>

            <div class="header-buttons flex items-center space-x-2">
                <div class="relative">
                    <button id="notificationBellBtn" class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="far fa-bell text-gray-600 dark:text-gray-300 text-xl"></i>
                        <span id="notificationBadge" class="notification-badge hidden">
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                        </span>
                    </button>
                </div>

                <button class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                    <i class="far fa-envelope text-gray-600 dark:text-gray-300"></i>
                    <span class="notification-dot"></span>
                </button>
            </div>

            <div class="dropdown relative">
                <button id="userMenuButton" class="flex items-center space-x-2">
                    <div class="avatar-minimal">
                        @if(Auth::user()->photo)
                            <img src="{{ Storage::url('public/users/photos/' . Auth::user()->photo) }}" alt="{{ Auth::user()->name }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                                {{ substr(Auth::user()->name, 0, 2) }}
                            </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                </button>

                <div id="userDropdown" class="dropdown-menu" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <a href="{{ route('developer.profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    <!-- Email Management -->
                    <a href="#" id="emailManagementDropdown" class="dropdown-item">
                        <i class="fas fa-envelope mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Email Management</span>
                        @if($totalUnreadEmails > 0)
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                {{ $totalUnreadEmails > 9 ? '9+' : $totalUnreadEmails }}
                            </span>
                        @endif
                    </a>

                    <!-- SMS Management — only when provider configured -->
                    @if($smsProviderConfigured)
                    <a href="#" id="smsManagementDropdown" class="dropdown-item">
                        <i class="fas fa-sms mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">SMS Management</span>
                        @if($pendingSmsCount > 0)
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                {{ $pendingSmsCount > 9 ? '9+' : $pendingSmsCount }}
                            </span>
                        @endif
                    </a>
                    @endif

                    <!-- WhatsApp Management — only when provider configured -->
                    @if($whatsappProviderConfigured)
                    <a href="#" id="whatsappManagementDropdown" class="dropdown-item">
                        <i class="fab fa-whatsapp mr-3" style="color: #25D366;"></i>
                        <span style="color: var(--text-primary);">WhatsApp Management</span>
                    </a>
                    @endif

                    <!-- Communication Providers -->
                    <a href="#" id="communicationManagementDropdown" class="dropdown-item">
                        <i class="fas fa-plug mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Communication Providers</span>
                        <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ $totalCommunicationProviders }}
                        </span>
                    </a>

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    <!-- System Settings -->
                    <a href="{{ route('developer.settings.index') }}" class="dropdown-item">
                        <i class="fas fa-cog mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">System Settings</span>
                    </a>

                    <!-- Payment Providers -->
                    <a href="{{ route('admin.payment-providers.index') }}" class="dropdown-item">
                        <i class="fas fa-credit-card mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Payment Providers</span>
                    </a>

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-left text-red-500">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <button id="scrollToTop" class="fixed bottom-6 right-6 w-12 h-12 rounded-full shadow-lg flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 pointer-events-none"
            style="background-color: var(--primary); color: white;">
        <i class="fas fa-arrow-up"></i>
    </button>

@include('layouts.partials.developer.scripts.styles')
@include('layouts.partials.developer.scripts.scripts')