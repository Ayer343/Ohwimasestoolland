@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="dashboard-container">
    <!-- Page Header with Dashboard Switcher -->
    <div class="dashboard-header mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-tachometer-alt mr-2" style="color: var(--primary);"></i>Admin Dashboard
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Welcome back, {{ auth()->user()->name }}! Here's what's happening today.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Dashboard Switcher (Multi-Role Support) - FIXED VERSION -->
                @php
                    $userRoles = auth()->user()->roles ?? collect();
                    $hasMultipleRoles = $userRoles->count() > 1;
                    
                    // FIX: Get current role from session OR detect from current route
                    $currentRole = session('selected_role');
                    
                    // If no role in session, detect from current route
                    if (!$currentRole) {
                        $currentRoute = Route::currentRouteName();
                        if (str_contains($currentRoute, 'super-admin')) {
                            $currentRole = 'super-admin';
                        } elseif (str_contains($currentRoute, 'admin')) {
                            $currentRole = 'admin';
                        } elseif (str_contains($currentRoute, 'developer')) {
                            $currentRole = 'developer';
                        } elseif (str_contains($currentRoute, 'landlord')) {
                            $currentRole = 'landlord';
                        } elseif (str_contains($currentRoute, 'field-agent')) {
                            $currentRole = 'field-agent';
                        } elseif (str_contains($currentRoute, 'security')) {
                            $currentRole = 'security-personnel';
                        } elseif (str_contains($currentRoute, 'tenant')) {
                            $currentRole = 'tenant';
                        } else {
                            $currentRole = 'admin'; // fallback
                        }
                    }
                    
                    // Ensure the user actually has this role
                    if (!$userRoles->contains('slug', $currentRole)) {
                        $primaryRole = auth()->user()->getPrimaryRoleAttribute();
                        $currentRole = $primaryRole ? $primaryRole->slug : 'admin';
                    }
                    
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
                            @foreach($userRoles as $role)
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
                
                <div class="text-right">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- BANNER 1: LANDLORD STATS EXPLAINED (Auto-hide) -->
        <div id="landlordStatsBanner" class="mt-4 p-3 rounded-lg flex items-center justify-between flex-wrap gap-3 transition-all duration-500" 
             style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%); border-left: 4px solid #10b981;">
            <div class="flex items-center gap-3">
                <i class="fas fa-info-circle text-lg" style="color: #10b981;"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-home mr-1" style="color: #10b981;"></i> 
                        Landlord Statistics Explained
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        Landlord counts include users who have the <strong class="font-semibold" style="color: #10b981;">"landlord" role</strong> assigned, 
                        regardless of their primary user type (Admins, Agents, etc. with landlord privileges).
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-tag mr-1"></i> Role-based counting
                </span>
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="fas fa-database mr-1"></i> Legacy type included
                </span>
            </div>
        </div>
        
        <!-- BANNER 2: MULTI-ROLE NOTICE (Auto-hide) -->
        @if($hasMultipleRoles)
        <div id="multiRoleBanner" class="mt-4 p-3 rounded-lg flex items-center justify-between flex-wrap gap-3 transition-all duration-500" 
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
            <div class="flex gap-2">
                @foreach($userRoles->where('slug', '!=', $currentRole)->take(3) as $role)
                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas {{ $role->slug === 'landlord' ? 'fa-home' : ($role->slug === 'tenant' ? 'fa-user' : ($role->slug === 'field-agent' ? 'fa-clipboard-list' : 'fa-shield-alt')) }} mr-1"></i>
                        {{ ucfirst(str_replace('-', ' ', $role->slug)) }}
                    </span>
                @endforeach
                @if($userRoles->where('slug', '!=', $currentRole)->count() > 3)
                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        +{{ $userRoles->where('slug', '!=', $currentRole)->count() - 3 }} more
                    </span>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Quick Stats Cards -->
    <div class="quick-stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Revenue Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Revenue</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ ($quickStats['total_revenue']['value'] ?? '$0.00') }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $trend = $quickStats['total_revenue']['trend'] ?? 0;
                        @endphp
                        @if($trend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $trend }}%</span>
                        @elseif($trend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $trend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-dollar-sign text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Users Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Users</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($userStats['total_users'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $userTrend = $quickStats['total_users']['trend'] ?? 0;
                        @endphp
                        @if($userTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $userTrend }}%</span>
                        @elseif($userTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $userTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Properties Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($propertyStats['total_properties'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $propertyTrend = $quickStats['total_properties']['trend'] ?? 0;
                        @endphp
                        @if($propertyTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $propertyTrend }}%</span>
                        @elseif($propertyTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $propertyTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-building text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Plans Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active Plans</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($quickStats['active_plans']['value'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $planTrend = $quickStats['active_plans']['trend'] ?? 0;
                        @endphp
                        @if($planTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $planTrend }}%</span>
                        @elseif($planTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $planTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-clipboard-list text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Cards -->
    <div class="quick-nav-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <a href="{{ route('admin.testimonials.index') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                <i class="fas fa-star text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Testimonials</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">Manage feedback</p>
        </a>
        
        <a href="{{ route('admin.users.index') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                <i class="fas fa-users text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Users</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">Manage accounts</p>
        </a>
        
        <a href="{{ route('properties.index') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-building text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Properties</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">View properties</p>
        </a>
        
        <a href="{{ route('admin.payments.index') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #ec489a 0%, #db2777 100%);">
                <i class="fas fa-credit-card text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Payments</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">View transactions</p>
        </a>
        
        <a href="{{ route('registration-plans.index') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                <i class="fas fa-map-marked-alt text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Plans</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">Registration plans</p>
        </a>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Revenue Overview
                </h3>
                <div class="flex space-x-2">
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-chart="revenue" data-period="monthly"
                            style="background-color: var(--primary); color: white;">Monthly</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="revenue" data-period="daily"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Daily</button>
                </div>
            </div>
            <canvas id="revenueChart" height="250"></canvas>
        </div>

        <!-- User Registration Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>User Registrations
                </h3>
                <div class="flex space-x-2">
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-chart="users" data-period="all"
                            style="background-color: var(--primary); color: white;">All Users</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="users" data-period="landlords"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Landlords</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="users" data-period="agents"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Agents</button>
                </div>
            </div>
            <div class="mb-3 text-center">
                <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-info-circle mr-1"></i>
                    Landlords = users with "landlord" role OR type=landlord
                </span>
            </div>
            <canvas id="userChart" height="250"></canvas>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- User Statistics -->
        <div class="stats-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>User Statistics
                <span class="inline-flex items-center ml-2 group relative">
                    <i class="fas fa-question-circle text-xs cursor-help" style="color: var(--text-secondary);"></i>
                    <span class="absolute bottom-full left-0 mb-2 hidden group-hover:block w-64 p-2 text-xs rounded-lg z-10 shadow-lg" 
                          style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <i class="fas fa-home mr-1" style="color: #10b981;"></i> 
                        Landlord count includes users with the "landlord" role assigned, regardless of their primary user type (Admins, Agents, etc. with landlord privileges).
                    </span>
                </span>
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Total Users</span>
                    <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($userStats['total_users'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Active Users</span>
                    <span class="font-semibold text-green-500">{{ number_format($userStats['active_users'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Pending Users</span>
                    <span class="font-semibold text-yellow-500">{{ number_format($userStats['pending_users'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Suspended Users</span>
                    <span class="font-semibold text-red-500">{{ number_format($userStats['suspended_users'] ?? 0) }}</span>
                </div>
                <div class="border-t pt-3 mt-3" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <span style="color: var(--text-secondary);">Admins</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($userStats['admins'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <div class="flex items-center gap-1">
                            <span style="color: var(--text-secondary);">Landlords</span>
                            <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                                <i class="fas fa-tag mr-0.5 text-xs"></i> role-based
                            </span>
                        </div>
                        <span class="font-semibold" style="color: #10b981;">{{ number_format($userStats['landlords'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Tenants</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($userStats['tenants'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Field Agents</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($userStats['field_agents'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Security Personnel</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($userStats['security_checkpoints'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Statistics -->
        <div class="stats-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-building mr-2" style="color: var(--primary);"></i>Property Statistics
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Total Properties</span>
                    <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($propertyStats['total_properties'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Active Properties</span>
                    <span class="font-semibold text-green-500">{{ number_format($propertyStats['active_properties'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Pending Properties</span>
                    <span class="font-semibold text-yellow-500">{{ number_format($propertyStats['pending_properties'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">With Digital Address</span>
                    <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($propertyStats['with_digital_address'] ?? 0) }}</span>
                </div>
                <div class="border-t pt-3 mt-3" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <span style="color: var(--text-secondary);">Registered Today</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($propertyStats['properties_registered_today'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Registered This Week</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($propertyStats['properties_registered_this_week'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Registered This Month</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($propertyStats['properties_registered_this_month'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Statistics -->
        <div class="stats-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i>Payment Statistics
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Total Payments</span>
                    <span class="font-semibold" style="color: var(--text-primary);">{{ number_format($paymentStats['total_payments'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Completed Payments</span>
                    <span class="font-semibold text-green-500">{{ number_format($paymentStats['completed_payments'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Pending Payments</span>
                    <span class="font-semibold text-yellow-500">{{ number_format($paymentStats['pending_payments'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Failed Payments</span>
                    <span class="font-semibold text-red-500">{{ number_format($paymentStats['failed_payments'] ?? 0) }}</span>
                </div>
                <div class="border-t pt-3 mt-3" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <span style="color: var(--text-secondary);">Today's Revenue</span>
                        <span class="font-semibold text-green-500">{{ $paymentStats['formatted_today_revenue'] ?? '$0.00' }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Weekly Revenue</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $paymentStats['formatted_weekly_revenue'] ?? '$0.00' }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span style="color: var(--text-secondary);">Monthly Revenue</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $paymentStats['formatted_monthly_revenue'] ?? '$0.00' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Plan Statistics Section -->
    <div class="plan-stats-card rounded-xl p-6 mb-8" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Registration Plan Statistics
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Total Plans</p>
                <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($planStats['total_plans'] ?? 0) }}</p>
            </div>
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Active Plans</p>
                <p class="text-2xl font-bold text-blue-500">{{ number_format($planStats['active_plans'] ?? 0) }}</p>
            </div>
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Completed Plans</p>
                <p class="text-2xl font-bold text-green-500">{{ number_format($planStats['completed_plans'] ?? 0) }}</p>
            </div>
            <div class="text-center">
                <p class="text-sm" style="color: var(--text-secondary);">Unassigned Plans</p>
                <p class="text-2xl font-bold text-yellow-500">{{ number_format($planStats['unassigned_plans'] ?? 0) }}</p>
            </div>
        </div>
        @if(($planStats['multi_agent_plans'] ?? 0) > 0 || ($planStats['single_assignment_plans'] ?? 0) > 0)
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-center space-x-8">
                <div class="text-center">
                    <span class="text-sm" style="color: var(--text-secondary);">Single Agent Plans</span>
                    <p class="text-xl font-semibold">{{ number_format($planStats['single_assignment_plans'] ?? 0) }}</p>
                </div>
                <div class="text-center">
                    <span class="text-sm" style="color: var(--text-secondary);">Multi-Agent Plans</span>
                    <p class="text-xl font-semibold">{{ number_format($planStats['multi_agent_plans'] ?? 0) }}</p>
                </div>
                <div class="text-center">
                    <span class="text-sm" style="color: var(--text-secondary);">Total Agent Assignments</span>
                    <p class="text-xl font-semibold">{{ number_format($planStats['total_agent_assignments'] ?? 0) }}</p>
                </div>
                <div class="text-center">
                    <span class="text-sm" style="color: var(--text-secondary);">Properties Through Plans</span>
                    <p class="text-xl font-semibold">{{ number_format($planStats['properties_through_plans'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Activity Section -->
    <div class="recent-activity-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Payments -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Payments
                    </h3>
                    <a href="{{ route('admin.payments.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse(($recentPayments ?? []) as $payment)
                    <div class="px-6 py-4 hover:bg-opacity-5 transition-colors" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">
                                    {{ $payment->transaction_id ?? 'Payment #' . $payment->id }}
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $payment->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold" style="color: var(--text-primary);">
                                    {{ $payment->formatted_amount ?? '$' . number_format($payment->amount, 2) }}
                                </p>
                                <span class="text-xs px-2 py-1 rounded-full payment-status-{{ $payment->status }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
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

        <!-- Recent Users -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>Recent Users
                    </h3>
                    <a href="{{ route('admin.users.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse(($recentUsers ?? []) as $user)
                    <div class="px-6 py-4 hover:bg-opacity-5 transition-colors" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $user->name }}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $user->email }}</p>
                                @if($user->hasRole('landlord') && $user->type != 2)
                                    <span class="inline-flex items-center text-xs mt-1 px-1.5 py-0.5 rounded-full" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        <i class="fas fa-user-tag mr-0.5 text-xs"></i> Landlord via role
                                    </span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded-full user-type-{{ $user->type }}">
                                    {{ $user->type_name ?? getUserTypeName($user->type) }}
                                </span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Joined {{ $user->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-users text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No recent users</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Properties & Recent Testimonials -->
    <div class="recent-activity-grid grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Properties -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2" style="color: var(--primary);"></i>Recent Properties
                    </h3>
                    <a href="{{ route('properties.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse(($recentProperties ?? []) as $property)
                    <div class="px-6 py-4 hover:bg-opacity-5 transition-colors" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $property->street_name ?? 'No street' }}, {{ $property->zone ?? 'No zone' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded-full property-status-{{ $property->status }}">
                                    {{ ucfirst($property->status ?? 'Active') }}
                                </span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Added {{ $property->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-building text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No recent properties</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Testimonials - APPROVED ONLY -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-star mr-2" style="color: var(--warning);"></i>Recent Testimonials
                        <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Approved Only
                        </span>
                    </h3>
                    <a href="{{ route('admin.testimonials.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        Manage All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse(($recentTestimonials ?? []) as $testimonial)
                    <div class="px-6 py-4 hover:bg-opacity-5 transition-colors" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <img src="{{ $testimonial->avatar_url }}" 
                                         alt="{{ $testimonial->name }}" 
                                         class="w-10 h-10 rounded-full object-cover"
                                         onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($testimonial->name) }}&background=3b82f6&color=fff'">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold text-sm" style="color: var(--text-primary);">
                                                {{ $testimonial->name }}
                                            </p>
                                            @if($testimonial->is_featured)
                                                <span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-star mr-0.5 text-xs"></i>Featured
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <div class="flex items-center">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="fas fa-star text-xs {{ $i <= $testimonial->rating ? 'text-warning' : 'text-muted' }}" 
                                                       style="{{ $i <= $testimonial->rating ? 'color: var(--warning);' : 'color: var(--text-secondary);' }}"></i>
                                                @endfor
                                            </div>
                                            <span class="text-xs" style="color: var(--text-secondary);">({{ $testimonial->rating }}/5)</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="text-sm mt-2" style="color: var(--text-primary); line-height: 1.5;">
                                    "{{ Str::limit($testimonial->content, 120) }}"
                                </p>
                                
                                <div class="flex flex-wrap items-center gap-3 mt-2">
                                    @if($testimonial->role)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-briefcase mr-1 text-xs"></i> {{ $testimonial->role }}
                                        </span>
                                    @endif
                                    @if($testimonial->property_location)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-map-marker-alt mr-1 text-xs"></i> {{ $testimonial->property_location }}
                                        </span>
                                    @endif
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        <i class="far fa-clock mr-1"></i> {{ $testimonial->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ml-3 flex gap-1">
                                <a href="{{ route('admin.testimonials.show', $testimonial->id) }}" 
                                   class="action-btn view" data-tooltip="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" 
                                        onclick="toggleFeatured({{ $testimonial->id }}, {{ $testimonial->is_featured ? 'true' : 'false' }})"
                                        class="action-btn" 
                                        data-tooltip="{{ $testimonial->is_featured ? 'Remove Featured' : 'Make Featured' }}"
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-star"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-star text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.3;"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">No approved testimonials yet</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">When customers submit testimonials and they are approved, they'll appear here.</p>
                        <div class="flex justify-center gap-3 mt-4">
                            <a href="{{ route('admin.testimonials.index') }}" class="inline-flex items-center gap-1 text-xs hover:underline" style="color: var(--primary);">
                                <i class="fas fa-arrow-right"></i> Manage Testimonials
                            </a>
                            <a href="{{ route('dashboard.testimonials.index') }}" class="inline-flex items-center gap-1 text-xs hover:underline" style="color: var(--success);">
                                <i class="fas fa-star"></i> My Testimonials
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
            @if(isset($recentTestimonials) && count($recentTestimonials) > 0)
            <div class="px-6 py-3 border-t" style="border-color: var(--border-color); background-color: var(--bg-secondary); border-radius: 0 0 0.75rem 0.75rem;">
                <div class="flex justify-between items-center">
                    <a href="{{ route('admin.testimonials.index', ['status' => 'approved']) }}" 
                       class="text-sm hover:underline flex items-center gap-2" style="color: var(--primary);">
                        <i class="fas fa-check-circle"></i>
                        <span>View All Approved Testimonials</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="{{ route('dashboard.testimonials.index') }}" 
                       class="text-sm hover:underline flex items-center gap-2" style="color: var(--success);">
                        <i class="fas fa-star"></i>
                        <span>My Testimonials</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Dashboard Switcher Styles - FIXED */
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
    
    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
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

    /* Banner auto-hide animation */
    @keyframes fadeOutUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
            visibility: hidden;
            display: none;
        }
    }
    
    .fade-out-up {
        animation: fadeOutUp 0.5s ease forwards;
    }

    /* Quick Navigation Cards */
    .quick-nav-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }
    .quick-nav-card:hover {
        transform: translateY(-4px);
    }

    /* Action Button Styles */
    .action-btn {
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .action-btn.view {
        background-color: rgba(var(--info-rgb), 0.1);
        color: var(--info);
        border-color: rgba(var(--info-rgb), 0.3);
    }

    .action-btn.view:hover {
        transform: translateY(-1px);
        background-color: rgba(var(--info-rgb), 0.2);
    }

    /* Status Badge Colors */
    .status-badge-active, .status-badge-completed, .status-badge-paid {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .status-badge-pending {
        background-color: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }
    .status-badge-inactive, .status-badge-cancelled, .status-badge-failed {
        background-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    .status-badge-suspended {
        background-color: rgba(107, 114, 128, 0.2);
        color: #6b7280;
    }

    /* Payment Status */
    .payment-status-completed {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .payment-status-pending {
        background-color: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }
    .payment-status-failed {
        background-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    /* User Type Badges */
    .user-type-1 {
        background-color: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    .user-type-2 {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .user-type-3 {
        background-color: rgba(236, 72, 153, 0.2);
        color: #ec4899;
    }
    .user-type-4 {
        background-color: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }
    .user-type-6 {
        background-color: rgba(107, 114, 128, 0.2);
        color: #6b7280;
    }

    /* Property Status */
    .property-status-active {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .property-status-pending {
        background-color: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }
    .property-status-inactive {
        background-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    /* Plan Status */
    .plan-status-draft {
        background-color: rgba(107, 114, 128, 0.2);
        color: #6b7280;
    }
    .plan-status-assigned, .plan-status-in_progress {
        background-color: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    .plan-status-completed {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .plan-status-cancelled {
        background-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    /* Text Colors */
    .text-warning {
        color: var(--warning) !important;
    }
    .text-muted {
        color: var(--text-secondary) !important;
    }

    /* Stat Cards Hover Effect */
    .stat-card, .stats-card, .recent-card, .chart-card, .plan-stats-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover, .stats-card:hover, .recent-card:hover, .chart-card:hover, .plan-stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Chart Period Buttons */
    .chart-period-btn {
        cursor: pointer;
    }
    .chart-period-btn.active {
        background-color: var(--primary) !important;
        color: white !important;
    }

    /* Tooltip */
    .tooltip {
        pointer-events: none;
    }

    /* Hover tooltip for info icon */
    .group:hover .group-hover\:block {
        display: block;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0 0.5rem;
        }
        .quick-stats-grid, .stats-grid {
            gap: 1rem;
        }
        .stat-card, .stats-card {
            padding: 1rem;
        }
        .quick-nav-grid {
            gap: 0.75rem;
        }
        .quick-nav-card {
            padding: 0.75rem;
        }
        .quick-nav-card .w-12 {
            width: 2.5rem;
            height: 2.5rem;
        }
        .quick-nav-card .w-12 i {
            font-size: 0.875rem;
        }
        .dashboard-header .flex {
            flex-direction: column;
            align-items: stretch;
        }
        .dashboard-switcher {
            width: 100%;
        }
        #dashboardSwitcherBtn {
            width: 100%;
            justify-content: center;
        }
        #dashboardSwitcherMenu {
            width: 100%;
            left: 0;
            right: auto;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ========== GLOBAL FUNCTIONS ==========
    
    // Toggle Featured Function for Testimonials
    function toggleFeatured(testimonialId, isFeatured) {
        console.log('📝 Toggle featured called for testimonial:', testimonialId);
        
        const url = `/admin/testimonials/${testimonialId}/toggle-featured`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (!csrfToken) {
            console.error('❌ CSRF token missing');
            alert('Security error. Please refresh the page.');
            return;
        }
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const toast = document.createElement('div');
                toast.className = 'fixed top-20 right-4 z-50 px-4 py-2 rounded-lg shadow-lg bg-green-500 text-white text-sm';
                toast.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${data.message}`;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error toggling featured:', error);
            alert(error.message || 'An error occurred while processing the request');
        });
    }
    
    // Global notification function
    window.showDashboardNotification = function(message, type = 'success') {
        const existingNotif = document.querySelectorAll('.dashboard-notification');
        existingNotif.forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `dashboard-notification fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white text-sm transition-all duration-300`;
        notification.style.zIndex = '9999';
        notification.style.position = 'fixed';
        
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
    };
    
    // Global dashboard switch function - FIXED with proper redirect
    window.switchDashboardRole = async function(roleSlug, currentRole, buttonElement) {
        console.log('🖱️ SwitchDashboardRole called with:', roleSlug, 'Current:', currentRole);
        
        if (roleSlug === currentRole) {
            console.log('Already on this dashboard');
            window.showDashboardNotification(`Already on ${roleSlug.replace('-', ' ')} dashboard`, 'info');
            return false;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.error('❌ CSRF token not found');
            window.showDashboardNotification('Security error. Please refresh the page.', 'error');
            return false;
        }
        
        // Show loading state if button element provided
        let originalHTML = '';
        if (buttonElement) {
            originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
            buttonElement.disabled = true;
            buttonElement.style.opacity = '0.7';
        }
        
        try {
            console.log('📤 Sending switch request for role:', roleSlug);
            console.log('📤 Endpoint: /dashboard/switch');
            
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
            
            console.log('📥 Response status:', response.status);
            
            const data = await response.json();
            console.log('📥 Response data:', data);
            
            if (response.ok && data.success && data.redirect_url) {
                console.log('✅ Switch successful! Redirecting to:', data.redirect_url);
                window.showDashboardNotification(data.message || `Switching to ${roleSlug.replace('-', ' ')} dashboard...`, 'success');
                
                // 🔥 CRITICAL FIX: Use window.location.replace for immediate redirect
                setTimeout(function() {
                    console.log('🚀 Executing redirect NOW to:', data.redirect_url);
                    window.location.replace(data.redirect_url);
                }, 200);
                return true;
            } else {
                throw new Error(data.message || `Server returned ${response.status}`);
            }
        } catch (error) {
            console.error('❌ Switch error:', error);
            window.showDashboardNotification(error.message || 'Failed to switch dashboard', 'error');
            
            // Restore button if provided
            if (buttonElement && originalHTML) {
                buttonElement.innerHTML = originalHTML;
                buttonElement.disabled = false;
                buttonElement.style.opacity = '1';
            }
            return false;
        }
    };
    
    // Manual redirect function for debugging
    window.manualRedirect = function(url) {
        console.log('🔧 Manual redirect to:', url);
        window.location.href = url;
    };
    
    // ========== MAIN INITIALIZATION ==========
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Admin Dashboard Initializing...');
        
        // Auto-hide banners after page load
        const landlordBanner = document.getElementById('landlordStatsBanner');
        if (landlordBanner) {
            setTimeout(function() {
                landlordBanner.classList.add('fade-out-up');
                setTimeout(function() {
                    if (landlordBanner && landlordBanner.parentNode) {
                        landlordBanner.remove();
                    }
                }, 500);
            }, 5000);
        }
        
        const multiRoleBanner = document.getElementById('multiRoleBanner');
        if (multiRoleBanner) {
            setTimeout(function() {
                multiRoleBanner.classList.add('fade-out-up');
                setTimeout(function() {
                    if (multiRoleBanner && multiRoleBanner.parentNode) {
                        multiRoleBanner.remove();
                    }
                }, 500);
            }, 5000);
        }
        
        // ========== DASHBOARD SWITCHER INITIALIZATION ==========
        function initDashboardSwitcher() {
            const switcherBtn = document.getElementById('dashboardSwitcherBtn');
            const switcherMenu = document.getElementById('dashboardSwitcherMenu');
            
            let currentRole = '{{ $currentRole }}';
            console.log('🔧 Dashboard Switcher Init - Current Role:', currentRole);
            
            if (!switcherBtn || !switcherMenu) {
                console.warn('⚠️ Dashboard switcher elements not found');
                return;
            }
            
            const newSwitcherBtn = switcherBtn.cloneNode(true);
            switcherBtn.parentNode.replaceChild(newSwitcherBtn, switcherBtn);
            
            newSwitcherBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                console.log('🖱️ Switcher button clicked');
                if (switcherMenu.classList.contains('show')) {
                    switcherMenu.classList.remove('show');
                } else {
                    switcherMenu.classList.add('show');
                }
            });
            
            document.addEventListener('click', function(e) {
                if (!newSwitcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                    if (switcherMenu.classList.contains('show')) {
                        switcherMenu.classList.remove('show');
                    }
                }
            });
            
            const switchOptions = document.querySelectorAll('.dashboard-switch-option');
            console.log('📋 Switch Options Found:', switchOptions.length);
            
            switchOptions.forEach(option => {
                const newOption = option.cloneNode(true);
                option.parentNode.replaceChild(newOption, option);
                
                newOption.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const roleSlug = this.dataset.role;
                    console.log('🎯 Dashboard option clicked:', roleSlug);
                    switcherMenu.classList.remove('show');
                    window.switchDashboardRole(roleSlug, currentRole, newSwitcherBtn);
                });
            });
        }
        
        setTimeout(initDashboardSwitcher, 100);
        
        // ========== CHART INITIALIZATION ==========
        console.log('📊 Initializing charts...');
        
        try {
            const revenueData = @json($revenueData ?? []);
            const monthlyData = revenueData.monthly || [];
            const dailyData = revenueData.daily || [];
            const monthlyLabels = revenueData.monthly_labels || [];
            const dailyLabels = revenueData.daily_labels || [];
            
            let revenueChart = null;
            
            function initRevenueChart(period = 'monthly') {
                const canvas = document.getElementById('revenueChart');
                if (!canvas) return;
                
                const ctx = canvas.getContext('2d');
                if (revenueChart) revenueChart.destroy();
                
                const data = period === 'monthly' ? monthlyData : dailyData;
                const labels = period === 'monthly' ? monthlyLabels : dailyLabels;
                const label = period === 'monthly' ? 'Monthly Revenue' : 'Daily Revenue';
                
                revenueChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: label,
                            data: data,
                            borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
                            backgroundColor: 'rgba(var(--primary-rgb), 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
                            pointBorderColor: '#fff',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) label += ': ';
                                        label += '{{ $settings->currency_symbol ?? "$" }}' + context.raw.toLocaleString();
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                                    callback: function(value) {
                                        return '{{ $settings->currency_symbol ?? "$" }}' + value.toLocaleString();
                                    }
                                },
                                grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                            },
                            x: {
                                ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                                grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                            }
                        }
                    }
                });
            }
            
            const userData = @json($userRegistrationData ?? []);
            const allUsersData = userData.all || [];
            const landlordsData = userData.landlords || [];
            const agentsData = userData.agents || [];
            const userLabels = userData.labels || [];
            
            let userChart = null;
            
            function initUserChart(type = 'all') {
                const canvas = document.getElementById('userChart');
                if (!canvas) return;
                
                const ctx = canvas.getContext('2d');
                if (userChart) userChart.destroy();
                
                let data, label, borderColor;
                
                switch(type) {
                    case 'landlords':
                        data = landlordsData;
                        label = 'Landlord Registrations';
                        borderColor = '#10b981';
                        break;
                    case 'agents':
                        data = agentsData;
                        label = 'Agent Registrations';
                        borderColor = '#f59e0b';
                        break;
                    default:
                        data = allUsersData;
                        label = 'All User Registrations';
                        borderColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
                }
                
                userChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: userLabels,
                        datasets: [{
                            label: label,
                            data: data,
                            backgroundColor: borderColor + '33',
                            borderColor: borderColor,
                            borderWidth: 2,
                            borderRadius: 8,
                            barPercentage: 0.7,
                            categoryPercentage: 0.8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.raw.toLocaleString() + ' users';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                                    stepSize: 1
                                },
                                grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                            },
                            x: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                                    maxRotation: 45,
                                    minRotation: 45
                                },
                                grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                            }
                        }
                    }
                });
            }
            
            initRevenueChart('monthly');
            initUserChart('all');
            
            document.querySelectorAll('.chart-period-btn[data-chart="revenue"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    initRevenueChart(this.dataset.period);
                    document.querySelectorAll('.chart-period-btn[data-chart="revenue"]').forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = '';
                        b.style.color = '';
                    });
                    this.classList.add('active');
                    this.style.backgroundColor = 'var(--primary)';
                    this.style.color = 'white';
                });
            });
            
            document.querySelectorAll('.chart-period-btn[data-chart="users"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    initUserChart(this.dataset.period);
                    document.querySelectorAll('.chart-period-btn[data-chart="users"]').forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = '';
                        b.style.color = '';
                    });
                    this.classList.add('active');
                    this.style.backgroundColor = 'var(--primary)';
                    this.style.color = 'white';
                });
            });
            
            console.log('✅ Charts initialized successfully');
            
        } catch (error) {
            console.error('❌ Error initializing charts:', error);
        }
        
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('data-tooltip');
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--text-primary);
                    color: var(--card-bg);
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    white-space: nowrap;
                    pointer-events: none;
                `;
                document.body.appendChild(tooltip);
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                this._tooltip = tooltip;
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    this._tooltip = null;
                }
            });
        });
        
        console.log('✅ Admin Dashboard fully initialized');
        
        fetch('/api/user-roles', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('📊 User roles from server:', data);
        })
        .catch(error => {
            console.error('Error fetching user roles:', error);
        });
    });
    
    if (document.readyState !== 'loading') {
        console.log('🔄 DOM already loaded, triggering initialization...');
        const event = new Event('DOMContentLoaded');
        document.dispatchEvent(event);
    }
</script>
@endpush

@php
    // Helper functions for the view
    function getUserTypeName($type) {
        $types = [
            1 => 'Admin',
            2 => 'Landlord',
            3 => 'Tenant',
            4 => 'Field Agent',
            6 => 'Security Checkpoint',
        ];
        return $types[$type] ?? 'User';
    }
@endphp
@endsection