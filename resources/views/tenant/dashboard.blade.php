@extends('layouts.tenant')

@php
    // Helper function for day suffix - FIXED to avoid controller dependency
    if (!function_exists('getDaySuffix')) {
        function getDaySuffix($day) {
            if ($day >= 11 && $day <= 13) return 'th';
            switch ($day % 10) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';
                default: return 'th';
            }
        }
    }
    
    // ==================== DASHBOARD AWARE LOGIC ====================
    $user = Auth::user();
    
    // Check if user has multiple roles via the dual-mode system
    $roleBasedRoles = $user->roles ?? collect();
    $legacyType = $user->type;
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
    
    // Combine roles for multi-role detection
    $allRoleSlugs = $roleBasedRoles->pluck('slug')->toArray();
    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $allRoleSlugs)) {
        $allRoleSlugs[] = $legacyRoleSlug;
    }
    $hasMultipleRoles = count($allRoleSlugs) > 1;
    
    // Get current role from session (set by dashboard switcher)
    $currentRole = session('selected_role', $legacyRoleSlug ?? 'tenant');
    
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
    
    $roleDescriptions = [
        'super-admin' => 'Full system control',
        'admin' => 'System management',
        'landlord' => 'Property portfolio management',
        'tenant' => 'Rental management',
        'field-agent' => 'Property registration',
        'security-personnel' => 'Security operations',
        'developer' => 'System development',
    ];
    
    // Build combined roles list for switcher
    $switcherRoles = [];
    $addedSlugs = [];
    
    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $addedSlugs)) {
        $switcherRoles[] = (object)[
            'slug' => $legacyRoleSlug,
            'display_name' => ucfirst(str_replace('-', ' ', $legacyRoleSlug)),
            'source' => 'legacy',
            'description' => $roleDescriptions[$legacyRoleSlug] ?? 'Dashboard access'
        ];
        $addedSlugs[] = $legacyRoleSlug;
    }
    
    foreach ($roleBasedRoles as $role) {
        if (!in_array($role->slug, $addedSlugs)) {
            $switcherRoles[] = (object)[
                'slug' => $role->slug,
                'display_name' => $role->display_name ?? ucfirst(str_replace('-', ' ', $role->slug)),
                'source' => 'role',
                'description' => $roleDescriptions[$role->slug] ?? 'Dashboard access'
            ];
            $addedSlugs[] = $role->slug;
        }
    }
    
    // Sort roles
    usort($switcherRoles, function($a, $b) {
        $order = ['super-admin' => 0, 'admin' => 1, 'landlord' => 2, 'tenant' => 3];
        $orderA = $order[$a->slug] ?? 99;
        $orderB = $order[$b->slug] ?? 99;
        if ($orderA === $orderB) return strcmp($a->slug, $b->slug);
        return $orderA - $orderB;
    });
@endphp

@section('title', 'Tenant Dashboard')

@push('styles')
<style>
    /* CSS Variables - Matching the admin/landlord dashboard theme */
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
        --purple: #8b5cf6;
        --purple-rgb: 139, 92, 246;
        --border-color-rgb: 229, 231, 235;
    }

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

    .stat-icon-purple {
        background: linear-gradient(135deg, var(--purple) 0%, #7c3aed 100%);
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

    .badge-pending, .badge-warning, .badge-pending_signature {
        background-color: rgba(var(--warning-rgb), 0.2);
        color: var(--warning);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-inactive, .badge-cancelled, .badge-failed, .badge-danger, .badge-terminated, .badge-overdue {
        background-color: rgba(var(--danger-rgb), 0.2);
        color: var(--danger);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-info, .badge-processing, .badge-draft {
        background-color: rgba(var(--info-rgb), 0.2);
        color: var(--info);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-purple {
        background-color: rgba(var(--purple-rgb), 0.2);
        color: var(--purple);
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

    /* Property Card */
    .property-card {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1.25rem;
        transition: all 0.2s ease;
    }

    .property-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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

    /* Lease Status Card */
    .lease-status-card {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1.25rem;
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

    .toast-warning {
        background-color: var(--warning);
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

    /* Signature Modal */
    .signature-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .signature-modal-overlay.hidden {
        display: none;
    }

    .signature-modal {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .signature-canvas {
        border: 2px solid var(--border-color);
        border-radius: 0.5rem;
        cursor: crosshair;
        background-color: white;
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
    
    /* Payment Method Icons */
    .payment-method-icon {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background-color: var(--bg-secondary);
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    <!-- Page Header with Dashboard Switcher -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-home mr-2" style="color: var(--primary);"></i>
                Tenant Dashboard
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Welcome back, {{ Auth::user()->name }}! Here's an overview of your rental.
            </p>
        </div>
        
        <div class="flex items-center gap-3 flex-wrap">
            <!-- Dashboard Switcher (Multi-Role Support) -->
            @if($hasMultipleRoles)
            <div class="dashboard-switcher relative">
                <button id="dashboardSwitcherBtn" 
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all text-sm"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <i class="fas fa-{{ $roleIcons[$currentRole] ?? 'user' }} mr-1"></i>
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
                            <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.7;">
                                You have {{ count($switcherRoles) }} dashboard{{ count($switcherRoles) > 1 ? 's' : '' }} available
                            </p>
                        </div>
                        
                        @foreach($switcherRoles as $role)
                            @php
                                $isActive = ($currentRole === $role->slug);
                                $icon = $roleIcons[$role->slug] ?? 'user';
                            @endphp
                            <button type="button"
                                    class="dashboard-switch-option w-full text-left flex items-center gap-3 px-4 py-3 transition-all hover:bg-opacity-10 {{ $isActive ? 'active-option' : '' }}"
                                    data-role="{{ $role->slug }}"
                                    data-current-role="{{ $currentRole }}"
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
                                                <i class="fas fa-database text-xs"></i> Legacy Type • {{ $role->description }}
                                            </span>
                                        @else
                                            {{ $role->description }}
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
            
            <!-- My Testimonials Link - Submit Feedback about the App -->
            <a href="{{ route('dashboard.testimonials.index') }}" 
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
               style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); text-decoration: none;">
                <i class="fas fa-star"></i>
                <span>My Testimonials</span>
            </a>
            
            <span class="text-sm" style="color: var(--text-secondary);">
                <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
            </span>
            
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

    <!-- Multi-Role Notice (Auto-hide) -->
    @if($hasMultipleRoles)
    <div id="multiRoleBanner" class="mt-4 p-3 rounded-lg flex items-center justify-between flex-wrap gap-3 transition-all duration-500 mb-6" 
         style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--secondary-rgb), 0.05) 100%); border: 1px solid rgba(var(--primary-rgb), 0.2);">
        <div class="flex items-center gap-3">
            <i class="fas fa-users text-lg" style="color: var(--primary);"></i>
            <div>
                <p class="text-sm font-medium" style="color: var(--text-primary);">
                    You have multiple roles in the system
                </p>
                <p class="text-xs" style="color: var(--text-secondary);">
                    Currently viewing as <strong class="font-semibold" style="color: var(--primary);">{{ ucfirst(str_replace('-', ' ', $currentRole)) }}</strong>. 
                    Use the switcher above to access other dashboards.
                </p>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @foreach($switcherRoles as $role)
                @if($role->slug !== $currentRole)
                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas {{ $role->slug === 'landlord' ? 'fa-home' : ($role->slug === 'tenant' ? 'fa-user' : ($role->slug === 'field-agent' ? 'fa-clipboard-list' : ($role->slug === 'security-personnel' ? 'fa-shield-alt' : 'fa-user'))) }} mr-1"></i>
                        {{ ucfirst(str_replace('-', ' ', $role->slug)) }}
                        @if(($role->source ?? '') === 'legacy')
                            <span class="text-xs ml-1 opacity-70">(legacy)</span>
                        @endif
                    </span>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    <!-- Unit Status Alert - If no unit assigned -->
    @if(!$unit)
    <div class="alert-card mb-6" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid var(--warning); border-radius: 0.75rem; padding: 1rem;">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-xl mr-3" style="color: var(--warning);"></i>
            <div>
                <h4 class="font-semibold" style="color: var(--warning);">No Property Unit Assigned</h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    You don't have a property unit assigned yet. Please contact your landlord or property manager to get started.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card" onclick="window.location.href='{{ route('tenant.property-units.my-unit') }}'">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">My Unit</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="unitNumber">
                        {{ $unit->unit_number ?? 'Not Assigned' }}
                    </p>
                    @if($unit)
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $property->property_name ?? 'N/A' }}</p>
                    @endif
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-door-open text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='{{ route('tenant.invoices.index') }}'">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Monthly Rent</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="monthlyRent">
                        GH₵{{ number_format($stats['monthly_rent'] ?? 0, 2) }}
                    </p>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-currency-dollar text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Community Development Dues Card -->
        <div class="stat-card" onclick="window.location.href='{{ route('tenant.invoices.index') }}'">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Community Development Dues</p>
                    <p class="text-2xl font-bold stat-value" style="color: {{ $stats['outstanding_balance'] > 0 ? 'var(--danger)' : 'var(--success)' }};" id="outstandingBalance">
                        GH₵{{ number_format($stats['outstanding_balance'] ?? 0, 2) }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Pending community contributions</p>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-hand-holding-heart text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='{{ route('tenant.property-units.maintenance-requests', $unit->id ?? 0) }}'">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Maintenance Requests</p>
                    <p class="text-2xl font-bold stat-value" style="color: var(--text-primary);" id="pendingMaintenance">
                        {{ $stats['pending_maintenance'] ?? 0 }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Pending requests</p>
                </div>
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-tools text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Lease Status Card -->
    @if($activeLease || $pendingLeaseSignature)
    <div class="lease-status-card mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-file-signature mr-2" style="color: var(--primary);"></i>Lease Agreement Status
                </h3>
                <div class="flex flex-wrap gap-4 mt-3">
                    @if($activeLease)
                    <div>
                        <span class="badge-active">Active Lease</span>
                        <p class="text-sm mt-2">
                            <strong>Period:</strong> 
                            {{ $activeLease->start_date ? $activeLease->start_date->format('M d, Y') : 'N/A' }} - 
                            {{ $activeLease->end_date ? $activeLease->end_date->format('M d, Y') : 'Month-to-Month' }}
                        </p>
                        @if($upcomingLease && $upcomingLease['is_expiring_soon'])
                        <p class="text-sm mt-1" style="color: var(--warning);">
                            <i class="fas fa-clock mr-1"></i> Expires in {{ $upcomingLease['days_remaining'] }} days
                        </p>
                        @endif
                    </div>
                    @endif
                    
                    @if($pendingLeaseSignature)
                    <div>
                        <span class="badge-pending">Awaiting Your Signature</span>
                        <p class="text-sm mt-2">
                            <strong>Monthly Rent:</strong> GH₵{{ number_format($pendingLeaseSignature['monthly_rent'], 2) }}
                        </p>
                        <p class="text-sm">
                            <strong>Landlord Signed:</strong> 
                            {{ $pendingLeaseSignature['landlord_signed'] ? 'Yes' : 'No' }}
                        </p>
                        @if($pendingLeaseSignature['days_until_expiry'] > 0)
                        <p class="text-sm mt-1" style="color: var(--warning);">
                            <i class="fas fa-hourglass-half mr-1"></i> 
                            Expires in {{ $pendingLeaseSignature['days_until_expiry'] }} days
                        </p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @if($pendingLeaseSignature && $pendingLeaseSignature['landlord_signed'])
            <button class="chart-period-btn" id="signLeaseBtn" style="background-color: var(--primary); color: white; padding: 0.5rem 1rem;">
                <i class="fas fa-pen-fancy mr-1"></i> Sign Lease Now
            </button>
            @endif
        </div>
    </div>
    @endif

    <!-- Property Details Section -->
    @if($unit && $property)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Property Information Card -->
        <div class="stats-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>Property Information
                </h3>
                <a href="{{ route('tenant.property-units.show', $unit->id) }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View Details <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Property Name:</span>
                    <span class="text-sm font-medium">{{ $property->property_name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Unit Number:</span>
                    <span class="text-sm font-medium">{{ $unit->unit_number ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Address:</span>
                    <span class="text-sm font-medium">{{ $property->address ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Property Type:</span>
                    <span class="text-sm font-medium">{{ $property->propertyType->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Landlord:</span>
                    <span class="text-sm font-medium">{{ $property->landlord->name ?? 'N/A' }}</span>
                </div>
                @if($property->landlord && $property->landlord->phone)
                <div class="flex justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Landlord Phone:</span>
                    <span class="text-sm font-medium">{{ $property->landlord->phone }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Financial Summary Card -->
        <div class="stats-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Financial Summary
                </h3>
                <a href="{{ route('tenant.invoices.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View All Invoices <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="financial-card">
                    <div class="financial-label">Monthly Rent</div>
                    <div class="financial-amount" id="financialMonthlyRent">GH₵{{ number_format($activeLease->monthly_rent ?? $unit->current_rent_amount ?? 0, 2) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Due on day {{ $activeLease->payment_due_day ?? 'N/A' }} of each month</div>
                </div>
                <div class="financial-card">
                    <div class="financial-label">Security Deposit</div>
                    <div class="financial-amount" id="securityDeposit">GH₵{{ number_format($stats['security_deposit'] ?? 0, 2) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Paid: GH₵{{ number_format($stats['deposit_paid'] ?? 0, 2) }}
                        @if(($stats['deposit_remaining'] ?? 0) > 0)
                        <span class="ml-1" style="color: var(--warning);">({{ number_format($stats['deposit_remaining'], 2) }} remaining)</span>
                        @endif
                    </div>
                    @if(($stats['deposit_remaining'] ?? 0) > 0)
                    <div class="progress-bar-container mt-2">
                        @php $depositPercent = ($stats['deposit_paid'] / max($stats['security_deposit'], 1)) * 100; @endphp
                        <div class="progress-bar-fill" style="width: {{ $depositPercent }}%"></div>
                    </div>
                    @endif
                </div>
                <div class="financial-card">
                    <div class="financial-label">Total Paid</div>
                    <div class="financial-amount" id="totalPaid">GH₵{{ number_format($stats['total_paid'] ?? 0, 2) }}</div>
                </div>
                <div class="financial-card">
                    <div class="financial-label">Community Dues Status</div>
                    <div class="financial-amount" id="paymentStatusBadge">
                        @if(($stats['outstanding_balance'] ?? 0) <= 0)
                        <span class="badge-success">All Paid</span>
                        @else
                        <span class="badge-warning">Balance Due</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Payment & Maintenance Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Invoices / Community Dues History -->
        <div class="recent-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i>Recent Community Dues Invoices
                    </h3>
                    <a href="{{ route('tenant.invoices.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div id="recentInvoicesList">
                @forelse($recentInvoices ?? [] as $invoice)
                <div class="list-group-item-custom">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium">Invoice #{{ $invoice['invoice_number'] }}</p>
                            <p class="text-xs mt-1">Period: {{ $invoice['period'] ?? $invoice['created_at'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold">GH₵{{ number_format($invoice['amount'], 2) }}</p>
                            <span class="badge {{ $invoice['status'] === 'paid' ? 'badge-success' : ($invoice['status'] === 'overdue' ? 'badge-overdue' : 'badge-warning') }}">
                                {{ ucfirst($invoice['status']) }}
                            </span>
                            @if($invoice['due_date'])
                            <p class="text-xs mt-1">Due: {{ $invoice['due_date'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-file-invoice text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No community dues invoices found</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Maintenance Requests -->
        <div class="recent-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>Recent Maintenance Requests
                    </h3>
                    <a href="{{ route('tenant.property-units.maintenance-requests', $unit->id ?? 0) }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div id="recentMaintenanceList">
                @forelse($recentMaintenance ?? [] as $request)
                <div class="list-group-item-custom">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <p class="font-medium">{{ $request['title'] }}</p>
                            <p class="text-xs mt-1">{{ Str::limit($request['description'], 80) }}</p>
                            <p class="text-xs mt-1">{{ $request['created_at'] }}</p>
                        </div>
                        <div>
                            <span class="badge {{ $request['status'] === 'completed' ? 'badge-success' : ($request['status'] === 'in_progress' ? 'badge-info' : 'badge-warning') }}">
                                {{ ucfirst($request['status']) }}
                            </span>
                            @if($request['priority'] === 'urgent')
                            <span class="badge-danger ml-1">Urgent</span>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-check-circle text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No maintenance requests</p>
                    <a href="{{ route('tenant.property-units.create-maintenance-request', $unit->id ?? 0) }}" class="chart-period-btn mt-2" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-plus-circle mr-1"></i> Submit Request
                    </a>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    @if($unit)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Community Dues Payment History
                </h3>
                <select class="chart-period-btn" id="paymentHistoryYearSelect">
                    <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                    <option value="{{ date('Y') - 1 }}">{{ date('Y') - 1 }}</option>
                    <option value="{{ date('Y') - 2 }}">{{ date('Y') - 2 }}</option>
                </select>
            </div>
            <canvas id="paymentHistoryChart" height="250" style="max-height: 250px;"></canvas>
        </div>

        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Maintenance Trends
                </h3>
                <div class="flex gap-2">
                    <button class="chart-period-btn maintenance-period-btn active" data-period="monthly">Monthly</button>
                    <button class="chart-period-btn maintenance-period-btn" data-period="quarterly">Quarterly</button>
                </div>
            </div>
            <canvas id="maintenanceChart" height="250" style="max-height: 250px;"></canvas>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="stats-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
            </h3>
            <div class="grid grid-cols-2 gap-3">
                @if($unit)
                <a href="{{ route('tenant.property-units.create-maintenance-request', $unit->id) }}" class="chart-period-btn text-center py-2" style="background-color: var(--info); color: white; text-decoration: none;">
                    <i class="fas fa-tools mr-1"></i> Report Maintenance
                </a>
                <a href="{{ route('tenant.invoices.index') }}" class="chart-period-btn text-center py-2" style="background-color: var(--success); color: white; text-decoration: none;">
                    <i class="fas fa-hand-holding-heart mr-1"></i> Pay Community Dues
                </a>
                <a href="{{ route('tenant.payments.create') }}" class="chart-period-btn text-center py-2" style="background-color: var(--primary); color: white; text-decoration: none;">
                    <i class="fas fa-credit-card mr-1"></i> Make Payment
                </a>
                <a href="{{ route('tenant.contact-landlord.form', $unit->id) }}" class="chart-period-btn text-center py-2" style="background-color: var(--warning); color: white; text-decoration: none;">
                    <i class="fas fa-envelope mr-1"></i> Contact Landlord
                </a>
                @endif
                <a href="{{ route('tenant.profile.edit') }}" class="chart-period-btn text-center py-2" style="background-color: var(--purple); color: white; text-decoration: none;">
                    <i class="fas fa-user-edit mr-1"></i> Update Profile
                </a>
                <!-- Share Feedback Button -->
                <a href="{{ route('dashboard.testimonials.index') }}" 
                   class="chart-period-btn text-center py-2" 
                   style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); text-decoration: none;">
                    <i class="fas fa-star mr-1"></i> Share Feedback
                </a>
                @if($pendingLeaseSignature && $pendingLeaseSignature['landlord_signed'])
                <button id="signLeaseQuickBtn" class="chart-period-btn text-center py-2" style="background-color: var(--danger); color: white;">
                    <i class="fas fa-pen-fancy mr-1"></i> Sign Lease
                </button>
                @endif
            </div>
        </div>

        <div class="stats-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>Important Information
            </h3>
            <div class="space-y-3">
                <div class="flex items-start">
                    <i class="fas fa-calendar-alt mt-1 mr-3" style="color: var(--primary);"></i>
                    <div>
                        <p class="text-sm font-medium">Payment Due Date</p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            Rent is due on the 
                            @if($activeLease && $activeLease->payment_due_day)
                                {{ $activeLease->payment_due_day }}{{ getDaySuffix($activeLease->payment_due_day) }}
                            @else
                                1st
                            @endif 
                            of each month
                        </p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-hand-holding-heart mt-1 mr-3" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-sm font-medium">Community Dues Policy</p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            @if($activeLease && $activeLease->late_fee_percentage > 0)
                                Late fee of {{ $activeLease->late_fee_percentage }}% applies after {{ $activeLease->grace_period_days }} days grace period
                            @else
                                Contact landlord for community dues policy
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-file-signature mt-1 mr-3" style="color: var(--success);"></i>
                    <div>
                        <p class="text-sm font-medium">Lease Documents</p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            @if($activeLease)
                                <a href="{{ route('tenant.property-units.lease-details', [$unit->id, $activeLease->id]) }}" style="color: var(--primary);">View your lease agreement</a>
                            @else
                                No active lease found
                            @endif
                        </p>
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

<!-- Signature Modal (for lease signing) -->
<div id="signatureModal" class="signature-modal-overlay hidden">
    <div class="signature-modal">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-pen-fancy mr-2" style="color: var(--primary);"></i>Sign Lease Agreement
                </h3>
                <button class="close-modal-btn" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            <div class="mb-4">
                <p class="text-sm" style="color: var(--text-secondary);">Please sign below to accept the lease terms and conditions.</p>
            </div>
            <div class="mb-4">
                <canvas id="signatureCanvas" width="450" height="200" class="signature-canvas w-full"></canvas>
            </div>
            <div class="flex gap-2 mb-4">
                <button id="clearSignatureBtn" class="chart-period-btn" style="background-color: var(--danger); color: white;">Clear</button>
                <button id="uploadSignatureBtn" class="chart-period-btn" style="background-color: var(--info); color: white;">Upload Image</button>
                <input type="file" id="signatureUpload" accept="image/*" class="hidden">
            </div>
            <div class="flex gap-2">
                <button id="cancelSignBtn" class="chart-period-btn">Cancel</button>
                <button id="confirmSignBtn" class="chart-period-btn" style="background-color: var(--primary); color: white;">Confirm Signature</button>
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
                        <label class="form-check-label" for="widgetStats">Statistics Cards</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="lease_status" id="widgetLeaseStatus" checked>
                        <label class="form-check-label" for="widgetLeaseStatus">Lease Status Card</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="property_info" id="widgetPropertyInfo" checked>
                        <label class="form-check-label" for="widgetPropertyInfo">Property Information</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="financial_summary" id="widgetFinancialSummary" checked>
                        <label class="form-check-label" for="widgetFinancialSummary">Financial Summary</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="recent_invoices" id="widgetRecentInvoices" checked>
                        <label class="form-check-label" for="widgetRecentInvoices">Recent Community Dues Invoices</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="recent_maintenance" id="widgetRecentMaintenance" checked>
                        <label class="form-check-label" for="widgetRecentMaintenance">Recent Maintenance</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="charts" id="widgetCharts" checked>
                        <label class="form-check-label" for="widgetCharts">Payment & Maintenance Charts</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="quick_actions" id="widgetQuickActions" checked>
                        <label class="form-check-label" for="widgetQuickActions">Quick Actions Card</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="important_info" id="widgetImportantInfo" checked>
                        <label class="form-check-label" for="widgetImportantInfo">Important Information Card</label>
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
// ==================== DASHBOARD SWITCHER FUNCTIONALITY ====================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard switcher for tenant dashboard
    const switcherBtn = document.getElementById('dashboardSwitcherBtn');
    const switcherMenu = document.getElementById('dashboardSwitcherMenu');
    const currentRole = '{{ $currentRole }}';
    
    if (switcherBtn && switcherMenu) {
        // Toggle dropdown
        switcherBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            switcherMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!switcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                switcherMenu.classList.remove('show');
            }
        });
        
        // Handle dashboard switching
        const switchOptions = document.querySelectorAll('#dashboardSwitcherMenu .dashboard-switch-option');
        switchOptions.forEach(option => {
            option.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const roleSlug = this.dataset.role;
                
                if (roleSlug === currentRole) {
                    showToast(`Already on ${roleSlug.replace('-', ' ')} dashboard`, 'info');
                    switcherMenu.classList.remove('show');
                    return;
                }
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    showToast('Security error. Please refresh the page.', 'error');
                    return;
                }
                
                // Show loading state
                const originalHTML = switcherBtn.innerHTML;
                switcherBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
                switcherBtn.disabled = true;
                switcherBtn.style.opacity = '0.7';
                switcherMenu.classList.remove('show');
                
                try {
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
                    
                    if (data.success && data.redirect_url) {
                        showToast(data.message || `Switching to ${roleSlug.replace('-', ' ')} dashboard...`, 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 300);
                    } else {
                        throw new Error(data.message || 'Failed to switch dashboard');
                    }
                } catch (error) {
                    console.error('Switch error:', error);
                    showToast(error.message, 'error');
                    switcherBtn.innerHTML = originalHTML;
                    switcherBtn.disabled = false;
                    switcherBtn.style.opacity = '1';
                }
            });
        });
    }
    
    // Auto-hide multi-role banner after 5 seconds
    const multiRoleBanner = document.getElementById('multiRoleBanner');
    if (multiRoleBanner) {
        setTimeout(function() {
            multiRoleBanner.style.opacity = '0';
            multiRoleBanner.style.transform = 'translateY(-20px)';
            setTimeout(function() {
                if (multiRoleBanner && multiRoleBanner.parentNode) {
                    multiRoleBanner.remove();
                }
            }, 500);
        }, 5000);
    }
});

// Store route URLs as JavaScript variables
const routes = {
    dashboardStats: '{{ route("tenant.dashboard.stats") }}',
    dashboardCharts: '{{ route("tenant.dashboard.charts") }}',
    dashboardExport: '{{ route("tenant.dashboard.export") }}',
    dashboardWidgetsUpdate: '{{ route("tenant.dashboard.widgets.update") }}',
    dashboardWidgetsReset: '{{ route("tenant.dashboard.widgets.reset") }}',
    dashboardRefresh: '{{ route("tenant.dashboard.refresh") }}',
    financialSummary: '{{ route("tenant.financial.summary") }}',
    maintenanceSummary: '{{ route("tenant.maintenance.summary") }}',
    notificationsIndex: '{{ route("tenant.notifications.index") }}',
    notificationsReadAll: '{{ route("tenant.notifications.read-all") }}',
    invoicesIndex: '{{ route("tenant.invoices.index") }}',
    paymentsCreate: '{{ route("tenant.payments.create") }}',
    leaseSign: '{{ route("tenant.property-units.tenant-sign-lease", ["id" => $unit->id ?? 0, "leaseId" => $pendingLeaseSignature["lease_id"] ?? 0]) }}',
    csrfToken: '{{ csrf_token() }}'
};

// Store initial data from server (for backup)
let cachedStats = @json($stats);
let cachedInvoices = @json($recentInvoices ?? []);
let cachedMaintenance = @json($recentMaintenance ?? []);
let cachedNotifications = @json($notifications ?? []);

const unitId = {{ $unit->id ?? 0 }};
const leaseId = {{ $pendingLeaseSignature['lease_id'] ?? 0 }};

let paymentChart = null;
let maintenanceChart = null;
let signatureCanvas = null;
let signatureContext = null;
let isDrawing = false;
let autoRefreshInterval = null;

// Widget visibility state
let widgetVisibility = {
    stats: true,
    lease_status: true,
    property_info: true,
    financial_summary: true,
    recent_invoices: true,
    recent_maintenance: true,
    charts: true,
    quick_actions: true,
    important_info: true,
    notifications: true
};

// ==================== DASHBOARD FUNCTIONS ====================

$(document).ready(function() {
    // Load saved widget preferences from localStorage
    loadWidgetPreferences();
    
    // Initialize with server-side data (use cached data)
    updateUI(cachedStats);
    displayRecentInvoices(cachedInvoices);
    displayRecentMaintenance(cachedMaintenance);
    displayNotifications(cachedNotifications);
    
    initializeCharts();
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Event handlers
    $('#refreshDashboardBtn').on('click', function() { refreshDashboard(); });
    $('#exportDashboardBtn').on('click', function() { exportDashboard(); });
    $('#widgetSettingsBtn').on('click', function() { openWidgetSettings(); });
    $('#saveWidgetSettings').on('click', function() { saveWidgetSettings(); });
    $('#resetWidgetSettings').on('click', function() { resetWidgetSettings(); });
    $('#markAllReadBtn').on('click', function() { markAllNotificationsRead(); });
    $('#clearAllNotificationsBtn').on('click', function() { clearAllNotifications(); });
    $('#paymentHistoryYearSelect').on('change', function() { loadPaymentChart(); });
    
    $('.maintenance-period-btn').on('click', function() {
        $('.maintenance-period-btn').removeClass('active');
        $(this).addClass('active');
        loadMaintenanceChart($(this).data('period'));
    });
    
    // Lease signing buttons
    $('#signLeaseBtn, #signLeaseQuickBtn').on('click', function() { openSignatureModal(); });
    
    // Signature modal handlers
    setupSignatureCanvas();
    setupModalCloseHandlers();
});

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    autoRefreshInterval = setInterval(function() { 
        if (!document.hidden) {
            refreshDashboardData();
        }
    }, 60000);
}

function refreshDashboardData() {
    loadDashboardData(false);
}

// ==================== MODAL CLOSE HANDLERS ====================

function setupModalCloseHandlers() {
    $('.close-modal-btn').on('click', function() { closeSignatureModal(); });
    $('#cancelSignBtn').on('click', function() { closeSignatureModal(); });
    $('#signatureModal').on('click', function(e) {
        if (e.target === this) { closeSignatureModal(); }
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && !$('#signatureModal').hasClass('hidden')) {
            closeSignatureModal();
        }
    });
}

function openSignatureModal() {
    if (!leaseId) {
        showToast('No lease available to sign', 'error');
        return;
    }
    clearSignature();
    $('#signatureModal').removeClass('hidden');
}

function closeSignatureModal() {
    $('#signatureModal').addClass('hidden');
    clearSignature();
}

// ==================== SIGNATURE FUNCTIONS ====================

function setupSignatureCanvas() {
    const canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;
    
    signatureCanvas = canvas;
    signatureContext = canvas.getContext('2d');
    signatureContext.strokeStyle = '#1f2937';
    signatureContext.lineWidth = 2;
    signatureContext.lineCap = 'round';
    
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);
    canvas.addEventListener('touchstart', startDrawingTouch);
    canvas.addEventListener('touchmove', drawTouch);
    canvas.addEventListener('touchend', stopDrawing);
    
    clearSignature();
}

function startDrawing(e) {
    isDrawing = true;
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * (signatureCanvas.width / rect.width);
    const y = (e.clientY - rect.top) * (signatureCanvas.height / rect.height);
    signatureContext.beginPath();
    signatureContext.moveTo(x, y);
}

function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * (signatureCanvas.width / rect.width);
    const y = (e.clientY - rect.top) * (signatureCanvas.height / rect.height);
    signatureContext.lineTo(x, y);
    signatureContext.stroke();
    signatureContext.beginPath();
    signatureContext.moveTo(x, y);
}

function startDrawingTouch(e) {
    isDrawing = true;
    const touch = e.touches[0];
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (touch.clientX - rect.left) * (signatureCanvas.width / rect.width);
    const y = (touch.clientY - rect.top) * (signatureCanvas.height / rect.height);
    signatureContext.beginPath();
    signatureContext.moveTo(x, y);
}

function drawTouch(e) {
    if (!isDrawing) return;
    e.preventDefault();
    const touch = e.touches[0];
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (touch.clientX - rect.left) * (signatureCanvas.width / rect.width);
    const y = (touch.clientY - rect.top) * (signatureCanvas.height / rect.height);
    signatureContext.lineTo(x, y);
    signatureContext.stroke();
    signatureContext.beginPath();
    signatureContext.moveTo(x, y);
}

function stopDrawing() { isDrawing = false; }

function clearSignature() {
    if (!signatureContext || !signatureCanvas) return;
    signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
    signatureContext.fillStyle = 'white';
    signatureContext.fillRect(0, 0, signatureCanvas.width, signatureCanvas.height);
    signatureContext.strokeStyle = '#1f2937';
    signatureContext.lineWidth = 2;
}

function uploadSignature(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(event) {
        const img = new Image();
        img.onload = function() {
            signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            signatureContext.drawImage(img, 0, 0, signatureCanvas.width, signatureCanvas.height);
        };
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
}

function getSignatureData() {
    return signatureCanvas.toDataURL('image/png');
}

function submitSignature() {
    const signatureData = getSignatureData();
    const confirmBtn = $('#confirmSignBtn');
    const originalText = confirmBtn.html();
    confirmBtn.html('<span class="loading-spinner"></span> Signing...').prop('disabled', true);
    
    $.ajax({
        url: routes.leaseSign,
        type: 'POST',
        data: {
            signature_data: signatureData,
            signature_type: 'digital',
            signed_at: new Date().toISOString(),
            agreement_check: true,
            _token: routes.csrfToken
        },
        success: function(response) {
            closeSignatureModal();
            showToast('Lease signed successfully!');
            setTimeout(function() { location.reload(); }, 2000);
        },
        error: function(xhr) {
            confirmBtn.html(originalText).prop('disabled', false);
            showToast('Error signing lease: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
        }
    });
}

// ==================== DATA LOADING FUNCTIONS ====================

function loadDashboardData(showLoader) {
    if (showLoader !== false) showLoading();
    $.ajax({ 
        url: routes.dashboardStats, 
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let stats = response.data || response;
            cachedStats = stats;
            updateUI(stats);
            loadFinancialSummary(); 
            loadMaintenanceSummary(); 
            loadRecentInvoices();
            loadRecentMaintenance();
            // loadNotifications() removed
            hideErrorMessage(); 
            if (showLoader !== false) hideLoading(); 
        }, 
        error: function(xhr) { 
            if (showLoader !== false) hideLoading(); 
            console.error('Error loading dashboard data:', xhr);
            if (cachedStats) {
                updateUI(cachedStats);
                showErrorMessage('Unable to refresh data. Showing cached information.');
            } else {
                showErrorMessage('Error loading dashboard data');
            }
        } 
    });
}

function loadFinancialSummary() {
    $.ajax({ 
        url: routes.financialSummary, 
        type: 'GET',
        success: function(response) { 
            updateFinancialSummary(response); 
        },
        error: function() { console.warn('Could not load financial summary'); }
    });
}

function loadMaintenanceSummary() {
    $.ajax({ 
        url: routes.maintenanceSummary, 
        type: 'GET',
        success: function(response) { 
            updateMaintenanceSummary(response); 
        },
        error: function() { console.warn('Could not load maintenance summary'); }
    });
}

function loadRecentInvoices() {
    $.ajax({ 
        url: routes.invoicesIndex, 
        type: 'GET', 
        data: { limit: 5 }, 
        success: function(response) {
            let invoices = response.data || (Array.isArray(response) ? response : []);
            cachedInvoices = invoices;
            displayRecentInvoices(invoices);
        }, 
        error: function() {
            if (cachedInvoices && cachedInvoices.length > 0) {
                displayRecentInvoices(cachedInvoices);
            }
        } 
    });
}

function loadRecentMaintenance() {
    if (!unitId) return;
    $.ajax({ 
        url: '/tenant/property-units/' + unitId + '/maintenance-requests', 
        type: 'GET', 
        data: { limit: 5 }, 
        success: function(response) {
            let requests = response.data || (Array.isArray(response) ? response : []);
            cachedMaintenance = requests;
            displayRecentMaintenance(requests);
        }, 
        error: function() {
            if (cachedMaintenance && cachedMaintenance.length > 0) {
                displayRecentMaintenance(cachedMaintenance);
            }
        } 
    });
}

// [removed] legacy loadNotifications � superseded by notification-bell component, 
        error: function() {
            if (cachedNotifications && cachedNotifications.length > 0) {
                displayNotifications(cachedNotifications);
            }
        } 
    });
}

function updateFinancialSummary(data) { 
    if (!data) return;
    $('#totalPaid').text('GH₵' + formatNumber(data.total_paid || 0));
    if (cachedStats) cachedStats.total_paid = data.total_paid;
}

function updateMaintenanceSummary(data) { 
    if (!data) return;
    $('#totalMaintenanceCost').text('GH₵' + formatNumber(data.total_cost || 0));
    if (cachedStats) cachedStats.total_maintenance_cost = data.total_cost;
}

function updateUI(stats) {
    // My Unit card
    $('#unitNumber').text(stats.unit_number || 'Not Assigned');
    
    // Monthly Rent card
    const monthlyRent = stats.monthly_rent || 0;
    $('#monthlyRent').text('GH₵' + formatNumber(monthlyRent));
    $('#financialMonthlyRent').text('GH₵' + formatNumber(monthlyRent));
    
    // Community Development Dues card
    const communityDues = stats.outstanding_balance || 0;
    const duesElement = $('#outstandingBalance');
    duesElement.text('GH₵' + formatNumber(communityDues));
    if (communityDues > 0) {
        duesElement.css('color', 'var(--danger)');
        $('#paymentStatusBadge').html('<span class="badge-warning">Balance Due: GH₵' + formatNumber(communityDues) + '</span>');
    } else {
        duesElement.css('color', 'var(--success)');
        $('#paymentStatusBadge').html('<span class="badge-success">All Paid</span>');
    }
    
    // Maintenance card
    $('#pendingMaintenance').text(stats.pending_maintenance || 0);
    
    // Security Deposit
    $('#securityDeposit').text('GH₵' + formatNumber(stats.security_deposit || 0));
    
    // Deposit progress
    const depositPaid = stats.deposit_paid || 0;
    const depositTotal = stats.security_deposit || 0;
    if (depositTotal > 0 && depositPaid < depositTotal) {
        const depositPercent = (depositPaid / depositTotal) * 100;
        const progressBar = $('.progress-bar-fill');
        if (progressBar.length) progressBar.css('width', depositPercent + '%');
    }
}

function loadWidgetPreferences() {
    const saved = localStorage.getItem('tenant_dashboard_widgets');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            widgetVisibility = { ...widgetVisibility, ...parsed };
            applyWidgetVisibility();
        } catch(e) { console.error('Error loading widget preferences:', e); }
    }
}

function saveWidgetPreferences() {
    localStorage.setItem('tenant_dashboard_widgets', JSON.stringify(widgetVisibility));
}

function applyWidgetVisibility() {
    if (widgetVisibility.stats) $('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4').first().show();
    else $('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4').first().hide();
    
    if (widgetVisibility.lease_status) $('.lease-status-card').show();
    else $('.lease-status-card').hide();
    
    if (widgetVisibility.property_info) $('.stats-card.p-6').first().parent().show();
    else $('.stats-card.p-6').first().parent().hide();
    
    if (widgetVisibility.financial_summary) $('.stats-card.p-6').eq(1).parent().show();
    else $('.stats-card.p-6').eq(1).parent().hide();
    
    if (widgetVisibility.recent_invoices) $('#recentInvoicesList').closest('.recent-card').first().show();
    else $('#recentInvoicesList').closest('.recent-card').first().hide();
    
    if (widgetVisibility.recent_maintenance) $('#recentMaintenanceList').closest('.recent-card').last().show();
    else $('#recentMaintenanceList').closest('.recent-card').last().hide();
    
    if (widgetVisibility.charts) $('.chart-card').first().closest('.grid-cols-1.lg\\:grid-cols-2').show();
    else $('.chart-card').first().closest('.grid-cols-1.lg\\:grid-cols-2').hide();
    
    if (widgetVisibility.quick_actions) $('.stats-card.p-6').eq(2).show();
    else $('.stats-card.p-6').eq(2).hide();
    
    if (widgetVisibility.important_info) $('.stats-card.p-6').eq(3).show();
    else $('.stats-card.p-6').eq(3).hide();
    
    if (widgetVisibility.notifications) $('.mt-8 > .recent-card').show();
    else $('.mt-8 > .recent-card').hide();
}

function openWidgetSettings() {
    $('#widgetStats').prop('checked', widgetVisibility.stats);
    $('#widgetLeaseStatus').prop('checked', widgetVisibility.lease_status);
    $('#widgetPropertyInfo').prop('checked', widgetVisibility.property_info);
    $('#widgetFinancialSummary').prop('checked', widgetVisibility.financial_summary);
    $('#widgetRecentInvoices').prop('checked', widgetVisibility.recent_invoices);
    $('#widgetRecentMaintenance').prop('checked', widgetVisibility.recent_maintenance);
    $('#widgetCharts').prop('checked', widgetVisibility.charts);
    $('#widgetQuickActions').prop('checked', widgetVisibility.quick_actions);
    $('#widgetImportantInfo').prop('checked', widgetVisibility.important_info);
    $('#widgetNotifications').prop('checked', widgetVisibility.notifications);
    $('#widgetSettingsModal').modal('show');
}

function saveWidgetSettings() {
    widgetVisibility = {
        stats: $('#widgetStats').is(':checked'),
        lease_status: $('#widgetLeaseStatus').is(':checked'),
        property_info: $('#widgetPropertyInfo').is(':checked'),
        financial_summary: $('#widgetFinancialSummary').is(':checked'),
        recent_invoices: $('#widgetRecentInvoices').is(':checked'),
        recent_maintenance: $('#widgetRecentMaintenance').is(':checked'),
        charts: $('#widgetCharts').is(':checked'),
        quick_actions: $('#widgetQuickActions').is(':checked'),
        important_info: $('#widgetImportantInfo').is(':checked'),
        notifications: $('#widgetNotifications').is(':checked')
    };
    
    saveWidgetPreferences();
    applyWidgetVisibility();
    
    const widgets = Object.keys(widgetVisibility).filter(key => widgetVisibility[key]);
    $.ajax({
        url: routes.dashboardWidgetsUpdate,
        type: 'POST',
        data: { widgets: widgets, _token: routes.csrfToken },
        success: function() {
            $('#widgetSettingsModal').modal('hide');
            showToast('Dashboard layout updated successfully!');
        },
        error: function() { showToast('Error saving widget settings', 'error'); }
    });
}

function resetWidgetSettings() {
    widgetVisibility = {
        stats: true, lease_status: true, property_info: true, financial_summary: true,
        recent_invoices: true, recent_maintenance: true, charts: true,
        quick_actions: true, important_info: true, notifications: true
    };
    
    saveWidgetPreferences();
    applyWidgetVisibility();
    
    $.ajax({
        url: routes.dashboardWidgetsReset,
        type: 'POST',
        data: { _token: routes.csrfToken },
        success: function() {
            $('#widgetSettingsModal').modal('hide');
            showToast('Dashboard layout reset to default!');
        },
        error: function() { showToast('Error resetting widget settings', 'error'); }
    });
}

function initializeCharts() {
    const paymentCtx = document.getElementById('paymentHistoryChart');
    const maintenanceCtx = document.getElementById('maintenanceChart');
    
    if (paymentCtx) {
        try {
            paymentChart = new Chart(paymentCtx, {
                type: 'bar',
                data: { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], datasets: [{ label: 'Community Dues (GH₵)', data: Array(12).fill(0), backgroundColor: 'rgba(59, 130, 246, 0.7)', borderColor: '#3b82f6', borderWidth: 2, borderRadius: 8 }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { tooltip: { callbacks: { label: function(context) { return 'GH₵ ' + context.raw.toLocaleString('en-US', { minimumFractionDigits: 2 }); } } } }, scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return 'GH₵ ' + value.toLocaleString(); } } } } }
            });
        } catch(e) { console.error('Payment chart error:', e); }
    }
    
    if (maintenanceCtx) {
        try {
            maintenanceChart = new Chart(maintenanceCtx, {
                type: 'line',
                data: { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], datasets: [{ label: 'Maintenance Requests', data: Array(12).fill(0), borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', borderWidth: 3, tension: 0.4, fill: true }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { tooltip: { callbacks: { label: function(context) { return context.raw + ' requests'; } } } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        } catch(e) { console.error('Maintenance chart error:', e); }
    }
}

function showErrorMessage(message) { 
    $('#errorText').text(message); 
    $('#errorMessage').removeClass('hidden'); 
    setTimeout(function() { $('#errorMessage').addClass('hidden'); }, 5000); 
}

function hideErrorMessage() { $('#errorMessage').addClass('hidden'); }

function displayRecentInvoices(invoices) {
    let html = '';
    if (invoices && invoices.length > 0) {
        for (let i = 0; i < invoices.length; i++) {
            const inv = invoices[i];
            const statusClass = inv.status === 'paid' ? 'badge-success' : (inv.status === 'overdue' ? 'badge-overdue' : 'badge-warning');
            html += '<div class="list-group-item-custom">' +
                '<div class="flex justify-between items-center">' +
                '<div><p class="font-medium">Invoice #' + escapeHtml(inv.invoice_number) + '</p>' +
                '<p class="text-xs mt-1">Period: ' + (inv.period || inv.created_at) + '</p></div>' +
                '<div class="text-right"><p class="font-bold">GH₵' + formatNumber(inv.amount) + '</p>' +
                '<span class="badge ' + statusClass + '">' + inv.status + '</span>' +
                (inv.due_date ? '<p class="text-xs mt-1">Due: ' + inv.due_date + '</p>' : '') +
                '</div></div></div>';
        }
    } else {
        html = '<div class="px-6 py-8 text-center"><i class="fas fa-file-invoice text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i><p style="color: var(--text-secondary);">No community dues invoices found</p></div>';
    }
    $('#recentInvoicesList').html(html);
}

function displayRecentMaintenance(requests) {
    let html = '';
    if (requests && requests.length > 0) {
        for (let i = 0; i < requests.length; i++) {
            const req = requests[i];
            const statusClass = req.status === 'completed' ? 'badge-success' : (req.status === 'in_progress' ? 'badge-info' : 'badge-warning');
            html += '<div class="list-group-item-custom">' +
                '<div class="flex justify-between items-start">' +
                '<div class="flex-1"><p class="font-medium">' + escapeHtml(req.title) + '</p>' +
                '<p class="text-xs mt-1">' + escapeHtml(req.description) + '</p>' +
                '<p class="text-xs mt-1">' + req.created_at + '</p></div>' +
                '<div><span class="badge ' + statusClass + '">' + req.status + '</span>' +
                (req.priority === 'urgent' ? '<span class="badge-danger ml-1">Urgent</span>' : '') +
                '</div></div></div>';
        }
    } else {
        html = '<div class="px-6 py-8 text-center"><i class="fas fa-check-circle text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i><p style="color: var(--text-secondary);">No maintenance requests</p>' +
            (unitId ? '<a href="/tenant/property-units/' + unitId + '/create-maintenance-request" class="chart-period-btn mt-2" style="background-color: var(--primary); color: white;"><i class="fas fa-plus-circle mr-1"></i> Submit Request</a>' : '') +
            '</div>';
    }
    $('#recentMaintenanceList').html(html);
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

function markNotificationRead(id) {
    $.ajax({ url: '{{ url("/tenant/notifications") }}/' + id + '/read', type: 'POST', data: { _token: routes.csrfToken }, 
             success: function() { // loadNotifications() removed - Alpine bell handles this loadDashboardData(false); } });
}

function deleteNotification(id) {
    $.ajax({ url: '{{ url("/tenant/notifications") }}/' + id, type: 'DELETE', data: { _token: routes.csrfToken }, 
             success: function() { // loadNotifications() removed - Alpine bell handles this } });
}

function markAllNotificationsRead() {
    $.ajax({ url: routes.notificationsReadAll, type: 'POST', data: { _token: routes.csrfToken }, 
             success: function() { // loadNotifications() removed - Alpine bell handles this loadDashboardData(false); showToast('All notifications marked as read'); } });
}

function clearAllNotifications() {
    if (confirm('Delete all notifications? This cannot be undone.')) {
        $.ajax({
            url: '/api/notifications',
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': routes.csrfToken,
                'Content-Type': 'application/json'
            },
            success: function(response) {
                // loadNotifications() removed
                loadDashboardData(false);
                showToast('All notifications cleared');
            },
            error: function(xhr) {
                console.error('Error clearing notifications:', xhr);
                showToast('Failed to clear notifications: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
            }
        });
    }
}

function loadPaymentChart() {
    const year = $('#paymentHistoryYearSelect').val();
    $.ajax({ url: routes.dashboardCharts, type: 'GET', data: { type: 'payment', year: year }, success: function(response) {
        const labels = response.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const data = response.data || Array(12).fill(0);
        if (paymentChart) { paymentChart.data.labels = labels; paymentChart.data.datasets[0].data = data; paymentChart.update(); }
    } });
}

function loadMaintenanceChart(period) {
    period = period || 'monthly';
    $.ajax({ url: routes.dashboardCharts, type: 'GET', data: { type: 'maintenance', period: period }, success: function(response) {
        let labels, data;
        if (period === 'quarterly') { labels = response.labels || ['Q1', 'Q2', 'Q3', 'Q4']; data = response.data || [0, 0, 0, 0]; } 
        else { labels = response.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; data = response.data || Array(12).fill(0); }
        if (maintenanceChart) { maintenanceChart.data.labels = labels; maintenanceChart.data.datasets[0].data = data; maintenanceChart.update(); }
    } });
}

function refreshDashboard() {
    showLoading();
    $.ajax({ url: routes.dashboardRefresh, type: 'POST', data: { _token: routes.csrfToken }, success: function(response) {
        if (response.success && response.data) {
            updateUI(response.data.stats);
            updateFinancialSummary(response.data.financial_summary);
            updateMaintenanceSummary(response.data.maintenance_summary);
            loadRecentInvoices();
            loadRecentMaintenance();
            loadPaymentChart();
            loadMaintenanceChart($('.maintenance-period-btn.active').data('period') || 'monthly');
            showToast('Dashboard refreshed!');
        }
        hideLoading();
    }, error: function() { loadDashboardData(true); showToast('Dashboard refreshed!'); hideLoading(); } });
}

function exportDashboard() { window.location.href = routes.dashboardExport + '?format=csv'; showToast('Exporting...'); }

// ==================== UTILITY FUNCTIONS ====================

function formatNumber(num) { 
    if (num === null || num === undefined) return '0'; 
    return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); 
}

function escapeHtml(text) { 
    if (!text) return ''; 
    const div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

function showLoading() { 
    $('#refreshDashboardBtn').html('<span class="loading-spinner"></span> Loading...').prop('disabled', true); 
}

function hideLoading() { 
    $('#refreshDashboardBtn').html('<i class="fas fa-sync-alt mr-1"></i> Refresh').prop('disabled', false); 
}

function showToast(message, type) { 
    type = type || 'success'; 
    const toast = $('<div class="toast-notification toast-' + type + '">' + message + '</div>'); 
    $('body').append(toast); 
    setTimeout(function() { toast.fadeOut(300, function() { toast.remove(); }); }, 3000); 
}
</script>
@endpush