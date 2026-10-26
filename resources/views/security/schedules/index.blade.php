@extends('layouts.secu')

@section('title', 'Security Schedules')

{{-- Capability (composer usually provides it; fallback recomputes defensively) --}}
@php
    $__caps       = \App\Providers\AppServiceProvider::resolveSecurityCapabilities();
    $canManage    = $canManage ?? $__caps['canManage'];

    // Route existence (avoids 500s if a route is missing)
    $__hasCreate          = \Illuminate\Support\Facades\Route::has('security.schedules.create');
    $__hasTrash           = \Illuminate\Support\Facades\Route::has('security.schedules.trash');
    $__hasBulkUpdate      = \Illuminate\Support\Facades\Route::has('security.schedules.bulk-update');
    $__hasBulkRestore     = \Illuminate\Support\Facades\Route::has('security.schedules.bulk-restore');
    $__hasBulkForceDelete = \Illuminate\Support\Facades\Route::has('security.schedules.bulk-force-delete');
    $__hasUpdateStatus    = \Illuminate\Support\Facades\Route::has('security.schedules.update-status');
    $__hasShow            = \Illuminate\Support\Facades\Route::has('security.schedules.show');
    $__hasEdit            = \Illuminate\Support\Facades\Route::has('security.schedules.edit');
    $__hasDestroy         = \Illuminate\Support\Facades\Route::has('security.schedules.destroy');
    $__hasRestore         = \Illuminate\Support\Facades\Route::has('security.schedules.restore');
    $__hasForceDelete     = \Illuminate\Support\Facades\Route::has('security.schedules.force-delete');
    $__hasRotateGroups    = \Illuminate\Support\Facades\Route::has('security.schedules.rotate-shift-groups');
    $__hasRotationHistory = \Illuminate\Support\Facades\Route::has('security.schedules.rotation-group.history');

    // ----------------------------------------------------------------------
    // Rotations now belong to the SECURITY area (not admin anymore)
    // ----------------------------------------------------------------------
    $__hasRotationGroups         = \Illuminate\Support\Facades\Route::has('security.rotation-groups.index');
    $__hasRotationGroupsShow     = \Illuminate\Support\Facades\Route::has('security.rotation-groups.show');
    $__hasRotationHistory        = \Illuminate\Support\Facades\Route::has('security.rotation-history.index');
    $__hasRotationHistoryByGroup = \Illuminate\Support\Facades\Route::has('security.rotation-history.group');
    $__hasRotationHistoryByPost  = \Illuminate\Support\Facades\Route::has('security.rotation-history.post');

    // Statistics — reuse controller data when present
    if (!isset($stats) || empty($stats)) {
        $today = now()->format('Y-m-d');
        $todaySchedules = $schedules->filter(function ($schedule) use ($today) {
            return $schedule->assignment_date instanceof \Carbon\Carbon
                ? $schedule->assignment_date->format('Y-m-d') === $today
                : $schedule->assignment_date === $today;
        });
        $stats = [
            'total_schedules'  => $schedules->total(),
            'active_now'       => $todaySchedules->where('status', 'active')->count(),
            'today_schedules'  => $todaySchedules->count(),
            'rotated_count'    => $schedules->where('is_rotated', true)->count(),
            'completed_today'  => $todaySchedules->where('status', 'completed')->count(),
            'absent_today'     => $todaySchedules->where('status', 'absent')->count(),
            'overtime_hours'   => round($schedules->sum('overtime_minutes') / 60, 1),
            'high_preference_count' => $schedules->where('rotation_preference_score', '>=', 8)->count(),
        ];
    }

    if (!isset($understaffedPosts) && isset($todaySummary['post_coverage'])) {
        $understaffedPosts = collect($todaySummary['post_coverage'])->filter(
            fn ($post) => $post['assigned'] < $post['required']
        );
    }

    $trashCount = $__hasTrash ? \App\Models\SecuritySchedule::onlyTrashed()->count() : 0;

    // NOTE: RotationGroup has a `status` column, not `is_active`
    $rotationGroups = class_exists(\App\Models\RotationGroup::class)
        ? \App\Models\RotationGroup::with(['post', 'shift'])
            ->where('status', 'active')->limit(5)->get()
        : collect();

    // NOTE: the column is `rotation_config` (JSON), not `configuration`
    $pendingRotations = class_exists(\App\Models\RotationGroup::class)
        ? \App\Models\RotationGroup::whereIn('group_type', ['day', 'night', 'evening'])
            ->whereNotNull('rotation_config')
            ->whereRaw('JSON_EXTRACT(rotation_config, "$.next_rotation_date") IS NOT NULL')
            ->whereRaw('JSON_EXTRACT(rotation_config, "$.next_rotation_date") <= ?', [now()->addDays(3)->format('Y-m-d')])
            ->count()
        : 0;

    $allRotationGroups = class_exists(\App\Models\RotationGroup::class)
        ? \App\Models\RotationGroup::with('post')->where('status', 'active')->get()
        : collect();
@endphp

@section('content')
<div id="security-schedules-page" class="grid grid-cols-1 gap-6 mb-6">
    {{-- Header Card --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
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
                        <span>Head of Security: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 flex-wrap gap-2">
                @if($__hasRotationGroups)
                    <a href="{{ route('security.rotation-groups.index') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-sync-alt mr-2"></i> Rotation Groups
                    </a>
                @endif

                @if($__hasRotationHistory)
                    <a href="{{ route('security.rotation-history.index') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chart-line mr-2"></i> Analytics
                    </a>
                @endif

                @if($__hasCreate)
                    <a href="{{ route('security.schedules.create') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-plus mr-2"></i> Assign Schedule
                    </a>
                @endif

                @if($__hasTrash)
                    <a href="{{ route('security.schedules.trash') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                       style="background-color: rgba(var(--{{ $trashCount > 0 ? 'danger' : 'secondary' }}-rgb), 0.1); color: var(--{{ $trashCount > 0 ? 'danger' : 'secondary' }}); border: 1px solid rgba(var(--{{ $trashCount > 0 ? 'danger' : 'secondary' }}-rgb), 0.3);">
                        <i class="fas fa-trash mr-2"></i> Trash
                        @if($trashCount > 0)
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ $trashCount }}
                            </span>
                        @endif
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Statistics --}}
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
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.1) 0%, rgba(var(--warning-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--warning);">{{ $stats['rotated_count'] ?? 0 }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Rotated Schedules</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                            <i class="fas fa-sync-alt text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.1) 0%, rgba(var(--info-rgb), 0.05) 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold" style="color: var(--info);">{{ \App\Models\RotationGroup::where('status', 'active')->count() }}</div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Groups</div>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                            <i class="fas fa-users-cog text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">Completed Today: <strong style="color: var(--success);">{{ $todaySummary['completed'] ?? $stats['completed_today'] ?? 0 }}</strong></span>
                    </div>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-user-slash mr-2" style="color: var(--danger);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">Absent Today: <strong style="color: var(--danger);">{{ $todaySummary['absent'] ?? $stats['absent_today'] ?? 0 }}</strong></span>
                    </div>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">Late Today: <strong style="color: var(--warning);">{{ $todaySummary['late_checkins'] ?? 0 }}</strong></span>
                    </div>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-hourglass-end mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">Overtime Hours: <strong style="color: var(--warning);">{{ $stats['overtime_hours'] ?? 0 }}h</strong></span>
                    </div>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-history mr-2" style="color: var(--info);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">Rotations Today: <strong style="color: var(--info);">{{ \App\Models\SecuritySchedule::whereDate('rotated_at', today())->count() }}</strong></span>
                    </div>
                </div>
            </div>

            @if(isset($todaySummary['post_coverage']) && count($todaySummary['post_coverage']) > 0)
            <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-medium flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                        Today's Post Coverage
                    </h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($todaySummary['post_coverage'] as $post)
                    @php
                        $coveragePercent = $post['coverage_rate'] ?? 0;
                        $statusColor = $coveragePercent >= 100 ? 'success' : ($coveragePercent >= 75 ? 'info' : ($coveragePercent >= 50 ? 'warning' : 'danger'));
                    @endphp
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">{{ $post['post_name'] }}</span>
                            <span class="text-xs font-semibold" style="color: var(--{{ $statusColor }});">{{ $post['assigned'] }}/{{ $post['required'] }}</span>
                        </div>
                        <div class="overflow-hidden h-2 text-xs flex rounded" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2);">
                            <div style="width: {{ min($coveragePercent, 100) }}%; background-color: var(--{{ $statusColor }});"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
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

    {{-- Rotation Groups Quick View --}}
    @if($rotationGroups->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i> Active Rotation Groups
                </h3>
                @if($__hasRotationGroups)
                    <a href="{{ route('security.rotation-groups.index') }}" class="text-sm" style="color: var(--primary);">View All <i class="fas fa-arrow-right ml-1"></i></a>
                @endif
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach($rotationGroups as $group)
                @php
                    $memberCount = $group->members()->count();
                    $nextRotation = $group->rotation_config['next_rotation_date'] ?? null;
                    $daysUntil = $nextRotation ? now()->diffInDays(\Carbon\Carbon::parse($nextRotation)) : null;
                    $isUrgent = $daysUntil !== null && $daysUntil <= 2;
                    $isRotating = in_array($group->group_type, ['day', 'night', 'evening']);
                @endphp
                <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">{{ $group->name }}</h4>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $group->post->name ?? 'N/A' }} • {{ $group->shift->name ?? 'N/A' }}</p>
                        </div>
                        <span class="px-2 py-1 text-xxs rounded-full" style="background-color: rgba(var(--{{ $group->group_type === 'night' ? 'info' : ($group->group_type === 'day' ? 'warning' : ($group->group_type === 'evening' ? 'secondary' : 'primary')) }}-rgb), 0.1); color: var(--{{ $group->group_type === 'night' ? 'info' : ($group->group_type === 'day' ? 'warning' : ($group->group_type === 'evening' ? 'secondary' : 'primary')) }});">
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
                        @if($__hasRotationGroupsShow)
                            <a href="{{ route('security.rotation-groups.show', $group->id) }}"
                               class="flex-1 py-1.5 text-xs rounded text-center"
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                View
                            </a>
                        @endif
                        @if($__hasRotationHistoryByGroup)
                            <a href="{{ route('security.rotation-history.group', $group->id) }}"
                               class="flex-1 py-1.5 text-xs rounded text-center"
                               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                History
                            </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @if($pendingRotations > 0)
            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--text-primary);">{{ $pendingRotations }} rotating group(s) need attention in the next 3 days</span>
                    </div>
                    @if($__hasRotationGroups)
                        <a href="{{ route('security.rotation-groups.index', ['needs_rotation' => true]) }}" class="text-sm font-medium" style="color: var(--warning);">
                            Review <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Schedules
                </h3>
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('security.schedules.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Date</label>
                        <input type="date" name="date" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" value="{{ request('date', now()->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i> Security Post</label>
                        <select name="post_id" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Posts</option>
                            @foreach($securityPosts as $post)
                                <option value="{{ $post->id }}" {{ request('post_id') == $post->id ? 'selected' : '' }}>{{ $post->name }} ({{ $post->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Shift</label>
                        <select name="shift_id" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Shifts</option>
                            @foreach($securityShifts as $shift)
                                <option value="{{ $shift->id }}" {{ request('shift_id') == $shift->id ? 'selected' : '' }}>{{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-flag mr-1" style="color: var(--primary);"></i> Status</label>
                        <select name="status" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            @foreach(['scheduled','active','completed','absent','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-user-shield mr-1" style="color: var(--primary);"></i> Security Personnel</label>
                        <select name="security_user_id" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Personnel</option>
                            @foreach($securityPersonnel as $person)
                                <option value="{{ $person->id }}" {{ request('security_user_id') == $person->id ? 'selected' : '' }}>{{ $person->name }} - {{ $person->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search</label>
                        <input type="text" name="search" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" placeholder="Search by name, post, shift..." value="{{ request('search') }}">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Status</label>
                        <select name="rotation_status" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Schedules</option>
                            <option value="rotated" {{ request('rotation_status') == 'rotated' ? 'selected' : '' }}>Rotated Only</option>
                            <option value="not_rotated" {{ request('rotation_status') == 'not_rotated' ? 'selected' : '' }}>Not Rotated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-star mr-1" style="color: var(--primary);"></i> Preference Score</label>
                        <select name="preference_score_min" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">Any Score</option>
                            <option value="8" {{ request('preference_score_min') == '8' ? 'selected' : '' }}>High (8-10)</option>
                            <option value="5" {{ request('preference_score_min') == '5' ? 'selected' : '' }}>Medium (5-7)</option>
                            <option value="1" {{ request('preference_score_min') == '1' ? 'selected' : '' }}>Low (1-4)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-sun mr-1" style="color: var(--primary);"></i> Shift Category</label>
                        <select name="shift_category" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Categories</option>
                            @foreach(['day','night','evening'] as $c)
                                <option value="{{ $c }}" {{ request('shift_category') == $c ? 'selected' : '' }}>{{ ucfirst($c) }} Shift</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Group</label>
                        <select name="rotation_group_id" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Groups</option>
                            @foreach($allRotationGroups as $group)
                                <option value="{{ $group->id }}" {{ request('rotation_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }} ({{ $group->post->name ?? 'No Post' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-handshake mr-1" style="color: var(--primary);"></i> Handover Status</label>
                        <select name="handover_status" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All</option>
                            <option value="pending" {{ request('handover_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ request('handover_status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);"><i class="fas fa-hourglass-half mr-1" style="color: var(--primary);"></i> Late/Overtime</label>
                        <select name="late_status" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All</option>
                            <option value="late" {{ request('late_status') == 'late' ? 'selected' : '' }}>Late Check-in</option>
                            <option value="overtime" {{ request('late_status') == 'overtime' ? 'selected' : '' }}>Overtime</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('security.schedules.index') }}" class="px-4 py-2 rounded-lg font-medium inline-flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Upcoming Handovers --}}
    @if(isset($upcomingHandovers) && $upcomingHandovers->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-handshake mr-3" style="color: var(--info);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Upcoming Handovers</h3>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">Next 2 hours</span>
            </div>
        </div>
        <div class="p-6 space-y-3">
            @foreach($upcomingHandovers as $handover)
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-exchange-alt text-xl"></i>
                        </div>
                        <div>
                            <div class="flex items-center flex-wrap gap-2">
                                <h5 class="font-semibold" style="color: var(--text-primary);">{{ $handover->post->name ?? 'N/A' }}</h5>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">{{ $handover->shift->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center mt-2 space-x-4">
                                <span class="text-sm" style="color: var(--text-secondary);"><i class="fas fa-user mr-1"></i> {{ $handover->securityUser->name ?? 'N/A' }}</span>
                                <span class="text-sm" style="color: var(--text-secondary);"><i class="fas fa-clock mr-1"></i> {{ $handover->handover_info['handover_start'] ?? (isset($handover->shift) ? substr($handover->shift->end_time, 0, 5) : '--:--') }}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="completeHandover({{ $handover->id }})" class="px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary" {{ $handover->handover_completed ? 'disabled' : '' }}>
                        <i class="fas fa-check mr-2"></i> Complete Handover
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Staffing Alerts --}}
    @if(isset($understaffedPosts) && $understaffedPosts->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Staffing Alerts</h3>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">{{ $understaffedPosts->count() }} posts need attention</span>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($understaffedPosts->take(3) as $post)
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center mb-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">{{ $post['post_name'] ?? ($post->name ?? 'N/A') }}</h4>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $post['post']->code ?? ($post->code ?? '') }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold" style="color: var(--warning);">{{ $post['assigned'] ?? 0 }}/{{ $post['required'] ?? 0 }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Short by {{ ($post['required'] ?? 0) - ($post['assigned'] ?? 0) }}</div>
                    </div>
                </div>
                @if($__hasCreate)
                    <a href="{{ route('security.schedules.create') }}?post_id={{ $post['post']->id ?? '' }}" class="btn-primary w-full py-2 rounded-lg text-sm font-medium flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i> Assign Personnel
                    </a>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Bulk Actions --}}
    @if($schedules->count() > 0 && $__hasBulkUpdate)
    <div class="card" style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 4px solid var(--primary);">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Select schedules to perform bulk operations</p>
                </div>
                <div class="flex items-center space-x-2 flex-wrap gap-2">
                    <button type="button" onclick="selectAllSchedules()" class="px-3 py-2 text-sm rounded-lg inline-flex items-center" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" onclick="deselectAllSchedules()" class="px-3 py-2 text-sm rounded-lg inline-flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span id="selectedCount" class="text-sm px-3 py-2 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);">0 items selected</span>
                <select id="bulkActionSelect" class="form-input px-3 py-2 text-sm rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-width: 200px;">
                    <option value="">Choose action...</option>
                    <optgroup label="Status Actions">
                        <option value="mark_completed">✅ Mark as Completed</option>
                        <option value="mark_absent">❌ Mark as Absent</option>
                        <option value="cancelled">🚫 Cancel Selected</option>
                    </optgroup>
                    <optgroup label="Trash Actions">
                        <option value="trash">🗑️ Move to Trash</option>
                    </optgroup>
                </select>
                <button type="button" onclick="performBulkAction()" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary" id="bulkActionBtn" disabled>
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Schedules Table --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Security Schedules
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $schedules->firstItem() ?? 0 }} to {{ $schedules->lastItem() ?? 0 }} of {{ $schedules->total() }} schedules
                </div>
            </div>
        </div>
        <div class="p-6 overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        @if($schedules->count() > 0)
                            <th class="text-left py-3 px-4" style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox"></th>
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
                            $isTrashed = method_exists($schedule, 'trashed') ? $schedule->trashed() : false;
                            $statusColors = [
                                'active'    => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'color' => 'var(--success)', 'icon' => 'play-circle'],
                                'completed' => ['bg' => 'rgba(var(--info-rgb), 0.1)',    'color' => 'var(--info)',    'icon' => 'check-circle'],
                                'absent'    => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'color' => 'var(--danger)',  'icon' => 'user-slash'],
                                'cancelled' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'color' => 'var(--warning)', 'icon' => 'times-circle'],
                            ];
                            $statusInfo = $statusColors[$schedule->status] ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'color' => 'var(--secondary)', 'icon' => 'clock'];
                            $isRotated = $schedule->is_rotated ?? false;
                            $preferenceScore = $schedule->rotation_preference_score ?? 5;
                            $swapCount = $schedule->rotation_swap_count ?? 0;
                            $rotationGroup = $schedule->rotationGroup ?? null;
                        @endphp
                        <tr class="border-b" style="border-color: var(--border-color); {{ $isRotated ? 'background-color: rgba(var(--info-rgb), 0.03);' : '' }}">
                            @if($schedules->count() > 0)
                                <td class="py-3 px-4"><input type="checkbox" class="schedule-checkbox bulk-checkbox" value="{{ $schedule->id }}"></td>
                            @endif
                            <td class="py-3 px-4">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $schedule->assignment_date->format('l') }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->name ?? 'N/A' }}</div>
                                @if($schedule->post->code ?? false)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Code: {{ $schedule->post->code }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->shift->name ?? 'N/A' }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $schedule->shift ? substr($schedule->shift->start_time, 0, 5) . ' - ' . substr($schedule->shift->end_time, 0, 5) : 'N/A' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="avatar mr-3 flex-shrink-0">{{ strtoupper(substr($schedule->securityUser->name ?? 'N/A', 0, 2)) }}</div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->securityUser->name ?? 'N/A' }}</div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $schedule->securityUser->phone ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                @if($schedule->checkin_time)
                                    <div class="font-medium" style="color: var(--success);"><i class="fas fa-sign-in-alt mr-1"></i> {{ $schedule->checkin_time->format('H:i') }}</div>
                                    @if($schedule->late_minutes > 0)
                                        <div class="text-xs mt-1" style="color: var(--danger);"><i class="fas fa-exclamation-triangle mr-1"></i> {{ $schedule->late_minutes }} min late</div>
                                    @endif
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);"><i class="fas fa-minus-circle mr-1"></i> Not checked in</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($schedule->checkout_time)
                                    <div class="font-medium" style="color: var(--info);"><i class="fas fa-sign-out-alt mr-1"></i> {{ $schedule->checkout_time->format('H:i') }}</div>
                                    @if($schedule->overtime_minutes > 0)
                                        <div class="text-xs mt-1" style="color: var(--warning);"><i class="fas fa-hourglass-end mr-1"></i> {{ $schedule->overtime_minutes }} min OT</div>
                                    @endif
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);"><i class="fas fa-minus-circle mr-1"></i> Not checked out</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium" style="background-color: {{ $statusInfo['bg'] }}; color: {{ $statusInfo['color'] }};">
                                    <i class="fas fa-{{ $statusInfo['icon'] }} mr-1"></i> {{ ucfirst($schedule->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                @if($isRotated)
                                    <span class="px-2 py-0.5 rounded-full text-xxs font-medium" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"><i class="fas fa-sync-alt mr-1"></i> Rotated</span>
                                    @if($swapCount > 0)
                                        <div class="text-xxs mt-1" style="color: var(--text-secondary);">Swapped {{ $swapCount }}x</div>
                                    @endif
                                @endif
                                <div class="mt-1 flex items-center">
                                    @php $scoreColor = $preferenceScore >= 8 ? 'success' : ($preferenceScore >= 5 ? 'warning' : 'danger'); @endphp
                                    <span class="text-xxs mr-1" style="color: var(--{{ $scoreColor }});"><i class="fas fa-star"></i></span>
                                    <span class="text-xxs" style="color: var(--text-secondary);">Score: {{ $preferenceScore }}/10</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center space-x-2">
                                    @if($__hasShow)
                                        <a href="{{ route('security.schedules.show', $schedule->id) }}" class="action-btn" title="View Details" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"><i class="fas fa-eye"></i></a>
                                    @endif
                                    @if($__hasEdit)
                                        <a href="{{ route('security.schedules.edit', $schedule->id) }}" class="action-btn" title="Edit" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"><i class="fas fa-edit"></i></a>
                                    @endif
                                    @if($__hasDestroy)
                                        <button type="button" onclick="showDeleteModal({{ $schedule->id }})" class="action-btn" title="Move to Trash" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"><i class="fas fa-trash"></i></button>
                                    @endif
                                    @if($__hasRotationHistoryByGroup && $rotationGroup)
                                        <a href="{{ route('security.rotation-history.group', $rotationGroup->id) }}" class="action-btn" title="Rotation History" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);"><i class="fas fa-history"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-times text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No schedules found</p>
                                <p>Try adjusting your filters or create a new schedule</p>
                                @if($__hasCreate)
                                    <a href="{{ route('security.schedules.create') }}" class="mt-4 inline-flex px-4 py-2 rounded-lg btn-primary text-white">Create Schedule</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($schedules->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">Showing {{ $schedules->firstItem() }} to {{ $schedules->lastItem() }} of {{ $schedules->total() }} entries</div>
                        <div>{{ $schedules->withQueryString()->links() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Move to Trash</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
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

<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
let selectedScheduleIds = [];
let currentScheduleId = null;

document.addEventListener('DOMContentLoaded', function () {
    initializeBulkSelection();
});

function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const scheduleCheckboxes = document.querySelectorAll('.schedule-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            scheduleCheckboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }

    scheduleCheckboxes.forEach(cb => cb.addEventListener('change', updateSelectedCount));
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.schedule-checkbox:checked').length;
    const countElement = document.getElementById('selectedCount');
    const btn = document.getElementById('bulkActionBtn');

    if (countElement) countElement.textContent = count + ' item' + (count === 1 ? '' : 's') + ' selected';
    if (btn) {
        btn.disabled = count === 0;
        btn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply${count > 0 ? ' (' + count + ')' : ''}`;
    }
}

function selectAllSchedules() {
    document.querySelectorAll('.schedule-checkbox').forEach(cb => cb.checked = true);
    const all = document.getElementById('selectAllCheckbox');
    if (all) all.checked = true;
    updateSelectedCount();
}

function deselectAllSchedules() {
    document.querySelectorAll('.schedule-checkbox').forEach(cb => cb.checked = false);
    const all = document.getElementById('selectAllCheckbox');
    if (all) all.checked = false;
    updateSelectedCount();
}

function getSelectedScheduleIds() {
    return Array.from(document.querySelectorAll('.schedule-checkbox:checked')).map(cb => cb.value);
}

async function performBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    const ids = getSelectedScheduleIds();

    if (!action) return showToast('Please select an action', 'warning');
    if (ids.length === 0) return showToast('Please select at least one schedule', 'warning');
    if (!confirm(`Apply "${action}" to ${ids.length} schedule(s)?`)) return;

    showLoading(`Processing ${ids.length} schedule(s)...`);

    try {
        const response = await fetch(`{{ route('security.schedules.bulk-update') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ schedule_ids: ids, action: action })
        });
        const data = await response.json();
        hideLoading();

        if (data.success) {
            showToast(data.message, 'success');
            deselectAllSchedules();
            document.getElementById('bulkActionSelect').value = '';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to perform action', 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Network error occurred', 'error');
    }
}

function showDeleteModal(scheduleId) {
    document.getElementById('deleteScheduleInfo').textContent = 'Schedule #' + scheduleId;
    document.getElementById('deleteForm').action = `{{ url('security/schedules') }}/${scheduleId}`;
    openModal('deleteModal');
}

function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    toast.innerHTML = `<span class="text-sm">${message}</span><button class="ml-4" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
    container.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 5000);
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
            </div>`;
        document.body.appendChild(overlay);
    }
    document.getElementById('loadingMessage').textContent = message;
}
function hideLoading() {
    document.getElementById('loadingOverlay')?.remove();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => {
            if (!m.classList.contains('hidden')) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        });
    }
});
</script>
@endsection

@push('styles')
<style>
#security-schedules-page .card { background-color: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 12px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important; }
#security-schedules-page .btn-primary,
#deleteModal .btn-primary {
    background-color: var(--primary) !important; color: white !important; border: 1px solid var(--primary) !important; transition: all 0.2s ease;
}
#security-schedules-page .btn-primary:hover,
#deleteModal .btn-primary:hover {
    background-color: var(--secondary) !important; border-color: var(--secondary) !important; transform: translateY(-1px);
}
#security-schedules-page .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
#security-schedules-page .btn-secondary,
#deleteModal .btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}
#security-schedules-page .action-btn {
    width: 36px; height: 36px; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s ease;
}
#security-schedules-page .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
#security-schedules-page .form-input,
#security-schedules-page select,
#security-schedules-page textarea,
#deleteModal .form-input,
#deleteModal select,
#deleteModal textarea {
    background-color: var(--card-bg) !important; color: var(--text-primary) !important; border: 1px solid var(--border-color) !important; border-radius: 8px !important;
}
#security-schedules-page .form-input:focus,
#security-schedules-page select:focus,
#security-schedules-page textarea:focus {
    outline: none !important; border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}
#security-schedules-page .avatar {
    width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;
}
#security-schedules-page .bulk-checkbox { width: 18px; height: 18px; border-radius: 4px; border: 2px solid var(--border-color); background-color: var(--card-bg); cursor: pointer; }
#security-schedules-page .bulk-checkbox:checked { background-color: var(--primary); border-color: var(--primary); }

#deleteModal.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }
#deleteModal .modal-overlay { position: absolute; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
#deleteModal .modal-container { position: relative; background-color: var(--card-bg); border-radius: 16px; margin: 2rem auto; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid var(--border-color); animation: modalFadeIn 0.3s ease-out; }
@keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
#deleteModal .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
#deleteModal .modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
#deleteModal .modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 6px; }
#deleteModal .modal-body { padding: 1.5rem; }
#deleteModal .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background-color: var(--bg-secondary); border-radius: 0 0 16px 16px; }

.text-xxs { font-size: 0.625rem; }

#deleteModal.hidden,
#security-schedules-page .hidden { display: none !important; }
</style>
@endpush