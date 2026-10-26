@extends('layouts.secu')

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
                <a href="{{ route('security.schedules.index') }}" class="btn-ghost flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                </a>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('security.schedules.store') }}" method="POST" id="security-schedule-form" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-cols-1 gap-6">
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
                {{-- LEFT --}}
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

                    {{-- Date & Shift --}}
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
                                        <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
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

                {{-- RIGHT --}}
                <div class="space-y-6">
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

            {{-- Rotation options --}}
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
                <input type="hidden" name="rotation_group_id" id="rotation_group_id" value="">
                <input type="hidden" name="rotation_group_type" id="rotation_group_type" value="">
                <input type="hidden" name="rotation_sequence_number" id="rotation_sequence_number" value="0">
                <input type="hidden" name="rotation_preference_score" id="rotation_preference_score" value="5">
                <input type="hidden" name="is_rotated" id="is_rotated" value="0">
            </div>

            {{-- Summary --}}
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

            {{-- Actions --}}
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div id="form-status" class="text-sm" style="color: var(--text-secondary);"></div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Create Schedule
                        </button>
                        <a href="{{ route('security.schedules.index') }}" class="btn-secondary">
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

{{-- Bulk Assign Modal (unchanged) --}}
<div id="bulkAssignModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkAssignModal')"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">Bulk Assign Personnel</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkAssignModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="bulkAssignForm" method="POST" action="{{ route('security.schedules.bulk-assign') }}">
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
                                <option value="{{ $shift->id }}"
                                        data-category="{{ $shift->category }}"
                                        data-rotation="{{ $shift->rotation_type }}"
                                        data-duration="{{ $shift->duration_hours }}"
                                        data-overnight="{{ $shift->is_overnight ? 'yes' : 'no' }}"
                                        data-has-breaks="{{ isset($shift->break_schedule['has_break']) && $shift->break_schedule['has_break'] ? 'true' : 'false' }}"
                                        data-has-handover="{{ isset($shift->handover_config['has_handover']) && $shift->handover_config['has_handover'] ? 'true' : 'false' }}"
                                        data-applicable-days="{{ json_encode($shift->applicable_days ?? []) }}">
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
                        <i class="fas fa-info-circle mr-1"></i> Hold Ctrl/Cmd to select multiple. Selected: <span id="selectedPersonnelCount">0</span>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-start mr-1" style="color: var(--primary);"></i> Start Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="start_date" id="startDate" class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ now()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-end mr-1" style="color: var(--primary);"></i> End Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="end_date" id="endDate" class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ now()->addWeek()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-repeat mr-1" style="color: var(--primary);"></i> Recurrence Type <span class="text-red-500">*</span>
                    </label>
                    <select name="recurrence_type" id="recurrenceType" class="form-input w-full p-3 rounded-lg border"
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
                        @foreach(['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>7] as $label=>$v)
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="recurrence_days[]" value="{{ $v }}" class="mr-2" checked> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div id="monthlyDatesContainer" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Select Dates of Month
                    </label>
                    <select name="recurrence_days[]" class="form-input w-full p-3 rounded-lg border" multiple size="5">
                        @for($i = 1; $i <= 31; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
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
                            <input type="text" name="rotation_group_name" id="rotation_group_name" class="form-input w-full p-2 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Enter rotation group name (e.g., 'Alpha Team - Night Shift')">
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
                        <label class="flex items-center"><input type="checkbox" name="include_breaks" id="bulk_include_breaks" value="1" class="mr-2" checked><span style="color: var(--text-primary);">Include Break Schedules</span></label>
                    </div>
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <label class="flex items-center"><input type="checkbox" name="include_handover" id="bulk_include_handover" value="1" class="mr-2"><span style="color: var(--text-primary);">Include Handover Procedures</span></label>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-1" style="color: var(--primary);"></i> Notes
                    </label>
                    <textarea name="notes" class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              rows="2" maxlength="500" placeholder="General notes for all assigned schedules"></textarea>
                </div>
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-phone-alt mr-1" style="color: var(--primary);"></i> Emergency Contact
                    </label>
                    <input type="text" name="emergency_contact" class="form-input w-full p-3 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                           maxlength="255" placeholder="Emergency contact number for all assignments">
                </div>
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-1" style="color: var(--primary);"></i> Special Instructions
                    </label>
                    <textarea name="special_instructions" class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              rows="2" maxlength="1000" placeholder="Special instructions for all assigned personnel"></textarea>
                </div>

                <div id="bulkAssignSummary" class="mt-6 p-4 rounded-lg hidden" style="background-color: rgba(var(--success-rgb), 0.05);">
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-calculator mr-2" style="color: var(--success);"></i> Assignment Summary
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <span style="color: var(--text-secondary);">Personnel Selected:</span>
                        <span style="color: var(--text-primary);" id="summaryPersonnelCount">0</span>
                        <span style="color: var(--text-secondary);">Date Range:</span>
                        <span style="color: var(--text-primary);" id="summaryDateRange">-</span>
                        <span style="color: var(--text-secondary);">Recurrence:</span>
                        <span style="color: var(--text-primary);" id="summaryRecurrence">-</span>
                        <span style="color: var(--text-secondary);">Rotation Pattern:</span>
                        <span style="color: var(--text-primary);" id="summaryRotationPattern">None</span>
                        <span style="color: var(--text-secondary);">Total Assignments:</span>
                        <span style="color: var(--text-primary); font-weight: bold;" id="summaryTotalAssignments">0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('bulkAssignModal')">Cancel</button>
                <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                        onclick="previewBulkAssign()"><i class="fas fa-eye mr-2"></i> Preview</button>
                <button type="submit" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary" id="bulkAssignSubmitBtn"><i class="fas fa-users mr-2"></i> Assign Personnel</button>
            </div>
        </form>
    </div>
</div>

{{-- Preview Modal --}}
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
            <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('bulkAssignPreviewModal')">Cancel</button>
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
        <div class="modal-body"><div id="rotationHistoryContent" class="space-y-3"></div></div>
        <div class="modal-footer">
            <button type="button" class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('rotation-history-modal')">Close</button>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// State
let currentCapacity = 0;
let selectedDate = document.getElementById('assignment_date')?.value || new Date().toISOString().split('T')[0];
let isFormValid = true;
let originalShiftConfig = { hasBreaks: false, hasHandover: false, handoverDuration: 30, handoverNotesRequired: false, breakSchedule: {} };
let isBreakOverridden = false;
let overrideAuditLog = [];

// Routes
const ROUTES = {
    postCapacity:      "{{ route('security.schedules.post-capacity') }}",
    handoverInfo:      "{{ route('security.schedules.handover-info') }}",
    checkAvailability: "{{ route('security.schedules.check-availability') }}",
    bulkPreview:       "{{ route('security.schedules.bulk-assign-preview') }}",
    index:             "{{ route('security.schedules.index') }}",
    rotationHistory:   (id) => `/security/rotation-groups/${id}/history`,
};

// Loading
function showLoading(msg = 'Processing...') {
    let o = document.getElementById('loadingOverlay');
    if (!o) {
        o = document.createElement('div');
        o.id = 'loadingOverlay';
        o.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        o.innerHTML = `<div class="text-center"><div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div><div class="text-white font-medium" id="loadingMessage">${msg}</div></div>`;
        document.body.appendChild(o);
    }
    const m = document.getElementById('loadingMessage');
    if (m) m.textContent = msg;
    o.classList.remove('hidden');
}
function hideLoading() { document.getElementById('loadingOverlay')?.classList.add('hidden'); }

// Day-of-week
const daysOfWeek = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const getDayOfWeek    = d => daysOfWeek[new Date(d + 'T12:00:00').getDay()];
const getDayOfWeekIso = d => { const x = new Date(d + 'T12:00:00').getDay(); return x === 0 ? 7 : x; };

// Shift defaults
function updateShiftDefaultIndicators() {
    const sel = document.getElementById('security_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    const hasBreaks = opt.dataset.hasBreaks === 'true';
    originalShiftConfig = {
        hasBreaks,
        hasHandover: opt.dataset.hasHandover === 'true',
        handoverDuration: opt.dataset.handoverDuration || '30',
        handoverNotesRequired: opt.dataset.handoverNotesRequired === 'true',
        breakSchedule: JSON.parse(opt.dataset.breakSchedule || '{}'),
        breakCount: opt.dataset.breakCount || '0',
    };
    const cb = document.getElementById('include_breaks');
    if (cb && !cb.dataset.userModified) cb.checked = hasBreaks;
    resetOverrideState();
    checkForOverrides();
}
function resetOverrideState() {
    isBreakOverridden = false;
    ['break-override-warning','break-override-reason-container','override-summary'].forEach(id => document.getElementById(id)?.classList.add('hidden'));
    const r = document.getElementById('break_override_reason');
    if (r) r.value = '';
}
function checkForOverrides() {
    const cb = document.getElementById('include_breaks');
    if (!cb) return;
    if (cb.checked !== originalShiftConfig.hasBreaks) {
        isBreakOverridden = true;
        const w = document.getElementById('break-override-warning');
        const rc = document.getElementById('break-override-reason-container');
        const ri = document.getElementById('break_override_reason');
        w?.classList.remove('hidden');
        rc?.classList.remove('hidden');
        ri?.setAttribute('required', 'required');
        overrideAuditLog.push({ field: 'breaks', action: cb.checked ? 'enabling' : 'disabling', timestamp: new Date().toISOString(), from: originalShiftConfig.hasBreaks, to: cb.checked });
    } else {
        isBreakOverridden = false;
        document.getElementById('break-override-warning')?.classList.add('hidden');
        document.getElementById('break-override-reason-container')?.classList.add('hidden');
        const ri = document.getElementById('break_override_reason');
        if (ri) { ri.removeAttribute('required'); ri.value = ''; }
    }
    updateOverrideSummary();
}
function updateOverrideSummary() {
    const s = document.getElementById('override-summary');
    const t = document.getElementById('override-summary-text');
    if (!s || !t) return;
    if (isBreakOverridden) {
        t.innerHTML = `<div>• Break schedule: ${document.getElementById('include_breaks').checked ? 'Enabled' : 'Disabled'} (default: ${originalShiftConfig.hasBreaks ? 'Enabled' : 'Disabled'})</div>`;
        s.classList.remove('hidden');
    } else {
        s.classList.add('hidden');
    }
}

// Post capacity
async function updatePostCapacity() {
    const postId = document.getElementById('security_post_id')?.value;
    const date = document.getElementById('assignment_date')?.value;
    const info = document.getElementById('post-capacity-info');
    const stats = document.getElementById('post-quick-stats');
    const equip = document.getElementById('post-equipment');
    if (!postId || !date) { info?.classList.add('hidden'); stats?.classList.add('hidden'); equip?.classList.add('hidden'); return; }

    try {
        const res = await fetch(`${ROUTES.postCapacity}?post_id=${postId}&date=${date}`);
        const data = await res.json();
        if (!data.success) return;
        currentCapacity = data.assigned ?? data.currently_assigned ?? 0;
        const max = data.max_personnel ?? data.capacity ?? 0;
        const available = data.available ?? data.available_slots ?? 0;

        document.getElementById('current-assignments').textContent = currentCapacity;
        document.getElementById('max-personnel').textContent = max;
        const bar = document.getElementById('capacity-progress');
        const pct = max > 0 ? (currentCapacity / max) * 100 : 0;
        bar.style.width = `${Math.min(pct, 100)}%`;
        bar.style.backgroundColor = pct >= 90 ? 'var(--danger)' : pct >= 75 ? 'var(--warning)' : 'var(--success)';

        const w = document.getElementById('capacity-warning');
        const wt = document.getElementById('capacity-warning-text');
        if (available <= 0) { w.classList.remove('hidden'); wt.textContent = 'Post is at full capacity.'; }
        else if (available < 3) { w.classList.remove('hidden'); wt.textContent = `Only ${available} slot(s) available.`; }
        else w.classList.add('hidden');

        info?.classList.remove('hidden');
    } catch (e) { console.error('post-capacity:', e); }
}

function updatePostDetails() {
    const sel = document.getElementById('security_post_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    const stats = document.getElementById('post-quick-stats');
    const equip = document.getElementById('post-equipment');
    if (!opt.value) { stats?.classList.add('hidden'); equip?.classList.add('hidden'); return; }
    document.getElementById('post-type-display').textContent = (opt.dataset.postType || 'Standard').replace('_', ' ').toUpperCase();
    document.getElementById('post-location-display').textContent = opt.dataset.postLocation || 'N/A';
    const equipment = JSON.parse(opt.dataset.postEquipment || '[]');
    const list = document.getElementById('post-equipment-list');
    if (equipment.length > 0 && list && equip) {
        list.innerHTML = equipment.map(i => `<span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${i}</span>`).join('');
        equip.classList.remove('hidden');
    } else equip?.classList.add('hidden');
    stats?.classList.remove('hidden');
}

// Shift details
function updateShiftDetails() {
    const sel = document.getElementById('security_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    const details = document.getElementById('shift-details');
    const rotOpts = document.getElementById('rotation-options');
    if (!opt.value) { details?.classList.add('hidden'); rotOpts?.classList.add('hidden'); return; }

    const start = opt.dataset.startTime?.substring(0, 5) || '--:--';
    const end = opt.dataset.endTime?.substring(0, 5) || '--:--';
    const duration = opt.dataset.duration || '0';
    const category = opt.dataset.category || 'standard';
    const isOvernight = opt.dataset.isOvernight === 'true';
    const hasHandover = opt.dataset.hasHandover === 'true';
    const handoverDuration = opt.dataset.handoverDuration || '30';
    const rotationType = opt.dataset.rotationType || 'fixed';
    const applicableDays = JSON.parse(opt.dataset.applicableDays || '[]');
    const rotationConfig = JSON.parse(opt.dataset.rotationConfig || '{}');
    const breakSchedule = JSON.parse(opt.dataset.breakSchedule || '{}');
    const hasBreaks = opt.dataset.hasBreaks === 'true';

    const el = id => document.getElementById(id);
    if (el('shift-time-display')) el('shift-time-display').textContent = `${start} - ${end}`;
    if (el('shift-duration-display')) el('shift-duration-display').textContent = `${duration} hours`;
    if (el('shift-category-display')) el('shift-category-display').textContent = category.charAt(0).toUpperCase() + category.slice(1);
    if (el('shift-handover-display')) el('shift-handover-display').textContent = hasHandover ? `Yes (${handoverDuration}min)` : 'No';
    el('overnight-warning')?.classList.toggle('hidden', !isOvernight);

    updateBreakInfo(hasBreaks, breakSchedule);

    // Applicability
    const dateVal = el('assignment_date')?.value;
    const appWarn = el('applicability-warning');
    const appText = el('applicability-warning-text');
    if (dateVal && appWarn && appText) {
        if (applicableDays.length > 0) {
            if (!applicableDays.includes(getDayOfWeekIso(dateVal))) {
                appWarn.classList.remove('hidden');
                appText.textContent = `This shift is not applicable on ${getDayOfWeek(dateVal)}s.`;
            } else appWarn.classList.add('hidden');
        } else appWarn.classList.add('hidden');
    }

    // Rotation
    if (rotationType === 'rotating') {
        rotOpts?.classList.remove('hidden');
        updateRotationInfo(rotationConfig);
        if (el('rotation_group_type'))      el('rotation_group_type').value = category;
        if (el('rotation_sequence_number')) el('rotation_sequence_number').value = String(parseInt(rotationConfig.current_sequence_index || 0, 10));
        // FIX: reset to a safe integer default so stale decimal from a previous personnel can't survive
        if (el('rotation_preference_score')) el('rotation_preference_score').value = '5';
    } else {
        rotOpts?.classList.add('hidden');
        if (el('rotation_group_type'))      el('rotation_group_type').value = '';
        if (el('rotation_sequence_number')) el('rotation_sequence_number').value = '0';
        if (el('rotation_preference_score')) el('rotation_preference_score').value = '5'; // FIX
        if (el('is_rotated'))               el('is_rotated').value = '0';
    }

    details?.classList.remove('hidden');
    updateShiftDefaultIndicators();
    updateHandoverInfo();
}

function updateBreakInfo(hasBreaks, breakSchedule) {
    const info = document.getElementById('break-info');
    const list = document.getElementById('break-list');
    if (hasBreaks && Array.isArray(breakSchedule.breaks) && breakSchedule.breaks.length > 0) {
        if (list) list.innerHTML = breakSchedule.breaks.map(b => `<div class="text-xs">${b.name}: ${b.start_time} - ${b.end_time} (${b.duration_minutes} min)${b.is_paid ? ' 💰' : ''}</div>`).join('');
        info?.classList.remove('hidden');
        const total = breakSchedule.breaks.reduce((s, b) => s + (b.duration_minutes || 0), 0);
        const bsText = document.getElementById('break-summary-text');
        const bs = document.getElementById('break-summary');
        if (bsText) bsText.textContent = `${breakSchedule.breaks.length} breaks, total ${Math.floor(total / 60)}h ${total % 60}m`;
        bs?.classList.remove('hidden');
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
    const pos = document.getElementById('current-rotation-position');
    if (pos) pos.textContent = display[cfg.current_sequence_index || 0];
    const days = cfg.rotation_days || 7;
    const nr = new Date(); nr.setDate(nr.getDate() + days);
    const nrEl = document.getElementById('next-rotation-date');
    if (nrEl) nrEl.textContent = nr.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

async function updateHandoverInfo() {
    const shiftId = document.getElementById('security_shift_id')?.value;
    const date = document.getElementById('assignment_date')?.value;
    const postId = document.getElementById('security_post_id')?.value;
    const preview = document.getElementById('handover-preview-container');
    if (!shiftId || !date || !postId) { preview?.classList.add('hidden'); return; }
    try {
        const res = await fetch(`${ROUTES.handoverInfo}?shift_id=${shiftId}&date=${date}&post_id=${postId}`);
        const data = await res.json();
        if (!data.success || !data.handover_info) { preview?.classList.add('hidden'); return; }
        const hi = data.handover_info;
        const el = id => document.getElementById(id);
        if (el('handover-start-display')) el('handover-start-display').textContent = hi.handover_start || '--:--';
        if (el('handover-end-display')) el('handover-end-display').textContent = hi.handover_end || '--:--';
        if (el('handover-duration-display')) el('handover-duration-display').textContent = `${hi.handover_duration || 30} minutes`;
        if (el('handover-window-display')) el('handover-window-display').textContent = hi.handover_window ? `${hi.handover_window} minutes` : '--';
        el('handover-notes-required')?.classList.toggle('hidden', !hi.notes_required);
        const wrap = el('handover-checklist-preview');
        const list = el('checklist-items');
        if (Array.isArray(hi.checklist) && hi.checklist.length > 0) {
            if (list) list.innerHTML = hi.checklist.map(i => `<div class="flex items-center"><i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> ${i}</div>`).join('');
            wrap?.classList.remove('hidden');
        } else wrap?.classList.add('hidden');
        preview?.classList.remove('hidden');
    } catch (e) { console.error('handover:', e); preview?.classList.add('hidden'); }
}

// Personnel
function updatePersonnelInfo() {
    const sel = document.getElementById('security_user_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('personnel-info');
    const el = id => document.getElementById(id);

    if (!opt.value) {
        info?.classList.add('hidden');
        ['rotation-preference','availability-checker','rest-period-warning','weekly-hours-warning','rotation-score-container','seniority-performance'].forEach(id => el(id)?.classList.add('hidden'));
        return;
    }

    if (el('personnel-phone')) el('personnel-phone').textContent = opt.dataset.phone || 'N/A';
    if (el('personnel-badge')) el('personnel-badge').textContent = opt.dataset.badge || 'N/A';
    if (el('personnel-last-shift')) el('personnel-last-shift').textContent = opt.dataset.lastShift || 'No previous shifts';
    if (el('personnel-total-shifts')) el('personnel-total-shifts').textContent = opt.dataset.shiftsCount || '0';

    const joined = opt.dataset.joinedDate;
    if (joined && el('seniority-years')) {
        el('seniority-years').textContent = ((new Date() - new Date(joined)) / (1000 * 60 * 60 * 24 * 365)).toFixed(1) + ' years';
    }
    if (el('performance-rating')) el('performance-rating').textContent = (opt.dataset.performanceRating || '7') + '/10';
    el('seniority-performance')?.classList.remove('hidden');

    if (opt.dataset.willingToRotate === 'true') {
        el('rotation-preference')?.classList.remove('hidden');
        let text = 'Willing to rotate';
        if (opt.dataset.willingDayToNight === 'true') text = 'Willing to rotate: Day → Night';
        else if (opt.dataset.willingNightToDay === 'true') text = 'Willing to rotate: Night → Day';
        else if (opt.dataset.prefersNight === 'true') text = 'Prefers night shifts';
        else if (opt.dataset.prefersDay === 'true') text = 'Prefers day shifts';
        else if (opt.dataset.rotationPreference === 'any') text = 'Open to any rotation';
        if (el('rotation-preference-text')) el('rotation-preference-text').textContent = text;
    } else el('rotation-preference')?.classList.add('hidden');

    info?.classList.remove('hidden');
    calculatePreferenceScore(opt);
    checkPersonnelAvailability();
}

/**
 * FIX: writes an INTEGER string (e.g. "7") instead of a float
 * ("7.0"). Controller validates `integer|min:1|max:10`; "7.0" fails.
 */
function calculatePreferenceScore(opt) {
    const shiftSel = document.getElementById('security_shift_id');
    const postSel = document.getElementById('security_post_id');
    if (!shiftSel || !shiftSel.options[shiftSel.selectedIndex]) return;

    const category = shiftSel.options[shiftSel.selectedIndex]?.dataset.category || '';
    const preferredShifts = JSON.parse(opt.dataset.preferredShifts || '[]');
    const preferredPosts = JSON.parse(opt.dataset.preferredPosts || '[]');
    const willingToRotate = opt.dataset.willingToRotate === 'true';
    const willingDN = opt.dataset.willingDayToNight === 'true';
    const willingND = opt.dataset.willingNightToDay === 'true';
    const prefersNight = opt.dataset.prefersNight === 'true';
    const prefersDay = opt.dataset.prefersDay === 'true';
    const joined = opt.dataset.joinedDate;
    const perf = parseFloat(opt.dataset.performanceRating || '7');
    const postId = postSel?.value;

    let seniorityScore = 0;
    if (joined) seniorityScore = Math.min(2, (new Date() - new Date(joined)) / (1000 * 60 * 60 * 24 * 365));

    const performanceScore = (perf - 5) / 2.5;
    let shiftPrefScore = 0;
    if (preferredShifts.includes(category)) shiftPrefScore += 2;
    if (preferredPosts.includes(parseInt(postId))) shiftPrefScore += 1;

    let rotationScore = 0;
    if (willingToRotate) {
        rotationScore += 1;
        if (category === 'night' && willingDN) rotationScore += 2;
        else if (category === 'day' && willingND) rotationScore += 2;
        else if (category === 'night' && prefersNight) rotationScore += 1;
        else if (category === 'day' && prefersDay) rotationScore += 1;
    }

    const el = id => document.getElementById(id);
    if (el('score-seniority')) el('score-seniority').textContent = '+' + seniorityScore.toFixed(1);
    if (el('score-performance')) el('score-performance').textContent = '+' + performanceScore.toFixed(1);
    if (el('score-shift-preference')) el('score-shift-preference').textContent = '+' + shiftPrefScore.toFixed(1);
    if (el('score-rotation')) el('score-rotation').textContent = '+' + rotationScore.toFixed(1);
    el('score-breakdown')?.classList.remove('hidden');

    let score = 5 + seniorityScore + performanceScore + shiftPrefScore + rotationScore;
    score = Math.max(1, Math.min(10, score));

    if (el('preference-score-bar')) el('preference-score-bar').style.width = `${(score / 10) * 100}%`;
    if (el('preference-score-value')) el('preference-score-value').textContent = `${score.toFixed(1)}/10`;
    if (el('preference-score-text')) {
        el('preference-score-text').textContent = score >= 8
            ? 'High match - Personnel strongly prefers this assignment'
            : score >= 5 ? 'Medium match - Personnel is neutral about this assignment'
            : 'Low match - Personnel may not prefer this assignment';
    }
    if (el('preference-score-bar')) el('preference-score-bar').style.backgroundColor = score >= 8 ? 'var(--success)' : score >= 5 ? 'var(--warning)' : 'var(--danger)';

    // FIX: clamp to integer 1..10
    const rounded = Math.max(1, Math.min(10, Math.round(score)));
    if (el('rotation_preference_score')) el('rotation_preference_score').value = String(rounded);
    el('rotation-score-container')?.classList.remove('hidden');
}

async function checkPersonnelAvailability() {
    const userId = document.getElementById('security_user_id')?.value;
    const date = document.getElementById('assignment_date')?.value;
    const shiftSel = document.getElementById('security_shift_id');
    const shiftId = shiftSel?.value;
    const checker = document.getElementById('availability-checker');
    if (!userId || !date) { checker?.classList.add('hidden'); return; }

    try {
        checker?.classList.remove('hidden');
        document.getElementById('availability-badge').textContent = 'Checking...';
        document.getElementById('availability-details').textContent = 'Verifying...';

        const res = await fetch(`${ROUTES.checkAvailability}?user_id=${userId}&date=${date}`);
        const data = await res.json();
        let available = true;
        const avail = document.getElementById('personnel-availability');
        const availDetail = document.getElementById('personnel-availability-detail');
        const badge = document.getElementById('availability-badge');
        const details = document.getElementById('availability-details');

        if (data.has_assignment) {
            available = false;
            avail.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            avail.style.borderLeftColor = 'var(--danger)';
            const icon = avail.querySelector('i'); if (icon) icon.style.color = 'var(--danger)';
            const span = avail.querySelector('span'); if (span) { span.textContent = 'Unavailable'; span.style.color = 'var(--danger)'; }
            if (availDetail) availDetail.textContent = 'Already assigned on this date.';
            badge.textContent = 'Unavailable';
            badge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            badge.style.color = 'var(--danger)';
            if (details) details.textContent = 'Personnel already has an assignment on this date.';
        } else {
            avail.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            avail.style.borderLeftColor = 'var(--success)';
            const icon = avail.querySelector('i'); if (icon) icon.style.color = 'var(--success)';
            const span = avail.querySelector('span'); if (span) { span.textContent = 'Available'; span.style.color = 'var(--success)'; }
            if (availDetail) availDetail.textContent = 'No conflicting assignments.';
            badge.textContent = 'Available';
            badge.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            badge.style.color = 'var(--success)';
            if (details) details.textContent = 'Personnel is available for this date.';
        }

        if (shiftId && shiftSel.options[shiftSel.selectedIndex]) {
            const shiftDur = parseFloat(shiftSel.options[shiftSel.selectedIndex].dataset.duration || '0');
            if (data.last_shift_date) {
                const hours = (new Date(date + 'T12:00:00') - new Date(data.last_shift_date + 'T12:00:00')) / (1000 * 60 * 60);
                const rw = document.getElementById('rest-period-warning');
                const rt = document.getElementById('rest-period-text');
                if (hours < 12) { rw.classList.remove('hidden'); rt.textContent = `Only ${Math.round(hours)} hours since last shift (min 12h).`; available = false; }
                else rw.classList.add('hidden');
            }
            if (data.weekly_hours !== undefined) {
                const wk = data.weekly_hours || 0;
                const ww = document.getElementById('weekly-hours-warning');
                const wt = document.getElementById('weekly-hours-text');
                if ((wk + shiftDur) > 60) { ww.classList.remove('hidden'); wt.textContent = `Current: ${wk}h, Adding: ${shiftDur}h, Max: 60h.`; available = false; }
                else ww.classList.add('hidden');
            }
        }

        if (!available) { badge.textContent = 'Constraints Detected'; badge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)'; badge.style.color = 'var(--warning)'; }
    } catch (e) { console.error('availability:', e); document.getElementById('availability-details').textContent = 'Could not verify availability.'; }
}

// Summary
function updateAssignmentSummary() {
    const post = document.getElementById('security_post_id');
    const shift = document.getElementById('security_shift_id');
    const personnel = document.getElementById('security_user_id');
    const date = document.getElementById('assignment_date');
    const includeBreaks = document.getElementById('include_breaks');
    const summary = document.getElementById('assignment-summary');

    if (!post?.value || !shift?.value || !personnel?.value || !date?.value) { summary?.classList.add('hidden'); return; }

    const opt = shift.options[shift.selectedIndex];
    const el = id => document.getElementById(id);
    if (el('summary-post')) el('summary-post').textContent = post.options[post.selectedIndex].text.split('-')[0].trim();
    if (el('summary-datetime')) el('summary-datetime').textContent = `${new Date(date.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${opt.text.split('(')[0].trim()}`;
    if (el('summary-personnel')) el('summary-personnel').textContent = personnel.options[personnel.selectedIndex].text.split('-')[0].trim();
    if (el('summary-duration')) el('summary-duration').textContent = `${opt.dataset.duration || '0'} hours`;

    if (opt.dataset.rotationType === 'rotating') { el('rotation-summary')?.classList.remove('hidden'); if (el('rotation-summary-text')) el('rotation-summary-text').textContent = 'Part of rotating shift schedule'; }
    else el('rotation-summary')?.classList.add('hidden');

    if (includeBreaks?.checked && opt.dataset.hasBreaks === 'true') el('break-summary')?.classList.remove('hidden');
    else el('break-summary')?.classList.add('hidden');

    updateTimelinePreview(opt, date.value);
    summary?.classList.remove('hidden');
    validateForm();
}

function updateTimelinePreview(opt, dateVal) {
    const start = opt.dataset.startTime?.substring(0, 5) || '00:00';
    const end = opt.dataset.endTime?.substring(0, 5) || '00:00';
    const el = id => document.getElementById(id);
    if (el('timeline-start')) el('timeline-start').textContent = start;
    if (el('timeline-end')) el('timeline-end').textContent = end;

    const now = new Date();
    const nowDec = now.getHours() + now.getMinutes() / 60;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const startDec = sh + sm / 60;
    let endDec = eh + em / 60;
    if (endDec < startDec) endDec += 24;

    let progress = 0, status = '';
    if (dateVal === now.toISOString().split('T')[0]) {
        if (nowDec < startDec) { progress = 0; status = 'Not started'; }
        else if (nowDec > endDec) { progress = 100; status = 'Ended'; }
        else { progress = ((nowDec - startDec) / (endDec - startDec)) * 100; status = 'In progress'; }
    } else if (new Date(dateVal) > now) status = 'Scheduled';
    else status = 'Past';

    if (el('shift-timeline-status')) el('shift-timeline-status').textContent = status;
    if (el('timeline-progress')) el('timeline-progress').style.width = `${Math.min(progress, 100)}%`;
}

// Validation
function validateForm() {
    const post = document.getElementById('security_post_id');
    const shift = document.getElementById('security_shift_id');
    const personnel = document.getElementById('security_user_id');
    const date = document.getElementById('assignment_date');
    let valid = true;
    const warnings = [];

    if (!post?.value) valid = false, warnings.push('Security Post is required');
    if (!shift?.value) valid = false, warnings.push('Security Shift is required');
    if (!personnel?.value) valid = false, warnings.push('Security Personnel is required');
    if (!date?.value) valid = false, warnings.push('Assignment Date is required');

    const maxEl = document.getElementById('max-personnel');
    if (post?.value && date?.value && maxEl) {
        const max = parseInt(maxEl.textContent || '0');
        if (currentCapacity >= max && max > 0) { valid = false; warnings.push('Post is at full capacity'); }
    }

    if (shift?.value && date?.value) {
        const days = JSON.parse(shift.options[shift.selectedIndex].dataset.applicableDays || '[]');
        if (days.length > 0 && !days.includes(getDayOfWeekIso(date.value))) { valid = false; warnings.push('Shift not applicable on this day'); }
    }

    if (!document.getElementById('rest-period-warning')?.classList.contains('hidden')) { valid = false; warnings.push('Insufficient rest period'); }
    if (!document.getElementById('weekly-hours-warning')?.classList.contains('hidden')) { valid = false; warnings.push('Weekly hour limit exceeded'); }

    if (isBreakOverridden) {
        const r = document.getElementById('break_override_reason');
        if (!r || !r.value.trim()) { valid = false; warnings.push('Override reason required'); }
    }

    const box = document.getElementById('submit-warnings');
    const text = document.getElementById('submit-warning-text');
    const btn = document.getElementById('submit-button');
    const status = document.getElementById('form-status');

    if (warnings.length > 0) {
        box?.classList.remove('hidden');
        if (text) text.textContent = warnings.join('. ') + '.';
        if (btn) {
            btn.disabled = !valid;
            btn.classList.toggle('opacity-50', !valid);
            btn.classList.toggle('cursor-not-allowed', !valid);
        }
        if (status) status.innerHTML = valid
            ? `<div class="flex items-center text-yellow-600"><i class="fas fa-exclamation-triangle mr-2"></i><span>Ready to submit (warnings only)</span></div>`
            : `<div class="flex items-center text-red-600"><i class="fas fa-exclamation-circle mr-2"></i><span>Fix validation errors</span></div>`;
    } else {
        box?.classList.add('hidden');
        if (btn) { btn.disabled = false; btn.classList.remove('opacity-50', 'cursor-not-allowed'); }
        if (status) status.innerHTML = `<div class="flex items-center text-green-600"><i class="fas fa-check-circle mr-2"></i><span>Ready to submit</span></div>`;
    }

    isFormValid = valid;
    return valid;
}

// Bulk
window.showBulkAssignModal = function () {
    updateBulkShiftDefaultIndicators();
    updateWeeklyDaysFromShift();
    updateBulkAssignSummary();
    openModal('bulkAssignModal');
};

function updateWeeklyDaysFromShift() {
    const sel = document.getElementById('bulk_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const days = JSON.parse(sel.options[sel.selectedIndex].dataset.applicableDays || '[]');
    if (days.length === 0) return;
    document.querySelectorAll('input[name="recurrence_days[]"]').forEach(cb => cb.checked = false);
    days.forEach(d => {
        const cb = document.querySelector(`input[name="recurrence_days[]"][value="${d}"]`);
        if (cb) cb.checked = true;
    });
}

function updateBulkShiftDefaultIndicators() {
    const sel = document.getElementById('bulk_shift_id');
    if (!sel || !sel.options[sel.selectedIndex]) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    const b = document.getElementById('bulk_include_breaks');
    const h = document.getElementById('bulk_include_handover');
    if (b && !b.dataset.userModified) b.checked = opt.dataset.hasBreaks === 'true';
    if (h && !h.dataset.userModified) h.checked = opt.dataset.hasHandover === 'true';
    checkBulkOverrides(opt);
}

function checkBulkOverrides(opt) {
    if (!opt) return;
    const b = document.getElementById('bulk_include_breaks');
    const h = document.getElementById('bulk_include_handover');
    const reasonBox = document.getElementById('bulk-override-reason-container');
    if (!b || !h) return;
    const bWarn = document.getElementById('bulk-break-override-warning');
    const hWarn = document.getElementById('bulk-handover-override-warning');
    let has = false;
    if (b.checked !== (opt.dataset.hasBreaks === 'true')) { bWarn?.classList.remove('hidden'); has = true; } else bWarn?.classList.add('hidden');
    if (h.checked !== (opt.dataset.hasHandover === 'true')) { hWarn?.classList.remove('hidden'); has = true; } else hWarn?.classList.add('hidden');
    reasonBox?.classList.toggle('hidden', !has);
    const reason = document.getElementById('bulk_override_reason');
    if (reason) has ? reason.setAttribute('required', 'required') : (reason.removeAttribute('required'), reason.value = '');
}

function updateBulkAssignSummary() {
    const u = document.getElementById('bulk_user_ids');
    const s = document.getElementById('startDate');
    const e = document.getElementById('endDate');
    const r = document.getElementById('recurrenceType');
    const box = document.getElementById('bulkAssignSummary');
    if (!u || !s || !e || !r) return;
    const n = u.selectedOptions.length;
    if (n > 0 && s.value && e.value) {
        const el = id => document.getElementById(id);
        if (el('summaryPersonnelCount')) el('summaryPersonnelCount').textContent = n;
        if (el('summaryDateRange')) el('summaryDateRange').textContent = `${s.value} to ${e.value}`;
        if (el('summaryRecurrence')) el('summaryRecurrence').textContent = r.options[r.selectedIndex].text;
        const pattern = document.querySelector('input[name="rotation_pattern"]:checked');
        if (el('summaryRotationPattern')) el('summaryRotationPattern').textContent = (pattern && r.value === 'rotating') ? pattern.value : 'None';
        const days = Math.ceil((new Date(e.value) - new Date(s.value)) / (1000 * 60 * 60 * 24)) + 1;
        let mult = 1;
        if (r.value === 'weekly') mult = document.querySelectorAll('input[name="recurrence_days[]"]:checked').length / 7;
        else if (r.value === 'monthly') mult = 0.5;
        if (el('summaryTotalAssignments')) el('summaryTotalAssignments').textContent = Math.ceil(n * days * mult);
        box?.classList.remove('hidden');
    } else box?.classList.add('hidden');
}

function buildBulkPayload() {
    const form = document.getElementById('bulkAssignForm');
    const fd = new FormData(form);
    const data = {
        _token: fd.get('_token'),
        security_post_id: fd.get('security_post_id'),
        security_shift_id: fd.get('security_shift_id'),
        security_user_ids: fd.getAll('security_user_ids[]'),
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
    if (fd.get('rotation_pattern')) data.rotation_pattern = fd.get('rotation_pattern');
    if (fd.get('create_rotation_group') === 'on') {
        data.create_rotation_group = true;
        data.rotation_group_name = fd.get('rotation_group_name') || 'Auto Group';
    }
    return data;
}

window.previewBulkAssign = function () {
    const form = document.getElementById('bulkAssignForm');
    if (!form) return;
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const payload = buildBulkPayload();
    if (!payload.security_user_ids.length) { showToast('Select at least one personnel', 'warning'); return; }

    const content = document.getElementById('previewContent');
    if (content) content.innerHTML = `<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i><p class="mt-2" style="color: var(--text-secondary);">Generating preview...</p></div>`;
    openModal('bulkAssignPreviewModal');

    fetch(ROUTES.bulkPreview, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    })
    .then(async r => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');
        const json = await r.json();
        if (!r.ok) throw new Error(json.message || `Server error ${r.status}`);
        return json;
    })
    .then(d => {
        if (d.success) {
            if (content) content.innerHTML = `<pre class="text-xs overflow-auto p-3" style="background-color: var(--bg-secondary); color: var(--text-primary);">${JSON.stringify(d, null, 2)}</pre>`;
        } else { showToast(d.message || 'Preview failed', 'error'); closeModal('bulkAssignPreviewModal'); }
    })
    .catch(e => { console.error(e); showToast('Preview error: ' + e.message, 'error'); closeModal('bulkAssignPreviewModal'); });
};

window.submitBulkAssignForm = function () {
    const form = document.getElementById('bulkAssignForm');
    if (!form) return;
    const payload = buildBulkPayload();
    if (!payload.security_user_ids.length) { showToast('Select at least one personnel', 'warning'); return; }
    closeModal('bulkAssignPreviewModal');

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
        body: JSON.stringify(payload),
    })
    .then(async r => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');
        const json = await r.json();
        if (!r.ok) throw new Error(json.message || `Server error ${r.status}`);
        return json;
    })
    .then(d => {
        hideLoading();
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        if (d.success) {
            showToast(`✅ ${d.created_count} schedule(s) created successfully!`, 'success');
            closeModal('bulkAssignModal');
            setTimeout(() => window.location.href = ROUTES.index, 1500);
        } else showToast('❌ ' + (d.message || 'Failed'), 'error');
    })
    .catch(e => {
        hideLoading();
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        console.error(e);
        showToast('❌ ' + e.message, 'error');
    });
};

// Modals + toast
window.openModal = function (id) { const m = document.getElementById(id); if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; } };
window.closeModal = function (id) { const m = document.getElementById(id); if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; } };
window.showToast = function (msg, type = 'info') {
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
    t.querySelector('span').textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.classList.remove('translate-x-full'), 10);
    setTimeout(() => { t.classList.add('translate-x-full'); setTimeout(() => t.remove(), 300); }, 5000);
};
window.viewRotationHistoryForCurrent = function () {
    const id = document.getElementById('rotation_group_id')?.value;
    if (!id) { showToast('No rotation group', 'info'); return; }
    const content = document.getElementById('rotationHistoryContent');
    if (content) content.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);"><i class="fas fa-spinner fa-spin mr-2"></i> Loading...</p>';
    openModal('rotation-history-modal');
    fetch(ROUTES.rotationHistory(id))
        .then(r => r.json())
        .then(d => {
            if (d.success && d.history?.length) {
                content.innerHTML = d.history.map(e => `
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
                        <div class="flex justify-between"><span class="font-medium" style="color: var(--text-primary);">${e.date}</span><span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${e.type}</span></div>
                        <p class="text-sm mt-1" style="color: var(--text-primary);">From: ${e.from} → To: ${e.to}</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">${e.reason || ''}</p>
                    </div>`).join('');
            } else content.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);">No history.</p>';
        })
        .catch(() => { content.innerHTML = '<p class="text-center py-4" style="color: var(--danger);">Failed to load.</p>'; });
};

// DOM Ready
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('security-schedule-form');
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const includeBreaksCheckbox = document.getElementById('include_breaks');

    const updateDayOfWeek = () => {
        const el = document.getElementById('day-of-week-display');
        if (el) el.textContent = dateInput?.value
            ? `${getDayOfWeek(dateInput.value)}, ${new Date(dateInput.value).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`
            : 'Select a date';
    };

    postSelect?.addEventListener('change', () => { updatePostDetails(); updatePostCapacity(); updateHandoverInfo(); updateAssignmentSummary(); });
    dateInput?.addEventListener('change', () => { selectedDate = dateInput.value; updateDayOfWeek(); updatePostCapacity(); updateShiftDetails(); updateHandoverInfo(); checkPersonnelAvailability(); updateAssignmentSummary(); });
    shiftSelect?.addEventListener('change', () => { updateShiftDetails(); updateHandoverInfo(); checkPersonnelAvailability(); updateAssignmentSummary(); updateShiftDefaultIndicators(); });
    personnelSelect?.addEventListener('change', () => { updatePersonnelInfo(); checkPersonnelAvailability(); updateAssignmentSummary(); });
    includeBreaksCheckbox?.addEventListener('change', function () { this.dataset.userModified = 'true'; checkForOverrides(); updateAssignmentSummary(); });
    document.getElementById('break_override_reason')?.addEventListener('input', validateForm);

    // FIX: harden the hidden fields at submit time
    form?.addEventListener('submit', function (e) {
        if (!validateForm()) {
            e.preventDefault();
            document.querySelector('.border-red-500')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const rps = document.getElementById('rotation_preference_score');
        const rsn = document.getElementById('rotation_sequence_number');
        const isr = document.getElementById('is_rotated');
        if (rps)  rps.value  = String(Math.max(1, Math.min(10, Math.round(parseFloat(rps.value)  || 5))));
        if (rsn)  rsn.value  = String(Math.max(0, parseInt(rsn.value, 10) || 0));
        if (isr)  isr.value  = (isr.value === '1' || isr.value === 'true') ? '1' : '0';

        if (isBreakOverridden && overrideAuditLog.length > 0) {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = 'override_audit_log';
            i.value = JSON.stringify({
                timestamp: new Date().toISOString(),
                overrides: overrideAuditLog,
                reason: document.getElementById('break_override_reason')?.value || '',
                user_id: {{ auth()->id() }},
            });
            form.appendChild(i);
        }
    });

    // Bulk form
    const bulkForm = document.getElementById('bulkAssignForm');
    bulkForm?.addEventListener('submit', function (e) { e.preventDefault(); submitBulkAssignForm(); });

    document.getElementById('bulk_user_ids')?.addEventListener('change', updateBulkAssignSummary);
    document.getElementById('bulk_post_id')?.addEventListener('change', updateBulkAssignSummary);
    document.getElementById('bulk_shift_id')?.addEventListener('change', () => { updateBulkShiftDefaultIndicators(); updateWeeklyDaysFromShift(); updateBulkAssignSummary(); });

    const startD = document.getElementById('startDate');
    const endD = document.getElementById('endDate');
    startD?.addEventListener('change', () => { if (endD) { endD.min = startD.value; if (endD.value < startD.value) endD.value = startD.value; } updateBulkAssignSummary(); });
    endD?.addEventListener('change', updateBulkAssignSummary);

    document.getElementById('recurrenceType')?.addEventListener('change', function () {
        const w = document.getElementById('weeklyDaysContainer');
        const m = document.getElementById('monthlyDatesContainer');
        const r = document.getElementById('rotationPatternContainer');
        [w, m, r].forEach(x => x?.classList.add('hidden'));
        if (this.value === 'weekly') w?.classList.remove('hidden');
        if (this.value === 'monthly') m?.classList.remove('hidden');
        if (this.value === 'rotating') r?.classList.remove('hidden');
        updateBulkAssignSummary();
    });

    document.getElementById('bulk_include_breaks')?.addEventListener('change', function () { this.dataset.userModified = 'true'; checkBulkOverrides(shiftSelect?.options[shiftSelect.selectedIndex]); });
    document.getElementById('bulk_include_handover')?.addEventListener('change', function () { this.dataset.userModified = 'true'; checkBulkOverrides(shiftSelect?.options[shiftSelect.selectedIndex]); });

    document.querySelectorAll('input[name="rotation_pattern"]').forEach(r => r.addEventListener('change', updateBulkAssignSummary));
    document.querySelectorAll('input[name="recurrence_days[]"]').forEach(c => c.addEventListener('change', updateBulkAssignSummary));

    document.getElementById('create_rotation_group')?.addEventListener('change', function () {
        document.getElementById('rotationGroupNameContainer')?.classList.toggle('hidden', !this.checked);
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal-overlay')) {
            const m = e.target.closest('.modal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        }
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden')); });

    if (dateInput && !dateInput.value) { dateInput.value = new Date().toISOString().split('T')[0]; selectedDate = dateInput.value; }
    updateDayOfWeek();
    if (postSelect?.value) { updatePostDetails(); updatePostCapacity(); }
    if (shiftSelect?.value) { updateShiftDetails(); updateHandoverInfo(); updateShiftDefaultIndicators(); }
    if (personnelSelect?.value) { updatePersonnelInfo(); checkPersonnelAvailability(); }
    if (postSelect?.value && shiftSelect?.value && personnelSelect?.value && dateInput?.value) updateAssignmentSummary();
    setInterval(() => { if (postSelect?.value && dateInput?.value) updatePostCapacity(); }, 30000);
});
</script>

<style>
.card { transition: transform 0.2s ease, box-shadow 0.2s ease; background-color: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 0.5rem; overflow: hidden; }
.card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); }
input, select, textarea { transition: border-color 0.15s ease, box-shadow 0.15s ease; background-color: var(--bg-secondary, #f9fafb); border: 1px solid var(--border-color, #d1d5db); border-radius: 0.375rem; padding: 0.5rem 0.75rem; }
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary, #3b82f6); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.btn-primary { background-color: var(--primary, #3b82f6); color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; transition: all 0.2s ease; border: none; cursor: pointer; }
.btn-primary:hover { background-color: var(--primary-dark, #2563eb); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.btn-secondary { background-color: var(--bg-tertiary, #f3f4f6); color: var(--text-primary, #1f2937); padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; border: 1px solid var(--border-color, #d1d5db); transition: all 0.2s ease; }
.btn-secondary:hover { background-color: var(--bg-secondary, #e5e7eb); text-decoration: none; }
.btn-ghost { background-color: transparent; color: var(--text-primary, #1f2937); padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; border: 1px solid transparent; transition: all 0.2s ease; display: inline-flex; align-items: center; text-decoration: none; cursor: pointer; }
.btn-ghost:hover { background-color: rgba(var(--primary-rgb, 59, 130, 246), 0.08); border-color: rgba(var(--primary-rgb, 59, 130, 246), 0.2); color: var(--primary, #3b82f6); text-decoration: none; }
.modal { position: fixed; inset: 0; z-index: 9999; }
.modal-overlay { position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); }
.modal-container { position: relative; background-color: var(--card-bg); border-radius: 16px; margin: 2rem auto; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color); animation: modalFadeIn 0.3s ease-out; }
.modal-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 6px; transition: all 0.2s ease; }
.modal-close:hover { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background-color: var(--bg-secondary); border-radius: 0 0 16px 16px; }
@keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.peer:checked ~ .peer-checked\:bg-info { background-color: var(--info, #3b82f6) !important; }
@keyframes pulseWarning { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }
#capacity-warning:not(.hidden), #applicability-warning:not(.hidden), #rest-period-warning:not(.hidden), #weekly-hours-warning:not(.hidden), #break-override-warning:not(.hidden), #submit-warnings:not(.hidden) { animation: pulseWarning 2s infinite; }
@media (max-width: 768px) { .grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr; } .modal-container { margin: 1rem; } .btn-primary, .btn-secondary, .btn-ghost { width: 100%; justify-content: center; } }
.hidden { display: none !important; }
.text-xxs { font-size: 0.65rem; line-height: 1rem; }
</style>
@endsection