{{-- ============ CRITICAL FIX: Ensure sidebarUnreadCount is always defined ============ --}}
@php
    // Defensive default so anything below can rely on this variable existing,
    // even if the SystemSetting lookup further down throws.
    if (!isset($sidebarUnreadCount)) {
        $sidebarUnreadCount = 0;
    }

    if ($sidebarUnreadCount === 0 && auth()->check()) {
        try {
            $sidebarUnreadCount = auth()->user()->unreadNotifications()->count();
        } catch (\Exception $e) {
            $sidebarUnreadCount = 0;
        }
    }
@endphp

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
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Contractor');
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

            // Get unread notifications count for sidebar
            $sidebarUnreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;

            // ✅ FIXED: Use contractor_user_id instead of contractor_id
            // Get count of active contracts
            $activeContractsCount = 0;
            if (class_exists('App\Models\ConstructionContract')) {
                $activeContractsCount = \App\Models\ConstructionContract::where('contractor_user_id', auth()->id())
                    ->whereIn('status', ['approved', 'in_progress'])
                    ->count();
            }

            // Count active projects (in_progress)
            $activeProjectsCount = 0;
            if (class_exists('App\Models\ConstructionContract')) {
                $activeProjectsCount = \App\Models\ConstructionContract::where('contractor_user_id', auth()->id())
                    ->where('status', 'in_progress')
                    ->count();
            }

            // Count completed contracts
            $completedCount = 0;
            if (class_exists('App\Models\ConstructionContract')) {
                $completedCount = \App\Models\ConstructionContract::where('contractor_user_id', auth()->id())
                    ->where('status', 'completed')
                    ->count();
            }

            // ✅ FIXED: Use the correct column name for milestones
            // Get upcoming milestones count (due in next 7 days)
            $upcomingMilestones = 0;
            if (class_exists('App\Models\ConstructionMilestone')) {
                // Check if the column exists first
                $hasDueDate = \Illuminate\Support\Facades\Schema::hasColumn('construction_milestones', 'due_date');
                $hasEstimatedCompletionDate = \Illuminate\Support\Facades\Schema::hasColumn('construction_milestones', 'estimated_completion_date');
                $hasCompletionDate = \Illuminate\Support\Facades\Schema::hasColumn('construction_milestones', 'completion_date');
                $hasMilestoneDate = \Illuminate\Support\Facades\Schema::hasColumn('construction_milestones', 'milestone_date');
                $hasTargetDate = \Illuminate\Support\Facades\Schema::hasColumn('construction_milestones', 'target_date');

                // Use the correct column name
                $dateColumn = 'due_date';
                if (!$hasDueDate && $hasEstimatedCompletionDate) {
                    $dateColumn = 'estimated_completion_date';
                } elseif (!$hasDueDate && !$hasEstimatedCompletionDate && $hasCompletionDate) {
                    $dateColumn = 'completion_date';
                } elseif (!$hasDueDate && !$hasEstimatedCompletionDate && !$hasCompletionDate && $hasMilestoneDate) {
                    $dateColumn = 'milestone_date';
                } elseif (!$hasDueDate && !$hasEstimatedCompletionDate && !$hasCompletionDate && !$hasMilestoneDate && $hasTargetDate) {
                    $dateColumn = 'target_date';
                }

                try {
                    $upcomingMilestones = \App\Models\ConstructionMilestone::whereHas('contract', function($query) {
                            $query->where('contractor_user_id', auth()->id());
                        })
                        ->where('status', '!=', 'completed')
                        ->whereBetween($dateColumn, [now(), now()->addDays(7)])
                        ->count();
                } catch (\Exception $e) {
                    // If query fails, set to 0
                    $upcomingMilestones = 0;
                    \Log::warning('Failed to get upcoming milestones: ' . $e->getMessage());
                }
            }
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
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                @else
                    <!-- Professional default logo when no system logo exists -->
                    <div class="logo-default">
                        <i class="fas fa-building"></i>
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

    <!-- Dashboard - Contractor Dashboard -->
    <a href="{{ route('contractor.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.dashboard') ? 'active' : '' }}">
        <i class="fas fa-home mr-4"></i>
        <span class="nav-text">Dashboard</span>
    </a>

    <!-- ============================================ -->
    <!-- ⭐ CONTRACTS SECTION -->
    <!-- ============================================ -->
    <div class="nav-divider mt-2">
        <span class="menu-text">CONTRACTS</span>
    </div>

    <!-- My Contracts -->
    <a href="{{ route('contractor.contracts.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.contracts.index') ? 'active' : '' }}">
        <i class="fas fa-file-contract mr-4"></i>
        <span class="nav-text">My Contracts</span>
        @if($activeContractsCount > 0)
            <span class="ml-auto bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $activeContractsCount }}
            </span>
        @endif
    </a>

    <!-- Active Projects -->
    <a href="{{ route('contractor.projects.active') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.projects.active') ? 'active' : '' }}">
        <i class="fas fa-tasks mr-4"></i>
        <span class="nav-text">Active Projects</span>
        @if($activeProjectsCount > 0)
            <span class="ml-auto bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $activeProjectsCount }}
            </span>
        @endif
    </a>

    <!-- Completed Projects -->
    <a href="{{ route('contractor.projects.completed') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.projects.completed') ? 'active' : '' }}">
        <i class="fas fa-check-circle mr-4"></i>
        <span class="nav-text">Completed Projects</span>
        @if($completedCount > 0)
            <span class="ml-auto bg-gray-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $completedCount }}
            </span>
        @endif
    </a>

  <!-- ============================================ -->
<!-- ⭐ PROJECT MANAGEMENT -->
<!-- ============================================ -->
<div class="nav-divider mt-2">
    <span class="menu-text">PROJECT MANAGEMENT</span>
</div>

<!-- ✅ Workers - Using Global Route (Recommended) -->
<a href="{{ route('contractor.workers.global') }}"
   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.workers.*') ? 'active' : '' }}">
    <i class="fas fa-users mr-4"></i>
    <span class="nav-text">Workers</span>
</a>

<!-- Project Calendar -->
<a href="{{ route('contractor.calendar') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.calendar') ? 'active' : '' }}">
    <i class="fas fa-calendar-alt mr-4"></i>
    <span class="nav-text">Project Calendar</span>
    @if($upcomingMilestones > 0)
        <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
            {{ $upcomingMilestones }}
        </span>
    @endif
</a>

<!-- Reports -->
<a href="{{ route('contractor.reports') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.reports') ? 'active' : '' }}">
    <i class="fas fa-chart-bar mr-4"></i>
    <span class="nav-text">Reports</span>
</a>

    <!-- ============================================ -->
    <!-- ⭐ COMMUNICATION -->
    <!-- ============================================ -->
    <div class="nav-divider mt-2">
        <span class="menu-text">COMMUNICATION</span>
    </div>

    <!-- Notifications - Using general notifications route -->
    <a href="{{ route('notifications.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('notifications.index') ? 'active' : '' }}">
        <i class="fas fa-bell mr-4"></i>
        <span class="nav-text">Notifications</span>
        @if($sidebarUnreadCount > 0)
            <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
            </span>
        @endif
    </a>

    <!-- ==================== SETTINGS SECTION ==================== -->
    <div class="nav-divider mt-2">
        <span class="menu-text">SETTINGS</span>
    </div>

    <!-- Profile Settings -->
    <a href="{{ route('contractor.profile.edit') }}"
       class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('contractor.profile.*') ? 'active' : '' }}">
        <i class="fas fa-user-cog mr-4"></i>
        <span class="nav-text">Profile Settings</span>
    </a>
</nav>

    <!-- Settings Button - Compact positioning -->
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
</div>

<!-- Theme Settings Modal -->
<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="theme-modal-compact">
        <!-- Modal Header -->
        <div class="theme-modal-header-compact flex justify-between items-center">
            <h3 class="text-base font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-palette mr-2"></i>Theme Settings
            </h3>
            <button id="closeThemeModal" class="p-1 rounded-full hover:bg-opacity-20 transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="theme-modal-body-compact">
            <!-- Appearance Selection -->
            <div class="mb-6">
                <h4 class="section-header-compact" style="color: var(--text-primary);">Appearance</h4>
                <p class="section-description-compact" style="color: var(--text-secondary);">Choose how the application looks</p>

                <div class="appearance-grid-compact">
                    <div class="appearance-option-compact relative" data-theme="light">
                        <div class="appearance-icon-compact" style="background-color: var(--light-theme-bg); border: 1px solid var(--border-color);">
                            <i class="fas fa-sun" style="color: var(--warning);"></i>
                        </div>
                        <span class="appearance-label-compact" style="color: var(--text-primary);">Light</span>
                    </div>

                    <div class="appearance-option-compact relative" data-theme="dark">
                        <div class="appearance-icon-compact" style="background-color: var(--dark-theme-bg); border: 1px solid var(--border-color);">
                            <i class="fas fa-moon" style="color: var(--info);"></i>
                        </div>
                        <span class="appearance-label-compact" style="color: var(--text-primary);">Dark</span>
                    </div>

                    <div class="appearance-option-compact relative" data-theme="system">
                        <div class="appearance-icon-compact" style="background: linear-gradient(to right, var(--light-theme-bg) 0%, var(--dark-theme-bg) 100%); border: 1px solid var(--border-color);">
                            <i class="fas fa-desktop" style="color: var(--text-secondary);"></i>
                        </div>
                        <span class="appearance-label-compact" style="color: var(--text-primary);">System</span>
                    </div>
                </div>
            </div>

            <!-- Sidebar Themes -->
            <div class="mb-4">
                <h4 class="section-header-compact" style="color: var(--text-primary);">Sidebar Theme</h4>
                <p class="section-description-compact" style="color: var(--text-secondary);">Customize your sidebar appearance</p>

                <div class="theme-grid-compact">
                    <div class="theme-option-compact relative" data-theme="default">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Default</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="dark">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #1f2937 0%, #374151 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Dark</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="light">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%); border: 1px solid var(--border-color);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Light</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="blue">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Blue</span>
                    </div>

                    <div class="theme-option-compact relative" data-theme="green">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Green</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="theme-modal-footer-compact flex justify-end" style="border-color: var(--border-color);">
            <button id="closeThemeModalBtn" class="px-3 py-1.5 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary); hover:color: var(--text-primary);">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Ownership Transfer Modal - UPDATED WITH THEME SUPPORT -->
<div id="transferModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl transfer-modal-container"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                animation: modalSlideUp 0.3s ease-out;">
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i>Transfer Property Ownership
            </h3>
            <button id="closeTransferModal" class="p-1 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2"
                       style="color: var(--text-primary);">
                    Select Property to Transfer
                </label>
                <select id="transferPropertySelect"
                        class="w-full px-4 py-2 rounded-lg focus:ring-2 transition-colors duration-200 appearance-none"
                        style="background-color: var(--bg-secondary);
                               color: var(--text-primary);
                               border: 1px solid var(--border-color);
                               outline: none;
                               background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                               background-position: right 0.5rem center;
                               background-repeat: no-repeat;
                               background-size: 1.5em 1.5em;
                               padding-right: 2.5rem;">
                    <option value="">-- Select a Property --</option>
                    @php
                        $properties = \App\Models\Property::where('landlord_id', auth()->id())
                            ->whereDoesntHave('currentOwnershipTransfer')
                            ->select('id', 'property_name', 'registration_pattern')
                            ->get();
                    @endphp
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}">
                            {{ $property->property_name }} ({{ $property->registration_pattern }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="transferFormContainer" class="hidden">
                <!-- Form will be loaded dynamically via AJAX -->
            </div>

            <!-- Info Box -->
            <div class="mt-4 p-3 rounded-lg"
                 style="background-color: rgba(var(--primary-rgb), 0.1);
                        border: 1px solid rgba(var(--primary-rgb), 0.3);">
                <p class="text-sm" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                    You will be redirected to the ownership transfer form for the selected property.
                </p>
            </div>
        </div>

        <div class="px-6 py-4 border-t flex justify-between"
             style="border-color: var(--border-color);
                    background-color: var(--bg-secondary);">
            <button id="cancelTransfer"
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary); hover:color: var(--text-primary);">
                Cancel
            </button>
            <button id="proceedToTransfer"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="background-color: var(--primary);
                           color: white;
                           hover:opacity: 0.9;">
                Proceed to Transfer
            </button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden pt-20">
    <div class="rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl search-modal-container"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-search mr-2" style="color: var(--primary);"></i>Advanced Search
            </h3>
            <button id="closeSearchModal"
                    class="p-1 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <!-- Search Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2"
                       style="color: var(--text-primary);">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Enter search term..."
                       class="w-full px-4 py-3 rounded-lg focus:ring-2 transition-colors duration-200"
                       style="background-color: var(--bg-secondary);
                              color: var(--text-primary);
                              border: 1px solid var(--border-color);
                              outline: none;">
            </div>

            <!-- Search Category -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2"
                       style="color: var(--text-primary);">
                    Search Category
                </label>
                <select id="searchCategory"
                        class="w-full px-4 py-3 rounded-lg focus:ring-2 transition-colors duration-200 appearance-none"
                        style="background-color: var(--bg-secondary);
                               color: var(--text-primary);
                               border: 1px solid var(--border-color);
                               outline: none;
                               background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                               background-position: right 0.5rem center;
                               background-repeat: no-repeat;
                               background-size: 1.5em 1.5em;
                               padding-right: 2.5rem;">
                    <option value="all">All Categories</option>
                    <option value="properties">Properties</option>
                    <option value="property-units">Property Units</option>
                    <option value="tenants">Tenants</option>
                    <option value="payments">Payments</option>
                    <option value="invoices">Invoices</option>
                    <option value="ownership-transfers">Ownership Transfers</option>
                </select>
            </div>

            <!-- Search Filters -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2"
                       style="color: var(--text-primary);">
                    Search Filters
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterExactMatch"
                                   class="rounded transition-colors duration-200"
                                   style="background-color: var(--bg-secondary);
                                          border-color: var(--border-color);
                                          color: var(--primary);">
                            <span class="ml-2 text-sm"
                                  style="color: var(--text-secondary);">Exact Match</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterCaseSensitive"
                                   class="rounded transition-colors duration-200"
                                   style="background-color: var(--bg-secondary);
                                          border-color: var(--border-color);
                                          color: var(--primary);">
                            <span class="ml-2 text-sm"
                                  style="color: var(--text-secondary);">Case Sensitive</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Recent Searches -->
            <div id="recentSearches" class="hidden">
                <label class="block text-sm font-medium mb-2"
                       style="color: var(--text-primary);">
                    Recent Searches
                </label>
                <div id="recentSearchesList" class="flex flex-wrap gap-2">
                    <!-- Recent searches will be populated here -->
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t flex justify-between"
             style="border-color: var(--border-color);
                    background-color: var(--bg-secondary);">
            <button id="clearSearch"
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary);
                           hover:color: var(--text-primary);">
                Clear
            </button>
            <div class="flex space-x-3">
                <button id="cancelSearch"
                        class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                        style="color: var(--text-secondary);
                               hover:color: var(--text-primary);">
                    Cancel
                </button>
                <button id="performSearch"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary);
                               color: white;">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Header -->
<div class="content" id="mainContent">
    <!-- Top Navigation -->
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button id="toggleSidebarMobile" class="mobile-menu-btn mr-4"
                    style="color: var(--text-secondary);">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">@yield('title', 'Dashboard')</h2>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Enhanced Search Bar -->
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <!-- Quick Search Input -->
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2"
                           style="color: var(--text-secondary);"></i>
                        <input type="text" id="quickSearchInput" placeholder="Quick search..."
                               class="header-search pl-10 pr-10 transition-colors duration-200"
                               style="color: var(--text-primary);
                                      background-color: var(--bg-secondary);
                                      border: 1px solid var(--border-color);
                                      outline: none;"
                               data-toggle="tooltip" title="Press / to focus">
                        <!-- Advanced Search Button - Now opens modal on click -->
                        <button id="advancedSearchBtn"
                                class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1 rounded transition-colors duration-200"
                                style="color: var(--text-secondary);
                                       hover:color: var(--text-primary);">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>
                    </div>

                    <!-- Mobile Search Button -->
                    <button id="mobileSearchBtn"
                            class="md:hidden p-2 rounded-full transition-colors duration-200"
                            style="color: var(--text-secondary);
                                   hover:background-color: var(--bg-secondary);">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <div class="header-buttons flex items-center space-x-4">
                <!-- Notification Bell Component with Glowing Red Light -->
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button
                        @click="open = !open; if(open) window.dispatchEvent(new CustomEvent('notifications-reload'))"
                        class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none transition-colors duration-200"
                        id="notificationBell"
                        style="color: var(--text-secondary); hover:color: var(--text-primary);"
                        aria-label="Notifications"
                    >
                        <!-- Glowing bell icon -->
                        <div class="relative">
                            <i class="fas fa-bell text-xl"></i>
                            @if($sidebarUnreadCount > 0)
                                <!-- Glowing red light effect -->
                                <div class="glowing-red-light"></div>

                                <!-- Notification count badge -->
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 text-xs flex items-center justify-center glowing-badge">
                                    {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                                </span>
                            @endif
                        </div>
                    </button>

                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 notification-dropdown"
                        style="display: none; background-color: var(--card-bg); border-color: var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);"
                    >
                        <div class="p-4 border-b border-gray-200" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-gray-900" style="color: var(--text-primary);">
                                    <i class="fas fa-bell mr-2"></i>Notifications
                                </h3>
                                @if($sidebarUnreadCount > 0)
                                    <button
                                        onclick="markAllAsRead()"
                                        class="text-sm text-blue-600 hover:text-blue-800 transition-colors duration-200"
                                        style="color: var(--primary); hover:color: var(--secondary);"
                                    >
                                        Mark all as read
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="max-h-96 overflow-y-auto" id="notificationList">
                            <div class="p-4 text-center text-gray-500" style="color: var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Loading notifications...
                            </div>
                        </div>

                        <div class="p-3 border-t border-gray-200" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-center">
                                <a
                                    href="{{ route('notifications.index') }}"
                                    class="text-sm text-blue-600 hover:text-blue-800 transition-colors duration-200"
                                    style="color: var(--primary); hover:color: var(--secondary);"
                                >
                                    <i class="fas fa-list mr-1"></i>
                                    View all
                                </a>
                                @if($sidebarUnreadCount > 0)
                                    <button
                                        onclick="clearAllNotifications()"
                                        class="text-sm text-red-600 hover:text-red-800 transition-colors duration-200"
                                        style="color: var(--danger); hover:color: var(--danger-dark);"
                                    >
                                        <i class="fas fa-trash-alt mr-1"></i>
                                        Clear all
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages/Email with Glowing Red Light -->
                <button class="relative p-2 rounded-full transition-colors duration-200"
                        style="color: var(--text-secondary);
                               hover:background-color: var(--bg-secondary);">
                    <div class="relative">
                        <i class="far fa-envelope text-xl"></i>
                        <!-- Glowing red light for email (optional) -->
                        <div class="notification-dot glowing-red-dot"></div>
                    </div>
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

                <div id="userDropdown" class="dropdown-menu"
                     style="background-color: var(--card-bg);
                            border: 1px solid var(--border-color);
                            color: var(--text-primary);">
                    <!-- Updated Profile Link -->
                    <a href="{{ route('contractor.profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>

                    <!-- Notifications -->
                    <a href="{{ route('notifications.index') }}" class="dropdown-item">
                        <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Notifications</span>
                        @if($sidebarUnreadCount > 0)
                            <span class="ml-auto text-xs px-2 py-1 rounded-full glowing-badge"
                                  style="background-color: var(--danger); color: white;">
                                {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                            </span>
                        @endif
                    </a>

                    <!-- Help & Support -->
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-question-circle mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Help & Support</span>
                    </a>

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    <!-- Logout -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-left"
                                style="color: var(--danger);">
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

/* =====================================================
   HEADER
   ===================================================== */
.header {
    padding: 12px 24px;
}
.header-search {
    min-width: 280px;
}
#mobileSearchBtn {
    padding: 8px;
}
.header-buttons {
    gap: 8px;
}
.dropdown-menu {
    min-width: 220px;
}
.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}
#advancedSearchBtn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: scale(1.1);
}

/* =====================================================
   NOTIFICATIONS DROPDOWN (full)
   ===================================================== */
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
.notifications-dropdown.hidden { display: none; }
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
.notifications-actions { display: flex; gap: 12px; }
.notifications-actions button {
    background: none; border: none; cursor: pointer; padding: 0;
}
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
    width: 40px; height: 40px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-secondary);
}
.notification-content { flex: 1; }
.notification-title {
    font-size: 14px; font-weight: 600;
    color: var(--text-primary); margin-bottom: 4px;
}
.notification-message {
    font-size: 13px; color: var(--text-secondary); line-height: 1.4;
}
.notification-time {
    font-size: 11px; color: var(--text-secondary); margin-top: 4px;
}
.notifications-loading { padding: 32px; text-align: center; color: var(--text-secondary); }
.notifications-empty { padding: 48px 32px; text-align: center; color: var(--text-secondary); }
.notifications-empty i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
.notifications-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--border-color);
    text-align: center;
}
.view-all-link {
    font-size: 13px; color: #3b82f6;
    text-decoration: none; font-weight: 500;
}
.view-all-link:hover { text-decoration: underline; }
.notification-badge { position: relative; }

@media (max-width: 768px) {
    .header { padding: 10px 16px; }
    .avatar-minimal { width: 28px; height: 28px; }
    .header-buttons { gap: 6px; }
    .notifications-dropdown {
        position: fixed;
        top: 60px; right: 10px; left: 10px;
        width: auto; max-width: none;
    }
}

/* Dark mode avatar */
[data-theme="dark"] .avatar-minimal { border-color: #4b5563; }
[data-theme="dark"] .avatar-minimal:hover { border-color: var(--primary); }

/* Smooth transitions */
.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn { transition: all 0.2s ease-in-out; }

/* =====================================================
   THEME SETTINGS MODAL (COMPACT)
   ===================================================== */
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
.theme-modal-header-compact { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; }
.theme-modal-body-compact { padding: 20px; }
.theme-modal-footer-compact { padding: 16px 20px; border-top: 1px solid #e5e7eb; }
.section-header-compact {
    font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 4px;
}
.section-description-compact {
    font-size: 12px; color: #6b7280; margin-bottom: 16px;
}
.appearance-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.appearance-option-compact {
    display: flex; flex-direction: column; align-items: center;
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
    width: 48px; height: 48px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.appearance-label-compact {
    font-size: 12px; font-weight: 500; color: #374151;
}
.theme-grid-compact {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}
.theme-option-compact {
    display: flex; flex-direction: column; align-items: center;
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
    width: 40px; height: 40px;
    border-radius: 6px;
    margin-bottom: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
.theme-label-compact {
    font-size: 11px; font-weight: 500; color: #374151;
}

/* =====================================================
   KEYFRAMES (merged — your modalSlideIn is kept canonical)
   ===================================================== */
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes modalSlideIn {
    from { opacity: 0; transform: scale(0.9); }
    to   { opacity: 1; transform: scale(1); }
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-5px); }
    60% { transform: translateY(-3px); }
}
@keyframes bellRing {
    0%   { transform: rotate(0); }
    25%  { transform: rotate(15deg); }
    50%  { transform: rotate(-15deg); }
    75%  { transform: rotate(10deg); }
    100% { transform: rotate(0); }
}
@keyframes notificationPulse {
    0%   { transform: scale(1); opacity: 1; }
    50%  { transform: scale(1.1); opacity: 0.85; }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
}
@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to   { transform: translateX(100%); opacity: 0; }
}

/* Current selection indicator */
.current-selection {
    position: absolute;
    top: 8px; right: 8px;
    width: 20px; height: 20px;
    background: var(--primary);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* =====================================================
   DARK MODE — THEME MODAL
   ===================================================== */
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
[data-theme="dark"] .theme-option-compact.active {
    background-color: rgba(var(--primary-rgb), 0.2);
}

/* Badge colors (tenant) */
.bg-orange-500 { background-color: #f97316; }
.bg-yellow-500 { background-color: #f59e0b; }
.bg-blue-500   { background-color: #3b82f6; }
.bg-red-500    { background-color: #ef4444; }

/* Account item */
.account-item { transition: all 0.2s ease; }
.account-item:hover { background-color: rgba(var(--primary-rgb), 0.05); }

/* =====================================================
   SIDEBAR — CORE VARIABLES
   ===================================================== */
:root {
    --sidebar-collapsed-width: 60px;
    --sidebar-mobile-width: 280px;
    --sidebar-toggle-offset: -14px;
    --sidebar-toggle-size: 28px;
}

/* =====================================================
   SIDEBAR COLLAPSE — CORE
   ===================================================== */
.sidebar.collapsed {
    width: var(--sidebar-collapsed-width) !important;
    min-width: var(--sidebar-collapsed-width) !important;
    max-width: var(--sidebar-collapsed-width) !important;
}

.sidebar.collapsed .nav-divider {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    min-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
}

.sidebar.collapsed nav { padding: 4px 0 !important; }

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
.sidebar.collapsed .nav-item .flex-grow,
.sidebar.collapsed .nav-item .menu-text {
    display: none !important;
}
.sidebar.collapsed .nav-item .bg-yellow-500,
.sidebar.collapsed .nav-item .bg-red-500,
.sidebar.collapsed .nav-item .bg-blue-500,
.sidebar.collapsed .nav-item .bg-orange-500 {
    display: none !important;
}

/* Logo section */
.sidebar.collapsed .logo-section {
    padding: 10px 6px !important;
    position: relative !important;
}
.sidebar.collapsed .logo-text-container,
.sidebar.collapsed .logo-fullname,
.sidebar.collapsed .logo-shortname {
    display: none !important;
}
.sidebar.collapsed .logo-wrapper { justify-content: center !important; }
.sidebar.collapsed .logo-image-container,
.sidebar.collapsed .logo-default {
    width: 32px !important;
    height: 32px !important;
}
.sidebar.collapsed .logo-separator { margin: 4px 8px !important; }

/* Toggle button — collapsed */
.sidebar.collapsed .toggle-sidebar {
    display: flex !important;
    position: absolute !important;
    right: var(--sidebar-toggle-offset) !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 100 !important;
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
    width: var(--sidebar-toggle-size) !important;
    height: var(--sidebar-toggle-size) !important;
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

.sidebar.collapsed .absolute.bottom-0 { padding: 6px 0 !important; }
.sidebar.collapsed #settingsIcon { font-size: 1rem !important; }

/* Active state — inset shadow keeps icon perfectly centered */
.sidebar.collapsed .nav-item.active {
    background: rgba(var(--primary-rgb), 0.15) !important;
    box-shadow: inset 3px 0 0 var(--primary) !important;
}

/* Spacing normalization */
.sidebar.collapsed .nav-divider + .nav-item { margin-top: 2px !important; }
.sidebar.collapsed .nav-item + .nav-item { margin-top: 2px !important; }

/* Center inner button / link */
.sidebar.collapsed .nav-item button,
.sidebar.collapsed .nav-item a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    padding: 0 !important;
}

/* =====================================================
   TOGGLE BUTTON — EXPANDED MODE
   ===================================================== */
.toggle-sidebar {
    cursor: pointer;
    display: flex !important;
    align-items: center;
    justify-content: center;
    width: var(--sidebar-toggle-size);
    height: var(--sidebar-toggle-size);
    border-radius: 50%;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.25);
    color: var(--sidebar-text, #ffffff);
    position: absolute;
    right: var(--sidebar-toggle-offset);
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
.logo-section { position: relative !important; }

[data-sidebar-theme="dark"] .toggle-sidebar,
[data-sidebar-theme="default"] .toggle-sidebar,
[data-sidebar-theme="blue"] .toggle-sidebar,
[data-sidebar-theme="green"] .toggle-sidebar,
[data-sidebar-theme="light"] .toggle-sidebar {
    background-color: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

/* =====================================================
   SIDEBAR THEMES
   ===================================================== */
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
.sidebar:not([data-sidebar-theme]) { --sidebar-text: #ffffff; }

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

/* =====================================================
   MOBILE SIDEBAR + OVERLAY
   ===================================================== */
.overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.overlay.active {
    display: block;
    opacity: 1;
}

@media (max-width: 768px) {
    /* Off-canvas base */
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        position: fixed !important;
        top: 0; left: 0;
        height: 100vh;
        z-index: 1050;
        width: var(--sidebar-mobile-width) !important;
    }
    .sidebar.mobile-open { transform: translateX(0); }

    /* Collapsed drawer hidden */
    .sidebar.collapsed:not(.mobile-open) {
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .sidebar.collapsed:not(.mobile-open) .logo-section,
    .sidebar.collapsed:not(.mobile-open) nav,
    .sidebar.collapsed:not(.mobile-open) .absolute.bottom-0 {
        display: none !important;
    }

    /* Mobile drawer open — restore full content even when collapsed */
    .sidebar.collapsed.mobile-open {
        width: var(--sidebar-mobile-width) !important;
        min-width: var(--sidebar-mobile-width) !important;
        max-width: var(--sidebar-mobile-width) !important;
        overflow-y: auto !important;
        padding: initial !important;
        margin: initial !important;
    }
    .sidebar.collapsed.mobile-open .logo-section {
        display: block !important;
        padding: 16px !important;
    }
    .sidebar.collapsed.mobile-open nav {
        display: block !important;
        padding: 4px 0 !important;
    }
    .sidebar.collapsed.mobile-open .absolute.bottom-0 {
        display: block !important;
        padding: 12px 0 !important;
    }
    .sidebar.collapsed.mobile-open .nav-divider { display: flex !important; }
    .sidebar.collapsed.mobile-open .nav-text,
    .sidebar.collapsed.mobile-open .menu-text { display: inline !important; }
    .sidebar.collapsed.mobile-open .logo-text-container,
    .sidebar.collapsed.mobile-open .logo-fullname,
    .sidebar.collapsed.mobile-open .logo-shortname { display: block !important; }
    .sidebar.collapsed.mobile-open .toggle-sidebar {
        display: flex !important;
        right: -12px !important;
    }
    .sidebar.collapsed.mobile-open .ml-auto { display: inline-flex !important; }
    .sidebar.collapsed.mobile-open .bg-yellow-500,
    .sidebar.collapsed.mobile-open .bg-red-500,
    .sidebar.collapsed.mobile-open .bg-blue-500,
    .sidebar.collapsed.mobile-open .bg-orange-500 { display: inline-flex !important; }

    /* Mobile toggle sizing */
    .toggle-sidebar,
    .sidebar.collapsed .toggle-sidebar {
        right: -12px !important;
        width: 24px !important;
        height: 24px !important;
        font-size: 10px !important;
    }
}

@media (max-width: 480px) {
    .toggle-sidebar,
    .sidebar.collapsed .toggle-sidebar {
        right: -10px !important;
        width: 22px !important;
        height: 22px !important;
        font-size: 9px !important;
    }
}

/* =====================================================
   NOTIFICATION BADGE (consolidated + animated)
   ===================================================== */
.notification-badge {
    position: absolute;
    top: -4px; right: -4px;
    background-color: #ef4444;
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    font-size: 10px;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--card-bg, #ffffff);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    animation: notificationPulse 2s infinite;
    z-index: 10;
    transform-origin: center;
}
[data-theme="light"] .notification-badge {
    border-color: #ffffff;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}
[data-theme="dark"] .notification-badge {
    border-color: #1a1e2c;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
}
#notificationBell {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
#notificationBell:hover .fa-bell {
    animation: bellRing 0.5s ease-in-out;
}

/* =====================================================
   PLACEHOLDER THEMING (valid syntax)
   ===================================================== */
[data-theme="light"] ::placeholder { color: #a0aec0 !important; opacity: 1; }
[data-theme="dark"]  ::placeholder { color: #718096 !important; opacity: 1; }

/* =====================================================
   MISC
   ===================================================== */
.modal-open { overflow: hidden; }

@media (max-width: 768px) {
    .nav-item .ml-auto { display: none; }
}

#toast-container {
    position: fixed;
    bottom: 20px; right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ============================================
    // SIDEBAR COLLAPSE / EXPAND
    // ============================================
    const sidebar = document.getElementById('sidebar');
    const toggleDesktop = document.getElementById('toggleSidebarDesktop');
    const toggleMobile = document.getElementById('toggleSidebarMobile');
    const overlay = document.getElementById('overlay');
    const mainContent = document.getElementById('mainContent');

    if (!sidebar) return;

    const DESKTOP_BREAKPOINT = 768;
    const STORAGE_KEY = 'sidebarCollapsed';

    function isMobile() {
        return window.innerWidth <= DESKTOP_BREAKPOINT;
    }

    // Restore collapsed state on desktop
    if (!isMobile() && localStorage.getItem(STORAGE_KEY) === 'true') {
        sidebar.classList.add('collapsed');
        document.body.classList.add('sidebar-collapsed');
    }

    // ---- DESKTOP toggle (the round chevron button) ----
    function handleDesktopToggle(e) {
        e.preventDefault();
        e.stopPropagation();

        // On mobile, treat the same button as "close drawer"
        if (isMobile()) {
            closeMobileSidebar();
            return;
        }

        const collapsed = sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');

        // Nudge the chevron rotation (CSS also handles this, but this is a safety net)
        const icon = toggleDesktop?.querySelector('i');
        if (icon) {
            icon.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    if (toggleDesktop) {
        toggleDesktop.addEventListener('click', handleDesktopToggle);
    }

    // ---- MOBILE open / close ----
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        overlay?.classList.add('active');
        document.body.classList.add('modal-open');
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay?.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    if (toggleMobile) {
        toggleMobile.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    // Close the mobile drawer when a nav link is clicked
    sidebar.querySelectorAll('a.nav-item').forEach(link => {
        link.addEventListener('click', function () {
            if (isMobile()) closeMobileSidebar();
        });
    });

    // Auto-cleanup when resizing between mobile / desktop
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!isMobile()) {
                // Returning to desktop: ensure drawer state is cleared
                closeMobileSidebar();
                // Re-apply persisted collapsed state
                const collapsed = localStorage.getItem(STORAGE_KEY) === 'true';
                sidebar.classList.toggle('collapsed', collapsed);
                document.body.classList.toggle('sidebar-collapsed', collapsed);
            } else {
                // Going to mobile: drop collapsed class so drawer opens fully
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
            }
        }, 150);
    });

    // Escape key closes mobile drawer
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeMobileSidebar();
        }
    });
});
</script>