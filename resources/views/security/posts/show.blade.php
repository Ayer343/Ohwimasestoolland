@extends('layouts.secu')

@section('title', $securityPost->name)

@section('content')
@php
    // ============================================================
    // MODE RESOLUTION
    // ------------------------------------------------------------
    // - Supervisor / Head of Security (or admin viewing the
    //   security side): full admin-parity dashboard.
    // - Ordinary security personnel: lightweight read-only view.
    // ============================================================
    $isSupervisorMode = isset($canManage, $isSupervisor) && $canManage && $isSupervisor;

    // Type icon map (shared by both modes' header).
    $typeIcons = [
        'main_gate'        => 'fa-door-closed',
        'internal_gate'    => 'fa-door-open',
        'checkpoint'       => 'fa-shield-alt',
        'patrol_route'     => 'fa-route',
        'observation_post' => 'fa-binoculars',
        'control_room'     => 'fa-tv',
        'access_point'     => 'fa-key',
    ];
    $typeIcon = $typeIcons[$securityPost->type] ?? 'fa-map-marker-alt';

    // Chip color lookup (replaces admin's 7-deep ternary chain).
    $typeChipColors = [
        'main_gate'        => ['bg' => 'rgba(var(--primary-rgb), 0.1)',   'fg' => 'var(--primary)'],
        'internal_gate'    => ['bg' => 'rgba(var(--info-rgb), 0.1)',      'fg' => 'var(--info)'],
        'checkpoint'       => ['bg' => 'rgba(var(--success-rgb), 0.1)',   'fg' => 'var(--success)'],
        'patrol_route'     => ['bg' => 'rgba(var(--warning-rgb), 0.1)',   'fg' => 'var(--warning)'],
        'observation_post' => ['bg' => 'rgba(var(--danger-rgb), 0.1)',    'fg' => 'var(--danger)'],
        'control_room'     => ['bg' => 'rgba(var(--purple-rgb), 0.1)',    'fg' => 'var(--purple)'],
        'access_point'     => ['bg' => 'rgba(var(--pink-rgb), 0.1)',      'fg' => 'var(--pink)'],
    ];
    $typeChip = $typeChipColors[$securityPost->type] ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'fg' => 'var(--secondary)'];

    // Status chip map (single source of truth for both modes' schedule tables).
    $statusChipMap = [
        'active'    => 'success',
        'scheduled' => 'info',
        'completed' => 'primary',
        'absent'    => 'danger',
        'pending'   => 'warning',
    ];

    // Fallbacks so the view is safe whether or not the controller
    // passed the supervisor-only keys (supervisor branch needs them).
    $stats           = $stats ?? ['total_assignments' => 0, 'completed_shifts' => 0, 'active_today' => 0, 'staffing_rate' => 0];
    $upcomingSchedules = $upcomingSchedules ?? collect();
    $equipment       = is_array($securityPost->equipment) ? $securityPost->equipment : [];
    $restrictions    = is_array($securityPost->restrictions) ? $securityPost->restrictions : [];
    $workingHoursInfo = $workingHoursInfo ?? null;
@endphp

<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================ --}}
    {{-- HEADER CARD (shared, action buttons conditional)             --}}
    {{-- ============================================================ --}}
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas {{ $typeIcon }} text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                        {{ $securityPost->name }}

                        <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium capitalize"
                              style="background-color: {{ $typeChip['bg'] }}; color: {{ $typeChip['fg'] }};">
                            {{ str_replace('_', ' ', $securityPost->type) }}
                        </span>

                        <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: {{ $securityPost->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }};
                                     color: {{ $securityPost->is_active ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                        </span>

                        @if($securityPost->requires_checkin)
                            <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium"
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-check-circle mr-1"></i> Check-in
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag mr-2"></i>
                        <span>Code: {{ $securityPost->code ?? 'N/A' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span>{{ Str::limit($securityPost->location ?? 'No location specified', 40) }}</span>
                        @if($securityPost->digital_address)
                            <span class="mx-2">•</span>
                            <i class="fas fa-map-pin mr-2"></i>
                            <span>{{ $securityPost->digital_address }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('security.posts.index') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>

                @if($isSupervisorMode)
                    <button type="button" onclick="showHistory()"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-history mr-2"></i> Activity
                    </button>
                    <a href="{{ route('security.posts.edit', $securityPost) }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>
                    <a href="#"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-user-plus mr-2"></i> Assign
                    </a>
                @else
                    <a href="{{ route('security.posts.schedule', ['securityPost' => $securityPost->id]) }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-calendar-alt mr-2"></i> View Schedule
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($isSupervisorMode)
        {{-- ======================================================== --}}
        {{-- SUPERVISOR MODE — admin-parity dashboard                 --}}
        {{-- ======================================================== --}}

        <!-- Navigation Card -->
        <div class="card p-6">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center flex-wrap gap-2">
                    <a href="{{ route('security.posts.index') }}"
                       class="inline-flex items-center text-sm font-medium"
                       style="color: var(--primary);">
                        <i class="fas fa-arrow-left mr-2"></i> All Posts
                    </a>
                    <a href="{{ route('security.schedules.index') }}?post_id={{ $securityPost->id }}"
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-calendar-alt mr-1"></i> Schedules
                    </a>
                    <a href="{{ route('security.posts.schedule', ['securityPost' => $securityPost->id]) }}"
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-calendar-day mr-1"></i> Schedule
                    </a>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="mr-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_assignments'] ?? 0 }}</div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Assignments</div>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center">
                    <div class="mr-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['completed_shifts'] ?? 0 }}</div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">Completed Shifts</div>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center">
                    <div class="mr-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['active_today'] ?? 0 }}</div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Today</div>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center">
                    <div class="mr-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="w-full">
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['staffing_rate'] ?? 0 }}%</div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">Staffing Rate</div>
                        <div class="w-full rounded-full h-2 mt-2" style="background-color: var(--bg-secondary);">
                            <div class="h-2 rounded-full progress-bar"
                                 style="width: {{ min(100, $stats['staffing_rate'] ?? 0) }}%; background-color: var(--warning);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Today's Schedule -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <div class="flex justify-between items-center flex-wrap gap-2">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i>
                                Today's Schedule
                                <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $todaySchedules->count() }}/{{ $securityPost->max_personnel }}
                                </span>
                            </h3>
                            <div class="flex space-x-2">
                                @if($todaySchedules->count() < $securityPost->max_personnel)
                                    <a href="#"
                                       class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center text-white btn-primary">
                                        <i class="fas fa-plus mr-1"></i> Add
                                    </a>
                                @endif
                                <a href="{{ route('security.schedules.index') }}?post_id={{ $securityPost->id }}&date={{ now()->toDateString() }}"
                                   class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                    View All
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        @if($todaySchedules->count() > 0)
                            <div class="space-y-3">
                                @foreach($todaySchedules as $schedule)
                                    @php
                                        $chip = $statusChipMap[$schedule->status] ?? 'secondary';
                                    @endphp
                                    <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                                        <div class="flex justify-between items-center flex-wrap gap-2">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-sm font-semibold"
                                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                                    {{ substr($schedule->securityUser->name ?? 'N/A', 0, 2) }}
                                                </div>
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">
                                                        {{ $schedule->securityUser->name ?? 'Unknown User' }}
                                                    </div>
                                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        {{ $schedule->shift?->getTimeRange() ?? 'N/A' }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium capitalize badge-{{ $chip }}">
                                                    {{ $schedule->status }}
                                                </span>
                                                @if($schedule->checkin_time)
                                                    <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                                                        <i class="fas fa-sign-in-alt mr-1"></i>
                                                        {{ $schedule->checkin_time->format('H:i') }}
                                                    </div>
                                                @endif
                                                @if($schedule->securityUser)
                                                    <a href="{{ route('security.schedules.index') }}?search={{ $schedule->securityUser->name }}"
                                                       class="action-btn"
                                                       title="View Schedules"
                                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <i class="fas fa-user-slash text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">
                                    No assignments scheduled for today
                                </p>
                                <p class="mb-4" style="color: var(--text-secondary);">
                                    Assign personnel to this post for today's shifts
                                </p>
                                <a href="#"
                                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                    <i class="fas fa-user-plus mr-2"></i> Assign Personnel
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Upcoming Schedule -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-week mr-2" style="color: var(--info);"></i>
                                Upcoming Schedule (Next 7 Days)
                            </h3>
                            <a href="{{ route('security.posts.schedule', ['securityPost' => $securityPost->id]) }}"
                               class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center"
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-calendar-alt mr-1"></i> Schedule
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        @if($upcomingSchedules->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b" style="border-color: var(--border-color);">
                                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Date</th>
                                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Personnel</th>
                                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Shift</th>
                                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($upcomingSchedules as $schedule)
                                            @php
                                                $chip = $statusChipMap[$schedule->status] ?? 'warning';
                                            @endphp
                                            <tr class="border-b transition-colors duration-150" style="border-color: var(--border-color);">
                                                <td class="py-3 px-4">
                                                    <div class="font-medium" style="color: var(--text-primary);">
                                                        {{ $schedule->assignment_date->format('D, M j') }}
                                                    </div>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        {{ $schedule->assignment_date->diffForHumans() }}
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 text-xs text-white"
                                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                                            {{ substr($schedule->securityUser->name ?? 'N/A', 0, 2) }}
                                                        </div>
                                                        <div style="color: var(--text-primary);">
                                                            {{ $schedule->securityUser->name ?? 'Unknown User' }}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div style="color: var(--text-primary);">
                                                        {{ $schedule->shift->name ?? 'N/A' }}
                                                    </div>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        {{ $schedule->shift?->getTimeRange() ?? 'N/A' }}
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="px-3 py-1 rounded-full text-xs font-medium capitalize badge-{{ $chip }}">
                                                        {{ $schedule->status }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    @if($schedule->securityUser)
                                                        <a href="{{ route('security.schedules.index') }}?search={{ $schedule->securityUser->name }}&date={{ $schedule->assignment_date->format('Y-m-d') }}"
                                                           class="action-btn"
                                                           title="View Schedules"
                                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">
                                    No upcoming schedules
                                </p>
                                <p class="mb-4" style="color: var(--text-secondary);">
                                    No personnel assigned for the next 7 days
                                </p>
                                <a href="#"
                                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                    <i class="fas fa-user-plus mr-2"></i> Schedule Personnel
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Post Details Card -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> Post Details
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Code</div>
                                <div style="color: var(--text-primary);">{{ $securityPost->code ?? 'N/A' }}</div>
                            </div>

                            <div>
                                <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Location</div>
                                <div style="color: var(--text-primary);">{{ $securityPost->location ?? 'No location specified' }}</div>
                                @if($securityPost->digital_address)
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-pin mr-1"></i> {{ $securityPost->digital_address }}
                                    </div>
                                @endif
                            </div>

                            <div>
                                <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Working Hours</div>
                                <div style="color: var(--text-primary);">
                                    @if($workingHoursInfo)
                                        {{ $workingHoursInfo['display'] ?? 'N/A' }}
                                    @elseif($securityPost->working_hours && is_array($securityPost->working_hours))
                                        {{ ($securityPost->working_hours['start'] ?? 'N/A') }} - {{ ($securityPost->working_hours['end'] ?? 'N/A') }}
                                    @else
                                        24/7
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Personnel Capacity</div>
                                @php
                                    $currentPersonnel   = $todaySchedules->count();
                                    $maxPersonnel       = max(1, (int) $securityPost->max_personnel);
                                    $capacityPercentage = min(100, ($currentPersonnel / $maxPersonnel) * 100);
                                    $capacityColor      = $currentPersonnel >= $maxPersonnel
                                        ? 'var(--success)'
                                        : ($currentPersonnel > 0 ? 'var(--warning)' : 'var(--danger)');
                                @endphp
                                <div class="flex items-center">
                                    <div class="w-full rounded-full h-2 mr-3" style="background-color: var(--bg-secondary);">
                                        <div class="h-2 rounded-full progress-bar"
                                             style="width: {{ $capacityPercentage }}%; background-color: {{ $capacityColor }};"></div>
                                    </div>
                                    <div class="text-sm font-medium" style="color: {{ $capacityColor }};">
                                        {{ $currentPersonnel }}/{{ $maxPersonnel }}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Status</div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium badge-{{ $securityPost->is_active ? 'success' : 'danger' }}">
                                        {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if($securityPost->requires_checkin)
                                        <span class="px-3 py-1 rounded-full text-sm font-medium badge-info">
                                            <i class="fas fa-check-circle mr-1"></i> Check-in Required
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if($securityPost->description)
                                <div>
                                    <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Description</div>
                                    <div class="p-3 rounded-lg border"
                                         style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                        {{ $securityPost->description }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Equipment Card -->
                @if(count($equipment) > 0)
                    <div class="card">
                        <div class="p-6 border-b" style="border-color: var(--border-color);">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-tools mr-2" style="color: var(--success);"></i> Equipment
                                <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    {{ count($equipment) }} items
                                </span>
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                @foreach($equipment as $item)
                                    <div class="flex items-center p-3 rounded-lg border"
                                         style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                        <i class="fas fa-check-circle mr-3" style="color: var(--success);"></i>
                                        <span style="color: var(--text-primary);">{{ $item }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Restrictions Card -->
                @if(count($restrictions) > 0)
                    <div class="card">
                        <div class="p-6 border-b" style="border-color: var(--border-color);">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-ban mr-2" style="color: var(--danger);"></i> Restrictions
                                <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium badge-danger">
                                    {{ count($restrictions) }} items
                                </span>
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                @foreach($restrictions as $restriction)
                                    <div class="flex items-center p-3 rounded-lg border"
                                         style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                        <i class="fas fa-exclamation-circle mr-3" style="color: var(--danger);"></i>
                                        <span style="color: var(--text-primary);">{{ $restriction }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Quick Actions Card -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Quick Actions
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <a href="{{ route('security.posts.edit', $securityPost) }}"
                               class="flex items-center p-3 rounded-lg border transition-colors duration-200"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="font-medium" style="color: var(--text-primary);">Edit Post</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Modify post details and settings</div>
                                </div>
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </a>

                            <a href="#"
                               class="flex items-center p-3 rounded-lg border transition-colors duration-200"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="font-medium" style="color: var(--text-primary);">Assign Personnel</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Schedule security personnel</div>
                                </div>
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </a>

                            <a href="{{ route('security.schedules.index') }}?post_id={{ $securityPost->id }}"
                               class="flex items-center p-3 rounded-lg border transition-colors duration-200"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="font-medium" style="color: var(--text-primary);">View Schedules</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">All assignments for this post</div>
                                </div>
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </a>

                            <button type="button" onclick="togglePostStatus()"
                                    class="w-full flex items-center p-3 rounded-lg border transition-colors duration-200 text-left"
                                    style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: {{ $securityPost->is_active ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)' }};
                                            color: {{ $securityPost->is_active ? 'var(--danger)' : 'var(--success)' }};">
                                    <i class="fas fa-power-off"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="font-medium"
                                         style="color: {{ $securityPost->is_active ? 'var(--danger)' : 'var(--success)' }};">
                                        {{ $securityPost->is_active ? 'Deactivate' : 'Activate' }} Post
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $securityPost->is_active ? 'Temporarily disable this post' : 'Enable this post for assignments' }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- ACTIVITY MODAL (supervisor only)                         --}}
        {{-- ======================================================== --}}
        <div id="historyModal" class="modal hidden">
            <div class="modal-overlay" onclick="closeModal('historyModal')"></div>
            <div class="modal-container" style="max-width: 700px;">
                <div class="modal-header">
                    <h3 class="modal-title flex items-center">
                        <i class="fas fa-history mr-2"></i> Recent Activity
                    </h3>
                    <button type="button" class="modal-close" onclick="closeModal('historyModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    @if($securityPost->schedules && $securityPost->schedules->count() > 0)
                        <div class="space-y-3">
                            @foreach($securityPost->schedules->sortByDesc('assignment_date')->take(20) as $schedule)
                                @php
                                    $chip = $statusChipMap[$schedule->status] ?? 'warning';
                                @endphp
                                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                                    <div class="flex justify-between items-center flex-wrap gap-2">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-sm font-semibold"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                                {{ substr($schedule->securityUser->name ?? 'N/A', 0, 2) }}
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $schedule->securityUser->name ?? 'Unknown User' }}
                                                </div>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    {{ $schedule->assignment_date->format('M j, Y') }}
                                                    @if($schedule->shift)
                                                        <span class="mx-1">•</span>
                                                        <i class="fas fa-clock mr-1"></i>
                                                        {{ $schedule->shift->getTimeRange() ?? 'N/A' }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            @if($schedule->checkin_time)
                                                <div class="text-sm" style="color: var(--text-secondary);">
                                                    <i class="fas fa-sign-in-alt mr-1"></i>
                                                    {{ $schedule->checkin_time->format('H:i') }}
                                                </div>
                                            @endif
                                            <span class="px-3 py-1 rounded-full text-xs font-medium capitalize badge-{{ $chip }}">
                                                {{ $schedule->status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-history text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">
                                No recent activity
                            </p>
                            <p style="color: var(--text-secondary);">
                                This post has no recent assignments
                            </p>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                            onclick="closeModal('historyModal')">
                        Close
                    </button>
                </div>
            </div>
        </div>

    @else
        {{-- ======================================================== --}}
        {{-- PERSONNEL MODE — original read-only layout               --}}
        {{-- ======================================================== --}}

        <!-- Post Info Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Staffing Card -->
            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-users mr-2"></i> Staffing
                </h4>
                <div class="flex items-center justify-between mb-2">
                    <span style="color: var(--text-secondary);">Current Personnel</span>
                    <span class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ $todaySchedules->count() }}/{{ $securityPost->max_personnel }}
                    </span>
                </div>
                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                    @php
                        $percentage = $securityPost->max_personnel > 0
                            ? min(100, ($todaySchedules->count() / $securityPost->max_personnel) * 100)
                            : 0;
                        $color = $percentage >= 100 ? 'var(--success)' : ($percentage > 0 ? 'var(--warning)' : 'var(--danger)');
                    @endphp
                    <div class="rounded-full h-2 transition-all duration-500"
                         style="width: {{ $percentage }}%; background-color: {{ $color }};"></div>
                </div>
                <div class="mt-3 flex justify-between text-xs" style="color: var(--text-secondary);">
                    <span>{{ $stats['staffing_rate'] ?? 0 }}% Staffed</span>
                    <span>Max: {{ $securityPost->max_personnel }}</span>
                </div>
            </div>

            <!-- Working Hours Card -->
            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-2"></i> Working Hours
                </h4>
                @if($securityPost->working_hours)
                    <div class="text-lg font-semibold" style="color: var(--text-primary);">
                        {{ $securityPost->working_hours['start'] ?? 'N/A' }} - {{ $securityPost->working_hours['end'] ?? 'N/A' }}
                    </div>
                @else
                    <div class="text-lg font-semibold" style="color: var(--text-primary);">24/7</div>
                @endif
                <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-{{ $securityPost->requires_checkin ? 'check-circle' : 'times-circle' }} mr-1"
                       style="color: {{ $securityPost->requires_checkin ? 'var(--success)' : 'var(--danger)' }};"></i>
                    {{ $securityPost->requires_checkin ? 'Requires' : 'Does not require' }} check-in
                </div>
            </div>

            <!-- Status Card -->
            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2"></i> Status
                </h4>
                <div class="flex items-center">
                    <span class="px-3 py-1 rounded-full text-sm font-medium
                        {{ $securityPost->is_active ? 'badge-success' : 'badge-danger' }}">
                        {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    @php
                        $status = $todaySchedules->count() >= $securityPost->max_personnel
                            ? 'Fully Staffed'
                            : ($todaySchedules->count() > 0 ? 'Understaffed' : 'Unstaffed');
                        $statusColor = $todaySchedules->count() >= $securityPost->max_personnel
                            ? 'success'
                            : ($todaySchedules->count() > 0 ? 'warning' : 'danger');
                    @endphp
                    <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium badge-{{ $statusColor }}">
                        {{ $status }}
                    </span>
                </div>
                <div class="mt-3 text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-day mr-1"></i>
                    {{ $todaySchedules->count() }} personnel today
                </div>
            </div>
        </div>

        <!-- Equipment & Restrictions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if(!empty($equipment))
                <div class="card p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                        <i class="fas fa-tools mr-2"></i> Equipment
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($equipment as $item)
                            <span class="px-3 py-1 rounded-full text-xs badge-info">{{ $item }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($restrictions))
                <div class="card p-6">
                    <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-circle mr-2"></i> Restrictions
                    </h4>
                    <ul class="space-y-2">
                        @foreach($restrictions as $restriction)
                            <li class="flex items-start">
                                <i class="fas fa-circle text-xs mt-1.5 mr-2" style="color: var(--warning);"></i>
                                <span style="color: var(--text-secondary);">{{ $restriction }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Today's Personnel -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-clock mr-2" style="color: var(--primary);"></i>
                    Today's Personnel
                    <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                        {{ $todaySchedules->count() }}
                    </span>
                </h3>
            </div>
            <div class="overflow-x-auto">
                @if($todaySchedules->count() > 0)
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Time</th>
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($todaySchedules as $schedule)
                                @php
                                    $chip = $statusChipMap[$schedule->status] ?? 'secondary';
                                @endphp
                                <tr class="border-b transition-colors duration-200"
                                    style="border-color: var(--border-color); background-color: var(--card-bg);">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600;">
                                                {{ $schedule->securityUser ? substr($schedule->securityUser->name, 0, 1) : '?' }}
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $schedule->securityUser->name ?? 'Unknown' }}
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    {{ $schedule->securityUser->badge_number ?? 'No Badge' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        @if($schedule->shift)
                                            <span class="px-2 py-1 text-xs rounded-full badge-info">
                                                {{ $schedule->shift->name }}
                                            </span>
                                        @else
                                            <span style="color: var(--text-secondary);">N/A</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6">
                                        @if($schedule->shift)
                                            <span style="color: var(--text-primary);">
                                                {{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}
                                            </span>
                                        @else
                                            <span style="color: var(--text-secondary);">N/A</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-2 py-1 text-xs rounded-full badge-{{ $chip }}">
                                            {{ ucfirst($schedule->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center">
                        <i class="fas fa-user-slash text-2xl mb-2" style="color: var(--text-secondary);"></i>
                        <p style="color: var(--text-secondary);">No personnel scheduled for today.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Upcoming Schedule Preview -->
        @if($upcomingSchedules->count() > 0)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-week mr-2" style="color: var(--primary);"></i>
                        Upcoming Schedule (Next 7 Days)
                        <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                            {{ $upcomingSchedules->count() }}
                        </span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                                <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcomingSchedules as $schedule)
                                @php
                                    $chip = $statusChipMap[$schedule->status] ?? 'info';
                                @endphp
                                <tr class="border-b transition-colors duration-200"
                                    style="border-color: var(--border-color); background-color: var(--card-bg);">
                                    <td class="py-4 px-6" style="color: var(--text-primary);">
                                        {{ \Carbon\Carbon::parse($schedule->assignment_date)->format('D, M d') }}
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600;">
                                                {{ $schedule->securityUser ? substr($schedule->securityUser->name, 0, 1) : '?' }}
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $schedule->securityUser->name ?? 'Unknown' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        @if($schedule->shift)
                                            <span class="px-2 py-1 text-xs rounded-full badge-info">
                                                {{ $schedule->shift->name }}
                                            </span>
                                        @else
                                            <span style="color: var(--text-secondary);">N/A</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-2 py-1 text-xs rounded-full badge-{{ $chip }}">
                                            {{ ucfirst($schedule->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="{{ route('security.posts.schedule', ['securityPost' => $securityPost->id]) }}"
               class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-calendar-alt text-xl" style="color: var(--primary);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">View Schedule</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">See full post schedule</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
            </a>

            @if(isset($isSupervisorForPost) && $isSupervisorForPost)
                <a href="{{ route('security.supervisor.team.today') }}"
                   class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-users text-xl" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">Team Today</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">View your team</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
                </a>
            @endif

            <a href="{{ route('security.posts.index') }}"
               class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-building text-xl" style="color: var(--info);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">All Posts</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">Back to all posts</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
            </a>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // ============================================================
    // SUPERVISOR-ONLY interactions (safe to define in both modes —
    // unused in personnel mode since the modal/button aren't rendered).
    // ============================================================

    function togglePostStatus() {
        if (!confirm(@json($securityPost->is_active ? 'Are you sure you want to deactivate this post?' : 'Are you sure you want to activate this post?'))) {
            return;
        }

        showLoading('Updating post status...');

        fetch(`{{ route('security.posts.toggle-activation', $securityPost) }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message || 'Status updated.', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Failed to update status.', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Network error occurred.', 'error');
        });
    }

    function showHistory() {
        openModal('historyModal');
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Hover lift on action buttons.
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
            btn.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    });

    // Close modal when clicking the backdrop.
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close modal with Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });

    // ---- Toast / loading helpers (admin parity) ----
    function showToast(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
            'bg-blue-100 text-blue-800 border border-blue-200'
        }`;

        const msgEl = document.createElement('span');
        msgEl.className = 'text-sm font-medium flex-1';
        msgEl.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.className = 'ml-4 transition-colors duration-200';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.onclick = () => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        };

        toast.appendChild(msgEl);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 10);

        setTimeout(() => {
            if (toast.parentNode === container) {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    function showLoading(message = 'Processing...') {
        let overlay = document.getElementById('loadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay hidden';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
                    <div class="text-white font-medium" id="loadingMessage">${message}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        const messageEl = document.getElementById('loadingMessage');
        if (messageEl) messageEl.textContent = message;
        overlay.classList.remove('hidden');
    }

    function hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.classList.add('hidden');
    }
</script>

<style>
    /* ---------- Shared card / button / badge system ---------- */
    .card {
        background-color: var(--card-bg) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 12px !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        transition: all 0.2s ease;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%) !important;
        color: white !important;
        border: 1px solid var(--primary) !important;
        transition: all 0.2s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
    }

    .badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
    .badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
    .badge-danger  { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger);  border: 1px solid rgba(var(--danger-rgb), 0.3); }
    .badge-info    { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border: 1px solid rgba(var(--info-rgb), 0.3); }
    .badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
    .badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }

    /* ---------- Action buttons ---------- */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    /* ---------- Progress bar ---------- */
    .progress-bar {
        transition: width 0.3s ease;
    }

    /* ---------- Modal system (supervisor mode) ---------- */
    .modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
    }

    .modal-overlay {
        position: absolute;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal-container {
        position: relative;
        background-color: var(--card-bg);
        border-radius: 16px;
        margin: 2rem auto;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        border: 1px solid var(--border-color);
        animation: modalFadeIn 0.3s ease-out;
    }

    .modal-header {
        padding: 1.5rem 1.5rem 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0.25rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--text-primary);
    }

    .modal-body   { padding: 1.5rem; }
    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        background-color: var(--bg-secondary);
        border-radius: 0 0 16px 16px;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.9) translateY(-20px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* ---------- Loading overlay ---------- */
    .loading-overlay {
        position: fixed;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-overlay .animate-spin {
        animation: spin 1s linear infinite;
        border-top-color: transparent;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* ---------- Scrollbar ---------- */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: rgba(var(--primary-rgb), 0.05); border-radius: 4px; }
    ::-webkit-scrollbar-thumb { background: rgba(var(--primary-rgb), 0.2); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(var(--primary-rgb), 0.3); }

    /* ---------- Focus-visible ---------- */
    button:focus-visible,
    a:focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 768px) {
        .grid.grid-cols-1.md\:grid-cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }
        .grid.grid-cols-1.lg\:grid-cols-3 {
            grid-template-columns: 1fr;
        }
        table th, table td {
            padding: 0.5rem;
            font-size: 0.875rem;
        }
        .modal-container { margin: 1rem; }
    }

    @media (max-width: 640px) {
        .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
        .p-6 { padding: 1rem !important; }
        .text-xl { font-size: 1.25rem !important; }
        .text-2xl { font-size: 1.5rem !important; }
        .text-4xl { font-size: 2rem !important; }
    }
</style>
@endsection