{{-- ============ ROLE RESOLUTION (dual-mode: legacy type + role system + session) ============ --}}
@php
    if (!isset($sidebarUnreadCount)) {
        $sidebarUnreadCount = auth()->check()
            ? auth()->user()->unreadNotifications()->count()
            : 0;
    }

    $user = auth()->user();

    // ---------- 1. Legacy type flags ----------
    $legacyType = $user->type;
    $isFieldAgentLegacy = ($legacyType === \App\Models\User::TYPE_FIELD_AGENT);
    $isSuperAdminLegacy = ($legacyType === \App\Models\User::TYPE_SUPER_ADMIN);
    $isAdminLegacy      = ($legacyType === \App\Models\User::TYPE_ADMIN);

    // ---------- 2. Role-based flags ----------
    // hasRole() returns true for any role the user has, regardless of legacy type
    $hasFieldAgentRole  = $user->hasRole('field-agent');
    $hasSuperAdminRole  = $user->hasRole('super-admin');
    $hasAdminRole       = $user->hasRole('admin');

    // Combined: user qualifies as X if EITHER legacy type OR role matches
    $canBeFieldAgent = $isFieldAgentLegacy || $hasFieldAgentRole;
    $canBeSuperAdmin = $isSuperAdminLegacy || $hasSuperAdminRole;
    $canBeAdmin      = $isAdminLegacy      || $hasAdminRole;

    // ---------- 3. Resolve the ACTIVE role ----------
    // Priority:
    //   a) session('selected_role') — what the dashboard switcher last set
    //   b) route-name sniffing      — fallback for direct URL access
    //   c) legacy type              — ultimate fallback
    $activeRole = session('selected_role');

    if (!$activeRole) {
        $currentRoute = \Route::currentRouteName() ?? '';
        if (\Illuminate\Support\Str::contains($currentRoute, 'super-admin')) {
            $activeRole = 'super-admin';
        } elseif (\Illuminate\Support\Str::contains($currentRoute, 'field-agent')) {
            $activeRole = 'field-agent';
        } elseif (\Illuminate\Support\Str::contains($currentRoute, 'admin')) {
            $activeRole = 'admin';
        } elseif (\Illuminate\Support\Str::contains($currentRoute, 'landlord')) {
            $activeRole = 'landlord';
        } elseif (\Illuminate\Support\Str::contains($currentRoute, 'tenant')) {
            $activeRole = 'tenant';
        } elseif (\Illuminate\Support\Str::contains($currentRoute, 'security')) {
            $activeRole = 'security-personnel';
        } elseif ($isFieldAgentLegacy) {
            $activeRole = 'field-agent';
        } elseif ($isSuperAdminLegacy) {
            $activeRole = 'super-admin';
        } elseif ($isAdminLegacy) {
            $activeRole = 'admin';
        } else {
            $activeRole = null;
        }
    }

    // ---------- 4. Decide which UI variant to render ----------
    // The field-agent sidebar should only show field-agent-specific links
    // when the ACTIVE role is field-agent (not just because the user has it).
    $viewAsFieldAgent = ($activeRole === 'field-agent') && $canBeFieldAgent;
    $viewAsAdmin      = in_array($activeRole, ['admin', 'super-admin'], true)
                        && ($canBeAdmin || $canBeSuperAdmin);

    // Kept for backward compatibility with any @if blocks below
    $isFieldAgent = $viewAsFieldAgent;
    $isSuperAdmin = ($activeRole === 'super-admin') && $canBeSuperAdmin;
    $isAdmin      = ($activeRole === 'admin') && $canBeAdmin;
@endphp

<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Enhanced Professional Logo Section -->
    <div class="logo-section">
        @php
            try {
                $systemSettings = \App\Models\SystemSetting::getSettings();
            } catch (\Throwable $e) {
                $systemSettings = null;
            }

            $systemLogo      = $systemSettings->system_logo ?? null;
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'FieldAgent');
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
        @endphp

        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogoUrl)
                    <div class="logo-image-container">
                        <img src="{{ $systemLogoUrl }}"
                             alt="{{ $systemName }}"
                             class="logo-image"
                             onerror="this.style.display='none'; var f=document.getElementById('logoFallback'); if(f) f.style.display='flex';">
                        <div id="logoFallback" class="logo-fallback" style="display: none;">
                            <i class="fas fa-rocket"></i>
                        </div>
                    </div>
                @else
                    <div class="logo-default">
                        <i class="fas fa-rocket"></i>
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

        {{-- ============================================================ --}}
        {{-- DASHBOARD LINK — now role-aware --}}
        {{-- ============================================================ --}}
        @if($viewAsFieldAgent)
            <a href="{{ route('field-agent.dashboard') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home mr-4"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        @elseif($isSuperAdmin)
            <a href="{{ route('super-admin.dashboard') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home mr-4"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        @elseif($isAdmin)
            <a href="{{ route('admin.dashboard') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home mr-4"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        @else
            <a href="{{ route('field-agent.dashboard') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home mr-4"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        @endif

        {{-- ============================================================ --}}
        {{-- ✅ FIXED: REGISTRATION PLANS LINK --}}
        {{-- Uses the ACTIVE role (session-driven) instead of raw legacy type --}}
        {{-- ============================================================ --}}
        @if($viewAsFieldAgent)
            {{-- Field Agent view: personal assigned plans --}}
            <a href="{{ route('field-agent.registration-plans.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.registration-plans.*') ? 'active' : '' }}">
                <i class="fas fa-map-marked-alt mr-4"></i>
                <span class="nav-text">My Assigned Plans</span>
            </a>
        @elseif($isAdmin || $isSuperAdmin)
            {{-- Admin view: full plan management --}}
            <a href="{{ route('registration-plans.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('registration-plans.*') ? 'active' : '' }}">
                <i class="fas fa-map-marked-alt mr-4"></i>
                <span class="nav-text">Registration Plans</span>
            </a>
        @endif

        {{-- ============================================================ --}}
        {{-- PROPERTIES LINK — role-aware --}}
        {{-- ============================================================ --}}
        @if($viewAsFieldAgent)
            <a href="{{ route('field-agent.properties.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.properties.*') ? 'active' : '' }}">
                <i class="fas fa-building mr-4"></i>
                <span class="nav-text">Properties</span>
            </a>
        @elseif($isAdmin || $isSuperAdmin)
            <a href="{{ route('properties.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('properties.*') ? 'active' : '' }}">
                <i class="fas fa-building mr-4"></i>
                <span class="nav-text">Properties</span>
            </a>
        @else
            <a href="{{ route('field-agent.properties.index') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.properties.*') ? 'active' : '' }}">
                <i class="fas fa-building mr-4"></i>
                <span class="nav-text">Properties</span>
            </a>
        @endif

        {{-- ============================================================ --}}
        {{-- NOTIFICATIONS — route-aware --}}
        {{-- ============================================================ --}}
        @php
            $notificationsRoute = $viewAsFieldAgent
                ? route('field-agent.notifications.index')
                : (($isAdmin || $isSuperAdmin)
                    ? route('notifications.index')
                    : route('field-agent.notifications.index'));
        @endphp
        <a href="{{ $notificationsRoute }}"
           class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('*.notifications.*') ? 'active' : '' }}"
           id="sidebarNotificationsLink">
            <i class="fas fa-bell mr-4"></i>
            <span class="nav-text">Notifications</span>
            <span class="ml-auto" id="sidebarNotificationBadge" style="display: none;">
                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"></span>
            </span>
        </a>

        {{-- ============================================================ --}}
        {{-- FIELD AGENT SECTION — only when viewing AS a field agent --}}
        {{-- ============================================================ --}}
        @if($viewAsFieldAgent)
            <div class="nav-divider mt-1">
                <span class="menu-text">FIELD AGENT</span>
            </div>

            <a href="{{ route('field-agent.statistics') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.statistics') ? 'active' : '' }}">
                <i class="fas fa-chart-bar mr-4"></i>
                <span class="nav-text">Statistics</span>
            </a>

            <a href="{{ route('field-agent.performance') }}"
               class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('field-agent.performance') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt mr-4"></i>
                <span class="nav-text">Performance</span>
            </a>
        @endif
    </nav>

    <!-- Settings Button -->
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
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
                    <option value="registration-plans">Registration Plans</option>
                    <option value="performance">Performance</option>
                    <option value="statistics">Statistics</option>
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
        <a href="{{ $notificationsRoute }}" class="view-all-link">
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
                    {{-- ✅ FIXED: Profile link depends on active role --}}
                    @if($viewAsFieldAgent)
                        <a href="{{ route('agent.profile.edit') }}" class="dropdown-item">
                            <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">My Profile</span>
                        </a>
                    @else
                        <a href="{{ route('admin.profile.edit') }}" class="dropdown-item">
                            <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">My Profile</span>
                        </a>
                    @endif

                    {{-- Performance link only shows in field-agent view --}}
                    @if($viewAsFieldAgent)
                        <a href="{{ route('field-agent.performance') }}" class="dropdown-item">
                            <i class="fas fa-tachometer-alt mr-3" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">Performance</span>
                        </a>
                    @endif

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    <a href="{{ $notificationsRoute }}" class="dropdown-item" id="dropdownNotificationsLink">
                        <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Notifications</span>
                        <span id="dropdownNotificationBadge" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full hidden">0</span>
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

@include('layouts.partials.field.style')
@include('layouts.partials.field.script')