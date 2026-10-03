{{-- resources/views/components/sanheader.blade.php --}}

{{-- Fix for undefined variable errors --}}
@php
    if (!isset($sidebarUnreadCount)) {
        $sidebarUnreadCount = auth()->check()
            ? auth()->user()->unreadNotifications()->count()
            : 0;
    }

    // ========== SANITATION PERSONNEL SPECIFIC ==========
    $user = auth()->user();
    $isSanitationPersonnel = $user->type === \App\Models\User::TYPE_SANITATION_PERSONNEL;
    $isSuperAdmin = $user->type === \App\Models\User::TYPE_SUPER_ADMIN;
    $isAdmin = $user->type === \App\Models\User::TYPE_ADMIN;

    // Get sanitation personnel record
    $personnel = $user->sanitationPersonnel;

    // ✅ Is the current user the ROOT supervisor?
    $isRootSupervisor = $personnel
        && $personnel->isSupervisor()
        && is_null($personnel->supervisor_id);

    // Get email accounts based on role
    if ($isSuperAdmin || $isAdmin) {
        $emailAccounts = \App\Models\UserEmailAccount::all();
        $pendingEmailCount = $emailAccounts->where('status', 'pending')->count();
        $failedEmailCount = $emailAccounts->where('status', 'failed')->count();
        $totalEmailIssues = $pendingEmailCount + $failedEmailCount;

        $totalUnreadEmails = 0;
        foreach ($emailAccounts as $account) {
            try {
                $totalUnreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
            } catch (\Exception $e) {
                // Skip if relationship fails
            }
        }
    } else {
        $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
        $pendingEmailCount = $emailAccounts->where('status', 'pending')->count();
        $failedEmailCount = $emailAccounts->where('status', 'failed')->count();
        $totalEmailIssues = $pendingEmailCount + $failedEmailCount;

        $totalUnreadEmails = 0;
        foreach ($emailAccounts as $account) {
            try {
                $totalUnreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
            } catch (\Exception $e) {
                // Skip if relationship fails
            }
        }
    }

    // ========== SANITATION STATS ==========
    $activeJobs = $personnel ? $personnel->active_jobs_count : 0;
    $completedToday = $personnel ? $personnel->completed_jobs_today : 0;
    $pendingRequests = \App\Models\WasteCollectionRequest::where('status', 'pending')->count();
    $linkedProperties = \App\Models\Property::whereHas('wasteCollectionRequests')->count();

    // Personnel and Worker counts
    $activePersonnel = \App\Models\SanitationPersonnel::active()->count();
    $activeWorkers = \App\Models\SanitationWorker::active()->count();

    // Collection Zones count
    $activeZones = \App\Models\CollectionZone::active()->count();
    $totalZones = \App\Models\CollectionZone::count();

    // ========== ✅ APPROVALS (NEW) ==========
    // Pending landlord approvals + how many are about to expire
    $pendingApprovalsCount = \App\Models\WasteCollectionRequest::query()
        ->where('approval_status', \App\Models\WasteCollectionRequest::APPROVAL_PENDING)
        ->count();

    $approvalsExpiringSoon = \App\Models\WasteCollectionRequest::query()
        ->where('approval_status', \App\Models\WasteCollectionRequest::APPROVAL_PENDING)
        ->where('approval_expires_at', '<=', now()->addHours(48))
        ->where('approval_expires_at', '>', now())
        ->count();
@endphp

<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Enhanced Professional Logo Section -->
    <div class="logo-section">
        @php
            $systemSettings = \App\Models\SystemSetting::getSettings();
            $systemLogo = $systemSettings->system_logo ?? null;
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Sanitation');
            $systemName = $systemSettings->system_name ?? config('app.name', 'Laravel');
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
                            <i class="fas fa-trash-alt"></i>
                        </div>
                    </div>
                @else
                    <div class="logo-default">
                        <i class="fas fa-trash-alt"></i>
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

    <!-- Navigation -->
    <nav class="mt-2">
        <div class="nav-divider">
            <span class="menu-text">MAIN NAVIGATION</span>
        </div>

        <a href="{{ route('sanitation.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
            @if($activeJobs > 0)
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $activeJobs > 9 ? '9+' : $activeJobs }}
                </span>
            @endif
        </a>

        <!-- ============================================ -->
        <!-- 📋 PROPERTY MANAGEMENT                       -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">PROPERTY MANAGEMENT</span>
        </div>

        <!-- Available Properties (Link to Collection) -->
        <a href="{{ route('sanitation.properties.available') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.properties.available') ? 'active' : '' }}">
            <i class="fas fa-building mr-4"></i>
            <span class="nav-text">Available Properties</span>
            @php
                $availableCount = \App\Models\Property::whereDoesntHave('wasteCollectionRequests')
                    ->where('status', 'active')
                    ->count();
            @endphp
            @if($availableCount > 0)
                <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $availableCount > 9 ? '9+' : $availableCount }}
                </span>
            @endif
        </a>

        <!-- Linked Properties -->
        <a href="{{ route('sanitation.properties.linked') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.properties.linked') ? 'active' : '' }}">
            <i class="fas fa-link mr-4"></i>
            <span class="nav-text">Linked Properties</span>
            @if($linkedProperties > 0)
                <span class="ml-auto bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $linkedProperties > 9 ? '9+' : $linkedProperties }}
                </span>
            @endif
        </a>

        <!-- ============================================ -->
        <!-- 🗑️ WASTE COLLECTION                          -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">WASTE COLLECTION</span>
        </div>

        <!-- All Requests -->
        <a href="{{ route('sanitation.requests.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.requests.index') ? 'active' : '' }}">
            <i class="fas fa-list mr-4"></i>
            <span class="nav-text">All Requests</span>
        </a>

        <!-- Pending Requests -->
        <a href="{{ route('sanitation.requests.pending') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.requests.pending') ? 'active' : '' }}">
            <i class="fas fa-clock mr-4"></i>
            <span class="nav-text">Pending Requests</span>
            @if($pendingRequests > 0)
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $pendingRequests > 9 ? '9+' : $pendingRequests }}
                </span>
            @endif
        </a>

        <!-- ✅ NEW: Landlord Approvals -->
        <a href="{{ route('sanitation.approvals.pending') }}"
           class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.approvals.*') ? 'active' : '' }}">
            <i class="fas fa-user-check mr-4"></i>
            <span class="nav-text">Landlord Approvals</span>

            @if($approvalsExpiringSoon > 0)
                {{-- Orange + pulse: some approvals expire within 48h --}}
                <span class="ml-auto bg-orange-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center approval-badge-pulse"
                      title="{{ $approvalsExpiringSoon }} approval(s) expiring within 48 hours">
                    {{ $pendingApprovalsCount > 9 ? '9+' : $pendingApprovalsCount }}
                </span>
            @elseif($pendingApprovalsCount > 0)
                {{-- Yellow: pending but not urgent --}}
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $pendingApprovalsCount > 9 ? '9+' : $pendingApprovalsCount }}
                </span>
            @else
                {{-- Nothing pending: green checkmark --}}
                <span class="ml-auto text-green-500 text-xs" title="No pending approvals">
                    <i class="fas fa-check-circle"></i>
                </span>
            @endif
        </a>

        <!-- ============================================ -->
        <!-- 📍 COLLECTION ZONES                          -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">COLLECTION ZONES</span>
        </div>

        <!-- Collection Zones Index -->
        <a href="{{ route('sanitation.zones.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.zones.index') ? 'active' : '' }}">
            <i class="fas fa-map-marked-alt mr-4"></i>
            <span class="nav-text">Collection Zones</span>
            @if($activeZones > 0)
                <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $activeZones }}
                </span>
            @endif
        </a>

        <!-- ============================================ -->
        <!-- 💬 COMMUNICATION                             -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">COMMUNICATION</span>
        </div>

        <!-- Email Management Button -->
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

        <!-- ============================================ -->
        <!-- 👥 PERSONNEL MANAGEMENT                      -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">PERSONNEL MANAGEMENT</span>
        </div>

        {{-- ✅ Any supervisor (root or sub) + admins can view personnel and workers --}}
        @if($isSuperAdmin || $isAdmin || ($personnel && $personnel->isSupervisor()))
            <!-- View Personnel -->
            <a href="{{ route('sanitation.personnel.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.personnel.index') ? 'active' : '' }}">
                <i class="fas fa-users mr-4"></i>
                <span class="nav-text">Personnel</span>
                @if($activePersonnel > 0)
                    <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ $activePersonnel > 9 ? '9+' : $activePersonnel }}
                    </span>
                @endif
            </a>

            <!-- View Workers -->
            <a href="{{ route('sanitation.workers.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.workers.index') ? 'active' : '' }}">
                <i class="fas fa-user-friends mr-4"></i>
                <span class="nav-text">Workers</span>
                @if($activeWorkers > 0)
                    <span class="ml-auto bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ $activeWorkers > 9 ? '9+' : $activeWorkers }}
                    </span>
                @endif
            </a>
        @endif

        <!-- ============================================ -->
        <!-- ⚙️ SANITATION SETTINGS                      -->
        <!-- ============================================ -->
        @php
            $canAccessSettings = false;

            if ($isSuperAdmin || $isAdmin) {
                $canAccessSettings = true;
            } elseif ($isRootSupervisor) {
                $canAccessSettings = true;
            }

            if ($canAccessSettings) {
                $settings = \App\Models\SanitationSetting::getSettings();
                $hasSettings = $settings->exists;
            }
        @endphp

        @if($canAccessSettings)
            <div class="nav-divider mt-1">
                <span class="menu-text">SYSTEM SETTINGS</span>
            </div>

            <a href="{{ route('sanitation.settings.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.settings.*') ? 'active' : '' }}">
                <i class="fas fa-cog mr-4"></i>
                <span class="nav-text">Sanitation Settings</span>
                @if($hasSettings)
                    <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-2 h-2 flex items-center justify-center">
                        <i class="fas fa-check text-[8px]"></i>
                    </span>
                @else
                    <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        <i class="fas fa-exclamation text-[8px]"></i>
                    </span>
                @endif
            </a>
        @endif

        <!-- ============================================ -->
        <!-- 📊 STATISTICS & REPORTS                      -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">STATISTICS</span>
        </div>

        <a href="{{ route('sanitation.statistics') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.statistics') ? 'active' : '' }}">
            <i class="fas fa-chart-bar mr-4"></i>
            <span class="nav-text">Statistics</span>
        </a>

        <a href="{{ route('sanitation.reports') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.reports') ? 'active' : '' }}">
            <i class="fas fa-file-alt mr-4"></i>
            <span class="nav-text">Reports</span>
        </a>

        <!-- ============================================ -->
        <!-- ✅ NOTIFICATIONS                             -->
        <!-- ============================================ -->
        <div class="nav-divider mt-1">
            <span class="menu-text">NOTIFICATIONS</span>
        </div>

        <a href="{{ route('notifications.index') }}"
           class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('*.notifications.*') ? 'active' : '' }}"
           id="sidebarNotificationsLink"
           data-notification-badge>
            <i class="fas fa-bell mr-4"></i>
            <span class="nav-text">Notifications</span>
            {{-- Server-rendered initial count; JS will keep it live --}}
            <span class="ml-auto {{ $sidebarUnreadCount > 0 ? '' : 'hidden' }}"
                  id="sidebarNotificationBadge">
                <span class="notif-count-pill bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                    {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                </span>
            </span>
        </a>
    </nav>

    <!-- Settings Button -->
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
</div>

<!-- ============ EMAIL ACCOUNT MANAGEMENT MODAL ============ -->
@if(auth()->check())
<div id="emailManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="email-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1400px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <!-- Modal Header -->
        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-3" style="color: var(--primary);"></i>
                    Email Account Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage your email accounts and communication
                </p>
            </div>
            <button id="closeEmailModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">

            {{-- Email Account Stats Summary --}}
            @php
                $user = auth()->user();

                if ($isSuperAdmin || $isAdmin) {
                    $emailAccounts = \App\Models\UserEmailAccount::all();
                    $userNames = [];
                    foreach ($emailAccounts as $account) {
                        try {
                            $userNames[$account->user_id] = $account->user->name ?? 'Unknown User';
                        } catch (\Exception $e) {
                            $userNames[$account->user_id] = 'Unknown User';
                        }
                    }
                } else {
                    $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
                    $userNames = [];
                }

                $totalAccounts = $emailAccounts->count();
                $verifiedAccounts = $emailAccounts->where('status', 'verified')->count();
                $pendingAccounts = $emailAccounts->where('status', 'pending')->count();
                $failedAccounts = $emailAccounts->where('status', 'failed')->count();
                $totalEmails = 0;
                $unreadEmails = 0;
                foreach ($emailAccounts as $account) {
                    try {
                        $totalEmails += $account->emails()->count();
                        $unreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                    } catch (\Exception $e) {
                        // Skip if relationship fails
                    }
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

                    <a href="#" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="event.preventDefault(); syncAllEmails();">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-sync text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Sync All</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Fetch new emails</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Email Accounts List -->
            @if($totalAccounts > 0)
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                        @if($isSuperAdmin || $isAdmin)
                            All Email Accounts
                        @else
                            Your Email Accounts
                        @endif
                        <span class="text-xs ml-2 px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            {{ $totalAccounts }}
                        </span>
                    </h4>
                    <div class="space-y-2">
                        @foreach($emailAccounts as $account)
                            @php
                                $accountUnread = 0;
                                try {
                                    $accountUnread = $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                                } catch (\Exception $e) {
                                    $accountUnread = 0;
                                }
                                $accountTotal = 0;
                                try {
                                    $accountTotal = $account->emails()->count();
                                } catch (\Exception $e) {
                                    $accountTotal = 0;
                                }
                                $ownerName = $userNames[$account->user_id] ?? 'Unknown';
                            @endphp
                            <div class="account-item flex items-center justify-between p-3 rounded-lg transition-all duration-200 hover:bg-opacity-10"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                         style="background: linear-gradient(135deg, {{ $account->is_primary ? 'var(--primary)' : '#6b7280' }}, {{ $account->is_primary ? 'var(--secondary)' : '#9ca3af' }});">
                                        <i class="fas fa-envelope text-white text-xs"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center flex-wrap">
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
                                            @if($isSuperAdmin || $isAdmin)
                                                <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded-full" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                                    {{ $ownerName }}
                                                </span>
                                            @endif
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
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        You haven't linked any email accounts yet.
                    </p>
                    <a href="{{ route('email-accounts.create') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
                       style="background-color: var(--primary); color: white;"
                       onclick="closeEmailModal()">
                        <i class="fas fa-plus mr-2"></i>Link Your First Account
                    </a>
                </div>
            @endif
        </div>

        <!-- Modal Footer -->
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
@endif

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
            <!-- Appearance Selection -->
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

            <!-- Sidebar Themes -->
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
                <i class="fas fa-search mr-2"></i>Search
            </h3>
            <button id="closeSearchModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Term</label>
                <input type="text" id="globalSearchInput" placeholder="Search properties, requests..."
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                <select id="searchCategory" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="all">All Categories</option>
                    <option value="properties">Properties</option>
                    <option value="requests">Collection Requests</option>
                    <option value="zones">Collection Zones</option>
                    <option value="personnel">Personnel</option>
                    <option value="workers">Workers</option>
                </select>
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
        <a href="{{ route('notifications.index') }}" class="view-all-link">
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
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">@yield('title', 'Sanitation Dashboard')</h2>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Quick Stats -->
            <div class="hidden md:flex items-center space-x-4">
                <div class="flex items-center space-x-1 text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-tasks text-green-500"></i>
                    <span>Active: <strong class="text-primary">{{ $activeJobs }}</strong></span>
                </div>
                <div class="flex items-center space-x-1 text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span>Today: <strong class="text-primary">{{ $completedToday }}</strong></span>
                </div>
                <div class="flex items-center space-x-1 text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-clock text-yellow-500"></i>
                    <span>Pending: <strong class="text-primary">{{ $pendingRequests }}</strong></span>
                </div>
            </div>

            <!-- Search Bar -->
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

            <!-- ========================================================= -->
            <!-- 🔔 NOTIFICATION BELL - LIVE COUNT                        -->
            <!-- ========================================================= -->
            <div class="header-buttons flex items-center space-x-2">
                <div class="relative">
                    <button id="notificationBellBtn"
                            class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200"
                            data-notification-badge
                            data-initial-count="{{ $sidebarUnreadCount }}"
                            aria-label="Notifications"
                            title="Notifications">
                        <i class="far fa-bell text-gray-600 dark:text-gray-300 text-xl"></i>
                        <span id="notificationBadge"
                              class="notification-badge {{ $sidebarUnreadCount > 0 ? '' : 'hidden' }}">
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold notif-count-pill">
                                {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                            </span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- User Dropdown -->
            <div class="dropdown relative">
                <button id="userMenuButton" class="flex items-center space-x-2">
                    <div class="avatar-minimal">
                        @if(Auth::user()->photo)
                            <img src="{{ Storage::url('public/users/photos/' . Auth::user()->photo) }}" alt="{{ Auth::user()->name }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-teal-600 flex items-center justify-center text-white text-sm font-semibold">
                                {{ substr(Auth::user()->name, 0, 2) }}
                            </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                </button>

                <div id="userDropdown" class="dropdown-menu" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <a href="{{ route('sanitation.profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>

                    @if($personnel)
                        <a href="{{ route('sanitation.personnel.show', $personnel) }}" class="dropdown-item">
                            <i class="fas fa-id-badge mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">My Stats</span>
                        </a>
                    @endif

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    <a href="{{ route('notifications.index') }}"
                       class="dropdown-item"
                       id="dropdownNotificationsLink"
                       data-notification-badge>
                        <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Notifications</span>
                        <span id="dropdownNotificationBadge"
                              class="ml-auto notif-count-pill bg-red-500 text-white text-xs px-2 py-1 rounded-full {{ $sidebarUnreadCount > 0 ? '' : 'hidden' }}">
                            {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                        </span>
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

    <!-- Main Content Area -->
    <main class="p-6">

<style>
/* ============================================ */
/* 🔔 NOTIFICATION BADGE — LIVE COUNT          */
/* ============================================ */

#notificationBellBtn {
    position: relative;
}

#notificationBellBtn .notification-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    pointer-events: none;
}

#notificationBellBtn .notification-badge .notif-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    border-radius: 999px;
    background-color: #ef4444;
    color: #fff;
    border: 2px solid var(--card-bg, #ffffff);
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
    animation: notificationPulse 2s infinite;
    transition: transform 0.15s ease, opacity 0.15s ease;
    transform-origin: center;
}

#notificationBellBtn:hover .notification-badge .notif-count-pill {
    transform: scale(1.1);
}

/* Highlight animation when a new notification arrives */
#notificationBellBtn.badge-bump .notif-count-pill {
    animation: badgeBump 0.6s ease-out;
}

@keyframes notificationPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(1.08); opacity: 0.85; }
}

@keyframes badgeBump {
    0%   { transform: scale(1); }
    35%  { transform: scale(1.4); }
    70%  { transform: scale(0.95); }
    100% { transform: scale(1); }
}

/* Dark mode border fix */
[data-theme="dark"] #notificationBellBtn .notification-badge .notif-count-pill {
    border-color: #1a1e2c;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.5);
}

[data-theme="light"] #notificationBellBtn .notification-badge .notif-count-pill {
    border-color: #ffffff;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
}

/* Sidebar pill tweak */
#sidebarNotificationBadge .notif-count-pill {
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 999px;
}

/* Dropdown pill tweak */
#dropdownNotificationBadge.notif-count-pill {
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 999px;
}

/* ============================================ */
/* ✅ APPROVAL BADGE PULSE (when expiring soon) */
/* ============================================ */

.approval-badge-pulse {
    animation: approvalPulse 1.6s infinite;
}

@keyframes approvalPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.6); }
    50%      { transform: scale(1.08); box-shadow: 0 0 0 5px rgba(249, 115, 22, 0); }
}

/* ============================================ */
/* 🎨 SIDEBAR COLLAPSE STYLES - Section Dividers Hidden */
/* ============================================ */

/* ===== SIDEBAR COLLAPSED MODE ===== */
.sidebar.collapsed {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
}

/* Hide section dividers completely when collapsed */
.sidebar.collapsed .nav-divider {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    min-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
}

/* Compact nav items in collapsed mode */
.sidebar.collapsed nav {
    padding: 4px 0 !important;
}

.sidebar.collapsed .nav-item {
    padding: 8px 0 !important;
    margin: 2px 4px !important;
    border-radius: 8px !important;
    justify-content: center !important;
    width: calc(100% - 8px) !important;
    min-height: 40px !important;
    display: flex !important;
    align-items: center !important;
}

.sidebar.collapsed .nav-item i {
    margin: 0 !important;
    font-size: 1.15rem !important;
}

.sidebar.collapsed .nav-item .nav-text,
.sidebar.collapsed .nav-item span.ml-auto,
.sidebar.collapsed .nav-item .ml-auto,
.sidebar.collapsed .nav-item .badge,
.sidebar.collapsed .nav-item .chevron-right,
.sidebar.collapsed .nav-item .flex-grow {
    display: none !important;
}

/* Hide badges and counts in collapsed mode */
.sidebar.collapsed .nav-item .bg-yellow-500,
.sidebar.collapsed .nav-item .bg-red-500,
.sidebar.collapsed .nav-item .bg-blue-500,
.sidebar.collapsed .nav-item .bg-orange-500,
.sidebar.collapsed .nav-item .bg-green-500 {
    display: none !important;
}

/* Compact logo section */
.sidebar.collapsed .logo-section {
    padding: 10px 6px !important;
    position: relative !important;
}

.sidebar.collapsed .logo-text-container,
.sidebar.collapsed .logo-fullname,
.sidebar.collapsed .logo-shortname {
    display: none !important;
}

.sidebar.collapsed .logo-wrapper {
    justify-content: center !important;
}

.sidebar.collapsed .logo-image-container,
.sidebar.collapsed .logo-default {
    width: 32px !important;
    height: 32px !important;
}

.sidebar.collapsed .logo-separator {
    margin: 4px 8px !important;
}

/* FIXED: Toggle button - Always visible and positioned on the edge */
.sidebar.collapsed .toggle-sidebar {
    display: flex !important;
    position: absolute !important;
    right: -14px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 100 !important;
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
    width: 28px !important;
    height: 28px !important;
    border-radius: 50% !important;
}

.sidebar.collapsed .toggle-sidebar:hover {
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    background-color: var(--secondary) !important;
}

.sidebar.collapsed .toggle-sidebar i {
    transform: rotate(180deg);
    font-size: 12px !important;
}

.sidebar.collapsed .absolute.bottom-0 {
    padding: 6px 0 !important;
}

.sidebar.collapsed #settingsIcon {
    font-size: 1rem !important;
}

/* Active state in collapsed mode */
.sidebar.collapsed .nav-item.active {
    background: rgba(var(--primary-rgb), 0.15) !important;
    border-left: 3px solid var(--primary) !important;
}

/* Remove extra spacing between nav items in collapsed mode */
.sidebar.collapsed .nav-divider + .nav-item {
    margin-top: 2px !important;
}

.sidebar.collapsed .nav-item + .nav-item {
    margin-top: 2px !important;
}

/* Ensure buttons are properly centered in collapsed mode */
.sidebar.collapsed .nav-item button,
.sidebar.collapsed .nav-item a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    padding: 0 !important;
}

/* Hide any remaining text or labels in collapsed mode */
.sidebar.collapsed .menu-text {
    display: none !important;
}

.sidebar.collapsed .nav-divider .menu-text {
    display: none !important;
}

/* ============================================ */
/* 🔄 SIDEBAR TOGGLE BUTTON - EXPANDED MODE */
/* ============================================ */

.toggle-sidebar {
    cursor: pointer;
    display: flex !important;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.25);
    color: var(--sidebar-text, #ffffff);
    position: absolute;
    right: -14px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 100;
    background-color: var(--primary);
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.toggle-sidebar:hover {
    transform: translateY(-50%) scale(1.1);
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.toggle-sidebar i {
    font-size: 12px;
    transition: transform 0.3s ease;
}

/* Logo section must be relative for toggle button positioning */
.logo-section {
    position: relative !important;
}

/* Ensure toggle button is visible in all sidebar themes */
[data-sidebar-theme="dark"] .toggle-sidebar,
[data-sidebar-theme="default"] .toggle-sidebar,
[data-sidebar-theme="blue"] .toggle-sidebar,
[data-sidebar-theme="green"] .toggle-sidebar {
    background-color: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

[data-sidebar-theme="light"] .toggle-sidebar {
    background-color: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

/* ============================================ */
/* 👤 ENHANCED MINIMAL AVATAR STYLING */
/* ============================================ */

.avatar-minimal {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}

.avatar-minimal:hover {
    transform: scale(1.05);
    border-color: var(--primary);
}

.avatar-minimal img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

/* ============================================ */
/* 📋 HEADER STYLES */
/* ============================================ */

.header {
    padding: 12px 24px;
}

/* Search bar enhancements */
.header-search {
    min-width: 280px;
    border-radius: 8px;
    padding: 8px 16px 8px 40px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    min-width: 350px;
    transition: min-width 0.3s ease;
    outline: none;
}

/* Theme-specific search input styling */
[data-theme="dark"] .header-search {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

[data-theme="dark"] .header-search::placeholder {
    color: #a0aec0 !important;
}

[data-theme="light"] .header-search {
    background-color: #f7fafc !important;
    border-color: #e2e8f0 !important;
    color: #2d3748 !important;
}

[data-theme="light"] .header-search::placeholder {
    color: #718096 !important;
}

/* Quick stats */
.header-buttons {
    gap: 8px;
}

/* Dropdown */
.dropdown-menu {
    min-width: 220px;
}

/* ============================================ */
/* 🔔 NOTIFICATION BADGE STYLES */
/* ============================================ */

.notification-badge {
    position: relative;
}

.notification-badge .badge-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: bold;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
}

/* Notification badge pulse animation */
.notification-badge .badge-count.pulse {
    animation: notificationPulse 2s infinite;
}

/* ============================================ */
/* 📬 NOTIFICATIONS DROPDOWN STYLES */
/* ============================================ */

.notifications-dropdown {
    position: absolute;
    top: 60px;
    right: 20px;
    width: 380px;
    max-width: calc(100vw - 40px);
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.notifications-dropdown.hidden {
    display: none;
}

.notifications-header {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notifications-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.notifications-actions {
    display: flex;
    gap: 12px;
}

.notifications-actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    color: var(--text-secondary);
    transition: color 0.2s;
}

.notifications-actions button:hover {
    color: var(--primary);
}

.notifications-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    cursor: pointer;
    position: relative;
}

.notification-item:hover {
    background-color: var(--bg-secondary);
}

.notification-item.unread {
    background-color: rgba(59, 130, 246, 0.05);
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #3b82f6;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.notification-time {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.notifications-loading {
    padding: 32px;
    text-align: center;
    color: var(--text-secondary);
}

.notifications-empty {
    padding: 48px 32px;
    text-align: center;
    color: var(--text-secondary);
}

.notifications-empty i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.notifications-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

.view-all-link {
    font-size: 13px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.view-all-link:hover {
    text-decoration: underline;
    color: var(--primary);
}

/* ============================================ */
/* 🔔 BELL ICON HOVER EFFECT */
/* ============================================ */

#notificationBell:hover .fa-bell {
    animation: bellRing 0.5s ease-in-out;
}

@keyframes bellRing {
    0% { transform: rotate(0); }
    25% { transform: rotate(15deg); }
    50% { transform: rotate(-15deg); }
    75% { transform: rotate(10deg); }
    100% { transform: rotate(0); }
}

/* ============================================ */
/* 📧 EMAIL ACCOUNT ITEMS */
/* ============================================ */

.account-item {
    transition: all 0.2s ease;
}

.account-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* ============================================ */
/* 🎨 THEME SETTINGS MODAL */
/* ============================================ */

.theme-modal-compact {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* ============================================ */
/* 🔄 MODAL ANIMATIONS */
/* ============================================ */

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* ============================================ */
/* 🔧 SANITATION SETTINGS LINK STYLES */
/* ============================================ */

.nav-item .bg-green-500 {
    transition: all 0.3s ease;
}

.nav-item .bg-yellow-500 {
    transition: all 0.3s ease;
    animation: pulse-warning 2s infinite;
}

@keyframes pulse-warning {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.nav-item[href*="settings"]:hover {
    background: rgba(var(--primary-rgb), 0.1);
}

.nav-item[href*="settings"].active {
    background: rgba(var(--primary-rgb), 0.15);
    border-right: 3px solid var(--primary);
}

/* ============================================ */
/* 📱 MODAL BACKDROP BLUR */
/* ============================================ */

#communicationManagementModal,
#themeSettingsModal,
#searchModal,
#emailManagementModal,
#smsManagementModal,
#whatsappManagementModal,
#billingManagementModal,
#administrativeToolsModal,
#invoiceManagementModal {
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

/* ============================================ */
/* 📱 RESPONSIVE ADJUSTMENTS */
/* ============================================ */

@media (max-width: 768px) {
    .header {
        padding: 10px 16px;
    }

    .avatar-minimal {
        width: 28px;
        height: 28px;
    }

    .header-buttons {
        gap: 6px;
    }

    .header-search {
        min-width: auto;
        width: 200px;
    }

    .header-search:focus {
        min-width: auto;
        width: 100%;
    }

    .notifications-dropdown {
        position: fixed;
        top: 60px;
        right: 10px;
        left: 10px;
        width: auto;
        max-width: none;
    }

    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        position: fixed !important;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1050;
        width: 280px !important;
    }

    .sidebar.mobile-open {
        transform: translateX(0);
    }

    .sidebar.collapsed.mobile-open {
        transform: translateX(0);
        width: 280px !important;
    }

    .sidebar.collapsed.mobile-open .nav-divider {
        display: flex !important;
    }

    .sidebar.collapsed.mobile-open .nav-text {
        display: inline !important;
    }

    .sidebar.collapsed.mobile-open .logo-text-container {
        display: block !important;
    }

    .sidebar.collapsed.mobile-open .toggle-sidebar {
        display: flex !important;
        right: -12px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }

    .sidebar.collapsed.mobile-open .ml-auto {
        display: inline-flex !important;
    }

    .sidebar.collapsed.mobile-open .bg-yellow-500,
    .sidebar.collapsed.mobile-open .bg-red-500,
    .sidebar.collapsed.mobile-open .bg-blue-500,
    .sidebar.collapsed.mobile-open .bg-orange-500,
    .sidebar.collapsed.mobile-open .bg-green-500 {
        display: inline-flex !important;
    }

    .overlay.active {
        display: block;
        opacity: 1;
    }

    .toggle-sidebar {
        right: -12px !important;
        width: 24px !important;
        height: 24px !important;
        font-size: 10px !important;
    }

    .sidebar.collapsed .toggle-sidebar {
        right: -12px !important;
        width: 24px !important;
        height: 24px !important;
        font-size: 10px !important;
    }
}

@media (max-width: 480px) {
    .header-search {
        width: 160px;
    }

    .theme-modal-compact {
        width: 100vw;
        max-width: none;
        margin: 0;
        border-radius: 0;
    }

    .toggle-sidebar {
        right: -10px !important;
        width: 22px !important;
        height: 22px !important;
        font-size: 9px !important;
    }

    .sidebar.collapsed .toggle-sidebar {
        right: -10px !important;
        width: 22px !important;
        height: 22px !important;
        font-size: 9px !important;
    }
}

/* ============================================ */
/* 🎨 SIDEBAR THEME TEXT COLORS */
/* ============================================ */

.sidebar,
.sidebar .nav-item,
.sidebar .logo-shortname,
.sidebar .logo-fullname,
.sidebar .nav-text,
.sidebar a:not(.active),
.sidebar button:not(.active) {
    color: var(--sidebar-text, #ffffff) !important;
    transition: color 0.2s ease;
}

.sidebar .nav-item i,
.sidebar button i {
    color: var(--sidebar-text, #ffffff) !important;
    opacity: 0.8;
    transition: all 0.2s ease;
}

[data-sidebar-theme="default"] { --sidebar-text: #ffffff; }
[data-sidebar-theme="dark"]    { --sidebar-text: #e2e8f0; }
[data-sidebar-theme="light"]   { --sidebar-text: #1e293b; }
[data-sidebar-theme="blue"]    { --sidebar-text: #ffffff; }
[data-sidebar-theme="green"]   { --sidebar-text: #ffffff; }

.sidebar .nav-item:hover,
.sidebar button:hover {
    color: var(--sidebar-text-hover, var(--primary)) !important;
}

.sidebar .nav-item:hover i,
.sidebar button:hover i {
    color: var(--sidebar-text-hover, var(--primary)) !important;
    opacity: 1;
}

.sidebar .nav-item.active,
.sidebar button.active {
    color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.15) !important;
}

.sidebar .nav-item.active i,
.sidebar button.active i {
    color: var(--primary) !important;
    opacity: 1;
}

/* ============================================ */
/* 🎨 BADGE COLORS */
/* ============================================ */

.bg-yellow-500 { background-color: #f59e0b; }
.bg-orange-500 { background-color: #f97316; }
.bg-blue-500   { background-color: #3b82f6; }
.bg-red-500    { background-color: #ef4444; }
.bg-green-500  { background-color: #22c55e; }

/* ============================================ */
/* 📝 PLACEHOLDER TEXT COLOR */
/* ============================================ */

[data-theme="light"] ::placeholder,
::placeholder.light-theme {
    color: #a0aec0 !important;
    opacity: 1;
}

[data-theme="dark"] ::placeholder,
::placeholder.dark-theme {
    color: #718096 !important;
    opacity: 1;
}

/* ============================================ */
/* ✨ SMOOTH TRANSITIONS */
/* ============================================ */

.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn,
.communication-option-card,
.quick-action-btn-sm,
.billing-option-card,
.admin-tools-card,
.quick-action-btn,
.recent-search-tag,
#clearRecentSearchesBtn,
.account-item {
    transition: all 0.2s ease-in-out;
}

/* ============================================ */
/* 🔄 SIDEBAR OVERLAY FOR MOBILE */
/* ============================================ */

.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.overlay.active {
    display: block;
    opacity: 1;
}
</style>

{{-- ========================================================= --}}
{{-- 🔔 LIVE NOTIFICATION COUNT SCRIPT (Echo-only, no polling) --}}
{{-- ========================================================= --}}
@auth
<script>
(function () {
    'use strict';

    if (window.__notificationBadgeInitialized) return;
    window.__notificationBadgeInitialized = true;

    // ---------------------------------------------------------
    // State (single source of truth for the whole page)
    // ---------------------------------------------------------
    let unreadCount = parseInt(
        document.getElementById('notificationBellBtn')?.dataset.initialCount || '0',
        10
    );

    // ---------------------------------------------------------
    // DOM refs — resolved lazily so we survive partial reloads
    // ---------------------------------------------------------
    const refs = {
        get bell()          { return document.getElementById('notificationBellBtn'); },
        get bellBadge()     { return document.getElementById('notificationBadge'); },
        get bellPill()      { return document.querySelector('#notificationBadge .notif-count-pill'); },
        get sidebarBadge()  { return document.getElementById('sidebarNotificationBadge'); },
        get sidebarPill()   { return document.querySelector('#sidebarNotificationBadge .notif-count-pill'); },
        get dropdownBadge() { return document.getElementById('dropdownNotificationBadge'); },
    };

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------
    function formatCount(n) {
        if (!Number.isFinite(n) || n < 0) n = 0;
        return n > 9 ? '9+' : String(n);
    }

    function setHidden(el, hidden) {
        if (!el) return;
        el.classList.toggle('hidden', hidden);
        el.style.display = hidden ? 'none' : '';
    }

    function paintCount() {
        const display = formatCount(unreadCount);

        // Header bell pill + badge
        const bellBadge = refs.bellBadge;
        const bellPill  = refs.bellPill;
        if (bellPill)  bellPill.textContent = display;
        setHidden(bellBadge, unreadCount <= 0);

        // Sidebar pill
        const sidebarBadge = refs.sidebarBadge;
        const sidebarPill  = refs.sidebarPill;
        if (sidebarPill) sidebarPill.textContent = display;
        setHidden(sidebarBadge, unreadCount <= 0);

        // Dropdown pill
        const dropdownBadge = refs.dropdownBadge;
        if (dropdownBadge) {
            dropdownBadge.textContent = display;
            setHidden(dropdownBadge, unreadCount <= 0);
        }

        // Update document.title with a "(N)" prefix
        const baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
        document.title = unreadCount > 0
            ? `(${formatCount(unreadCount)}) ${baseTitle}`
            : baseTitle;
    }

    function bumpBell() {
        const bell = refs.bell;
        if (!bell) return;
        bell.classList.remove('badge-bump');
        void bell.offsetWidth; // force reflow so animation restarts
        bell.classList.add('badge-bump');
        setTimeout(() => bell.classList.remove('badge-bump'), 700);
    }

    function setCount(n, options = {}) {
        const { animate = false } = options;
        const next = Math.max(0, parseInt(n, 10) || 0);
        const increased = next > unreadCount;
        unreadCount = next;
        paintCount();
        if (animate && increased) bumpBell();
    }

    function incrementCount(by = 1) {
        setCount(unreadCount + by, { animate: true });
    }

    function decrementCount(by = 1) {
        setCount(unreadCount - by, { animate: false });
    }

    // ---------------------------------------------------------
    // Real-time via Pusher/Echo — the ONLY live source now
    // ---------------------------------------------------------
    let realtimeBound = false;

    function bindRealtime() {
        if (realtimeBound) return true;

        const user = @json(auth()->id());
        if (!user || !window.Echo) return false;

        try {
            window.Echo
                .private(`App.Models.User.${user}`)
                .notification(() => {
                    // A new notification just arrived on our private channel.
                    incrementCount(1);
                });

            realtimeBound = true;
            return true;
        } catch (err) {
            console.warn('[notifications] realtime bind failed:', err);
            return false;
        }
    }

    // Echo may not be ready immediately (it's usually loaded async),
    // so retry a few times before giving up.
    function tryBindRealtimeWithRetry(attempt = 0) {
        if (bindRealtime()) return;

        if (attempt < 10) {
            setTimeout(() => tryBindRealtimeWithRetry(attempt + 1), 500);
        } else {
            console.warn('[notifications] Echo unavailable — badge will only update from client events.');
        }
    }

    // ---------------------------------------------------------
    // App-wide events so other components can tell us to update
    // ---------------------------------------------------------
    window.addEventListener('notifications:read', (e) => {
        const detail = e.detail || {};
        if (typeof detail.count === 'number') {
            setCount(detail.count, { animate: false });
        } else if (typeof detail.decrement === 'number') {
            decrementCount(detail.decrement);
        } else {
            // Full reset (e.g. "mark all as read")
            setCount(0, { animate: false });
        }
    });

    window.addEventListener('notifications:new', () => incrementCount(1));

    // Expose for other scripts / tests
    window.NotificationBadge = {
        get count() { return unreadCount; },
        setCount,
        increment: incrementCount,
        decrement: decrementCount,
    };

    // ---------------------------------------------------------
    // Wire-up
    // ---------------------------------------------------------
    function init() {
        paintCount();          // show server-rendered count immediately
        tryBindRealtimeWithRetry();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endauth