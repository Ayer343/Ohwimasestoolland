@extends('layouts.secu')

@section('title', 'Edit Security Schedule')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center p-6 gap-4">
            <div class="flex items-center space-x-3">
                <div class="p-3 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-edit text-2xl" style="color: var(--primary);"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Edit Security Schedule</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Schedule ID: #{{ $schedule->id }} • Created {{ $schedule->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Schedule Status Badge -->
                <div class="px-4 py-2 rounded-full text-sm font-medium flex items-center"
                     style="background-color:
                        @switch($schedule->status)
                            @case('active') rgba(var(--success-rgb), 0.1); color: var(--success); @break
                            @case('scheduled') rgba(var(--info-rgb), 0.1); color: var(--info); @break
                            @case('completed') rgba(var(--primary-rgb), 0.1); color: var(--primary); @break
                            @case('absent') rgba(var(--danger-rgb), 0.1); color: var(--danger); @break
                            @case('cancelled') rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary); @break
                            @default rgba(var(--warning-rgb), 0.1); color: var(--warning);
                        @endswitch
                     ">
                    <i class="fas
                        @switch($schedule->status)
                            @case('active') fa-check-circle @break
                            @case('scheduled') fa-clock @break
                            @case('completed') fa-check-double @break
                            @case('absent') fa-user-slash @break
                            @case('cancelled') fa-ban @break
                            @default fa-question-circle
                        @endswitch
                     mr-2"></i>
                    {{ ucfirst($schedule->status) }}
                </div>

                <div class="flex items-center space-x-2">
                    <a href="{{ route('security.schedules.show', $schedule->id) }}"
                       class="btn-secondary flex items-center">
                        <i class="fas fa-eye mr-2"></i> View
                    </a>
                    <a href="{{ route('security.schedules.index') }}"
                       class="btn-secondary flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Info Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 pt-0 border-t"
             style="border-color: var(--border-color);">
            <div class="flex items-center">
                <div class="p-2 rounded-full mr-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-map-marker-alt" style="color: var(--primary);"></i>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--text-secondary);">Security Post</div>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->name }}</div>
                    @if($schedule->post->code)
                        <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->post->code }}</div>
                    @endif
                </div>
            </div>

            <div class="flex items-center">
                <div class="p-2 rounded-full mr-3" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-calendar-alt" style="color: var(--success);"></i>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--text-secondary);">Assignment Date</div>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->assignment_date->format('l') }}</div>
                </div>
            </div>

            <div class="flex items-center">
                <div class="p-2 rounded-full mr-3" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--text-secondary);">Shift</div>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->shift->name }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">
                        {{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}
                        @if($schedule->shift->is_overnight) <span class="ml-1">🌙</span> @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center">
                <div class="p-2 rounded-full mr-3" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-user-shield" style="color: var(--warning);"></i>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--text-secondary);">Personnel</div>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->securityUser->name }}</div>
                    @if($schedule->securityUser->badge_number)
                        <div class="text-xs" style="color: var(--text-secondary);">Badge: #{{ $schedule->securityUser->badge_number }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <form action="{{ route('security.schedules.update', $schedule->id) }}"
          method="POST"
          id="security-schedule-form"
          class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT COLUMN (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Security Post -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-map-marker-alt mr-2" style="color: var(--primary);"></i>
                            Security Post
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            Required *
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="security_post_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Select Security Post <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full p-2 border rounded @error('security_post_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="security_post_id" name="security_post_id" required>
                                <option value="">-- Choose Security Post --</option>
                                @foreach($securityPosts as $post)
                                    <option value="{{ $post->id }}"
                                            data-max-personnel="{{ $post->max_personnel }}"
                                            data-post-type="{{ $post->type }}"
                                            data-post-location="{{ $post->location ?? 'N/A' }}"
                                            data-is-active="{{ $post->is_active ? 'true' : 'false' }}"
                                            {{ old('security_post_id', $schedule->security_post_id) == $post->id ? 'selected' : '' }}>
                                        {{ $post->name }}
                                        @if($post->code)({{ $post->code }})@endif
                                        @if(!$post->is_active) [Inactive] @endif
                                        - Max: {{ $post->max_personnel }} personnel
                                    </option>
                                @endforeach
                            </select>

                            <div id="post-capacity-info" class="mt-3 p-4 rounded"
                                 style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-users mr-2" style="color: var(--info);"></i>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">Current Post Status:</span>
                                    </div>
                                    <span id="post-active-status" class="text-xs px-2 py-1 rounded-full"
                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        Active
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Assigned Today</div>
                                        <div class="text-lg font-semibold" id="current-assignments" style="color: var(--text-primary);">0</div>
                                    </div>
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Max Capacity</div>
                                        <div class="text-lg font-semibold" id="max-personnel" style="color: var(--text-primary);">0</div>
                                    </div>
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Available Slots</div>
                                        <div class="text-lg font-semibold" id="available-slots" style="color: var(--success);">0</div>
                                    </div>
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Post Type</div>
                                        <div class="text-sm font-medium" id="post-type-display" style="color: var(--text-primary);">-</div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span style="color: var(--text-secondary);">Capacity Utilization</span>
                                        <span id="capacity-percentage" style="color: var(--text-primary);">0%</span>
                                    </div>
                                    <div class="w-full h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                        <div id="capacity-progress" class="h-2 rounded-full" style="width: 0%; transition: width 0.3s ease;"></div>
                                    </div>
                                </div>
                                <div id="capacity-warning" class="mt-3 p-2 rounded text-sm hidden"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1" style="color: var(--warning);"></i>
                                    <span id="capacity-warning-text" style="color: var(--text-primary);"></span>
                                </div>
                            </div>

                            @error('security_post_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Date & Shift -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--success);"></i>
                            Schedule Details
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            Required *
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="assignment_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Assignment Date <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="date"
                                       class="w-full p-2 border rounded @error('assignment_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="assignment_date" name="assignment_date"
                                       value="{{ old('assignment_date', $schedule->assignment_date->format('Y-m-d')) }}"
                                       required min="{{ date('Y-m-d') }}">
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-calendar-day" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                            <div id="day-info" class="mt-2 flex items-center text-sm">
                                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                <span style="color: var(--text-secondary);" id="day-of-week-display">
                                    {{ $schedule->assignment_date->format('l, F j, Y') }}
                                </span>
                            </div>
                            @error('assignment_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="security_shift_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Security Shift <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full p-2 border rounded @error('security_shift_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="security_shift_id" name="security_shift_id" required>
                                <option value="">-- Select Shift --</option>
                                @foreach($securityShifts as $shift)
                                    <option value="{{ $shift->id }}"
                                            data-start-time="{{ $shift->start_time }}"
                                            data-end-time="{{ $shift->end_time }}"
                                            data-duration="{{ $shift->duration_hours }}"
                                            data-category="{{ $shift->category }}"
                                            data-is-overnight="{{ $shift->is_overnight ? 'true' : 'false' }}"
                                            data-has-handover="{{ isset($shift->handover_config['has_handover']) && $shift->handover_config['has_handover'] ? 'true' : 'false' }}"
                                            data-handover-duration="{{ $shift->handover_config['handover_duration'] ?? 30 }}"
                                            data-applicable-days="{{ json_encode($shift->applicable_days ?? [1,2,3,4,5]) }}"
                                            data-rotation-type="{{ $shift->rotation_type ?? 'fixed' }}"
                                            {{ old('security_shift_id', $schedule->security_shift_id) == $shift->id ? 'selected' : '' }}>
                                        {{ $shift->name }}
                                        ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                        @if($shift->is_overnight) 🌙 @endif
                                        - {{ ucfirst($shift->category) }}
                                        @if(isset($shift->rotation_type) && $shift->rotation_type === 'rotating') 🔄 @endif
                                    </option>
                                @endforeach
                            </select>

                            <div id="shift-details" class="mt-3 p-4 rounded"
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Time</span>
                                        <div class="font-medium" id="shift-time-display" style="color: var(--text-primary);">
                                            {{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Duration</span>
                                        <div class="font-medium" id="shift-duration-display" style="color: var(--text-primary);">
                                            {{ $schedule->shift->duration_hours }} hours
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Category</span>
                                        <div class="font-medium" id="shift-category-display" style="color: var(--text-primary);">
                                            {{ ucfirst($schedule->shift->category) }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Handover</span>
                                        <div class="font-medium" id="shift-handover-display" style="color: var(--text-primary);">
                                            @if(isset($schedule->shift->handover_config['has_handover']) && $schedule->shift->handover_config['has_handover'])
                                                Yes ({{ $schedule->shift->handover_config['handover_duration'] ?? 30 }}min)
                                            @else
                                                No
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div id="overnight-warning" class="mt-2 p-2 rounded text-xs {{ $schedule->shift->is_overnight ? '' : 'hidden' }}"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-moon mr-1"></i>
                                    <span>This is an overnight shift ending the next day.</span>
                                </div>

                                <div id="applicability-warning" class="mt-2 p-2 rounded text-xs hidden"
                                     style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <span id="applicability-warning-text"></span>
                                </div>
                            </div>

                            @error('security_shift_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div id="handover-preview-container" class="mt-4 p-4 rounded {{ $schedule->handover_info ? '' : 'hidden' }}"
                         style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 4px solid var(--primary);">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-handshake mr-2" style="color: var(--primary);"></i>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Handover Schedule</span>
                            </div>
                            <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                Required
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Handover Start</span>
                                <div class="font-medium" id="handover-start-display" style="color: var(--text-primary);">
                                    {{ $schedule->handover_info['handover_start'] ?? '--:--' }}
                                </div>
                            </div>
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Handover End</span>
                                <div class="font-medium" id="handover-end-display" style="color: var(--text-primary);">
                                    {{ $schedule->handover_info['handover_end'] ?? '--:--' }}
                                </div>
                            </div>
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Duration</span>
                                <div class="font-medium" id="handover-duration-display" style="color: var(--text-primary);">
                                    {{ $schedule->handover_info['handover_duration'] ?? 30 }} minutes
                                </div>
                            </div>
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Previous Shift</span>
                                <div class="font-medium" id="previous-shift-display" style="color: var(--text-primary);">
                                    {{ isset($schedule->handover_info['previous_shift_id']) ? 'Shift #'.$schedule->handover_info['previous_shift_id'] : 'None' }}
                                </div>
                            </div>
                        </div>

                        @if(!empty($schedule->handover_info['checklist'] ?? []))
                            <div class="mt-3 pt-3 border-t" style="border-color: rgba(var(--primary-rgb), 0.2);">
                                <span class="text-xs font-medium" style="color: var(--text-primary);">Handover Checklist:</span>
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    @foreach($schedule->handover_info['checklist'] as $item)
                                        <div class="flex items-center text-xs">
                                            <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                            <span style="color: var(--text-secondary);">{{ $item }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Status Management -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-flag mr-2" style="color: var(--warning);"></i>
                            Schedule Status
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            Update Status
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Current Status <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full p-2 border rounded @error('status') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="status" name="status" required>
                                <option value="scheduled" {{ old('status', $schedule->status) == 'scheduled' ? 'selected' : '' }}>🕐 Scheduled</option>
                                <option value="active" {{ old('status', $schedule->status) == 'active' ? 'selected' : '' }}>✅ Active (Checked In)</option>
                                <option value="completed" {{ old('status', $schedule->status) == 'completed' ? 'selected' : '' }}>✔️ Completed</option>
                                <option value="absent" {{ old('status', $schedule->status) == 'absent' ? 'selected' : '' }}>❌ Absent</option>
                                <option value="cancelled" {{ old('status', $schedule->status) == 'cancelled' ? 'selected' : '' }}>🚫 Cancelled</option>
                            </select>
                            @error('status')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">Schedule Timeline</label>
                            <div class="p-3 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <div class="flex items-center justify-between text-xs mb-2">
                                    <span style="color: var(--text-secondary);">Created:</span>
                                    <span style="color: var(--text-primary);">{{ $schedule->created_at->format('M j, Y H:i') }}</span>
                                </div>
                                @if($schedule->checkin_time)
                                    <div class="flex items-center justify-between text-xs mb-2">
                                        <span style="color: var(--text-secondary);">Check-in:</span>
                                        <span style="color: var(--success);">{{ $schedule->checkin_time->format('M j, Y H:i') }}</span>
                                    </div>
                                @endif
                                @if($schedule->checkout_time)
                                    <div class="flex items-center justify-between text-xs">
                                        <span style="color: var(--text-secondary);">Check-out:</span>
                                        <span style="color: var(--info);">{{ $schedule->checkout_time->format('M j, Y H:i') }}</span>
                                    </div>
                                @endif
                                @if($schedule->late_minutes > 0)
                                    <div class="flex items-center justify-between text-xs mt-2">
                                        <span style="color: var(--text-secondary);">Late by:</span>
                                        <span style="color: var(--danger);">{{ $schedule->late_minutes }} minutes</span>
                                    </div>
                                @endif
                                @if($schedule->overtime_minutes > 0)
                                    <div class="flex items-center justify-between text-xs">
                                        <span style="color: var(--text-secondary);">Overtime:</span>
                                        <span style="color: var(--warning);">{{ $schedule->overtime_minutes }} minutes</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div id="status-warning" class="mt-3 p-3 rounded text-sm hidden"
                         style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                        <span id="status-warning-text" style="color: var(--text-primary);"></span>
                    </div>
                </div>

                <!-- Rotation (conditional) -->
                <div id="rotation-options" class="card p-6 {{ $schedule->shift->rotation_type === 'rotating' ? '' : 'hidden' }}">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i>
                            Rotation Settings
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            Rotating Shift
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Rotation Sequence</div>
                            <div class="flex items-center space-x-2 mt-2">
                                <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $schedule->rotation_data['current_position'] ?? 'Morning' }}
                                </span>
                                <i class="fas fa-arrow-right text-xs" style="color: var(--text-secondary);"></i>
                                <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    {{ $schedule->rotation_data['next_position'] ?? 'Evening' }}
                                </span>
                            </div>
                        </div>
                        <div class="p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Current Position</div>
                            <div class="font-medium mt-1" id="current-rotation-position" style="color: var(--text-primary);">
                                {{ $schedule->rotation_data['current_position'] ?? 'Morning' }}
                            </div>
                        </div>
                        <div class="p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Next Rotation</div>
                            <div class="font-medium mt-1" id="next-rotation-date" style="color: var(--text-primary);">
                                {{ isset($schedule->rotation_data['next_rotation_date']) ? \Carbon\Carbon::parse($schedule->rotation_data['next_rotation_date'])->format('M j, Y') : 'Not scheduled' }}
                            </div>
                        </div>
                    </div>
                    @if(!empty($schedule->rotation_data['user_rotation_history'] ?? []))
                        <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="text-xs font-medium mb-2" style="color: var(--text-primary);">Recent Rotation History</div>
                            <div class="space-y-2">
                                @foreach($schedule->rotation_data['user_rotation_history'] as $history)
                                    <div class="flex items-center justify-between text-xs">
                                        <span style="color: var(--text-secondary);">{{ $history['date'] }}</span>
                                        <span class="px-2 py-0.5 rounded"
                                              style="background-color: {{ $history['was_on_time'] ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }};
                                                     color: {{ $history['was_on_time'] ? 'var(--success)' : 'var(--warning)' }};">
                                            {{ $history['duration'] }} hrs
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- RIGHT COLUMN (1/3) -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Personnel -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-user-shield mr-2" style="color: var(--info);"></i>
                            Security Personnel
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            Required *
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="security_user_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Assign Security Personnel <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full p-2 border rounded @error('security_user_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="security_user_id" name="security_user_id" required>
                                <option value="">-- Select Personnel --</option>
                                @foreach($securityPersonnel as $personnel)
                                    <option value="{{ $personnel->id }}"
                                            data-phone="{{ $personnel->phone }}"
                                            data-email="{{ $personnel->email }}"
                                            data-badge="{{ $personnel->badge_number ?? 'N/A' }}"
                                            data-status="{{ $personnel->status }}"
                                            data-last-shift="{{ $personnel->schedules()->latest('assignment_date')->first()?->assignment_date->format('Y-m-d') }}"
                                            data-shifts-count="{{ $personnel->schedules()->count() }}"
                                            data-attendance-rate="{{ $personnel->schedules()->where('status', 'completed')->count() > 0 ? round(($personnel->schedules()->where('status', 'completed')->count() / max($personnel->schedules()->count(), 1)) * 100, 2) : 100 }}"
                                            {{ old('security_user_id', $schedule->security_user_id) == $personnel->id ? 'selected' : '' }}>
                                        {{ $personnel->name }}
                                        @if($personnel->badge_number)(#{{ $personnel->badge_number }})@endif
                                        - {{ $personnel->phone }}
                                    </option>
                                @endforeach
                            </select>

                            <div id="personnel-profile" class="mt-3 p-4 rounded"
                                 style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.05) 0%, rgba(var(--primary-rgb), 0.05) 100%);">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                                        <i class="fas fa-user-shield text-2xl" style="color: var(--primary);"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="font-medium" id="personnel-name" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->name }}
                                        </div>
                                        <div class="text-xs" id="personnel-badge" style="color: var(--text-secondary);">
                                            Badge: #{{ $schedule->securityUser->badge_number ?? 'N/A' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                        <span class="text-xs" style="color: var(--text-secondary);">Phone</span>
                                        <div class="font-medium" id="personnel-phone" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->phone }}
                                        </div>
                                    </div>
                                    <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                        <span class="text-xs" style="color: var(--text-secondary);">Email</span>
                                        <div class="font-medium text-sm" id="personnel-email" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->email }}
                                        </div>
                                    </div>
                                    <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                        <span class="text-xs" style="color: var(--text-secondary);">Last Shift</span>
                                        <div class="font-medium" id="personnel-last-shift" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->schedules()->latest('assignment_date')->first()?->assignment_date->format('M j, Y') ?? 'No previous shifts' }}
                                        </div>
                                    </div>
                                    <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                        <span class="text-xs" style="color: var(--text-secondary);">Total Shifts</span>
                                        <div class="font-medium" id="personnel-total-shifts" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->schedules()->count() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t" style="border-color: rgba(var(--border-color), 0.5);">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs" style="color: var(--text-secondary);">Attendance Rate</span>
                                        @php
                                            $totalShifts = $schedule->securityUser->schedules()->count();
                                            $completedShifts = $schedule->securityUser->schedules()->where('status', 'completed')->count();
                                            $attendanceRate = $totalShifts > 0 ? round(($completedShifts / $totalShifts) * 100, 2) : 100;
                                        @endphp
                                        <span class="text-xs font-medium"
                                              style="color: {{ $attendanceRate >= 90 ? 'var(--success)' : ($attendanceRate >= 75 ? 'var(--warning)' : 'var(--danger)') }};">
                                            {{ $attendanceRate }}%
                                        </span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                        <div class="h-1.5 rounded-full"
                                             style="width: {{ $attendanceRate }}%;
                                                    background-color: {{ $attendanceRate >= 90 ? 'var(--success)' : ($attendanceRate >= 75 ? 'var(--warning)' : 'var(--danger)') }};">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="personnel-availability-status" class="mt-3 p-3 rounded flex items-start"
                                 style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                <div>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">Available for this date</span>
                                    <p class="text-xs mt-0.5" id="availability-detail" style="color: var(--text-secondary);">
                                        No conflicting assignments found.
                                    </p>
                                </div>
                            </div>

                            <div id="rest-period-warning" class="mt-3 p-3 rounded text-sm hidden"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                                <div class="flex items-start">
                                    <i class="fas fa-clock mr-2 mt-0.5" style="color: var(--warning);"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">Insufficient Rest Period</span>
                                        <p class="text-xs mt-1" id="rest-period-text" style="color: var(--text-secondary);"></p>
                                    </div>
                                </div>
                            </div>

                            <div id="weekly-hours-warning" class="mt-3 p-3 rounded text-sm hidden"
                                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">Weekly Hour Limit Exceeded</span>
                                        <p class="text-xs mt-1" id="weekly-hours-text" style="color: var(--text-secondary);"></p>
                                    </div>
                                </div>
                            </div>

                            @error('security_user_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Assignment Details -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-clipboard-list mr-2" style="color: var(--warning);"></i>
                            Assignment Details
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            Optional
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="emergency_contact" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-phone-alt mr-1" style="color: var(--danger);"></i>
                                Emergency Contact
                            </label>
                            <input type="text"
                                   class="w-full p-2 border rounded @error('emergency_contact') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="emergency_contact" name="emergency_contact"
                                   value="{{ old('emergency_contact', $schedule->emergency_contact) }}"
                                   placeholder="e.g., +233 XXX XXX XXX / Supervisor Name">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> Contact number for emergencies during this shift
                            </p>
                            @error('emergency_contact')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="special_instructions" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-exclamation-circle mr-1" style="color: var(--warning);"></i>
                                Special Instructions
                            </label>
                            <textarea class="w-full p-2 border rounded @error('special_instructions') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="special_instructions" name="special_instructions" rows="4"
                                      placeholder="Specific instructions for this assignment...">{{ old('special_instructions', $schedule->special_instructions) }}</textarea>
                            <div class="flex justify-between mt-1">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i> Max 1000 characters.
                                </p>
                                <span id="special-instructions-counter" class="text-xs" style="color: var(--text-secondary);">0/1000</span>
                            </div>
                            @error('special_instructions')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="notes" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-sticky-note mr-1" style="color: var(--info);"></i>
                                Additional Notes
                            </label>
                            <textarea class="w-full p-2 border rounded @error('notes') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="notes" name="notes" rows="3"
                                      placeholder="Any additional notes about this assignment...">{{ old('notes', $schedule->notes) }}</textarea>
                            <div class="flex justify-between mt-1">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i> Max 500 characters.
                                </p>
                                <span id="notes-counter" class="text-xs" style="color: var(--text-secondary);">0/500</span>
                            </div>
                            @error('notes')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="text-xs font-medium mb-2" style="color: var(--text-primary);">Audit Information</div>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Created by:</span>
                                    <span style="color: var(--text-primary);">{{ $schedule->assignedBy->name ?? 'System' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Created at:</span>
                                    <span style="color: var(--text-primary);">{{ $schedule->created_at->format('M j, Y H:i') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Last updated:</span>
                                    <span style="color: var(--text-primary);">{{ $schedule->updated_at->format('M j, Y H:i') }}</span>
                                </div>
                                @if($schedule->handover_info && isset($schedule->handover_info['notes_submitted_by']))
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Handover by:</span>
                                        <span style="color: var(--text-primary);">User #{{ $schedule->handover_info['notes_submitted_by'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <button type="button"
                                onclick="sendAssignmentNotification({{ $schedule->id }})"
                                class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <span class="flex items-center"><i class="fas fa-bell mr-2"></i> Send Assignment Notification</span>
                            <i class="fas fa-chevron-right text-xs"></i>
                        </button>

                        @if($schedule->status === 'active')
                            <button type="button"
                                    onclick="quickCompleteSchedule({{ $schedule->id }})"
                                    class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <span class="flex items-center"><i class="fas fa-check-double mr-2"></i> Mark as Completed</span>
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        @endif

                        @if($schedule->status === 'scheduled')
                            <button type="button"
                                    onclick="quickMarkAbsent({{ $schedule->id }})"
                                    class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <span class="flex items-center"><i class="fas fa-user-slash mr-2"></i> Mark as Absent</span>
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        @endif

                        @if(!in_array($schedule->status, ['completed', 'cancelled']))
                            <button type="button"
                                    onclick="confirmCancelSchedule({{ $schedule->id }})"
                                    class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <span class="flex items-center"><i class="fas fa-ban mr-2"></i> Cancel Schedule</span>
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card p-6">
            <div class="flex flex-col lg:flex-row justify-between items-center gap-4">
                <button type="button"
                        onclick="confirmDelete({{ $schedule->id }})"
                        class="px-6 py-2 rounded flex items-center order-2 lg:order-1"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Schedule
                </button>

                <div class="flex flex-col sm:flex-row items-center gap-4 order-1 lg:order-2 w-full lg:w-auto">
                    <div id="form-status" class="text-sm" style="color: var(--text-secondary);"></div>
                    <div class="flex space-x-4 w-full sm:w-auto">
                        <button type="submit"
                                class="btn-primary flex items-center justify-center px-6 py-2 w-full sm:w-auto"
                                id="submit-button">
                            <i class="fas fa-save mr-2"></i> Update Schedule
                        </button>
                        <a href="{{ route('security.schedules.index') }}"
                           class="btn-secondary flex items-center justify-center px-6 py-2 w-full sm:w-auto">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>

            <div id="submit-warnings" class="mt-4 hidden">
                <div class="flex items-center p-4 rounded text-sm"
                     style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                    <span id="submit-warning-text" style="color: var(--text-primary);"></span>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Delete Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 pt-5 pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium" style="color: var(--text-primary);">Delete Security Schedule</h3>
                        <div class="mt-2">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Are you sure you want to delete this schedule? This action cannot be undone.
                                This will permanently remove the assignment for
                                <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->securityUser->name }}</span>
                                at <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->name }}</span>
                                on <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</span>.
                            </p>
                        </div>
                        @if($schedule->status === 'active')
                            <div class="mt-3 p-3 rounded text-sm"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                                <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                                <span style="color: var(--text-secondary);">This shift is currently active. Deleting it will require immediate reassignment.</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 flex justify-end space-x-3" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 rounded text-sm font-medium"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    Cancel
                </button>
                <form id="delete-form" action="{{ route('security.schedules.destroy', $schedule->id) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-medium text-white"
                            style="background-color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i>Delete Schedule
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="notification-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="px-6 pt-5 pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-bell" style="color: var(--info);"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium" style="color: var(--text-primary);">Send Assignment Notification</h3>
                        <div class="mt-2">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Send a notification to <span id="notification-personnel-name" style="color: var(--text-primary); font-weight: 500;"></span>
                                about their assignment.
                            </p>
                        </div>
                        <div class="mt-4 space-y-3">
                            <label class="flex items-center p-3 rounded border"
                                   style="border-color: var(--border-color); background-color: rgba(var(--success-rgb), 0.02);">
                                <input type="radio" name="notification_channel" value="sms" checked class="mr-3">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-sms mr-2" style="color: var(--success);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">SMS</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Send to {{ $schedule->securityUser->phone }}</p>
                                </div>
                            </label>
                            <label class="flex items-center p-3 rounded border"
                                   style="border-color: var(--border-color); background-color: rgba(var(--info-rgb), 0.02);">
                                <input type="radio" name="notification_channel" value="email" class="mr-3">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2" style="color: var(--info);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">Email</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Send to {{ $schedule->securityUser->email }}</p>
                                </div>
                            </label>
                            <label class="flex items-center p-3 rounded border"
                                   style="border-color: var(--border-color); background-color: rgba(var(--warning-rgb), 0.02);">
                                <input type="radio" name="notification_channel" value="whatsapp" class="mr-3">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fab fa-whatsapp mr-2" style="color: var(--warning);"></i>
                                        <span class="font-medium" style="color: var(--text-primary);">WhatsApp</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Send to {{ $schedule->securityUser->phone }}</p>
                                </div>
                            </label>
                        </div>
                        <div class="mt-4">
                            <label for="notification_message" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Additional Message (Optional)</label>
                            <textarea id="notification_message" rows="3"
                                      class="w-full p-2 border rounded text-sm"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Add any additional instructions..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 flex justify-end space-x-3" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                <button type="button" onclick="closeNotificationModal()"
                        class="px-4 py-2 rounded text-sm font-medium"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    Cancel
                </button>
                <button type="button" onclick="sendNotification({{ $schedule->id }})"
                        class="px-4 py-2 rounded text-sm font-medium text-white"
                        style="background-color: var(--info);">
                    <i class="fas fa-paper-plane mr-2"></i>Send Notification
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// =========================================
// ROUTES (blade-injected; keeps JS off hardcoded /admin/...)
// =========================================
const ROUTES = {
    postCapacity:      "{{ route('security.schedules.post-capacity') }}",
    checkSchedule:     "{{ route('security.schedules.check-schedule', ['schedule_id' => $schedule->id]) }}",
    handoverInfo:      "{{ route('security.schedules.handover-info') }}",
    checkAvailability: "{{ route('security.schedules.check-availability') }}",
    status:            (id) => "{{ url('security/schedules') }}/" + id + "/status",
    sendNotification:  (id) => "{{ url('security/schedules') }}/" + id + "/send-notification",
};

// =========================================
// SAFE BLADE VALUES (avoid O'Brien-style breakage)
// =========================================
const SCHEDULE = {
    id:                 {{ $schedule->id }},
    postId:             {{ $schedule->security_post_id }},
    shiftId:            {{ $schedule->security_shift_id }},
    userId:             {{ $schedule->security_user_id }},
    date:               @json($schedule->assignment_date->format('Y-m-d')),
    status:             @json($schedule->status),
    userName:           @json($schedule->securityUser->name),
    emergencyContact:   @json($schedule->emergency_contact),
    specialInstructions:@json($schedule->special_instructions),
    notes:              @json($schedule->notes),
    rotationData:       @json($schedule->rotation_data),
};

document.addEventListener('DOMContentLoaded', function () {
    // ===========================
    // ELEMENTS
    // ===========================
    const form           = document.getElementById('security-schedule-form');
    const postSelect     = document.getElementById('security_post_id');
    const shiftSelect    = document.getElementById('security_shift_id');
    const personnelSelect= document.getElementById('security_user_id');
    const dateInput      = document.getElementById('assignment_date');
    const statusSelect   = document.getElementById('status');
    const submitButton   = document.getElementById('submit-button');
    const formStatus     = document.getElementById('form-status');

    const postCapacityInfo    = document.getElementById('post-capacity-info');
    const handoverPreview     = document.getElementById('handover-preview-container');
    const personnelAvailability = document.getElementById('personnel-availability-status');
    const restPeriodWarning   = document.getElementById('rest-period-warning');
    const weeklyHoursWarning  = document.getElementById('weekly-hours-warning');
    const rotationOptions     = document.getElementById('rotation-options');
    const statusWarning       = document.getElementById('status-warning');
    const submitWarnings      = document.getElementById('submit-warnings');

    const el = id => document.getElementById(id);

    // ===========================
    // STATE
    // ===========================
    const original = {
        postId:    String(SCHEDULE.postId),
        shiftId:   String(SCHEDULE.shiftId),
        userId:    String(SCHEDULE.userId),
        date:      SCHEDULE.date,
        status:    SCHEDULE.status,
        emergency: SCHEDULE.emergencyContact || '',
        special:   SCHEDULE.specialInstructions || '',
        notes:     SCHEDULE.notes || '',
    };
    let currentCapacity = 0;
    let isFormValid = true;

    // ===========================
    // DAY-OF-WEEK
    // ===========================
    const daysOfWeek = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    function getDayOfWeek(s) { return daysOfWeek[new Date(s + 'T12:00:00').getDay()]; }
    function getDayOfWeekIso(s) { const d = new Date(s + 'T12:00:00').getDay(); return d === 0 ? 7 : d; }

    // ===========================
    // TEXT COUNTERS
    // ===========================
    function updateTextCounters() {
        const si = document.getElementById('special_instructions');
        const n  = document.getElementById('notes');
        const siC = el('special-instructions-counter');
        const nC  = el('notes-counter');
        if (siC) {
            siC.textContent = `${si.value.length}/1000`;
            siC.style.color = si.value.length > 1000 ? 'var(--danger)' : si.value.length > 950 ? 'var(--warning)' : 'var(--text-secondary)';
        }
        if (nC) {
            nC.textContent = `${n.value.length}/500`;
            nC.style.color = n.value.length > 500 ? 'var(--danger)' : n.value.length > 450 ? 'var(--warning)' : 'var(--text-secondary)';
        }
    }

    // ===========================
    // POST CAPACITY
    // ===========================
    async function updatePostCapacity() {
        const postId = postSelect.value;
        const date = dateInput.value;
        if (!postId || !date) { postCapacityInfo?.classList.add('hidden'); return; }

        try {
            const res = await fetch(`${ROUTES.postCapacity}?post_id=${postId}&date=${date}`);
            const data = await res.json();
            if (!data.success) return;

            // Accept both admin and supervisor response shapes.
            currentCapacity = data.assigned ?? data.currently_assigned ?? 0;
            const max = data.max_personnel ?? data.capacity ?? 0;
            const available = data.available ?? data.available_slots ?? 0;

            const included = await checkIfCurrentScheduleIncluded(postId, date);
            const adjusted = included ? currentCapacity - 1 : currentCapacity;

            el('current-assignments').textContent = adjusted;
            el('max-personnel').textContent = max;
            el('available-slots').textContent = max - adjusted;

            const pct = max > 0 ? (adjusted / max) * 100 : 0;
            const bar = el('capacity-progress');
            bar.style.width = `${Math.min(pct, 100)}%`;
            el('capacity-percentage').textContent = `${Math.round(pct)}%`;
            bar.style.backgroundColor = pct >= 90 ? 'var(--danger)' : pct >= 75 ? 'var(--warning)' : 'var(--success)';

            const warn = el('capacity-warning');
            const warnTxt = el('capacity-warning-text');
            if (max - adjusted <= 0 && !included) {
                warn.classList.remove('hidden');
                warnTxt.textContent = 'This post is at full capacity for the selected date.';
                bar.style.backgroundColor = 'var(--danger)';
            } else if (max - adjusted < 3 && !included) {
                warn.classList.remove('hidden');
                warnTxt.textContent = `Only ${max - adjusted} slot(s) available for this post on the selected date.`;
                bar.style.backgroundColor = 'var(--warning)';
            } else {
                warn.classList.add('hidden');
            }
            postCapacityInfo?.classList.remove('hidden');
        } catch (e) {
            console.error('post-capacity:', e);
        }
    }

    async function checkIfCurrentScheduleIncluded(postId, date) {
        try {
            const res = await fetch(`${ROUTES.checkSchedule}?post_id=${postId}&date=${date}`);
            const data = await res.json();
            return data.is_included || false;
        } catch (e) {
            return false;
        }
    }

    function updatePostDetails() {
        const opt = postSelect.options[postSelect.selectedIndex];
        if (!opt.value) return;
        const postType = opt.dataset.postType || 'Standard';
        const isActive = opt.dataset.isActive === 'true';
        const typeDisplay = el('post-type-display');
        if (typeDisplay) typeDisplay.textContent = postType.replace('_', ' ').toUpperCase();
        const statusBadge = el('post-active-status');
        if (statusBadge) {
            statusBadge.textContent = isActive ? 'Active' : 'Inactive';
            statusBadge.style.backgroundColor = isActive ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)';
            statusBadge.style.color = isActive ? 'var(--success)' : 'var(--text-secondary)';
        }
    }

    // ===========================
    // SHIFT
    // ===========================
    function updateShiftDetails() {
        const opt = shiftSelect.options[shiftSelect.selectedIndex];
        if (!opt.value) return;
        const start = opt.dataset.startTime?.substring(0, 5) || '--:--';
        const end = opt.dataset.endTime?.substring(0, 5) || '--:--';
        const duration = opt.dataset.duration || '0';
        const category = opt.dataset.category || 'standard';
        const overnight = opt.dataset.isOvernight === 'true';
        const hasHandover = opt.dataset.hasHandover === 'true';
        const handoverDuration = opt.dataset.handoverDuration || '30';
        const rotationType = opt.dataset.rotationType || 'fixed';
        const applicableDays = JSON.parse(opt.dataset.applicableDays || '[]');

        el('shift-time-display').textContent = `${start} - ${end}`;
        el('shift-duration-display').textContent = `${duration} hours`;
        el('shift-category-display').textContent = category.charAt(0).toUpperCase() + category.slice(1);
        el('shift-handover-display').textContent = hasHandover ? `Yes (${handoverDuration}min)` : 'No';
        el('overnight-warning')?.classList.toggle('hidden', !overnight);

        const dateVal = dateInput.value;
        const applWarn = el('applicability-warning');
        const applTxt = el('applicability-warning-text');
        if (dateVal && applWarn && applTxt) {
            if (applicableDays.length > 0 && !applicableDays.includes(getDayOfWeekIso(dateVal))) {
                applWarn.classList.remove('hidden');
                applTxt.textContent = `This shift is not applicable on ${getDayOfWeek(dateVal)}s.`;
            } else {
                applWarn.classList.add('hidden');
            }
        }
        rotationOptions?.classList.toggle('hidden', rotationType !== 'rotating');
        if (rotationType === 'rotating') updateRotationInfo();
        updateHandoverInfo();
    }

    async function updateHandoverInfo() {
        const shiftId = shiftSelect.value;
        const date = dateInput.value;
        const postId = postSelect.value;
        if (!shiftId || !date || !postId) { handoverPreview?.classList.add('hidden'); return; }
        try {
            const res = await fetch(`${ROUTES.handoverInfo}?shift_id=${shiftId}&date=${date}&post_id=${postId}`);
            const data = await res.json();
            if (data.success && data.handover_info) {
                const hi = data.handover_info;
                el('handover-start-display').textContent = hi.handover_start || '--:--';
                el('handover-end-display').textContent = hi.handover_end || '--:--';
                el('handover-duration-display').textContent = `${hi.handover_duration || 30} minutes`;
                el('previous-shift-display').textContent = hi.previous_shift_id ? 'Shift #' + hi.previous_shift_id : 'None';
                handoverPreview?.classList.remove('hidden');
            } else {
                handoverPreview?.classList.add('hidden');
            }
        } catch (e) {
            handoverPreview?.classList.add('hidden');
        }
    }

    function updateRotationInfo() {
        if (!SCHEDULE.rotationData) return;
        if (el('current-rotation-position')) el('current-rotation-position').textContent = SCHEDULE.rotationData.current_position || 'Morning';
        if (el('next-rotation-date') && SCHEDULE.rotationData.next_rotation_date) {
            el('next-rotation-date').textContent = new Date(SCHEDULE.rotationData.next_rotation_date)
                .toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }

    // ===========================
    // PERSONNEL
    // ===========================
    function updatePersonnelInfo() {
        const opt = personnelSelect.options[personnelSelect.selectedIndex];
        if (!opt.value) return;
        const name = opt.text.split('-')[0].trim();
        const badge = opt.dataset.badge || 'N/A';
        const phone = opt.dataset.phone || 'N/A';
        const email = opt.dataset.email || 'N/A';
        const lastShift = opt.dataset.lastShift || 'No previous shifts';
        const totalShifts = opt.dataset.shiftsCount || '0';
        el('personnel-name').textContent = name;
        el('personnel-badge').textContent = `Badge: #${badge}`;
        el('personnel-phone').textContent = phone;
        el('personnel-email').textContent = email;
        el('personnel-last-shift').textContent = lastShift !== 'No previous shifts'
            ? new Date(lastShift).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
            : 'No previous shifts';
        el('personnel-total-shifts').textContent = totalShifts;
        checkPersonnelAvailability();
    }

    async function checkPersonnelAvailability() {
        const userId = personnelSelect.value;
        const date = dateInput.value;
        const shiftId = shiftSelect.value;
        if (!userId || !date) return;
        try {
            const res = await fetch(`${ROUTES.checkAvailability}?user_id=${userId}&date=${date}`);
            const data = await res.json();
            const isCurrent = (parseInt(userId) === SCHEDULE.userId && date === SCHEDULE.date);
            const messages = [];
            let isAvailable = true;

            if (data.has_assignment && !isCurrent) {
                isAvailable = false;
                personnelAvailability.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--danger)';
                personnelAvailability.querySelector('i').style.color = 'var(--danger)';
                const s = personnelAvailability.querySelector('span');
                s.textContent = 'Unavailable';
                s.style.color = 'var(--danger)';
                el('availability-detail').textContent = 'Already assigned to another post on this date.';
                messages.push('Already assigned');
            } else {
                personnelAvailability.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--success)';
                personnelAvailability.querySelector('i').style.color = 'var(--success)';
                const s = personnelAvailability.querySelector('span');
                s.textContent = 'Available';
                s.style.color = 'var(--success)';
                el('availability-detail').textContent = 'No conflicting assignments found.';
            }

            if (shiftId && data.last_shift_date && !isCurrent) {
                const hours = (new Date(date + 'T12:00:00') - new Date(data.last_shift_date + 'T12:00:00')) / (1000 * 60 * 60);
                if (hours < 12) {
                    restPeriodWarning?.classList.remove('hidden');
                    el('rest-period-text').textContent = `Only ${Math.round(hours)} hours since last shift. Minimum rest period is 12 hours.`;
                    isAvailable = false;
                    messages.push('Insufficient rest period');
                } else {
                    restPeriodWarning?.classList.add('hidden');
                }
            } else {
                restPeriodWarning?.classList.add('hidden');
            }

            if (shiftId && data.weekly_hours && !isCurrent) {
                const shiftDuration = parseFloat(shiftSelect.options[shiftSelect.selectedIndex].dataset.duration || '0');
                const wk = data.weekly_hours || 0;
                if ((wk + shiftDuration) > 60) {
                    weeklyHoursWarning?.classList.remove('hidden');
                    el('weekly-hours-text').textContent = `Current week: ${wk}h, Adding: ${shiftDuration}h, Total: ${wk + shiftDuration}h (Max: 60h)`;
                    isAvailable = false;
                    messages.push('Weekly hour limit exceeded');
                } else {
                    weeklyHoursWarning?.classList.add('hidden');
                }
            } else {
                weeklyHoursWarning?.classList.add('hidden');
            }

            if (!isAvailable && messages.length) el('availability-detail').textContent = messages.join('. ') + '.';
        } catch (e) {
            console.error('check-availability:', e);
        }
    }

    // ===========================
    // STATUS WARNING
    // ===========================
    function updateStatusWarning() {
        const newStatus = statusSelect.value;
        if (newStatus === SCHEDULE.status) { statusWarning?.classList.add('hidden'); return; }
        let msg = '';
        switch (newStatus) {
            case 'completed': if (SCHEDULE.status !== 'active') msg = 'Marking as completed without check-in/out data. Consider "Active" first.'; break;
            case 'absent':    msg = 'Marking personnel as absent. This will affect attendance.'; break;
            case 'cancelled': msg = 'Cancelling this schedule. This can be reversed by reassigning.'; break;
            case 'active':    if (SCHEDULE.status === 'scheduled') msg = 'Manually activating shift. Ensure personnel checked in.'; break;
        }
        if (msg) { el('status-warning-text').textContent = msg; statusWarning?.classList.remove('hidden'); }
        else { statusWarning?.classList.add('hidden'); }
    }

    // ===========================
    // FORM VALIDATION
    // ===========================
    async function validateForm() {
        let valid = true;
        const warnings = [];

        if (!postSelect.value) valid = false, warnings.push('Security Post is required');
        if (!shiftSelect.value) valid = false, warnings.push('Security Shift is required');
        if (!personnelSelect.value) valid = false, warnings.push('Security Personnel is required');
        if (!dateInput.value) valid = false, warnings.push('Assignment Date is required');

        const hasChanges =
            postSelect.value !== original.postId ||
            shiftSelect.value !== original.shiftId ||
            personnelSelect.value !== original.userId ||
            dateInput.value !== original.date ||
            statusSelect.value !== original.status ||
            el('emergency_contact').value !== original.emergency ||
            el('special_instructions').value !== original.special ||
            el('notes').value !== original.notes;

        if (!hasChanges) {
            warnings.push('No changes detected');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            formStatus.innerHTML = `<div class="flex items-center" style="color: var(--warning);"><i class="fas fa-info-circle mr-2"></i><span>No changes to save</span></div>`;
        } else {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        if ((postSelect.value !== original.postId || dateInput.value !== original.date) && postSelect.value && dateInput.value) {
            const max = parseInt(el('max-personnel').textContent || '0');
            const available = parseInt(el('available-slots').textContent || '0');
            if (available <= 0) { valid = false; warnings.push('Selected post is at full capacity for this date'); }
        }

        if ((shiftSelect.value !== original.shiftId || dateInput.value !== original.date) && shiftSelect.value && dateInput.value) {
            const applicable = JSON.parse(shiftSelect.options[shiftSelect.selectedIndex].dataset.applicableDays || '[]');
            if (applicable.length > 0 && !applicable.includes(getDayOfWeekIso(dateInput.value))) {
                valid = false; warnings.push('Selected shift is not applicable on this day');
            }
        }

        if ((personnelSelect.value !== original.userId || dateInput.value !== original.date) && personnelSelect.value && dateInput.value) {
            if (restPeriodWarning && !restPeriodWarning.classList.contains('hidden')) { valid = false; warnings.push('Insufficient rest period for selected personnel'); }
            if (weeklyHoursWarning && !weeklyHoursWarning.classList.contains('hidden')) { valid = false; warnings.push('Weekly hour limit would be exceeded'); }
            const span = personnelAvailability?.querySelector('span');
            if (span && span.textContent === 'Unavailable') { valid = false; warnings.push('Selected personnel is already assigned on this date'); }
        }

        if (el('special_instructions').value.length > 1000) { valid = false; warnings.push('Special instructions exceed 1000 characters'); }
        if (el('notes').value.length > 500) { valid = false; warnings.push('Notes exceed 500 characters'); }

        if (warnings.length) {
            submitWarnings?.classList.remove('hidden');
            el('submit-warning-text').textContent = warnings.join('. ') + '.';
        } else {
            submitWarnings?.classList.add('hidden');
        }

        if (!valid) {
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            formStatus.innerHTML = `<div class="flex items-center" style="color: var(--danger);"><i class="fas fa-exclamation-circle mr-2"></i><span>Please fix validation errors</span></div>`;
        } else if (hasChanges) {
            formStatus.innerHTML = `<div class="flex items-center" style="color: var(--success);"><i class="fas fa-check-circle mr-2"></i><span>Ready to update</span></div>`;
        }

        isFormValid = valid;
        return valid;
    }

    // ===========================
    // EVENT LISTENERS
    // ===========================
    postSelect.addEventListener('change', () => { updatePostDetails(); updatePostCapacity(); updateHandoverInfo(); validateForm(); });
    dateInput.addEventListener('change', function () {
        el('day-of-week-display').textContent = `${getDayOfWeek(this.value)}, ${new Date(this.value).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        updatePostCapacity(); updateShiftDetails(); updateHandoverInfo(); checkPersonnelAvailability(); validateForm();
    });
    shiftSelect.addEventListener('change', () => { updateShiftDetails(); updateHandoverInfo(); checkPersonnelAvailability(); validateForm(); });
    personnelSelect.addEventListener('change', () => { updatePersonnelInfo(); checkPersonnelAvailability(); validateForm(); });
    statusSelect.addEventListener('change', () => { updateStatusWarning(); validateForm(); });
    el('emergency_contact').addEventListener('input', validateForm);
    el('special_instructions').addEventListener('input', () => { updateTextCounters(); validateForm(); });
    el('notes').addEventListener('input', () => { updateTextCounters(); validateForm(); });

    form.addEventListener('submit', async function (e) {
        if (!(await validateForm())) {
            e.preventDefault();
            const first = document.querySelector('.border-red-500');
            if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); }
            return;
        }
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    });

    // ===========================
    // INIT
    // ===========================
    updateTextCounters();
    updatePostCapacity();
    el('day-of-week-display').textContent = {{ Illuminate\Support\Js::from($schedule->assignment_date->format('l, F j, Y')) }};
    dateInput.min = new Date().toISOString().split('T')[0];
    setInterval(() => { if (postSelect.value && dateInput.value) updatePostCapacity(); }, 30000);
});

// ===========================
// QUICK ACTIONS
// ===========================
function sendAssignmentNotification(scheduleId) {
    document.getElementById('notification-personnel-name').textContent = SCHEDULE.userName;
    document.getElementById('notification-modal').classList.remove('hidden');
}
function closeNotificationModal() { document.getElementById('notification-modal').classList.add('hidden'); }

async function sendNotification(scheduleId) {
    const channel = document.querySelector('input[name="notification_channel"]:checked')?.value;
    const message = document.getElementById('notification_message')?.value;
    if (!channel) { alert('Please select a notification channel'); return; }
    const btn = document.querySelector('#notification-modal button[onclick="sendNotification(' + scheduleId + ')"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    try {
        const res = await fetch(ROUTES.sendNotification(scheduleId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ channel, message }),
        });
        const data = await res.json();
        if (data.success) { alert('Notification sent successfully!'); closeNotificationModal(); }
        else { alert('Failed: ' + (data.message || 'Unknown error')); }
    } catch (e) {
        alert('Failed to send notification. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Notification';
    }
}

function postQuickAction(scheduleId, action) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = ROUTES.status(scheduleId);
    const csrf = document.createElement('input'); csrf.name = '_token'; csrf.value = '{{ csrf_token() }}'; csrf.type = 'hidden'; form.appendChild(csrf);
    const method = document.createElement('input'); method.name = '_method'; method.value = 'PATCH'; method.type = 'hidden'; form.appendChild(method);
    const act = document.createElement('input'); act.name = 'action'; act.value = action; act.type = 'hidden'; form.appendChild(act);
    document.body.appendChild(form);
    form.submit();
}

function quickCompleteSchedule(id) {
    if (confirm('Mark this schedule as completed? This will set check-out time to now.')) postQuickAction(id, 'mark_complete');
}
function quickMarkAbsent(id) {
    if (confirm('Mark this personnel as absent? This will affect their attendance record.')) postQuickAction(id, 'mark_absent');
}
function confirmCancelSchedule(id) {
    if (confirm('Are you sure you want to cancel this schedule?')) {
        document.getElementById('status').value = 'cancelled';
        document.getElementById('security-schedule-form').submit();
    }
}
function confirmDelete(id) { document.getElementById('delete-modal').classList.remove('hidden'); }
function closeDeleteModal() { document.getElementById('delete-modal').classList.add('hidden'); }

window.addEventListener('click', function (e) {
    const d = document.getElementById('delete-modal');
    const n = document.getElementById('notification-modal');
    if (e.target === d) d.classList.add('hidden');
    if (e.target === n) n.classList.add('hidden');
});

if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
</script>

<style>
/* =========================================
   SECURITY SCHEDULE EDIT — cosmetic layer
   (all token-driven; identical to admin blade)
   ========================================= */
.card.status-scheduled { border-left: 4px solid var(--info); }
.card.status-active    { border-left: 4px solid var(--success); }
.card.status-completed { border-left: 4px solid var(--primary); }
.card.status-absent    { border-left: 4px solid var(--danger); }
.card.status-cancelled { border-left: 4px solid var(--secondary); }

.quick-info-item { transition: transform 0.2s ease; }
.quick-info-item:hover { transform: translateY(-2px); }

.personnel-avatar { transition: all 0.3s ease; }
.personnel-avatar:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2); }

@keyframes modalSlideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
#delete-modal .sm\:align-middle,
#notification-modal .sm\:align-middle { animation: modalSlideIn 0.3s ease-out; }

input, select, textarea { transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease; }
input:disabled, select:disabled, textarea:disabled { opacity: 0.7; cursor: not-allowed; }

#capacity-progress { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease; }
@keyframes softPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.8; } }
#status-warning:not(.hidden) { animation: softPulse 2s infinite; }

#special-instructions-counter, #notes-counter { transition: color 0.2s ease; }

@media print {
    .btn-primary, .btn-secondary, #delete-modal, #notification-modal, button[type="submit"] { display: none !important; }
    .card { break-inside: avoid; box-shadow: none; border: 1px solid #000; }
}

[data-theme="dark"] .card { background: linear-gradient(145deg, var(--card-bg), rgba(var(--primary-rgb), 0.02)); }
[data-theme="dark"] #post-capacity-info,
[data-theme="dark"] #shift-details,
[data-theme="dark"] #handover-preview-container { background-color: rgba(255, 255, 255, 0.02); }
[data-theme="dark"] select option { background-color: var(--bg-primary); color: var(--text-primary); }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.fa-spinner { animation: spin 1s linear infinite; }

@media (max-width: 1024px) {
    .lg\:col-span-2, .lg\:col-span-1 { grid-column: span 1 / span 1; }
    .grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .card .flex.justify-between { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    #shift-details .grid-cols-2.md\:grid-cols-4 { grid-template-columns: 1fr 1fr; }
}

:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

input[type="radio"] { appearance: none; width: 1.25rem; height: 1.25rem; border: 2px solid var(--border-color); border-radius: 50%; transition: all 0.2s ease; position: relative; cursor: pointer; }
input[type="radio"]:checked { border-color: var(--primary); background-color: var(--primary); box-shadow: inset 0 0 0 3px white; }
input[type="radio"]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }

@supports (backdrop-filter: blur(8px)) {
    #delete-modal, #notification-modal { background-color: rgba(0, 0, 0, 0.3); backdrop-filter: blur(8px); }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; scroll-behavior: auto !important; }
}
@media (forced-colors: active) {
    .card, button, input, select, textarea { border: 1px solid CanvasText; }
    .btn-primary, .btn-secondary { border: 1px solid CanvasText; }
}

/* fill missing classes used by the header status badge inline style */
.btn-primary { background-color: var(--primary, #3b82f6); color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; transition: all 0.2s ease; border: none; cursor: pointer; }
.btn-primary:hover { background-color: var(--primary-dark, #2563eb); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.btn-secondary { background-color: var(--bg-tertiary, #f3f4f6); color: var(--text-primary, #1f2937); padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; border: 1px solid var(--border-color, #d1d5db); transition: all 0.2s ease; }
.btn-secondary:hover { background-color: var(--bg-secondary, #e5e7eb); text-decoration: none; }
</style>
@endsection