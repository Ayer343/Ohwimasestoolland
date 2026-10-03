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


@include('components.partials.landlord.style.style')
@include('components.partials.landlord.scripts.script')