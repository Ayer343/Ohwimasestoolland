{{-- ============================================================
     Sidebar + Header + Modals partial
     Included by: layouts.secu (and equivalents)

     Required from AppServiceProvider::registerSecurityComposers():
       - $canManage  (bool)  — true for head-of-security / supervisor / admin

     ROUTE MAP (security namespace):
       GET /security/dashboard              → SecurityDashboardController@index
                                              (name: security.dashboard)
       GET /security/schedules              → SecurityScheduleController@index
                                              (name: security.schedules.index)
       GET /security/my-schedules           → SecurityScheduleController@mySchedules
                                              (name: security.schedules.my)
       GET /security/supervisor/dashboard   → SupervisorController@dashboard
                                              (name: security.supervisor.dashboard)

       GET /security/rotation-groups        → Security\RotationGroupController@index
                                              (name: security.rotation-groups.index)
       GET /security/rotation-history       → Security\RotationHistoryController@index
                                              (name: security.rotation-history.index)
       GET /security/security-reports       → Security\SecurityReportsController@index
                                              (name: security.security-reports.index)
       GET /security/security-shifts        → Security\SecurityShiftController@index
                                              (name: security.security-shifts.index)

     NAVIGATION LOGIC:
       - Everyone sees "Dashboard" → /security/dashboard
       - Guards see "My Schedules" → /security/my-schedules
       - Head-of-security / supervisors see BOTH:
           * "Schedule Management" → /security/schedules
           * "My Schedule"         → /security/my-schedules
         …plus rotation group / rotation history / shift / report management.
     ============================================================ --}}

@php
    // ---- Capability (single resolver call) ----
    $__caps         = \App\Providers\AppServiceProvider::resolveSecurityCapabilities();
    $canManage      = $canManage    ?? $__caps['canManage'];
    $isSupervisor   = $isSupervisor ?? $__caps['isSupervisor'];
    $isGuard        = $isGuard      ?? $__caps['isGuard'];

    // ---- Route existence checks (defensive) ----
    $__hasDashboard           = \Illuminate\Support\Facades\Route::has('security.dashboard');
    $__hasMySchedules         = \Illuminate\Support\Facades\Route::has('security.schedules.my');
    $__hasScheduleIndex       = \Illuminate\Support\Facades\Route::has('security.schedules.index');
    $__hasSupervisorDashboard = \Illuminate\Support\Facades\Route::has('security.supervisor.dashboard');
    $__hasSupervisorIndex     = \Illuminate\Support\Facades\Route::has('security.supervisor-assignments.index');
    $__hasPersonnelIndex      = \Illuminate\Support\Facades\Route::has('security.personnel.index');
    $__hasNotifications       = \Illuminate\Support\Facades\Route::has('security.notifications.index');
    $__hasPostsIndex          = \Illuminate\Support\Facades\Route::has('security.posts.index');

    // ---- Rotation groups & history (security namespace) ----
    $__hasRotationGroups          = \Illuminate\Support\Facades\Route::has('security.rotation-groups.index');
    $__hasRotationGroupsCreate    = \Illuminate\Support\Facades\Route::has('security.rotation-groups.create');
    $__hasRotationGroupsShow      = \Illuminate\Support\Facades\Route::has('security.rotation-groups.show');
    $__hasRotationGroupsNeeding   = \Illuminate\Support\Facades\Route::has('security.rotation-groups.needing-rotation');
    $__hasRotationHistory         = \Illuminate\Support\Facades\Route::has('security.rotation-history.index');
    $__hasRotationHistoryStats    = \Illuminate\Support\Facades\Route::has('security.rotation-history.statistics');
    $__hasRotationHistoryTimeline = \Illuminate\Support\Facades\Route::has('security.rotation-history.timeline');
    $__hasRotationHistoryExport   = \Illuminate\Support\Facades\Route::has('security.rotation-history.export');
    $__hasRotationHistoryByGroup  = \Illuminate\Support\Facades\Route::has('security.rotation-history.group');

    // ---- Security shifts & reports ----
    $__hasSecurityShifts = \Illuminate\Support\Facades\Route::has('security.security-shifts.index');
    $__hasSecurityReports = \Illuminate\Support\Facades\Route::has('security.security-reports.index');

    // ---- Branding ----
    try {
        $systemSettings = \App\Models\SystemSetting::getSettings();
    } catch (\Throwable $e) {
        $systemSettings = null;
    }
    $systemLogo      = $systemSettings->system_logo ?? null;
    $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Security');
    $systemName      = $systemSettings->system_name ?? config('app.name', 'Laravel');

    $systemLogoUrl = null;
    if ($systemLogo) {
        try {
            $systemLogoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($systemLogo);
        } catch (\Throwable $e) {
            try {
                $systemLogoUrl = \Illuminate\Support\Facades\Storage::url($systemLogo);
            } catch (\Throwable $e2) {
                $systemLogoUrl = null;
            }
        }
    }

    // ---- Unread notification count ----
    if (!isset($sidebarUnreadCount)) {
        $sidebarUnreadCount = auth()->check()
            ? auth()->user()->unreadNotifications()->count()
            : 0;
    }

    // ---- Badge data (only queried for supervisors) ----
    $activeSupervisorAssignments = 0;
    $totalAssignments            = 0;
    $personnelCount              = 0;
    $pendingRotationsCount       = 0;
    $rotationGroupsCount         = 0;

    if ($canManage && auth()->check()) {
        try {
            $activeSupervisorAssignments = \App\Models\SecuritySupervisorAssignment::where('user_id', auth()->id())
                ->where('is_active', true)->count();
        } catch (\Throwable $e) { $activeSupervisorAssignments = 0; }

        try {
            $totalAssignments = \App\Models\SecuritySupervisorAssignment::where('user_id', auth()->id())->count();
        } catch (\Throwable $e) { $totalAssignments = 0; }

        try {
            $personnelCount = \App\Models\User::where('type', \App\Models\User::TYPE_SECURITY_PERSONNEL)
                ->where('status', \App\Models\User::STATUS_ACTIVE)->count();
        } catch (\Throwable $e) { $personnelCount = 0; }

        // Pending rotations count (only if the model exists)
        if (class_exists(\App\Models\RotationGroup::class)) {
            try {
                $rotationGroupsCount = \App\Models\RotationGroup::where('status', 'active')->count();
            } catch (\Throwable $e) { $rotationGroupsCount = 0; }

            try {
                $pendingRotationsCount = \App\Models\RotationGroup::where('status', 'active')
                    ->whereNotNull('rotation_config')
                    ->whereRaw('JSON_EXTRACT(rotation_config, "$.next_rotation_date") IS NOT NULL')
                    ->whereRaw('JSON_EXTRACT(rotation_config, "$.next_rotation_date") <= ?', [now()->addDays(3)->format('Y-m-d')])
                    ->count();
            } catch (\Throwable $e) { $pendingRotationsCount = 0; }
        }
    }

    // ---- Role-aware schedule entries ----
    if ($canManage) {
        $__scheduleManagerHref = $__hasScheduleIndex
            ? route('security.schedules.index')
            : ($__hasMySchedules ? route('security.schedules.my') : '#');
    } else {
        $__scheduleManagerHref = $__hasMySchedules
            ? route('security.schedules.my')
            : ($__hasScheduleIndex ? route('security.schedules.index') : '#');
    }

    $__myScheduleHref = $__hasMySchedules
        ? route('security.schedules.my')
        : ($__hasScheduleIndex ? route('security.schedules.index') : '#');

    // Split active flags
    $__scheduleManagerActive =
           request()->routeIs('security.schedules.index')
        || request()->routeIs('security.schedules.trash')
        || request()->routeIs('security.schedules.trash.*')
        || request()->routeIs('security.schedules.calendar')
        || request()->routeIs('security.schedules.date-details')
        || request()->routeIs('security.schedules.create')
        || request()->routeIs('security.schedules.edit')
        || request()->routeIs('security.schedules.show')
        || request()->routeIs('security.schedules.update-status*')
        || request()->routeIs('security.schedules.bulk-*')
        || request()->routeIs('security.schedules.rotate-shift-groups');

    $__myScheduleActive =
           request()->routeIs('security.schedules.my')
        || request()->routeIs('security.schedule.show')
        || request()->routeIs('security.schedules.checkin')
        || request()->routeIs('security.schedules.checkout')
        || request()->routeIs('security.schedules.smart-checkin')
        || request()->routeIs('security.schedules.smart-checkout');

    // Rotation active flags
    $__rotationGroupsActive  = request()->routeIs('security.rotation-groups.*');
    $__rotationHistoryActive = request()->routeIs('security.rotation-history.*');
    $__securityShiftsActive  = request()->routeIs('security.security-shifts.*');
    $__securityReportsActive = request()->routeIs('security.security-reports.*');

    $__dashboardHref = $__hasDashboard ? route('security.dashboard') : '#';
@endphp

<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="logo-section">
        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogoUrl)
                    <div class="logo-image-container">
                        <img src="{{ $systemLogoUrl }}"
                             alt="{{ $systemName }}"
                             class="logo-image"
                             onerror="this.style.display='none'; var f=document.getElementById('logoFallback'); if(f) f.style.display='flex';">
                        <div id="logoFallback" class="logo-fallback" style="display: none;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                @else
                    <div class="logo-default">
                        <i class="fas fa-shield-alt"></i>
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

    <div class="logo-separator"></div>

    <!-- Navigation -->
    <nav class="mt-2">
        <div class="nav-divider">
            <span class="menu-text">MAIN NAVIGATION</span>
        </div>

        {{-- GENERAL DASHBOARD --}}
        @if($__hasDashboard)
            <a href="{{ $__dashboardHref }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home mr-4"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        @endif

        {{-- SCHEDULE ENTRIES --}}
        @if($canManage)
            <a href="{{ $__scheduleManagerHref }}"
               class="nav-item flex items-center py-2 px-6 {{ $__scheduleManagerActive ? 'active' : '' }}">
                <i class="fas fa-calendar-check mr-4"></i>
                <span class="nav-text">Schedule Management</span>
            </a>

            @if($__hasMySchedules)
                <a href="{{ $__myScheduleHref }}"
                   class="nav-item flex items-center py-2 px-6 {{ $__myScheduleActive ? 'active' : '' }}">
                    <i class="fas fa-user-clock mr-4"></i>
                    <span class="nav-text">My Schedule</span>
                </a>
            @endif
        @else
            <a href="{{ $__scheduleManagerHref }}"
               class="nav-item flex items-center py-2 px-6 {{ $__myScheduleActive ? 'active' : '' }}">
                <i class="fas fa-calendar-alt mr-4"></i>
                <span class="nav-text">My Schedules</span>
            </a>
        @endif

        {{-- SUPERVISOR-ONLY NAVIGATION --}}
        @if($canManage)
            @if($__hasSupervisorDashboard)
                <a href="{{ route('security.supervisor.dashboard') }}"
                   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-user-tie mr-4"></i>
                    <span class="nav-text">Head of Security Dashboard</span>
                    @if($activeSupervisorAssignments > 0)
                        <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                            {{ $activeSupervisorAssignments > 9 ? '9+' : $activeSupervisorAssignments }}
                        </span>
                    @endif
                </a>
            @endif

            {{-- ============================================================
                 ROTATION GROUPS & HISTORY — first-class sidebar entries
                 These replace the old admin.rotation-groups.* references.
                 ============================================================ --}}
            @if($__hasRotationGroups || $__hasRotationHistory)
                <div class="nav-divider mt-1">
                    <span class="menu-text">ROTATION MANAGEMENT</span>
                </div>

                @if($__hasRotationGroups)
                    <a href="{{ route('security.rotation-groups.index') }}"
                       class="nav-item flex items-center py-2 px-6 {{ $__rotationGroupsActive ? 'active' : '' }}">
                        <i class="fas fa-sync-alt mr-4"></i>
                        <span class="nav-text">Rotation Groups</span>
                        @if($pendingRotationsCount > 0)
                            <span class="ml-auto bg-orange-500 text-white text-xs px-2 py-1 rounded-full" title="{{ $pendingRotationsCount }} groups need rotation in next 3 days">
                                {{ $pendingRotationsCount > 9 ? '9+' : $pendingRotationsCount }}
                            </span>
                        @elseif($rotationGroupsCount > 0)
                            <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                                {{ $rotationGroupsCount > 9 ? '9+' : $rotationGroupsCount }}
                            </span>
                        @endif
                    </a>
                @endif

                @if($__hasRotationHistory)
                    <a href="{{ route('security.rotation-history.index') }}"
                       class="nav-item flex items-center py-2 px-6 {{ $__rotationHistoryActive ? 'active' : '' }}">
                        <i class="fas fa-history mr-4"></i>
                        <span class="nav-text">Rotation History</span>
                    </a>
                @endif
            @endif

            {{-- Supervisor Assignments --}}
            <div class="nav-divider mt-1">
                <span class="menu-text">SUPERVISOR ASSIGNMENTS</span>
            </div>

            @if($__hasSupervisorIndex)
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor-assignments.*') ? 'active' : '' }}">
                    <i class="fas fa-tasks mr-4"></i>
                    <span class="nav-text">My Appointments</span>
                    @if($totalAssignments > 0)
                        <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                            {{ $totalAssignments > 9 ? '9+' : $totalAssignments }}
                        </span>
                    @endif
                </a>
            @endif

            {{-- Personnel Management --}}
            <div class="nav-divider mt-1">
                <span class="menu-text">PERSONNEL MANAGEMENT</span>
            </div>

            @if($__hasPersonnelIndex)
                <a href="{{ route('security.personnel.index') }}"
                   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.personnel.index') ? 'active' : '' }}">
                    <i class="fas fa-users mr-4"></i>
                    <span class="nav-text">All Personnel</span>
                    @if($personnelCount > 0)
                        <span class="ml-auto bg-indigo-500 text-white text-xs px-2 py-1 rounded-full">
                            {{ $personnelCount > 9 ? '9+' : $personnelCount }}
                        </span>
                    @endif
                </a>
            @endif

            {{-- Shift Management --}}
            @if($__hasSecurityShifts)
                <div class="nav-divider mt-1">
                    <span class="menu-text">SHIFT MANAGEMENT</span>
                </div>

                <a href="{{ route('security.security-shifts.index') }}"
                   class="nav-item flex items-center py-2 px-6 {{ $__securityShiftsActive ? 'active' : '' }}">
                    <i class="fas fa-clock mr-4"></i>
                    <span class="nav-text">Shift Templates</span>
                </a>
            @endif

            {{-- Reports --}}
            @if($__hasSecurityReports)
                <a href="{{ route('security.security-reports.index') }}"
                   class="nav-item flex items-center py-2 px-6 {{ $__securityReportsActive ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list mr-4"></i>
                    <span class="nav-text">Reports</span>
                </a>
            @endif
        @endif

        {{-- COMMON SECURITY FEATURES --}}
        @if($__hasPostsIndex)
            <a href="{{ route('security.posts.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.posts.*') ? 'active' : '' }}">
                <i class="fas fa-building mr-4"></i>
                <span class="nav-text">Security Posts</span>
            </a>
        @endif

        <a href="#" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.visitor-logs.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield mr-4"></i>
            <span class="nav-text">Visitor Logs</span>
        </a>

        <a href="#" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.incidents.*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list mr-4"></i>
            <span class="nav-text">Incident Reports</span>
        </a>

        <a href="#" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.surveillance.*') ? 'active' : '' }}">
            <i class="fas fa-camera mr-4"></i>
            <span class="nav-text">Surveillance</span>
        </a>

        @if($__hasNotifications)
            <a href="{{ route('security.notifications.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('*.notifications.*') ? 'active' : '' }}"
               id="sidebarNotificationsLink">
                <i class="fas fa-bell mr-4"></i>
                <span class="nav-text">Notifications</span>
                <span class="ml-auto" id="sidebarNotificationBadge" style="display: none;">
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"></span>
                </span>
            </a>
        @endif

        {{-- TOOLS --}}
        <div class="nav-divider mt-1">
            <span class="menu-text">TOOLS</span>
        </div>

        <button type="button" id="scheduleToolsTrigger"
                class="nav-item flex items-center py-2 px-6 w-full text-left {{
                    request()->routeIs('security.schedules.preferences')
                    || request()->routeIs('security.schedules.availability*')
                    || request()->routeIs('security.schedules.upcoming-handovers')
                    || request()->routeIs('security.schedules.statistics')
                        ? 'active' : '' }}">
            <i class="fas fa-tools mr-4"></i>
            <span class="nav-text">Schedule Tools</span>
            <i class="fas fa-chevron-right ml-auto text-xs opacity-50"></i>
        </button>
    </nav>

    <!-- Settings Button -->
    <div class="absolute bottom-0 w-full p-3">
        <button type="button" id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
</div>

<!-- ============================================================
     SCHEDULE TOOLS MODAL
     ============================================================ -->
<div id="scheduleToolsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="schedule-tools-modal">
        <div class="schedule-tools-header flex justify-between items-center">
            <h3 class="text-base font-semibold">
                <i class="fas fa-tools mr-2"></i>Schedule Tools
            </h3>
            <button type="button" id="closeScheduleToolsModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="schedule-tools-body">
            <p class="schedule-tools-description">Select a schedule management tool to access</p>

            <div class="schedule-tools-grid">
                <div class="tools-section">
                    <h4 class="tools-section-title">
                        <i class="fas fa-calendar-alt mr-2"></i>Schedule Management
                    </h4>
                    <div class="tools-section-grid">
                        @if($__hasMySchedules)
                            <a href="{{ $__myScheduleHref }}"
                               class="schedule-tool-item {{ $__myScheduleActive ? 'active' : '' }}">
                                <div class="tool-icon-wrapper"><i class="fas fa-user-clock"></i></div>
                                <div class="tool-content">
                                    <h4 class="tool-title">My Schedule</h4>
                                    <p class="tool-description">Your personal shifts, check-ins, and check-outs</p>
                                </div>
                                <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                            </a>
                        @endif

                        <a href="{{ route('security.schedules.preferences') }}"
                           class="schedule-tool-item {{ request()->routeIs('security.schedules.preferences') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper"><i class="fas fa-sliders-h"></i></div>
                            <div class="tool-content">
                                <h4 class="tool-title">Shift Preferences</h4>
                                <p class="tool-description">Set your preferred shifts and working hours</p>
                            </div>
                            <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                        </a>

                        <a href="{{ route('security.schedules.availability') }}"
                           class="schedule-tool-item {{ request()->routeIs('security.schedules.availability*') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper"><i class="fas fa-clock"></i></div>
                            <div class="tool-content">
                                <h4 class="tool-title">Availability</h4>
                                <p class="tool-description">Manage your available time slots</p>
                            </div>
                            <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                        </a>

                        <a href="{{ route('security.schedules.upcoming-handovers') }}"
                           class="schedule-tool-item {{ request()->routeIs('security.schedules.upcoming-handovers') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper"><i class="fas fa-exchange-alt"></i></div>
                            <div class="tool-content">
                                <h4 class="tool-title">Upcoming Handovers</h4>
                                <p class="tool-description">View and manage shift handovers</p>
                            </div>
                            <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                        </a>
                    </div>
                </div>

                @if($canManage)
                    <div class="tools-section">
                        <h4 class="tools-section-title">
                            <i class="fas fa-sync-alt mr-2"></i>Rotation Management
                        </h4>
                        <div class="tools-section-grid">
                            @if($__hasRotationGroups)
                                <a href="{{ route('security.rotation-groups.index') }}"
                                   class="schedule-tool-item {{ $__rotationGroupsActive ? 'active' : '' }}">
                                    <div class="tool-icon-wrapper"><i class="fas fa-users-cog"></i></div>
                                    <div class="tool-content">
                                        <h4 class="tool-title">Rotation Groups</h4>
                                        <p class="tool-description">Manage shift rotation groups and members</p>
                                    </div>
                                    <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                                </a>
                            @endif

                            @if($__hasRotationHistory)
                                <a href="{{ route('security.rotation-history.index') }}"
                                   class="schedule-tool-item {{ $__rotationHistoryActive ? 'active' : '' }}">
                                    <div class="tool-icon-wrapper analytics-icon"><i class="fas fa-history"></i></div>
                                    <div class="tool-content">
                                        <h4 class="tool-title">Rotation History</h4>
                                        <p class="tool-description">View past rotations, statistics, and trends</p>
                                    </div>
                                    <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                                </a>
                            @endif

                            @if($__hasRotationHistoryTimeline)
                                <a href="{{ route('security.rotation-history.timeline') }}"
                                   class="schedule-tool-item {{ request()->routeIs('security.rotation-history.timeline') ? 'active' : '' }}">
                                    <div class="tool-icon-wrapper"><i class="fas fa-stream"></i></div>
                                    <div class="tool-content">
                                        <h4 class="tool-title">Rotation Timeline</h4>
                                        <p class="tool-description">Chronological view of all rotation events</p>
                                    </div>
                                    <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="tools-section">
                        <h4 class="tools-section-title">
                            <i class="fas fa-chart-pie mr-2"></i>Analytics & Insights
                        </h4>
                        <div class="tools-section-grid">
                            <a href="{{ route('security.schedules.statistics') }}"
                               class="schedule-tool-item {{ request()->routeIs('security.schedules.statistics') ? 'active' : '' }}">
                                <div class="tool-icon-wrapper analytics-icon"><i class="fas fa-chart-pie"></i></div>
                                <div class="tool-content">
                                    <h4 class="tool-title">Schedule Analytics</h4>
                                    <p class="tool-description">View schedule statistics, trends, and insights</p>
                                </div>
                                <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                            </a>

                            @if($__hasRotationHistoryStats)
                                <a href="{{ route('security.rotation-history.statistics') }}"
                                   class="schedule-tool-item {{ request()->routeIs('security.rotation-history.statistics') ? 'active' : '' }}">
                                    <div class="tool-icon-wrapper analytics-icon"><i class="fas fa-chart-bar"></i></div>
                                    <div class="tool-content">
                                        <h4 class="tool-title">Rotation Statistics</h4>
                                        <p class="tool-description">Detailed rotation metrics and success rates</p>
                                    </div>
                                    <div class="tool-arrow"><i class="fas fa-chevron-right"></i></div>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="schedule-tools-footer flex justify-end">
            <button type="button" id="closeScheduleToolsBtn" class="px-4 py-2 text-sm font-medium transition-colors duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     THEME SETTINGS MODAL
     ============================================================ -->
<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="theme-modal-compact">
        <div class="theme-modal-header-compact flex justify-between items-center">
            <h3 class="text-base font-semibold">
                <i class="fas fa-palette mr-2"></i>Theme Settings
            </h3>
            <button type="button" id="closeThemeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
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
            <button type="button" id="closeThemeModalBtn" class="px-3 py-1.5 text-sm font-medium transition-colors duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     SEARCH MODAL
     ============================================================ -->
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden pt-20">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl">
        <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-search mr-2"></i>Security Search
            </h3>
            <button type="button" id="closeSearchModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Term</label>
                <input type="text" id="globalSearchInput" placeholder="Enter visitor name, ID, or incident..."
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Category</label>
                <select id="searchCategory" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="all">All Categories</option>
                    <option value="visitors">Visitors</option>
                    <option value="incidents">Incident Reports</option>
                    <option value="properties">Properties</option>
                    <option value="surveillance">Surveillance</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Filters</label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="filterExactMatch" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Exact Match</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="filterCaseSensitive" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Case Sensitive</span>
                    </label>
                </div>
            </div>

            <div id="recentSearches" class="hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recent Searches</label>
                <div id="recentSearchesList" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex justify-between">
            <button type="button" id="clearSearch" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                Clear
            </button>
            <div class="flex space-x-3">
                <button type="button" id="cancelSearch" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                    Cancel
                </button>
                <button type="button" id="performSearch" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     NOTIFICATIONS DROPDOWN
     ============================================================ -->
<div id="notificationsDropdown" class="notifications-dropdown hidden">
    <div class="notifications-header">
        <h3>Notifications</h3>
        <div class="notifications-actions">
            <button type="button" id="markAllReadBtn" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                Mark all as read
            </button>
            <button type="button" id="clearAllNotificationsBtn" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">
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
        @if($__hasNotifications)
            <a href="{{ route('security.notifications.index') }}" class="view-all-link">
                View all notifications →
            </a>
        @endif
    </div>
</div>

<!-- ============================================================
     MAIN CONTENT HEADER
     ============================================================ -->
<div class="content" id="mainContent">
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button type="button" id="toggleSidebarMobile" class="mobile-menu-btn mr-4 text-gray-600">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                @if($canManage && request()->routeIs('security.supervisor.*'))
                    @yield('title', 'Head of Security')
                @else
                    @yield('title', 'Security Dashboard')
                @endif
            </h2>
        </div>

        <div class="flex items-center space-x-4">
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        <input type="text" id="quickSearchInput" placeholder="Search visitors or incidents..."
                               class="header-search pl-10 pr-10"
                               style="color: var(--text-primary); background-color: var(--bg-secondary); border: 1px solid transparent;"
                               data-toggle="tooltip" title="Press / to focus">
                        <button type="button" id="advancedSearchBtn" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>
                    </div>

                    <button type="button" id="mobileSearchBtn" class="md:hidden p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                        <i class="fas fa-search text-gray-600 dark:text-gray-300"></i>
                    </button>
                </div>
            </div>

            <div class="header-buttons flex items-center space-x-2">
                <button type="button" class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                    <i class="fas fa-exclamation-triangle text-gray-600 dark:text-gray-300"></i>
                    <span class="notification-dot bg-red-500"></span>
                </button>

                <div class="relative">
                    <button type="button" id="notificationBellBtn" class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="far fa-bell text-gray-600 dark:text-gray-300 text-xl"></i>
                        <span id="notificationBadge" class="notification-badge hidden">
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="dropdown relative">
                <button type="button" id="userMenuButton" class="flex items-center space-x-2">
                    <div class="avatar-minimal">
                        @if(Auth::user()->photo)
                            <img src="{{ Storage::disk('public')->url('users/photos/' . Auth::user()->photo) }}"
                                 alt="{{ Auth::user()->name }}"
                                 class="w-8 h-8 rounded-full object-cover"
                                 onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.png') }}';">
                        @else
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                                {{ substr(Auth::user()->name, 0, 2) }}
                            </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                </button>

                <div id="userDropdown" class="dropdown-menu" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>

                    @if($__hasDashboard)
                        <a href="{{ $__dashboardHref }}" class="dropdown-item">
                            <i class="fas fa-home mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">General Dashboard</span>
                        </a>
                    @endif

                    @if($__hasMySchedules)
                        <a href="{{ $__myScheduleHref }}" class="dropdown-item">
                            <i class="fas fa-user-clock mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">My Schedule</span>
                        </a>
                    @endif

                    @if($__hasNotifications)
                        <a href="{{ route('security.notifications.index') }}" class="dropdown-item" id="dropdownNotificationsLink">
                            <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">Notifications</span>
                            <span id="dropdownNotificationBadge" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full hidden">0</span>
                        </a>
                    @endif

                    @if($canManage && $__hasSupervisorDashboard)
                        <a href="{{ route('security.supervisor.dashboard') }}" class="dropdown-item" id="dropdownSupervisorLink">
                            <i class="fas fa-user-tie mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">Head of Security</span>
                            @if($activeSupervisorAssignments > 0)
                                <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                                    {{ $activeSupervisorAssignments }}
                                </span>
                            @endif
                        </a>
                        <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                    @endif

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

    <main class="p-6">
        {{-- $slot / @yield('content') in the layout that includes this partial --}}

<style>
/* =====================================================
   ENHANCED MINIMAL AVATAR
   ===================================================== */
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
.avatar-minimal:hover { transform: scale(1.05); border-color: var(--primary); }
.avatar-minimal img {
    width: 100%; height: 100%; border-radius: 50%;
    object-fit: cover; border: 1px solid var(--border-color);
}

/* HEADER */
.header { padding: 12px 24px; }
.header-search { min-width: 280px; }
#mobileSearchBtn { padding: 8px; }
.header-buttons { gap: 8px; }
.dropdown-menu { min-width: 220px; }
.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}
#advancedSearchBtn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: scale(1.1);
}

/* NOTIFICATIONS DROPDOWN */
.notifications-dropdown {
    position: absolute; top: 60px; right: 20px;
    width: 380px; max-width: calc(100vw - 40px);
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    border: 1px solid var(--border-color);
    overflow: hidden;
}
.notifications-dropdown.hidden { display: none; }
.notifications-header {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
}
.notifications-header h3 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
.notifications-actions { display: flex; gap: 12px; }
.notifications-actions button { background: none; border: none; cursor: pointer; padding: 0; }
.notifications-list { max-height: 400px; overflow-y: auto; }
.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    cursor: pointer;
}
.notification-item:hover { background-color: var(--bg-secondary); }
.notification-item.unread { background-color: rgba(59, 130, 246, 0.05); }
.notification-item.unread:hover { background-color: rgba(59, 130, 246, 0.1); }
.notification-icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-secondary);
}
.notification-content { flex: 1; }
.notification-title { font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
.notification-message { font-size: 13px; color: var(--text-secondary); line-height: 1.4; }
.notification-time { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }
.notifications-loading { padding: 32px; text-align: center; color: var(--text-secondary); }
.notifications-empty { padding: 48px 32px; text-align: center; color: var(--text-secondary); }
.notifications-empty i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
.notifications-footer { padding: 12px 16px; border-top: 1px solid var(--border-color); text-align: center; }
.view-all-link { font-size: 13px; color: #3b82f6; text-decoration: none; font-weight: 500; }
.view-all-link:hover { text-decoration: underline; }
.notification-badge { position: relative; }

@media (max-width: 768px) {
    .header { padding: 10px 16px; }
    .avatar-minimal { width: 28px; height: 28px; }
    .header-buttons { gap: 6px; }
    .notifications-dropdown {
        position: fixed; top: 60px; right: 10px; left: 10px;
        width: auto; max-width: none;
    }
}

/* THEME SETTINGS MODAL */
.theme-modal-compact {
    background: white; border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%; max-width: 400px; max-height: 90vh;
    overflow-y: auto;
    animation: modalFadeIn 0.3s ease-out;
}
.theme-modal-header-compact { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; }
.theme-modal-body-compact { padding: 20px; }
.theme-modal-footer-compact { padding: 16px 20px; border-top: 1px solid #e5e7eb; }
.section-header-compact { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 4px; }
.section-description-compact { font-size: 12px; color: #6b7280; margin-bottom: 16px; }
.appearance-grid-compact { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.appearance-option-compact {
    display: flex; flex-direction: column; align-items: center;
    cursor: pointer; padding: 12px 8px; border-radius: 8px;
    transition: all 0.2s ease; border: 2px solid transparent; position: relative;
}
.appearance-option-compact:hover { background-color: #f9fafb; transform: translateY(-2px); }
.appearance-option-compact.active { border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.08); }
.appearance-icon-compact {
    width: 48px; height: 48px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 8px; transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.appearance-label-compact { font-size: 12px; font-weight: 500; color: #374151; }
.theme-grid-compact { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
.theme-option-compact {
    display: flex; flex-direction: column; align-items: center;
    cursor: pointer; padding: 8px 4px; border-radius: 6px;
    transition: all 0.2s ease; border: 2px solid transparent; position: relative;
}
.theme-option-compact:hover { background-color: #f9fafb; transform: translateY(-2px); }
.theme-option-compact.active { border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.08); }
.theme-preview-compact {
    width: 40px; height: 40px; border-radius: 6px;
    margin-bottom: 6px; transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
.theme-label-compact { font-size: 11px; font-weight: 500; color: #374151; }

/* SCHEDULE TOOLS MODAL */
.schedule-tools-modal {
    background: white; border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 90%; max-width: 700px; max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideUp 0.3s ease-out;
}
.schedule-tools-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
.schedule-tools-body { padding: 24px; }
.schedule-tools-description { font-size: 14px; color: #6b7280; margin-bottom: 20px; }
.schedule-tools-grid { display: flex; flex-direction: column; gap: 24px; margin-bottom: 24px; }
.tools-section { background: #f9fafb; border-radius: 16px; padding: 16px; }
.tools-section-title {
    font-size: 14px; font-weight: 600; color: #374151;
    margin-bottom: 16px; display: flex; align-items: center;
}
.tools-section-grid { display: flex; flex-direction: column; gap: 8px; }
.schedule-tool-item {
    display: flex; align-items: center; padding: 16px;
    background: white; border-radius: 12px;
    transition: all 0.2s ease; border: 2px solid transparent;
    text-decoration: none; color: inherit;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.schedule-tool-item:hover { transform: translateX(4px); border-color: var(--primary); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
.schedule-tool-item.active { background: rgba(var(--primary-rgb), 0.04); border-color: var(--primary); }
.tool-icon-wrapper {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin-right: 16px; color: white; font-size: 20px;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}
.tool-icon-wrapper.analytics-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.tool-content { flex: 1; }
.tool-title { font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 4px; }
.tool-description { font-size: 13px; color: #6b7280; }
.tool-arrow { color: #9ca3af; font-size: 14px; transition: all 0.2s ease; margin-left: 12px; }
.schedule-tool-item:hover .tool-arrow { color: var(--primary); transform: translateX(4px); }
.analytics-preview {
    margin-top: 12px; padding: 16px;
    background: white; border-radius: 12px; border: 1px solid #e5e7eb;
}
.preview-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
.preview-stat-item { text-align: center; }
.preview-stat-label { display: block; font-size: 11px; color: #6b7280; margin-bottom: 4px; }
.preview-stat-value { font-size: 16px; font-weight: 600; color: #111827; }
.preview-link {
    display: block; text-align: center; font-size: 13px;
    color: var(--primary); text-decoration: none;
    padding: 8px; border-radius: 8px;
    background: rgba(var(--primary-rgb), 0.04);
    transition: all 0.2s ease;
}
.preview-link:hover { background: rgba(var(--primary-rgb), 0.08); }
.schedule-tools-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
    padding: 20px; background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    border-radius: 16px;
}
.stat-item { text-align: center; }
.stat-label { display: block; font-size: 12px; color: #4b5563; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; color: #111827; }
.schedule-tools-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; }

/* DARK MODE */
[data-theme="dark"] .schedule-tools-modal { background: #1f2937; color: white; }
[data-theme="dark"] .schedule-tools-header,
[data-theme="dark"] .schedule-tools-footer { border-color: #374151; }
[data-theme="dark"] .schedule-tools-description { color: #9ca3af; }
[data-theme="dark"] .tools-section { background: #2d3748; }
[data-theme="dark"] .tools-section-title { color: #f9fafb; }
[data-theme="dark"] .schedule-tool-item { background: #1f2937; }
[data-theme="dark"] .schedule-tool-item:hover { background: #2d3748; }
[data-theme="dark"] .tool-title { color: #f9fafb; }
[data-theme="dark"] .tool-description { color: #9ca3af; }
[data-theme="dark"] .analytics-preview { background: #2d3748; border-color: #4b5563; }
[data-theme="dark"] .preview-stat-label { color: #9ca3af; }
[data-theme="dark"] .preview-stat-value { color: #f9fafb; }
[data-theme="dark"] .schedule-tools-stats { background: linear-gradient(135deg, #2d3748, #1f2937); }
[data-theme="dark"] .stat-label { color: #9ca3af; }
[data-theme="dark"] .stat-value { color: #f9fafb; }
[data-theme="dark"] .theme-modal-compact { background: #1f2937; color: white; }
[data-theme="dark"] .theme-modal-header-compact,
[data-theme="dark"] .theme-modal-footer-compact { border-color: #374151; }
[data-theme="dark"] .section-header-compact { color: #f9fafb; }
[data-theme="dark"] .section-description-compact { color: #9ca3af; }
[data-theme="dark"] .appearance-option-compact:hover { background-color: #374151; }
[data-theme="dark"] .appearance-label-compact,
[data-theme="dark"] .theme-label-compact { color: #f9fafb; }
[data-theme="dark"] .theme-option-compact:hover { background-color: #374151; }
[data-theme="dark"] .appearance-option-compact.active,
[data-theme="dark"] .theme-option-compact.active { background-color: rgba(var(--primary-rgb), 0.2); }
[data-theme="dark"] .avatar-minimal { border-color: #4b5563; }
[data-theme="dark"] .avatar-minimal:hover { border-color: var(--primary); }

/* KEYFRAMES */
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes notificationPulse {
    0%   { transform: scale(1); opacity: 1; }
    50%  { transform: scale(1.1); opacity: 0.85; }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes bellRing {
    0%   { transform: rotate(0); }
    25%  { transform: rotate(15deg); }
    50%  { transform: rotate(-15deg); }
    75%  { transform: rotate(10deg); }
    100% { transform: rotate(0); }
}
.current-selection {
    position: absolute; top: 8px; right: 8px;
    width: 20px; height: 20px;
    background: var(--primary); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
.notification-dot.bg-red-500 { background-color: #ef4444; }
.avatar-minimal, #advancedSearchBtn, #mobileSearchBtn { transition: all 0.2s ease-in-out; }

/* SIDEBAR COLLAPSE */
:root {
    --sidebar-collapsed-width: 60px;
    --sidebar-toggle-offset: -14px;
    --sidebar-toggle-size: 28px;
}
.sidebar.collapsed { width: var(--sidebar-collapsed-width) !important; min-width: var(--sidebar-collapsed-width) !important; max-width: var(--sidebar-collapsed-width) !important; }
.sidebar.collapsed .nav-divider { display: none !important; visibility: hidden !important; height: 0 !important; min-height: 0 !important; padding: 0 !important; margin: 0 !important; border: 0 !important; }
.sidebar.collapsed nav { padding: 4px 0 !important; }
.sidebar.collapsed .nav-item {
    padding: 8px 0 !important; margin: 2px 4px !important;
    border-radius: 8px !important; justify-content: center !important;
    width: calc(100% - 8px) !important; min-height: 40px !important;
    display: flex !important; align-items: center !important;
}
.sidebar.collapsed .nav-item i { margin: 0 !important; font-size: 1.15rem !important; }
.sidebar.collapsed .nav-item .nav-text,
.sidebar.collapsed .nav-item span.ml-auto,
.sidebar.collapsed .nav-item .ml-auto,
.sidebar.collapsed .nav-item .badge,
.sidebar.collapsed .nav-item .chevron-right,
.sidebar.collapsed .nav-item .flex-grow,
.sidebar.collapsed .nav-item .menu-text { display: none !important; }
.sidebar.collapsed .nav-item .bg-yellow-500,
.sidebar.collapsed .nav-item .bg-red-500,
.sidebar.collapsed .nav-item .bg-blue-500,
.sidebar.collapsed .nav-item .bg-orange-500 { display: none !important; }
.sidebar.collapsed .logo-section { padding: 10px 6px !important; position: relative !important; }
.sidebar.collapsed .logo-text-container,
.sidebar.collapsed .logo-fullname,
.sidebar.collapsed .logo-shortname { display: none !important; }
.sidebar.collapsed .logo-wrapper { justify-content: center !important; }
.sidebar.collapsed .logo-image-container,
.sidebar.collapsed .logo-default { width: 32px !important; height: 32px !important; }
.sidebar.collapsed .logo-separator { margin: 4px 8px !important; }
.sidebar.collapsed .toggle-sidebar {
    display: flex !important; position: absolute !important;
    right: var(--sidebar-toggle-offset) !important; top: 50% !important;
    transform: translateY(-50%) !important; z-index: 100 !important;
    background-color: var(--primary) !important; border-color: var(--primary) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
    width: var(--sidebar-toggle-size) !important; height: var(--sidebar-toggle-size) !important;
    border-radius: 50% !important;
}
.sidebar.collapsed .toggle-sidebar:hover {
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    background-color: var(--secondary) !important;
}
.sidebar.collapsed .toggle-sidebar i { transform: rotate(180deg); font-size: 12px !important; }
.sidebar.collapsed .absolute.bottom-0 { padding: 6px 0 !important; }
.sidebar.collapsed #settingsIcon { font-size: 1rem !important; }
.sidebar.collapsed .nav-item.active { background: rgba(var(--primary-rgb), 0.15) !important; box-shadow: inset 3px 0 0 var(--primary) !important; }
.sidebar.collapsed .nav-divider + .nav-item { margin-top: 2px !important; }
.sidebar.collapsed .nav-item + .nav-item { margin-top: 2px !important; }
.sidebar.collapsed .nav-item button,
.sidebar.collapsed .nav-item a {
    display: flex !important; align-items: center !important; justify-content: center !important;
    width: 100% !important; padding: 0 !important;
}

/* TOGGLE BUTTON — EXPANDED MODE */
.toggle-sidebar {
    cursor: pointer;
    display: flex !important; align-items: center; justify-content: center;
    width: var(--sidebar-toggle-size); height: var(--sidebar-toggle-size);
    border-radius: 50%;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.25);
    color: var(--sidebar-text, #ffffff);
    position: absolute; right: var(--sidebar-toggle-offset); top: 50%;
    transform: translateY(-50%); z-index: 100;
    background-color: var(--primary); border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
.toggle-sidebar:hover {
    transform: translateY(-50%) scale(1.1);
    background-color: var(--secondary) !important; border-color: var(--secondary) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
.toggle-sidebar i { font-size: 12px; transition: transform 0.3s ease; }
.logo-section { position: relative !important; }
[data-sidebar-theme="dark"] .toggle-sidebar,
[data-sidebar-theme="default"] .toggle-sidebar,
[data-sidebar-theme="blue"] .toggle-sidebar,
[data-sidebar-theme="green"] .toggle-sidebar,
[data-sidebar-theme="light"] .toggle-sidebar {
    background-color: var(--primary); border-color: var(--primary); color: #ffffff;
}

/* SIDEBAR THEMES */
.sidebar,
.sidebar .nav-item,
.sidebar .logo-shortname,
.sidebar .logo-fullname,
.sidebar .nav-text,
.sidebar a:not(.active),
.sidebar button:not(.active) { color: var(--sidebar-text, #ffffff) !important; transition: color 0.2s ease; }
.sidebar .nav-item i,
.sidebar button i { color: var(--sidebar-text, #ffffff) !important; opacity: 0.8; transition: all 0.2s ease; }
[data-sidebar-theme="default"] { --sidebar-text: #ffffff; }
[data-sidebar-theme="dark"]    { --sidebar-text: #e2e8f0; }
[data-sidebar-theme="light"]   { --sidebar-text: #1e293b; }
[data-sidebar-theme="blue"]    { --sidebar-text: #ffffff; }
[data-sidebar-theme="green"]   { --sidebar-text: #ffffff; }
.sidebar:not([data-sidebar-theme]) { --sidebar-text: #ffffff; }
.sidebar .nav-item:hover,
.sidebar button:hover { color: var(--sidebar-text-hover, var(--primary)) !important; }
.sidebar .nav-item:hover i,
.sidebar button:hover i { color: var(--sidebar-text-hover, var(--primary)) !important; opacity: 1; }
.sidebar .nav-item.active,
.sidebar button.active { color: var(--primary) !important; background-color: rgba(var(--primary-rgb), 0.15) !important; }
.sidebar .nav-item.active i,
.sidebar button.active i { color: var(--primary) !important; opacity: 1; }

/* MOBILE */
.overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040; opacity: 0; transition: opacity 0.3s ease;
}
.overlay.active { display: block; opacity: 1; }

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%); transition: transform 0.3s ease;
        position: fixed !important; top: 0; left: 0;
        height: 100vh; z-index: 1050; width: 280px !important;
    }
    .sidebar.mobile-open { transform: translateX(0); }
    .sidebar.collapsed:not(.mobile-open) {
        width: 0 !important; min-width: 0 !important; max-width: 0 !important;
        overflow: hidden !important; padding: 0 !important; margin: 0 !important;
    }
    .sidebar.collapsed:not(.mobile-open) .logo-section,
    .sidebar.collapsed:not(.mobile-open) nav,
    .sidebar.collapsed:not(.mobile-open) .absolute.bottom-0 { display: none !important; }
    .sidebar.collapsed.mobile-open {
        width: 280px !important; min-width: 280px !important; max-width: 280px !important;
        overflow-y: auto !important; padding: initial !important; margin: initial !important;
    }
    .sidebar.collapsed.mobile-open .logo-section { display: block !important; padding: 16px !important; }
    .sidebar.collapsed.mobile-open nav { display: block !important; padding: 4px 0 !important; }
    .sidebar.collapsed.mobile-open .absolute.bottom-0 { display: block !important; padding: 12px 0 !important; }
    .sidebar.collapsed.mobile-open .nav-divider { display: flex !important; }
    .sidebar.collapsed.mobile-open .nav-text,
    .sidebar.collapsed.mobile-open .menu-text { display: inline !important; }
    .sidebar.collapsed.mobile-open .logo-text-container,
    .sidebar.collapsed.mobile-open .logo-fullname,
    .sidebar.collapsed.mobile-open .logo-shortname { display: block !important; }
    .sidebar.collapsed.mobile-open .toggle-sidebar { display: flex !important; right: -12px !important; }
    .sidebar.collapsed.mobile-open .ml-auto { display: inline-flex !important; }
    .sidebar.collapsed.mobile-open .bg-yellow-500,
    .sidebar.collapsed.mobile-open .bg-red-500,
    .sidebar.collapsed.mobile-open .bg-blue-500,
    .sidebar.collapsed.mobile-open .bg-orange-500 { display: inline-flex !important; }
    .toggle-sidebar,
    .sidebar.collapsed .toggle-sidebar {
        right: -12px !important; width: 24px !important; height: 24px !important; font-size: 10px !important;
    }
}
@media (max-width: 480px) {
    .toggle-sidebar,
    .sidebar.collapsed .toggle-sidebar {
        right: -10px !important; width: 22px !important; height: 22px !important; font-size: 9px !important;
    }
}

/* NOTIFICATION BADGE */
.notification-badge {
    position: absolute; top: -4px; right: -4px;
    background-color: #ef4444; color: white;
    border-radius: 50%; min-width: 20px; height: 20px;
    padding: 0 5px; font-size: 10px; font-weight: 700;
    line-height: 20px; text-align: center;
    display: inline-flex; align-items: center; justify-content: center;
    border: 2px solid var(--card-bg, #ffffff);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    animation: notificationPulse 2s infinite;
    z-index: 10; transform-origin: center;
}
[data-theme="light"] .notification-badge { border-color: #ffffff; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3); }
[data-theme="dark"] .notification-badge { border-color: #1a1e2c; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4); }
#notificationBell { position: relative; display: inline-flex; align-items: center; cursor: pointer; }
#notificationBell:hover .fa-bell { animation: bellRing 0.5s ease-in-out; }
.bg-yellow-500 { background-color: #f59e0b; }
.bg-orange-500 { background-color: #f97316; }
.bg-blue-500   { background-color: #3b82f6; }
.bg-red-500    { background-color: #ef4444; }

/* PLACEHOLDER + MISC */
[data-theme="light"] ::placeholder { color: #a0aec0 !important; opacity: 1; }
[data-theme="dark"]  ::placeholder { color: #718096 !important; opacity: 1; }
.modal-open { overflow: hidden; }
@media (max-width: 768px) { .nav-item .ml-auto { display: none; } }
#toast-container {
    position: fixed; bottom: 20px; right: 20px;
    z-index: 9999; display: flex; flex-direction: column; align-items: flex-end;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const CAN_MANAGE = @json($canManage);
    const ROUTES = {
        schedulesIndex: @json($__hasScheduleIndex ? route('security.schedules.index') : null),
        schedulesMy:    @json($__hasMySchedules    ? route('security.schedules.my')    : null),
        dashboard:      @json($__hasDashboard      ? route('security.dashboard')      : null),
    };

    let unreadCount = 0;
    let updateInterval = null;

    async function fetchUnreadCount() {
        try {
            const response = await fetch('/api/notifications/count');
            const data = await response.json();
            unreadCount = data.unread_count || 0;
            updateNotificationBadges(unreadCount);
            return unreadCount;
        } catch (error) { console.error('Error fetching notification count:', error); return 0; }
    }

    async function fetchRecentNotifications(limit = 10) {
        try {
            const response = await fetch(`/api/notifications/recent?limit=${limit}`);
            const notifications = await response.json();
            renderNotificationsDropdown(notifications);
            return notifications;
        } catch (error) { console.error('Error fetching notifications:', error); return []; }
    }

    async function markNotificationAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            if (response.ok) { fetchUnreadCount(); fetchRecentNotifications(); return true; }
        } catch (error) { console.error('Error marking notification as read:', error); }
        return false;
    }

    async function markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            if (response.ok) { fetchUnreadCount(); fetchRecentNotifications(); showToast('All notifications marked as read', 'success'); return true; }
        } catch (error) { console.error('Error marking all as read:', error); showToast('Failed to mark all as read', 'error'); }
        return false;
    }

    async function clearAllNotifications() {
        if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) return;
        try {
            const response = await fetch('/api/notifications', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            if (response.ok) { fetchUnreadCount(); fetchRecentNotifications(); showToast('All notifications cleared', 'success'); return true; }
        } catch (error) { console.error('Error clearing notifications:', error); showToast('Failed to clear notifications', 'error'); }
        return false;
    }

    function updateNotificationBadges(count) {
        const badge = document.getElementById('notificationBadge');
        const badgeCount = badge?.querySelector('span');
        if (count > 0) {
            if (badgeCount) badgeCount.textContent = count > 99 ? '99+' : count;
            badge?.classList.remove('hidden');
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) {
                const badgeSpan = sidebarBadge.querySelector('span');
                if (badgeSpan) badgeSpan.textContent = count > 99 ? '99+' : count;
                sidebarBadge.style.display = 'block';
            }
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) { dropdownBadge.textContent = count > 99 ? '99+' : count; dropdownBadge.classList.remove('hidden'); }
        } else {
            badge?.classList.add('hidden');
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) sidebarBadge.style.display = 'none';
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) dropdownBadge.classList.add('hidden');
        }
    }

    function renderNotificationsDropdown(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        if (!notifications || notifications.length === 0) {
            container.innerHTML = `<div class="notifications-empty"><i class="far fa-bell-slash"></i><p>No notifications yet</p><p class="text-xs mt-2">When you receive notifications, they'll appear here</p></div>`;
            return;
        }
        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" data-id="${notification.id}">
                <div class="flex items-start space-x-3">
                    <div class="notification-icon"><i class="${notification.icon || 'fas fa-bell'}"></i></div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notification.title)}</div>
                        <div class="notification-message">${escapeHtml(notification.message)}</div>
                        <div class="notification-time">${notification.time_ago || formatTimeAgo(notification.created_at)}</div>
                    </div>
                </div>
            </div>
        `).join('');
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                e.stopPropagation();
                const notificationId = item.dataset.id;
                const notification = notifications.find(n => n.id === notificationId);
                if (notification && !notification.is_read) await markNotificationAsRead(notificationId);
                if (notification?.action_url) window.location.href = notification.action_url;
                else {
                    document.getElementById('notificationsDropdown')?.classList.add('hidden');
                    window.location.href = `/security/notifications/${notificationId}`;
                }
            });
        });
    }

    function formatTimeAgo(dateString) {
        const date = new Date(dateString); const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
        return date.toLocaleDateString();
    }

    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function initNotificationSystem() {
        fetchUnreadCount();
        fetchRecentNotifications();
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(() => {
            fetchUnreadCount();
            const dropdown = document.getElementById('notificationsDropdown');
            if (dropdown && !dropdown.classList.contains('hidden')) fetchRecentNotifications();
        }, 30000);
    }

    const notificationBell = document.getElementById('notificationBellBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    if (notificationBell && notificationsDropdown) {
        notificationBell.addEventListener('click', async (e) => {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            if (!notificationsDropdown.classList.contains('hidden')) await fetchRecentNotifications();
        });
        document.addEventListener('click', (e) => {
            if (!notificationsDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    }

    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) markAllReadBtn.addEventListener('click', async (e) => { e.stopPropagation(); await markAllAsRead(); });
    const clearAllBtn = document.getElementById('clearAllNotificationsBtn');
    if (clearAllBtn) clearAllBtn.addEventListener('click', async (e) => { e.stopPropagation(); await clearAllNotifications(); });

    initNotificationSystem();

    const scheduleToolsTrigger = document.getElementById('scheduleToolsTrigger');
    const scheduleToolsModal = document.getElementById('scheduleToolsModal');
    const closeScheduleToolsModal = document.getElementById('closeScheduleToolsModal');
    const closeScheduleToolsBtn = document.getElementById('closeScheduleToolsBtn');
    if (scheduleToolsTrigger && scheduleToolsModal) {
        scheduleToolsTrigger.addEventListener('click', function(e) {
            e.preventDefault(); scheduleToolsModal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
        });
    }
    function closeScheduleToolsModalFunc() { scheduleToolsModal.classList.add('hidden'); document.body.style.overflow = 'auto'; }
    if (closeScheduleToolsModal) closeScheduleToolsModal.addEventListener('click', closeScheduleToolsModalFunc);
    if (closeScheduleToolsBtn) closeScheduleToolsBtn.addEventListener('click', closeScheduleToolsModalFunc);
    if (scheduleToolsModal) scheduleToolsModal.addEventListener('click', function(e) { if (e.target === scheduleToolsModal) closeScheduleToolsModalFunc(); });

    const advancedSearchBtn = document.getElementById('advancedSearchBtn');
    const searchModal = document.getElementById('searchModal');
    const closeSearchModal = document.getElementById('closeSearchModal');
    const cancelSearch = document.getElementById('cancelSearch');
    const clearSearch = document.getElementById('clearSearch');
    const performSearch = document.getElementById('performSearch');
    const quickSearchInput = document.getElementById('quickSearchInput');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchCategory = document.getElementById('searchCategory');
    if (advancedSearchBtn && searchModal) {
        advancedSearchBtn.addEventListener('click', function(e) {
            e.preventDefault(); searchModal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }
    function closeSearchModalFunc() { searchModal.classList.add('hidden'); document.body.style.overflow = 'auto'; }
    if (closeSearchModal) closeSearchModal.addEventListener('click', closeSearchModalFunc);
    if (cancelSearch) cancelSearch.addEventListener('click', closeSearchModalFunc);
    if (searchModal) searchModal.addEventListener('click', function(e) { if (e.target === searchModal) closeSearchModalFunc(); });
    if (clearSearch) clearSearch.addEventListener('click', function() {
        if (globalSearchInput) globalSearchInput.value = '';
        if (searchCategory) searchCategory.value = 'all';
        const exactMatch = document.getElementById('filterExactMatch');
        const caseSensitive = document.getElementById('filterCaseSensitive');
        if (exactMatch) exactMatch.checked = false;
        if (caseSensitive) caseSensitive.checked = false;
    });
    if (performSearch) performSearch.addEventListener('click', function() {
        const searchTerm = globalSearchInput ? globalSearchInput.value.trim() : '';
        const category = searchCategory ? searchCategory.value : 'all';
        const exactMatch = document.getElementById('filterExactMatch')?.checked || false;
        const caseSensitive = document.getElementById('filterCaseSensitive')?.checked || false;
        if (searchTerm) {
            addToRecentSearches(searchTerm, category);
            performGlobalSearch(searchTerm, category, exactMatch, caseSensitive);
            closeSearchModalFunc();
        }
    });
    if (quickSearchInput) {
        quickSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) { addToRecentSearches(searchTerm, 'all'); performGlobalSearch(searchTerm, 'all', false, false); }
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && document.activeElement !== quickSearchInput) {
                e.preventDefault(); quickSearchInput.focus();
            }
        });
    }

    let recentSearches = JSON.parse(localStorage.getItem('securityRecentSearches') || '[]');
    function addToRecentSearches(term, category) {
        const search = { term, category, timestamp: Date.now() };
        recentSearches = recentSearches.filter(s => !(s.term === term && s.category === category));
        recentSearches.unshift(search);
        recentSearches = recentSearches.slice(0, 10);
        localStorage.setItem('securityRecentSearches', JSON.stringify(recentSearches));
    }
    function updateRecentSearches() {
        const container = document.getElementById('recentSearchesList');
        const parent = document.getElementById('recentSearches');
        if (recentSearches.length > 0 && parent && container) {
            parent.classList.remove('hidden');
            container.innerHTML = '';
            recentSearches.forEach(search => {
                const tag = document.createElement('div');
                tag.className = 'recent-search-tag';
                tag.textContent = `${search.term} (${search.category})`;
                tag.addEventListener('click', function() {
                    if (globalSearchInput) globalSearchInput.value = search.term;
                    if (searchCategory) searchCategory.value = search.category;
                });
                container.appendChild(tag);
            });
        } else if (parent) { parent.classList.add('hidden'); }
    }
    function performGlobalSearch(term, category, exactMatch, caseSensitive) {
        const searchParams = new URLSearchParams();
        searchParams.append('search', term);
        if (category !== 'all') searchParams.append('category', category);
        if (exactMatch) searchParams.append('exact_match', '1');
        if (caseSensitive) searchParams.append('case_sensitive', '1');
        let searchUrl = CAN_MANAGE
            ? (ROUTES.schedulesIndex || ROUTES.schedulesMy || ROUTES.dashboard || '/')
            : (ROUTES.schedulesMy    || ROUTES.schedulesIndex || ROUTES.dashboard || '/');
        window.location.href = searchUrl + '?' + searchParams.toString();
    }

    const themeSettingsButton = document.getElementById('themeSettingsButton');
    const themeSettingsModal = document.getElementById('themeSettingsModal');
    const closeThemeModal = document.getElementById('closeThemeModal');
    const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');

    let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    let selectedAppearance = localStorage.getItem('appearance') || 'system';

    function getCurrentAppearance() {
        const currentTheme = document.body.getAttribute('data-theme');
        if (currentTheme === 'light' || currentTheme === 'dark') return currentTheme;
        const savedAppearance = localStorage.getItem('appearance');
        if (savedAppearance === 'light' || savedAppearance === 'dark') return savedAppearance;
        return 'system';
    }
    function getSystemAppearance() { return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    function applyAppearance(appearance) {
        if (appearance === 'system') { document.body.setAttribute('data-theme', getSystemAppearance()); selectedAppearance = 'system'; }
        else { document.body.setAttribute('data-theme', appearance); selectedAppearance = appearance; }
        localStorage.setItem('appearance', selectedAppearance);
    }
    selectedAppearance = getCurrentAppearance();

    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
            selectedAppearance = getCurrentAppearance();
            selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            setThemeSelection(selectedSidebarTheme);
            setAppearanceSelection(selectedAppearance);
        });
    }
    const closeModal = function() { themeSettingsModal.classList.add('hidden'); document.body.style.overflow = 'auto'; };
    if (closeThemeModal) closeThemeModal.addEventListener('click', closeModal);
    if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeModal);
    if (themeSettingsModal) themeSettingsModal.addEventListener('click', function(e) { if (e.target === themeSettingsModal) closeModal(); });

    const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            setThemeSelection(theme);
            selectedSidebarTheme = theme;
            document.body.setAttribute('data-sidebar-theme', theme);
            localStorage.setItem('sidebarTheme', theme);
        });
    });
    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
    appearanceOptions.forEach(option => {
        option.addEventListener('click', function() {
            const appearance = this.getAttribute('data-theme');
            setAppearanceSelection(appearance);
            applyAppearance(appearance);
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (scheduleToolsModal && !scheduleToolsModal.classList.contains('hidden')) closeScheduleToolsModalFunc();
            if (searchModal && !searchModal.classList.contains('hidden')) closeSearchModalFunc();
            if (themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) closeModal();
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) notificationsDropdown.classList.add('hidden');
        }
    });

    function setThemeSelection(theme) {
        themeOptions.forEach(opt => {
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) existingIndicator.remove();
            if (opt.getAttribute('data-theme') === theme) {
                opt.classList.add('active');
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else { opt.classList.remove('active'); }
        });
    }
    function setAppearanceSelection(appearance) {
        appearanceOptions.forEach(opt => {
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) existingIndicator.remove();
            if (opt.getAttribute('data-theme') === appearance) {
                opt.classList.add('active');
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else { opt.classList.remove('active'); }
        });
    }

    const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    const savedAppearance = localStorage.getItem('appearance') || 'system';
    document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
    applyAppearance(savedAppearance);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (selectedAppearance === 'system') applyAppearance('system');
    });
});
</script>