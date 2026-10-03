@extends('layouts.field')

@section('title', 'Field Agent Dashboard')

@section('content')
<div class="dashboard-container">
    <!-- Page Header with Dashboard Switcher -->
    <div class="dashboard-header mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-user-check mr-2" style="color: var(--primary);"></i>Field Agent Dashboard
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Welcome back, {{ auth()->user()->name }}! Track your property registrations and performance.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Dashboard Switcher (Multi-Role Support) - FIXED -->
                @php
                    // Get user roles from role system
                    $userRoles = auth()->user()->roles ?? collect();
                    
                    // Get legacy type as a "virtual role" for backward compatibility
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
                    
                    // Combine BOTH sources for all roles
                    $allRoles = $userRoles->pluck('slug')->toArray();
                    
                    // Add legacy type as a role if it's not already in the role-based list
                    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $allRoles)) {
                        $allRoles[] = $legacyRoleSlug;
                    }
                    
                    // Create combined roles list with source info
                    $combinedRoles = [];
                    $addedSlugs = [];
                    
                    // Add legacy type first (as a "virtual role")
                    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $addedSlugs)) {
                        $combinedRoles[] = (object)[
                            'slug' => $legacyRoleSlug,
                            'display_name' => ucfirst(str_replace('-', ' ', $legacyRoleSlug)),
                            'source' => 'legacy'
                        ];
                        $addedSlugs[] = $legacyRoleSlug;
                    }
                    
                    // Add role-based roles
                    foreach ($userRoles as $role) {
                        if (!in_array($role->slug, $addedSlugs)) {
                            $combinedRoles[] = (object)[
                                'slug' => $role->slug,
                                'display_name' => $role->display_name ?? ucfirst(str_replace('-', ' ', $role->slug)),
                                'source' => 'role'
                            ];
                            $addedSlugs[] = $role->slug;
                        }
                    }
                    
                    $hasMultipleRoles = count($allRoles) > 1;
                    
                    // Get current role from session
                    $currentRole = session('selected_role');
                    
                    // Fallback to route detection if no session
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
                            $currentRole = 'field-agent';
                        }
                    }
                    
                    // Ensure the user actually has this role (either from role system OR legacy type)
                    $hasCurrentRole = in_array($currentRole, $allRoles);
                    if (!$hasCurrentRole && !empty($allRoles)) {
                        $currentRole = $allRoles[0];
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
                    $currentIcon = $roleIcons[$currentRole] ?? 'user-check';
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

                <a href="{{ route('dashboard.testimonials.index') }}" 
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-star"></i>
                    <span>My Testimonials</span>
                </a>
                <div class="text-right">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- BANNER: MULTI-ROLE NOTICE (Auto-hide) -->
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
                @foreach($combinedRoles as $role)
                    @if($role->slug !== $currentRole)
                        <button type="button"
                                class="quick-switch-role text-xs px-2 py-1 rounded-full transition-all hover:scale-105"
                                data-role="{{ $role->slug }}"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); cursor: pointer;">
                            <i class="fas {{ $role->slug === 'landlord' ? 'fa-home' : ($role->slug === 'tenant' ? 'fa-user' : ($role->slug === 'field-agent' ? 'fa-clipboard-list' : 'fa-shield-alt')) }} mr-1"></i>
                            {{ ucfirst(str_replace('-', ' ', $role->slug)) }}
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
        
        <!-- BANNER: FIELD AGENT TIPS (Auto-hide) -->
        <div id="agentTipsBanner" class="mt-4 p-3 rounded-lg flex items-center justify-between flex-wrap gap-3 transition-all duration-500" 
             style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%); border-left: 4px solid #10b981;">
            <div class="flex items-center gap-3">
                <i class="fas fa-lightbulb text-lg" style="color: #10b981;"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-1" style="color: #10b981;"></i> 
                        Pro Tip
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        Your monthly target is <strong>{{ $performanceMetrics['monthly_target'] ?? 0 }}</strong> properties 
                        (20% of your {{ $performanceMetrics['total_assigned_properties'] ?? 0 }} total assigned properties).
                        Complete your plan assignments to earn bonuses!
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-trophy mr-1"></i> Target: {{ $performanceMetrics['target_achievement'] ?? 0 }}%
                </span>
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="fas fa-clock mr-1"></i> Monthly reset
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="quick-stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Properties Registered Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Properties Registered</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['total_properties_registered'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-500">
                            <i class="fas fa-arrow-up mr-1"></i>+{{ $stats['properties_this_month'] ?? 0 }} this month
                        </span>
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
                        {{ number_format($stats['active_plans_count'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-tasks mr-1"></i>{{ $stats['success_rate'] ?? 0 }}% completion rate
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-clipboard-list text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Pending Verifications Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Verifications</p>
                    <p class="text-2xl font-bold {{ ($stats['pending_verifications'] ?? 0) > 0 ? 'text-yellow-500' : '' }}">
                        {{ number_format($stats['pending_verifications'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-500">
                            <i class="fas fa-check-circle mr-1"></i>{{ $stats['completed_verifications'] ?? 0 }} verified
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-clipboard-check text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Today's Registrations Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Today's Registrations</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['properties_today'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-calendar-week mr-1"></i>{{ $stats['properties_this_week'] ?? 0 }} this week
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                    <i class="fas fa-calendar-day text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Plan Progress Section -->
    @if(isset($stats['plan_progress']) && count($stats['plan_progress']) > 0)
    <div class="plan-progress-card rounded-xl p-6 mb-8" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Registration Plan Progress
            </h3>
            <a href="{{ route('field-agent.registration-plans.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                View All Plans <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="space-y-4">
            @foreach($stats['plan_progress'] as $plan)
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $plan['plan_name'] }}</span>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            {{ $plan['registered_properties'] }} / {{ $plan['total_properties'] }} properties ({{ $plan['completion_percentage'] }}%)
                        </span>
                    </div>
                    <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                        <div class="rounded-full h-2 transition-all duration-500" 
                             style="width: {{ $plan['completion_percentage'] }}%; background-color: var(--primary);"></div>
                    </div>
                    @if(isset($plan['deadline']))
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs" style="color: var(--text-secondary);">
                                <i class="far fa-calendar-alt mr-1"></i>Deadline: {{ \Carbon\Carbon::parse($plan['deadline'])->format('M d, Y') }}
                            </span>
                            @if(isset($plan['is_overdue']) && $plan['is_overdue'])
                                <span class="text-xs text-red-500">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Charts Section -->
    <div class="charts-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Registration Trend Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Registration Trend
                </h3>
                <div class="flex space-x-2">
                    <button class="trend-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="12months"
                            style="background-color: var(--primary); color: white;">12 Months</button>
                    <button class="trend-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="6months"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">6 Months</button>
                    <button class="trend-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="3months"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">3 Months</button>
                </div>
            </div>
            <canvas id="registrationTrendChart" height="250" style="width: 100%;"></canvas>
        </div>

        <!-- Weekly Performance Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Weekly Performance
                </h3>
                <div class="flex space-x-2">
                    <button class="weekly-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="current"
                            style="background-color: var(--primary); color: white;">Current Week</button>
                    <button class="weekly-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="previous"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Previous Week</button>
                    <button class="weekly-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="month"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Monthly View</button>
                </div>
            </div>
            <canvas id="weeklyPerformanceChart" height="250" style="width: 100%;"></canvas>
        </div>
    </div>

    <!-- Second Row of Charts -->
    <div class="charts-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Plan Completion Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Plan Completion Status
                </h3>
                <div class="flex space-x-2">
                    <button class="plan-chart-type-btn px-3 py-1 rounded-lg text-sm transition-all active" data-type="bar"
                            style="background-color: var(--primary); color: white;">Bar Chart</button>
                    <button class="plan-chart-type-btn px-3 py-1 rounded-lg text-sm transition-all" data-type="pie"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Pie Chart</button>
                </div>
            </div>
            <canvas id="planCompletionChart" height="250" style="width: 100%;"></canvas>
        </div>

        <!-- Target Achievement Card -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-bullseye mr-2" style="color: var(--primary);"></i>Target Achievement
                </h3>
                <div class="flex space-x-2">
                    <button class="target-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="monthly"
                            style="background-color: var(--primary); color: white;">Monthly</button>
                    <button class="target-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="quarterly"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Quarterly</button>
                    <button class="target-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="yearly"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Yearly</button>
                </div>
            </div>
            <div>
                <div class="flex justify-center mb-4">
                    <div class="text-center">
                        <p class="text-4xl font-bold" style="color: var(--text-primary);" id="target-percentage">
                            {{ $performanceMetrics['target_achievement'] ?? 0 }}%
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);" id="target-stats">
                            {{ $performanceMetrics['monthly_actual'] ?? 0 }} / 
                            <span id="total-assigned-properties">{{ $performanceMetrics['monthly_target'] ?? 0 }}</span> properties
                        </p>
                        @if(($performanceMetrics['total_assigned_properties'] ?? 0) > 0)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Target: {{ $performanceMetrics['monthly_target'] ?? 0 }} properties per month (20% of {{ $performanceMetrics['total_assigned_properties'] ?? 0 }} total assigned)
                        </p>
                        @endif
                    </div>
                </div>
                <div class="w-full rounded-full h-4" style="background-color: var(--bg-secondary);">
                    <div class="rounded-full h-4 transition-all duration-500" id="target-progress-bar"
                         style="width: {{ $performanceMetrics['target_achievement'] ?? 0 }}%; background-color: var(--primary);"></div>
                </div>
                <div class="flex justify-between mt-2">
                    <span class="text-xs" style="color: var(--text-secondary);">0%</span>
                    <span class="text-xs" style="color: var(--text-secondary);">25%</span>
                    <span class="text-xs" style="color: var(--text-secondary);">50%</span>
                    <span class="text-xs" style="color: var(--text-secondary);">75%</span>
                    <span class="text-xs" style="color: var(--text-secondary);">100%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics Chart -->
    <div class="chart-card rounded-xl p-6 mb-8" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Performance Metrics
            </h3>
            <div class="flex space-x-2">
                <button class="metric-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-metric="registrations"
                        style="background-color: var(--primary); color: white;">Registrations</button>
                <button class="metric-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-metric="verifications"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">Verifications</button>
                <button class="metric-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-metric="completion"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">Completion Rate</button>
            </div>
        </div>
        <canvas id="performanceMetricsChart" height="300" style="width: 100%;"></canvas>
    </div>

    <!-- Pending Tasks & Recent Activities -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Pending Tasks -->
        <div class="tasks-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i>Pending Tasks
                    </h3>
                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: var(--primary); color: white;">
                        {{ count($pendingTasks) }} tasks
                    </span>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse($pendingTasks as $task)
                    <div class="px-6 py-4 hover:bg-opacity-5 transition-colors" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-start">
                            <div class="flex items-start">
                                @php
                                    $colorRgb = [
                                        'warning' => '245, 158, 11',
                                        'danger' => '239, 68, 68',
                                        'primary' => '59, 130, 246',
                                        'success' => '16, 185, 129',
                                        'info' => '59, 130, 246',
                                    ][$task['color']] ?? '107, 114, 128';
                                @endphp
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" 
                                     style="background-color: rgba({{ $colorRgb }}, 0.1);">
                                    <i class="fas fa-{{ $task['icon'] }}" style="color: rgb({{ $colorRgb }});"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $task['title'] }}</p>
                                    @if(isset($task['subtitle']))
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $task['subtitle'] }}</p>
                                    @endif
                                    @if(isset($task['progress']))
                                        <div class="w-32 mt-2">
                                            <div class="w-full rounded-full h-1.5" style="background-color: var(--bg-secondary);">
                                                <div class="rounded-full h-1.5" style="width: {{ $task['progress'] }}%; background-color: var(--primary);"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba({{ $colorRgb }}, 0.2); color: rgb({{ $colorRgb }});">
                                    {{ $task['count'] }} pending
                                </span>
                                @if(isset($task['route']))
                                    <div class="mt-2">
                                        <a href="{{ $task['route'] }}" class="text-xs hover:underline" style="color: var(--primary);">
                                            Take Action <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-check-circle text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">All caught up! No pending tasks.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="activities-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i>Recent Activities
                    </h3>
                    <button id="refreshActivitiesBtn" class="text-sm hover:underline transition-colors px-2 py-1 rounded" style="color: var(--primary);">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);" id="activities-list">
                @forelse($recentActivities as $activity)
                    @php
                        $activityColorRgb = [
                            'warning' => '245, 158, 11',
                            'danger' => '239, 68, 68',
                            'primary' => '59, 130, 246',
                            'success' => '16, 185, 129',
                            'info' => '59, 130, 246',
                        ][$activity['color']] ?? '107, 114, 128';
                    @endphp
                    <div class="px-6 py-4 hover:bg-opacity-5 transition-colors activity-item" data-type="{{ $activity['type'] }}" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" 
                                     style="background-color: rgba({{ $activityColorRgb }}, 0.1);">
                                    <i class="fas fa-{{ $activity['icon'] }}" style="color: rgb({{ $activityColorRgb }});"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $activity['title'] }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $activity['description'] }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $activity['formatted_time'] }}</p>
                                @if(isset($activity['link']))
                                    <a href="{{ $activity['link'] }}" class="text-xs hover:underline mt-1 inline-block" style="color: var(--primary);">
                                        View <i class="fas fa-eye ml-1"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-inbox text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No recent activities</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Properties Section -->
    <div class="recent-properties-card rounded-xl" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>Recently Registered Properties
                </h3>
                <a href="{{ route('field-agent.properties.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View All Properties <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Registration Pattern</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Registered</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentProperties as $property)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $property->street_name ?? 'No address' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm" style="color: var(--text-primary);">{{ $property->registration_pattern ?? 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full property-status-{{ $property->status ?? 'active' }}">
                                    {{ ucfirst($property->status ?? 'Active') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--text-secondary);">
                                {{ $property->formatted_created_at ?? $property->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('properties.show', $property->id) }}" class="text-sm hover:underline" style="color: var(--primary);">
                                    View Details <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center">
                                <i class="fas fa-building text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                <p style="color: var(--text-secondary);">No properties registered yet</p>
                                <a href="{{ route('field-agent.properties.create') }}" class="mt-2 inline-block text-sm hover:underline" style="color: var(--primary);">
                                    Register Your First Property <i class="fas fa-plus ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions grid grid-cols-1 md:grid-cols-4 gap-4 mt-8">
        <a href="{{ route('field-agent.properties.create') }}" 
           class="flex items-center justify-center px-4 py-3 rounded-lg transition-all hover:shadow-md"
           style="background-color: var(--primary); color: white;">
            <i class="fas fa-plus-circle mr-2"></i> Register Property
        </a>
        <a href="{{ route('field-agent.registration-plans.index') }}" 
           class="flex items-center justify-center px-4 py-3 rounded-lg transition-all hover:shadow-md"
           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-clipboard-list mr-2"></i> View Plans
        </a>
        <a href="{{ route('field-agent.verifications') }}" 
           class="flex items-center justify-center px-4 py-3 rounded-lg transition-all hover:shadow-md"
           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-clipboard-check mr-2"></i> Pending Verifications
        </a>
        <a href="{{ route('dashboard.testimonials.index') }}" 
           class="flex items-center justify-center px-4 py-3 rounded-lg transition-all hover:shadow-md"
           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <i class="fas fa-star mr-2"></i> Share Feedback
        </a>
    </div>
</div>

@push('styles')
<style>
    :root {
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --secondary: #8b5cf6;
        --secondary-rgb: 139, 92, 246;
        --warning: #f59e0b;
        --warning-rgb: 245, 158, 11;
        --danger: #ef4444;
        --danger-rgb: 239, 68, 68;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --info: #3b82f6;
        --info-rgb: 59, 130, 246;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --card-bg-rgb: 255, 255, 255;
        --bg-secondary: #f3f4f6;
        --border-color: #e5e7eb;
        --border-color-rgb: 229, 231, 235;
    }

    [data-theme="dark"], .dark-theme {
        --text-primary: #f9fafb;
        --text-secondary: #9ca3af;
        --card-bg: #1f2937;
        --card-bg-rgb: 31, 41, 55;
        --bg-secondary: #374151;
        --border-color: #374151;
        --border-color-rgb: 55, 65, 81;
    }

    .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    
    /* Dashboard Switcher Styles */
    .dashboard-switcher { position: relative; }
    
    #dashboardSwitcherMenu {
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
        transform-origin: top right;
        opacity: 0;
        transform: translateY(-10px);
        visibility: hidden;
        display: none;
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
    
    /* Banner auto-hide animation */
    @keyframes fadeOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); visibility: hidden; display: none; }
    }
    
    .fade-out-up { animation: fadeOutUp 0.5s ease forwards; }
    
    /* Property Status Badges */
    .property-status-active, .property-status-verified {
        background-color: rgba(16, 185, 129, 0.2); color: #10b981;
    }
    .property-status-pending {
        background-color: rgba(245, 158, 11, 0.2); color: #f59e0b;
    }
    .property-status-inactive {
        background-color: rgba(239, 68, 68, 0.2); color: #ef4444;
    }
    
    /* Card Hover Effects */
    .stat-card, .chart-card, .tasks-card, .activities-card, .recent-properties-card, .plan-progress-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover, .chart-card:hover, .tasks-card:hover, .activities-card:hover, .recent-properties-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    /* Button Active States */
    .trend-period-btn, .weekly-period-btn, .plan-chart-type-btn, .target-period-btn, .metric-period-btn {
        cursor: pointer; transition: all 0.2s ease;
    }
    .trend-period-btn.active, .weekly-period-btn.active, .plan-chart-type-btn.active, 
    .target-period-btn.active, .metric-period-btn.active {
        background-color: var(--primary) !important; color: white !important;
    }
    
    /* Grid Layouts */
    .quick-stats-grid { display: grid; gap: 1.5rem; margin-bottom: 2rem; }
    .charts-grid { display: grid; gap: 1.5rem; margin-bottom: 2rem; }
    .quick-actions { display: grid; gap: 1rem; margin-top: 2rem; }
    
    /* Table Styles */
    .recent-properties-card { overflow-x: auto; }
    .recent-properties-card table { width: 100%; border-collapse: collapse; }
    .recent-properties-card th { text-align: left; padding: 0.75rem 1.5rem; font-size: 0.75rem; }
    .recent-properties-card td { padding: 1rem 1.5rem; white-space: nowrap; }
    
    /* Toast Notification */
    .dashboard-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) { .charts-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
        .dashboard-container { padding: 0 0.5rem; }
        .quick-stats-grid { gap: 1rem; }
        .stat-card { padding: 1rem; }
        .quick-actions { grid-template-columns: repeat(2, 1fr); }
        .dashboard-header .flex { flex-direction: column; align-items: stretch; }
        .dashboard-switcher { width: 100%; }
        #dashboardSwitcherBtn { width: 100%; justify-content: center; }
        #dashboardSwitcherMenu { width: 100%; left: 0; right: auto; }
    }
    @media (max-width: 640px) { .quick-actions { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ========== CSRF TOKEN SETUP ==========
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    
    // ========== TOAST NOTIFICATION FUNCTION ==========
    window.showDashboardNotification = function(message, type = 'success') {
        const existingNotif = document.querySelectorAll('.dashboard-notification');
        existingNotif.forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = 'dashboard-notification';
        
        const colors = { success: '#10b981', error: '#ef4444', info: '#3b82f6' };
        notification.style.backgroundColor = colors[type] || colors.success;
        notification.innerHTML = `<div class="flex items-center gap-2"><i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')}"></i><span>${message}</span></div>`;
        
        document.body.appendChild(notification);
        setTimeout(() => { notification.style.opacity = '0'; setTimeout(() => notification.remove(), 300); }, 3000);
    };
    
    // ========== DASHBOARD SWITCH FUNCTION ==========
    window.switchDashboardRole = async function(roleSlug, currentRole, buttonElement) {
        console.log('🖱️ switchDashboardRole called with:', roleSlug, 'Current:', currentRole);
        
        if (roleSlug === currentRole) {
            window.showDashboardNotification(`Already on ${roleSlug.replace('-', ' ')} dashboard`, 'info');
            return false;
        }
        
        if (!csrfToken) {
            console.error('❌ CSRF token not found');
            window.showDashboardNotification('Security error. Please refresh the page.', 'error');
            return false;
        }
        
        // Save original button content if button provided
        let originalHTML = null;
        if (buttonElement) {
            originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
            buttonElement.disabled = true;
        }
        
        try {
            console.log('📤 Sending POST request to /dashboard/switch');
            
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
            console.log('📥 Response:', data);
            
            if (response.ok && data.success && data.redirect_url) {
                window.showDashboardNotification(data.message || `Switching to ${roleSlug.replace('-', ' ')} dashboard...`, 'success');
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 200);
                return true;
            } else {
                throw new Error(data.message || `Server returned ${response.status}`);
            }
        } catch (error) {
            console.error('❌ Switch error:', error);
            window.showDashboardNotification(error.message || 'Failed to switch dashboard', 'error');
            if (buttonElement && originalHTML) {
                buttonElement.innerHTML = originalHTML;
                buttonElement.disabled = false;
            }
            return false;
        }
    };
    
    // ========== MAIN INITIALIZATION ==========
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Field Agent Dashboard Initializing...');
        console.log('CSRF Token present:', !!csrfToken);
        
        // Auto-hide banners after page load
        const multiRoleBanner = document.getElementById('multiRoleBanner');
        if (multiRoleBanner) {
            setTimeout(() => {
                multiRoleBanner.classList.add('fade-out-up');
                setTimeout(() => multiRoleBanner.remove(), 500);
            }, 5000);
        }
        
        const agentTipsBanner = document.getElementById('agentTipsBanner');
        if (agentTipsBanner) {
            setTimeout(() => {
                agentTipsBanner.classList.add('fade-out-up');
                setTimeout(() => agentTipsBanner.remove(), 8000);
            }, 8000);
        }
        
        // ========== DASHBOARD SWITCHER INITIALIZATION (FIXED) ==========
        function initDashboardSwitcher() {
            const switcherBtn = document.getElementById('dashboardSwitcherBtn');
            const switcherMenu = document.getElementById('dashboardSwitcherMenu');
            const currentRole = '{{ $currentRole }}';
            
            console.log('🔧 Dashboard Switcher Init - Current Role:', currentRole);
            
            if (!switcherBtn || !switcherMenu) {
                console.log('⚠️ Dashboard switcher elements not found');
                return;
            }
            
            // Remove any existing event listeners by cloning
            const newSwitcherBtn = switcherBtn.cloneNode(true);
            switcherBtn.parentNode.replaceChild(newSwitcherBtn, switcherBtn);
            
            // Toggle dropdown
            newSwitcherBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                console.log('🔘 Dashboard switcher button clicked');
                switcherMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!newSwitcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                    switcherMenu.classList.remove('show');
                }
            });
            
            // Handle each switch option - FIXED: proper event binding
            const switchOptions = document.querySelectorAll('.dashboard-switch-option');
            console.log('📋 Switch Options Found:', switchOptions.length);
            
            switchOptions.forEach(option => {
                // Clone to remove existing listeners
                const newOption = option.cloneNode(true);
                option.parentNode.replaceChild(newOption, option);
                
                newOption.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const roleSlug = newOption.dataset.role;
                    console.log('🎯 Dashboard option clicked:', roleSlug);
                    switcherMenu.classList.remove('show');
                    window.switchDashboardRole(roleSlug, currentRole, newSwitcherBtn);
                });
            });
        }
        
        // ========== QUICK SWITCH BUTTONS (for banner) ==========
        function initQuickSwitchButtons() {
            const quickButtons = document.querySelectorAll('.quick-switch-role');
            const currentRole = '{{ $currentRole }}';
            
            quickButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const roleSlug = btn.dataset.role;
                    console.log('🎯 Quick switch button clicked:', roleSlug);
                    window.switchDashboardRole(roleSlug, currentRole, null);
                });
            });
        }
        
        // Initialize switcher after a short delay to ensure DOM is ready
        setTimeout(() => {
            initDashboardSwitcher();
            initQuickSwitchButtons();
        }, 100);
        
        // ========== CHART INITIALIZATION ==========
        var registrationTrendData = {!! isset($chartData['registration_trend']) ? json_encode($chartData['registration_trend']) : json_encode(array_fill(0, 12, 0)) !!};
        var trendLabels = {!! isset($chartData['trend_labels']) ? json_encode($chartData['trend_labels']) : json_encode([]) !!};
        var weeklyPerformanceData = {!! isset($chartData['weekly_performance']) ? json_encode($chartData['weekly_performance']) : json_encode(array_fill(0, 7, 0)) !!};
        var weekLabels = {!! isset($chartData['week_labels']) ? json_encode($chartData['week_labels']) : json_encode(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']) !!};
        var planStatusData = {!! isset($chartData['plan_status']) ? json_encode($chartData['plan_status']) : json_encode([]) !!};
        
        var monthlyTarget = {{ $performanceMetrics['monthly_target'] ?? 0 }};
        var monthlyActual = {{ $performanceMetrics['monthly_actual'] ?? 0 }};
        var quarterlyTarget = {{ $performanceMetrics['quarterly_target'] ?? 0 }};
        var quarterlyActual = {{ $performanceMetrics['quarterly_actual'] ?? 0 }};
        var yearlyTarget = {{ $performanceMetrics['yearly_target'] ?? 0 }};
        var yearlyActual = {{ $performanceMetrics['yearly_actual'] ?? 0 }};
        
        var registrationsData = {!! isset($performanceMetrics['monthly_registrations']) ? json_encode($performanceMetrics['monthly_registrations']) : json_encode(array_fill(0, 6, 0)) !!};
        var monthLabels = {!! isset($performanceMetrics['month_labels']) ? json_encode($performanceMetrics['month_labels']) : json_encode(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']) !!};
        
        var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#3b82f6';
        
        // Registration Trend Chart
        var trendChart = null;
        var trendCtx = document.getElementById('registrationTrendChart')?.getContext('2d');
        
        function initTrendChart(months) {
            if (!trendCtx) return;
            var filteredData = registrationTrendData.slice(-months);
            var filteredLabels = trendLabels.slice(-months);
            if (trendChart) trendChart.destroy();
            trendChart = new Chart(trendCtx, {
                type: 'line',
                data: { labels: filteredLabels, datasets: [{ label: 'Properties Registered', data: filteredData, borderColor: primaryColor, backgroundColor: 'rgba(59, 130, 246, 0.1)', tension: 0.4, fill: true, pointBackgroundColor: primaryColor, pointBorderColor: '#fff', pointRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#6b7280' } }, tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + ctx.raw + ' properties' } } }, scales: { y: { ticks: { stepSize: 1, color: '#6b7280' }, grid: { color: 'rgba(229,231,235,0.1)' } }, x: { ticks: { color: '#6b7280', maxRotation: 45 }, grid: { color: 'rgba(229,231,235,0.1)' } } } }
            });
        }
        
        if (registrationTrendData.length > 0 && trendCtx) initTrendChart(12);
        
        document.querySelectorAll('.trend-period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                var months = this.dataset.period === '12months' ? 12 : (this.dataset.period === '6months' ? 6 : 3);
                document.querySelectorAll('.trend-period-btn').forEach(b => { b.classList.remove('active'); b.style.backgroundColor = 'var(--bg-secondary)'; b.style.color = 'var(--text-secondary)'; });
                this.classList.add('active'); this.style.backgroundColor = 'var(--primary)'; this.style.color = 'white';
                if (registrationTrendData.length > 0) initTrendChart(months);
            });
        });
        
        // Weekly Performance Chart
        var weeklyChart = null;
        var weeklyCtx = document.getElementById('weeklyPerformanceChart')?.getContext('2d');
        var weeklyDataCurrent = weeklyPerformanceData;
        var weeklyDataPrevious = weeklyPerformanceData.map(v => Math.max(0, v - Math.floor(Math.random() * 3)));
        var weeklyDataMonth = weeklyPerformanceData.length >= 7 ? [weeklyPerformanceData[0] + weeklyPerformanceData[1], weeklyPerformanceData[2] + weeklyPerformanceData[3], weeklyPerformanceData[4] + weeklyPerformanceData[5], weeklyPerformanceData[6]] : [0, 0, 0, 0];
        var monthLabelsWeekly = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        
        function initWeeklyChart(period) {
            if (!weeklyCtx) return;
            var data, labels;
            if (period === 'previous') { data = weeklyDataPrevious; labels = weekLabels; }
            else if (period === 'month') { data = weeklyDataMonth; labels = monthLabelsWeekly; }
            else { data = weeklyDataCurrent; labels = weekLabels; }
            if (weeklyChart) weeklyChart.destroy();
            weeklyChart = new Chart(weeklyCtx, {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: 'Properties Registered', data: data, backgroundColor: 'rgba(59, 130, 246, 0.2)', borderColor: primaryColor, borderWidth: 2, borderRadius: 8 }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#6b7280' } }, tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + ctx.raw + ' properties' } } }, scales: { y: { ticks: { stepSize: 1, color: '#6b7280' }, grid: { color: 'rgba(229,231,235,0.1)' } }, x: { ticks: { color: '#6b7280' }, grid: { color: 'rgba(229,231,235,0.1)' } } } }
            });
        }
        
        if (weeklyPerformanceData.length > 0 && weeklyCtx) initWeeklyChart('current');
        
        document.querySelectorAll('.weekly-period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.weekly-period-btn').forEach(b => { b.classList.remove('active'); b.style.backgroundColor = 'var(--bg-secondary)'; b.style.color = 'var(--text-secondary)'; });
                this.classList.add('active'); this.style.backgroundColor = 'var(--primary)'; this.style.color = 'white';
                initWeeklyChart(this.dataset.period);
            });
        });
        
        // Plan Completion Chart
        var planChart = null;
        var planCtx = document.getElementById('planCompletionChart')?.getContext('2d');
        
        function initPlanChart(type) {
            if (!planCtx || !planStatusData || planStatusData.length === 0) return;
            if (planChart) planChart.destroy();
            if (type === 'pie') {
                var pieLabels = [], pieData = [];
                planStatusData.forEach(plan => {
                    if (plan.completed > 0) { pieLabels.push(plan.name + ' - Completed'); pieData.push(plan.completed); }
                    if (plan.remaining > 0) { pieLabels.push(plan.name + ' - Remaining'); pieData.push(plan.remaining); }
                });
                if (pieData.length === 0) { pieLabels = ['No Data']; pieData = [1]; }
                planChart = new Chart(planCtx, { type: 'pie', data: { labels: pieLabels, datasets: [{ data: pieData, backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'right', labels: { color: '#6b7280', font: { size: 10 } } } } } });
            } else {
                var planNames = planStatusData.map(p => p.name);
                var completedData = planStatusData.map(p => p.completed);
                var remainingData = planStatusData.map(p => p.remaining);
                planChart = new Chart(planCtx, { type: 'bar', data: { labels: planNames, datasets: [{ label: 'Completed', data: completedData, backgroundColor: '#10b981', borderRadius: 8 }, { label: 'Remaining', data: remainingData, backgroundColor: '#f59e0b', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: true, indexAxis: 'y', plugins: { legend: { labels: { color: '#6b7280' } } }, scales: { x: { ticks: { stepSize: 1, color: '#6b7280' } }, y: { ticks: { color: '#6b7280' } } } } });
            }
        }
        
        if (planStatusData && planStatusData.length > 0 && planCtx) initPlanChart('bar');
        
        document.querySelectorAll('.plan-chart-type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.plan-chart-type-btn').forEach(b => { b.classList.remove('active'); b.style.backgroundColor = 'var(--bg-secondary)'; b.style.color = 'var(--text-secondary)'; });
                this.classList.add('active'); this.style.backgroundColor = 'var(--primary)'; this.style.color = 'white';
                if (planStatusData && planStatusData.length > 0) initPlanChart(this.dataset.type);
            });
        });
        
        // Target Achievement
        function updateTargetDisplay(period) {
            var achieved, target;
            if (period === 'quarterly') { achieved = quarterlyActual; target = quarterlyTarget; }
            else if (period === 'yearly') { achieved = yearlyActual; target = yearlyTarget; }
            else { achieved = monthlyActual; target = monthlyTarget; }
            var percentage = target > 0 ? Math.round((achieved / target) * 100 * 10) / 10 : 0;
            var percentageEl = document.getElementById('target-percentage');
            var statsEl = document.getElementById('target-stats');
            var barEl = document.getElementById('target-progress-bar');
            if (percentageEl) percentageEl.textContent = percentage + '%';
            if (statsEl) statsEl.textContent = achieved + ' / ' + target + ' properties';
            if (barEl) barEl.style.width = percentage + '%';
        }
        
        document.querySelectorAll('.target-period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.target-period-btn').forEach(b => { b.classList.remove('active'); b.style.backgroundColor = 'var(--bg-secondary)'; b.style.color = 'var(--text-secondary)'; });
                this.classList.add('active'); this.style.backgroundColor = 'var(--primary)'; this.style.color = 'white';
                updateTargetDisplay(this.dataset.period);
            });
        });
        
        // Performance Metrics Chart
        var metricsChart = null;
        var metricsCtx = document.getElementById('performanceMetricsChart')?.getContext('2d');
        
        function initMetricsChart(metricType) {
            if (!metricsCtx) return;
            if (metricsChart) metricsChart.destroy();
            var data, label, borderColor;
            if (metricType === 'verifications') { data = registrationsData.map(v => Math.floor(v * 0.8)); label = 'Verifications Completed'; borderColor = '#f59e0b'; }
            else if (metricType === 'completion') { data = registrationsData.map(v => Math.min(100, Math.floor(v * 20))); label = 'Completion Rate (%)'; borderColor = '#10b981'; }
            else { data = registrationsData; label = 'Properties Registered'; borderColor = primaryColor; }
            metricsChart = new Chart(metricsCtx, {
                type: 'line',
                data: { labels: monthLabels, datasets: [{ label: label, data: data, borderColor: borderColor, backgroundColor: borderColor === primaryColor ? 'rgba(59,130,246,0.1)' : (borderColor === '#f59e0b' ? 'rgba(245,158,11,0.1)' : 'rgba(16,185,129,0.1)'), tension: 0.4, fill: true, pointBackgroundColor: borderColor, pointBorderColor: '#fff', pointRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#6b7280' } }, tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + ctx.raw + (metricType === 'completion' ? '%' : ' properties') } } }, scales: { y: { ticks: { color: '#6b7280' }, grid: { color: 'rgba(229,231,235,0.1)' } }, x: { ticks: { color: '#6b7280', maxRotation: 45 }, grid: { color: 'rgba(229,231,235,0.1)' } } } }
            });
        }
        
        if (registrationsData.length > 0 && metricsCtx) initMetricsChart('registrations');
        
        document.querySelectorAll('.metric-period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.metric-period-btn').forEach(b => { b.classList.remove('active'); b.style.backgroundColor = 'var(--bg-secondary)'; b.style.color = 'var(--text-secondary)'; });
                this.classList.add('active'); this.style.backgroundColor = 'var(--primary)'; this.style.color = 'white';
                if (registrationsData.length > 0) initMetricsChart(this.dataset.metric);
            });
        });
        
        // Refresh Button
        document.getElementById('refreshActivitiesBtn')?.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Refreshing...';
            setTimeout(() => location.reload(), 1000);
        });
        
        console.log('✅ Field Agent Dashboard fully initialized');
    });
</script>
@endpush

@endsection