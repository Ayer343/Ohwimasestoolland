@extends('layouts.landlord')

@section('title', 'Dashboard')

@push('styles')
<style>
    /* CRITICAL THEME STYLES - Prevent Flash of Incorrect Theme */
    /* These styles load immediately and prevent the white flash */
    
    /* Light theme variables (default) */
    :root {
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --bg-secondary: #f9fafb;
        --border-color: #e5e7eb;
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --secondary: #8b5cf6;
        --secondary-rgb: 139, 92, 246;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --warning: #f59e0b;
        --warning-rgb: 245, 158, 11;
        --danger: #ef4444;
        --danger-rgb: 239, 68, 68;
        --info: #06b6d4;
        --info-rgb: 6, 182, 212;
        --border-color-rgb: 229, 231, 235;
    }
    
    /* Dark theme variables - applied immediately */
    html.dark-theme,
    html[data-theme="dark"] {
        --text-primary: #f3f4f6;
        --text-secondary: #9ca3af;
        --card-bg: #1f2937;
        --bg-secondary: #111827;
        --border-color: #374151;
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --secondary: #8b5cf6;
        --secondary-rgb: 139, 92, 246;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --warning: #f59e0b;
        --warning-rgb: 245, 158, 11;
        --danger: #ef4444;
        --danger-rgb: 239, 68, 68;
        --info: #06b6d4;
        --info-rgb: 6, 182, 212;
        --border-color-rgb: 55, 65, 81;
    }
    
    /* Ensure body background matches theme immediately */
    body {
        background-color: var(--bg-secondary);
        margin: 0;
        padding: 0;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* Dashboard Container Styles */
    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 1rem;
    }

    /* Dashboard Switcher Styles */
    .dashboard-switcher {
        position: relative;
    }

    #dashboardSwitcherMenu {
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
        transform-origin: top right;
        opacity: 0;
        transform: translateY(-10px);
        visibility: hidden;
    }

    #dashboardSwitcherMenu.show {
        opacity: 1;
        transform: translateY(0);
        visibility: visible;
        display: block !important;
    }

    .dashboard-switch-option {
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .dashboard-switch-option:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        padding-left: 1rem;
    }

    .active-option {
        background-color: rgba(var(--primary-rgb), 0.1);
        border-left: 3px solid var(--primary);
    }

    /* Stat Cards */
    .stat-card {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .stat-icon-success {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    }

    .stat-icon-info {
        background: linear-gradient(135deg, var(--info) 0%, #0891b2 100%);
    }

    .stat-icon-warning {
        background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
    }

    /* Financial Summary Cards */
    .financial-card {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .financial-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .financial-amount {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--primary);
    }

    .financial-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Maintenance Summary Cards */
    .maintenance-card {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .maintenance-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .maintenance-count {
        font-size: 1.5rem;
        font-weight: bold;
    }

    .maintenance-urgent {
        color: var(--danger);
    }

    .maintenance-pending {
        color: var(--warning);
    }

    .maintenance-completed {
        color: var(--success);
    }

    /* Chart Cards */
    .chart-card, .stats-card, .recent-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .chart-card:hover, .stats-card:hover, .recent-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Status Badges */
    .badge-active, .badge-completed, .badge-paid, .badge-success {
        background-color: rgba(var(--success-rgb), 0.2);
        color: var(--success);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-pending, .badge-warning {
        background-color: rgba(var(--warning-rgb), 0.2);
        color: var(--warning);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-inactive, .badge-cancelled, .badge-failed, .badge-danger {
        background-color: rgba(var(--danger-rgb), 0.2);
        color: var(--danger);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-info, .badge-processing {
        background-color: rgba(var(--info-rgb), 0.2);
        color: var(--info);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    /* Chart Period Buttons */
    .chart-period-btn {
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        cursor: pointer;
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
        border: none;
    }

    .chart-period-btn.active {
        background-color: var(--primary);
        color: white;
    }

    .chart-period-btn:hover:not(.active) {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }

    /* List Group Items */
    .list-group-item-custom {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.2s ease;
        background-color: var(--card-bg);
    }

    .list-group-item-custom:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    html.dark-theme .list-group-item-custom:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* Dropdown */
    .payment-dropdown {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .payment-dropdown-content {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        margin-bottom: 0.5rem;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        z-index: 50;
        max-height: 200px;
        overflow-y: auto;
    }

    .payment-dropdown-content.hidden {
        display: none;
    }

    .payment-property-item {
        padding: 0.5rem 1rem;
        transition: background-color 0.2s ease;
        cursor: pointer;
        display: block;
        text-decoration: none;
        color: var(--text-primary);
    }

    .payment-property-item:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
    }

    /* Error Message */
    .error-message {
        background-color: rgba(var(--danger-rgb), 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .error-message.hidden {
        display: none;
    }

    /* Upcoming Leases Section */
    .upcoming-leases-list {
        max-height: 300px;
        overflow-y: auto;
    }

    /* Progress Bar */
    .progress-bar-container {
        background-color: var(--bg-secondary);
        border-radius: 9999px;
        height: 0.5rem;
        overflow: hidden;
    }

    .progress-bar-fill {
        background-color: var(--primary);
        height: 100%;
        border-radius: 9999px;
        transition: width 0.3s ease;
    }

    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    }

    .toast-success {
        background-color: var(--success);
    }

    .toast-error {
        background-color: var(--danger);
    }

    .toast-info {
        background-color: var(--info);
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Loading Spinner */
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.6s linear infinite;
        margin-right: 8px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0.5rem;
        }
        
        .stat-card {
            padding: 1rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
        }
        
        .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
        }
        
        .stat-icon i {
            font-size: 1.25rem;
        }
        
        .financial-amount {
            font-size: 1.25rem;
        }
        
        .maintenance-count {
            font-size: 1.25rem;
        }
    }

    .bg-light {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    html.dark-theme .bg-light {
        background-color: rgba(255, 255, 255, 0.02);
    }
    
    /* Occupancy Chart Tooltip */
    .occupancy-tooltip {
        position: absolute;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        pointer-events: none;
        z-index: 1000;
    }
</style>
@endpush

{{-- CRITICAL: Theme initialization script that runs BEFORE page render --}}
@push('scripts')
<script>
    // THIS MUST RUN IMMEDIATELY TO PREVENT THEME FLASH
    (function() {
        // Check for saved theme preference
        let savedTheme = localStorage.getItem('theme');
        let theme = savedTheme;
        
        // If no saved theme, check system preference
        if (!theme) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            theme = prefersDark ? 'dark' : 'light';
        }
        
        // Apply theme class IMMEDIATELY to html element
        if (theme === 'dark') {
            document.documentElement.classList.add('dark-theme');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark-theme');
            document.documentElement.setAttribute('data-theme', 'light');
        }
        
        // Set theme-color meta tag to prevent browser white flash
        const metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.content = theme === 'dark' ? '#1f2937' : '#f9fafb';
        } else {
            const meta = document.createElement('meta');
            meta.name = "theme-color";
            meta.content = theme === 'dark' ? '#1f2937' : '#f9fafb';
            document.head.appendChild(meta);
        }
        
        // Also set background color on body immediately
        document.body.style.backgroundColor = theme === 'dark' ? '#111827' : '#f9fafb';
    })();
</script>
@endpush

@section('content')
<div class="dashboard-container">
    <!-- Page Header with Dashboard Switcher -->
    <div class="flex justify-between items-center flex-wrap gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-tachometer-alt mr-2" style="color: var(--primary);"></i>
                Landlord Dashboard
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Welcome back, {{ Auth::user()->name }}! Here's what's happening with your properties today.
            </p>
        </div>
        
        <div class="flex items-center gap-3 flex-wrap">
            <!-- Dashboard Switcher (Multi-Role Support) - DUAL MODE -->
            @php
                // ==================== DUAL MODE ROLE DETECTION ====================
                // Get user roles from role system
                $roleBasedRoles = auth()->user()->roles ?? collect();
                $roleBasedRoleSlugs = $roleBasedRoles->pluck('slug')->toArray();
                
                // Get legacy type as a "virtual role"
                $legacyType = auth()->user()->type;
                $legacyTypeRoleMap = [
                    0 => 'super-admin',
                    1 => 'admin',
                    2 => 'landlord',
                    3 => 'tenant',
                    4 => 'field-agent',
                    5 => 'developer',
                    6 => 'security-personnel',
                ];
                $legacyRoleSlug = $legacyTypeRoleMap[$legacyType] ?? null;
                
                // Combine both sources for all roles
                $allRoles = $roleBasedRoleSlugs;
                
                // Add legacy type as a role if it's not already in the role-based list
                if ($legacyRoleSlug && !in_array($legacyRoleSlug, $allRoles)) {
                    $allRoles[] = $legacyRoleSlug;
                }
                
                // Check if user has multiple roles (either from role system OR from legacy type + role)
                $hasMultipleRoles = count($allRoles) > 1;
                
                // Get current role from session
                $currentRole = session('selected_role', 'landlord');
                
                // Role icon mapping
                $roleIcons = [
                    'super-admin' => 'crown',
                    'admin' => 'shield-alt',
                    'landlord' => 'home',
                    'tenant' => 'user',
                    'field-agent' => 'clipboard-list',
                    'security-personnel' => 'shield-alt',
                    'developer' => 'code',
                ];
                $currentIcon = $roleIcons[$currentRole] ?? 'tachometer-alt';
                
                // Build the combined roles list for the switcher menu
                $combinedRoles = [];
                $addedSlugs = [];
                
                // Add legacy type as a role option if it's valid
                if ($legacyRoleSlug && !in_array($legacyRoleSlug, $addedSlugs)) {
                    $combinedRoles[] = (object)[
                        'slug' => $legacyRoleSlug,
                        'display_name' => ucfirst(str_replace('-', ' ', $legacyRoleSlug)),
                        'source' => 'legacy'
                    ];
                    $addedSlugs[] = $legacyRoleSlug;
                }
                
                // Add role-based roles that aren't already represented
                foreach ($roleBasedRoles as $role) {
                    if (!in_array($role->slug, $addedSlugs)) {
                        $combinedRoles[] = (object)[
                            'slug' => $role->slug,
                            'display_name' => $role->display_name ?? ucfirst(str_replace('-', ' ', $role->slug)),
                            'source' => 'role'
                        ];
                        $addedSlugs[] = $role->slug;
                    }
                }
            @endphp
            
            @if($hasMultipleRoles)
            <div class="dashboard-switcher relative">
                <button id="dashboardSwitcherBtn" 
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <i class="fas fa-{{ $currentIcon }} mr-1"></i>
                    <span class="text-sm font-medium">{{ ucfirst(str_replace('-', ' ', $currentRole)) }}</span>
                    <i class="fas fa-chevron-down text-xs ml-1"></i>
                </button>
                
                <div id="dashboardSwitcherMenu" 
                     class="absolute right-0 mt-2 w-72 rounded-xl shadow-lg overflow-hidden z-50 hidden"
                     style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="py-2">
                        <div class="px-4 py-2 border-b" style="border-color: var(--border-color);">
                            <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">
                                <i class="fas fa-layer-group mr-1"></i> Switch Dashboard
                            </p>
                        </div>
                        @foreach($combinedRoles as $role)
                            @php
                                $isActive = ($currentRole === $role->slug);
                                $icon = $roleIcons[$role->slug] ?? 'user';
                            @endphp
                            <button type="button"
                                    class="dashboard-switch-option w-full text-left flex items-center gap-3 px-4 py-3 transition-all hover:bg-opacity-10 {{ $isActive ? 'active-option' : '' }}"
                                    data-role="{{ $role->slug }}"
                                    style="display: flex; color: var(--text-primary); {{ $isActive ? 'background-color: rgba(var(--primary-rgb), 0.1); border-left: 3px solid var(--primary);' : '' }}">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center" 
                                     style="background: {{ $isActive ? 'linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%)' : 'rgba(var(--primary-rgb), 0.1)' }}">
                                    <i class="fas fa-{{ $icon }} text-sm" style="color: {{ $isActive ? 'white' : 'var(--primary)' }}"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium">{{ ucfirst(str_replace('-', ' ', $role->slug)) }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        @if(($role->source ?? '') === 'legacy')
                                            <span class="inline-flex items-center gap-1">
                                                <i class="fas fa-database text-xs"></i> Legacy Type
                                            </span>
                                        @else
                                            @switch($role->slug)
                                                @case('super-admin') Full system control @break
                                                @case('admin') System management @break
                                                @case('landlord') Property portfolio @break
                                                @case('tenant') Rental management @break
                                                @case('field-agent') Property registration @break
                                                @case('security-personnel') Security operations @break
                                                @case('developer') System development @break
                                                @default Dashboard access
                                            @endswitch
                                        @endif
                                    </p>
                                </div>
                                @if($isActive)
                                    <i class="fas fa-check-circle mr-2" style="color: var(--success); font-size: 0.875rem;"></i>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
            
            <button class="chart-period-btn" id="refreshDashboardBtn">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
            <button class="chart-period-btn" id="exportDashboardBtn">
                <i class="fas fa-download mr-1"></i> Export
            </button>
            <button class="chart-period-btn" id="widgetSettingsBtn">
                <i class="fas fa-th-large mr-1"></i> Customize
            </button>
        </div>
    </div>

    <!-- Error Message Display -->
    <div id="errorMessage" class="error-message hidden">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <span id="errorText"></span>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="totalProperties">
                        {{ $stats['total_properties'] ?? 0 }}
                    </p>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-building text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Units</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="totalUnits">
                        {{ $stats['total_units'] ?? 0 }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs" style="color: var(--text-secondary);">Occupancy Rate:</span>
                        <span class="text-xs font-semibold ml-1" id="occupancyRate">{{ $stats['occupancy_rate'] ?? 0 }}%</span>
                    </div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-door-open text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Monthly Revenue</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="monthlyRevenue">
                        GH₵{{ number_format($stats['monthly_revenue'] ?? 0, 2) }}
                    </p>
                </div>
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-currency-dollar text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active Tenants</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="totalTenants">
                        {{ $stats['total_tenants'] ?? 0 }}
                    </p>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial & Maintenance Summary Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Financial Summary Card -->
        <div class="stats-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Financial Summary
                </h3>
                <a href="{{ route('landlord.financial.summary') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View Details <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="financial-card">
                    <div class="financial-label">Total Revenue</div>
                    <div class="financial-amount" id="totalRevenue">GH₵0</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">All time</div>
                </div>
                <div class="financial-card">
                    <div class="financial-label">Outstanding Balance</div>
                    <div class="financial-amount" style="color: var(--danger);" id="totalOutstanding">GH₵0</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Pending payments</div>
                </div>
                <div class="financial-card">
                    <div class="financial-label">Collection Rate</div>
                    <div class="financial-amount" id="collectionRate">0%</div>
                    <div class="progress-bar-container mt-2">
                        <div class="progress-bar-fill" id="collectionProgressBar" style="width: 0%"></div>
                    </div>
                </div>
                <div class="financial-card">
                    <div class="financial-label">Total Invoices</div>
                    <div class="financial-amount" id="totalInvoices">0</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        <span id="paidInvoices">0</span> paid / <span id="unpaidInvoices">0</span> unpaid
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Summary Card -->
        <div class="stats-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>Maintenance Summary
                </h3>
                <a href="{{ route('landlord.maintenance.analytics') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View Details <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="maintenance-card">
                    <div class="financial-label">Total Requests</div>
                    <div class="maintenance-count" id="totalRequests">0</div>
                </div>
                <div class="maintenance-card">
                    <div class="financial-label">Completion Rate</div>
                    <div class="maintenance-count" id="completionRate">0%</div>
                    <div class="progress-bar-container mt-2">
                        <div class="progress-bar-fill" id="completionProgressBar" style="width: 0%"></div>
                    </div>
                </div>
                <div class="maintenance-card">
                    <div class="financial-label">Pending Requests</div>
                    <div class="maintenance-count maintenance-pending" id="pendingRequests">0</div>
                </div>
                <div class="maintenance-card">
                    <div class="financial-label">Urgent Issues</div>
                    <div class="maintenance-count maintenance-urgent" id="urgentRequests">0</div>
                </div>
                <div class="maintenance-card">
                    <div class="financial-label">Avg Resolution Time</div>
                    <div class="maintenance-count" id="avgResolutionTime">0 days</div>
                </div>
                <div class="maintenance-card">
                    <div class="financial-label">Total Cost</div>
                    <div class="maintenance-count" id="totalMaintenanceCost">GH₵0</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Revenue Overview
                </h3>
                <select class="chart-period-btn" id="revenueYearSelect">
                    <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                    <option value="{{ date('Y') - 1 }}">{{ date('Y') - 1 }}</option>
                    <option value="{{ date('Y') - 2 }}">{{ date('Y') - 2 }}</option>
                </select>
            </div>
            <canvas id="revenueChart" height="250" style="max-height: 250px;"></canvas>
        </div>

        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Occupancy Trends
                </h3>
                <div class="flex gap-2">
                    <button class="chart-period-btn occupancy-period-btn active" data-period="monthly">Monthly</button>
                    <button class="chart-period-btn occupancy-period-btn" data-period="quarterly">Quarterly</button>
                </div>
            </div>
            <canvas id="occupancyChart" height="250" style="max-height: 250px;"></canvas>
            <div class="mt-3 text-center">
                <small class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i> 
                    Occupancy is calculated based on active rental agreements (tenants who have signed and are currently renting)
                </small>
            </div>
        </div>
    </div>

    <!-- Recent Activity & Upcoming Leases Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="recent-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i>Recent Payments
                    </h3>
                    <a href="{{ route('landlord.payments.history') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div id="recentPaymentsList">
                @forelse($recentPayments ?? [] as $payment)
                <div class="list-group-item-custom">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ $payment->invoices->first()->tenant->name ?? 'Unknown' }}</p>
                            <p class="text-xs mt-1">{{ $payment->created_at->format('M d, Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold">GH₵{{ number_format($payment->amount, 2) }}</p>
                            <span class="badge payment-status-{{ $payment->status }}">{{ $payment->status }}</span>
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-credit-card text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No recent payments</p>
                </div>
                @endforelse
            </div>
        </div>

        <div class="recent-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>Upcoming Lease Expirations
                    </h3>
                    <a href="{{ route('landlord.leases.analytics') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div id="upcomingLeasesList" class="upcoming-leases-list">
                @forelse($upcomingLeaseExpirations ?? [] as $lease)
                <div class="list-group-item-custom">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ $lease->unit->property->property_name ?? 'N/A' }} - Unit {{ $lease->unit->unit_number ?? 'N/A' }}</p>
                            <p class="text-xs mt-1">{{ $lease->tenant->name ?? 'Unknown' }}</p>
                        </div>
                        <div class="text-right">
                            @php
                                $daysLeft = \Carbon\Carbon::parse($lease->end_date)->diffInDays(now());
                            @endphp
                            <span class="badge {{ $daysLeft <= 7 ? 'badge-danger' : ($daysLeft <= 30 ? 'badge-warning' : 'badge-info') }}">
                                {{ $daysLeft }} days left
                            </span>
                            <p class="text-xs mt-1">Ends: {{ $lease->end_date->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-calendar-check text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No upcoming lease expirations</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="stats-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Pending Actions
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="alert-card" style="background-color: rgba(var(--warning-rgb), 0.1); padding: 1rem; border-radius: 0.75rem;">
                    <div class="flex justify-between items-center">
                        <span><i class="fas fa-user-plus"></i> Tenant Approvals</span>
                        <span class="badge-warning" id="pendingTenantApprovals">{{ $pendingActions['tenant_approvals'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="alert-card" style="background-color: rgba(var(--info-rgb), 0.1); padding: 1rem; border-radius: 0.75rem;">
                    <div class="flex justify-between items-center">
                        <span><i class="fas fa-file-signature"></i> Pending Lease Signatures</span>
                        <span class="badge-info" id="pendingLeaseSignatures">{{ $stats['pending_lease_signatures'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="alert-card" style="background-color: rgba(var(--danger-rgb), 0.1); padding: 1rem; border-radius: 0.75rem;">
                    <div class="flex justify-between items-center">
                        <span><i class="fas fa-envelope"></i> Unread Messages</span>
                        <span class="badge-danger" id="unreadMessages">{{ $pendingActions['unread_messages'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="alert-card" style="background-color: rgba(var(--success-rgb), 0.1); padding: 1rem; border-radius: 0.75rem;">
                    <div class="flex justify-between items-center">
                        <span><i class="fas fa-file-invoice"></i> Pending Invoices</span>
                        <span class="badge-success" id="pendingInvoices">{{ $stats['pending_payments'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('property-units.create') }}" class="chart-period-btn text-center py-2" style="background-color: var(--success); color: white; text-decoration: none;">
                    <i class="fas fa-plus-circle mr-1"></i> Add Property Unit
                </a>
                <a href="{{ route('landlord.financial.summary') }}" class="chart-period-btn text-center py-2" style="background-color: var(--info); color: white; text-decoration: none;">
                    <i class="fas fa-chart-line mr-1"></i> Financial Reports
                </a>
                <a href="{{ route('landlord.maintenance.analytics') }}" class="chart-period-btn text-center py-2" style="background-color: var(--warning); color: white; text-decoration: none;">
                    <i class="fas fa-tools mr-1"></i> Maintenance Overview
                </a>
                <a href="{{ route('dashboard.testimonials.index') }}" 
                   class="chart-period-btn text-center py-2" 
                   style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); text-decoration: none;">
                    <i class="fas fa-star mr-1"></i> Share Feedback
                </a>
                <div class="payment-dropdown">
                    <button id="collectPaymentBtn" class="chart-period-btn text-center py-2 w-full" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-cash-register mr-1"></i> Collect Payment
                        <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    <div id="collectPaymentDropdown" class="payment-dropdown-content hidden">
                        <div class="p-2" id="propertyListForPayment">
                            <div class="text-center py-2 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Loading properties...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div class="mt-8">
        <div class="recent-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-bell mr-2" style="color: var(--primary);"></i>Recent Notifications
                    </h3>
                    <div class="flex gap-2">
                        <button class="chart-period-btn" id="markAllReadBtn">
                            <i class="fas fa-check-double mr-1"></i> Mark All Read
                        </button>
                        <button class="chart-period-btn" id="clearAllNotificationsBtn">
                            <i class="fas fa-trash-alt mr-1"></i> Clear All
                        </button>
                    </div>
                </div>
            </div>
            <div id="notificationsList">
                @forelse($notifications ?? [] as $notification)
                <div class="list-group-item-custom {{ is_null($notification->read_at) ? 'bg-light' : '' }}" data-id="{{ $notification->id }}">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-1">
                                <i class="fas fa-{{ $notification->data['icon'] ?? 'bell' }} mr-2" style="color: var(--primary);"></i>
                                <strong>{{ $notification->data['title'] ?? 'Notification' }}</strong>
                                @if(is_null($notification->read_at))
                                <span class="badge-primary ml-2" style="background-color: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px;">New</span>
                                @endif
                            </div>
                            <p class="mb-1 text-sm">{{ $notification->data['message'] ?? '' }}</p>
                            <small>{{ $notification->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="flex gap-1">
                            @if(is_null($notification->read_at))
                            <button class="chart-period-btn mark-read-btn" data-id="{{ $notification->id }}" style="padding: 0.25rem 0.5rem;">
                                <i class="fas fa-check"></i>
                            </button>
                            @endif
                            <button class="chart-period-btn delete-notification-btn" data-id="{{ $notification->id }}" style="padding: 0.25rem 0.5rem;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-bell text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No notifications</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Widget Settings Modal -->
<div class="modal fade" id="widgetSettingsModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header border-b" style="border-color: var(--border-color); padding: 1rem;">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="fas fa-th-large mr-2" style="color: var(--primary);"></i>Customize Dashboard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1rem;">
                <p style="color: var(--text-secondary);">Select which widgets to display on your dashboard:</p>
                <div class="space-y-2 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="stats" id="widgetStats" checked>
                        <label class="form-check-label" for="widgetStats">Statistics Cards (Properties, Units, Revenue, Tenants)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="financial_summary" id="widgetFinancialSummary" checked>
                        <label class="form-check-label" for="widgetFinancialSummary">Financial Summary Card</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="maintenance_summary" id="widgetMaintenanceSummary" checked>
                        <label class="form-check-label" for="widgetMaintenanceSummary">Maintenance Summary Card</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="charts" id="widgetCharts" checked>
                        <label class="form-check-label" for="widgetCharts">Revenue & Occupancy Charts</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="recent_payments" id="widgetRecentPayments" checked>
                        <label class="form-check-label" for="widgetRecentPayments">Recent Payments List</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="upcoming_leases" id="widgetUpcomingLeases" checked>
                        <label class="form-check-label" for="widgetUpcomingLeases">Upcoming Lease Expirations</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="pending_actions" id="widgetPendingActions" checked>
                        <label class="form-check-label" for="widgetPendingActions">Pending Actions Card</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="quick_actions" id="widgetQuickActions" checked>
                        <label class="form-check-label" for="widgetQuickActions">Quick Actions Card</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="notifications" id="widgetNotifications" checked>
                        <label class="form-check-label" for="widgetNotifications">Notifications Section</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-t" style="border-color: var(--border-color); padding: 1rem;">
                <button type="button" class="chart-period-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="chart-period-btn" id="saveWidgetSettings" style="background-color: var(--primary); color: white;">Save Changes</button>
                <button type="button" class="chart-period-btn" id="resetWidgetSettings" style="background-color: var(--warning); color: white;">Reset to Default</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script>
// Store route URLs as JavaScript variables
const routes = {
    dashboardStats: '{{ route("landlord.dashboard.stats") }}',
    dashboardCharts: '{{ route("landlord.dashboard.charts") }}',
    dashboardExport: '{{ route("landlord.dashboard.export") }}',
    dashboardWidgetsUpdate: '{{ route("landlord.dashboard.widgets.update") }}',
    dashboardWidgetsReset: '{{ route("landlord.dashboard.widgets.reset") }}',
    dashboardRefresh: '{{ route("landlord.dashboard.refresh") }}',
    financialSummary: '{{ route("landlord.financial.summary") }}',
    maintenanceSummary: '{{ route("landlord.maintenance.summary") }}',
    paymentsHistory: '{{ route("landlord.payments.history") }}',
    propertiesIndex: '{{ route("landlord.properties.index") }}',
    leasesAnalytics: '{{ route("landlord.leases.analytics") }}',
    notificationsIndex: '{{ route("landlord.notifications.index") }}',
    notificationsReadAll: '{{ route("landlord.notifications.read-all") }}',
    notificationsClear: '{{ route("landlord.notifications.clear") }}',
    csrfToken: '{{ csrf_token() }}'
};

// Store initial data from server
const initialStats = @json($stats);
const initialPendingActions = @json($pendingActions);
const initialFinancialSummary = @json($financialSummary ?? []);
const initialMaintenanceSummary = @json($maintenanceSummary ?? []);
const initialChartData = @json($chartData ?? []);
const initialRecentPayments = @json($recentPayments ?? []);
const initialUpcomingLeases = @json($upcomingLeaseExpirations ?? []);
const initialNotifications = @json($notifications ?? []);

let revenueChart = null;
let occupancyChart = null;
let dashboardStats = initialStats;

// Widget visibility state
let widgetVisibility = {
    stats: true,
    financial_summary: true,
    maintenance_summary: true,
    charts: true,
    recent_payments: true,
    upcoming_leases: true,
    pending_actions: true,
    quick_actions: true,
    notifications: true
};

function showNotification(message, type = 'success') {
    const existingNotif = document.querySelectorAll('.dashboard-notification');
    existingNotif.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `dashboard-notification fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white text-sm transition-all duration-300`;
    notification.style.zIndex = '9999';
    
    if (type === 'success') {
        notification.style.backgroundColor = '#10b981';
        notification.innerHTML = `<div class="flex items-center gap-2"><i class="fas fa-check-circle"></i><span>${message}</span></div>`;
    } else if (type === 'error') {
        notification.style.backgroundColor = '#ef4444';
        notification.innerHTML = `<div class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i><span>${message}</span></div>`;
    } else {
        notification.style.backgroundColor = '#3b82f6';
        notification.innerHTML = `<div class="flex items-center gap-2"><i class="fas fa-info-circle"></i><span>${message}</span></div>`;
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ==================== DASHBOARD SWITCHER INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Landlord Dashboard Initializing...');
    
    function initDashboardSwitcher() {
        const switcherBtn = document.getElementById('dashboardSwitcherBtn');
        const switcherMenu = document.getElementById('dashboardSwitcherMenu');
        
        if (!switcherBtn || !switcherMenu) {
            console.log('Dashboard switcher not present (no multiple roles)');
            return;
        }
        
        let currentRole = '{{ $currentRole ?? "landlord" }}';
        console.log('🔧 Dashboard Switcher Init - Current Role:', currentRole);
        console.log('Has Multiple Roles:', {{ $hasMultipleRoles ? 'true' : 'false' }});
        
        // Toggle dropdown
        switcherBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            switcherMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!switcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                switcherMenu.classList.remove('show');
            }
        });
        
        // Handle switch options
        const switchOptions = document.querySelectorAll('.dashboard-switch-option');
        console.log('📋 Switch Options Found:', switchOptions.length);
        
        switchOptions.forEach(option => {
            option.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const roleSlug = this.dataset.role;
                console.log('🎯 Dashboard option clicked:', roleSlug);
                
                if (roleSlug === currentRole) {
                    showNotification(`Already on ${roleSlug.replace('-', ' ')} dashboard`, 'info');
                    switcherMenu.classList.remove('show');
                    return;
                }
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    showNotification('Security error. Please refresh the page.', 'error');
                    return;
                }
                
                // Show loading state
                const originalHTML = switcherBtn.innerHTML;
                switcherBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
                switcherBtn.disabled = true;
                switcherBtn.style.opacity = '0.7';
                switcherMenu.classList.remove('show');
                
                try {
                    console.log('📤 Sending switch request for role:', roleSlug);
                    
                    const response = await fetch('/dashboard/switch', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ role: roleSlug })
                    });
                    
                    const data = await response.json();
                    console.log('📥 Response data:', data);
                    
                    if (response.ok && data.success && data.redirect_url) {
                        console.log('✅ Switch successful! Redirecting to:', data.redirect_url);
                        showNotification(data.message || `Switching to ${roleSlug.replace('-', ' ')} dashboard...`, 'success');
                        
                        setTimeout(function() {
                            window.location.href = data.redirect_url;
                        }, 300);
                    } else {
                        throw new Error(data.message || `Server returned ${response.status}`);
                    }
                } catch (error) {
                    console.error('❌ Switch error:', error);
                    showNotification(error.message || 'Failed to switch dashboard', 'error');
                    switcherBtn.innerHTML = originalHTML;
                    switcherBtn.disabled = false;
                    switcherBtn.style.opacity = '1';
                }
            });
        });
    }
    
    setTimeout(initDashboardSwitcher, 100);
    
    // Load saved widget preferences from localStorage
    loadWidgetPreferences();
    
    // Initialize with server-side data
    updateUI(initialStats);
    updatePendingActions(initialPendingActions);
    updateFinancialSummary(initialFinancialSummary);
    updateMaintenanceSummary(initialMaintenanceSummary);
    
    // Display initial data immediately
    displayRecentPayments(initialRecentPayments);
    displayUpcomingLeases(initialUpcomingLeases);
    displayNotifications(initialNotifications);
    
    initializeCharts();
    loadDashboardData(false);
    
    // Event handlers
    $('#refreshDashboardBtn').on('click', function() { refreshDashboard(); });
    $('#exportDashboardBtn').on('click', function() { exportDashboard(); });
    $('#widgetSettingsBtn').on('click', function() { openWidgetSettings(); });
    $('#saveWidgetSettings').on('click', function() { saveWidgetSettings(); });
    $('#resetWidgetSettings').on('click', function() { resetWidgetSettings(); });
    $('#markAllReadBtn').on('click', function() { markAllNotificationsRead(); });
    $('#clearAllNotificationsBtn').on('click', function() { clearAllNotifications(); });
    $('#revenueYearSelect').on('change', function() { loadRevenueChart(); });
    
    $('.occupancy-period-btn').on('click', function() {
        $('.occupancy-period-btn').removeClass('active');
        $(this).addClass('active');
        loadOccupancyChart($(this).data('period'));
    });
    
    setupCollectPaymentDropdown();
    
    // Auto-refresh every 60 seconds
    setInterval(function() { if (!document.hidden) loadDashboardData(false); }, 60000);
});

function loadWidgetPreferences() {
    const saved = localStorage.getItem('dashboard_widgets');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            widgetVisibility = { ...widgetVisibility, ...parsed };
            applyWidgetVisibility();
        } catch(e) { console.error('Error loading widget preferences:', e); }
    }
}

function saveWidgetPreferences() {
    localStorage.setItem('dashboard_widgets', JSON.stringify(widgetVisibility));
}

function applyWidgetVisibility() {
    // Statistics Cards
    if (widgetVisibility.stats) {
        $('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4').first().show();
    } else {
        $('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4').first().hide();
    }
    
    // Financial Summary
    if (widgetVisibility.financial_summary) {
        $('.stats-card.p-6').first().closest('.grid').find('.stats-card.p-6').first().parent().show();
    } else {
        $('.stats-card.p-6').first().parent().hide();
    }
    
    // Maintenance Summary
    if (widgetVisibility.maintenance_summary) {
        $('.stats-card.p-6').eq(1).parent().show();
    } else {
        $('.stats-card.p-6').eq(1).parent().hide();
    }
    
    // Charts Section
    if (widgetVisibility.charts) {
        $('.chart-card').first().closest('.grid-cols-1.lg\\:grid-cols-2').show();
    } else {
        $('.chart-card').first().closest('.grid-cols-1.lg\\:grid-cols-2').hide();
    }
    
    // Recent Payments
    if (widgetVisibility.recent_payments) {
        $('#recentPaymentsList').closest('.recent-card').show();
    } else {
        $('#recentPaymentsList').closest('.recent-card').hide();
    }
    
    // Upcoming Leases
    if (widgetVisibility.upcoming_leases) {
        $('#upcomingLeasesList').closest('.recent-card').show();
    } else {
        $('#upcomingLeasesList').closest('.recent-card').hide();
    }
    
    // Pending Actions
    if (widgetVisibility.pending_actions) {
        $('.stats-card.p-6').eq(2).show();
    } else {
        $('.stats-card.p-6').eq(2).hide();
    }
    
    // Quick Actions
    if (widgetVisibility.quick_actions) {
        $('.stats-card.p-6').eq(3).show();
    } else {
        $('.stats-card.p-6').eq(3).hide();
    }
    
    // Notifications
    if (widgetVisibility.notifications) {
        $('.mt-8 > .recent-card').show();
    } else {
        $('.mt-8 > .recent-card').hide();
    }
}

function openWidgetSettings() {
    // Update modal checkboxes to match current visibility
    $('#widgetStats').prop('checked', widgetVisibility.stats);
    $('#widgetFinancialSummary').prop('checked', widgetVisibility.financial_summary);
    $('#widgetMaintenanceSummary').prop('checked', widgetVisibility.maintenance_summary);
    $('#widgetCharts').prop('checked', widgetVisibility.charts);
    $('#widgetRecentPayments').prop('checked', widgetVisibility.recent_payments);
    $('#widgetUpcomingLeases').prop('checked', widgetVisibility.upcoming_leases);
    $('#widgetPendingActions').prop('checked', widgetVisibility.pending_actions);
    $('#widgetQuickActions').prop('checked', widgetVisibility.quick_actions);
    $('#widgetNotifications').prop('checked', widgetVisibility.notifications);
    
    $('#widgetSettingsModal').modal('show');
}

function saveWidgetSettings() {
    widgetVisibility = {
        stats: $('#widgetStats').is(':checked'),
        financial_summary: $('#widgetFinancialSummary').is(':checked'),
        maintenance_summary: $('#widgetMaintenanceSummary').is(':checked'),
        charts: $('#widgetCharts').is(':checked'),
        recent_payments: $('#widgetRecentPayments').is(':checked'),
        upcoming_leases: $('#widgetUpcomingLeases').is(':checked'),
        pending_actions: $('#widgetPendingActions').is(':checked'),
        quick_actions: $('#widgetQuickActions').is(':checked'),
        notifications: $('#widgetNotifications').is(':checked')
    };
    
    saveWidgetPreferences();
    applyWidgetVisibility();
    
    // Also save to server
    const widgets = Object.keys(widgetVisibility).filter(key => widgetVisibility[key]);
    $.ajax({
        url: routes.dashboardWidgetsUpdate,
        type: 'POST',
        data: { widgets: widgets, _token: routes.csrfToken },
        success: function() {
            $('#widgetSettingsModal').modal('hide');
            showToast('Dashboard layout updated successfully!');
        },
        error: function() {
            showToast('Error saving widget settings', 'error');
        }
    });
}

function resetWidgetSettings() {
    widgetVisibility = {
        stats: true,
        financial_summary: true,
        maintenance_summary: true,
        charts: true,
        recent_payments: true,
        upcoming_leases: true,
        pending_actions: true,
        quick_actions: true,
        notifications: true
    };
    
    saveWidgetPreferences();
    applyWidgetVisibility();
    
    // Also reset on server
    $.ajax({
        url: routes.dashboardWidgetsReset,
        type: 'POST',
        data: { _token: routes.csrfToken },
        success: function() {
            $('#widgetSettingsModal').modal('hide');
            showToast('Dashboard layout reset to default!');
        },
        error: function() {
            showToast('Error resetting widget settings', 'error');
        }
    });
}

function initializeCharts() {
    const revenueCtx = document.getElementById('revenueChart');
    const occupancyCtx = document.getElementById('occupancyChart');
    if (!revenueCtx || !occupancyCtx) return;
    
    const revenueData = initialChartData.revenue || { labels: [], data: [] };
    const occupancyData = initialChartData.occupancy || { labels: [], data: [] };
    
    try {
        revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: { 
                labels: revenueData.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], 
                datasets: [{ 
                    label: 'Revenue (GH₵)', 
                    data: revenueData.data || Array(12).fill(0), 
                    borderColor: '#3b82f6', 
                    backgroundColor: 'rgba(59, 130, 246, 0.1)', 
                    borderWidth: 3, 
                    tension: 0.4, 
                    fill: true 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true, 
                plugins: { 
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'GH₵ ' + context.raw.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                }, 
                scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return 'GH₵ ' + value.toLocaleString(); } } } } 
            }
        });
    } catch(e) { console.error('Revenue chart error:', e); }
    
    try {
        occupancyChart = new Chart(occupancyCtx, {
            type: 'bar',
            data: { 
                labels: occupancyData.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], 
                datasets: [{ 
                    label: 'Occupancy Rate (%)', 
                    data: occupancyData.data || Array(12).fill(0), 
                    backgroundColor: 'rgba(16, 185, 129, 0.7)', 
                    borderColor: '#10b981', 
                    borderWidth: 2, 
                    borderRadius: 8,
                    hoverBackgroundColor: 'rgba(16, 185, 129, 0.9)'
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true, 
                plugins: { 
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw.toFixed(1) + '% occupied';
                            },
                            afterLabel: function(context) {
                                return 'Based on active rental agreements';
                            }
                        }
                    }
                }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        max: 100,
                        title: {
                            display: true,
                            text: 'Occupancy Rate (%)',
                            color: 'var(--text-secondary)'
                        },
                        ticks: { 
                            callback: function(value) { return value + '%'; } 
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Period',
                            color: 'var(--text-secondary)'
                        }
                    }
                } 
            }
        });
    } catch(e) { console.error('Occupancy chart error:', e); }
}

function setupCollectPaymentDropdown() {
    const btn = $('#collectPaymentBtn');
    const dropdown = $('#collectPaymentDropdown');
    let loaded = false;
    btn.on('click', function(e) { e.stopPropagation(); dropdown.toggleClass('hidden'); if (!loaded) { loadPropertiesForPayment(); loaded = true; } });
    $(document).on('click', function(e) { if (!btn.is(e.target) && !dropdown.is(e.target) && dropdown.has(e.target).length === 0) dropdown.addClass('hidden'); });
}

function loadPropertiesForPayment() {
    $.ajax({ url: routes.propertiesIndex, type: 'GET', data: { limit: 50 }, success: function(response) {
        let html = '';
        if (response.data && response.data.length) { 
            response.data.forEach(function(p) { 
                html += '<a href="/landlord/properties/' + p.id + '/pay" class="payment-property-item"><div class="flex justify-between items-center"><div><span class="font-medium">' + escapeHtml(p.property_name) + '</span><span class="text-xs block" style="color: var(--text-secondary);">' + (p.units_count || 0) + ' units</span></div><i class="fas fa-chevron-right text-xs"></i></div></a>'; 
            }); 
        } else { 
            html = '<div class="text-center py-4 text-sm">No properties found.</div>'; 
        }
        $('#propertyListForPayment').html(html);
    }, error: function() { 
        $('#propertyListForPayment').html('<div class="text-center py-4 text-sm text-danger">Error loading properties.</div>');
    } });
}

function loadDashboardData(showLoader) {
    if (showLoader !== false) showLoading();
    $.ajax({ url: routes.dashboardStats, type: 'GET', success: function(response) {
        dashboardStats = response; 
        updateUI(response); 
        updatePendingActionsFromStats(response);
        loadFinancialSummary(); 
        loadMaintenanceSummary(); 
        loadRecentPayments();
        loadUpcomingLeases();
        // loadNotifications() removed
        hideErrorMessage(); 
        if (showLoader !== false) hideLoading(); 
    }, error: function(xhr) { 
        if (showLoader !== false) hideLoading(); 
        showErrorMessage('Error loading dashboard data'); 
    } });
}

function loadFinancialSummary() {
    $.ajax({ url: routes.financialSummary, type: 'GET', success: function(response) { updateFinancialSummary(response); }, 
             error: function() { if (initialFinancialSummary) updateFinancialSummary(initialFinancialSummary); } });
}

function loadMaintenanceSummary() {
    $.ajax({ url: routes.maintenanceSummary, type: 'GET', success: function(response) { updateMaintenanceSummary(response); }, 
             error: function() { if (initialMaintenanceSummary) updateMaintenanceSummary(initialMaintenanceSummary); } });
}

function updateFinancialSummary(data) { 
    if (!data) return;
    $('#totalRevenue').text('GH₵' + formatNumber(data.total_revenue || 0));
    $('#totalOutstanding').text('GH₵' + formatNumber(data.total_outstanding || 0));
    $('#collectionRate').text((data.collection_rate || 0) + '%');
    $('#collectionProgressBar').css('width', (data.collection_rate || 0) + '%');
    $('#totalInvoices').text(data.total_invoices || 0);
    $('#paidInvoices').text(data.paid_invoices || 0);
    $('#unpaidInvoices').text((data.total_invoices - data.paid_invoices) || 0);
}

function updateMaintenanceSummary(data) { 
    if (!data) return;
    $('#totalRequests').text(data.total_requests || 0);
    $('#completionRate').text((data.completion_rate || 0) + '%');
    $('#completionProgressBar').css('width', (data.completion_rate || 0) + '%');
    $('#pendingRequests').text(data.pending_requests || 0);
    $('#urgentRequests').text(data.urgent_requests || 0);
    $('#avgResolutionTime').text((data.avg_resolution_days || 0) + ' days');
    $('#totalMaintenanceCost').text('GH₵' + formatNumber(data.total_cost || 0));
}

function updateUI(stats) {
    $('#totalProperties').text(stats.total_properties || 0);
    $('#totalUnits').text(stats.total_units || 0);
    $('#occupancyRate').text((stats.occupancy_rate || 0) + '%');
    $('#monthlyRevenue').text('GH₵' + formatNumber(stats.monthly_revenue || 0));
    $('#totalTenants').text(stats.total_tenants || 0);
    if (stats.pending_lease_signatures !== undefined) $('#pendingLeaseSignatures').text(stats.pending_lease_signatures);
}

function updatePendingActions(stats) {
    $('#pendingTenantApprovals').text(stats.tenant_approvals || 0);
    $('#unreadMessages').text(stats.unread_messages || 0);
}

function updatePendingActionsFromStats(stats) {
    $('#pendingTenantApprovals').text(stats.pending_tenant_approvals || stats.tenant_approvals || 0);
    $('#unreadMessages').text(stats.unread_messages || 0);
    $('#pendingInvoices').text(stats.pending_payments || 0);
    if (stats.pending_lease_signatures !== undefined) $('#pendingLeaseSignatures').text(stats.pending_lease_signatures);
}

function showErrorMessage(message) { $('#errorText').text(message); $('#errorMessage').removeClass('hidden'); setTimeout(function() { $('#errorMessage').addClass('hidden'); }, 5000); }
function hideErrorMessage() { $('#errorMessage').addClass('hidden'); }

function displayRecentPayments(payments) {
    let html = '';
    if (payments && payments.length > 0) {
        for (let i = 0; i < payments.length; i++) {
            const p = payments[i];
            const tenantName = (p.invoices && p.invoices.tenant && p.invoices.tenant.name) || (p.tenant && p.tenant.name) || 'Unknown';
            const amount = p.amount || 0;
            const status = p.status || 'completed';
            const date = p.created_at || new Date().toISOString();
            html += '<div class="list-group-item-custom">' +
                '<div class="flex justify-between items-center">' +
                '<div><p class="font-medium">' + escapeHtml(tenantName) + '</p>' +
                '<p class="text-xs mt-1">' + moment(date).format('MMM D, YYYY') + '</p></div>' +
                '<div class="text-right"><p class="font-bold">GH₵' + formatNumber(amount) + '</p>' +
                '<span class="badge payment-status-' + status + '">' + status + '</span></div>' +
                '</div></div>';
        }
    } else {
        html = '<div class="px-6 py-8 text-center"><i class="fas fa-credit-card text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i><p style="color: var(--text-secondary);">No recent payments</p></div>';
    }
    $('#recentPaymentsList').html(html);
}

function loadRecentPayments() {
    $.ajax({ url: routes.paymentsHistory, type: 'GET', data: { limit: 5 }, success: function(response) {
        let payments = [];
        if (response.data && Array.isArray(response.data)) payments = response.data;
        else if (response.payments && Array.isArray(response.payments)) payments = response.payments;
        else if (Array.isArray(response)) payments = response;
        displayRecentPayments(payments);
    }, error: function() {
        if (initialRecentPayments && initialRecentPayments.length > 0) displayRecentPayments(initialRecentPayments);
        else $('#recentPaymentsList').html('<div class="px-6 py-8 text-center"><p style="color: var(--text-secondary);">Unable to load payments</p></div>');
    } });
}

function displayUpcomingLeases(leases) {
    let html = '';
    if (leases && leases.length > 0) {
        for (let i = 0; i < leases.length; i++) {
            const l = leases[i];
            const propertyName = (l.unit && l.unit.property && l.unit.property.property_name) || l.property_name || 'Unknown';
            const unitNumber = (l.unit && l.unit.unit_number) || l.unit_number || 'N/A';
            const tenantName = (l.tenant && l.tenant.name) || l.tenant_name || 'Unknown';
            const endDate = l.end_date;
            const daysLeft = moment(endDate).diff(moment(), 'days');
            const badgeClass = daysLeft <= 7 ? 'badge-danger' : (daysLeft <= 30 ? 'badge-warning' : 'badge-info');
            html += '<div class="list-group-item-custom">' +
                '<div class="flex justify-between items-center">' +
                '<div><p class="font-medium">' + escapeHtml(propertyName) + ' - Unit ' + escapeHtml(unitNumber) + '</p>' +
                '<p class="text-xs mt-1">' + escapeHtml(tenantName) + '</p></div>' +
                '<div class="text-right"><span class="badge ' + badgeClass + '">' + daysLeft + ' days left</span>' +
                '<p class="text-xs mt-1">Ends: ' + moment(endDate).format('MMM D, YYYY') + '</p></div>' +
                '</div></div>';
        }
    } else {
        html = '<div class="px-6 py-8 text-center"><i class="fas fa-calendar-check text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i><p style="color: var(--text-secondary);">No upcoming lease expirations</p></div>';
    }
    $('#upcomingLeasesList').html(html);
}

function loadUpcomingLeases() {
    $.ajax({ url: routes.leasesAnalytics, type: 'GET', data: { upcoming: true, limit: 5 }, success: function(response) {
        let leases = response.leases || response.data || (Array.isArray(response) ? response : []);
        displayUpcomingLeases(leases);
    }, error: function() {
        if (initialUpcomingLeases && initialUpcomingLeases.length > 0) displayUpcomingLeases(initialUpcomingLeases);
        else $('#upcomingLeasesList').html('<div class="px-6 py-8 text-center"><p style="color: var(--text-secondary);">Unable to load leases</p></div>');
    } });
}

function displayNotifications(notifications) {
    let html = '';
    if (notifications && notifications.length > 0) {
        for (let i = 0; i < notifications.length; i++) {
            const n = notifications[i];
            const isRead = n.read_at !== null;
            html += '<div class="list-group-item-custom ' + (!isRead ? 'bg-light' : '') + '" data-id="' + n.id + '">' +
                '<div class="flex justify-between items-start">' +
                '<div class="flex-1">' +
                '<div class="flex items-center mb-1">' +
                '<i class="fas fa-bell mr-2" style="color: var(--primary);"></i>' +
                '<strong>' + escapeHtml((n.data && n.data.title) || n.title || 'Notification') + '</strong>' +
                (!isRead ? '<span class="badge-primary ml-2" style="background-color: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px;">New</span>' : '') +
                '</div>' +
                '<p class="mb-1 text-sm">' + escapeHtml((n.data && n.data.message) || n.message || '') + '</p>' +
                '<small>' + moment(n.created_at).fromNow() + '</small>' +
                '</div>' +
                '<div class="flex gap-1">' +
                (!isRead ? '<button class="chart-period-btn mark-read-btn" data-id="' + n.id + '" style="padding: 0.25rem 0.5rem;"><i class="fas fa-check"></i></button>' : '') +
                '<button class="chart-period-btn delete-notification-btn" data-id="' + n.id + '" style="padding: 0.25rem 0.5rem;"><i class="fas fa-trash"></i></button>' +
                '</div></div></div>';
        }
    } else {
        html = '<div class="px-6 py-8 text-center"><i class="fas fa-bell text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i><p style="color: var(--text-secondary);">No notifications</p></div>';
    }
    $('#notificationsList').html(html);
    
    $('.mark-read-btn').off('click').on('click', function() { markNotificationRead($(this).data('id')); });
    $('.delete-notification-btn').off('click').on('click', function() { deleteNotification($(this).data('id')); });
}

// [removed] legacy loadNotifications � superseded by notification-bell component, error: function() {
        if (initialNotifications && initialNotifications.length > 0) displayNotifications(initialNotifications);
        else $('#notificationsList').html('<div class="px-6 py-8 text-center"><p style="color: var(--text-secondary);">Unable to load notifications</p></div>');
    } });
}

function markNotificationRead(id) {
    $.ajax({ url: '{{ url("/landlord/notifications") }}/' + id + '/read', type: 'POST', data: { _token: routes.csrfToken }, 
             success: function() { // loadNotifications() removed - Alpine bell handles this loadDashboardData(false); } });
}

function deleteNotification(id) {
    $.ajax({ url: '{{ url("/landlord/notifications") }}/' + id, type: 'DELETE', data: { _token: routes.csrfToken }, 
             success: function() { // loadNotifications() removed - Alpine bell handles this } });
}

function markAllNotificationsRead() {
    $.ajax({ url: routes.notificationsReadAll, type: 'POST', data: { _token: routes.csrfToken }, 
             success: function() { // loadNotifications() removed - Alpine bell handles this loadDashboardData(false); showToast('All notifications marked as read'); } });
}

function clearAllNotifications() {
    if (confirm('Delete all notifications? This cannot be undone.')) {
        $.ajax({ url: routes.notificationsClear, type: 'DELETE', data: { _token: routes.csrfToken }, 
                 success: function() { // loadNotifications() removed - Alpine bell handles this showToast('All notifications cleared'); } });
    }
}

function loadRevenueChart() {
    const year = $('#revenueYearSelect').val();
    $.ajax({ url: routes.dashboardCharts, type: 'GET', data: { type: 'revenue', year: year }, success: function(response) {
        const labels = response.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const data = response.data || Array(12).fill(0);
        if (revenueChart) { revenueChart.data.labels = labels; revenueChart.data.datasets[0].data = data; revenueChart.update(); }
    } });
}

function loadOccupancyChart(period) {
    period = period || 'monthly';
    $.ajax({ 
        url: routes.dashboardCharts, 
        type: 'GET', 
        data: { type: 'occupancy', period: period }, 
        success: function(response) {
            let labels, data;
            if (period === 'quarterly') { 
                labels = response.labels || ['Q1', 'Q2', 'Q3', 'Q4']; 
                data = response.data || [0, 0, 0, 0]; 
            } else { 
                labels = response.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; 
                data = response.data || Array(12).fill(0); 
            }
            
            console.log('Occupancy data loaded:', { period: period, labels: labels, data: data });
            
            const totalOccupancy = data.reduce((a, b) => a + b, 0);
            if (totalOccupancy === 0 && occupancyChart) {
                console.warn('No occupancy data available. This could mean no rental agreements exist yet.');
            }
            
            if (occupancyChart) { 
                occupancyChart.data.labels = labels; 
                occupancyChart.data.datasets[0].data = data; 
                occupancyChart.update(); 
            }
        },
        error: function(xhr) {
            console.error('Error loading occupancy chart:', xhr);
            showToast('Error loading occupancy data. Please refresh the page.', 'error');
        }
    });
}

function refreshDashboard() {
    showLoading();
    $.ajax({ url: routes.dashboardRefresh, type: 'POST', data: { _token: routes.csrfToken }, success: function(response) {
        if (response.success && response.data) {
            updateUI(response.data.stats);
            updateFinancialSummary(response.data.financial_summary);
            updateMaintenanceSummary(response.data.maintenance_summary);
            updatePendingActionsFromStats(response.data.stats);
            loadRecentPayments();
            loadUpcomingLeases();
            loadRevenueChart();
            loadOccupancyChart($('.occupancy-period-btn.active').data('period') || 'monthly');
            showToast('Dashboard refreshed!');
        }
        hideLoading();
    }, error: function() { loadDashboardData(true); showToast('Dashboard refreshed!'); hideLoading(); } });
}

function exportDashboard() { window.location.href = routes.dashboardExport + '?format=csv'; showToast('Exporting...'); }

function formatNumber(num) { if (!num && num !== 0) return '0'; return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
function showLoading() { $('#refreshDashboardBtn').html('<span class="loading-spinner"></span> Loading...').prop('disabled', true); }
function hideLoading() { $('#refreshDashboardBtn').html('<i class="fas fa-sync-alt mr-1"></i> Refresh').prop('disabled', false); }
function showToast(message, type) { type = type || 'success'; const toast = $('<div class="toast-notification toast-' + type + '">' + message + '</div>'); $('body').append(toast); setTimeout(function() { toast.fadeOut(300, function() { toast.remove(); }); }, 3000); }
</script>
@endpush