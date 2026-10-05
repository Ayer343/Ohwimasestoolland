@extends('layouts.app')

@section('title', 'Security Schedules')

{{-- FIXED: Always share auth variables with layout for search bar visibility --}}
@php
    // Get current user role for layout authorization - ALWAYS SHARE
    $currentUserRole = auth()->user()->type ?? null;
    $isSuperAdmin = $currentUserRole === 0;
    $isAdmin = $currentUserRole === 1;
    $isAuthorized = $isSuperAdmin || $isAdmin;
    
    // ALWAYS share these variables with all views including layout (removed the if condition)
    view()->share('currentUserRole', $currentUserRole);
    view()->share('isSuperAdmin', $isSuperAdmin);
    view()->share('isAdmin', $isAdmin);
    view()->share('isAuthorized', $isAuthorized);
    
    // Calculate statistics from actual data if not provided by controller
    if(!isset($stats) || empty($stats)) {
        // Get today's date
        $today = now()->format('Y-m-d');
        
        // Calculate today's schedules from the collection
        $todaySchedules = $schedules->filter(function($schedule) use ($today) {
            return $schedule->assignment_date instanceof \Carbon\Carbon 
                ? $schedule->assignment_date->format('Y-m-d') === $today
                : $schedule->assignment_date === $today;
        });
        
        // Calculate active now (status = 'active' and today)
        $activeNow = $todaySchedules->where('status', 'active')->count();
        
        // Calculate completed today
        $completedToday = $todaySchedules->where('status', 'completed')->count();
        
        // Calculate absent today
        $absentToday = $todaySchedules->where('status', 'absent')->count();
        
        // Calculate rotated count (is_rotated = true)
        $rotatedCount = $schedules->where('is_rotated', true)->count();
        
        // Calculate overtime hours
        $overtimeHours = round($schedules->sum('overtime_minutes') / 60, 1);
        
        // Calculate high preference scores (>= 8)
        $highPreferenceCount = $schedules->where('rotation_preference_score', '>=', 8)->count();
        
        // Build stats array
        $stats = [
            'total_schedules' => $schedules->total(),
            'active_now' => $activeNow,
            'today_schedules' => $todaySchedules->count(),
            'rotated_count' => $rotatedCount,
            'completed_today' => $completedToday,
            'absent_today' => $absentToday,
            'overtime_hours' => $overtimeHours,
            'high_preference_count' => $highPreferenceCount
        ];
    }
    
    // Calculate understaffed posts if not provided
    if(!isset($understaffedPosts) && isset($todaySummary['post_coverage'])) {
        $understaffedPosts = collect($todaySummary['post_coverage'])
            ->filter(function($post) {
                return $post['assigned'] < $post['required'];
            });
    }
    
    // Get trash count
    $trashCount = \App\Models\SecuritySchedule::onlyTrashed()->count();
    
    // Get active rotation groups - using is_active instead of status
    $rotationGroups = \App\Models\RotationGroup::with(['post', 'shift'])
        ->where('is_active', true)
        ->limit(5)
        ->get();
    
    // FIXED: Using 'group_type' column for day/night/evening/mixed
    // FIXED: Using 'configuration' column instead of 'rotation_config'
    $pendingRotations = \App\Models\RotationGroup::whereIn('group_type', ['day', 'night', 'evening'])
        ->whereNotNull('configuration')
        ->whereRaw('JSON_EXTRACT(configuration, "$.next_rotation_date") IS NOT NULL')
        ->whereRaw('JSON_EXTRACT(configuration, "$.next_rotation_date") <= ?', [now()->addDays(3)->format('Y-m-d')])
        ->count();
    
    // FIXED: Get all active rotation groups for filter dropdown
    $allRotationGroups = \App\Models\RotationGroup::with('post')
        ->where('is_active', true)
        ->get();
@endphp

@section('content')
<div id="security-schedules-page" class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> 
                        Security Schedules
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-2"></i>
                        <span>Manage security personnel assignments and schedules</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.rotation-groups.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-sync-alt mr-2"></i> Rotation Groups
                </a>
                <a href="{{ route('admin.rotation-history.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-2"></i> Analytics
                </a>
                <a href="{{ route('admin.security-schedules.create') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-plus mr-2"></i> Assign Schedule
                </a>
                @if($trashCount > 0)
                <a href="{{ route('admin.security-schedules.trash') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $trashCount }}
                    </span>
                </a>
                @else
                <a href="{{ route('admin.security-schedules.trash') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================= -->
    <!-- STATISTICS CARDS                              -->
    <!-- ============================================= -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Schedule Statistics
                </h3>
                <span class="text-sm" style="color: var(--text-secondary);">{{ now()->format('F j, Y') }}</span>
            </div>
        </div>
        <div class="p-6">
            <!-- Main Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Total Schedules -->
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--primary-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--primary);">{{ $stats['total_schedules'] ?? $schedules->total() ?? 0 }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Schedules</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                            <i class="fas fa-calendar-check text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Active Now -->
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--success-rgb), 0.1) 0%, rgba(var(--success-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--success);">{{ $stats['active_now'] ?? ($todaySummary['checked_in'] ?? 0) }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Now</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                            <i class="fas fa-play-circle text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Today's Schedules -->
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.1) 0%, rgba(var(--info-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--info);">{{ $stats['today_schedules'] ?? ($todaySummary['total_assigned'] ?? 0) }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Today's Schedules</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Rotated Schedules -->
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.1) 0%, rgba(var(--warning-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--warning);">{{ $stats['rotated_count'] ?? ($rotatedCount ?? 0) }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Rotated Schedules</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                            <i class="fas fa-sync-alt text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Rotation Groups -->
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.1) 0%, rgba(var(--info-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--info);">{{ \App\Models\RotationGroup::where('is_active', true)->count() }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Groups</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                            <i class="fas fa-users-cog text-xl"></i>
                        </div>
                    </div>
                    <a href="{{ route('admin.rotation-groups.index') }}" class="mt-2 text-xs flex items-center justify-end" style="color: var(--info);">
                        Manage Groups <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Today's Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
                <!-- Completed Today -->
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Completed Today: <strong style="color: var(--success);">{{ $todaySummary['completed'] ?? $stats['completed_today'] ?? 0 }}</strong>
                        </span>
                    </div>
                </div>
                
                <!-- Absent Today -->
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-user-slash mr-2" style="color: var(--danger);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Absent Today: <strong style="color: var(--danger);">{{ $todaySummary['absent'] ?? $stats['absent_today'] ?? 0 }}</strong>
                        </span>
                    </div>
                </div>
                
                <!-- Late Check-ins -->
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Late Today: <strong style="color: var(--warning);">{{ $todaySummary['late_checkins'] ?? 0 }}</strong>
                        </span>
                    </div>
                </div>
                
                <!-- Overtime Hours -->
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-hourglass-end mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Overtime Hours: <strong style="color: var(--warning);">{{ $stats['overtime_hours'] ?? 0 }}h</strong>
                        </span>
                    </div>
                </div>

                <!-- Rotations Today -->
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-history mr-2" style="color: var(--info);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Rotations Today: <strong style="color: var(--info);">{{ \App\Models\SecuritySchedule::whereDate('rotated_at', today())->count() }}</strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Post Coverage Summary -->
            @if(isset($todaySummary['post_coverage']) && count($todaySummary['post_coverage']) > 0)
            <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-medium flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                        Today's Post Coverage
                    </h4>
                    <div class="flex space-x-3 text-xs">
                        <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-green-500 mr-1"></span> Full</span>
                        <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-blue-500 mr-1"></span> Good</span>
                        <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-500 mr-1"></span> Partial</span>
                        <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-red-500 mr-1"></span> Critical</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($todaySummary['post_coverage'] as $post)
                    @php
                        $coveragePercent = $post['coverage_rate'] ?? 0;
                        $statusColor = $coveragePercent >= 100 ? 'success' : ($coveragePercent >= 75 ? 'info' : ($coveragePercent >= 50 ? 'warning' : 'danger'));
                    @endphp
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold inline-block" style="color: var(--text-primary);">
                                    {{ $post['post_name'] }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-semibold inline-block" style="color: var(--{{ $statusColor }});">
                                    {{ $post['assigned'] }}/{{ $post['required'] }}
                                </span>
                            </div>
                        </div>
                        <div class="overflow-hidden h-2 text-xs flex rounded" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2);">
                            <div style="width: {{ min($coveragePercent, 100) }}%; background-color: var(--{{ $statusColor }});" 
                                 class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <div class="p-2 rounded text-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <span class="text-xs font-medium" style="color: var(--success);">Fully Staffed</span>
                        <span class="block text-lg font-bold" style="color: var(--success);">{{ $todaySummary['fully_staffed_posts'] ?? 0 }}</span>
                    </div>
                    <div class="p-2 rounded text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <span class="text-xs font-medium" style="color: var(--warning);">Understaffed</span>
                        <span class="block text-lg font-bold" style="color: var(--warning);">{{ $todaySummary['understaffed_posts'] ?? 0 }}</span>
                    </div>
                    <div class="p-2 rounded text-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <span class="text-xs font-medium" style="color: var(--danger);">Unstaffed</span>
                        <span class="block text-lg font-bold" style="color: var(--danger);">{{ $todaySummary['unstaffed_posts'] ?? 0 }}</span>
                    </div>
                    <div class="p-2 rounded text-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <span class="text-xs font-medium" style="color: var(--info);">Total Posts</span>
                        <span class="block text-lg font-bold" style="color: var(--info);">{{ $todaySummary['total_posts'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- ============================================= -->
    <!-- ROTATION GROUPS QUICK VIEW CARD                -->
    <!-- ============================================= -->
    @if($rotationGroups->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i> Active Rotation Groups
                </h3>
                <a href="{{ route('admin.rotation-groups.index') }}" class="text-sm" style="color: var(--primary);">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach($rotationGroups as $group)
                @php
                    $memberCount = $group->members()->count();
                    // FIXED: Using 'configuration' instead of 'rotation_config'
                    $nextRotation = $group->configuration['next_rotation_date'] ?? null;
                    $daysUntil = $nextRotation ? now()->diffInDays(\Carbon\Carbon::parse($nextRotation)) : null;
                    $isUrgent = $daysUntil !== null && $daysUntil <= 2;
                    // FIXED: Using 'group_type' instead of 'type'
                    $isRotating = in_array($group->group_type, ['day', 'night', 'evening']);
                @endphp
                <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">{{ $group->name }}</h4>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $group->post->name ?? 'N/A' }} • {{ $group->shift->name ?? 'N/A' }}
                            </p>
                        </div>
                        <span class="px-2 py-1 text-xxs rounded-full" 
                              style="background-color: rgba(var(--{{ $group->group_type === 'night' ? 'info' : ($group->group_type === 'day' ? 'warning' : ($group->group_type === 'evening' ? 'secondary' : 'primary')) }}-rgb), 0.1); color: var(--{{ $group->group_type === 'night' ? 'info' : ($group->group_type === 'day' ? 'warning' : ($group->group_type === 'evening' ? 'secondary' : 'primary')) }});">
                            {{ ucfirst($group->group_type) }}
                            @if($isRotating) <i class="fas fa-sync-alt ml-1"></i> @endif
                        </span>
                    </div>
                    <div class="flex justify-between text-xs mb-2">
                        <span style="color: var(--text-secondary);">Members:</span>
                        <span class="font-medium" style="color: var(--text-primary);">{{ $memberCount }}/{{ $group->max_members ?? '∞' }}</span>
                    </div>
                    @if($nextRotation && $isRotating)
                    <div class="flex justify-between text-xs mb-3">
                        <span style="color: var(--text-secondary);">Next rotation:</span>
                        <span class="font-medium {{ $isUrgent ? 'text-red-500' : '' }}">
                            {{ \Carbon\Carbon::parse($nextRotation)->format('M j') }}
                            @if($isUrgent) <i class="fas fa-exclamation-circle ml-1"></i> @endif
                        </span>
                    </div>
                    @endif
                    <div class="flex space-x-2">
                        <a href="{{ route('admin.rotation-groups.show', $group->id) }}" 
                           class="flex-1 py-1.5 text-xs rounded text-center"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            View
                        </a>
                        <a href="{{ route('admin.rotation-history.group', $group->id) }}" 
                           class="flex-1 py-1.5 text-xs rounded text-center"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            History
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
            @if($pendingRotations > 0)
            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--text-primary);">{{ $pendingRotations }} rotating group(s) need attention in the next 3 days</span>
                    </div>
                    <a href="{{ route('admin.rotation-groups.index', ['needs_rotation' => true]) }}" 
                       class="text-sm font-medium" style="color: var(--warning);">
                        Review <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- ============================================= -->
    <!-- FILTERS CARD                                   -->
    <!-- ============================================= -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Schedules
                </h3>
                @if(request()->hasAny(['date', 'post_id', 'shift_id', 'status', 'security_user_id', 'search', 'rotation_status', 'preference_score_min', 'rotation_group_id']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('admin.security-schedules.index') }}" class="space-y-4">
                <!-- Row 1: Date, Post, Shift, Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Date
                        </label>
                        <input type="date" 
                               name="date" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date', now()->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i> Security Post
                        </label>
                        <select name="post_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Posts</option>
                            @foreach($securityPosts as $post)
                                <option value="{{ $post->id }}" {{ request('post_id') == $post->id ? 'selected' : '' }}>
                                    {{ $post->name }} ({{ $post->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Shift
                        </label>
                        <select name="shift_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Shifts</option>
                            @foreach($securityShifts as $shift)
                                <option value="{{ $shift->id }}" {{ request('shift_id') == $shift->id ? 'selected' : '' }}>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                    @if($shift->rotation_type === 'rotating') 🔄 @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-flag mr-1" style="color: var(--primary);"></i> Status
                        </label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <!-- Row 2: Personnel, Search, Rotation Status, Preference Score -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-user-shield mr-1" style="color: var(--primary);"></i> Security Personnel
                        </label>
                        <select name="security_user_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Personnel</option>
                            @foreach($securityPersonnel as $person)
                                <option value="{{ $person->id }}" {{ request('security_user_id') == $person->id ? 'selected' : '' }}>
                                    {{ $person->name }} - {{ $person->phone }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                        </label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by name, post, shift..."
                               value="{{ request('search') }}">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Status
                        </label>
                        <select name="rotation_status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Schedules</option>
                            <option value="rotated" {{ request('rotation_status') == 'rotated' ? 'selected' : '' }}>Rotated Only</option>
                            <option value="not_rotated" {{ request('rotation_status') == 'not_rotated' ? 'selected' : '' }}>Not Rotated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-star mr-1" style="color: var(--primary);"></i> Preference Score
                        </label>
                        <select name="preference_score_min" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">Any Score</option>
                            <option value="8" {{ request('preference_score_min') == '8' ? 'selected' : '' }}>High (8-10)</option>
                            <option value="5" {{ request('preference_score_min') == '5' ? 'selected' : '' }}>Medium (5-7)</option>
                            <option value="1" {{ request('preference_score_min') == '1' ? 'selected' : '' }}>Low (1-4)</option>
                        </select>
                    </div>
                </div>

                <!-- Row 3: Shift Category, Rotation Group, Handover Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-sun mr-1" style="color: var(--primary);"></i> Shift Category
                        </label>
                        <select name="shift_category" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Categories</option>
                            <option value="day" {{ request('shift_category') == 'day' ? 'selected' : '' }}>Day Shift</option>
                            <option value="night" {{ request('shift_category') == 'night' ? 'selected' : '' }}>Night Shift</option>
                            <option value="evening" {{ request('shift_category') == 'evening' ? 'selected' : '' }}>Evening Shift</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Group
                        </label>
                        <select name="rotation_group_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Groups</option>
                            @foreach($allRotationGroups as $group)
                                <option value="{{ $group->id }}" {{ request('rotation_group_id') == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }} ({{ $group->post->name ?? 'No Post' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-handshake mr-1" style="color: var(--primary);"></i> Handover Status
                        </label>
                        <select name="handover_status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All</option>
                            <option value="pending" {{ request('handover_status') == 'pending' ? 'selected' : '' }}>Handover Pending</option>
                            <option value="completed" {{ request('handover_status') == 'completed' ? 'selected' : '' }}>Handover Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-hourglass-half mr-1" style="color: var(--primary);"></i> Late/Overtime
                        </label>
                        <select name="late_status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All</option>
                            <option value="late" {{ request('late_status') == 'late' ? 'selected' : '' }}>Late Check-in</option>
                            <option value="overtime" {{ request('late_status') == 'overtime' ? 'selected' : '' }}>Overtime</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2 pt-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.security-schedules.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                    <a href="{{ route('admin.rotation-history.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-chart-line mr-2"></i> Rotation Analytics
                    </a>
                    @if(request()->boolean('show_trashed'))
                    <a href="{{ route('admin.security-schedules.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-eye mr-2"></i> Hide Trashed
                    </a>
                    @else
                    <a href="{{ route('admin.security-schedules.index') }}?show_trashed=true" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-eye mr-2"></i> Show Trashed
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================= -->
    <!-- UPCOMING HANDOVERS CARD                        -->
    <!-- ============================================= -->
    @if(isset($upcomingHandovers) && $upcomingHandovers->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-handshake mr-3" style="color: var(--info);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Upcoming Handovers</h3>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" 
                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    Next 2 hours
                </span>
            </div>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                @foreach($upcomingHandovers as $handover)
                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-exchange-alt text-xl"></i>
                            </div>
                            <div>
                                <div class="flex items-center flex-wrap gap-2">
                                    <h5 class="font-semibold" style="color: var(--text-primary);">{{ $handover->post->name }}</h5>
                                    <span class="text-xs px-2 py-1 rounded-full" 
                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        {{ $handover->shift->name ?? 'N/A' }}
                                    </span>
                                </div>
                                <div class="flex items-center mt-2 space-x-4">
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i> {{ $handover->securityUser->name ?? 'N/A' }}
                                    </span>
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1"></i> 
                                        @php
                                            $handoverTime = $handover->handover_info['handover_start'] ?? 
                                                            (isset($handover->shift) ? substr($handover->shift->end_time, 0, 5) : '--:--');
                                        @endphp
                                        {{ $handoverTime }}
                                    </span>
                                </div>
                                @if($handover->rotation_group_id)
                                <div class="mt-2">
                                    <a href="{{ route('admin.rotation-groups.show', $handover->rotation_group_id) }}" 
                                       class="text-xxs px-2 py-0.5 rounded-full inline-flex items-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-users mr-1"></i> {{ $handover->rotationGroup->name ?? 'Group' }}
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                        <button onclick="completeHandover({{ $handover->id }})" 
                                class="px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary"
                                {{ $handover->handover_completed ? 'disabled' : '' }}>
                            <i class="fas fa-check mr-2"></i> Complete Handover
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================= -->
    <!-- STAFFING ALERTS                                -->
    <!-- ============================================= -->
    @if(isset($understaffedPosts) && $understaffedPosts->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Staffing Alerts</h3>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" 
                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    {{ $understaffedPosts->count() }} posts need attention
                </span>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($understaffedPosts->take(3) as $post)
                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold" style="color: var(--text-primary);">{{ $post['post_name'] ?? $post->name }}</h4>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $post['post']->code ?? $post->code ?? '' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold" style="color: var(--warning);">
                                {{ $post['assigned'] ?? $post->current_personnel ?? 0 }}/{{ $post['required'] ?? $post->max_personnel }}
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Short by {{ ($post['required'] ?? $post->max_personnel) - ($post['assigned'] ?? $post->current_personnel ?? 0) }}
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $post['post']->id ?? $post->id }}" 
                       class="btn-primary w-full py-2 rounded-lg text-sm font-medium flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i> Assign Personnel
                    </a>
                </div>
                @endforeach
            </div>
            @if($understaffedPosts->count() > 3)
            <div class="mt-4 text-center">
                <a href="#" class="text-sm" style="color: var(--primary);">
                    View all {{ $understaffedPosts->count() }} understaffed posts
                </a>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- ============================================= -->
    <!-- BULK ACTIONS CARD                              -->
    <!-- ============================================= -->
    @if($schedules->count() > 0)
    <div class="card" style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 4px solid var(--primary);">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select schedules to perform bulk operations
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            onclick="selectAllSchedules()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" 
                            onclick="deselectAllSchedules()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            
            <div class="mt-4 flex items-center space-x-3">
                <span id="selectedCount" class="text-sm px-3 py-2 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                    0 items selected
                </span>
                
                <!-- Action Dropdown -->
                <select id="bulkActionSelect" 
                        class="form-input px-3 py-2 text-sm rounded-lg border"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-width: 200px;">
                    <option value="">Choose action...</option>
                    
                    <!-- Status Actions -->
                    <optgroup label="Status Actions">
                        <option value="mark_completed">✅ Mark as Completed</option>
                        <option value="mark_absent">❌ Mark as Absent</option>
                        <option value="cancelled">🚫 Cancel Selected</option>
                    </optgroup>
                    
                    <!-- Rotation Actions -->
                    <optgroup label="Rotation Actions">
                        <option value="apply_rotation">🔄 Apply Fair Rotation</option>
                        <option value="swap_groups">🔄 Swap Day/Night Groups</option>
                        <option value="calculate_preferences">⭐ Recalculate Preference Scores</option>
                        <option value="add_to_group">➕ Add to Rotation Group</option>
                        <option value="remove_from_group">➖ Remove from Group</option>
                    </optgroup>
                    
                    <!-- Trash Actions -->
                    <optgroup label="Trash Actions">
                        <option value="trash">🗑️ Move to Trash</option>
                    </optgroup>
                </select>
                
                <button onclick="performBulkAction()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary"
                        id="bulkActionBtn" disabled>
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
                
                <a href="{{ route('admin.rotation-groups.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-sync-alt mr-2"></i> Manage Groups
                </a>
            </div>
            
            <div id="bulkActionStatus" class="mt-3 hidden">
                <!-- Status messages will appear here -->
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================= -->
    <!-- SCHEDULES TABLE CARD                           -->
    <!-- ============================================= -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Security Schedules
                    @if(request()->boolean('show_trashed'))
                    <span class="ml-2 px-2 py-1 text-xs rounded-full" 
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        Showing Trashed Schedules
                    </span>
                    @endif
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $schedules->firstItem() ?? 0 }} to {{ $schedules->lastItem() ?? 0 }} of {{ $schedules->total() }} schedules
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            @if($schedules->count() > 0)
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
                            </th>
                            @endif
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Date</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Post</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Shift</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Personnel</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Check-in</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Check-out</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Rotation</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $schedule)
                            @php
                                $isTrashed = $schedule->trashed();
                                $statusInfo = method_exists($schedule, 'getStatusInfo') ? $schedule->getStatusInfo() : [
                                    'bg' => $schedule->status == 'active' ? 'rgba(var(--success-rgb), 0.1)' : 
                                          ($schedule->status == 'completed' ? 'rgba(var(--info-rgb), 0.1)' : 
                                          ($schedule->status == 'absent' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                          ($schedule->status == 'cancelled' ? 'rgba(var(--warning-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)'))),
                                    'color' => $schedule->status == 'active' ? 'var(--success)' : 
                                              ($schedule->status == 'completed' ? 'var(--info)' : 
                                              ($schedule->status == 'absent' ? 'var(--danger)' : 
                                              ($schedule->status == 'cancelled' ? 'var(--warning)' : 'var(--secondary)'))),
                                    'icon' => $schedule->status == 'active' ? 'play-circle' : 
                                             ($schedule->status == 'completed' ? 'check-circle' : 
                                             ($schedule->status == 'absent' ? 'user-slash' : 
                                             ($schedule->status == 'cancelled' ? 'times-circle' : 'clock'))),
                                    'label' => ucfirst($schedule->status)
                                ];
                                $isRotated = $schedule->is_rotated ?? false;
                                $preferenceScore = $schedule->rotation_preference_score ?? 5;
                                $swapCount = $schedule->rotation_swap_count ?? 0;
                                $rotationGroupType = $schedule->rotation_group_type ?? null;
                                $rotationGroup = $schedule->rotationGroup ?? null;
                            @endphp
                            <tr class="border-b {{ $isTrashed ? 'bg-red-50' : '' }} {{ $isRotated ? 'bg-blue-50' : '' }}" 
                                style="border-color: var(--border-color); {{ $isTrashed ? 'background-color: rgba(var(--danger-rgb), 0.05);' : '' }} {{ $isRotated ? 'background-color: rgba(var(--info-rgb), 0.05);' : '' }}">
                                @if($schedules->count() > 0)
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="schedule-checkbox bulk-checkbox" 
                                           value="{{ $schedule->id }}"
                                           data-rotated="{{ $isRotated ? 'true' : 'false' }}"
                                           data-preference="{{ $preferenceScore }}"
                                           {{ $isTrashed ? 'data-trashed="true"' : '' }}>
                                </td>
                                @endif
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->assignment_date instanceof \Carbon\Carbon ? $schedule->assignment_date->format('M j, Y') : \Carbon\Carbon::parse($schedule->assignment_date)->format('M j, Y') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $schedule->assignment_date instanceof \Carbon\Carbon ? $schedule->assignment_date->format('l') : \Carbon\Carbon::parse($schedule->assignment_date)->format('l') }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        @if($isTrashed)
                                        <div class="mr-2 text-red-500" title="This schedule is in trash">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                        @endif
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->post->name ?? 'N/A' }}
                                            </div>
                                            @if($schedule->post->code ?? false)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Code: {{ $schedule->post->code }}
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->shift->name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $schedule->shift ? substr($schedule->shift->start_time, 0, 5) . ' - ' . substr($schedule->shift->end_time, 0, 5) : 'N/A' }}
                                    </div>
                                    @if($schedule->shift->is_overnight ?? false)
                                    <span class="text-xxs px-2 py-0.5 rounded-full mt-1 inline-block"
                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-moon mr-1"></i> Overnight
                                    </span>
                                    @endif
                                    @if(($schedule->shift->rotation_type ?? 'fixed') === 'rotating')
                                    <span class="text-xxs px-2 py-0.5 rounded-full mt-1 inline-block ml-1"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-sync-alt mr-1"></i> Rotating
                                    </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="avatar mr-3 flex-shrink-0">
                                            {{ strtoupper(substr($schedule->securityUser->name ?? 'N/A', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->securityUser->name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $schedule->securityUser->phone ?? 'N/A' }}
                                            </div>
                                            @if($schedule->rotated_from_user_id && !$isTrashed)
                                            <div class="text-xxs mt-1" style="color: var(--info);" title="Originally assigned to {{ $schedule->rotatedFromUser?->name }}">
                                                <i class="fas fa-exchange-alt mr-1"></i> Rotated
                                            </div>
                                            @endif
                                            @if($rotationGroup && !$isTrashed)
                                            <div class="text-xxs mt-1">
                                                <a href="{{ route('admin.rotation-groups.show', $rotationGroup->id) }}" 
                                                   class="text-info hover:underline">
                                                    <i class="fas fa-users mr-1"></i> {{ $rotationGroup->name }}
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @if($schedule->checkin_time)
                                        <div class="font-medium" style="color: var(--success);">
                                            <i class="fas fa-sign-in-alt mr-1"></i>
                                            {{ $schedule->checkin_time instanceof \Carbon\Carbon ? $schedule->checkin_time->format('H:i') : \Carbon\Carbon::parse($schedule->checkin_time)->format('H:i') }}
                                        </div>
                                        @if($schedule->late_minutes > 0)
                                            <div class="text-xs mt-1" style="color: var(--danger);">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                {{ $schedule->late_minutes }} min late
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-minus-circle mr-1"></i> Not checked in
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($schedule->checkout_time)
                                        <div class="font-medium" style="color: var(--info);">
                                            <i class="fas fa-sign-out-alt mr-1"></i>
                                            {{ $schedule->checkout_time instanceof \Carbon\Carbon ? $schedule->checkout_time->format('H:i') : \Carbon\Carbon::parse($schedule->checkout_time)->format('H:i') }}
                                        </div>
                                        @if($schedule->overtime_minutes > 0)
                                            <div class="text-xs mt-1" style="color: var(--warning);">
                                                <i class="fas fa-hourglass-end mr-1"></i>
                                                {{ $schedule->overtime_minutes }} min overtime
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-minus-circle mr-1"></i> Not checked out
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($isTrashed)
                                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                                              style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash mr-1"></i> Trashed
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                                              style="background-color: {{ $statusInfo['bg'] }}; color: {{ $statusInfo['color'] }};">
                                            <i class="fas fa-{{ $statusInfo['icon'] }} mr-1"></i>
                                            {{ $statusInfo['label'] }}
                                        </span>
                                    @endif
                                    @if($schedule->handover_info && !$isTrashed)
                                    <div class="text-xs mt-1" style="color: var(--info);">
                                        <i class="fas fa-exchange-alt mr-1"></i>
                                        @if($schedule->handover_completed)
                                            Handover completed
                                        @else
                                            Handover pending
                                        @endif
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($isRotated)
                                        <div class="flex items-center mb-1">
                                            <span class="px-2 py-0.5 rounded-full text-xxs font-medium"
                                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-sync-alt mr-1"></i> Rotated
                                            </span>
                                        </div>
                                        @if($swapCount > 0)
                                        <div class="text-xxs" style="color: var(--text-secondary);">
                                            Swapped {{ $swapCount }} time(s)
                                        </div>
                                        @endif
                                    @endif
                                    
                                    <div class="mt-1 flex items-center">
                                        @php
                                            $scoreColor = $preferenceScore >= 8 ? 'success' : ($preferenceScore >= 5 ? 'warning' : 'danger');
                                        @endphp
                                        <span class="text-xxs mr-1" style="color: var(--{{ $scoreColor }});">
                                            <i class="fas fa-star"></i>
                                        </span>
                                        <span class="text-xxs" style="color: var(--text-secondary);">
                                            Score: {{ $preferenceScore }}/10
                                        </span>
                                    </div>
                                    
                                    @if($rotationGroupType && !$isTrashed)
                                    <div class="text-xxs mt-1 px-2 py-0.5 rounded-full inline-block"
                                         style="background-color: rgba(var(--info-rgb), 0.05); color: var(--info);">
                                        {{ ucfirst($rotationGroupType) }} group
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        @if($isTrashed)
                                            <button onclick="restoreSchedule({{ $schedule->id }})"
                                                    class="action-btn" 
                                                    title="Restore"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button onclick="showForceDeleteModal({{ $schedule->id }})"
                                                    class="action-btn" 
                                                    title="Permanently Delete"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <a href="{{ route('admin.security-schedules.show', $schedule->id) }}" 
                                               class="action-btn" 
                                               title="View Details"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('admin.security-schedules.show', $schedule->id) }}" 
                                               class="action-btn" 
                                               title="View Details"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($schedule->status == 'scheduled')
                                                <button onclick="checkInSchedule({{ $schedule->id }})"
                                                        class="action-btn" 
                                                        title="Check In"
                                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                    <i class="fas fa-sign-in-alt"></i>
                                                </button>
                                                
                                                <button onclick="markAbsent({{ $schedule->id }})"
                                                        class="action-btn" 
                                                        title="Mark Absent"
                                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                                
                                                <button onclick="showSwapModal({{ $schedule->id }})"
                                                        class="action-btn" 
                                                        title="Swap Shift"
                                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                
                                                @if($isRotated || $schedule->isEligibleForRotation() ?? false)
                                                <button onclick="showRotateModal({{ $schedule->id }}, {{ $preferenceScore }})"
                                                        class="action-btn" 
                                                        title="Apply Rotation"
                                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                @endif
                                            @endif
                                            
                                            @if($schedule->status == 'active')
                                                <button onclick="checkOutSchedule({{ $schedule->id }})"
                                                        class="action-btn" 
                                                        title="Check Out"
                                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </button>
                                                
                                                @if($schedule->include_breaks)
                                                <button onclick="startBreak({{ $schedule->id }})"
                                                        class="action-btn" 
                                                        title="Start Break"
                                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-coffee"></i>
                                                </button>
                                                @endif
                                            @endif
                                            
                                            <a href="{{ route('admin.security-schedules.edit', $schedule->id) }}" 
                                               class="action-btn" 
                                               title="Edit"
                                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <button onclick="showDeleteModal({{ $schedule->id }})"
                                                    class="action-btn" 
                                                    title="Delete"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <button onclick="viewRotationHistory({{ $schedule->id }})"
                                                    class="action-btn" 
                                                    title="Rotation History"
                                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            
                                            @if($rotationGroup)
                                            <a href="{{ route('admin.rotation-groups.show', $rotationGroup->id) }}" 
                                               class="action-btn" 
                                               title="View Group"
                                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-calendar-times text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No schedules found</p>
                                        <p style="color: var(--text-secondary);">Try adjusting your filters or create a new schedule</p>
                                        <div class="mt-4 flex space-x-3">
                                            <a href="{{ route('admin.security-schedules.create') }}" class="px-4 py-2 rounded-lg btn-primary text-white">
                                                Create Schedule
                                            </a>
                                            <a href="{{ route('admin.rotation-groups.index') }}" class="px-4 py-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                Manage Rotation Groups
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($schedules->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $schedules->firstItem() }} to {{ $schedules->lastItem() }} of {{ $schedules->total() }} entries
                        </div>
                        <div class="flex space-x-2">
                            {{ $schedules->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODALS (Rotate, Swap, Delete, etc.)           -->
<!-- ============================================= -->

<!-- Rotate Modal -->
<div id="rotateModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rotateModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Apply Rotation</h3>
            <button type="button" class="modal-close" onclick="closeModal('rotateModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="rotateForm">
            @csrf
            <input type="hidden" name="schedule_id" id="rotateScheduleId">
            <div class="modal-body">
                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
                    <p class="text-sm" style="color: var(--text-primary);">Current preference score: <strong id="currentPreferenceScore">5</strong>/10</p>
                </div>
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Rotation Pattern</label>
                    <select name="rotation_pattern" class="form-input w-full p-3 rounded-lg border" required>
                        <option value="full_swap">Full Swap</option>
                        <option value="staggered">Staggered</option>
                        <option value="partial">Based on Preferences</option>
                    </select>
                </div>
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Rotation Group</label>
                    <select name="rotation_group_id" class="form-input w-full p-3 rounded-lg border">
                        <option value="">No Group</option>
                        @foreach($allRotationGroups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Reason</label>
                    <textarea name="reason" 
                              class="form-input w-full p-3 rounded-lg border"
                              rows="3" 
                              required
                              placeholder="Why are you applying this rotation?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('rotateModal')">Cancel</button>
                <button type="button" class="btn-primary" onclick="confirmRotation()">Apply Rotation</button>
            </div>
        </form>
    </div>
</div>

<!-- Swap Modal -->
<div id="swapModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('swapModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Swap Shift</h3>
            <button type="button" class="modal-close" onclick="closeModal('swapModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="swapForm">
            @csrf
            <input type="hidden" name="schedule_id" id="swapScheduleId">
            <div class="modal-body">
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Swap With</label>
                    <select name="new_user_id" 
                            class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                            required>
                        <option value="">Select Personnel</option>
                        @foreach($securityPersonnel as $person)
                            <option value="{{ $person->id }}">{{ $person->name }} - {{ $person->phone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Reason</label>
                    <textarea name="reason" 
                              class="form-input w-full p-3 rounded-lg border"
                              rows="3" 
                              required
                              placeholder="Why are you swapping this shift?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('swapModal')">Cancel</button>
                <button type="button" class="btn-primary" onclick="confirmSwap()">Swap Shift</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Move to Trash</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body text-center">
            <i class="fas fa-trash text-5xl mb-4" style="color: var(--warning);"></i>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="deleteScheduleInfo"></h4>
            <p style="color: var(--text-secondary);">Move this schedule to trash? You can restore it later.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            <form id="deleteForm" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white" style="background-color: var(--warning);">Move to Trash</button>
            </form>
        </div>
    </div>
</div>

<!-- Force Delete Modal -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete</h3>
            <button type="button" class="modal-close" onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body text-center">
            <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="forceDeleteScheduleInfo"></h4>
            <p class="text-red-600 font-bold">This action cannot be undone!</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('forceDeleteModal')">Cancel</button>
            <form id="forceDeleteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" class="btn-primary" onclick="confirmForceDelete()">Permanently Delete</button>
        </div>
    </div>
</div>

<!-- Rotation History Modal -->
<div id="rotationHistoryModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rotationHistoryModal')"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Rotation History</h3>
            <button type="button" class="modal-close" onclick="closeModal('rotationHistoryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="rotationHistoryContent" class="space-y-3">
                <!-- History items will be inserted here -->
            </div>
            <div class="mt-4 text-center">
                <a href="#" id="viewFullHistoryLink" class="text-sm" style="color: var(--info);">View Full History</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('rotationHistoryModal')">Close</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- FIXED: Debug script to check if auth variables are being passed correctly -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DEBUG: Security Schedules Index Page ===');
    console.log('Auth variables from PHP:');
    console.log('isSuperAdmin:', {{ isset($isSuperAdmin) ? ($isSuperAdmin ? 'true' : 'false') : 'null' }});
    console.log('isAdmin:', {{ isset($isAdmin) ? ($isAdmin ? 'true' : 'false') : 'null' }});
    console.log('isAuthorized:', {{ isset($isAuthorized) ? ($isAuthorized ? 'true' : 'false') : 'null' }});
    console.log('currentUserRole:', {{ isset($currentUserRole) ? $currentUserRole : 'null' }});
    
    // Check if search elements exist in the DOM
    setTimeout(() => {
        console.log('=== DOM Element Check ===');
        console.log('Advanced search button exists:', !!document.getElementById('advancedSearchBtn'));
        console.log('Search modal exists:', !!document.getElementById('searchModal'));
        console.log('Quick search input exists:', !!document.getElementById('quickSearchInput'));
        
        // If elements don't exist, check if the component is loaded
        const sidebar = document.querySelector('.sidebar');
        console.log('Sidebar exists:', !!sidebar);
        
        if (sidebar) {
            console.log('Sidebar found, checking for search elements inside...');
            console.log('Search button in sidebar:', !!sidebar.querySelector('#advancedSearchBtn'));
        } else {
            console.log('WARNING: Sidebar not found! The component might not be included in the layout.');
        }
    }, 500);
});
</script>

@endsection

@section('scripts')
<script>
// =============================================
// GLOBAL VARIABLES
// =============================================
let selectedScheduleIds = [];
let currentScheduleId = null;
let currentPreferenceScore = 5;

// =============================================
// INITIALIZATION
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    initializeBulkSelection();
    initializeActionButtons();
});

// =============================================
// BULK SELECTION FUNCTIONS
// =============================================
function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const scheduleCheckboxes = document.querySelectorAll('.schedule-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            scheduleCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    scheduleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            
            // Update select all checkbox state
            if (selectAllCheckbox) {
                const allCheckboxes = document.querySelectorAll('.schedule-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.schedule-checkbox:checked');
                selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            }
        });
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    const selectedCount = checkboxes.length;
    const countElement = document.getElementById('selectedCount');
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    
    if (countElement) {
        countElement.textContent = selectedCount + ' item' + (selectedCount !== 1 ? 's' : '') + ' selected';
    }
    
    if (bulkActionBtn) {
        bulkActionBtn.disabled = selectedCount === 0;
        if (selectedCount > 0) {
            bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply (${selectedCount})`;
        } else {
            bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply`;
        }
    }
}

function selectAllSchedules() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
    
    updateSelectedCount();
}

function deselectAllSchedules() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
    
    updateSelectedCount();
}

function getSelectedScheduleIds() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// =============================================
// BULK ACTION FUNCTIONS
// =============================================
async function performBulkAction() {
    const actionSelect = document.getElementById('bulkActionSelect');
    const selectedAction = actionSelect.value;
    const selectedIds = getSelectedScheduleIds();
    
    if (!selectedAction) {
        showToast('Please select an action', 'warning');
        return;
    }
    
    if (selectedIds.length === 0) {
        showToast('Please select at least one schedule', 'warning');
        return;
    }
    
    if (!confirm(`Apply ${selectedAction} to ${selectedIds.length} schedule(s)?`)) {
        return;
    }
    
    showLoading(`Processing ${selectedIds.length} schedule(s)...`);
    
    try {
        const response = await fetch('{{ route("admin.security-schedules.bulk-update") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                schedule_ids: selectedIds, 
                action: selectedAction 
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            showToast(data.message, 'success');
            deselectAllSchedules();
            actionSelect.value = '';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to perform action', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Bulk action error:', error);
        showToast('Network error occurred', 'error');
    }
}

// =============================================
// SCHEDULE ACTION FUNCTIONS
// =============================================
function showRotateModal(scheduleId, preferenceScore) {
    document.getElementById('rotateScheduleId').value = scheduleId;
    document.getElementById('currentPreferenceScore').textContent = preferenceScore;
    openModal('rotateModal');
}

function showSwapModal(scheduleId) {
    document.getElementById('swapScheduleId').value = scheduleId;
    openModal('swapModal');
}

function showDeleteModal(scheduleId) {
    document.getElementById('deleteScheduleInfo').textContent = 'Schedule #' + scheduleId;
    document.getElementById('deleteForm').action = `{{ url('admin/security-schedules') }}/${scheduleId}`;
    openModal('deleteModal');
}

function showForceDeleteModal(scheduleId) {
    document.getElementById('forceDeleteScheduleInfo').textContent = 'Schedule #' + scheduleId;
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.action = `{{ url('admin/security-schedules') }}/${scheduleId}/force-delete`;
    openModal('forceDeleteModal');
}

function restoreSchedule(scheduleId) {
    if (!confirm('Restore this schedule?')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `{{ url('admin/security-schedules/trash') }}/${scheduleId}/restore`;
    form.style.display = 'none';
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '{{ csrf_token() }}';
    form.appendChild(csrfToken);
    
    const methodField = document.createElement('input');
    methodField.type = 'hidden';
    methodField.name = '_method';
    methodField.value = 'PATCH';
    form.appendChild(methodField);
    
    document.body.appendChild(form);
    form.submit();
}

function checkInSchedule(scheduleId) {
    updateScheduleStatus(scheduleId, 'checkin');
}

function checkOutSchedule(scheduleId) {
    updateScheduleStatus(scheduleId, 'checkout');
}

function markAbsent(scheduleId) {
    if (confirm('Mark as absent?')) {
        updateScheduleStatus(scheduleId, 'mark_absent');
    }
}

function startBreak(scheduleId) {
    updateScheduleStatus(scheduleId, 'start_break');
}

function completeHandover(scheduleId) {
    if (confirm('Complete handover?')) {
        updateScheduleStatus(scheduleId, 'complete_handover');
    }
}

function viewRotationHistory(scheduleId) {
    fetch(`{{ url('admin/security-schedules') }}/${scheduleId}/rotation-history`)
        .then(response => response.json())
        .then(data => {
            const content = document.getElementById('rotationHistoryContent');
            if (data.history?.length) {
                content.innerHTML = data.history.map(h => `
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="flex justify-between items-start">
                            <div class="text-sm font-medium" style="color: var(--text-primary);">${h.date}</div>
                            ${h.group ? `<span class="text-xxs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${h.group}</span>` : ''}
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">${h.description}</p>
                        ${h.reason ? `<p class="text-xxs mt-1" style="color: var(--text-secondary);">Reason: ${h.reason}</p>` : ''}
                    </div>
                `).join('');
                
                document.getElementById('viewFullHistoryLink').href = `{{ url('admin/rotation-history/schedule') }}/${scheduleId}`;
            } else {
                content.innerHTML = '<p class="text-center" style="color: var(--text-secondary);">No rotation history found</p>';
                document.getElementById('viewFullHistoryLink').href = `{{ url('admin/rotation-history') }}`;
            }
            openModal('rotationHistoryModal');
        });
}

function updateScheduleStatus(scheduleId, action) {
    fetch(`{{ url('admin/security-schedules') }}/${scheduleId}/status`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        }
    });
}

function confirmRotation() {
    const form = document.getElementById('rotateForm');
    const formData = new FormData(form);
    
    fetch(`{{ url('admin/security-schedules') }}/${formData.get('schedule_id')}/rotate`, {
        method: 'POST',
        headers: { 
            'X-CSRF-TOKEN': '{{ csrf_token() }}', 
            'Content-Type': 'application/json' 
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Rotation applied', 'success');
            closeModal('rotateModal');
            setTimeout(() => window.location.reload(), 1500);
        }
    });
}

function confirmSwap() {
    const form = document.getElementById('swapForm');
    const formData = new FormData(form);
    
    fetch(`{{ url('admin/security-schedules') }}/${formData.get('schedule_id')}/swap`, {
        method: 'POST',
        headers: { 
            'X-CSRF-TOKEN': '{{ csrf_token() }}', 
            'Content-Type': 'application/json' 
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Swap completed', 'success');
            closeModal('swapModal');
            setTimeout(() => window.location.reload(), 1500);
        }
    });
}

function confirmForceDelete() {
    document.getElementById('forceDeleteForm').submit();
}

// =============================================
// UI UTILITIES
// =============================================
function openModal(id) {
    document.getElementById(id)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id)?.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container') || (() => {
        const c = document.createElement('div');
        c.id = 'toast-container';
        c.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(c);
        return c;
    })();
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    toast.innerHTML = `
        <span class="text-sm">${message}</span>
        <button class="ml-4" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function showLoading(message = 'Loading...') {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overlay.innerHTML = `
            <div class="bg-white p-5 rounded-lg shadow-xl text-center">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto mb-3"></div>
                <div class="text-gray-700" id="loadingMessage"></div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    document.getElementById('loadingMessage').textContent = message;
}

function hideLoading() {
    document.getElementById('loadingOverlay')?.remove();
}

function initializeActionButtons() {
    // Removed hover effects to eliminate white layer
}

// Close modals on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => {
            if (!m.classList.contains('hidden')) {
                m.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
    
    // Ctrl+A for select all
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        if (document.querySelector('.schedule-checkbox')) {
            selectAllSchedules();
        }
    }
});

// Close modals on overlay click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.closest('.modal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
});
</script>
@endsection

{{-- ============================================================ --}}
{{-- STYLES — moved OUT of @section('scripts') into @push('styles') --}}
{{-- so they render in <head> via the layout's @stack('styles'),    --}}
{{-- and don't leak into the header/sidebar.                        --}}
{{-- The global `:root` block and `.hidden` rules have been scoped  --}}
{{-- to only the elements this page controls, and .card/.btn/.form- --}}
{{-- input/.modal rules are scoped to #security-schedules-page and  --}}
{{-- the modal IDs so they don't override the header's styles.      --}}
{{-- ============================================================ --}}
@push('styles')
<style>
/* ============================================================ */
/* Note: we no longer redeclare :root variables — the layout's   */
/* theme system already provides --primary, --success, etc.      */
/* Redeclaring them here was overriding the whole app.            */
/* ============================================================ */

/* Card — scoped to this page so header cards aren't affected */
#security-schedules-page .card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
}

/* Primary button — scoped */
#security-schedules-page .btn-primary,
#rotateModal .btn-primary,
#swapModal .btn-primary,
#deleteModal .btn-primary,
#forceDeleteModal .btn-primary,
#rotationHistoryModal .btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

#security-schedules-page .btn-primary:hover,
#rotateModal .btn-primary:hover,
#swapModal .btn-primary:hover,
#deleteModal .btn-primary:hover,
#forceDeleteModal .btn-primary:hover,
#rotationHistoryModal .btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

#security-schedules-page .btn-primary:disabled,
#rotateModal .btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Secondary button — scoped */
#security-schedules-page .btn-secondary,
#rotateModal .btn-secondary,
#swapModal .btn-secondary,
#deleteModal .btn-secondary,
#forceDeleteModal .btn-secondary,
#rotationHistoryModal .btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Action button */
#security-schedules-page .action-btn {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

#security-schedules-page .action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Form inputs scoped to this page + modals (so we don't clobber header inputs) */
#security-schedules-page .form-input,
#security-schedules-page select,
#security-schedules-page textarea,
#rotateModal .form-input,
#rotateModal select,
#rotateModal textarea,
#swapModal .form-input,
#swapModal select,
#swapModal textarea,
#deleteModal .form-input,
#deleteModal select,
#deleteModal textarea,
#forceDeleteModal .form-input,
#forceDeleteModal select,
#forceDeleteModal textarea,
#rotationHistoryModal .form-input,
#rotationHistoryModal select,
#rotationHistoryModal textarea {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
}

#security-schedules-page .form-input:focus,
#security-schedules-page select:focus,
#security-schedules-page textarea:focus,
#rotateModal .form-input:focus,
#swapModal .form-input:focus,
#deleteModal .form-input:focus,
#forceDeleteModal .form-input:focus,
#rotationHistoryModal .form-input:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Avatar */
#security-schedules-page .avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Bulk checkbox */
#security-schedules-page .bulk-checkbox {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s ease;
}

#security-schedules-page .bulk-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

#security-schedules-page .bulk-checkbox:checked::after {
    content: '✓';
    color: white;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

/* Modal (only when it's inside this page's modal IDs) */
#rotateModal.modal,
#swapModal.modal,
#deleteModal.modal,
#forceDeleteModal.modal,
#rotationHistoryModal.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

#rotateModal .modal-overlay,
#swapModal .modal-overlay,
#deleteModal .modal-overlay,
#forceDeleteModal .modal-overlay,
#rotationHistoryModal .modal-overlay {
    position: absolute;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

#rotateModal .modal-container,
#swapModal .modal-container,
#deleteModal .modal-container,
#forceDeleteModal .modal-container,
#rotationHistoryModal .modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

#rotateModal .modal-header,
#swapModal .modal-header,
#deleteModal .modal-header,
#forceDeleteModal .modal-header,
#rotationHistoryModal .modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#rotateModal .modal-title,
#swapModal .modal-title,
#deleteModal .modal-title,
#forceDeleteModal .modal-title,
#rotationHistoryModal .modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

#rotateModal .modal-close,
#swapModal .modal-close,
#deleteModal .modal-close,
#forceDeleteModal .modal-close,
#rotationHistoryModal .modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
}

#rotateModal .modal-body,
#swapModal .modal-body,
#deleteModal .modal-body,
#forceDeleteModal .modal-body,
#rotationHistoryModal .modal-body {
    padding: 1.5rem;
}

#rotateModal .modal-footer,
#swapModal .modal-footer,
#deleteModal .modal-footer,
#forceDeleteModal .modal-footer,
#rotationHistoryModal .modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

.text-xxs {
    font-size: 0.625rem;
}

@media (max-width: 768px) {
    #rotateModal .modal-container,
    #swapModal .modal-container,
    #deleteModal .modal-container,
    #forceDeleteModal .modal-container,
    #rotationHistoryModal .modal-container {
        margin: 1rem;
    }
    #security-schedules-page .action-btn {
        width: 32px;
        height: 32px;
    }
}

/* ============================================================ */
/* SCOPED .hidden OVERRIDE                                      */
/* The previous GLOBAL `.hidden { display: none !important; }`  */
/* was leaking into the header (and Alpine's x-show, etc.).     */
/* Now scoped to the specific modal IDs this page controls.      */
/* ============================================================ */

#rotateModal.hidden,
#swapModal.hidden,
#deleteModal.hidden,
#forceDeleteModal.hidden,
#rotationHistoryModal.hidden,
#bulkActionStatus.hidden,
#security-schedules-page .hidden {
    display: none !important;
}
</style>
@endpush