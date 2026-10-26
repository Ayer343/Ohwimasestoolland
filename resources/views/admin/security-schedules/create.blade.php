@extends('layouts.app')

@section('title', 'Create Security Schedule')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    {{-- Header --}}
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center space-x-3">
                <i class="fas fa-shield-alt text-2xl" style="color: var(--primary);"></i>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create Security Schedule</h2>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2">
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-map-marker-alt mr-1"></i> Posts: {{ $securityPosts->count() }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-clock mr-1"></i> Shifts: {{ $securityShifts->count() }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-users mr-1"></i> Personnel: {{ $securityPersonnel->count() }}
                    </div>
                </div>
                <button type="button" onclick="showBulkAssignModal()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-users mr-2"></i> Bulk Assign
                </button>
                <a href="{{ route('admin.security-schedules.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                </a>
            </div>
        </div>
    </div>

    {{-- Main Form --}}
    <form action="{{ route('admin.security-schedules.store') }}" method="POST" id="security-schedule-form" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 gap-6">
            {{-- Validation Errors --}}
            @if($errors->any())
                <div class="card p-6">
                    <div class="alert alert-danger">
                        <h4 class="font-bold mb-2" style="color: var(--danger);">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Validation Errors:
                        </h4>
                        <ul class="mb-0 space-y-1">
                            @foreach($errors->all() as $error)
                                <li class="text-sm" style="color: var(--text-secondary);">• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- LEFT: Post & Assignment --}}
                <div class="space-y-6">
                    {{-- Post --}}
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
                                                data-current-assignments="0"
                                                data-post-location="{{ $post->location }}"
                                                data-post-code="{{ $post->code }}"
                                                data-post-equipment="{{ json_encode($post->equipment ?? []) }}"
                                                data-post-active="{{ $post->is_active ? 'true' : 'false' }}"
                                                data-post-requirements="{{ json_encode($post->requirements ?? []) }}"
                                                {{ old('security_post_id') == $post->id ? 'selected' : '' }}>
                                            {{ $post->name }}
                                            @if($post->code)({{ $post->code }})@endif
                                            @if(!$post->is_active) [Inactive] @endif
                                            - Max: {{ $post->max_personnel }} personnel
                                        </option>
                                    @endforeach
                                </select>

                                <div id="post-capacity-info" class="mt-3 p-3 rounded hidden"
                                     style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-users mr-2" style="color: var(--info);"></i>
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">Current Capacity:</span>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <span class="text-sm" id="current-assignments" style="color: var(--text-secondary);">0</span>
                                            <span class="text-sm" style="color: var(--text-secondary);">/</span>
                                            <span class="text-sm font-bold" id="max-personnel" style="color: var(--primary);">0</span>
                                            <span class="text-sm" style="color: var(--text-secondary);">assigned</span>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="w-full h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                            <div id="capacity-progress" class="h-2 rounded-full" style="width: 0%; background-color: var(--success); transition: width 0.3s ease;"></div>
                                        </div>
                                    </div>
                                    <div id="capacity-warning" class="mt-2 text-xs hidden" style="color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <span id="capacity-warning-text"></span>
                                    </div>
                                </div>

                                <div id="post-quick-stats" class="grid grid-cols-2 gap-3 mt-2 hidden">
                                    <div class="p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                        <div class="text-xs" style="color: var(--text-secondary);">Post Type</div>
                                        <div class="text-sm font-medium" id="post-type-display" style="color: var(--text-primary);">-</div>
                                    </div>
                                    <div class="p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                                        <div class="text-xs" style="color: var(--text-secondary);">Location</div>
                                        <div class="text-sm font-medium" id="post-location-display" style="color: var(--text-primary);">-</div>
                                    </div>
                                </div>

                                <div id="post-equipment" class="mt-2 hidden">
                                    <div class="text-xs font-medium mb-2" style="color: var(--text-secondary);">Equipment at this post:</div>
                                    <div class="flex flex-wrap gap-2" id="post-equipment-list"></div>
                                </div>

                                @error('security_post_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Assignment Date & Shift --}}
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-2" style="color: var(--success);"></i>
                                Assignment Schedule
                            </h3>
                            <span class="px-3 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                Required *
                            </span>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="assignment_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Assignment Date <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="date" class="w-full p-2 border rounded @error('assignment_date') border-red-500 @enderror"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           id="assignment_date" name="assignment_date"
                                           value="{{ old('assignment_date', date('Y-m-d')) }}" required
                                           min="{{ date('Y-m-d') }}">
                                    <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                        <i class="fas fa-calendar-day" style="color: var(--text-secondary);"></i>
                                    </div>
                                </div>
                                <div id="day-info" class="mt-2 flex items-center text-sm">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                    <span style="color: var(--text-secondary);" id="day-of-week-display"></span>
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
                                                data-handover-notes-required="{{ isset($shift->handover_config['handover_notes_required']) && $shift->handover_config['handover_notes_required'] ? 'true' : 'false' }}"
                                                data-handover-checklist="{{ json_encode($shift->handover_config['handover_checklist'] ?? []) }}"
                                                data-applicable-days="{{ json_encode($shift->applicable_days ?? []) }}"
                                                data-rotation-type="{{ $shift->rotation_type }}"
                                                data-rotation-config="{{ json_encode($shift->rotation_config ?? []) }}"
                                                data-break-schedule="{{ json_encode($shift->break_schedule ?? []) }}"
                                                data-has-breaks="{{ isset($shift->break_schedule['has_break']) && $shift->break_schedule['has_break'] ? 'true' : 'false' }}"
                                                data-break-count="{{ count($shift->break_schedule['breaks'] ?? []) }}"
                                                data-required-personnel="{{ $shift->required_personnel ?? 1 }}"
                                                {{ old('security_shift_id') == $shift->id ? 'selected' : '' }}>
                                            {{ $shift->name }}
                                            ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                            @if($shift->is_overnight) 🌙 @endif
                                            - {{ ucfirst($shift->category) }}
                                            @if($shift->rotation_type === 'rotating') 🔄 @endif
                                        </option>
                                    @endforeach
                                </select>

                                <div id="shift-details" class="mt-3 p-3 rounded hidden"
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                        <div class="flex items-center">
                                            <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <span class="text-xs" style="color: var(--text-secondary);">Time</span>
                                                <div class="font-medium" id="shift-time-display" style="color: var(--text-primary);">-</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-hourglass-half mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <span class="text-xs" style="color: var(--text-secondary);">Duration</span>
                                                <div class="font-medium" id="shift-duration-display" style="color: var(--text-primary);">-</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-tag mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <span class="text-xs" style="color: var(--text-secondary);">Category</span>
                                                <div class="font-medium" id="shift-category-display" style="color: var(--text-primary);">-</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-exchange-alt mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <span class="text-xs" style="color: var(--text-secondary);">Handover</span>
                                                <div class="font-medium" id="shift-handover-display" style="color: var(--text-primary);">-</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="overnight-warning" class="mt-2 p-2 rounded text-xs hidden"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-moon mr-1"></i>
                                        <span>This is an overnight shift ending the next day.</span>
                                    </div>

                                    <div id="applicability-warning" class="mt-2 p-2 rounded text-xs hidden"
                                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <span id="applicability-warning-text"></span>
                                    </div>

                                    <div id="break-info" class="mt-2 p-2 rounded text-xs hidden"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <div class="flex items-center mb-1">
                                            <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                                            <span class="font-medium" style="color: var(--text-primary);">Break Schedule:</span>
                                        </div>
                                        <div id="break-list" class="ml-6 space-y-1"></div>
                                    </div>
                                </div>

                                <div id="handover-preview-container" class="mt-3 p-3 rounded hidden"
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 4px solid var(--primary);">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-handshake mr-2" style="color: var(--primary);"></i>
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">Handover Schedule</span>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded"
                                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            Required
                                        </span>
                                    </div>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <span class="text-xs" style="color: var(--text-secondary);">Handover Start</span>
                                            <div class="font-medium" id="handover-start-display" style="color: var(--text-primary);">-</div>
                                        </div>
                                        <div>
                                            <span class="text-xs" style="color: var(--text-secondary);">Handover End</span>
                                            <div class="font-medium" id="handover-end-display" style="color: var(--text-primary);">-</div>
                                        </div>
                                        <div>
                                            <span class="text-xs" style="color: var(--text-secondary);">Duration</span>
                                            <div class="font-medium" id="handover-duration-display" style="color: var(--text-primary);">-</div>
                                        </div>
                                        <div>
                                            <span class="text-xs" style="color: var(--text-secondary);">Window</span>
                                            <div class="font-medium" id="handover-window-display" style="color: var(--text-primary);">-</div>
                                        </div>
                                    </div>
                                    <div id="handover-checklist-preview" class="mt-2 text-xs hidden">
                                        <div class="font-medium mb-1" style="color: var(--text-primary);">Checklist Items:</div>
                                        <div id="checklist-items" class="ml-4 space-y-1"></div>
                                    </div>
                                    <div id="handover-notes-required" class="mt-2 text-xs hidden" style="color: var(--warning);">
                                        <i class="fas fa-sticky-note mr-1"></i>
                                        <span>Handover notes are required</span>
                                    </div>
                                </div>

                                @error('security_shift_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center justify-between p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                                <div class="flex items-center">
                                    <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">Include Breaks</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="include_breaks" id="include_breaks" class="sr-only peer" value="1" {{ old('include_breaks') ? 'checked' : '' }}>
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-info after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Personnel & Details --}}
                <div class="space-y-6">
                    {{-- Personnel --}}
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
                                        @php
                                            $lastShift = $personnel->schedules()->latest('assignment_date')->first();
                                            $joinedDate = $personnel->created_at ? $personnel->created_at->format('Y-m-d') : '';
                                        @endphp
                                        <option value="{{ $personnel->id }}"
                                                data-phone="{{ $personnel->phone }}"
                                                data-email="{{ $personnel->email }}"
                                                data-status="{{ $personnel->status }}"
                                                data-badge="{{ $personnel->badge_number ?? '' }}"
                                                data-last-shift="{{ $lastShift?->assignment_date?->format('Y-m-d') ?? '' }}"
                                                data-shifts-count="{{ $personnel->schedules()->count() }}"
                                                data-weekly-hours="{{ $personnel->schedules()->whereBetween('assignment_date', [now()->startOfWeek(), now()->endOfWeek()])->with('shift')->get()->sum(fn($s) => $s->shift->duration_hours ?? 0) }}"
                                                data-preferred-shifts="{{ json_encode($personnel->preferences['preferred_shifts'] ?? []) }}"
                                                data-preferred-posts="{{ json_encode($personnel->preferences['preferred_posts'] ?? []) }}"
                                                data-willing-to-rotate="{{ isset($personnel->preferences['willing_to_rotate']) ? (is_array($personnel->preferences['willing_to_rotate']) ? 'true' : ($personnel->preferences['willing_to_rotate'] ? 'true' : 'false')) : 'false' }}"
                                                data-rotation-preference="{{ $personnel->preferences['rotation_preference'] ?? 'neutral' }}"
                                                data-prefers-night="{{ isset($personnel->preferences['prefers_night_shift']) && $personnel->preferences['prefers_night_shift'] ? 'true' : 'false' }}"
                                                data-prefers-day="{{ isset($personnel->preferences['prefers_day_shift']) && $personnel->preferences['prefers_day_shift'] ? 'true' : 'false' }}"
                                                data-willing-day-to-night="{{ isset($personnel->preferences['willing_to_rotate']['day_to_night']) && $personnel->preferences['willing_to_rotate']['day_to_night'] ? 'true' : 'false' }}"
                                                data-willing-night-to-day="{{ isset($personnel->preferences['willing_to_rotate']['night_to_day']) && $personnel->preferences['willing_to_rotate']['night_to_day'] ? 'true' : 'false' }}"
                                                data-performance-rating="{{ $personnel->preferences['performance_rating'] ?? 7 }}"
                                                data-joined-date="{{ $joinedDate }}"
                                                {{ old('security_user_id') == $personnel->id ? 'selected' : '' }}>
                                            {{ $personnel->name }}
                                            @if($personnel->badge_number)(#{{ $personnel->badge_number }})@endif
                                            - {{ $personnel->phone }}
                                        </option>
                                    @endforeach
                                </select>

                                <div id="personnel-info" class="mt-3 space-y-2 hidden">
                                    <div id="personnel-availability" class="p-3 rounded flex items-center"
                                         style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                                        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                        <div>
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">Available</span>
                                            <p class="text-xs" id="personnel-availability-detail" style="color: var(--text-secondary);"></p>
                                        </div>
                                    </div>

                                    <div id="personnel-details" class="grid grid-cols-2 gap-2 text-sm">
                                        <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                            <span class="text-xs" style="color: var(--text-secondary);">Phone</span>
                                            <div class="font-medium" id="personnel-phone" style="color: var(--text-primary);">-</div>
                                        </div>
                                        <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                            <span class="text-xs" style="color: var(--text-secondary);">Badge #</span>
                                            <div class="font-medium" id="personnel-badge" style="color: var(--text-primary);">-</div>
                                        </div>
                                        <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                            <span class="text-xs" style="color: var(--text-secondary);">Last Shift</span>
                                            <div class="font-medium" id="personnel-last-shift" style="color: var(--text-primary);">-</div>
                                        </div>
                                        <div class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                            <span class="text-xs" style="color: var(--text-secondary);">Total Shifts</span>
                                            <div class="font-medium" id="personnel-total-shifts" style="color: var(--text-primary);">-</div>
                                        </div>
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

                                <div id="rotation-preference" class="mt-3 p-3 rounded text-sm hidden"
                                     style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                                    <div class="flex items-start">
                                        <i class="fas fa-sync-alt mr-2 mt-0.5" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-primary);">Rotation Preference</span>
                                            <p class="text-xs mt-1" id="rotation-preference-text" style="color: var(--text-secondary);"></p>
                                        </div>
                                    </div>
                                </div>

                                <div id="availability-checker" class="mt-2 p-3 rounded hidden"
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px dashed rgba(var(--info-rgb), 0.3);">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            <i class="fas fa-search mr-1"></i> Availability Check
                                        </span>
                                        <span class="text-xs px-2 py-1 rounded" id="availability-badge"
                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            Checking...
                                        </span>
                                    </div>
                                    <div class="text-xs" id="availability-details" style="color: var(--text-secondary);"></div>
                                </div>

                                <div id="seniority-performance" class="mt-2 p-2 rounded hidden"
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px dashed rgba(var(--primary-rgb), 0.3);">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-medium" style="color: var(--text-primary);">Seniority:</span>
                                        <span class="text-xs" id="seniority-years" style="color: var(--text-secondary);">-</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-medium" style="color: var(--text-primary);">Performance:</span>
                                        <span class="text-xs" id="performance-rating" style="color: var(--text-secondary);">-</span>
                                    </div>
                                </div>

                                @error('security_user_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Assignment Details --}}
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
                                <input type="text" class="w-full p-2 border rounded @error('emergency_contact') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact') }}"
                                       placeholder="e.g., +233 XXX XXX XXX / Supervisor Name"
                                       maxlength="255">
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Contact number for emergencies during this shift
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
                                          id="special_instructions" name="special_instructions" rows="3"
                                          placeholder="Specific instructions for this assignment..."
                                          maxlength="1000">{{ old('special_instructions') }}</textarea>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Max 1000 characters. Include any site-specific requirements.
                                </p>
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
                                          id="notes" name="notes" rows="2"
                                          placeholder="Any additional notes about this assignment..."
                                          maxlength="500">{{ old('notes') }}</textarea>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Max 500 characters. Internal notes not visible to security personnel.
                                </p>
                                @error('notes')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Rotation Score --}}
                    <div id="rotation-score-container" class="card p-6 hidden">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-star mr-2" style="color: var(--warning);"></i>
                            <h4 class="font-medium" style="color: var(--text-primary);">Rotation Preference Score</h4>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <div id="preference-score-bar" class="h-2 rounded-full" style="width: 50%; background-color: var(--warning);"></div>
                            </div>
                            <span id="preference-score-value" class="text-sm font-medium" style="color: var(--text-primary);">5/10</span>
                        </div>
                        <p class="text-xs mt-2" id="preference-score-text" style="color: var(--text-secondary);">
                            Based on personnel preferences and shift compatibility
                        </p>

                        <div id="score-breakdown" class="mt-2 text-xs hidden" style="color: var(--text-secondary);">
                            <div class="flex justify-between"><span>Seniority:</span><span id="score-seniority">0</span></div>
                            <div class="flex justify-between"><span>Performance:</span><span id="score-performance">0</span></div>
                            <div class="flex justify-between"><span>Shift Preference:</span><span id="score-shift-preference">0</span></div>
                            <div class="flex justify-between"><span>Rotation Willingness:</span><span id="score-rotation">0</span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rotation Options --}}
            <div id="rotation-options" class="card p-6 hidden">
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
                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Rotation Sequence</label>
                        <div class="p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="flex items-center space-x-2 flex-wrap">
                                <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">Morning</span>
                                <i class="fas fa-arrow-right text-xs" style="color: var(--text-secondary);"></i>
                                <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">Evening</span>
                                <i class="fas fa-arrow-right text-xs" style="color: var(--text-secondary);"></i>
                                <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">Night</span>
                                <i class="fas fa-arrow-right text-xs" style="color: var(--text-secondary);"></i>
                                <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">Off</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Current Position</label>
                        <div class="p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <span class="text-sm" id="current-rotation-position" style="color: var(--text-primary);">-</span>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Next Rotation</label>
                        <div class="p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <span class="text-sm" id="next-rotation-date" style="color: var(--text-primary);">-</span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-right">
                    <button type="button" onclick="viewRotationHistoryForCurrent()" class="text-xs inline-flex items-center" style="color: var(--info);">
                        <i class="fas fa-history mr-1"></i> View Rotation History
                    </button>
                </div>

                {{-- Hidden rotation fields — initial values are valid integers --}}
                <input type="hidden" name="rotation_group_id" id="rotation_group_id" value="">
                <input type="hidden" name="rotation_group_type" id="rotation_group_type" value="">
                <input type="hidden" name="rotation_sequence_number" id="rotation_sequence_number" value="0">
                <input type="hidden" name="rotation_preference_score" id="rotation_preference_score" value="5">
                <input type="hidden" name="is_rotated" id="is_rotated" value="0">
            </div>

            {{-- Assignment Summary --}}
            <div id="assignment-summary" class="card p-6 hidden">
                <div class="flex items-center mb-4">
                    <i class="fas fa-clipboard-check text-2xl mr-3" style="color: var(--success);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Assignment Summary</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-xs" style="color: var(--text-secondary);">Security Post</div>
                        <div class="font-medium" id="summary-post" style="color: var(--text-primary);">-</div>
                    </div>
                    <div class="p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="text-xs" style="color: var(--text-secondary);">Date & Shift</div>
                        <div class="font-medium" id="summary-datetime" style="color: var(--text-primary);">-</div>
                    </div>
                    <div class="p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="text-xs" style="color: var(--text-secondary);">Personnel</div>
                        <div class="font-medium" id="summary-personnel" style="color: var(--text-primary);">-</div>
                    </div>
                    <div class="p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <div class="text-xs" style="color: var(--text-secondary);">Duration</div>
                        <div class="font-medium" id="summary-duration" style="color: var(--text-primary);">-</div>
                    </div>
                </div>

                <div id="rotation-summary" class="mt-3 p-3 rounded hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-sync-alt mr-2" style="color: var(--info);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Rotation Info:</span>
                        <span class="text-sm ml-2" id="rotation-summary-text" style="color: var(--text-secondary);">Fixed shift</span>
                    </div>
                </div>

                <div class="mt-4 p-4 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Shift Timeline</span>
                        <span class="text-xs" id="shift-timeline-status" style="color: var(--text-secondary);"></span>
                    </div>
                    <div class="relative pt-2">
                        <div class="flex items-center justify-between text-xs mb-2">
                            <span id="timeline-start" style="color: var(--text-secondary);">-</span>
                            <span id="timeline-end" style="color: var(--text-secondary);">-</span>
                        </div>
                        <div class="w-full h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                            <div id="timeline-progress" class="h-2 rounded-full" style="width: 0%; background-color: var(--success);"></div>
                        </div>
                    </div>
                </div>

                <div id="break-summary" class="mt-3 p-3 rounded hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Break Schedule:</span>
                        <span class="text-sm ml-2" id="break-summary-text" style="color: var(--text-secondary);">No breaks</span>
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div id="form-status" class="text-sm" style="color: var(--text-secondary);"></div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Create Schedule
                        </button>
                        <a href="{{ route('admin.security-schedules.index') }}" class="btn-secondary">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                </div>

                <div id="submit-warnings" class="mt-3 hidden">
                    <div class="flex items-center p-3 rounded text-sm" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        <span id="submit-warning-text" style="color: var(--text-primary);"></span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Bulk Assign Modal --}}
<div id="bulkAssignModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkAssignModal')"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">Bulk Assign Personnel</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkAssignModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="bulkAssignForm" method="POST" action="{{ route('admin.security-schedules.bulk-assign') }}">
            @csrf
            <div class="modal-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i> Security Post <span class="text-red-500">*</span>
                        </label>
                        <select name="security_post_id" id="bulk_post_id"
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" required>
                            <option value="">Select Post</option>
                            @foreach($securityPosts as $post)
                                <option value="{{ $post->id }}" data-max="{{ $post->max_personnel }}" data-type="{{ $post->type }}" data-location="{{ $post->location }}">
                                    {{ $post->name }} ({{ $post->code }}) - Max: {{ $post->max_personnel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Shift <span class="text-red-500">*</span>
                        </label>
                        <select name="security_shift_id" id="bulk_shift_id"
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" required>
                            <option value="">Select Shift</option>
                            @foreach($securityShifts as $shift)
                                <option value="{{ $shift->id }}" data-category="{{ $shift->category }}" data-rotation="{{ $shift->rotation_type }}" data-duration="{{ $shift->duration_hours }}" data-overnight="{{ $shift->is_overnight ? 'yes' : 'no' }}" data-applicable-days="{{ json_encode($shift->applicable_days ?? []) }}">
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                    @if($shift->category) - {{ ucfirst($shift->category) }} @endif
                                    @if($shift->rotation_type === 'rotating') 🔄 @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-1" style="color: var(--primary);"></i> Security Personnel <span class="text-red-500">*</span>
                    </label>
                    <select name="security_user_ids[]" id="bulk_user_ids"
                            class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                            multiple size="6" required>
                        @foreach($securityPersonnel as $person)
                            @php $joinedDate = $person->created_at ? $person->created_at->format('Y-m-d') : ''; @endphp
                            <option value="{{ $person->id }}" data-preferences="{{ json_encode($person->preferences ?? []) }}" data-badge="{{ $person->badge_number ?? '' }}" data-joined="{{ $joinedDate }}" data-performance="{{ $person->preferences['performance_rating'] ?? 7 }}">
                                {{ $person->name }} - {{ $person->phone }}
                                @if($person->badge_number) (Badge: {{ $person->badge_number }}) @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Hold Ctrl/Cmd to select multiple personnel. Selected: <span id="selectedPersonnelCount">0</span>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-start mr-1" style="color: var(--primary);"></i> Start Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="start_date" id="startDate"
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ now()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-end mr-1" style="color: var(--primary);"></i> End Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="end_date" id="endDate"
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ now()->addWeek()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-repeat mr-1" style="color: var(--primary);"></i> Recurrence Type <span class="text-red-500">*</span>
                    </label>
                    <select name="recurrence_type" id="recurrenceType"
                            class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" required>
                        <option value="daily">Daily (Every day)</option>
                        <option value="weekly" selected>Weekly (Selected days)</option>
                        <option value="monthly">Monthly (Selected dates)</option>
                        <option value="custom">Custom (Manual selection)</option>
                        <option value="rotating">Rotating Pattern (Advanced)</option>
                    </select>
                </div>

                <div id="weeklyDaysContainer" class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-week mr-1" style="color: var(--primary);"></i> Select Days of Week
                    </label>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="1" class="mr-2" checked> Monday</label>
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="2" class="mr-2" checked> Tuesday</label>
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="3" class="mr-2" checked> Wednesday</label>
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="4" class="mr-2" checked> Thursday</label>
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="5" class="mr-2" checked> Friday</label>
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="6" class="mr-2" checked> Saturday</label>
                        <label class="inline-flex items-center"><input type="checkbox" name="recurrence_days[]" value="7" class="mr-2" checked> Sunday</label>
                    </div>
                </div>

                <div id="monthlyDatesContainer" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Select Dates of Month
                    </label>
                    <select name="recurrence_days[]" class="form-input w-full p-3 rounded-lg border" multiple size="5">
                        @for($i = 1; $i <= 31; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                    </select>
                </div>

                <div id="rotationPatternContainer" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--warning-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Pattern
                    </label>
                    <div class="space-y-3">
                        @foreach([
                            'full_swap' => ['Full Swap', 'Complete rotation: All day personnel swap with night personnel (Weekly rotation)'],
                            'staggered' => ['Staggered', '50% rotation: Rotate half of the personnel each cycle (3-day rotation)'],
                            'partial' => ['Partial', 'Preference-based: Only rotate personnel who have opted in (Weekly rotation)'],
                            'sequential' => ['Sequential', 'Simple sequential rotation through all personnel (Daily rotation)'],
                            'alternating' => ['Alternating', 'Alternate between top and bottom performers (Weekly rotation)'],
                            'preference_based' => ['Preference-Based', 'Always assign highest preference scores first (Weekly rotation)'],
                        ] as $value => $meta)
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="{{ $value }}" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">{{ $meta[0] }}</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $meta[1] }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <label class="flex items-center">
                            <input type="checkbox" name="create_rotation_group" id="create_rotation_group" value="1" class="mr-2">
                            <span style="color: var(--text-primary);">Create new rotation group</span>
                        </label>
                        <div id="rotationGroupNameContainer" class="mt-2 hidden">
                            <input type="text" name="rotation_group_name" id="rotation_group_name"
                                   class="form-input w-full p-2 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Enter rotation group name">
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);"><i class="fas fa-cog mr-2"></i> Advanced Options</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <label class="flex items-center"><input type="checkbox" name="maintain_coverage" value="1" class="mr-2" checked><span style="color: var(--text-primary);">Maintain Coverage</span></label>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <label class="flex items-center"><input type="checkbox" name="respect_preferences" value="1" class="mr-2" checked><span style="color: var(--text-primary);">Respect Preferences</span></label>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <label class="flex items-center"><input type="checkbox" name="notify_personnel" value="1" class="mr-2" checked><span style="color: var(--text-primary);">Notify Personnel</span></label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <label class="flex items-center"><input type="checkbox" name="include_breaks" value="1" class="mr-2" checked><span style="color: var(--text-primary);">Include Break Schedules</span></label>
                    </div>
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <label class="flex items-center"><input type="checkbox" name="include_handover" value="1" class="mr-2"><span style="color: var(--text-primary);">Include Handover Procedures</span></label>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-1" style="color: var(--primary);"></i> Notes
                    </label>
                    <textarea name="notes" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" rows="2" maxlength="500" placeholder="General notes"></textarea>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-phone-alt mr-1" style="color: var(--primary);"></i> Emergency Contact
                    </label>
                    <input type="text" name="emergency_contact" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" maxlength="255">
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-1" style="color: var(--primary);"></i> Special Instructions
                    </label>
                    <textarea name="special_instructions" class="form-input w-full p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" rows="2" maxlength="1000"></textarea>
                </div>

                <div id="bulkAssignSummary" class="mt-6 p-4 rounded-lg hidden" style="background-color: rgba(var(--success-rgb), 0.05);">
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-calculator mr-2" style="color: var(--success);"></i> Assignment Summary
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <span style="color: var(--text-secondary);">Personnel Selected:</span><span style="color: var(--text-primary);" id="summaryPersonnelCount">0</span>
                        <span style="color: var(--text-secondary);">Date Range:</span><span style="color: var(--text-primary);" id="summaryDateRange">-</span>
                        <span style="color: var(--text-secondary);">Recurrence:</span><span style="color: var(--text-primary);" id="summaryRecurrence">-</span>
                        <span style="color: var(--text-secondary);">Rotation Pattern:</span><span style="color: var(--text-primary);" id="summaryRotationPattern">None</span>
                        <span style="color: var(--text-secondary);">Total Assignments:</span><span style="color: var(--text-primary); font-weight: bold;" id="summaryTotalAssignments">0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);" onclick="closeModal('bulkAssignModal')">Cancel</button>
                <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);" onclick="previewBulkAssign()"><i class="fas fa-eye mr-2"></i> Preview</button>
                <button type="submit" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary" id="bulkAssignSubmitBtn"><i class="fas fa-users mr-2"></i> Assign Personnel</button>
            </div>
        </form>
    </div>
</div>

{{-- Bulk Assign Preview Modal --}}
<div id="bulkAssignPreviewModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkAssignPreviewModal')"></div>
    <div class="modal-container" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Preview Bulk Assignment</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkAssignPreviewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="previewContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Calculating assignments...</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);" onclick="closeModal('bulkAssignPreviewModal')">Cancel</button>
            <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary" onclick="submitBulkAssignForm()"><i class="fas fa-check mr-2"></i> Confirm & Submit</button>
        </div>
    </div>
</div>

{{-- Rotation History Modal --}}
<div id="rotation-history-modal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rotation-history-modal')"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Rotation History</h3>
            <button type="button" class="modal-close" onclick="closeModal('rotation-history-modal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="rotationHistoryContent" class="space-y-3"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);" onclick="closeModal('rotation-history-modal')">Close</button>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// =============================================
// GLOBAL STATE
// =============================================
let currentCapacity = 0;
let selectedDate = document.getElementById('assignment_date')?.value || new Date().toISOString().split('T')[0];
let isFormValid = true;
let originalShiftConfig = { hasBreaks: false, hasHandover: false, handoverDuration: 30, handoverNotesRequired: false, breakSchedule: {} };
let isBreakOverridden = false;
let isHandoverOverridden = false;
let overrideAuditLog = [];

// =============================================
// LOADING
// =============================================
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overlay.innerHTML = `<div class="text-center"><div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div><div class="text-white font-medium" id="loadingMessage">${message}</div></div>`;
        document.body.appendChild(overlay);
    }
    const messageEl = document.getElementById('loadingMessage');
    if (messageEl) messageEl.textContent = message;
    overlay.classList.remove('hidden');
}
function hideLoading() {
    document.getElementById('loadingOverlay')?.classList.add('hidden');
}

// =============================================
// DAY OF WEEK
// =============================================
const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
function getDayOfWeek(dateString) { return daysOfWeek[new Date(dateString + 'T12:00:00').getDay()]; }
function getDayOfWeekIso(dateString) { const d = new Date(dateString + 'T12:00:00').getDay(); return d === 0 ? 7 : d; }

// =============================================
// SHIFT DEFAULTS
// =============================================
function updateShiftDefaultIndicators() {
    const shiftSelect = document.getElementById('security_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    if (!selectedOption.value) return;

    const shiftHasBreaks = selectedOption.dataset.hasBreaks === 'true';
    const shiftHasHandover = selectedOption.dataset.hasHandover === 'true';
    const shiftBreakCount = selectedOption.dataset.breakCount || '0';
    const handoverDuration = selectedOption.dataset.handoverDuration || '30';

    originalShiftConfig = {
        hasBreaks: shiftHasBreaks,
        hasHandover: shiftHasHandover,
        handoverDuration: handoverDuration,
        handoverNotesRequired: selectedOption.dataset.handoverNotesRequired === 'true',
        breakSchedule: JSON.parse(selectedOption.dataset.breakSchedule || '{}'),
        breakCount: shiftBreakCount,
    };

    const breakIndicator = document.getElementById('shift-break-indicator');
    if (breakIndicator) {
        breakIndicator.textContent = shiftHasBreaks ? `Default: Yes (${shiftBreakCount} breaks)` : 'Default: No breaks';
        breakIndicator.style.backgroundColor = shiftHasBreaks ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)';
        breakIndicator.style.color = shiftHasBreaks ? 'var(--success)' : 'var(--text-secondary)';
    }

    const includeBreaksCheckbox = document.getElementById('include_breaks');
    if (includeBreaksCheckbox && !includeBreaksCheckbox.dataset.userModified) {
        includeBreaksCheckbox.checked = shiftHasBreaks;
    }

    resetOverrideState();
    checkForOverrides();
}

function resetOverrideState() {
    isBreakOverridden = false;
    isHandoverOverridden = false;
    document.getElementById('break-override-warning')?.classList.add('hidden');
    document.getElementById('break-override-reason-container')?.classList.add('hidden');
    document.getElementById('override-summary')?.classList.add('hidden');
    const r = document.getElementById('break_override_reason');
    if (r) r.value = '';
}

function checkForOverrides() {
    const cb = document.getElementById('include_breaks');
    if (!cb || !originalShiftConfig) return;
    if (cb.checked !== originalShiftConfig.hasBreaks) {
        isBreakOverridden = true;
        showBreakOverrideWarning(cb.checked);
    } else {
        isBreakOverridden = false;
        hideBreakOverrideWarning();
    }
    updateOverrideSummary();
}

function showBreakOverrideWarning(currentValue) {
    const warningEl = document.getElementById('break-override-warning');
    const warningText = document.getElementById('break-override-text');
    const reasonContainer = document.getElementById('break-override-reason-container');
    if (warningEl && warningText) {
        const action = currentValue ? 'enabling' : 'disabling';
        const defaultState = originalShiftConfig.hasBreaks ? 'has' : 'does not have';
        warningText.innerHTML = `You are ${action} breaks.<br><strong>Shift default:</strong> ${defaultState} breaks.<br><strong>Reason required.</strong>`;
        warningEl.classList.remove('hidden');
        if (reasonContainer) {
            reasonContainer.classList.remove('hidden');
            document.getElementById('break_override_reason')?.setAttribute('required', 'required');
        }
    }
}
function hideBreakOverrideWarning() {
    document.getElementById('break-override-warning')?.classList.add('hidden');
    const c = document.getElementById('break-override-reason-container');
    if (c) {
        c.classList.add('hidden');
        const r = document.getElementById('break_override_reason');
        if (r) { r.removeAttribute('required'); r.value = ''; }
    }
}
function updateOverrideSummary() {
    const s = document.getElementById('override-summary');
    const t = document.getElementById('override-summary-text');
    if (!s || !t) return;
    const overrides = [];
    if (isBreakOverridden) {
        overrides.push(`Break schedule: ${document.getElementById('include_breaks').checked ? 'Enabled' : 'Disabled'} (default: ${originalShiftConfig.hasBreaks ? 'Enabled' : 'Disabled'})`);
    }
    if (overrides.length > 0) {
        t.innerHTML = overrides.map(o => `<div>• ${o}</div>`).join('');
        s.classList.remove('hidden');
    } else {
        s.classList.add('hidden');
    }
}

// =============================================
// POST
// =============================================
async function updatePostCapacity() {
    const postId = document.getElementById('security_post_id')?.value;
    const date = document.getElementById('assignment_date')?.value;
    if (!postId || !date) {
        document.getElementById('post-capacity-info')?.classList.add('hidden');
        return;
    }
    try {
        const res = await fetch(`/admin/security-schedules/post-capacity?post_id=${postId}&date=${date}`);
        const data = await res.json();
        if (data.success) {
            currentCapacity = data.currently_assigned || 0;
            const max = data.max_personnel || 0;
            const available = data.available_slots || 0;
            document.getElementById('current-assignments').textContent = currentCapacity;
            document.getElementById('max-personnel').textContent = max;
            const bar = document.getElementById('capacity-progress');
            const pct = max > 0 ? (currentCapacity / max) * 100 : 0;
            bar.style.width = `${Math.min(pct, 100)}%`;
            bar.style.backgroundColor = pct >= 90 ? 'var(--danger)' : pct >= 75 ? 'var(--warning)' : 'var(--success)';
            const w = document.getElementById('capacity-warning');
            const wt = document.getElementById('capacity-warning-text');
            if (available <= 0) { w.classList.remove('hidden'); wt.textContent = 'This post is at full capacity.'; }
            else if (available < 3) { w.classList.remove('hidden'); wt.textContent = `Only ${available} slot(s) available.`; }
            else { w.classList.add('hidden'); }
            document.getElementById('post-capacity-info').classList.remove('hidden');
        }
    } catch (e) { console.error(e); }
}

function updatePostDetails() {
    const sel = document.getElementById('security_post_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) {
        document.getElementById('post-quick-stats')?.classList.add('hidden');
        document.getElementById('post-equipment')?.classList.add('hidden');
        return;
    }
    const type = opt.dataset.postType || 'Standard';
    const loc = opt.dataset.postLocation || 'N/A';
    const equipment = JSON.parse(opt.dataset.postEquipment || '[]');
    document.getElementById('post-type-display').textContent = type.replace(/_/g, ' ').toUpperCase();
    document.getElementById('post-location-display').textContent = loc;
    const eqList = document.getElementById('post-equipment-list');
    if (equipment.length > 0) {
        eqList.innerHTML = equipment.map(i => `<span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${i}</span>`).join('');
        document.getElementById('post-equipment').classList.remove('hidden');
    } else {
        document.getElementById('post-equipment').classList.add('hidden');
    }
    document.getElementById('post-quick-stats').classList.remove('hidden');
}

// =============================================
// SHIFT
// =============================================
function updateShiftDetails() {
    const sel = document.getElementById('security_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) {
        document.getElementById('shift-details')?.classList.add('hidden');
        document.getElementById('rotation-options')?.classList.add('hidden');
        return;
    }
    const start = opt.dataset.startTime?.substring(0, 5) || '--:--';
    const end = opt.dataset.endTime?.substring(0, 5) || '--:--';
    const dur = opt.dataset.duration || '0';
    const cat = opt.dataset.category || 'standard';
    const isOvernight = opt.dataset.isOvernight === 'true';
    const hasHandover = opt.dataset.hasHandover === 'true';
    const handoverDuration = opt.dataset.handoverDuration || '30';
    const rotationType = opt.dataset.rotationType || 'fixed';
    const applicableDays = JSON.parse(opt.dataset.applicableDays || '[]');
    const rotationConfig = JSON.parse(opt.dataset.rotationConfig || '{}');
    const breakSchedule = JSON.parse(opt.dataset.breakSchedule || '{}');
    const hasBreaks = opt.dataset.hasBreaks === 'true';

    document.getElementById('shift-time-display').textContent = `${start} - ${end}`;
    document.getElementById('shift-duration-display').textContent = `${dur} hours`;
    document.getElementById('shift-category-display').textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
    document.getElementById('shift-handover-display').textContent = hasHandover ? `Yes (${handoverDuration}min)` : 'No';
    document.getElementById('overnight-warning')?.classList.toggle('hidden', !isOvernight);

    updateBreakInfo(hasBreaks, breakSchedule);

    const dateInput = document.getElementById('assignment_date');
    const appWarn = document.getElementById('applicability-warning');
    const appText = document.getElementById('applicability-warning-text');
    if (dateInput?.value && appWarn && appText) {
        if (Array.isArray(applicableDays) && applicableDays.length > 0) {
            const iso = getDayOfWeekIso(dateInput.value);
            if (!applicableDays.includes(iso)) {
                appWarn.classList.remove('hidden');
                appText.textContent = `This shift is not applicable on ${getDayOfWeek(dateInput.value)}s.`;
            } else {
                appWarn.classList.add('hidden');
            }
        } else {
            appWarn.classList.add('hidden');
        }
    }

    const rotOpts = document.getElementById('rotation-options');
    const rotNum = document.getElementById('rotation_sequence_number');
    const rotType = document.getElementById('rotation_group_type');
    const isRot = document.getElementById('is_rotated');

    if (rotationType === 'rotating') {
        rotOpts?.classList.remove('hidden');
        updateRotationInfo(rotationConfig);
        if (rotType) rotType.value = cat;
        if (rotNum) rotNum.value = String(parseInt(rotationConfig.current_sequence_index || 0, 10));
    } else {
        rotOpts?.classList.add('hidden');
        if (rotType) rotType.value = '';
        if (rotNum) rotNum.value = '0';
        if (isRot) isRot.value = '0';
    }

    document.getElementById('shift-details')?.classList.remove('hidden');
    updateShiftDefaultIndicators();
    updateHandoverInfo();
}

function updateBreakInfo(hasBreaks, breakSchedule) {
    const info = document.getElementById('break-info');
    const list = document.getElementById('break-list');
    if (hasBreaks && breakSchedule.breaks?.length > 0) {
        list.innerHTML = breakSchedule.breaks.map(b => `<div class="text-xs">${b.name}: ${b.start_time} - ${b.end_time} (${b.duration_minutes} min)${b.is_paid ? ' 💰' : ''}</div>`).join('');
        info.classList.remove('hidden');
        const total = breakSchedule.breaks.reduce((s, b) => s + (b.duration_minutes || 0), 0);
        document.getElementById('break-summary-text').textContent = `${breakSchedule.breaks.length} breaks, total ${Math.floor(total / 60)}h ${total % 60}m`;
        document.getElementById('break-summary')?.classList.remove('hidden');
    } else {
        info?.classList.add('hidden');
        document.getElementById('break-summary')?.classList.add('hidden');
    }
}

function updateRotationInfo(cfg) {
    const seq = cfg.rotation_sequence || 'morning_evening';
    const map = {
        morning_evening: ['Morning', 'Evening', 'Night', 'Off'],
        evening_morning: ['Evening', 'Morning', 'Night', 'Off'],
        night_morning:   ['Night', 'Morning', 'Evening', 'Off'],
    };
    const display = map[seq] || map.morning_evening;
    document.getElementById('current-rotation-position').textContent = display[cfg.current_sequence_index || 0];
    const days = cfg.rotation_days || 7;
    const next = new Date(); next.setDate(next.getDate() + days);
    document.getElementById('next-rotation-date').textContent = next.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

async function updateHandoverInfo() {
    const shiftId = document.getElementById('security_shift_id')?.value;
    const date = document.getElementById('assignment_date')?.value;
    const postId = document.getElementById('security_post_id')?.value;
    const preview = document.getElementById('handover-preview-container');
    if (!shiftId || !date || !postId) {
        preview?.classList.add('hidden');
        return;
    }
    try {
        const res = await fetch(`/admin/security-schedules/handover-info?shift_id=${shiftId}&date=${date}&post_id=${postId}`);
        const data = await res.json();
        if (data.success && data.handover_info) {
            const h = data.handover_info;
            document.getElementById('handover-start-display').textContent = h.handover_start || '--:--';
            document.getElementById('handover-end-display').textContent = h.handover_end || '--:--';
            document.getElementById('handover-duration-display').textContent = `${h.handover_duration || 30} minutes`;
            document.getElementById('handover-window-display').textContent = h.handover_window ? `${h.handover_window} minutes` : '--';
            document.getElementById('handover-notes-required')?.classList.toggle('hidden', !h.notes_required);
            if (h.checklist?.length > 0) {
                document.getElementById('checklist-items').innerHTML = h.checklist.map(i => `<div class="flex items-center"><i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> ${i}</div>`).join('');
                document.getElementById('handover-checklist-preview')?.classList.remove('hidden');
            } else {
                document.getElementById('handover-checklist-preview')?.classList.add('hidden');
            }
            preview?.classList.remove('hidden');
        } else {
            preview?.classList.add('hidden');
        }
    } catch (e) {
        console.error(e);
        preview?.classList.add('hidden');
    }
}

// =============================================
// PERSONNEL
// =============================================
function updatePersonnelInfo() {
    const sel = document.getElementById('security_user_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) {
        document.getElementById('personnel-info')?.classList.add('hidden');
        document.getElementById('rotation-preference')?.classList.add('hidden');
        document.getElementById('availability-checker')?.classList.add('hidden');
        document.getElementById('rest-period-warning')?.classList.add('hidden');
        document.getElementById('weekly-hours-warning')?.classList.add('hidden');
        document.getElementById('rotation-score-container')?.classList.add('hidden');
        document.getElementById('seniority-performance')?.classList.add('hidden');
        return;
    }
    document.getElementById('personnel-phone').textContent = opt.dataset.phone || 'N/A';
    document.getElementById('personnel-badge').textContent = opt.dataset.badge || 'N/A';
    document.getElementById('personnel-last-shift').textContent = opt.dataset.lastShift || 'No previous shifts';
    document.getElementById('personnel-total-shifts').textContent = opt.dataset.shiftsCount || '0';

    const joined = opt.dataset.joinedDate;
    if (joined) {
        const years = (Date.now() - new Date(joined).getTime()) / (1000 * 60 * 60 * 24 * 365);
        document.getElementById('seniority-years').textContent = years.toFixed(1) + ' years';
    }
    document.getElementById('performance-rating').textContent = (opt.dataset.performanceRating || '7') + '/10';
    document.getElementById('seniority-performance')?.classList.remove('hidden');

    const willing = opt.dataset.willingToRotate === 'true';
    if (willing) {
        document.getElementById('rotation-preference')?.classList.remove('hidden');
        let t = 'Willing to rotate';
        if (opt.dataset.willingDayToNight === 'true') t = 'Willing to rotate: Day → Night';
        else if (opt.dataset.willingNightToDay === 'true') t = 'Willing to rotate: Night → Day';
        else if (opt.dataset.prefersNight === 'true') t = 'Prefers night shifts';
        else if (opt.dataset.prefersDay === 'true') t = 'Prefers day shifts';
        else if (opt.dataset.rotationPreference === 'any') t = 'Open to any rotation';
        document.getElementById('rotation-preference-text').textContent = t;
    } else {
        document.getElementById('rotation-preference')?.classList.add('hidden');
    }

    document.getElementById('personnel-info')?.classList.remove('hidden');
    calculatePreferenceScore(opt);
    checkPersonnelAvailability();
}

/**
 * FIXED: writes an INTEGER string (e.g. "7") instead of a float
 * ("7.0"). The controller validates rotation_preference_score as
 * `integer|min:1|max:10` — a value like "7.0" fails the integer rule.
 */
function calculatePreferenceScore(opt) {
    const shiftSel = document.getElementById('security_shift_id');
    const postSel = document.getElementById('security_post_id');
    if (!shiftSel || !shiftSel.options[shiftSel.selectedIndex]) return;

    const shiftCategory = shiftSel.options[shiftSel.selectedIndex]?.dataset.category || '';
    const preferredShifts = JSON.parse(opt.dataset.preferredShifts || '[]');
    const preferredPosts = JSON.parse(opt.dataset.preferredPosts || '[]');
    const willing = opt.dataset.willingToRotate === 'true';
    const willingDN = opt.dataset.willingDayToNight === 'true';
    const willingND = opt.dataset.willingNightToDay === 'true';
    const prefersNight = opt.dataset.prefersNight === 'true';
    const prefersDay = opt.dataset.prefersDay === 'true';
    const joined = opt.dataset.joinedDate;
    const performance = parseFloat(opt.dataset.performanceRating || '7');
    const postId = postSel?.value;

    let seniorityScore = 0;
    if (joined) {
        const years = (Date.now() - new Date(joined).getTime()) / (1000 * 60 * 60 * 24 * 365);
        seniorityScore = Math.min(2, years);
    }

    const performanceScore = (performance - 5) / 2.5;

    let shiftPrefScore = 0;
    if (preferredShifts.includes(shiftCategory)) shiftPrefScore += 2;
    if (preferredPosts.includes(parseInt(postId))) shiftPrefScore += 1;

    let rotationScore = 0;
    if (willing) {
        rotationScore += 1;
        if (shiftCategory === 'night' && willingDN) rotationScore += 2;
        else if (shiftCategory === 'day' && willingND) rotationScore += 2;
        else if (shiftCategory === 'night' && prefersNight) rotationScore += 1;
        else if (shiftCategory === 'day' && prefersDay) rotationScore += 1;
    }

    document.getElementById('score-seniority').textContent = '+' + seniorityScore.toFixed(1);
    document.getElementById('score-performance').textContent = '+' + performanceScore.toFixed(1);
    document.getElementById('score-shift-preference').textContent = '+' + shiftPrefScore.toFixed(1);
    document.getElementById('score-rotation').textContent = '+' + rotationScore.toFixed(1);
    document.getElementById('score-breakdown')?.classList.remove('hidden');

    let score = 5 + seniorityScore + performanceScore + shiftPrefScore + rotationScore;
    score = Math.max(1, Math.min(10, score));

    const bar = document.getElementById('preference-score-bar');
    const val = document.getElementById('preference-score-value');
    if (bar && val) {
        bar.style.width = `${(score / 10) * 100}%`;
        val.textContent = `${score.toFixed(1)}/10`;
        bar.style.backgroundColor = score >= 8 ? 'var(--success)' : score >= 5 ? 'var(--warning)' : 'var(--danger)';
    }

    const text = document.getElementById('preference-score-text');
    if (text) {
        text.textContent = score >= 8 ? 'High match' : score >= 5 ? 'Medium match' : 'Low match';
    }

    // ⬇⬇ FIX: send an integer (1–10), never a decimal string
    const rounded = Math.max(1, Math.min(10, Math.round(score)));
    const hidden = document.getElementById('rotation_preference_score');
    if (hidden) hidden.value = String(rounded);

    document.getElementById('rotation-score-container')?.classList.remove('hidden');
}

async function checkPersonnelAvailability() {
    const userId = document.getElementById('security_user_id')?.value;
    const date = document.getElementById('assignment_date')?.value;
    const shiftId = document.getElementById('security_shift_id')?.value;
    const checker = document.getElementById('availability-checker');
    if (!userId || !date) {
        checker?.classList.add('hidden');
        return;
    }
    try {
        checker?.classList.remove('hidden');
        document.getElementById('availability-badge').textContent = 'Checking...';
        document.getElementById('availability-details').textContent = 'Verifying availability...';

        const res = await fetch(`/admin/security-schedules/check-availability?user_id=${userId}&date=${date}`);
        const data = await res.json();
        let available = true;

        if (data.has_assignment) {
            available = false;
            document.getElementById('personnel-availability').style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            document.getElementById('personnel-availability').style.borderLeftColor = 'var(--danger)';
            document.querySelector('#personnel-availability i').style.color = 'var(--danger)';
            const span = document.querySelector('#personnel-availability span');
            span.textContent = 'Unavailable'; span.style.color = 'var(--danger)';
            document.getElementById('personnel-availability-detail').textContent = 'Already assigned on this date.';
            document.getElementById('availability-badge').textContent = 'Unavailable';
            document.getElementById('availability-badge').style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            document.getElementById('availability-badge').style.color = 'var(--danger)';
            document.getElementById('availability-details').textContent = 'Personnel already assigned on this date.';
        } else {
            document.getElementById('personnel-availability').style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            document.getElementById('personnel-availability').style.borderLeftColor = 'var(--success)';
            document.querySelector('#personnel-availability i').style.color = 'var(--success)';
            const span = document.querySelector('#personnel-availability span');
            span.textContent = 'Available'; span.style.color = 'var(--success)';
            document.getElementById('personnel-availability-detail').textContent = 'No conflicts.';
            document.getElementById('availability-badge').textContent = 'Available';
            document.getElementById('availability-badge').style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            document.getElementById('availability-badge').style.color = 'var(--success)';
            document.getElementById('availability-details').textContent = 'Personnel is available.';
        }

        if (shiftId) {
            const shiftOpt = document.getElementById('security_shift_id').options[document.getElementById('security_shift_id').selectedIndex];
            const shiftDur = parseFloat(shiftOpt.dataset.duration || '0');
            if (data.last_shift_date) {
                const h = (new Date(date + 'T12:00:00') - new Date(data.last_shift_date + 'T12:00:00')) / (1000 * 60 * 60);
                if (h < 12) {
                    document.getElementById('rest-period-warning')?.classList.remove('hidden');
                    document.getElementById('rest-period-text').textContent = `Only ${Math.round(h)} hours since last shift (min 12h).`;
                    available = false;
                } else {
                    document.getElementById('rest-period-warning')?.classList.add('hidden');
                }
            }
            if (data.weekly_hours !== undefined && (data.weekly_hours + shiftDur) > 60) {
                document.getElementById('weekly-hours-warning')?.classList.remove('hidden');
                document.getElementById('weekly-hours-text').textContent = `Current: ${data.weekly_hours}h, Adding: ${shiftDur}h, Max: 60h.`;
                available = false;
            } else {
                document.getElementById('weekly-hours-warning')?.classList.add('hidden');
            }
        }

        if (!available) {
            document.getElementById('availability-badge').textContent = 'Constraints Detected';
            document.getElementById('availability-badge').style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
            document.getElementById('availability-badge').style.color = 'var(--warning)';
        }
    } catch (e) {
        console.error(e);
        document.getElementById('availability-details').textContent = 'Could not verify. Proceed with caution.';
    }
}

// =============================================
// SUMMARY
// =============================================
function updateAssignmentSummary() {
    const p = document.getElementById('security_post_id');
    const s = document.getElementById('security_shift_id');
    const u = document.getElementById('security_user_id');
    const d = document.getElementById('assignment_date');
    const cb = document.getElementById('include_breaks');
    const summary = document.getElementById('assignment-summary');

    if (!p?.value || !s?.value || !u?.value || !d?.value) {
        summary?.classList.add('hidden');
        return;
    }
    const postText = p.options[p.selectedIndex].text.split('-')[0].trim();
    const shiftText = s.options[s.selectedIndex].text.split('(')[0].trim();
    const personnelText = u.options[u.selectedIndex].text.split('-')[0].trim();
    const shiftDur = s.options[s.selectedIndex].dataset.duration || '0';
    const rotationType = s.options[s.selectedIndex].dataset.rotationType || 'fixed';
    const hasBreaks = s.options[s.selectedIndex].dataset.hasBreaks === 'true';

    document.getElementById('summary-post').textContent = postText;
    document.getElementById('summary-datetime').textContent = `${new Date(d.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${shiftText}`;
    document.getElementById('summary-personnel').textContent = personnelText;
    document.getElementById('summary-duration').textContent = `${shiftDur} hours`;

    document.getElementById('rotation-summary')?.classList.toggle('hidden', rotationType !== 'rotating');
    if (rotationType === 'rotating') {
        document.getElementById('rotation-summary-text').textContent = 'Part of rotating shift schedule';
    }

    const breakSummary = document.getElementById('break-summary');
    if (cb?.checked && hasBreaks) breakSummary?.classList.remove('hidden');
    else breakSummary?.classList.add('hidden');

    updateTimelinePreview(s.options[s.selectedIndex], d.value);
    summary?.classList.remove('hidden');
    validateForm();
}

function updateTimelinePreview(shiftOpt, dateValue) {
    if (!shiftOpt) return;
    const start = shiftOpt.dataset.startTime?.substring(0, 5) || '00:00';
    const end = shiftOpt.dataset.endTime?.substring(0, 5) || '00:00';
    document.getElementById('timeline-start').textContent = start;
    document.getElementById('timeline-end').textContent = end;

    const now = new Date();
    const nowDec = now.getHours() + now.getMinutes() / 60;
    const [sh, sm] = start.split(':').map(Number);
    const startDec = sh + sm / 60;
    let [eh, em] = end.split(':').map(Number);
    let endDec = eh + em / 60;
    if (endDec < startDec) endDec += 24;

    let progress = 0, status = '';
    if (dateValue === now.toISOString().split('T')[0]) {
        if (nowDec < startDec) { progress = 0; status = 'Not started'; }
        else if (nowDec > endDec) { progress = 100; status = 'Ended'; }
        else { progress = ((nowDec - startDec) / (endDec - startDec)) * 100; status = 'In progress'; }
    } else if (new Date(dateValue) > now) {
        status = 'Scheduled';
    } else {
        status = 'Past';
    }
    document.getElementById('shift-timeline-status').textContent = status;
    document.getElementById('timeline-progress').style.width = `${Math.min(progress, 100)}%`;
}

// =============================================
// VALIDATION
// =============================================
function validateForm() {
    const p = document.getElementById('security_post_id');
    const s = document.getElementById('security_shift_id');
    const u = document.getElementById('security_user_id');
    const d = document.getElementById('assignment_date');
    let valid = true;
    const warnings = [];

    if (!p?.value) { valid = false; warnings.push('Security Post is required'); }
    if (!s?.value) { valid = false; warnings.push('Security Shift is required'); }
    if (!u?.value) { valid = false; warnings.push('Security Personnel is required'); }
    if (!d?.value) { valid = false; warnings.push('Assignment Date is required'); }

    if (s?.value && d?.value) {
        const opt = s.options[s.selectedIndex];
        if (opt) {
            const days = JSON.parse(opt.dataset.applicableDays || '[]');
            if (Array.isArray(days) && days.length > 0 && !days.includes(getDayOfWeekIso(d.value))) {
                valid = false;
                warnings.push('Shift not applicable on this day');
            }
        }
    }

    if (u?.value && d?.value) {
        if (!document.getElementById('rest-period-warning')?.classList.contains('hidden')) {
            valid = false; warnings.push('Insufficient rest period');
        }
        if (!document.getElementById('weekly-hours-warning')?.classList.contains('hidden')) {
            valid = false; warnings.push('Weekly hour limit exceeded');
        }
        const span = document.querySelector('#personnel-availability span');
        if (span?.textContent === 'Unavailable') {
            valid = false; warnings.push('Personnel unavailable on this date');
        }
    }

    if (isBreakOverridden) {
        const r = document.getElementById('break_override_reason');
        if (!r || !r.value.trim()) {
            valid = false;
            warnings.push('Override reason is required');
        }
    }

    const w = document.getElementById('submit-warnings');
    const wt = document.getElementById('submit-warning-text');
    const btn = document.getElementById('submit-button');
    const status = document.getElementById('form-status');

    if (warnings.length > 0) {
        w?.classList.remove('hidden');
        if (wt) wt.textContent = warnings.join('. ') + '.';
        if (btn) {
            btn.disabled = !valid;
            btn.classList.toggle('opacity-50', !valid);
            btn.classList.toggle('cursor-not-allowed', !valid);
        }
        if (status) {
            status.innerHTML = !valid                ? '<div class="flex items-center text-red-600"><i class="fas fa-exclamation-circle mr-2"></i><span>Please fix validation errors</span></div>'
                : '<div class="flex items-center text-yellow-600"><i class="fas fa-exclamation-triangle mr-2"></i><span>Ready (warnings only)</span></div>';
        }
    } else {
        w?.classList.add('hidden');
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (status) {
            status.innerHTML = '<div class="flex items-center text-green-600"><i class="fas fa-check-circle mr-2"></i><span>Ready to submit</span></div>';
        }
    }

    isFormValid = valid;
    return valid;
}

// =============================================
// BULK ASSIGN (unchanged logic; only score is clamped)
// =============================================
window.showBulkAssignModal = function() {
    updateBulkShiftDefaultIndicators();
    updateWeeklyDaysFromShift();
    updateBulkAssignSummary();
    openModal('bulkAssignModal');
};

function updateWeeklyDaysFromShift() {
    const sel = document.getElementById('bulk_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const days = JSON.parse(sel.options[sel.selectedIndex].dataset.applicableDays || '[]');
    if (Array.isArray(days) && days.length > 0) {
        document.querySelectorAll('input[name="recurrence_days[]"]').forEach(cb => cb.checked = false);
        days.forEach(d => {
            const cb = document.querySelector(`input[name="recurrence_days[]"][value="${d}"]`);
            if (cb) cb.checked = true;
        });
    }
}

function updateBulkShiftDefaultIndicators() {
    const sel = document.getElementById('bulk_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    const hasBreaks = opt.dataset.hasBreaks === 'true';
    const hasHandover = opt.dataset.hasHandover === 'true';
    document.getElementById('bulk-shift-break-indicator').textContent = hasBreaks ? 'Default: Yes' : 'Default: No';
    document.getElementById('bulk-shift-handover-indicator').textContent = hasHandover ? 'Default: Yes' : 'Default: No';
    document.getElementById('bulk_include_breaks').checked = hasBreaks;
    document.getElementById('bulk_include_handover').checked = hasHandover;
    checkBulkOverrides(opt);
}

function checkBulkOverrides(opt) {
    const b = document.getElementById('bulk_include_breaks');
    const h = document.getElementById('bulk_include_handover');
    if (!opt || !b || !h) return;
    const sb = opt.dataset.hasBreaks === 'true';
    const sh = opt.dataset.hasHandover === 'true';
    let override = false;
    if (b.checked !== sb) { document.getElementById('bulk-break-override-warning')?.classList.remove('hidden'); override = true; }
    else document.getElementById('bulk-break-override-warning')?.classList.add('hidden');
    if (h.checked !== sh) { document.getElementById('bulk-handover-override-warning')?.classList.remove('hidden'); override = true; }
    else document.getElementById('bulk-handover-override-warning')?.classList.add('hidden');
    document.getElementById('bulk-override-reason-container')?.classList.toggle('hidden', !override);
    const r = document.getElementById('bulk_override_reason');
    if (override) r?.setAttribute('required', 'required');
    else { r?.removeAttribute('required'); if (r) r.value = ''; }
}

function updateBulkAssignSummary() {
    const p = document.getElementById('bulk_post_id');
    const s = document.getElementById('bulk_shift_id');
    const u = document.getElementById('bulk_user_ids');
    const sd = document.getElementById('startDate');
    const ed = document.getElementById('endDate');
    const rt = document.getElementById('recurrenceType');
    const summary = document.getElementById('bulkAssignSummary');
    if (!p || !s || !u || !sd || !ed || !rt) return;
    const n = u.selectedOptions.length;
    if (n > 0 && sd.value && ed.value) {
        document.getElementById('summaryPersonnelCount').textContent = n;
        document.getElementById('summaryDateRange').textContent = sd.value + ' to ' + ed.value;
        document.getElementById('summaryRecurrence').textContent = rt.options[rt.selectedIndex].text;
        const pattern = document.querySelector('input[name="rotation_pattern"]:checked');
        document.getElementById('summaryRotationPattern').textContent = (rt.value === 'rotating' && pattern) ? pattern.value : 'None';
        const days = Math.ceil((new Date(ed.value) - new Date(sd.value)) / (1000 * 60 * 60 * 24)) + 1;
        let mult = 1;
        if (rt.value === 'weekly') mult = document.querySelectorAll('input[name="recurrence_days[]"]:checked').length / 7;
        else if (rt.value === 'monthly') mult = 0.5;
        document.getElementById('summaryTotalAssignments').textContent = Math.ceil(n * days * mult);
        summary?.classList.remove('hidden');
    } else {
        summary?.classList.add('hidden');
    }
}

window.previewBulkAssign = function() {
    const form = document.getElementById('bulkAssignForm');
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
    const fd = new FormData(form);
    const userIds = fd.getAll('security_user_ids[]');
    if (userIds.length === 0) { showToast('Select at least one personnel', 'warning'); return; }

    const data = {
        _token: fd.get('_token'),
        security_post_id: fd.get('security_post_id'),
        security_shift_id: fd.get('security_shift_id'),
        security_user_ids: userIds,
        start_date: fd.get('start_date'),
        end_date: fd.get('end_date'),
        recurrence_type: fd.get('recurrence_type'),
        include_breaks: fd.get('include_breaks') === 'on',
        include_handover: fd.get('include_handover') === 'on',
        maintain_coverage: fd.get('maintain_coverage') === 'on',
        respect_preferences: fd.get('respect_preferences') === 'on',
        notify_personnel: fd.get('notify_personnel') === 'on',
        notes: fd.get('notes') || '',
        emergency_contact: fd.get('emergency_contact') || '',
        special_instructions: fd.get('special_instructions') || '',
        override_reason: document.getElementById('bulk_override_reason')?.value || '',
    };
    const days = fd.getAll('recurrence_days[]');
    if (days.length > 0) data.recurrence_days = days.map(Number);
    const pattern = fd.get('rotation_pattern');
    if (pattern) data.rotation_pattern = pattern;
    if (fd.get('create_rotation_group') === 'on') {
        data.create_rotation_group = true;
        data.rotation_group_name = fd.get('rotation_group_name') || 'Auto Group';
    }

    document.getElementById('previewContent').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i><p class="mt-2" style="color: var(--text-secondary);">Generating preview...</p></div>';
    openModal('bulkAssignPreviewModal');

    fetch('{{ route("admin.security-schedules.bulk-assign-preview") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    })
    .then(r => r.headers.get('content-type')?.includes('application/json') ? r.json() : r.text().then(t => { throw new Error('Server returned non-JSON'); }))
    .then(d => {
        if (d.success) {
            document.getElementById('previewContent').innerHTML = `<pre class="text-xs overflow-auto p-3" style="background-color: var(--bg-secondary); color: var(--text-primary);">${JSON.stringify(d, null, 2)}</pre>`;
        } else {
            showToast(d.message || 'Preview failed', 'error');
            closeModal('bulkAssignPreviewModal');
        }
    })
    .catch(e => { showToast(e.message, 'error'); closeModal('bulkAssignPreviewModal'); });
};

window.submitBulkAssignForm = function() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) return;
    const fd = new FormData(form);
    const userIds = fd.getAll('security_user_ids[]');
    if (userIds.length === 0) { showToast('Select at least one personnel', 'warning'); return; }

    const data = {
        _token: fd.get('_token'),
        security_post_id: fd.get('security_post_id'),
        security_shift_id: fd.get('security_shift_id'),
        security_user_ids: userIds,
        start_date: fd.get('start_date'),
        end_date: fd.get('end_date'),
        recurrence_type: fd.get('recurrence_type'),
        include_breaks: fd.get('include_breaks') === 'on',
        include_handover: fd.get('include_handover') === 'on',
        maintain_coverage: fd.get('maintain_coverage') === 'on',
        respect_preferences: fd.get('respect_preferences') === 'on',
        notify_personnel: fd.get('notify_personnel') === 'on',
        notes: fd.get('notes') || '',
        emergency_contact: fd.get('emergency_contact') || '',
        special_instructions: fd.get('special_instructions') || '',
        override_reason: document.getElementById('bulk_override_reason')?.value || '',
    };
    const days = fd.getAll('recurrence_days[]');
    if (days.length > 0) data.recurrence_days = days.map(Number);
    const pattern = fd.get('rotation_pattern');
    if (pattern) data.rotation_pattern = pattern;
    if (fd.get('create_rotation_group') === 'on') {
        data.create_rotation_group = true;
        data.rotation_group_name = fd.get('rotation_group_name') || 'Auto Group';
    }

    const btn = document.getElementById('bulkAssignSubmitBtn');
    const orig = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...'; }
    showLoading('Creating schedules...');

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(data),
    })
    .then(r => r.headers.get('content-type')?.includes('application/json') ? r.json() : r.text().then(t => { throw new Error('Server returned non-JSON'); }))
    .then(d => {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        hideLoading();
        if (d.success) {
            showToast(`✅ ${d.created_count} schedule(s) created successfully!`, 'success');
            closeModal('bulkAssignModal');
            setTimeout(() => location.href = '{{ route("admin.security-schedules.index") }}', 1500);
        } else {
            showToast('❌ ' + (d.message || 'Failed'), 'error');
        }
    })
    .catch(e => {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        hideLoading();
        showToast('❌ ' + e.message, 'error');
    });
};

// =============================================
// MODAL
// =============================================
window.openModal = function(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
};
window.closeModal = function(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
};
window.viewRotationHistoryForCurrent = function() {
    const gid = document.getElementById('rotation_group_id')?.value;
    if (gid) {
        fetch(`/admin/rotation-groups/${gid}/history`)
            .then(r => r.json())
            .then(d => {
                const c = document.getElementById('rotationHistoryContent');
                if (d.success && d.history?.length > 0) {
                    c.innerHTML = d.history.map(h => `
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
                            <div class="flex justify-between">
                                <span class="font-medium" style="color: var(--text-primary);">${h.date}</span>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${h.type}</span>
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-primary);">From: ${h.from || '-'} → To: ${h.to || '-'}</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">${h.reason || ''}</p>
                        </div>
                    `).join('');
                } else {
                    c.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);">No rotation history.</p>';
                }
            })
            .catch(() => {
                document.getElementById('rotationHistoryContent').innerHTML = '<p class="text-center py-4" style="color: var(--danger);">Failed to load.</p>';
            });
        openModal('rotation-history-modal');
    } else {
        showToast('No rotation group', 'info');
    }
};

// =============================================
// TOAST
// =============================================
window.showToast = function(message, type = 'info') {
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; c.className = 'fixed top-4 right-4 z-50 space-y-2'; document.body.appendChild(c); }
    const t = document.createElement('div');
    t.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    t.innerHTML = `<span class="text-sm font-medium flex-1"></span><button class="ml-4" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
    t.querySelector('span').textContent = message;
    c.appendChild(t);
    setTimeout(() => t.classList.remove('translate-x-full'), 10);
    setTimeout(() => { t.classList.add('translate-x-full'); setTimeout(() => t.remove(), 300); }, 5000);
};

// =============================================
// INIT
// =============================================
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('security-schedule-form');
    const p = document.getElementById('security_post_id');
    const s = document.getElementById('security_shift_id');
    const u = document.getElementById('security_user_id');
    const d = document.getElementById('assignment_date');
    const cb = document.getElementById('include_breaks');

    p?.addEventListener('change', () => { updatePostDetails(); updatePostCapacity(); updateHandoverInfo(); updateAssignmentSummary(); });
    d?.addEventListener('change', () => { selectedDate = d.value; updateDayOfWeek(); updatePostCapacity(); updateShiftDetails(); updateHandoverInfo(); checkPersonnelAvailability(); updateAssignmentSummary(); });
    s?.addEventListener('change', () => { updateShiftDetails(); updateHandoverInfo(); checkPersonnelAvailability(); updateAssignmentSummary(); updateShiftDefaultIndicators(); });
    u?.addEventListener('change', () => { updatePersonnelInfo(); checkPersonnelAvailability(); updateAssignmentSummary(); });
    cb?.addEventListener('change', () => { cb.dataset.userModified = 'true'; checkForOverrides(); updateAssignmentSummary(); });
    document.getElementById('break_override_reason')?.addEventListener('input', validateForm);

    form?.addEventListener('submit', function (e) {
        if (!validateForm()) {
            e.preventDefault();
            const first = document.querySelector('.border-red-500');
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first?.focus();
            return;
        }
        if (isBreakOverridden) {
            const reason = document.getElementById('break_override_reason')?.value || '';
            const audit = document.createElement('input');
            audit.type = 'hidden';
            audit.name = 'override_audit_log';
            audit.value = JSON.stringify({
                timestamp: new Date().toISOString(),
                reason,
                overrides: overrideAuditLog,
                user_id: {{ auth()->id() }},
                user_name: @js(auth()->user()->name),
            });
            form.appendChild(audit);
        }
    });

    setupBulkAssignForm();

    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.closest('.modal')?.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal:not(.hidden)').forEach(m => { m.classList.add('hidden'); });
            document.body.style.overflow = 'auto';
        }
    });

    function updateDayOfWeek() {
        const el = document.getElementById('day-of-week-display');
        const dv = d?.value;
        if (dv && el) {
            el.textContent = `${getDayOfWeek(dv)}, ${new Date(dv).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        } else if (el) {
            el.textContent = 'Select a date';
        }
    }

    if (d && !d.value) d.value = new Date().toISOString().split('T')[0];
    updateDayOfWeek();
    if (p?.value) { updatePostDetails(); updatePostCapacity(); }
    if (s?.value) { updateShiftDetails(); updateHandoverInfo(); updateShiftDefaultIndicators(); }
    if (u?.value) { updatePersonnelInfo(); checkPersonnelAvailability(); }
    if (p?.value && s?.value && u?.value && d?.value) updateAssignmentSummary();

    setInterval(() => { if (p?.value && d?.value) updatePostCapacity(); }, 30000);
});

function setupBulkAssignForm() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) return;
    form.addEventListener('submit', e => { e.preventDefault(); window.submitBulkAssignForm(); });

    form.querySelector('select[name="security_user_ids[]"]')?.addEventListener('change', updateBulkAssignSummary);
    document.getElementById('bulk_post_id')?.addEventListener('change', updateBulkAssignSummary);
    document.getElementById('bulk_shift_id')?.addEventListener('change', () => {
        updateBulkShiftDefaultIndicators();
        updateWeeklyDaysFromShift();
        updateBulkAssignSummary();
    });
    document.getElementById('startDate')?.addEventListener('change', function () {
        const end = document.getElementById('endDate');
        end.min = this.value;
        if (end.value && end.value < this.value) end.value = this.value;
        updateBulkAssignSummary();
    });
    document.getElementById('endDate')?.addEventListener('change', updateBulkAssignSummary);
    document.getElementById('recurrenceType')?.addEventListener('change', function () {
        toggleRecurrenceOptions(this.value);
        updateBulkAssignSummary();
    });
    document.getElementById('bulk_include_breaks')?.addEventListener('change', function () {
        this.dataset.userModified = 'true';
        checkBulkOverrides(document.getElementById('bulk_shift_id').options[document.getElementById('bulk_shift_id').selectedIndex]);
    });
    document.getElementById('bulk_include_handover')?.addEventListener('change', function () {
        this.dataset.userModified = 'true';
        checkBulkOverrides(document.getElementById('bulk_shift_id').options[document.getElementById('bulk_shift_id').selectedIndex]);
    });
    document.querySelectorAll('input[name="rotation_pattern"]').forEach(r => r.addEventListener('change', updateBulkAssignSummary));
    document.querySelectorAll('input[name="recurrence_days[]"]').forEach(c => c.addEventListener('change', updateBulkAssignSummary));
    document.getElementById('create_rotation_group')?.addEventListener('change', function () {
        document.getElementById('rotationGroupNameContainer')?.classList.toggle('hidden', !this.checked);
    });
}

function toggleRecurrenceOptions(type) {
    const w = document.getElementById('weeklyDaysContainer');
    const m = document.getElementById('monthlyDatesContainer');
    const r = document.getElementById('rotationPatternContainer');
    w?.classList.add('hidden'); m?.classList.add('hidden'); r?.classList.add('hidden');
    if (type === 'weekly') w?.classList.remove('hidden');
    else if (type === 'monthly') m?.classList.remove('hidden');
    else if (type === 'rotating') r?.classList.remove('hidden');
}
</script>

<style>
.card { transition: transform 0.2s ease, box-shadow 0.2s ease; background-color: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 0.5rem; overflow: hidden; }
.card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); }
input, select, textarea { transition: border-color 0.15s ease, box-shadow 0.15s ease; background-color: var(--bg-secondary, #f9fafb); border: 1px solid var(--border-color, #d1d5db); border-radius: 0.375rem; padding: 0.5rem 0.75rem; }
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary, #3b82f6); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.btn-primary { background-color: var(--primary, #3b82f6); color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; transition: all 0.2s ease; border: none; cursor: pointer; }
.btn-primary:hover { background-color: var(--primary-dark, #2563eb); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.btn-secondary { background-color: var(--bg-tertiary, #f3f4f6); color: var(--text-primary, #1f2937); padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; transition: all 0.2s ease; border: 1px solid var(--border-color, #d1d5db); }
.btn-secondary:hover { background-color: var(--bg-secondary, #e5e7eb); text-decoration: none; }
.modal { position: fixed; inset: 0; z-index: 9999; }
.modal-overlay { position: absolute; inset: 0; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
.modal-container { position: relative; background-color: var(--card-bg); border-radius: 16px; margin: 2rem auto; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid var(--border-color); animation: modalFadeIn 0.3s ease-out; }
.modal-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 6px; transition: all 0.2s ease; }
.modal-close:hover { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background-color: var(--bg-secondary); border-radius: 0 0 16px 16px; }
@keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.peer:checked ~ .peer-checked\:bg-info { background-color: var(--info, #3b82f6) !important; }
.peer:checked ~ .peer-checked\:after\:translate-x-full::after { transform: translateX(100%); }
@keyframes pulseWarning { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
#capacity-warning:not(.hidden), #applicability-warning:not(.hidden), #rest-period-warning:not(.hidden), #weekly-hours-warning:not(.hidden), #break-override-warning:not(.hidden), #bulk-break-override-warning:not(.hidden), #bulk-handover-override-warning:not(.hidden), #submit-warnings:not(.hidden) { animation: pulseWarning 2s infinite; }
.hidden { display: none !important; }
.text-xxs { font-size: 0.65rem; line-height: 1rem; }
@media (max-width: 768px) {
    .grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr; }
    .modal-container { margin: 1rem; }
    .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
}
</style>
@endsection