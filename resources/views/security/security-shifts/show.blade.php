@extends('layouts.secu')

@section('title', 'Shift Details: ' . $securityShift->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <!-- Shift Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 shift-icon-main"
                         style="background-color: {{ $securityShift->category == 'day' ? 'rgba(var(--warning-rgb), 0.1)' :
                                                    ($securityShift->category == 'night' ? 'rgba(var(--primary-rgb), 0.1)' :
                                                    ($securityShift->category == 'evening' ? 'rgba(var(--info-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)')) }};
                                border-color: {{ $securityShift->category == 'day' ? 'var(--warning)' :
                                                ($securityShift->category == 'night' ? 'var(--primary)' :
                                                ($securityShift->category == 'evening' ? 'var(--info)' : 'var(--secondary)')) }};
                                color: {{ $securityShift->category == 'day' ? 'var(--warning)' :
                                         ($securityShift->category == 'night' ? 'var(--primary)' :
                                         ($securityShift->category == 'evening' ? 'var(--info)' : 'var(--secondary)')) }};">
                        @if($securityShift->category == 'day')
                            <i class="fas fa-sun text-xl"></i>
                        @elseif($securityShift->category == 'night')
                            <i class="fas fa-moon text-xl"></i>
                        @elseif($securityShift->category == 'evening')
                            <i class="fas fa-sunset text-xl"></i>
                        @else
                            <i class="fas fa-star text-xl"></i>
                        @endif
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        {{ $securityShift->name }}
                        @if($securityShift->rotation_type === 'rotating')
                            <span class="px-2 py-1 text-xs rounded-full badge-info">
                                <i class="fas fa-sync-alt text-xs mr-1"></i> Rotating Shift
                            </span>
                        @endif
                        <span class="px-2 py-1 text-xs rounded-full"
                              style="background-color: {{ $securityShift->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }};
                                     color: {{ $securityShift->is_active ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $securityShift->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-2" style="color: var(--text-secondary);">
                        <span class="flex items-center">
                            <i class="fas fa-hashtag mr-1"></i>
                            <code>{{ $securityShift->code }}</code>
                        </span>
                        <span>•</span>
                        <span class="flex items-center">
                            <i class="fas fa-clock mr-1"></i>
                            {{ \Carbon\Carbon::parse($securityShift->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($securityShift->end_time)->format('h:i A') }}
                        </span>
                        <span>•</span>
                        <span class="flex items-center">
                            <i class="fas fa-user-shield mr-1"></i>
                            {{ $securityShift->required_personnel }} personnel
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 flex-wrap gap-2">
                <a href="{{ route('security.security-shifts.index') }}"
                   class="px-3 py-2 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Shifts
                </a>
                <a href="{{ route('security.security-shifts.edit', $securityShift) }}"
                   class="btn-primary px-3 py-2 rounded-lg text-xs font-medium inline-flex items-center">
                    <i class="fas fa-edit mr-1"></i> Edit Shift
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total Assignments -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $shiftStats['total_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Assignments</div>
                </div>
            </div>
        </div>

        <!-- Completed Shifts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $shiftStats['completed_shifts'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Completed Shifts</div>
                </div>
            </div>
        </div>

        <!-- Active Assignments -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $shiftStats['active_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Assignments</div>
                </div>
            </div>
        </div>

        <!-- Attendance Rate -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $shiftStats['attendance_rate'] ?? 0 }}%</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Attendance Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Shift Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Shift Information
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Shift Name</label>
                                <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    {{ $securityShift->name }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Shift Code</label>
                                <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    <code>{{ $securityShift->code }}</code>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Category</label>
                                <div class="p-3 rounded-lg border flex items-center"
                                     style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 shift-icon-small"
                                         style="background-color: {{ $securityShift->category == 'day' ? 'rgba(var(--warning-rgb), 0.1)' :
                                                                ($securityShift->category == 'night' ? 'rgba(var(--primary-rgb), 0.1)' :
                                                                ($securityShift->category == 'evening' ? 'rgba(var(--info-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)')) }};
                                                color: {{ $securityShift->category == 'day' ? 'var(--warning)' :
                                                        ($securityShift->category == 'night' ? 'var(--primary)' :
                                                        ($securityShift->category == 'evening' ? 'var(--info)' : 'var(--secondary)')) }};">
                                        @if($securityShift->category == 'day')
                                            <i class="fas fa-sun text-sm"></i>
                                        @elseif($securityShift->category == 'night')
                                            <i class="fas fa-moon text-sm"></i>
                                        @elseif($securityShift->category == 'evening')
                                            <i class="fas fa-sunset text-sm"></i>
                                        @else
                                            <i class="fas fa-star text-sm"></i>
                                        @endif
                                    </div>
                                    <span class="capitalize">{{ $securityShift->category }} Shift</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Rotation Type</label>
                                <div class="p-3 rounded-lg border flex items-center"
                                     style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    @if($securityShift->rotation_type === 'rotating')
                                        <i class="fas fa-sync-alt mr-2" style="color: var(--info);"></i>
                                        Rotating Shift
                                    @else
                                        <i class="fas fa-lock mr-2" style="color: var(--secondary);"></i>
                                        Fixed Shift
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Required Personnel</label>
                                <div class="p-3 rounded-lg border flex items-center" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    <i class="fas fa-user-shield mr-2" style="color: var(--teal);"></i>
                                    {{ $securityShift->required_personnel }} personnel
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Shift Duration</label>
                                <div class="p-3 rounded-lg border flex items-center" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    <i class="fas fa-hourglass-half mr-2" style="color: var(--primary);"></i>
                                    {{ $securityShift->duration_hours }} hours
                                    @if($securityShift->is_overnight)
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full badge-primary">
                                            Overnight
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Applicable Days</label>
                                <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    @php
                                        $dayTypeLabels = [
                                            'all_days' => 'All Days',
                                            'weekday'  => 'Weekdays Only',
                                            'weekend'  => 'Weekends Only',
                                            'custom'   => 'Custom Days',
                                        ];
                                    @endphp
                                    {{ $dayTypeLabels[$securityShift->day_type] ?? ucfirst($securityShift->day_type) }}

                                    @if($securityShift->day_type === 'custom' && $securityShift->applicable_days)
                                        <div class="mt-2">
                                            @php
                                                $days = [
                                                    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                                                    5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
                                                ];
                                                $applicable = array_map(fn($day) => $days[$day] ?? $day, $securityShift->applicable_days);
                                            @endphp
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($applicable as $day)
                                                    <span class="px-2 py-1 text-xs rounded badge-primary">{{ $day }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Status</label>
                                <div class="p-3 rounded-lg border flex items-center"
                                     style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                    @if($securityShift->is_active)
                                        <i class="fas fa-toggle-on mr-2" style="color: var(--success);"></i> Active
                                    @else
                                        <i class="fas fa-toggle-off mr-2" style="color: var(--danger);"></i> Inactive
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    @if($securityShift->description)
                    <div class="mt-6">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Description</label>
                        <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary); min-height: 100px;">
                            {{ $securityShift->description }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Rotation Schedule Card -->
            @if($securityShift->rotation_type === 'rotating' && !empty($upcomingRotations))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--info);"></i> Rotation Schedule
                        </h3>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Next {{ count($upcomingRotations) }} days
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <th class="text-left py-2 px-3 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Date</th>
                                    <th class="text-left py-2 px-3 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Day</th>
                                    <th class="text-left py-2 px-3 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Shift Type</th>
                                    <th class="text-left py-2 px-3 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($upcomingRotations as $rotation)
                                    @php
                                        $isToday    = $rotation['date']->isToday();
                                        $isTomorrow = $rotation['date']->isTomorrow();
                                        $isWeekend  = $rotation['date']->isWeekend();
                                    @endphp
                                    <tr class="border-b" style="border-color: var(--border-color);">
                                        <td class="py-3 px-3">
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $rotation['date']->format('M j, Y') }}
                                            </div>
                                            @if($isToday)
                                                <div class="text-xs mt-1" style="color: var(--success);">
                                                    <i class="fas fa-circle text-xs mr-1"></i> Today
                                                </div>
                                            @elseif($isTomorrow)
                                                <div class="text-xs mt-1" style="color: var(--info);">
                                                    <i class="fas fa-arrow-right text-xs mr-1"></i> Tomorrow
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $rotation['date']->format('l') }}
                                            </div>
                                            @if($isWeekend)
                                                <div class="text-xs mt-1" style="color: var(--warning);">
                                                    <i class="fas fa-umbrella-beach text-xs mr-1"></i> Weekend
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                                  style="background-color: {{ $rotation['shift_type'] == 'morning' ? 'rgba(var(--warning-rgb), 0.1)' :
                                                                           ($rotation['shift_type'] == 'evening' ? 'rgba(var(--info-rgb), 0.1)' :
                                                                           ($rotation['shift_type'] == 'night' ? 'rgba(var(--primary-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)')) }};
                                                     color: {{ $rotation['shift_type'] == 'morning' ? 'var(--warning)' :
                                                             ($rotation['shift_type'] == 'evening' ? 'var(--info)' :
                                                             ($rotation['shift_type'] == 'night' ? 'var(--primary)' : 'var(--success)')) }};">
                                                {{ $rotation['shift_type'] }}
                                                @if($rotation['shift_type'] == 'off')
                                                    (Rest Day)
                                                @endif
                                            </span>
                                        </td>
                                        <td class="py-3 px-3">
                                            @if($rotation['date']->isPast())
                                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">
                                                    <i class="fas fa-check-circle mr-1"></i> Completed
                                                </span>
                                            @else
                                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">
                                                    <i class="fas fa-clock mr-1"></i> Upcoming
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Upcoming Schedules -->
            @if($securityShift->schedules->count() > 0)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> Upcoming Schedules
                        </h3>
                        <a href="{{ route('security.schedules.index') }}?shift_id={{ $securityShift->id }}"
                           class="text-sm inline-flex items-center"
                           style="color: var(--primary);">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Date</th>
                                    <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Post</th>
                                    <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Personnel</th>
                                    <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Status</th>
                                    <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($securityShift->schedules as $schedule)
                                    @php
                                        $isToday = $schedule->assignment_date->isToday();
                                        $isPast  = $schedule->assignment_date->isPast();
                                        $statusColors = [
                                            'scheduled' => ['bg' => 'rgba(var(--info-rgb), 0.1)',    'text' => 'var(--info)',    'icon' => 'fa-clock'],
                                            'active'    => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-play-circle'],
                                            'completed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-check-circle'],
                                            'cancelled' => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)',  'icon' => 'fa-times-circle'],
                                            'absent'    => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)',  'icon' => 'fa-user-slash'],
                                        ];
                                        $status = $schedule->status ?? 'scheduled';
                                        $color  = $statusColors[$status] ?? $statusColors['scheduled'];
                                    @endphp
                                    <tr class="border-b" style="border-color: var(--border-color);">
                                        <td class="py-3 px-4">
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->assignment_date->format('M j, Y') }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $schedule->assignment_date->format('l') }}
                                                @if($isToday)
                                                    <span class="ml-1" style="color: var(--success);">(Today)</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($schedule->post)
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-map-marker-alt text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium" style="color: var(--text-primary);">
                                                            {{ $schedule->post->name }}
                                                        </div>
                                                        <div class="text-xs" style="color: var(--text-secondary);">
                                                            {{ $schedule->post->code }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-sm" style="color: var(--text-secondary);">No post assigned</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($schedule->securityUser)
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 text-sm font-medium"
                                                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        {{ substr($schedule->securityUser->name, 0, 1) }}
                                                    </div>
                                                    <div>
                                                        <div class="font-medium" style="color: var(--text-primary);">
                                                            {{ $schedule->securityUser->name }}
                                                        </div>
                                                        <div class="text-xs" style="color: var(--text-secondary);">
                                                            {{ $schedule->securityUser->badge_number ?? 'No badge' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-sm" style="color: var(--text-secondary);">Unassigned</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                                  style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }};">
                                                <i class="fas {{ $color['icon'] }} mr-1 text-xs"></i>
                                                {{ ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('security.schedules.show', $schedule) }}"
                                                   class="action-btn"
                                                   title="View Details"
                                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </a>
                                                @if(!$isPast && $status === 'scheduled')
                                                <a href="{{ route('security.schedules.edit', $schedule) }}"
                                                   class="action-btn"
                                                   title="Edit"
                                                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column: Configuration & Actions -->
        <div class="space-y-6">
            <!-- Timing Details Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Timing Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Start Time</label>
                            <div class="p-3 rounded-lg border flex items-center justify-between"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                <div class="flex items-center">
                                    <i class="fas fa-play-circle mr-2" style="color: var(--success);"></i>
                                    <span>{{ \Carbon\Carbon::parse($securityShift->start_time)->format('h:i A') }}</span>
                                </div>
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    ({{ \Carbon\Carbon::parse($securityShift->start_time)->format('H:i') }})
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">End Time</label>
                            <div class="p-3 rounded-lg border flex items-center justify-between"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                <div class="flex items-center">
                                    <i class="fas fa-stop-circle mr-2" style="color: var(--danger);"></i>
                                    <span>{{ \Carbon\Carbon::parse($securityShift->end_time)->format('h:i A') }}</span>
                                </div>
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    ({{ \Carbon\Carbon::parse($securityShift->end_time)->format('H:i') }})
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Total Duration</label>
                            <div class="p-3 rounded-lg border flex items-center justify-between"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                <div class="flex items-center">
                                    <i class="fas fa-hourglass-half mr-2" style="color: var(--primary);"></i>
                                    <span>{{ $securityShift->duration_hours }} hours</span>
                                </div>
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    {{ round($securityShift->duration_hours * 60) }} minutes
                                </span>
                            </div>
                        </div>

                        @if($securityShift->is_overnight)
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Overnight Shift</label>
                            <div class="p-3 rounded-lg border flex items-center"
                                 style="border-color: var(--border-color); background-color: rgba(var(--primary-rgb), 0.05); color: var(--text-primary);">
                                <i class="fas fa-moon mr-2" style="color: var(--primary);"></i>
                                <span>This shift spans across midnight</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Break Schedule Card -->
            @if($securityShift->break_schedule && ($securityShift->break_schedule['has_break'] ?? false))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-coffee mr-2" style="color: var(--warning);"></i> Break Schedule
                        </h3>
                        <span class="px-2 py-1 text-xs rounded-full badge-warning">
                            {{ $securityShift->break_schedule['total_break_minutes'] ?? 0 }} min total
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($securityShift->break_schedule['breaks'] ?? [] as $break)
                        <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-center mb-2">
                                <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-utensils mr-2 text-sm" style="color: var(--warning);"></i>
                                    {{ $break['name'] }}
                                </div>
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    {{ $break['duration_minutes'] }} minutes
                                    @if($break['is_paid'] ?? false)
                                        <span class="ml-1" style="color: var(--success);" title="Paid Break">
                                            <i class="fas fa-dollar-sign"></i>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="far fa-clock mr-1"></i>
                                {{ \Carbon\Carbon::parse($break['start_time'])->format('h:i A') }} -
                                {{ \Carbon\Carbon::parse($break['end_time'])->format('h:i A') }}
                                @if($break['description'] ?? false)
                                    <div class="mt-1 text-xs" style="color: var(--text-secondary);">
                                        {{ $break['description'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Handover Configuration Card -->
            @if($securityShift->handover_config && ($securityShift->handover_config['has_handover'] ?? false))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--info);"></i> Handover Configuration
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Handover Duration</label>
                            <div class="p-3 rounded-lg border flex items-center justify-between"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                <div class="flex items-center">
                                    <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                                    <span>{{ $securityShift->handover_config['handover_duration'] ?? 30 }} minutes</span>
                                </div>
                                <span class="text-xs" style="color: var(--text-secondary);">Overlap period</span>
                            </div>
                        </div>

                        @if($securityShift->handover_config['handover_notes_required'] ?? false)
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Notes Requirement</label>
                            <div class="p-3 rounded-lg border flex items-center"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                                <i class="fas fa-sticky-note mr-2" style="color: var(--info);"></i>
                                <span>Handover notes are required</span>
                            </div>
                        </div>
                        @endif

                        @if(!empty($securityShift->handover_config['handover_checklist']))
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Handover Checklist</label>
                            <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <ul class="space-y-2">
                                    @foreach($securityShift->handover_config['handover_checklist'] as $item)
                                        @php
                                            $checklistLabels = [
                                                'equipment_check'      => 'Equipment Check',
                                                'incident_report'      => 'Incident Reports',
                                                'visitor_logs'         => 'Visitor Logs',
                                                'key_handover'         => 'Key Handover',
                                                'patrol_report'        => 'Patrol Report',
                                                'special_instructions' => 'Special Instructions',
                                            ];
                                        @endphp
                                        <li class="flex items-center text-sm" style="color: var(--text-primary);">
                                            <i class="fas fa-check-circle mr-2 text-xs" style="color: var(--success);"></i>
                                            {{ $checklistLabels[$item] ?? ucfirst(str_replace('_', ' ', $item)) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Quick Actions
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <a href="{{ route('security.schedules.create') }}?shift_id={{ $securityShift->id }}"
                           class="quick-action-btn"
                           style="background-color: rgba(var(--primary-rgb), 0.1); border-color: var(--primary); color: var(--primary);">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Create Schedule
                        </a>

                        <button type="button"
                                onclick="toggleShiftStatus({{ $securityShift->id }}, {{ $securityShift->is_active ? 'false' : 'true' }})"
                                class="quick-action-btn"
                                style="background-color: {{ $securityShift->is_active ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)' }};
                                       border-color: {{ $securityShift->is_active ? 'var(--danger)' : 'var(--success)' }};
                                       color: {{ $securityShift->is_active ? 'var(--danger)' : 'var(--success)' }};">
                            <i class="fas fa-{{ $securityShift->is_active ? 'toggle-on' : 'toggle-off' }} mr-2"></i>
                            {{ $securityShift->is_active ? 'Deactivate Shift' : 'Activate Shift' }}
                        </button>

                        <button type="button"
                                onclick="showDeleteModal({{ $securityShift->id }}, @js($securityShift->name))"
                                class="quick-action-btn"
                                style="background-color: rgba(var(--danger-rgb), 0.1); border-color: var(--danger); color: var(--danger);">
                            <i class="fas fa-trash mr-2"></i>
                            Delete Shift
                        </button>

                        <a href="{{ route('security.schedules.index') }}?shift_id={{ $securityShift->id }}"
                           class="quick-action-btn"
                           style="background-color: rgba(var(--info-rgb), 0.1); border-color: var(--info); color: var(--info);">
                            <i class="fas fa-list mr-2"></i>
                            View All Schedules
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Shift</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="deleteShiftName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);" id="deleteWarningText"></p>
                <div id="activeSchedulesWarning" class="hidden p-3 mb-4 rounded-lg border"
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                        <span style="color: var(--danger);" id="schedulesCountText"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('deleteModal')">
                Cancel
            </button>
            <form id="deleteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" id="confirmDeleteBtn"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmDelete()">
                <i class="fas fa-trash mr-2"></i> Delete Shift
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('styles')
<style>
/* ========== SHIFT ICONS ========== */
.shift-icon-main  { transition: all 0.3s ease; }
.shift-icon-main:hover { transform: rotate(15deg) scale(1.05); }
.shift-icon-small { transition: all 0.2s ease; }

/* ========== CARDS ========== */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* ========== BUTTONS ========== */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}
.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(var(--primary-rgb), 0.2);
}

.quick-action-btn {
    width: 100%;
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
    font-weight: 500;
}
.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* ========== ACTION BUTTONS ========== */
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

/* ========== BADGES ========== */
.badge-success { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger  { background-color: rgba(var(--danger-rgb), 0.1)  !important; color: var(--danger)  !important; border: 1px solid rgba(var(--danger-rgb), 0.3)  !important; }
.badge-info    { background-color: rgba(var(--info-rgb), 0.1)    !important; color: var(--info)    !important; border: 1px solid rgba(var(--info-rgb), 0.3)    !important; }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }

/* ========== MODAL ========== */
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }
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
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
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
.modal-close:hover { background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary); }
.modal-body { padding: 1.5rem; }
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

/* ========== LOADING OVERLAY ========== */
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

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
    .modal-container { margin: 1rem; }
}
@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
    .text-5xl { font-size: 2rem !important; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const CSRF_TOKEN = '{{ csrf_token() }}';
    const BASE_URL   = '{{ url("security/security-shifts") }}';

    // ========== TOGGLE STATUS ==========
    window.toggleShiftStatus = async function(shiftId, newStatus) {
        const action = newStatus ? 'activate' : 'deactivate';
        if (!confirm(`Are you sure you want to ${action} this shift?`)) return;

        try {
            showLoading('Updating shift status...');
            const response = await fetch(`${BASE_URL}/${shiftId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            hideLoading();

            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showToast(data.message || 'Failed to update status', 'error');
            }
        } catch (error) {
            hideLoading();
            console.error('Error toggling status:', error);
            showToast('Network error occurred', 'error');
        }
    };

    // ========== DELETE MODAL ==========
    window.showDeleteModal = async function(shiftId, shiftName) {
        document.getElementById('deleteShiftName').textContent = shiftName;
        showLoading('Checking shift usage...');

        try {
            const response = await fetch(`{{ route('security.security-shifts.check-in-use') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: [shiftId] }),
            });
            const data = await response.json();
            hideLoading();

            const deleteBtn = document.getElementById('confirmDeleteBtn');

            if (data.in_use_count > 0) {
                document.getElementById('deleteWarningText').textContent =
                    'This shift cannot be deleted because it is currently in use by active or future schedules.';
                document.getElementById('activeSchedulesWarning').classList.remove('hidden');

                let usageDetails = [];
                if (data.used_shifts && data.used_shifts.length > 0) {
                    const shift = data.used_shifts[0];
                    usageDetails = [`Used in ${shift.schedules_count || 0} active/future schedule(s)`];
                }
                document.getElementById('schedulesCountText').textContent = usageDetails.join(' ');

                deleteBtn.disabled = true;
                deleteBtn.style.opacity = '0.5';
                deleteBtn.style.cursor = 'not-allowed';
            } else {
                document.getElementById('deleteWarningText').textContent =
                    'Are you sure you want to move this shift to trash? You can restore it later from the trash bin.';
                document.getElementById('activeSchedulesWarning').classList.add('hidden');

                deleteBtn.disabled = false;
                deleteBtn.style.opacity = '1';
                deleteBtn.style.cursor = 'pointer';
            }
        } catch (error) {
            hideLoading();
            showToast('Error checking shift usage', 'error');
        }

        document.getElementById('deleteForm').action = `${BASE_URL}/${shiftId}`;
        openModal('deleteModal');
    };

    window.confirmDelete = function() {
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        if (!deleteBtn.disabled) {
            document.getElementById('deleteForm').submit();
        }
    };

    // ========== MODAL HELPERS ==========
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    };

    // Close on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal:not(.hidden)').forEach(modal => {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
        }
    });

    // ========== TOASTS ==========
    window.showToast = function(message, type = 'info') {
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
            type === 'error'   ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                                 'bg-blue-100 text-blue-800 border border-blue-200'
        }`;

        const messageEl = document.createElement('span');
        messageEl.className = 'text-sm font-medium flex-1';
        messageEl.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.className = 'ml-4';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.onclick = () => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        };

        toast.appendChild(messageEl);
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
    };

    // ========== LOADING ==========
    window.showLoading = function(message = 'Processing...') {
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
        const msgEl = document.getElementById('loadingMessage');
        if (msgEl) msgEl.textContent = message;
        overlay.classList.remove('hidden');
    };

    window.hideLoading = function() {
        document.getElementById('loadingOverlay')?.classList.add('hidden');
    };
});
</script>
@endpush