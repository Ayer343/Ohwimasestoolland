<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Enhanced Professional Logo Section -->
    <div class="logo-section">
        @php
            // Get system settings (guarded so a DB issue never breaks the sidebar)
            try {
                $systemSettings = \App\Models\SystemSetting::getSettings();
            } catch (\Throwable $e) {
                $systemSettings = null;
            }

            $systemLogo      = $systemSettings->system_logo ?? null;
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Security');
            $systemName      = $systemSettings->system_name ?? config('app.name', 'Laravel');

            // ============ FIXED: Resolve logo URL from the SAME disk uploads use ============
            // The `public` disk resolves to either storage/app/public (local) or
            // DigitalOcean Spaces / S3 (production) via PUBLIC_FILESYSTEM_DRIVER.
            // Using it here keeps local and production consistent.
            $systemLogoUrl = null;
            if ($systemLogo) {
                try {
                    $systemLogoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($systemLogo);
                } catch (\Throwable $e) {
                    // Last-resort fallback: treat it as a path under /storage
                    try {
                        $systemLogoUrl = \Illuminate\Support\Facades\Storage::url($systemLogo);
                    } catch (\Throwable $e2) {
                        $systemLogoUrl = null;
                    }
                }
            }

            // FIX: Ensure sidebarUnreadCount is always defined
            if (!isset($sidebarUnreadCount)) {
                $sidebarUnreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
            }

            // ✅ CHECK IF USER IS A SUPERVISOR
            $isSupervisor = auth()->check() && auth()->user()->hasActiveSupervisorAssignment();

            // ✅ GET SUPERVISOR ASSIGNMENT COUNT FOR BADGE
            $activeSupervisorAssignments = auth()->check() ? auth()->user()->activeSupervisorAssignments()->count() : 0;
        @endphp

        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogoUrl)
                    <!-- Professional logo display with proper error handling -->
                    <div class="logo-image-container">
                        <img src="{{ $systemLogoUrl }}"
                             alt="{{ $systemName }}"
                             class="logo-image"
                             onerror="this.style.display='none'; var f=document.getElementById('logoFallback'); if(f) f.style.display='flex';">
                        <!-- Professional fallback - initially hidden -->
                        <div id="logoFallback" class="logo-fallback" style="display: none;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                @else
                    <!-- Professional default logo when no system logo exists -->
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

    <!-- Professional separator -->
    <div class="logo-separator"></div>

    <!-- Compact navigation with minimal gap -->
<nav class="mt-2">
    <div class="nav-divider">
        <span class="menu-text">MAIN NAVIGATION</span>
    </div>

    <!-- Dashboard Link -->
    <a href="{{ route('security-personnel.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security-personnel.dashboard') ? 'active' : '' }}">
        <i class="fas fa-home mr-4"></i>
        <span class="nav-text">Dashboard</span>
    </a>

    <!-- Schedule Management Section -->
    <a href="{{ route('security.schedules.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.schedules.index') ? 'active' : '' }}">
        <i class="fas fa-calendar-alt mr-4"></i>
        <span class="nav-text">My Schedules</span>
    </a>

    <!-- ======================================== -->
    <!-- ✅ SUPERVISOR FEATURES (Conditional)     -->
    <!-- ======================================== -->
    @if($isSupervisor)
        <!-- Supervisor Dashboard -->
        <a href="{{ route('security.supervisor.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.dashboard') ? 'active' : '' }}">
            <i class="fas fa-user-tie mr-4"></i>
            <span class="nav-text">Supervisor Dashboard</span>
            @if($activeSupervisorAssignments > 0)
                <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                    {{ $activeSupervisorAssignments }}
                </span>
            @endif
        </a>

        <!-- Supervisor Assignments Section -->
        <div class="nav-divider mt-1">
            <span class="menu-text">SUPERVISOR ASSIGNMENTS</span>
        </div>

        <!-- All Supervisor Assignments -->
        <a href="{{ route('security.supervisor-assignments.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor-assignments.index') ? 'active' : '' }}">
            <i class="fas fa-tasks mr-4"></i>
            <span class="nav-text">All Assignments</span>
            @php
                try {
                    $totalAssignments = \App\Models\SecuritySupervisorAssignment::whereIn('security_post_id', function($query) {
                        $query->select('security_post_id')
                            ->from('security_supervisor_assignments')
                            ->where('user_id', auth()->id())
                            ->where('supervisor_type', 'area_supervisor')
                            ->where('is_active', true)
                            ->where(function($q) {
                                $q->whereNull('end_date')
                                  ->orWhere('end_date', '>=', now());
                            });
                    })->count();
                } catch (\Exception $e) {
                    $totalAssignments = 0;
                }
            @endphp
            @if($totalAssignments > 0)
                <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                    {{ $totalAssignments > 9 ? '9+' : $totalAssignments }}
                </span>
            @endif
        </a>

        <!-- Create New Assignment -->
        <a href="{{ route('security.supervisor-assignments.create') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor-assignments.create') ? 'active' : '' }}">
            <i class="fas fa-plus-circle mr-4"></i>
            <span class="nav-text">Create Assignment</span>
        </a>

        <!-- ======================================== -->
        <!-- ✅ PERSONNEL MANAGEMENT                  -->
        <!-- ======================================== -->
        <div class="nav-divider mt-1">
            <span class="menu-text">PERSONNEL MANAGEMENT</span>
        </div>

        <!-- View Personnel -->
        <a href="{{ route('security.personnel.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.personnel.index') ? 'active' : '' }}">
            <i class="fas fa-users mr-4"></i>
            <span class="nav-text">All Personnel</span>
            @php
                try {
                    $personnelCount = \App\Models\User::where('type', 'security_personnel')
                        ->where('status', 'active')
                        ->count();
                } catch (\Exception $e) {
                    $personnelCount = 0;
                }
            @endphp
            @if($personnelCount > 0)
                <span class="ml-auto bg-indigo-500 text-white text-xs px-2 py-1 rounded-full">
                    {{ $personnelCount > 9 ? '9+' : $personnelCount }}
                </span>
            @endif
        </a>

        <!-- ======================================== -->
        <!-- SUPERVISOR ACTIONS                      -->
        <!-- ======================================== -->
        <div class="nav-divider mt-1">
            <span class="menu-text">ACTIONS</span>
        </div>

        <!-- Pending Approvals -->
        <a href="{{ route('security.supervisor.actions.pending') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.actions.pending') ? 'active' : '' }}">
            <i class="fas fa-clock mr-4"></i>
            <span class="nav-text">Pending Approvals</span>
            <span id="pendingApprovalsBadge" class="ml-auto hidden">
                <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full">0</span>
            </span>
        </a>

        <!-- ======================================== -->
        <!-- TEAM MANAGEMENT                         -->
        <!-- ======================================== -->
        <div class="nav-divider mt-1">
            <span class="menu-text">TEAM MANAGEMENT</span>
        </div>

        <!-- Team Today -->
        <a href="{{ route('security.supervisor.team.today') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.team.today') ? 'active' : '' }}">
            <i class="fas fa-users mr-4"></i>
            <span class="nav-text">Team Today</span>
        </a>

        <!-- Team Schedule -->
        <a href="{{ route('security.supervisor.team.schedule') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.team.schedule') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt mr-4"></i>
            <span class="nav-text">Team Schedule</span>
        </a>

        <!-- Team Attendance -->
        <a href="{{ route('security.supervisor.team.attendance') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.team.attendance') ? 'active' : '' }}">
            <i class="fas fa-clipboard-check mr-4"></i>
            <span class="nav-text">Team Attendance</span>
        </a>

        <!-- Team Performance -->
        <a href="{{ route('security.supervisor.team.performance') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.supervisor.team.performance') ? 'active' : '' }}">
            <i class="fas fa-chart-line mr-4"></i>
            <span class="nav-text">Team Performance</span>
        </a>

        <!-- ======================================== -->
        <!-- POST MANAGEMENT                         -->
        <!-- ======================================== -->
        <div class="nav-divider mt-1">
            <span class="menu-text">POST MANAGEMENT</span>
        </div>

        <!-- Post Schedule -->
        <a href="{{ route('security.supervisor.posts.schedule', ['postId' => 'current']) }}" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-map-marker-alt mr-4"></i>
            <span class="nav-text">View Post Schedule</span>
        </a>
    @endif

    <!-- ======================================== -->
    <!-- COMMON SECURITY FEATURES                 -->
    <!-- ======================================== -->

    <!-- Security Posts - For ALL Security Personnel -->
    <a href="{{ route('security.posts.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('security.posts.*') ? 'active' : '' }}">
        <i class="fas fa-building mr-4"></i>
        <span class="nav-text">Security Posts</span>
    </a>

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

    <!-- Notifications link with live count -->
    <a href="{{ route('security.notifications.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('*.notifications.*') ? 'active' : '' }}" id="sidebarNotificationsLink">
        <i class="fas fa-bell mr-4"></i>
        <span class="nav-text">Notifications</span>
        <span class="ml-auto" id="sidebarNotificationBadge" style="display: none;">
            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"></span>
        </span>
    </a>

    <!-- ======================================== -->
    <!-- TOOLS (Always visible)                   -->
    <!-- ======================================== -->
    <div class="nav-divider mt-1">
        <span class="menu-text">TOOLS</span>
    </div>

    <!-- Schedule Tools Modal Trigger -->
    <button id="scheduleToolsTrigger" class="nav-item flex items-center py-2 px-6 w-full text-left {{
        request()->routeIs('security.schedules.preferences') ||
        request()->routeIs('security.schedules.availability*') ||
        request()->routeIs('security.schedules.upcoming-handovers') ||
        request()->routeIs('security.schedules.rotation-groups') ||
        request()->routeIs('security.schedules.rotation-group*') ||
        request()->routeIs('security.schedules.statistics') ? 'active' : ''
    }}">
        <i class="fas fa-tools mr-4"></i>
        <span class="nav-text">Schedule Tools</span>
        <i class="fas fa-chevron-right ml-auto text-xs opacity-50"></i>
    </button>
</nav>

    <!-- Settings Button - Compact positioning -->
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
</div>

<!-- Schedule Tools Modal (Now includes Analytics) -->
<div id="scheduleToolsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="schedule-tools-modal">
        <!-- Modal Header -->
        <div class="schedule-tools-header flex justify-between items-center">
            <h3 class="text-base font-semibold">
                <i class="fas fa-tools mr-2"></i>Schedule Tools
            </h3>
            <button id="closeScheduleToolsModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="schedule-tools-body">
            <p class="schedule-tools-description">Select a schedule management tool to access</p>

            <div class="schedule-tools-grid">
                <!-- Schedule Management Tools Section -->
                <div class="tools-section">
                    <h4 class="tools-section-title">
                        <i class="fas fa-calendar-alt mr-2"></i>Schedule Management
                    </h4>
                    <div class="tools-section-grid">
                        <!-- Shift Preferences -->
                        <a href="{{ route('security.schedules.preferences') }}" class="schedule-tool-item {{ request()->routeIs('security.schedules.preferences') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <div class="tool-content">
                                <h4 class="tool-title">Shift Preferences</h4>
                                <p class="tool-description">Set your preferred shifts and working hours</p>
                            </div>
                            <div class="tool-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>

                        <!-- Availability -->
                        <a href="{{ route('security.schedules.availability') }}" class="schedule-tool-item {{ request()->routeIs('security.schedules.availability*') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="tool-content">
                                <h4 class="tool-title">Availability</h4>
                                <p class="tool-description">Manage your available time slots</p>
                            </div>
                            <div class="tool-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>

                        <!-- Upcoming Handovers -->
                        <a href="{{ route('security.schedules.upcoming-handovers') }}" class="schedule-tool-item {{ request()->routeIs('security.schedules.upcoming-handovers') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="tool-content">
                                <h4 class="tool-title">Upcoming Handovers</h4>
                                <p class="tool-description">View and manage shift handovers</p>
                            </div>
                            <div class="tool-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>

                        <!-- Rotation Groups -->
                        <a href="{{ route('security.schedules.rotation-groups') }}" class="schedule-tool-item {{ request()->routeIs('security.schedules.rotation-groups') || request()->routeIs('security.schedules.rotation-group*') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="tool-content">
                                <h4 class="tool-title">Rotation Groups</h4>
                                <p class="tool-description">Manage team rotation schedules</p>
                            </div>
                            <div class="tool-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Analytics & Insights Section -->
                <div class="tools-section">
                    <h4 class="tools-section-title">
                        <i class="fas fa-chart-pie mr-2"></i>Analytics & Insights
                    </h4>
                    <div class="tools-section-grid">
                        <!-- Analytics -->
                        <a href="{{ route('security.schedules.statistics') }}" class="schedule-tool-item {{ request()->routeIs('security.schedules.statistics') ? 'active' : '' }}">
                            <div class="tool-icon-wrapper analytics-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="tool-content">
                                <h4 class="tool-title">Analytics Dashboard</h4>
                                <p class="tool-description">View schedule statistics, trends, and insights</p>
                            </div>
                            <div class="tool-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>

                        <!-- Quick Stats Preview -->
                        <div class="analytics-preview">
                            <div class="preview-stats">
                                <div class="preview-stat-item">
                                    <span class="preview-stat-label">This Week</span>
                                    <span class="preview-stat-value">32 shifts</span>
                                </div>
                                <div class="preview-stat-item">
                                    <span class="preview-stat-label">Coverage</span>
                                    <span class="preview-stat-value">94%</span>
                                </div>
                                <div class="preview-stat-item">
                                    <span class="preview-stat-label">Overtime</span>
                                    <span class="preview-stat-value">8h</span>
                                </div>
                            </div>
                            <a href="{{ route('security.schedules.statistics') }}" class="preview-link">
                                View full analytics <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="schedule-tools-stats">
                <div class="stat-item">
                    <span class="stat-label">Pending Handovers</span>
                    <span class="stat-value">3</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Upcoming Rotations</span>
                    <span class="stat-value">2</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Availability Gaps</span>
                    <span class="stat-value">1</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Schedule Changes</span>
                    <span class="stat-value">5</span>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="schedule-tools-footer flex justify-end">
            <button id="closeScheduleToolsBtn" class="px-4 py-2 text-sm font-medium transition-colors duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Theme Settings Modal -->
<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="theme-modal-compact">
        <!-- Modal Header -->
        <div class="theme-modal-header-compact flex justify-between items-center">
            <h3 class="text-base font-semibold">
                <i class="fas fa-palette mr-2"></i>Theme Settings
            </h3>
            <button id="closeThemeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
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

        <!-- Modal Footer -->
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
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-search mr-2"></i>Security Search
            </h3>
            <button id="closeSearchModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <!-- Search Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Enter visitor name, ID, or incident..."
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- Search Category -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Category
                </label>
                <select id="searchCategory" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="all">All Categories</option>
                    <option value="visitors">Visitors</option>
                    <option value="incidents">Incident Reports</option>
                    <option value="properties">Properties</option>
                    <option value="surveillance">Surveillance</option>
                </select>
            </div>

            <!-- Search Filters -->
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

            <!-- Recent Searches -->
            <div id="recentSearches" class="hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Recent Searches
                </label>
                <div id="recentSearchesList" class="flex flex-wrap gap-2">
                    <!-- Recent searches will be populated here -->
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
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
        <a href="{{ route('security.notifications.index') }}" class="view-all-link">
            View all notifications →
        </a>
    </div>
</div>

<!-- Main Content Header -->
<div class="content" id="mainContent">
    <!-- Top Navigation -->
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button id="toggleSidebarMobile" class="mobile-menu-btn mr-4 text-gray-600">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                @if($isSupervisor && request()->routeIs('security.supervisor.*'))
                    @yield('title', 'Supervisor Dashboard')
                @else
                    @yield('title', 'Security Dashboard')
                @endif
            </h2>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Enhanced Search Bar -->
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <!-- Quick Search Input -->
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        <input type="text" id="quickSearchInput" placeholder="Search visitors or incidents..."
                               class="header-search pl-10 pr-10"
                               style="color: var(--text-primary); background-color: var(--bg-secondary); border: 1px solid transparent;"
                               data-toggle="tooltip" title="Press / to focus">
                        <!-- Advanced Search Button - Now opens modal on click -->
                        <button id="advancedSearchBtn" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>
                    </div>

                    <!-- Mobile Search Button -->
                    <button id="mobileSearchBtn" class="md:hidden p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                        <i class="fas fa-search text-gray-600 dark:text-gray-300"></i>
                    </button>
                </div>
            </div>

            <!-- Notification Bell with Live Counter -->
            <div class="header-buttons flex items-center space-x-2">
                <!-- Security Specific Alerts -->
                <button class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                    <i class="fas fa-exclamation-triangle text-gray-600 dark:text-gray-300"></i>
                    <span class="notification-dot bg-red-500"></span>
                </button>

                <div class="relative">
                    <button id="notificationBellBtn" class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="far fa-bell text-gray-600 dark:text-gray-300 text-xl"></i>
                        <span id="notificationBadge" class="notification-badge hidden">
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="dropdown relative">
    <button id="userMenuButton" class="flex items-center space-x-2">
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
                    <!-- Updated Profile Link -->
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>

                    <!-- Notifications with live count -->
                    <a href="{{ route('security.notifications.index') }}" class="dropdown-item" id="dropdownNotificationsLink">
                        <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Notifications</span>
                        <span id="dropdownNotificationBadge" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full hidden">0</span>
                    </a>

                    <!-- ✅ Supervisor Quick Link in Dropdown -->
                    @if($isSupervisor)
                        <a href="{{ route('security.supervisor.dashboard') }}" class="dropdown-item" id="dropdownSupervisorLink">
                            <i class="fas fa-user-tie mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">Supervisor Panel</span>
                            @if($activeSupervisorAssignments > 0)
                                <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                                    {{ $activeSupervisorAssignments }}
                                </span>
                            @endif
                        </a>
                        <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                    @endif

                    <!-- Logout -->
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
/* Enhanced Minimal Avatar Styling */
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

/* Enhanced header spacing for cleaner look */
.header {
    padding: 12px 24px;
}

/* Search bar enhancements */
.header-search {
    min-width: 280px;
}

/* Mobile search button styling */
#mobileSearchBtn {
    padding: 8px;
}

/* Header buttons spacing adjustment */
.header-buttons {
    gap: 8px;
}

/* Dropdown menu positioning adjustment */
.dropdown-menu {
    min-width: 220px;
}

/* Quick search input focus state */
.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}

/* Advanced search button hover effect */
#advancedSearchBtn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: scale(1.1);
}

/* Notifications Dropdown Styles */
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
}

.notification-item:hover {
    background-color: var(--bg-secondary);
}

.notification-item.unread {
    background-color: rgba(59, 130, 246, 0.05);
}

.notification-item.unread:hover {
    background-color: rgba(59, 130, 246, 0.1);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
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
}

.view-all-link:hover {
    text-decoration: underline;
}

.notification-badge {
    position: relative;
}

/* Mobile responsiveness adjustments */
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

    .notifications-dropdown {
        position: fixed;
        top: 60px;
        right: 10px;
        left: 10px;
        width: auto;
        max-width: none;
    }
}

/* Dark mode adjustments for minimal avatar */
[data-theme="dark"] .avatar-minimal {
    border-color: #4b5563;
}

[data-theme="dark"] .avatar-minimal:hover {
    border-color: var(--primary);
}

/* Smooth transitions for all interactive elements */
.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn {
    transition: all 0.2s ease-in-out;
}

/* Theme Settings Modal Compact Styles */
.theme-modal-compact {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalFadeIn 0.3s ease-out;
}

.theme-modal-header-compact {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.theme-modal-body-compact {
    padding: 20px;
}

.theme-modal-footer-compact {
    padding: 16px 20px;
    border-top: 1px solid #e5e7eb;
}

.section-header-compact {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.section-description-compact {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 16px;
}

.appearance-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.appearance-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 12px 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    position: relative;
}

.appearance-option-compact:hover {
    background-color: #f9fafb;
    transform: translateY(-2px);
}

.appearance-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.appearance-icon-compact {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.appearance-label-compact {
    font-size: 12px;
    font-weight: 500;
    color: #374151;
}

.theme-grid-compact {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}

.theme-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 8px 4px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    position: relative;
}

.theme-option-compact:hover {
    background-color: #f9fafb;
    transform: translateY(-2px);
}

.theme-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.theme-preview-compact {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    margin-bottom: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.theme-label-compact {
    font-size: 11px;
    font-weight: 500;
    color: #374151;
}

/* Schedule Tools Modal Enhanced Styles */
.schedule-tools-modal {
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideUp 0.3s ease-out;
}

.schedule-tools-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.schedule-tools-body {
    padding: 24px;
}

.schedule-tools-description {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
}

.schedule-tools-grid {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 24px;
}

.tools-section {
    background: #f9fafb;
    border-radius: 16px;
    padding: 16px;
}

.tools-section-title {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
}

.tools-section-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.schedule-tool-item {
    display: flex;
    align-items: center;
    padding: 16px;
    background: white;
    border-radius: 12px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.schedule-tool-item:hover {
    transform: translateX(4px);
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.schedule-tool-item.active {
    background: rgba(var(--primary-rgb), 0.04);
    border-color: var(--primary);
}

.tool-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    color: white;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}

.tool-icon-wrapper.analytics-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.tool-content {
    flex: 1;
}

.tool-title {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
}

.tool-description {
    font-size: 13px;
    color: #6b7280;
}

.tool-arrow {
    color: #9ca3af;
    font-size: 14px;
    transition: all 0.2s ease;
    margin-left: 12px;
}

.schedule-tool-item:hover .tool-arrow {
    color: var(--primary);
    transform: translateX(4px);
}

/* Analytics Preview Styles */
.analytics-preview {
    margin-top: 12px;
    padding: 16px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 12px;
}

.preview-stat-item {
    text-align: center;
}

.preview-stat-label {
    display: block;
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 4px;
}

.preview-stat-value {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.preview-link {
    display: block;
    text-align: center;
    font-size: 13px;
    color: var(--primary);
    text-decoration: none;
    padding: 8px;
    border-radius: 8px;
    background: rgba(var(--primary-rgb), 0.04);
    transition: all 0.2s ease;
}

.preview-link:hover {
    background: rgba(var(--primary-rgb), 0.08);
}

.schedule-tools-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    padding: 20px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    border-radius: 16px;
}

.stat-item {
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #4b5563;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
}

.schedule-tools-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
}

/* Dark mode styles for schedule tools modal */
[data-theme="dark"] .schedule-tools-modal {
    background: #1f2937;
    color: white;
}

[data-theme="dark"] .schedule-tools-header,
[data-theme="dark"] .schedule-tools-footer {
    border-color: #374151;
}

[data-theme="dark"] .schedule-tools-description {
    color: #9ca3af;
}

[data-theme="dark"] .tools-section {
    background: #2d3748;
}

[data-theme="dark"] .tools-section-title {
    color: #f9fafb;
}

[data-theme="dark"] .schedule-tool-item {
    background: #1f2937;
}

[data-theme="dark"] .schedule-tool-item:hover {
    background: #2d3748;
}

[data-theme="dark"] .tool-title {
    color: #f9fafb;
}

[data-theme="dark"] .tool-description {
    color: #9ca3af;
}

[data-theme="dark"] .analytics-preview {
    background: #2d3748;
    border-color: #4b5563;
}

[data-theme="dark"] .preview-stat-label {
    color: #9ca3af;
}

[data-theme="dark"] .preview-stat-value {
    color: #f9fafb;
}

[data-theme="dark"] .schedule-tools-stats {
    background: linear-gradient(135deg, #2d3748, #1f2937);
}

[data-theme="dark"] .stat-label {
    color: #9ca3af;
}

[data-theme="dark"] .stat-value {
    color: #f9fafb;
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

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Current selection indicator */
.current-selection {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Dark mode styles for modal */
[data-theme="dark"] .theme-modal-compact {
    background: #1f2937;
    color: white;
}

[data-theme="dark"] .theme-modal-header-compact,
[data-theme="dark"] .theme-modal-footer-compact {
    border-color: #374151;
}

[data-theme="dark"] .section-header-compact {
    color: #f9fafb;
}

[data-theme="dark"] .section-description-compact {
    color: #9ca3af;
}

[data-theme="dark"] .appearance-option-compact:hover {
    background-color: #374151;
}

[data-theme="dark"] .appearance-label-compact,
[data-theme="dark"] .theme-label-compact {
    color: #f9fafb;
}

[data-theme="dark"] .theme-option-compact:hover {
    background-color: #374151;
}

[data-theme="dark"] .appearance-option-compact.active,
[data-theme="dark"] .theme-option-compact.active {
    background-color: rgba(var(--primary-rgb), 0.2);
}

/* Security specific styles */
.notification-dot.bg-red-500 {
    background-color: #ef4444;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================

    let unreadCount = 0;
    let updateInterval = null;

    // Get user role
    const userRole = 'security';

    // Notification functions
    async function fetchUnreadCount() {
        try {
            const response = await fetch('/api/notifications/count');
            const data = await response.json();
            unreadCount = data.unread_count || 0;
            updateNotificationBadges(unreadCount);
            return unreadCount;
        } catch (error) {
            console.error('Error fetching notification count:', error);
            return 0;
        }
    }

    async function fetchRecentNotifications(limit = 10) {
        try {
            const response = await fetch(`/api/notifications/recent?limit=${limit}`);
            const notifications = await response.json();
            renderNotificationsDropdown(notifications);
            return notifications;
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return [];
        }
    }

    async function markNotificationAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                return true;
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
        return false;
    }

    async function markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                showToast('All notifications marked as read', 'success');
                return true;
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            showToast('Failed to mark all as read', 'error');
        }
        return false;
    }

    async function clearAllNotifications() {
        if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('/api/notifications', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                showToast('All notifications cleared', 'success');
                return true;
            }
        } catch (error) {
            console.error('Error clearing notifications:', error);
            showToast('Failed to clear notifications', 'error');
        }
        return false;
    }

    function updateNotificationBadges(count) {
        // Update bell badge
        const badge = document.getElementById('notificationBadge');
        const badgeCount = badge?.querySelector('span');

        if (count > 0) {
            if (badgeCount) {
                badgeCount.textContent = count > 99 ? '99+' : count;
            }
            badge?.classList.remove('hidden');

            // Update sidebar badge
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) {
                const badgeSpan = sidebarBadge.querySelector('span');
                if (badgeSpan) badgeSpan.textContent = count > 99 ? '99+' : count;
                sidebarBadge.style.display = 'block';
            }

            // Update dropdown badge
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) {
                dropdownBadge.textContent = count > 99 ? '99+' : count;
                dropdownBadge.classList.remove('hidden');
            }
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
            container.innerHTML = `
                <div class="notifications-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                    <p class="text-xs mt-2">When you receive notifications, they'll appear here</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" data-id="${notification.id}">
                <div class="flex items-start space-x-3">
                    <div class="notification-icon">
                        <i class="${notification.icon || 'fas fa-bell'}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notification.title)}</div>
                        <div class="notification-message">${escapeHtml(notification.message)}</div>
                        <div class="notification-time">${notification.time_ago || formatTimeAgo(notification.created_at)}</div>
                    </div>
                </div>
            </div>
        `).join('');

        // Add click handlers
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                e.stopPropagation();
                const notificationId = item.dataset.id;
                const notification = notifications.find(n => n.id === notificationId);

                if (notification && !notification.is_read) {
                    await markNotificationAsRead(notificationId);
                }

                // Redirect if action URL exists
                if (notification?.action_url) {
                    window.location.href = notification.action_url;
                } else {
                    // Close dropdown and go to notifications page
                    document.getElementById('notificationsDropdown')?.classList.add('hidden');
                    window.location.href = `/${userRole}/notifications/${notificationId}`;
                }
            });
        });
    }

    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
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

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // Initialize notification system
    function initNotificationSystem() {
        fetchUnreadCount();
        fetchRecentNotifications();

        // Set up polling every 30 seconds
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(() => {
            fetchUnreadCount();
            const dropdown = document.getElementById('notificationsDropdown');
            if (dropdown && !dropdown.classList.contains('hidden')) {
                fetchRecentNotifications();
            }
        }, 30000);
    }

    // Notification dropdown toggle
    const notificationBell = document.getElementById('notificationBellBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');

    if (notificationBell && notificationsDropdown) {
        notificationBell.addEventListener('click', async (e) => {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            if (!notificationsDropdown.classList.contains('hidden')) {
                await fetchRecentNotifications();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationsDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    }

    // Mark all as read button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await markAllAsRead();
        });
    }

    // Clear all notifications button
    const clearAllBtn = document.getElementById('clearAllNotificationsBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await clearAllNotifications();
        });
    }

    // Start notification system
    initNotificationSystem();

    // ============================================
    // SCHEDULE TOOLS MODAL FUNCTIONALITY
    // ============================================

    const scheduleToolsTrigger = document.getElementById('scheduleToolsTrigger');
    const scheduleToolsModal = document.getElementById('scheduleToolsModal');
    const closeScheduleToolsModal = document.getElementById('closeScheduleToolsModal');
    const closeScheduleToolsBtn = document.getElementById('closeScheduleToolsBtn');

    if (scheduleToolsTrigger && scheduleToolsModal) {
        scheduleToolsTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            scheduleToolsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }

    function closeScheduleToolsModalFunc() {
        scheduleToolsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    if (closeScheduleToolsModal) {
        closeScheduleToolsModal.addEventListener('click', closeScheduleToolsModalFunc);
    }

    if (closeScheduleToolsBtn) {
        closeScheduleToolsBtn.addEventListener('click', closeScheduleToolsModalFunc);
    }

    if (scheduleToolsModal) {
        scheduleToolsModal.addEventListener('click', function(e) {
            if (e.target === scheduleToolsModal) {
                closeScheduleToolsModalFunc();
            }
        });
    }

    // ============================================
    // SEARCH MODAL FUNCTIONALITY
    // ============================================

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
            e.preventDefault();
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }

    function closeSearchModalFunc() {
        searchModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    if (closeSearchModal) closeSearchModal.addEventListener('click', closeSearchModalFunc);
    if (cancelSearch) cancelSearch.addEventListener('click', closeSearchModalFunc);

    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) closeSearchModalFunc();
        });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            if (globalSearchInput) globalSearchInput.value = '';
            if (searchCategory) searchCategory.value = 'all';
            const exactMatch = document.getElementById('filterExactMatch');
            const caseSensitive = document.getElementById('filterCaseSensitive');
            if (exactMatch) exactMatch.checked = false;
            if (caseSensitive) caseSensitive.checked = false;
        });
    }

    if (performSearch) {
        performSearch.addEventListener('click', function() {
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
    }

    if (quickSearchInput) {
        quickSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    addToRecentSearches(searchTerm, 'all');
                    performGlobalSearch(searchTerm, 'all', false, false);
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && document.activeElement !== quickSearchInput) {
                e.preventDefault();
                quickSearchInput.focus();
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
        } else if (parent) {
            parent.classList.add('hidden');
        }
    }

    function performGlobalSearch(term, category, exactMatch, caseSensitive) {
        const searchParams = new URLSearchParams();
        searchParams.append('search', term);
        if (category !== 'all') searchParams.append('category', category);
        if (exactMatch) searchParams.append('exact_match', '1');
        if (caseSensitive) searchParams.append('case_sensitive', '1');

        const currentPath = window.location.pathname;
        let searchUrl = '#';

        if (currentPath.includes('/schedules')) {
            searchUrl = '{{ route("security.schedules.index") }}?' + searchParams.toString();
        } else {
            searchUrl = '{{ route("security-personnel.dashboard") }}?' + searchParams.toString();
        }

        window.location.href = searchUrl;
    }

    // ============================================
    // THEME SETTINGS MODAL FUNCTIONALITY
    // ============================================

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

    function getSystemAppearance() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyAppearance(appearance) {
        if (appearance === 'system') {
            const systemTheme = getSystemAppearance();
            document.body.setAttribute('data-theme', systemTheme);
            selectedAppearance = 'system';
        } else {
            document.body.setAttribute('data-theme', appearance);
            selectedAppearance = appearance;
        }
        localStorage.setItem('appearance', selectedAppearance);
    }

    selectedAppearance = getCurrentAppearance();

    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            selectedAppearance = getCurrentAppearance();
            selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            setThemeSelection(selectedSidebarTheme);
            setAppearanceSelection(selectedAppearance);
        });
    }

    const closeModal = function() {
        themeSettingsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    };

    if (closeThemeModal) closeThemeModal.addEventListener('click', closeModal);
    if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeModal);

    if (themeSettingsModal) {
        themeSettingsModal.addEventListener('click', function(e) {
            if (e.target === themeSettingsModal) closeModal();
        });
    }

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
            } else {
                opt.classList.remove('active');
            }
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
            } else {
                opt.classList.remove('active');
            }
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