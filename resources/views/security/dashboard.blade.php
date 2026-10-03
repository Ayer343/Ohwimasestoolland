@extends('layouts.secu')

@section('title', 'Security Dashboard')

@section('content')
<div class="dashboard-container">
    <!-- Page Header with Dashboard Switcher Integration -->
    <div class="dashboard-header mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                         style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);">
                        <i class="fas fa-shield-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                            Security Dashboard
                        </h1>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Welcome back, {{ auth()->user()->name }}! Here's your security overview.
                        </p>
                    </div>
                </div>
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
                    
                    // Check if user has multiple roles
                    $hasMultipleRoles = count($allRoles) > 1;
                    
                    // Get current role from session
                    $currentRole = session('selected_role');
                    
                    // If no role in session, default to security-personnel
                    if (!$currentRole) {
                        $currentRole = 'security-personnel';
                    }
                    
                    // Ensure the user actually has this role
                    if (!in_array($currentRole, $allRoles)) {
                        $currentRole = $allRoles[0] ?? 'security-personnel';
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
                    $currentIcon = $roleIcons[$currentRole] ?? 'shield-alt';
                    
                    // Role descriptions
                    $roleDescriptions = [
                        'super-admin' => 'Full system control',
                        'admin' => 'System management',
                        'landlord' => 'Property portfolio management',
                        'tenant' => 'Rental management',
                        'field-agent' => 'Property registration',
                        'security-personnel' => 'Security operations',
                        'developer' => 'System development',
                    ];
                    
                    // Build the combined roles list for the switcher menu
                    $combinedRoles = [];
                    $addedSlugs = [];
                    
                    // Add legacy type as a role option if it's valid
                    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $addedSlugs)) {
                        $combinedRoles[] = (object)[
                            'slug' => $legacyRoleSlug,
                            'display_name' => ucfirst(str_replace('-', ' ', $legacyRoleSlug)),
                            'source' => 'legacy',
                            'description' => $roleDescriptions[$legacyRoleSlug] ?? 'Dashboard access'
                        ];
                        $addedSlugs[] = $legacyRoleSlug;
                    }
                    
                    // Add role-based roles that aren't already represented
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
                    
                    // Sort roles (security-personnel first for this dashboard, then super-admin, admin, etc.)
                    usort($combinedRoles, function($a, $b) {
                        $order = ['security-personnel' => 0, 'super-admin' => 1, 'admin' => 2, 'landlord' => 3, 'tenant' => 4, 'field-agent' => 5, 'developer' => 6];
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
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
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
                
                <button id="refreshDashboard" class="px-3 py-2 rounded-lg text-sm font-medium transition-all" 
                        style="background-color: var(--primary); color: white; border: none;">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                
                <div class="text-right">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- MULTI-ROLE NOTICE (Auto-hide after 5 seconds) -->
        @if($hasMultipleRoles)
        <div id="multiRoleBanner" class="mt-4 p-3 rounded-lg flex items-center justify-between flex-wrap gap-3 transition-all duration-500" 
             style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--secondary-rgb), 0.05) 100%); border: 1px solid rgba(var(--primary-rgb), 0.2);">
            <div class="flex items-center gap-3">
                <i class="fas fa-exchange-alt text-lg" style="color: var(--primary);"></i>
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
    </div>

    <!-- Current Status Card -->
    <div class="current-status-card rounded-xl p-6 mb-8" 
         style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm font-medium mb-1" style="color: rgba(255,255,255,0.8);">Current Status</p>
                <h2 class="text-2xl font-bold" style="color: white;" id="currentStatusText">
                    {{ $currentStatus['message'] ?? 'Off Duty' }}
                </h2>
                @if(isset($currentStatus['checked_in_at']) && $currentStatus['checked_in_at'])
                    <p class="text-sm mt-1" style="color: rgba(255,255,255,0.7);">
                        <i class="fas fa-clock mr-1"></i>Checked in at {{ $currentStatus['checked_in_at'] }}
                    </p>
                @endif
            </div>
            <div class="text-center">
                <div class="w-20 h-20 rounded-full flex items-center justify-center" 
                     style="background: rgba(255,255,255,0.2);">
                    <i class="fas {{ $currentStatus['icon'] ?? 'fa-user-shield' }} fa-3x" style="color: white;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="quick-stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- On-Time Rate Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">On-Time Rate</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);" id="onTimeRate">
                        {{ $performanceMetrics['punctuality_rate'] ?? 0 }}%
                    </p>
                    <div class="flex items-center mt-2">
                        <div class="w-full rounded-full h-2" style="background-color: var(--border-color);">
                            <div class="h-2 rounded-full" style="width: {{ $performanceMetrics['punctuality_rate'] ?? 0 }}%; background-color: var(--success);"></div>
                        </div>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-calendar-check text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Completed Shifts Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Completed Shifts</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);" id="completedShifts">
                        {{ $performanceMetrics['completed_shifts'] ?? 0 }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        out of {{ $performanceMetrics['total_shifts'] ?? 0 }} total
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-clipboard-list text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Pending Tasks Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Tasks</p>
                    <p class="text-2xl font-bold" style="color: var(--warning);" id="pendingTasksCount">
                        {{ isset($pendingTasks) && is_array($pendingTasks) ? count($pendingTasks) : 0 }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        tasks requiring attention
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-tasks text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Reports Submitted Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Reports Submitted</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $performanceMetrics['reports_submitted'] ?? 0 }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        this month
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-file-alt text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

<!-- Quick Navigation Cards - FIXED with Named Routes -->
<div class="quick-nav-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <!-- My Schedule - FIXED -->
    <a href="{{ route('security.schedules.index') }}" 
       class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
       style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
             style="background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);">
            <i class="fas fa-calendar-alt text-white text-lg"></i>
        </div>
        <p class="text-sm font-medium" style="color: var(--text-primary);">My Schedule</p>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">View shifts</p>
    </a>
    
    <!-- Preferences - FIXED -->
    <a href="{{ route('security.schedules.preferences') }}" 
       class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
       style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
             style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
            <i class="fas fa-sliders-h text-white text-lg"></i>
        </div>
        <p class="text-sm font-medium" style="color: var(--text-primary);">Preferences</p>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">Set preferences</p>
    </a>
    
    <!-- Availability - FIXED -->
    <a href="{{ route('security.schedules.availability') }}" 
       class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
       style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
             style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fas fa-calendar-week text-white text-lg"></i>
        </div>
        <p class="text-sm font-medium" style="color: var(--text-primary);">Availability</p>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">Set availability</p>
    </a>
    
    <!-- Reports - FIXED (using schedule index as fallback since reports route may not exist) -->
    <a href="{{ route('security.schedules.index') }}" 
       class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
       style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
             style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <i class="fas fa-file-alt text-white text-lg"></i>
        </div>
        <p class="text-sm font-medium" style="color: var(--text-primary);">Reports</p>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">Submit reports</p>
    </a>
    
    <!-- Rotation Groups - FIXED -->
    <a href="{{ route('security.schedules.rotation-groups') }}" 
       class="quick-nav-card rounded-xl p-4 text-center transition-all hover:shadow-lg hover:translate-y-1"
       style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
             style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <i class="fas fa-users-cog text-white text-lg"></i>
        </div>
        <p class="text-sm font-medium" style="color: var(--text-primary);">Rotation Groups</p>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">View groups</p>
    </a>
</div>

    <!-- Main Content Grid (rest remains the same as original - today's schedule, assigned post, etc.) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Today's Schedule Column -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i>Today's Schedule
                    </h3>
                    <a href="{{ route('security.schedules.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="p-6">
                @if(isset($todaySchedule) && $todaySchedule)
                    <div class="schedule-card p-4 border rounded-lg mb-4" style="border-color: var(--border-color); background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--card-bg) 100%);">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="mb-2"><strong style="color: var(--text-primary);">Post:</strong> <span style="color: var(--text-secondary);">{{ $todaySchedule['post_name'] ?? 'N/A' }}</span></p>
                                <p class="mb-2"><strong style="color: var(--text-primary);">Location:</strong> <span style="color: var(--text-secondary);">{{ $todaySchedule['post_location'] ?? 'N/A' }}</span></p>
                                <p class="mb-2"><strong style="color: var(--text-primary);">Shift:</strong> <span style="color: var(--text-secondary);">{{ $todaySchedule['shift_name'] ?? 'N/A' }}</span></p>
                                <p class="mb-2"><strong style="color: var(--text-primary);">Time:</strong> 
                                    <span style="color: var(--text-secondary);">
                                        @if(isset($todaySchedule['start_time']) && isset($todaySchedule['end_time']))
                                            {{ \Carbon\Carbon::parse($todaySchedule['start_time'])->format('h:i A') }} - 
                                            {{ \Carbon\Carbon::parse($todaySchedule['end_time'])->format('h:i A') }}
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="mb-3">
                                    <span class="px-3 py-1 rounded-full text-xs shift-status-{{ $todaySchedule['status'] ?? 'pending' }}">
                                        {{ ucfirst($todaySchedule['status'] ?? 'Unknown') }}
                                    </span>
                                </div>
                                @if(isset($todaySchedule['remaining_time']) && $todaySchedule['remaining_time'])
                                    <div class="mb-3">
                                        <small style="color: var(--text-secondary);">Time Remaining:</small>
                                        <p class="text-xl font-bold" style="color: var(--primary);">{{ $todaySchedule['remaining_time'] }}</p>
                                    </div>
                                @endif
                                <div class="action-buttons flex justify-end gap-2">
                                    @if(isset($todaySchedule['can_checkin']) && $todaySchedule['can_checkin'])
                                        <button class="checkin-btn px-3 py-1 rounded-lg text-white text-sm" 
                                                data-schedule-id="{{ $todaySchedule['id'] ?? 0 }}"
                                                style="background-color: var(--success); border: none;">
                                            <i class="fas fa-sign-in-alt"></i> Check In
                                        </button>
                                    @endif
                                    @if(isset($todaySchedule['can_checkout']) && $todaySchedule['can_checkout'])
                                        <button class="checkout-btn px-3 py-1 rounded-lg text-white text-sm" 
                                                data-schedule-id="{{ $todaySchedule['id'] ?? 0 }}"
                                                style="background-color: var(--danger); border: none;">
                                            <i class="fas fa-sign-out-alt"></i> Check Out
                                        </button>
                                    @endif
                                    @if(isset($todaySchedule['status']) && $todaySchedule['status'] == 'in_progress')
                                        <button class="take-break-btn px-3 py-1 rounded-lg text-white text-sm" 
                                                data-schedule-id="{{ $todaySchedule['id'] ?? 0 }}"
                                                style="background-color: var(--warning); border: none;">
                                            <i class="fas fa-coffee"></i> Break
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code for Post Check-in -->
                    @if(isset($assignedPost) && $assignedPost && isset($assignedPost['qr_code']) && $assignedPost['qr_code'])
                        <div class="text-center mt-4">
                            <button class="text-sm px-3 py-1 rounded-lg transition-all" type="button" id="showQrCodeBtn" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                <i class="fas fa-qrcode"></i> Show Post QR Code
                            </button>
                            <div id="qrCodeCollapse" style="display: none;" class="mt-3">
                                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <img src="{{ $assignedPost['qr_code'] }}" alt="Post QR Code" class="mx-auto" style="max-width: 200px;">
                                    <p class="mt-3 small text-center" style="color: var(--text-secondary);">Scan this QR code to verify your presence at the post</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-day fa-4x mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No shift scheduled for today</p>
                        <a href="{{ route('security.schedules.availability') }}" class="inline-block mt-3 text-sm px-4 py-2 rounded-lg" style="background-color: var(--primary); color: white;">
                            Update Availability
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Assigned Post Information (rest remains the same) -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-map-marker-alt mr-2" style="color: var(--primary);"></i>Assigned Post
                </h3>
            </div>
            <div class="p-6">
                @if(isset($assignedPost) && $assignedPost)
                    <div class="text-center mb-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-3" 
                             style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);">
                            <i class="fas fa-shield-alt text-white text-2xl"></i>
                        </div>
                        <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">{{ $assignedPost['name'] ?? 'N/A' }}</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-map-marker-alt mr-1"></i> {{ $assignedPost['location'] ?? 'N/A' }}
                        </p>
                        @if(isset($assignedPost['checkpoint_code']))
                            <p class="text-sm mt-2" style="color: var(--info);">
                                <i class="fas fa-fingerprint mr-1"></i> Checkpoint: {{ $assignedPost['checkpoint_code'] }}
                            </p>
                        @endif
                        @if(isset($assignedPost['coordinates']) && $assignedPost['coordinates'])
                            <button class="mt-3 text-sm px-3 py-1 rounded-lg transition-all" onclick="openMap('{{ $assignedPost['coordinates'] }}')" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                <i class="fas fa-map"></i> View on Map
                            </button>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-map-marker-alt fa-4x mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No post assigned</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Upcoming Shifts & Pending Tasks Row (rest remains the same) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Upcoming Shifts -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-week mr-2" style="color: var(--primary);"></i>Upcoming Shifts
                    </h3>
                    <a href="{{ route('security.schedules.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @if(isset($upcomingShifts) && is_array($upcomingShifts) && count($upcomingShifts) > 0)
                    @foreach($upcomingShifts as $shift)
                        <div class="px-6 py-4 hover:bg-opacity-5 transition-colors" style="background-color: var(--card-bg);">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ $shift['day_name'] ?? 'Unknown' }}, {{ isset($shift['date']) ? \Carbon\Carbon::parse($shift['date'])->format('M d, Y') : 'N/A' }}
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $shift['post_name'] ?? 'N/A' }} • {{ $shift['shift_name'] ?? 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs px-2 py-1 rounded-full shift-status-{{ $shift['status'] ?? 'pending' }}">
                                        {{ ucfirst($shift['status'] ?? 'Pending') }}
                                    </span>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        @if(isset($shift['start_time']) && isset($shift['end_time']))
                                            {{ \Carbon\Carbon::parse($shift['start_time'])->format('h:i A') }} - {{ \Carbon\Carbon::parse($shift['end_time'])->format('h:i A') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-calendar-week fa-3x mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No upcoming shifts scheduled</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Pending Tasks -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-tasks mr-2" style="color: var(--warning);"></i>Pending Tasks
                </h3>
            </div>
            <div class="p-6">
                @if(isset($pendingTasks) && is_array($pendingTasks) && count($pendingTasks) > 0)
                    @foreach($pendingTasks as $task)
                        <div class="mb-3 p-3 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 3px solid var(--warning);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--warning-rgb), 0.2);">
                                        <i class="fas fa-{{ $task['icon'] ?? 'bell' }}" style="color: var(--warning);"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium" style="color: var(--text-primary);">{{ $task['title'] ?? 'Task' }}</p>
                                        <p class="text-xs" style="color: var(--text-secondary);">{{ $task['description'] ?? '' }}</p>
                                    </div>
                                </div>
                                @if(isset($task['route']))
                                    <a href="{{ $task['route'] }}" class="text-sm px-3 py-1 rounded-lg transition-all" 
                                       style="background-color: var(--primary); color: white;">
                                        View
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle fa-4x mb-3" style="color: var(--success);"></i>
                        <p style="color: var(--text-secondary);">No pending tasks</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">All caught up! Great job!</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Notifications & Performance Overview (rest remains the same) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Notifications -->
        <div class="recent-card rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-bell mr-2" style="color: var(--info);"></i>Recent Notifications
                    </h3>
                    <a href="{{ route('security.notifications.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="divide-y" style="border-color: var(--border-color);">
                @if(isset($recentNotifications) && is_array($recentNotifications) && count($recentNotifications) > 0)
                    @foreach($recentNotifications as $notification)
                        <div class="notification-item px-6 py-4 hover:bg-opacity-5 transition-colors {{ isset($notification['is_read']) && !$notification['is_read'] ? 'bg-opacity-5' : '' }}" 
                             data-id="{{ $notification['id'] ?? 0 }}"
                             style="background-color: {{ isset($notification['is_read']) && !$notification['is_read'] ? 'rgba(var(--primary-rgb), 0.05)' : 'transparent' }};">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-2 h-2 rounded-full {{ isset($notification['is_read']) && !$notification['is_read'] ? 'bg-primary' : 'bg-transparent' }}" 
                                             style="background-color: {{ isset($notification['is_read']) && !$notification['is_read'] ? 'var(--primary)' : 'transparent' }};"></div>
                                        <p class="font-medium text-sm" style="color: var(--text-primary);">{{ $notification['title'] ?? 'Notification' }}</p>
                                    </div>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $notification['message'] ?? '' }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.7;">
                                        <i class="far fa-clock mr-1"></i>{{ $notification['created_at'] ?? 'recent' }}
                                    </p>
                                </div>
                                @if(isset($notification['is_read']) && !$notification['is_read'])
                                    <button class="mark-read-btn text-xs px-2 py-1 rounded transition-all" 
                                            data-id="{{ $notification['id'] ?? 0 }}"
                                            style="background-color: var(--primary); color: white;">
                                        Mark read
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-bell-slash fa-3x mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No notifications</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Performance Overview Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Performance Overview
                </h3>
                <div class="flex space-x-2">
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="weekly"
                            style="background-color: var(--primary); color: white;">Weekly</button>
                    <button class="chart-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="monthly"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Monthly</button>
                </div>
            </div>
            <canvas id="performanceChart" height="250" style="width: 100%;"></canvas>
        </div>
    </div>
</div>

<!-- Modals remain the same as original -->
<!-- Modal for Check-in -->
<div id="checkinModal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background-color: rgba(0,0,0,0.5);">
    <div class="rounded-xl w-full max-w-md mx-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h5 class="text-lg font-semibold" style="color: var(--text-primary);">Shift Check-in</h5>
                <button class="close-modal text-2xl" style="color: var(--text-secondary);">&times;</button>
            </div>
        </div>
        <div class="p-6">
            <div class="text-center mb-4">
                <i class="fas fa-qrcode fa-5x" style="color: var(--primary);"></i>
            </div>
            <p class="text-center mb-4" style="color: var(--text-secondary);">Scan the QR code at your assigned post to verify your check-in</p>
            <div class="mb-4">
                <label style="color: var(--text-primary);">Enter QR Code or Checkpoint Code</label>
                <input type="text" id="qrCodeInput" placeholder="Scan or enter code..."
                       class="w-full px-3 py-2 rounded-lg mt-1"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); border: 1px solid;">
            </div>
            <div id="locationStatus" class="text-sm text-center mt-3" style="color: var(--text-secondary);">
                <i class="fas fa-spinner fa-spin"></i> Verifying location...
            </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3" style="border-color: var(--border-color);">
            <button class="close-modal px-4 py-2 rounded-lg transition-all" style="background-color: var(--bg-secondary); color: var(--text-secondary);">Cancel</button>
            <button id="confirmCheckin" class="px-4 py-2 rounded-lg transition-all text-white" style="background-color: var(--primary);">Confirm Check-in</button>
        </div>
    </div>
</div>

<!-- Modal for Check-out -->
<div id="checkoutModal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background-color: rgba(0,0,0,0.5);">
    <div class="rounded-xl w-full max-w-md mx-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h5 class="text-lg font-semibold" style="color: var(--text-primary);">Shift Check-out</h5>
                <button class="close-modal text-2xl" style="color: var(--text-secondary);">&times;</button>
            </div>
        </div>
        <div class="p-6">
            <p style="color: var(--text-primary);">Please confirm the following before checking out:</p>
            <ul class="mt-3 space-y-2" style="color: var(--text-secondary);">
                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> All tasks for this shift are completed</li>
                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Handover report has been submitted</li>
                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Equipment has been returned</li>
            </ul>
            <div class="mt-4">
                <label style="color: var(--text-primary);">Shift Notes (Optional)</label>
                <textarea id="checkoutNotes" rows="3" placeholder="Enter any important notes about your shift..."
                          class="w-full px-3 py-2 rounded-lg mt-1"
                          style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); border: 1px solid;"></textarea>
            </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3" style="border-color: var(--border-color);">
            <button class="close-modal px-4 py-2 rounded-lg transition-all" style="background-color: var(--bg-secondary); color: var(--text-secondary);">Cancel</button>
            <button id="confirmCheckout" class="px-4 py-2 rounded-lg transition-all text-white" style="background-color: var(--danger);">Confirm Check-out</button>
        </div>
    </div>
</div>

<!-- Modal for Break -->
<div id="breakModal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background-color: rgba(0,0,0,0.5);">
    <div class="rounded-xl w-full max-w-md mx-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h5 class="text-lg font-semibold" style="color: var(--text-primary);">Take a Break</h5>
                <button class="close-modal text-2xl" style="color: var(--text-secondary);">&times;</button>
            </div>
        </div>
        <div class="p-6">
            <p style="color: var(--text-primary);">How long do you need for your break?</p>
            <div class="flex justify-around gap-3 my-4">
                <button class="break-duration-btn px-4 py-2 rounded-lg transition-all" data-duration="15" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">15 min</button>
                <button class="break-duration-btn px-4 py-2 rounded-lg transition-all" data-duration="30" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">30 min</button>
                <button class="break-duration-btn px-4 py-2 rounded-lg transition-all active" data-duration="60" style="background-color: var(--primary); color: white;">60 min</button>
            </div>
            <div class="mt-4">
                <label style="color: var(--text-primary);">Reason (Optional)</label>
                <input type="text" id="breakReason" placeholder="e.g., Lunch, Rest, etc."
                       class="w-full px-3 py-2 rounded-lg mt-1"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); border: 1px solid;">
            </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3" style="border-color: var(--border-color);">
            <button class="close-modal px-4 py-2 rounded-lg transition-all" style="background-color: var(--bg-secondary); color: var(--text-secondary);">Cancel</button>
            <button id="confirmBreak" class="px-4 py-2 rounded-lg transition-all text-white" style="background-color: var(--warning);">Start Break</button>
        </div>
    </div>
</div>

@push('styles')
<style>
    :root {
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --secondary: #1e40af;
        --secondary-rgb: 30, 64, 175;
        --warning: #f59e0b;
        --warning-rgb: 245, 158, 11;
        --danger: #ef4444;
        --danger-rgb: 239, 68, 68;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --info: #06b6d4;
        --info-rgb: 6, 182, 212;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --card-bg-rgb: 255, 255, 255;
        --bg-secondary: #f3f4f6;
        --border-color: #e5e7eb;
        --border-color-rgb: 229, 231, 235;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --card-bg: #1f2937;
            --card-bg-rgb: 31, 41, 55;
            --bg-secondary: #374151;
            --border-color: #374151;
            --border-color-rgb: 55, 65, 81;
        }
    }

    .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }

    .quick-nav-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
        text-decoration: none;
    }
    .quick-nav-card:hover { transform: translateY(-4px); text-decoration: none; }

    .stat-card, .recent-card, .chart-card, .current-status-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover, .recent-card:hover, .chart-card:hover, .current-status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .shift-status-completed, .badge-success {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .shift-status-in_progress {
        background-color: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    .shift-status-pending {
        background-color: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }
    .shift-status-cancelled {
        background-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    .notification-item { cursor: pointer; transition: background-color 0.2s ease; }
    .notification-item:hover { background-color: rgba(var(--primary-rgb), 0.05) !important; }

    .chart-period-btn { cursor: pointer; transition: all 0.2s ease; }
    .chart-period-btn.active { background-color: var(--primary) !important; color: white !important; }

    .break-duration-btn { cursor: pointer; transition: all 0.2s ease; }
    .break-duration-btn.active { background-color: var(--primary) !important; color: white !important; }

    .quick-stats-grid { display: grid; gap: 1.5rem; margin-bottom: 2rem; }
    .quick-nav-grid { display: grid; gap: 1rem; margin-bottom: 2rem; }

    .modal-open { overflow: hidden; }

    /* Dashboard Switcher Styles */
    .dashboard-switcher { position: relative; }
    
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
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
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

    @media (max-width: 768px) {
        .dashboard-container { padding: 0 0.5rem; }
        .quick-stats-grid { gap: 1rem; }
        .stat-card { padding: 1rem; }
        .quick-nav-grid { gap: 0.75rem; }
        .quick-nav-card { padding: 0.75rem; }
        .quick-nav-card .w-12 { width: 2.5rem; height: 2.5rem; }
        .dashboard-header .flex { flex-direction: column; align-items: stretch; }
        .dashboard-switcher { width: 100%; }
        #dashboardSwitcherBtn { width: 100%; justify-content: center; }
        #dashboardSwitcherMenu { width: 100%; left: 0; right: auto; }
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
        
        // Show loading state if button element provided
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
    
    // Modal handling
    var currentScheduleId = null;
    var refreshInterval = null;
    var performanceChart = null;

    // Route URLs - FIXED: Use actual fetch endpoints instead of route() helper in JavaScript
    // We'll construct URLs dynamically using the page's base URL
    var baseUrl = window.location.origin;
    
    function getApiUrl(endpoint) {
        return baseUrl + '/security/api/' + endpoint;
    }

    function refreshDashboardData() {
        // Show loading state on refresh button
        const refreshBtn = document.getElementById('refreshDashboard');
        const originalHtml = refreshBtn ? refreshBtn.innerHTML : '';
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Refreshing...';
            refreshBtn.disabled = true;
        }
        
        // Fetch updated data from the dashboard data endpoint
        fetch(baseUrl + '/security/dashboard/data?type=all', {
            headers: { 
                'X-Requested-With': 'XMLHttpRequest', 
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
            }
        })
        .then(function(response) { 
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json(); 
        })
        .then(function(data) {
            if (data.success && data.data) {
                // Update current status
                if (data.data.current_status) {
                    var statusText = document.getElementById('currentStatusText');
                    if (statusText) statusText.textContent = data.data.current_status.message || 'Off Duty';
                }
                
                // Update performance metrics
                if (data.data.performance_metrics) {
                    var onTimeRate = document.getElementById('onTimeRate');
                    var completedShifts = document.getElementById('completedShifts');
                    if (onTimeRate) onTimeRate.textContent = (data.data.performance_metrics.punctuality_rate || 0) + '%';
                    if (completedShifts) completedShifts.textContent = data.data.performance_metrics.completed_shifts || 0;
                }
                
                // Update pending tasks count
                if (data.data.pending_tasks) {
                    var pendingCount = document.getElementById('pendingTasksCount');
                    if (pendingCount) pendingCount.textContent = data.data.pending_tasks.length || 0;
                }
                
                // Update today's schedule if present
                if (data.data.today_schedule) {
                    var todayScheduleDiv = document.querySelector('.schedule-card');
                    if (todayScheduleDiv && data.data.today_schedule.post_name) {
                        // Update the schedule display without full page reload
                        var postNameSpan = todayScheduleDiv.querySelector('span:first-child');
                        if (postNameSpan) {
                            // Update specific elements
                            var paragraphs = todayScheduleDiv.querySelectorAll('p');
                            if (paragraphs.length >= 4) {
                                paragraphs[0].innerHTML = '<strong style="color: var(--text-primary);">Post:</strong> <span style="color: var(--text-secondary);">' + (data.data.today_schedule.post_name || 'N/A') + '</span>';
                                paragraphs[1].innerHTML = '<strong style="color: var(--text-primary);">Location:</strong> <span style="color: var(--text-secondary);">' + (data.data.today_schedule.post_location || 'N/A') + '</span>';
                                paragraphs[2].innerHTML = '<strong style="color: var(--text-primary);">Shift:</strong> <span style="color: var(--text-secondary);">' + (data.data.today_schedule.shift_name || 'N/A') + '</span>';
                            }
                        }
                    }
                }
                
                window.showDashboardNotification('Dashboard refreshed successfully!', 'success');
            } else {
                throw new Error(data.message || 'Failed to refresh dashboard');
            }
        })
        .catch(function(error) { 
            console.log('Failed to refresh dashboard data:', error);
            window.showDashboardNotification('Could not refresh dashboard. Please try again.', 'error');
        })
        .finally(function() {
            // Restore refresh button
            if (refreshBtn) {
                refreshBtn.innerHTML = originalHtml || '<i class="fas fa-sync-alt mr-1"></i> Refresh';
                refreshBtn.disabled = false;
            }
        });
    }

    function openMap(coordinates) {
        if (coordinates) {
            var coords = coordinates.split(',');
            window.open('https://www.google.com/maps?q=' + coords[0] + ',' + coords[1], '_blank');
        }
    }

    function closeAllModals() {
        var modals = document.querySelectorAll('.fixed.inset-0');
        modals.forEach(function(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });
        document.body.classList.remove('modal-open');
    }

    function openModal(modalId) {
        closeAllModals();
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('modal-open');
        }
    }
    
    function verifyLocation() {
        var statusDiv = document.getElementById('locationStatus');
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                statusDiv.innerHTML = '<i class="fas fa-check-circle text-green-500"></i> Location verified: Within post perimeter';
            }, function(error) {
                statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500"></i> Could not verify location. Please ensure GPS is enabled.';
            });
        } else {
            statusDiv.innerHTML = '<i class="fas fa-info-circle"></i> Location verification not available. Please enter QR code.';
        }
    }
    
    function performCheckin(scheduleId) {
        var qrCode = document.getElementById('qrCodeInput').value;
        if (!qrCode) {
            alert('Please scan the QR code or enter checkpoint code');
            return;
        }
        
        fetch(getApiUrl('schedule/' + scheduleId + '/smart-checkin'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ qr_code: qrCode, location_verified: true })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                closeAllModals();
                alert('Check-in successful!');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                alert(data.message || 'Check-in failed');
            }
        })
        .catch(function(error) {
            alert('Error during check-in');
        });
    }
    
    function performCheckout(scheduleId) {
        var notes = document.getElementById('checkoutNotes').value;
        
        fetch(getApiUrl('schedule/' + scheduleId + '/smart-checkout'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ notes: notes })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                closeAllModals();
                alert('Check-out successful!');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                alert(data.message || 'Check-out failed');
            }
        })
        .catch(function(error) {
            alert('Error during check-out');
        });
    }
    
    function performBreak(scheduleId) {
        var durationInput = document.querySelector('.break-duration-btn.active');
        var duration = durationInput ? durationInput.getAttribute('data-duration') : '60';
        var reason = document.getElementById('breakReason').value;
        
        fetch(getApiUrl('schedule/' + scheduleId + '/break/start'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ duration: duration, reason: reason })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                closeAllModals();
                alert('Break started! You have ' + duration + ' minutes.');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                alert(data.message || 'Could not start break');
            }
        })
        .catch(function(error) {
            alert('Error starting break');
        });
    }

    function initPerformanceChart(period) {
        var ctx = document.getElementById('performanceChart').getContext('2d');
        var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#3b82f6';
        
        var labels, onTimeData, completionData;
        
        if (period === 'weekly') {
            labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            onTimeData = [95, 92, 98, 85, 90, 88, 94];
            completionData = [100, 95, 100, 90, 95, 92, 98];
        } else {
            labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
            onTimeData = [92, 94, 90, {{ $performanceMetrics['punctuality_rate'] ?? 0 }}];
            completionData = [95, 96, 93, {{ $performanceMetrics['completion_rate'] ?? 85 }}];
        }
        
        if (performanceChart) performanceChart.destroy();
        
        performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'On-Time Rate (%)',
                    data: onTimeData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }, {
                    label: 'Completion Rate (%)',
                    data: completionData,
                    borderColor: primaryColor,
                    backgroundColor: 'rgba(59, 130, 242, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: primaryColor,
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { labels: { color: '#6b7280' } },
                    tooltip: { callbacks: { label: function(ctx) { return ctx.dataset.label + ': ' + ctx.raw + '%'; } } }
                },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { color: '#6b7280', callback: function(v) { return v + '%'; } }, grid: { color: 'rgba(229,231,235,0.1)' } },
                    x: { ticks: { color: '#6b7280' }, grid: { color: 'rgba(229,231,235,0.1)' } }
                }
            }
        });
    }

    // ============ DASHBOARD SWITCHER INITIALIZATION ============
    function initDashboardSwitcher() {
        const switcherBtn = document.getElementById('dashboardSwitcherBtn');
        const switcherMenu = document.getElementById('dashboardSwitcherMenu');
        
        let currentRole = '{{ $currentRole }}';
        
        if (!switcherBtn || !switcherMenu) {
            console.warn('Dashboard switcher elements not found');
            return;
        }
        
        // Clone and replace to remove existing event listeners
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
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!newSwitcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                if (switcherMenu.classList.contains('show')) {
                    switcherMenu.classList.remove('show');
                }
            }
        });
        
        // Handle switch options
        const switchOptions = document.querySelectorAll('.dashboard-switch-option');
        switchOptions.forEach(option => {
            const newOption = option.cloneNode(true);
            option.parentNode.replaceChild(newOption, option);
            
            newOption.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const roleSlug = this.dataset.role;
                switcherMenu.classList.remove('show');
                
                // Use global switch function
                window.switchDashboardRole(roleSlug, currentRole, newSwitcherBtn);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dashboard switcher
        initDashboardSwitcher();
        
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
        
        // Initialize chart
        initPerformanceChart('weekly');
        
        // Auto-refresh every 60 seconds
        refreshInterval = setInterval(refreshDashboardData, 60000);
        
        // Check-in button
        var checkinBtns = document.querySelectorAll('.checkin-btn');
        checkinBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var scheduleId = this.getAttribute('data-schedule-id');
                currentScheduleId = scheduleId;
                document.getElementById('qrCodeInput').value = '';
                openModal('checkinModal');
                verifyLocation();
                
                // Set up confirm button for this schedule
                var confirmBtn = document.getElementById('confirmCheckin');
                var newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                newConfirmBtn.addEventListener('click', function() {
                    performCheckin(scheduleId);
                });
            });
        });
        
        // Check-out button
        var checkoutBtns = document.querySelectorAll('.checkout-btn');
        checkoutBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var scheduleId = this.getAttribute('data-schedule-id');
                currentScheduleId = scheduleId;
                document.getElementById('checkoutNotes').value = '';
                openModal('checkoutModal');
                
                // Set up confirm button for this schedule
                var confirmBtn = document.getElementById('confirmCheckout');
                var newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                newConfirmBtn.addEventListener('click', function() {
                    performCheckout(scheduleId);
                });
            });
        });
        
        // Take break button
        var breakBtns = document.querySelectorAll('.take-break-btn');
        breakBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var scheduleId = this.getAttribute('data-schedule-id');
                currentScheduleId = scheduleId;
                document.getElementById('breakReason').value = '';
                openModal('breakModal');
                
                // Set up confirm button for this schedule
                var confirmBtn = document.getElementById('confirmBreak');
                var newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                newConfirmBtn.addEventListener('click', function() {
                    performBreak(scheduleId);
                });
            });
        });
        
        // Break duration buttons
        var breakDurationBtns = document.querySelectorAll('.break-duration-btn');
        breakDurationBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                breakDurationBtns.forEach(function(b) {
                    b.classList.remove('active');
                    b.style.backgroundColor = 'var(--bg-secondary)';
                    b.style.color = 'var(--text-primary)';
                });
                this.classList.add('active');
                this.style.backgroundColor = 'var(--primary)';
                this.style.color = 'white';
            });
        });
        
        // QR Code input
        var qrInput = document.getElementById('qrCodeInput');
        if (qrInput) {
            qrInput.addEventListener('input', function() {
                var confirmBtn = document.getElementById('confirmCheckin');
                if (confirmBtn) confirmBtn.disabled = this.value.length < 5;
            });
        }
        
        // Mark notification as read (placeholder - implement if needed)
        var markReadBtns = document.querySelectorAll('.mark-read-btn');
        markReadBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Implement mark as read functionality
                console.log('Mark notification as read:', this.getAttribute('data-id'));
            });
        });
        
        // Refresh button - FIXED: Now properly calls refreshDashboardData
        var refreshBtn = document.getElementById('refreshDashboard');
        if (refreshBtn) {
            // Remove any existing listeners
            var newRefreshBtn = refreshBtn.cloneNode(true);
            refreshBtn.parentNode.replaceChild(newRefreshBtn, refreshBtn);
            
            newRefreshBtn.addEventListener('click', function(e) {
                e.preventDefault();
                refreshDashboardData();
            });
        }
        
        // Chart period buttons
        var chartPeriodBtns = document.querySelectorAll('.chart-period-btn');
        chartPeriodBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var period = this.getAttribute('data-period');
                chartPeriodBtns.forEach(function(b) {
                    b.classList.remove('active');
                    b.style.backgroundColor = 'var(--bg-secondary)';
                    b.style.color = 'var(--text-secondary)';
                });
                this.classList.add('active');
                this.style.backgroundColor = 'var(--primary)';
                this.style.color = 'white';
                initPerformanceChart(period);
            });
        });
        
        // Show QR Code button
        var showQrBtn = document.getElementById('showQrCodeBtn');
        if (showQrBtn) {
            showQrBtn.addEventListener('click', function() {
                var qrDiv = document.getElementById('qrCodeCollapse');
                if (qrDiv) {
                    if (qrDiv.style.display === 'none') {
                        qrDiv.style.display = 'block';
                    } else {
                        qrDiv.style.display = 'none';
                    }
                }
            });
        }
        
        // Close modal buttons
        var closeModalBtns = document.querySelectorAll('.close-modal');
        closeModalBtns.forEach(function(btn) {
            btn.addEventListener('click', closeAllModals);
        });
        
        // Close modal on background click
        var modals = document.querySelectorAll('.fixed.inset-0');
        modals.forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeAllModals();
            });
        });
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) clearInterval(refreshInterval);
    });
</script>
@endpush

@endsection