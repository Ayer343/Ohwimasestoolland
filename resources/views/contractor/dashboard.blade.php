@extends('layouts.contract')

@section('title', 'Contractor Dashboard')

@section('content')
<div class="dashboard-container">
    <!-- Page Header with Dashboard Switcher -->
    <div class="dashboard-header mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-hard-hat mr-2" style="color: var(--primary);"></i>Contractor Dashboard
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Welcome back, {{ auth()->user()->name }}! Manage your construction projects and contracts.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Dashboard Switcher (Multi-Role Support) -->
                @php
                    // ==================== DUAL MODE ROLE DETECTION ====================
                    $roleBasedRoles = auth()->user()->roles ?? collect();
                    $roleBasedRoleSlugs = $roleBasedRoles->pluck('slug')->toArray();
                    
                    $legacyType = auth()->user()->type;
                    $legacyTypeRoleMap = [
                        0 => 'super-admin',
                        1 => 'admin',
                        2 => 'landlord',
                        3 => 'tenant',
                        4 => 'field-agent',
                        5 => 'developer',
                        6 => 'security-personnel',
                        8 => 'contractor',
                    ];
                    $legacyRoleSlug = $legacyTypeRoleMap[$legacyType] ?? null;
                    
                    $allRoles = $roleBasedRoleSlugs;
                    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $allRoles)) {
                        $allRoles[] = $legacyRoleSlug;
                    }
                    
                    $hasMultipleRoles = count($allRoles) > 1;
                    
                    $currentRole = session('selected_role');
                    if (!$currentRole) {
                        $currentRole = $legacyRoleSlug ?? 'contractor';
                    }
                    
                    if (!in_array($currentRole, $allRoles)) {
                        $currentRole = $allRoles[0] ?? 'contractor';
                    }
                    
                    $roleIcons = [
                        'super-admin' => 'crown',
                        'admin' => 'shield-alt',
                        'landlord' => 'home',
                        'tenant' => 'user',
                        'field-agent' => 'clipboard-list',
                        'security-personnel' => 'shield-alt',
                        'developer' => 'code',
                        'contractor' => 'hard-hat',
                    ];
                    $currentIcon = $roleIcons[$currentRole] ?? 'hard-hat';
                    
                    $roleDescriptions = [
                        'super-admin' => 'Full system control',
                        'admin' => 'System management',
                        'landlord' => 'Property portfolio management',
                        'tenant' => 'Rental management',
                        'field-agent' => 'Property registration',
                        'security-personnel' => 'Security operations',
                        'developer' => 'System development',
                        'contractor' => 'Construction project management',
                    ];
                    
                    $combinedRoles = [];
                    $addedSlugs = [];
                    
                    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $addedSlugs)) {
                        $combinedRoles[] = (object)[
                            'slug' => $legacyRoleSlug,
                            'display_name' => ucfirst(str_replace('-', ' ', $legacyRoleSlug)),
                            'source' => 'legacy',
                            'description' => $roleDescriptions[$legacyRoleSlug] ?? 'Dashboard access'
                        ];
                        $addedSlugs[] = $legacyRoleSlug;
                    }
                    
                    foreach ($roleBasedRoles as $role) {
                        if (!in_array($role->slug, $addedSlugs)) {
                            $combinedRoles[] = (object)[
                                'slug' => $role->slug,
                                'display_name' => $role->display_name ?? ucfirst(str_replace('-', ' ', $role->slug)),
                                'source' => 'role',
                                'description' => $roleDescriptions[$role->slug] ?? 'Dashboard access'
                            ];
                            $addedSlugs[] = $role->slug;
                        }
                    }
                    
                    usort($combinedRoles, function($a, $b) {
                        $order = ['super-admin' => 0, 'admin' => 1, 'contractor' => 2];
                        $orderA = $order[$a->slug] ?? 99;
                        $orderB = $order[$b->slug] ?? 99;
                        if ($orderA === $orderB) return strcmp($a->slug, $b->slug);
                        return $orderA - $orderB;
                    });
                @endphp
                
                @if($hasMultipleRoles)
                <div class="dashboard-switcher relative">
                    <button id="dashboardSwitcherBtn" 
                            class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%); color: white;">
                        <i class="fas fa-{{ $currentIcon }} mr-1"></i>
                        <span class="text-sm font-medium">{{ ucfirst(str_replace('-', ' ', $currentRole)) }}</span>
                        <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </button>
                    
                    <div id="dashboardSwitcherMenu" 
                         class="absolute right-0 mt-2 w-80 rounded-xl shadow-lg overflow-hidden z-50 hidden"
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="py-2">
                            <div class="px-4 py-2 border-b" style="border-color: var(--border-color);">
                                <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">
                                    <i class="fas fa-layer-group mr-1"></i> Switch Dashboard
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.7;">
                                    You have {{ count($combinedRoles) }} dashboard{{ count($combinedRoles) > 1 ? 's' : '' }} available
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
                                        data-current-role="{{ $currentRole }}"
                                        style="display: flex; color: var(--text-primary); {{ $isActive ? 'background-color: rgba(var(--primary-rgb), 0.1); border-left: 3px solid var(--primary);' : '' }}">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center" 
                                         style="background: {{ $isActive ? 'linear-gradient(135deg, var(--primary) 0%, var(--info) 100%)' : 'rgba(var(--primary-rgb), 0.1)' }}">
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
                
                <div class="text-right">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Multi-Role Notice -->
        @if($hasMultipleRoles)
        <div id="multiRoleBanner" class="mt-4 p-3 rounded-lg flex items-center justify-between flex-wrap gap-3 transition-all duration-500" 
             style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--info-rgb), 0.05) 100%); border: 1px solid rgba(var(--primary-rgb), 0.2);">
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
                @foreach($combinedRoles as $role)
                    @if($role->slug !== $currentRole)
                        <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas {{ $role->slug === 'contractor' ? 'fa-hard-hat' : ($role->slug === 'landlord' ? 'fa-home' : ($role->slug === 'tenant' ? 'fa-user' : ($role->slug === 'field-agent' ? 'fa-clipboard-list' : ($role->slug === 'security-personnel' ? 'fa-shield-alt' : 'fa-user')))) }} mr-1"></i>
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
    </div>

    <!-- Quick Stats Cards -->
    <div class="quick-stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Contracts Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Contracts</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['total'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $contractTrend = $quickStats['contract_trend'] ?? 0;
                        @endphp
                        @if($contractTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $contractTrend }}%</span>
                        @elseif($contractTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $contractTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);">
                    <i class="fas fa-file-signature text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Contracts Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active Contracts</p>
                    <p class="text-2xl font-bold text-green-500">
                        {{ number_format($stats['active'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $activeTrend = $quickStats['active_trend'] ?? 0;
                        @endphp
                        @if($activeTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $activeTrend }}%</span>
                        @elseif($activeTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $activeTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-play-circle text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Contract Value Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Contract Value</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @if($currencyPosition === 'right')
                            {{ number_format($totalContractValue ?? 0, 2) }}{{ $currencySymbol }}
                        @elseif($currencyPosition === 'left_with_space')
                            {{ $currencySymbol }} {{ number_format($totalContractValue ?? 0, 2) }}
                        @elseif($currencyPosition === 'right_with_space')
                            {{ number_format($totalContractValue ?? 0, 2) }} {{ $currencySymbol }}
                        @else
                            {{ $currencySymbol }}{{ number_format($totalContractValue ?? 0, 2) }}
                        @endif
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $valueTrend = $quickStats['value_trend'] ?? 0;
                        @endphp
                        @if($valueTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $valueTrend }}%</span>
                        @elseif($valueTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $valueTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-dollar-sign text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Milestone Completion Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Milestone Completion</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $milestoneCompletionRate ?? 0 }}%
                    </p>
                    <div class="flex items-center mt-2">
                        @php
                            $milestoneTrend = $quickStats['milestone_trend'] ?? 0;
                        @endphp
                        @if($milestoneTrend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $milestoneTrend }}%</span>
                        @elseif($milestoneTrend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $milestoneTrend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-flag-checkered text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Cards -->
    <div class="quick-nav-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('contractor.contracts.index') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);">
                <i class="fas fa-file-signature text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Contracts</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">View all contracts</p>
        </a>
        
        <a href="{{ route('contractor.contracts.index', ['status' => 'in_progress']) }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                <i class="fas fa-tasks text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Active Projects</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">In progress work</p>
        </a>
        
        <a href="{{ route('contractor.projects.completed') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check-double text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Completed</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">Finished projects</p>
        </a>
        
        <a href="{{ route('contractor.calendar') }}" 
           class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
           style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                 style="background: linear-gradient(135deg, #ec489a 0%, #db2777 100%);">
                <i class="fas fa-calendar-alt text-white text-lg"></i>
            </div>
            <p class="text-sm font-medium" style="color: var(--text-primary);">Calendar</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">View schedule</p>
        </a>
    </div>

    <!-- Performance Metrics -->
    <div class="performance-card rounded-xl p-6 mb-8" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Performance Metrics
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="text-2xl font-bold" style="color: var(--text-primary);">
                    {{ $performanceMetrics['performance_score'] ?? 0 }}%
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Performance Score</div>
                <div class="w-full h-1.5 bg-gray-200 rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full" 
                         style="width: {{ $performanceMetrics['performance_score'] ?? 0 }}%; 
                                background: linear-gradient(to right, var(--primary), var(--success));"></div>
                </div>
            </div>
            <div class="text-center p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="text-2xl font-bold text-green-500">{{ $performanceMetrics['completion_rate'] ?? 0 }}%</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completion Rate</div>
            </div>
            <div class="text-center p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="text-2xl font-bold text-blue-500">{{ $performanceMetrics['on_time_rate'] ?? 0 }}%</div>
                <div class="text-xs" style="color: var(--text-secondary);">On-Time Delivery</div>
            </div>
            <div class="text-center p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="text-2xl font-bold text-purple-500">{{ $performanceMetrics['milestone_completion_rate'] ?? 0 }}%</div>
                <div class="text-xs" style="color: var(--text-secondary);">Milestone Rate</div>
            </div>
            <div class="text-center p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="text-2xl font-bold text-orange-500">{{ $performanceMetrics['avg_completion_time'] ?? 0 }} days</div>
                <div class="text-xs" style="color: var(--text-secondary);">Avg. Completion Time</div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Contract Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Contract Overview
                </h3>
                <div class="flex space-x-2">
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-chart="contracts" data-period="monthly"
                            style="background-color: var(--primary); color: white;">Monthly</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="contracts" data-period="daily"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Daily</button>
                </div>
            </div>
            <canvas id="contractChart" height="250"></canvas>
        </div>

        <!-- Milestone Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-flag-checkered mr-2" style="color: var(--info);"></i>Milestone Progress
                </h3>
                <div class="flex space-x-2">
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-chart="milestones" data-period="all"
                            style="background-color: var(--info); color: white;">All</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="milestones" data-period="pending"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Pending</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="milestones" data-period="completed"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Completed</button>
                </div>
            </div>
            <canvas id="milestoneChart" height="250"></canvas>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="recent-activity-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Contracts -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i>Recent Contracts
                    </h3>
                    <a href="{{ route('contractor.contracts.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse($recentContracts ?? [] as $contract)
                    <a href="{{ route('contractor.contracts.show', $contract) }}" 
                       class="px-6 py-4 hover:bg-opacity-5 transition-colors block" style="background-color: var(--card-bg);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $contract->title }}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-home mr-1"></i>{{ $contract->property->property_name ?? 'N/A' }}
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-user mr-1"></i>{{ $contract->landlord->name ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded-full
                                    @if($contract->status === 'completed') status-badge-completed
                                    @elseif($contract->status === 'in_progress' || $contract->status === 'approved') status-badge-active
                                    @elseif($contract->status === 'pending_approval') status-badge-pending
                                    @elseif($contract->status === 'on_hold') status-badge-suspended
                                    @else status-badge-inactive
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $contract->status)) }}
                                </span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    @if($currencyPosition === 'right')
                                        {{ number_format($contract->contract_amount, 2) }}{{ $currencySymbol }}
                                    @elseif($currencyPosition === 'left_with_space')
                                        {{ $currencySymbol }} {{ number_format($contract->contract_amount, 2) }}
                                    @elseif($currencyPosition === 'right_with_space')
                                        {{ number_format($contract->contract_amount, 2) }} {{ $currencySymbol }}
                                    @else
                                        {{ $currencySymbol }}{{ number_format($contract->contract_amount, 2) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-file-contract text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No contracts yet</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Milestones -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-flag-checkered mr-2" style="color: var(--info);"></i>Upcoming Milestones
                    </h3>
                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        Next 7 Days
                    </span>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @forelse($upcomingMilestones ?? [] as $milestone)
                    <div class="px-6 py-4" style="background-color: var(--card-bg);">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 mt-1">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                                     style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $loop->iteration }}
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-sm" style="color: var(--text-primary);">
                                    {{ $milestone->title }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $milestone->contract->title ?? 'N/A' }}
                                </p>
                                <div class="flex items-center mt-1 space-x-2">
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        {{ Carbon\Carbon::parse($milestone->due_date)->format('M d, Y') }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        @if($milestone->due_date < now()) bg-red-100 text-red-600
                                        @elseif($milestone->due_date->diffInDays(now()) <= 3) bg-yellow-100 text-yellow-600
                                        @else bg-green-100 text-green-600
                                        @endif">
                                        {{ $milestone->due_date->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="text-xs px-2 py-1 rounded-full
                                    @if($milestone->status === 'completed') status-badge-completed
                                    @elseif($milestone->status === 'in_progress') status-badge-active
                                    @elseif($milestone->status === 'pending') status-badge-pending
                                    @else status-badge-suspended
                                    @endif">
                                    {{ ucfirst($milestone->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-calendar-check text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No upcoming milestones</p>
                    </div>
                @endforelse
            </div>
            @if(isset($stats['overdue']) && $stats['overdue'] > 0)
            <div class="px-6 py-3 border-t" style="border-color: var(--border-color); background-color: var(--bg-secondary); border-radius: 0 0 0.75rem 0.75rem;">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span class="text-sm text-red-600">
                        {{ $stats['overdue'] }} overdue contract{{ $stats['overdue'] > 1 ? 's' : '' }}
                    </span>
                    <a href="{{ route('contractor.contracts.index', ['status' => 'overdue']) }}" 
                       class="text-sm hover:underline ml-auto" style="color: var(--primary);">
                        View <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Recent Activity Feed -->
    <div class="activity-card rounded-xl p-6" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-clock mr-2" style="color: var(--info);"></i>Recent Activity
        </h3>
        
        @if(isset($recentActivity) && $recentActivity->isNotEmpty())
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($recentActivity as $activity)
                    <div class="flex items-start space-x-3 p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex-shrink-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center
                                @if($activity->color === 'blue') bg-blue-100 text-blue-500
                                @elseif($activity->color === 'green') bg-green-100 text-green-500
                                @elseif($activity->color === 'purple') bg-purple-100 text-purple-500
                                @else bg-gray-100 text-gray-500
                                @endif">
                                <i class="fas {{ $activity->icon }}"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm" style="color: var(--text-primary);">
                                {{ $activity->description }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <span class="font-medium">{{ $activity->contract_number }}</span>
                                <span class="mx-1">•</span>
                                {{ $activity->time_ago }}
                                @if(isset($activity->user_name))
                                    <span class="mx-1">•</span>
                                    by {{ $activity->user_name }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8" style="color: var(--text-secondary);">
                <i class="fas fa-inbox text-4xl mb-3 opacity-30"></i>
                <p>No recent activity to display</p>
            </div>
        @endif
    </div>
</div>

<!-- Currency Display Helper -->
@php
    // Helper function to format currency
    function formatCurrency($amount, $symbol, $position)
    {
        if ($position === 'right') {
            return number_format($amount, 2) . $symbol;
        } elseif ($position === 'left_with_space') {
            return $symbol . ' ' . number_format($amount, 2);
        } elseif ($position === 'right_with_space') {
            return number_format($amount, 2) . ' ' . $symbol;
        } else {
            return $symbol . number_format($amount, 2);
        }
    }
@endphp

@push('styles')
<style>
    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
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
    
    /* Multi-Role Alert Styles */
    .multi-role-alert {
        animation: slideInDown 0.3s ease;
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
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

    /* Status Badge Colors */
    .status-badge-completed, .status-badge-paid {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .status-badge-active {
        background-color: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
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

    /* Stat Cards Hover Effect */
    .stat-card, .performance-card, .recent-card, .chart-card, .activity-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover, .performance-card:hover, .recent-card:hover, .chart-card:hover, .activity-card:hover {
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

    /* Activity Feed Scrollbar */
    .max-h-96 {
        max-height: 24rem;
    }
    .max-h-96::-webkit-scrollbar {
        width: 4px;
    }
    .max-h-96::-webkit-scrollbar-track {
        background: transparent;
    }
    .max-h-96::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 2px;
    }
    .max-h-96::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0 0.5rem;
        }
        .quick-stats-grid {
            gap: 1rem;
        }
        .stat-card {
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
        .multi-role-alert .flex-wrap {
            flex-direction: column;
            text-align: center;
        }
    }
</style>
@endpush


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ========== GLOBAL FUNCTIONS ==========
    
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
    
    // Global dashboard switch function
    window.switchDashboardRole = async function(roleSlug, currentRole, buttonElement) {
        if (roleSlug === currentRole) {
            window.showDashboardNotification(`Already on ${roleSlug.replace('-', ' ')} dashboard`, 'info');
            return false;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            window.showDashboardNotification('Security error. Please refresh the page.', 'error');
            return false;
        }
        
        let originalHTML = '';
        if (buttonElement) {
            originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
            buttonElement.disabled = true;
            buttonElement.style.opacity = '0.7';
        }
        
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
            
            if (response.ok && data.success && data.redirect_url) {
                window.showDashboardNotification(data.message || `Switching to ${roleSlug.replace('-', ' ')} dashboard...`, 'success');
                setTimeout(function() {
                    window.location.replace(data.redirect_url);
                }, 200);
                return true;
            } else {
                throw new Error(data.message || `Server returned ${response.status}`);
            }
        } catch (error) {
            window.showDashboardNotification(error.message || 'Failed to switch dashboard', 'error');
            if (buttonElement && originalHTML) {
                buttonElement.innerHTML = originalHTML;
                buttonElement.disabled = false;
                buttonElement.style.opacity = '1';
            }
            return false;
        }
    };
    
    // ========== MAIN INITIALIZATION ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide multi-role banner after 5 seconds
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
            
            let currentRole = '{{ $currentRole ?? "contractor" }}';
            
            if (!switcherBtn || !switcherMenu) {
                console.warn('Dashboard switcher elements not found');
                return;
            }
            
            const newSwitcherBtn = switcherBtn.cloneNode(true);
            switcherBtn.parentNode.replaceChild(newSwitcherBtn, switcherBtn);
            
            newSwitcherBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
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
            switchOptions.forEach(option => {
                const newOption = option.cloneNode(true);
                option.parentNode.replaceChild(newOption, option);
                
                newOption.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const roleSlug = this.dataset.role;
                    switcherMenu.classList.remove('show');
                    window.switchDashboardRole(roleSlug, currentRole, newSwitcherBtn);
                });
            });
        }
        
        setTimeout(initDashboardSwitcher, 100);
        
        // ========== CHART INITIALIZATION ==========
        try {
            // Contract Chart Data
            const contractData = @json($chartData['monthly_totals'] ?? []);
            const contractLabels = @json($chartData['monthly_labels'] ?? []);
            const contractCompleted = @json($chartData['monthly_completed'] ?? []);
            
            let contractChart = null;
            
            function initContractChart() {
                const canvas = document.getElementById('contractChart');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (contractChart) contractChart.destroy();
                
                contractChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: contractLabels,
                        datasets: [
                            {
                                label: 'Total Contracts',
                                data: contractData,
                                backgroundColor: 'rgba(var(--primary-rgb), 0.4)',
                                borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
                                borderWidth: 2,
                                borderRadius: 8,
                                order: 2
                            },
                            {
                                label: 'Completed',
                                data: contractCompleted,
                                backgroundColor: 'rgba(16, 185, 129, 0.4)',
                                borderColor: '#10b981',
                                borderWidth: 2,
                                borderRadius: 8,
                                order: 1
                            }
                        ]
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
                                        return context.dataset.label + ': ' + context.raw.toLocaleString();
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
            
            // Milestone Chart Data
            const milestoneData = @json($chartData['milestone_data'] ?? []);
            const milestoneLabels = @json($chartData['milestone_labels'] ?? []);
            
            let milestoneChart = null;
            
            function initMilestoneChart(type = 'all') {
                const canvas = document.getElementById('milestoneChart');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (milestoneChart) milestoneChart.destroy();
                
                let data, label, color;
                
                switch(type) {
                    case 'completed':
                        data = milestoneData.completed || [];
                        label = 'Completed Milestones';
                        color = '#10b981';
                        break;
                    case 'pending':
                        data = milestoneData.pending || [];
                        label = 'Pending Milestones';
                        color = '#f59e0b';
                        break;
                    default:
                        data = milestoneData.total || [];
                        label = 'Total Milestones';
                        color = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
                }
                
                milestoneChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: milestoneLabels,
                        datasets: [{
                            label: label,
                            data: data,
                            borderColor: color,
                            backgroundColor: color + '33',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: color,
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
                                        return context.dataset.label + ': ' + context.raw.toLocaleString();
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
            
            initContractChart();
            initMilestoneChart('all');
            
            // Chart period buttons - Contracts
            document.querySelectorAll('.chart-period-btn[data-chart="contracts"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.chart-period-btn[data-chart="contracts"]').forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = '';
                        b.style.color = '';
                    });
                    this.classList.add('active');
                    this.style.backgroundColor = 'var(--primary)';
                    this.style.color = 'white';
                    // Re-render with new data if needed
                });
            });
            
            // Chart period buttons - Milestones
            document.querySelectorAll('.chart-period-btn[data-chart="milestones"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.chart-period-btn[data-chart="milestones"]').forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = '';
                        b.style.color = '';
                    });
                    this.classList.add('active');
                    this.style.backgroundColor = 'var(--info)';
                    this.style.color = 'white';
                    initMilestoneChart(this.dataset.period);
                });
            });
            
        } catch (error) {
            console.error('Error initializing charts:', error);
        }
        
        // Tooltips for elements with data-tooltip attribute
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
    });
</script>
@endpush
@endsection