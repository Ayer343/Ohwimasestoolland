@extends('layouts.app')

@section('title', 'Create Security Schedule')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card with Status Indicators -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center space-x-3">
                <i class="fas fa-shield-alt text-2xl" style="color: var(--primary);"></i>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create Security Schedule</h2>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Post Status Indicators -->
                <div class="flex items-center space-x-2">
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        Posts: {{ $securityPosts->count() }}
                    </div>
                    
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-clock mr-1"></i>
                        Shifts: {{ $securityShifts->count() }}
                    </div>
                    
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-users mr-1"></i>
                        Personnel: {{ $securityPersonnel->count() }}
                    </div>
                </div>
                
                <!-- Bulk Assign Button -->
                <button type="button" 
                        onclick="showBulkAssignModal()" 
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

    <!-- Main Form -->
    <form action="{{ route('admin.security-schedules.store') }}" method="POST" id="security-schedule-form" enctype="multipart/form-data">
        @csrf
        
        <div class="grid grid-cols-1 gap-6">
            <!-- Validation Errors -->
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

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- LEFT COLUMN: Security Post & Assignment -->
                <div class="space-y-6">
                    <!-- Security Post Card -->
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
                            <!-- Post Selection with Capacity Display -->
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
                                
                                <!-- Post Capacity & Status Indicator -->
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
                                
                                <!-- Quick Stats for Selected Post -->
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
                                
                                <!-- Post Equipment (Conditional) -->
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
                    
                    <!-- Assignment Date & Time Card -->
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
                            <!-- Date Picker with Day Information -->
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
                                
                                <!-- Day Information -->
                                <div id="day-info" class="mt-2 flex items-center text-sm">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                    <span style="color: var(--text-secondary);" id="day-of-week-display"></span>
                                </div>
                                
                                @error('assignment_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <!-- Shift Selection with Time Display -->
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
                                                data-applicable-days="{{ json_encode($shift->applicable_days ?? [1,2,3,4,5]) }}"
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
                                
                                <!-- Shift Details Panel -->
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
                                    
                                    <!-- Overnight Warning -->
                                    <div id="overnight-warning" class="mt-2 p-2 rounded text-xs hidden"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-moon mr-1"></i>
                                        <span>This is an overnight shift ending the next day.</span>
                                    </div>
                                    
                                    <!-- Applicability Warning -->
                                    <div id="applicability-warning" class="mt-2 p-2 rounded text-xs hidden"
                                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <span id="applicability-warning-text"></span>
                                    </div>
                                    
                                    <!-- Break Information -->
                                    <div id="break-info" class="mt-2 p-2 rounded text-xs hidden"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <div class="flex items-center mb-1">
                                            <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                                            <span class="font-medium" style="color: var(--text-primary);">Break Schedule:</span>
                                        </div>
                                        <div id="break-list" class="ml-6 space-y-1"></div>
                                    </div>
                                </div>
                                
                                <!-- Handover Information Preview -->
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
                            
                            <!-- Include Breaks Toggle -->
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
                
                <!-- RIGHT COLUMN: Personnel & Assignment Details -->
                <div class="space-y-6">
                    <!-- Security Personnel Card -->
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
                            <!-- Personnel Selection with Availability Status -->
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
                                
                                <!-- Personnel Status & Availability -->
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
                                
                                <!-- Rest Period Warning -->
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
                                
                                <!-- Weekly Hours Warning -->
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
                                
                                <!-- Rotation Preference Indicator -->
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
                                
                                <!-- Quick Availability Checker -->
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
                                
                                <!-- Seniority & Performance Indicators -->
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
                    
                    <!-- Assignment Details Card -->
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
                            <!-- Emergency Contact -->
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
                            
                            <!-- Special Instructions -->
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
                            
                            <!-- Notes -->
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
                    
                    <!-- Rotation & Preference Score -->
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
                        
                        <!-- Score Breakdown -->
                        <div id="score-breakdown" class="mt-2 text-xs hidden" style="color: var(--text-secondary);">
                            <div class="flex justify-between">
                                <span>Seniority:</span>
                                <span id="score-seniority">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Performance:</span>
                                <span id="score-performance">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Shift Preference:</span>
                                <span id="score-shift-preference">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Rotation Willingness:</span>
                                <span id="score-rotation">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ROTATION & ADVANCED OPTIONS -->
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
                
                <!-- Rotation History Link -->
                <div class="mt-3 text-right">
                    <button type="button" onclick="viewRotationHistoryForCurrent()" class="text-xs inline-flex items-center" style="color: var(--info);">
                        <i class="fas fa-history mr-1"></i> View Rotation History
                    </button>
                </div>
                
                <!-- Rotation Hidden Fields -->
                <input type="hidden" name="rotation_group_id" id="rotation_group_id" value="">
                <input type="hidden" name="rotation_group_type" id="rotation_group_type" value="">
                <input type="hidden" name="rotation_sequence_number" id="rotation_sequence_number" value="0">
                <input type="hidden" name="rotation_preference_score" id="rotation_preference_score" value="5">
                <input type="hidden" name="is_rotated" id="is_rotated" value="0">
            </div>
            
            <!-- ASSIGNMENT SUMMARY CARD -->
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
                
                <!-- Rotation Summary -->
                <div id="rotation-summary" class="mt-3 p-3 rounded hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-sync-alt mr-2" style="color: var(--info);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Rotation Info:</span>
                        <span class="text-sm ml-2" id="rotation-summary-text" style="color: var(--text-secondary);">Fixed shift</span>
                    </div>
                </div>
                
                <!-- Shift Timeline Preview -->
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
                        <div class="flex justify-between mt-1">
                            <span class="text-xxs" style="color: var(--text-secondary);">Check-in</span>
                            <span class="text-xxs" style="color: var(--text-secondary);">Handover</span>
                            <span class="text-xxs" style="color: var(--text-secondary);">Check-out</span>
                        </div>
                    </div>
                </div>
                
                <!-- Break Summary -->
                <div id="break-summary" class="mt-3 p-3 rounded hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center">
                        <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Break Schedule:</span>
                        <span class="text-sm ml-2" id="break-summary-text" style="color: var(--text-secondary);">No breaks</span>
                    </div>
                </div>
            </div>
            
            <!-- FORM ACTIONS -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div id="form-status" class="text-sm" style="color: var(--text-secondary);">
                        <!-- Dynamic form status messages -->
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Create Schedule
                        </button>
                        <a href="{{ route('admin.security-schedules.index') }}" class="btn-secondary">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                </div>
                
                <!-- Submission Warnings -->
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

<!-- ============================================= -->
<!-- BULK ASSIGN MODAL - FIXED WITH WEEKEND SUPPORT -->
<!-- ============================================= -->
<div id="bulkAssignModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkAssignModal')"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">Bulk Assign Personnel</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkAssignModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="bulkAssignForm" method="POST" action="{{ route('admin.security-schedules.bulk-assign') }}">
            @csrf
            <div class="modal-body">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i> Security Post <span class="text-red-500">*</span>
                        </label>
                        <select name="security_post_id" 
                                id="bulk_post_id"
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                required>
                            <option value="">Select Post</option>
                            @foreach($securityPosts as $post)
                                <option value="{{ $post->id }}" 
                                        data-max="{{ $post->max_personnel }}"
                                        data-type="{{ $post->type }}"
                                        data-location="{{ $post->location }}">
                                    {{ $post->name }} ({{ $post->code }}) - Max: {{ $post->max_personnel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Shift <span class="text-red-500">*</span>
                        </label>
                        <select name="security_shift_id" 
                                id="bulk_shift_id"
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                required>
                            <option value="">Select Shift</option>
                            @foreach($securityShifts as $shift)
                                <option value="{{ $shift->id }}" 
                                        data-category="{{ $shift->category }}"
                                        data-rotation="{{ $shift->rotation_type }}"
                                        data-duration="{{ $shift->duration_hours }}"
                                        data-overnight="{{ $shift->is_overnight ? 'yes' : 'no' }}"
                                        data-applicable-days="{{ json_encode($shift->applicable_days ?? []) }}">
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                    @if($shift->category) - {{ ucfirst($shift->category) }} @endif
                                    @if($shift->rotation_type === 'rotating') 🔄 @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Personnel Selection -->
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-1" style="color: var(--primary);"></i> Security Personnel <span class="text-red-500">*</span>
                    </label>
                    <select name="security_user_ids[]" 
                            id="bulk_user_ids"
                            class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                            multiple size="6" required>
                        @foreach($securityPersonnel as $person)
                            @php
                                $joinedDate = $person->created_at ? $person->created_at->format('Y-m-d') : '';
                            @endphp
                            <option value="{{ $person->id }}" 
                                    data-preferences="{{ json_encode($person->preferences ?? []) }}"
                                    data-badge="{{ $person->badge_number ?? '' }}"
                                    data-joined="{{ $joinedDate }}"
                                    data-performance="{{ $person->preferences['performance_rating'] ?? 7 }}">
                                {{ $person->name }} - {{ $person->phone }} 
                                @if($person->badge_number) (Badge: {{ $person->badge_number }}) @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Hold Ctrl/Cmd to select multiple personnel. Selected: <span id="selectedPersonnelCount">0</span>
                    </p>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-start mr-1" style="color: var(--primary);"></i> Start Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               name="start_date" 
                               id="startDate"
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ now()->format('Y-m-d') }}"
                               min="{{ now()->format('Y-m-d') }}"
                               required>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-end mr-1" style="color: var(--primary);"></i> End Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               name="end_date" 
                               id="endDate"
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ now()->addWeek()->format('Y-m-d') }}"
                               min="{{ now()->format('Y-m-d') }}"
                               required>
                    </div>
                </div>

                <!-- Recurrence Type -->
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-repeat mr-1" style="color: var(--primary);"></i> Recurrence Type <span class="text-red-500">*</span>
                    </label>
                    <select name="recurrence_type" 
                            id="recurrenceType"
                            class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                            required>
                        <option value="daily">Daily (Every day)</option>
                        <option value="weekly" selected>Weekly (Selected days)</option>
                        <option value="monthly">Monthly (Selected dates)</option>
                        <option value="custom">Custom (Manual selection)</option>
                        <option value="rotating">Rotating Pattern (Advanced)</option>
                    </select>
                </div>

                <!-- Weekly Days Selection - FIXED: Now includes all days checked by default -->
                <div id="weeklyDaysContainer" class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-week mr-1" style="color: var(--primary);"></i> Select Days of Week
                    </label>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="1" class="mr-2" checked> Monday
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="2" class="mr-2" checked> Tuesday
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="3" class="mr-2" checked> Wednesday
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="4" class="mr-2" checked> Thursday
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="5" class="mr-2" checked> Friday
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="6" class="mr-2" checked> Saturday
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="recurrence_days[]" value="7" class="mr-2" checked> Sunday
                        </label>
                    </div>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Select the days when assignments should be created
                    </p>
                </div>

                <!-- Monthly Dates Selection -->
                <div id="monthlyDatesContainer" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Select Dates of Month
                    </label>
                    <select name="recurrence_days[]" class="form-input w-full p-3 rounded-lg border" multiple size="5">
                        @for($i = 1; $i <= 31; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Select the dates (1-31) when assignments should be created
                    </p>
                </div>

                <!-- Rotation Pattern Section -->
                <div id="rotationPatternContainer" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--warning-rgb), 0.05);">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Pattern
                    </label>
                    <div class="space-y-3">
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="full_swap" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">Full Swap</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Complete rotation: All day personnel swap with night personnel (Weekly rotation)</p>
                            </div>
                        </label>
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="staggered" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">Staggered</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">50% rotation: Rotate half of the personnel each cycle (3-day rotation)</p>
                            </div>
                        </label>
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="partial" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">Partial</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Preference-based: Only rotate personnel who have opted in (Weekly rotation)</p>
                            </div>
                        </label>
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="sequential" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">Sequential</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Simple sequential rotation through all personnel (Daily rotation)</p>
                            </div>
                        </label>
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="alternating" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">Alternating</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Alternate between top and bottom performers (Weekly rotation)</p>
                            </div>
                        </label>
                        <label class="flex items-center p-2 border rounded-lg" style="border-color: var(--border-color);">
                            <input type="radio" name="rotation_pattern" value="preference_based" class="mr-3">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">Preference-Based</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Always assign highest preference scores first (Weekly rotation)</p>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Rotation Group Creation Option -->
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <label class="flex items-center">
                            <input type="checkbox" name="create_rotation_group" id="create_rotation_group" value="1" class="mr-2">
                            <span style="color: var(--text-primary);">Create new rotation group</span>
                        </label>
                        <div id="rotationGroupNameContainer" class="mt-2 hidden">
                            <input type="text" 
                                   name="rotation_group_name" 
                                   id="rotation_group_name"
                                   class="form-input w-full p-2 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Enter rotation group name (e.g., 'Alpha Team - Night Shift')">
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Creates a permanent rotation group that can be used for future rotations
                        </p>
                    </div>
                </div>

                <!-- Advanced Options -->
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-cog mr-2"></i> Advanced Options
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <label class="flex items-center">
                                <input type="checkbox" name="maintain_coverage" value="1" class="mr-2" checked>
                                <span style="color: var(--text-primary);">Maintain Coverage</span>
                            </label>
                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                                Ensure all posts are fully staffed
                            </p>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <label class="flex items-center">
                                <input type="checkbox" name="respect_preferences" value="1" class="mr-2" checked>
                                <span style="color: var(--text-primary);">Respect Preferences</span>
                            </label>
                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                                Use personnel preferences for assignments
                            </p>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_personnel" value="1" class="mr-2" checked>
                                <span style="color: var(--text-primary);">Notify Personnel</span>
                            </label>
                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                                Send notifications to assigned personnel
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Additional Options -->
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_breaks" value="1" class="mr-2" checked>
                            <span style="color: var(--text-primary);">Include Break Schedules</span>
                        </label>
                        <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                            Automatically assign breaks based on shift configuration
                        </p>
                    </div>
                    
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_handover" value="1" class="mr-2">
                            <span style="color: var(--text-primary);">Include Handover Procedures</span>
                        </label>
                        <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                            Calculate handover times and generate checklists
                        </p>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-1" style="color: var(--primary);"></i> Notes
                    </label>
                    <textarea name="notes" 
                              class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              rows="2"
                              maxlength="500"
                              placeholder="General notes for all assigned schedules"></textarea>
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-phone-alt mr-1" style="color: var(--primary);"></i> Emergency Contact
                    </label>
                    <input type="text" 
                           name="emergency_contact" 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                           maxlength="255"
                           placeholder="Emergency contact number for all assignments">
                </div>

                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-1" style="color: var(--primary);"></i> Special Instructions
                    </label>
                    <textarea name="special_instructions" 
                              class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              rows="2"
                              maxlength="1000"
                              placeholder="Special instructions for all assigned personnel"></textarea>
                </div>

                <!-- Summary Section (Dynamic) -->
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
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('bulkAssignModal')">
                    Cancel
                </button>
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                        onclick="previewBulkAssign()">
                    <i class="fas fa-eye mr-2"></i> Preview
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary"
                        id="bulkAssignSubmitBtn">
                    <i class="fas fa-users mr-2"></i> Assign Personnel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Assign Preview Modal -->
<div id="bulkAssignPreviewModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkAssignPreviewModal')"></div>
    <div class="modal-container" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Preview Bulk Assignment</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkAssignPreviewModal')">
                <i class="fas fa-times"></i>
            </button>
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
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('bulkAssignPreviewModal')">
                Cancel
            </button>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary"
                    onclick="submitBulkAssignForm()">
                <i class="fas fa-check mr-2"></i> Confirm & Submit
            </button>
        </div>
    </div>
</div>

<!-- Rotation History Modal -->
<div id="rotation-history-modal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rotation-history-modal')"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Rotation History</h3>
            <button type="button" class="modal-close" onclick="closeModal('rotation-history-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="rotationHistoryContent" class="space-y-3">
                <!-- History items will be inserted here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('rotation-history-modal')">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

@endsection

@section('scripts')
<script>
// =============================================
// GLOBAL VARIABLES & STATE
// =============================================
let currentCapacity = 0;
let selectedDate = document.getElementById('assignment_date')?.value || new Date().toISOString().split('T')[0];
let isFormValid = true;
let originalShiftConfig = {
    hasBreaks: false,
    hasHandover: false,
    handoverDuration: 30,
    handoverNotesRequired: false,
    breakSchedule: {}
};
let isBreakOverridden = false;
let isHandoverOverridden = false;

// Audit log for overrides
let overrideAuditLog = [];

// =============================================
// LOADING STATE FUNCTIONS
// =============================================
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
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

// =============================================
// DAY OF WEEK UTILITIES
// =============================================
const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const dayOfWeekIso = {1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday'};

function getDayOfWeek(dateString) {
    const date = new Date(dateString + 'T12:00:00');
    return daysOfWeek[date.getDay()];
}

function getDayOfWeekIso(dateString) {
    const date = new Date(dateString + 'T12:00:00');
    let day = date.getDay();
    return day === 0 ? 7 : day;
}

// =============================================
// SHIFT DEFAULT INDICATOR FUNCTIONS
// =============================================
function updateShiftDefaultIndicators() {
    const shiftSelect = document.getElementById('security_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    
    if (selectedOption.value) {
        // Get shift defaults
        const shiftHasBreaks = selectedOption.dataset.shiftBreakDefault === 'true';
        const shiftHasHandover = selectedOption.dataset.shiftHandoverDefault === 'true';
        const shiftBreakCount = selectedOption.dataset.breakCount || '0';
        const handoverDuration = selectedOption.dataset.handoverDuration || '30';
        
        // Store original config
        originalShiftConfig = {
            hasBreaks: shiftHasBreaks,
            hasHandover: shiftHasHandover,
            handoverDuration: handoverDuration,
            handoverNotesRequired: selectedOption.dataset.handoverNotesRequired === 'true',
            breakSchedule: JSON.parse(selectedOption.dataset.breakSchedule || '{}'),
            breakCount: shiftBreakCount
        };
        
        // Update break indicator
        const breakIndicator = document.getElementById('shift-break-indicator');
        if (breakIndicator) {
            breakIndicator.textContent = shiftHasBreaks ? 
                `Default: Yes (${shiftBreakCount} breaks)` : 
                'Default: No breaks';
            breakIndicator.style.backgroundColor = shiftHasBreaks ? 
                'rgba(var(--success-rgb), 0.1)' : 
                'rgba(var(--secondary-rgb), 0.1)';
            breakIndicator.style.color = shiftHasBreaks ? 
                'var(--success)' : 
                'var(--text-secondary)';
        }
        
        // Auto-populate include_breaks based on shift default
        const includeBreaksCheckbox = document.getElementById('include_breaks');
        if (includeBreaksCheckbox && !includeBreaksCheckbox.dataset.userModified) {
            includeBreaksCheckbox.checked = shiftHasBreaks;
        }
        
        // Reset override state when shift changes
        resetOverrideState();
        
        // Check if current selection matches default
        checkForOverrides();
    }
}

function resetOverrideState() {
    isBreakOverridden = false;
    isHandoverOverridden = false;
    
    const breakOverrideWarning = document.getElementById('break-override-warning');
    const overrideReasonContainer = document.getElementById('break-override-reason-container');
    const overrideSummary = document.getElementById('override-summary');
    
    if (breakOverrideWarning) breakOverrideWarning.classList.add('hidden');
    if (overrideReasonContainer) overrideReasonContainer.classList.add('hidden');
    if (overrideSummary) overrideSummary.classList.add('hidden');
    
    // Clear override reason
    const overrideReason = document.getElementById('break_override_reason');
    if (overrideReason) overrideReason.value = '';
}

function checkForOverrides() {
    const includeBreaksCheckbox = document.getElementById('include_breaks');
    if (!includeBreaksCheckbox || !originalShiftConfig) return;
    
    const currentBreakValue = includeBreaksCheckbox.checked;
    const defaultBreakValue = originalShiftConfig.hasBreaks;
    
    // Check if break setting is overridden
    if (currentBreakValue !== defaultBreakValue) {
        isBreakOverridden = true;
        showBreakOverrideWarning(currentBreakValue);
    } else {
        isBreakOverridden = false;
        hideBreakOverrideWarning();
    }
    
    // Update override summary
    updateOverrideSummary();
}

function showBreakOverrideWarning(currentValue) {
    const warningEl = document.getElementById('break-override-warning');
    const warningText = document.getElementById('break-override-text');
    const reasonContainer = document.getElementById('break-override-reason-container');
    
    if (warningEl && warningText) {
        const action = currentValue ? 'enabling' : 'disabling';
        const defaultState = originalShiftConfig.hasBreaks ? 'has' : 'does not have';
        
        warningText.innerHTML = `
            You are ${action} breaks for this assignment.<br>
            <strong>Shift default:</strong> This shift ${defaultState} breaks configured.<br>
            <strong>Reason required:</strong> Please explain why you're overriding the shift default.
        `;
        warningEl.classList.remove('hidden');
        
        // Show reason input
        if (reasonContainer) {
            reasonContainer.classList.remove('hidden');
            // Make reason required
            const reasonInput = document.getElementById('break_override_reason');
            if (reasonInput) reasonInput.setAttribute('required', 'required');
        }
        
        // Add to audit log
        overrideAuditLog.push({
            field: 'breaks',
            action: action,
            timestamp: new Date().toISOString(),
            from: originalShiftConfig.hasBreaks,
            to: currentValue
        });
    }
}

function hideBreakOverrideWarning() {
    const warningEl = document.getElementById('break-override-warning');
    const reasonContainer = document.getElementById('break-override-reason-container');
    
    if (warningEl) warningEl.classList.add('hidden');
    if (reasonContainer) {
        reasonContainer.classList.add('hidden');
        const reasonInput = document.getElementById('break_override_reason');
        if (reasonInput) {
            reasonInput.removeAttribute('required');
            reasonInput.value = '';
        }
    }
}

function updateOverrideSummary() {
    const overrideSummary = document.getElementById('override-summary');
    const overrideSummaryText = document.getElementById('override-summary-text');
    
    if (!overrideSummary || !overrideSummaryText) return;
    
    const overrides = [];
    
    if (isBreakOverridden) {
        overrides.push(`Break schedule: ${document.getElementById('include_breaks').checked ? 'Enabled' : 'Disabled'} (shift default: ${originalShiftConfig.hasBreaks ? 'Enabled' : 'Disabled'})`);
    }
    
    // Add handover override check here when implemented
    
    if (overrides.length > 0) {
        overrideSummaryText.innerHTML = overrides.map(o => `<div>• ${o}</div>`).join('');
        overrideSummary.classList.remove('hidden');
    } else {
        overrideSummary.classList.add('hidden');
    }
}

// =============================================
// POST CAPACITY FUNCTIONS
// =============================================
async function updatePostCapacity() {
    const postSelect = document.getElementById('security_post_id');
    const dateInput = document.getElementById('assignment_date');
    const postId = postSelect?.value;
    const date = dateInput?.value;
    
    const postCapacityInfo = document.getElementById('post-capacity-info');
    const postQuickStats = document.getElementById('post-quick-stats');
    const postEquipment = document.getElementById('post-equipment');
    
    if (!postId || !date) {
        if (postCapacityInfo) postCapacityInfo.classList.add('hidden');
        if (postQuickStats) postQuickStats.classList.add('hidden');
        if (postEquipment) postEquipment.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`/admin/security-schedules/post-capacity?post_id=${postId}&date=${date}`);
        const data = await response.json();
        
        if (data.success) {
            currentCapacity = data.currently_assigned || 0;
            const max = data.max_personnel || 0;
            const available = data.available_slots || 0;
            
            const currentAssignments = document.getElementById('current-assignments');
            const maxPersonnel = document.getElementById('max-personnel');
            const capacityProgress = document.getElementById('capacity-progress');
            
            if (currentAssignments) currentAssignments.textContent = currentCapacity;
            if (maxPersonnel) maxPersonnel.textContent = max;
            
            const capacityPercentage = max > 0 ? (currentCapacity / max) * 100 : 0;
            if (capacityProgress) capacityProgress.style.width = `${Math.min(capacityPercentage, 100)}%`;
            
            if (capacityProgress) {
                if (capacityPercentage >= 90) {
                    capacityProgress.style.backgroundColor = 'var(--danger)';
                } else if (capacityPercentage >= 75) {
                    capacityProgress.style.backgroundColor = 'var(--warning)';
                } else {
                    capacityProgress.style.backgroundColor = 'var(--success)';
                }
            }
            
            const capacityWarning = document.getElementById('capacity-warning');
            const capacityWarningText = document.getElementById('capacity-warning-text');
            
            if (capacityWarning && capacityWarningText) {
                if (available <= 0) {
                    capacityWarning.classList.remove('hidden');
                    capacityWarningText.textContent = 'This post is at full capacity for the selected date.';
                    if (capacityProgress) capacityProgress.style.backgroundColor = 'var(--danger)';
                } else if (available < 3) {
                    capacityWarning.classList.remove('hidden');
                    capacityWarningText.textContent = `Only ${available} slot(s) available for this post on the selected date.`;
                    if (capacityProgress) capacityProgress.style.backgroundColor = 'var(--warning)';
                } else {
                    capacityWarning.classList.add('hidden');
                }
            }
            
            if (postCapacityInfo) postCapacityInfo.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Failed to fetch post capacity:', error);
    }
}

function updatePostDetails() {
    const postSelect = document.getElementById('security_post_id');
    if (!postSelect || !postSelect.options[postSelect.selectedIndex]) return;
    
    const selectedOption = postSelect.options[postSelect.selectedIndex];
    
    if (selectedOption.value) {
        const postType = selectedOption.dataset.postType || 'Standard';
        const location = selectedOption.dataset.postLocation || 'N/A';
        const equipment = JSON.parse(selectedOption.dataset.postEquipment || '[]');
        
        const postTypeDisplay = document.getElementById('post-type-display');
        const postLocationDisplay = document.getElementById('post-location-display');
        const postEquipmentList = document.getElementById('post-equipment-list');
        const postEquipment = document.getElementById('post-equipment');
        const postQuickStats = document.getElementById('post-quick-stats');
        
        if (postTypeDisplay) postTypeDisplay.textContent = postType.replace('_', ' ').toUpperCase();
        if (postLocationDisplay) postLocationDisplay.textContent = location;
        
        if (equipment.length > 0 && postEquipmentList && postEquipment) {
            postEquipmentList.innerHTML = equipment.map(item => 
                `<span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${item}</span>`
            ).join('');
            postEquipment.classList.remove('hidden');
        } else if (postEquipment) {
            postEquipment.classList.add('hidden');
        }
        
        if (postQuickStats) postQuickStats.classList.remove('hidden');
    } else {
        const postQuickStats = document.getElementById('post-quick-stats');
        const postEquipment = document.getElementById('post-equipment');
        if (postQuickStats) postQuickStats.classList.add('hidden');
        if (postEquipment) postEquipment.classList.add('hidden');
    }
}

// =============================================
// SHIFT FUNCTIONS
// =============================================
function updateShiftDetails() {
    const shiftSelect = document.getElementById('security_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    
    if (selectedOption.value) {
        const startTime = selectedOption.dataset.startTime?.substring(0, 5) || '--:--';
        const endTime = selectedOption.dataset.endTime?.substring(0, 5) || '--:--';
        const duration = selectedOption.dataset.duration || '0';
        const category = selectedOption.dataset.category || 'standard';
        const isOvernight = selectedOption.dataset.isOvernight === 'true';
        const hasHandover = selectedOption.dataset.hasHandover === 'true';
        const handoverDuration = selectedOption.dataset.handoverDuration || '30';
        const rotationType = selectedOption.dataset.rotationType || 'fixed';
        const applicableDays = JSON.parse(selectedOption.dataset.applicableDays || '[1,2,3,4,5]');
        const rotationConfig = JSON.parse(selectedOption.dataset.rotationConfig || '{}');
        const breakSchedule = JSON.parse(selectedOption.dataset.breakSchedule || '{}');
        const hasBreaks = selectedOption.dataset.hasBreaks === 'true';
        
        const shiftTimeDisplay = document.getElementById('shift-time-display');
        const shiftDurationDisplay = document.getElementById('shift-duration-display');
        const shiftCategoryDisplay = document.getElementById('shift-category-display');
        const shiftHandoverDisplay = document.getElementById('shift-handover-display');
        const overnightWarning = document.getElementById('overnight-warning');
        const shiftDetails = document.getElementById('shift-details');
        
        if (shiftTimeDisplay) shiftTimeDisplay.textContent = `${startTime} - ${endTime}`;
        if (shiftDurationDisplay) shiftDurationDisplay.textContent = `${duration} hours`;
        if (shiftCategoryDisplay) shiftCategoryDisplay.textContent = category.charAt(0).toUpperCase() + category.slice(1);
        if (shiftHandoverDisplay) shiftHandoverDisplay.textContent = hasHandover ? `Yes (${handoverDuration}min)` : 'No';
        
        if (overnightWarning) {
            if (isOvernight) {
                overnightWarning.classList.remove('hidden');
            } else {
                overnightWarning.classList.add('hidden');
            }
        }
        
        // Update break information
        updateBreakInfo(hasBreaks, breakSchedule);
        
        // Check shift applicability
        const dateInput = document.getElementById('assignment_date');
        const applicabilityWarning = document.getElementById('applicability-warning');
        const applicabilityWarningText = document.getElementById('applicability-warning-text');
        
        if (dateInput?.value && applicabilityWarning && applicabilityWarningText) {
            const dayIso = getDayOfWeekIso(dateInput.value);
            const isApplicable = applicableDays.includes(dayIso);
            
            if (!isApplicable) {
                applicabilityWarning.classList.remove('hidden');
                applicabilityWarningText.textContent = `This shift is not applicable on ${getDayOfWeek(dateInput.value)}s.`;
            } else {
                applicabilityWarning.classList.add('hidden');
            }
        }
        
        // Update rotation options
        const rotationOptions = document.getElementById('rotation-options');
        if (rotationType === 'rotating') {
            if (rotationOptions) rotationOptions.classList.remove('hidden');
            updateRotationInfo(rotationConfig);
            
            const rotationGroupType = document.getElementById('rotation_group_type');
            const rotationSequenceNumber = document.getElementById('rotation_sequence_number');
            
            if (rotationGroupType) rotationGroupType.value = category;
            if (rotationSequenceNumber) rotationSequenceNumber.value = rotationConfig.current_sequence_index || 0;
        } else {
            if (rotationOptions) rotationOptions.classList.add('hidden');
            const rotationGroupType = document.getElementById('rotation_group_type');
            const rotationSequenceNumber = document.getElementById('rotation_sequence_number');
            const isRotated = document.getElementById('is_rotated');
            
            if (rotationGroupType) rotationGroupType.value = '';
            if (rotationSequenceNumber) rotationSequenceNumber.value = 0;
            if (isRotated) isRotated.value = 0;
        }
        
        if (shiftDetails) shiftDetails.classList.remove('hidden');
        
        // Update shift default indicators
        updateShiftDefaultIndicators();
        
        // Update handover info
        updateHandoverInfo();
    } else {
        const shiftDetails = document.getElementById('shift-details');
        if (shiftDetails) shiftDetails.classList.add('hidden');
        
        const rotationOptions = document.getElementById('rotation-options');
        if (rotationOptions) rotationOptions.classList.add('hidden');
    }
}

function updateBreakInfo(hasBreaks, breakSchedule) {
    const breakInfo = document.getElementById('break-info');
    const breakList = document.getElementById('break-list');
    
    if (hasBreaks && breakSchedule.breaks && breakSchedule.breaks.length > 0) {
        if (breakList) {
            breakList.innerHTML = breakSchedule.breaks.map((break_item, index) => 
                `<div class="text-xs">${break_item.name}: ${break_item.start_time} - ${break_item.end_time} (${break_item.duration_minutes} min) ${break_item.is_paid ? '💰' : ''}</div>`
            ).join('');
        }
        if (breakInfo) breakInfo.classList.remove('hidden');
        
        // Update break summary
        const totalBreakMinutes = breakSchedule.breaks.reduce((sum, b) => sum + (b.duration_minutes || 0), 0);
        const breakSummaryText = document.getElementById('break-summary-text');
        const breakSummary = document.getElementById('break-summary');
        
        if (breakSummaryText) breakSummaryText.textContent = `${breakSchedule.breaks.length} breaks, total ${Math.floor(totalBreakMinutes / 60)}h ${totalBreakMinutes % 60}m`;
        if (breakSummary) breakSummary.classList.remove('hidden');
    } else {
        if (breakInfo) breakInfo.classList.add('hidden');
        const breakSummary = document.getElementById('break-summary');
        if (breakSummary) breakSummary.classList.add('hidden');
    }
}

function updateRotationInfo(rotationConfig) {
    const sequence = rotationConfig.rotation_sequence || 'morning_evening';
    const sequenceMap = {
        'morning_evening': ['Morning', 'Evening', 'Night', 'Off'],
        'evening_morning': ['Evening', 'Morning', 'Night', 'Off'],
        'night_morning': ['Night', 'Morning', 'Evening', 'Off']
    };
    
    const sequenceDisplay = sequenceMap[sequence] || ['Morning', 'Evening', 'Night', 'Off'];
    const rotationDays = rotationConfig.rotation_days || 7;
    
    const currentRotationPosition = document.getElementById('current-rotation-position');
    if (currentRotationPosition) currentRotationPosition.textContent = sequenceDisplay[rotationConfig.current_sequence_index || 0];
    
    // Calculate next rotation date
    const today = new Date();
    const nextRotation = new Date(today);
    nextRotation.setDate(today.getDate() + rotationDays);
    
    const nextRotationDate = document.getElementById('next-rotation-date');
    if (nextRotationDate) {
        nextRotationDate.textContent = nextRotation.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }
}

async function updateHandoverInfo() {
    const shiftSelect = document.getElementById('security_shift_id');
    const dateInput = document.getElementById('assignment_date');
    const postSelect = document.getElementById('security_post_id');
    
    const shiftId = shiftSelect?.value;
    const date = dateInput?.value;
    const postId = postSelect?.value;
    
    const handoverPreview = document.getElementById('handover-preview-container');
    
    if (!shiftId || !date || !postId) {
        if (handoverPreview) handoverPreview.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`/admin/security-schedules/handover-info?shift_id=${shiftId}&date=${date}&post_id=${postId}`);
        const data = await response.json();
        
        if (data.success && data.handover_info) {
            const handoverInfo = data.handover_info;
            
            const handoverStartDisplay = document.getElementById('handover-start-display');
            const handoverEndDisplay = document.getElementById('handover-end-display');
            const handoverDurationDisplay = document.getElementById('handover-duration-display');
            const handoverWindowDisplay = document.getElementById('handover-window-display');
            const handoverNotesRequired = document.getElementById('handover-notes-required');
            const handoverChecklistPreview = document.getElementById('handover-checklist-preview');
            const checklistItems = document.getElementById('checklist-items');
            
            if (handoverStartDisplay) handoverStartDisplay.textContent = handoverInfo.handover_start || '--:--';
            if (handoverEndDisplay) handoverEndDisplay.textContent = handoverInfo.handover_end || '--:--';
            if (handoverDurationDisplay) handoverDurationDisplay.textContent = `${handoverInfo.handover_duration || 30} minutes`;
            if (handoverWindowDisplay) handoverWindowDisplay.textContent = handoverInfo.handover_window ? `${handoverInfo.handover_window} minutes` : '--';
            
            if (handoverNotesRequired) {
                if (handoverInfo.notes_required) {
                    handoverNotesRequired.classList.remove('hidden');
                } else {
                    handoverNotesRequired.classList.add('hidden');
                }
            }
            
            if (handoverInfo.checklist && handoverInfo.checklist.length > 0) {
                if (checklistItems) {
                    checklistItems.innerHTML = handoverInfo.checklist.map(item => 
                        `<div class="flex items-center"><i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> ${item}</div>`
                    ).join('');
                }
                if (handoverChecklistPreview) handoverChecklistPreview.classList.remove('hidden');
            } else {
                if (handoverChecklistPreview) handoverChecklistPreview.classList.add('hidden');
            }
            
            if (handoverPreview) handoverPreview.classList.remove('hidden');
        } else {
            if (handoverPreview) handoverPreview.classList.add('hidden');
        }
    } catch (error) {
        console.error('Failed to fetch handover info:', error);
        if (handoverPreview) handoverPreview.classList.add('hidden');
    }
}

// =============================================
// PERSONNEL FUNCTIONS
// =============================================
function updatePersonnelInfo() {
    const personnelSelect = document.getElementById('security_user_id');
    if (!personnelSelect || !personnelSelect.options[personnelSelect.selectedIndex]) return;
    
    const selectedOption = personnelSelect.options[personnelSelect.selectedIndex];
    
    if (selectedOption.value) {
        const phone = selectedOption.dataset.phone || 'N/A';
        const badge = selectedOption.dataset.badge || 'N/A';
        const lastShift = selectedOption.dataset.lastShift || 'No previous shifts';
        const totalShifts = selectedOption.dataset.shiftsCount || '0';
        const willingToRotate = selectedOption.dataset.willingToRotate === 'true';
        const rotationPref = selectedOption.dataset.rotationPreference || 'neutral';
        const prefersNight = selectedOption.dataset.prefersNight === 'true';
        const prefersDay = selectedOption.dataset.prefersDay === 'true';
        const willingDayToNight = selectedOption.dataset.willingDayToNight === 'true';
        const willingNightToDay = selectedOption.dataset.willingNightToDay === 'true';
        const joinedDate = selectedOption.dataset.joinedDate;
        const performance = selectedOption.dataset.performanceRating || '7';
        
        const personnelPhone = document.getElementById('personnel-phone');
        const personnelBadge = document.getElementById('personnel-badge');
        const personnelLastShift = document.getElementById('personnel-last-shift');
        const personnelTotalShifts = document.getElementById('personnel-total-shifts');
        const seniorityYears = document.getElementById('seniority-years');
        const performanceRating = document.getElementById('performance-rating');
        const seniorityPerformance = document.getElementById('seniority-performance');
        const rotationPreference = document.getElementById('rotation-preference');
        const rotationPreferenceText = document.getElementById('rotation-preference-text');
        const personnelInfo = document.getElementById('personnel-info');
        
        if (personnelPhone) personnelPhone.textContent = phone;
        if (personnelBadge) personnelBadge.textContent = badge;
        if (personnelLastShift) personnelLastShift.textContent = lastShift;
        if (personnelTotalShifts) personnelTotalShifts.textContent = totalShifts;
        
        // Update seniority and performance
        if (joinedDate && seniorityYears) {
            const joinDate = new Date(joinedDate);
            const today = new Date();
            const yearsEmployed = (today - joinDate) / (1000 * 60 * 60 * 24 * 365);
            seniorityYears.textContent = yearsEmployed.toFixed(1) + ' years';
        }
        
        if (performance && performanceRating) {
            performanceRating.textContent = performance + '/10';
        }
        
        if (seniorityPerformance) seniorityPerformance.classList.remove('hidden');
        
        // Update rotation preference
        if (willingToRotate) {
            if (rotationPreference) rotationPreference.classList.remove('hidden');
            let prefText = 'Willing to rotate';
            if (willingDayToNight) {
                prefText = 'Willing to rotate: Day → Night';
            } else if (willingNightToDay) {
                prefText = 'Willing to rotate: Night → Day';
            } else if (prefersNight) {
                prefText = 'Prefers night shifts';
            } else if (prefersDay) {
                prefText = 'Prefers day shifts';
            } else if (rotationPref === 'any') {
                prefText = 'Open to any rotation';
            }
            if (rotationPreferenceText) rotationPreferenceText.textContent = prefText;
        } else {
            if (rotationPreference) rotationPreference.classList.add('hidden');
        }
        
        if (personnelInfo) personnelInfo.classList.remove('hidden');
        
        // Calculate preference score
        calculatePreferenceScore(selectedOption);
        checkPersonnelAvailability();
    } else {
        const personnelInfo = document.getElementById('personnel-info');
        const rotationPreference = document.getElementById('rotation-preference');
        const availabilityChecker = document.getElementById('availability-checker');
        const restPeriodWarning = document.getElementById('rest-period-warning');
        const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
        const rotationScoreContainer = document.getElementById('rotation-score-container');
        const seniorityPerformance = document.getElementById('seniority-performance');
        
        if (personnelInfo) personnelInfo.classList.add('hidden');
        if (rotationPreference) rotationPreference.classList.add('hidden');
        if (availabilityChecker) availabilityChecker.classList.add('hidden');
        if (restPeriodWarning) restPeriodWarning.classList.add('hidden');
        if (weeklyHoursWarning) weeklyHoursWarning.classList.add('hidden');
        if (rotationScoreContainer) rotationScoreContainer.classList.add('hidden');
        if (seniorityPerformance) seniorityPerformance.classList.add('hidden');
    }
}

function calculatePreferenceScore(selectedOption) {
    const shiftSelect = document.getElementById('security_shift_id');
    const postSelect = document.getElementById('security_post_id');
    
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const shiftCategory = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.category || '';
    const preferredShifts = JSON.parse(selectedOption.dataset.preferredShifts || '[]');
    const preferredPosts = JSON.parse(selectedOption.dataset.preferredPosts || '[]');
    const willingToRotate = selectedOption.dataset.willingToRotate === 'true';
    const willingDayToNight = selectedOption.dataset.willingDayToNight === 'true';
    const willingNightToDay = selectedOption.dataset.willingNightToDay === 'true';
    const prefersNight = selectedOption.dataset.prefersNight === 'true';
    const prefersDay = selectedOption.dataset.prefersDay === 'true';
    const joinedDate = selectedOption.dataset.joinedDate;
    const performance = parseFloat(selectedOption.dataset.performanceRating || '7');
    const postId = postSelect?.value;
    
    // Calculate seniority score (0-2)
    let seniorityScore = 0;
    if (joinedDate) {
        const joinDate = new Date(joinedDate);
        const today = new Date();
        const yearsEmployed = (today - joinDate) / (1000 * 60 * 60 * 24 * 365);
        seniorityScore = Math.min(2, yearsEmployed);
    }
    
    // Calculate performance score (0-2)
    const performanceScore = (performance - 5) / 2.5; // 5=0, 7.5=1, 10=2
    
    // Calculate shift preference score (0-3)
    let shiftPreferenceScore = 0;
    if (preferredShifts.includes(shiftCategory)) {
        shiftPreferenceScore += 2;
    }
    if (preferredPosts.includes(parseInt(postId))) {
        shiftPreferenceScore += 1;
    }
    
    // Calculate rotation willingness score (0-3)
    let rotationScore = 0;
    if (willingToRotate) {
        rotationScore += 1;
        if (shiftCategory === 'night' && willingDayToNight) {
            rotationScore += 2;
        } else if (shiftCategory === 'day' && willingNightToDay) {
            rotationScore += 2;
        } else if (shiftCategory === 'night' && prefersNight) {
            rotationScore += 1;
        } else if (shiftCategory === 'day' && prefersDay) {
            rotationScore += 1;
        }
    }
    
    // Update score breakdown
    const scoreSeniority = document.getElementById('score-seniority');
    const scorePerformance = document.getElementById('score-performance');
    const scoreShiftPreference = document.getElementById('score-shift-preference');
    const scoreRotation = document.getElementById('score-rotation');
    const scoreBreakdown = document.getElementById('score-breakdown');
    
    if (scoreSeniority) scoreSeniority.textContent = '+' + seniorityScore.toFixed(1);
    if (scorePerformance) scorePerformance.textContent = '+' + performanceScore.toFixed(1);
    if (scoreShiftPreference) scoreShiftPreference.textContent = '+' + shiftPreferenceScore.toFixed(1);
    if (scoreRotation) scoreRotation.textContent = '+' + rotationScore.toFixed(1);
    
    if (scoreBreakdown) scoreBreakdown.classList.remove('hidden');
    
    // Calculate total score (base 5 + bonuses)
    let score = 5 + seniorityScore + performanceScore + shiftPreferenceScore + rotationScore;
    
    // Ensure score is between 1-10
    score = Math.max(1, Math.min(10, score));
    
    // Update UI
    const preferenceScoreBar = document.getElementById('preference-score-bar');
    const preferenceScoreValue = document.getElementById('preference-score-value');
    const preferenceScoreText = document.getElementById('preference-score-text');
    const rotationScoreContainer = document.getElementById('rotation-score-container');
    
    if (preferenceScoreBar && preferenceScoreValue) {
        const percentage = (score / 10) * 100;
        preferenceScoreBar.style.width = `${percentage}%`;
        preferenceScoreValue.textContent = `${score.toFixed(1)}/10`;
    }
    
    if (preferenceScoreText) {
        let scoreText = 'Based on personnel preferences';
        if (score >= 8) {
            if (preferenceScoreBar) preferenceScoreBar.style.backgroundColor = 'var(--success)';
            scoreText = 'High match - Personnel strongly prefers this assignment';
        } else if (score >= 5) {
            if (preferenceScoreBar) preferenceScoreBar.style.backgroundColor = 'var(--warning)';
            scoreText = 'Medium match - Personnel is neutral about this assignment';
        } else {
            if (preferenceScoreBar) preferenceScoreBar.style.backgroundColor = 'var(--danger)';
            scoreText = 'Low match - Personnel may not prefer this assignment';
        }
        preferenceScoreText.textContent = scoreText;
    }
    
    // Set hidden input value
    const rotationPreferenceScore = document.getElementById('rotation_preference_score');
    if (rotationPreferenceScore) rotationPreferenceScore.value = score.toFixed(1);
    
    if (rotationScoreContainer) rotationScoreContainer.classList.remove('hidden');
}

async function checkPersonnelAvailability() {
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const shiftSelect = document.getElementById('security_shift_id');
    
    const userId = personnelSelect?.value;
    const date = dateInput?.value;
    const shiftId = shiftSelect?.value;
    
    const availabilityChecker = document.getElementById('availability-checker');
    const availabilityBadge = document.getElementById('availability-badge');
    const availabilityDetails = document.getElementById('availability-details');
    const personnelAvailability = document.getElementById('personnel-availability');
    const personnelAvailabilityDetail = document.getElementById('personnel-availability-detail');
    const restPeriodWarning = document.getElementById('rest-period-warning');
    const restPeriodText = document.getElementById('rest-period-text');
    const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
    const weeklyHoursText = document.getElementById('weekly-hours-text');
    
    if (!userId || !date) {
        if (availabilityChecker) availabilityChecker.classList.add('hidden');
        return;
    }

    try {
        if (availabilityChecker) availabilityChecker.classList.remove('hidden');
        if (availabilityBadge) {
            availabilityBadge.textContent = 'Checking...';
            availabilityBadge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
            availabilityBadge.style.color = 'var(--warning)';
        }
        if (availabilityDetails) availabilityDetails.textContent = 'Verifying availability and constraints...';
        
        const existingResponse = await fetch(`/admin/security-schedules/check-availability?user_id=${userId}&date=${date}`);
        const existingData = await existingResponse.json();
        
        let isAvailable = true;
        
        if (existingData.has_assignment) {
            isAvailable = false;
            if (personnelAvailability) {
                personnelAvailability.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--danger)';
                const icon = document.querySelector('#personnel-availability i');
                if (icon) icon.style.color = 'var(--danger)';
                const span = document.querySelector('#personnel-availability span');
                if (span) {
                    span.textContent = 'Unavailable';
                    span.style.color = 'var(--danger)';
                }
            }
            if (personnelAvailabilityDetail) personnelAvailabilityDetail.textContent = 'Already assigned to another post on this date.';
            
            if (availabilityBadge) {
                availabilityBadge.textContent = 'Unavailable';
                availabilityBadge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                availabilityBadge.style.color = 'var(--danger)';
            }
            if (availabilityDetails) availabilityDetails.textContent = 'Personnel already has an assignment on this date.';
        } else {
            if (personnelAvailability) {
                personnelAvailability.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--success)';
                const icon = document.querySelector('#personnel-availability i');
                if (icon) icon.style.color = 'var(--success)';
                const span = document.querySelector('#personnel-availability span');
                if (span) {
                    span.textContent = 'Available';
                    span.style.color = 'var(--success)';
                }
            }
            if (personnelAvailabilityDetail) personnelAvailabilityDetail.textContent = 'No conflicting assignments found.';
            
            if (availabilityBadge) {
                availabilityBadge.textContent = 'Available';
                availabilityBadge.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                availabilityBadge.style.color = 'var(--success)';
            }
            if (availabilityDetails) availabilityDetails.textContent = 'Personnel is available for this date.';
        }
        
        // Check rest period
        if (shiftId && shiftSelect && shiftSelect.options[shiftSelect.selectedIndex]) {
            const selectedShift = shiftSelect.options[shiftSelect.selectedIndex];
            const shiftDuration = parseFloat(selectedShift.dataset.duration || '0');
            
            if (existingData.last_shift_date) {
                const lastShiftDate = new Date(existingData.last_shift_date + 'T12:00:00');
                const currentDate = new Date(date + 'T12:00:00');
                const hoursDiff = (currentDate - lastShiftDate) / (1000 * 60 * 60);
                const minRestHours = 12;
                
                if (hoursDiff < minRestHours) {
                    if (restPeriodWarning) restPeriodWarning.classList.remove('hidden');
                    if (restPeriodText) restPeriodText.textContent = `Only ${Math.round(hoursDiff)} hours since last shift. Minimum rest period is ${minRestHours} hours.`;
                    isAvailable = false;
                } else {
                    if (restPeriodWarning) restPeriodWarning.classList.add('hidden');
                }
            }
            
            // Check weekly hours
            if (existingData.weekly_hours !== undefined) {
                const weeklyHours = existingData.weekly_hours || 0;
                const maxWeeklyHours = 60;
                
                if ((weeklyHours + shiftDuration) > maxWeeklyHours) {
                    if (weeklyHoursWarning) weeklyHoursWarning.classList.remove('hidden');
                    if (weeklyHoursText) weeklyHoursText.textContent = `Current week: ${weeklyHours}h, Adding: ${shiftDuration}h, Total: ${weeklyHours + shiftDuration}h (Max: ${maxWeeklyHours}h)`;
                    isAvailable = false;
                } else {
                    if (weeklyHoursWarning) weeklyHoursWarning.classList.add('hidden');
                }
            }
        }
        
        if (!isAvailable && availabilityBadge) {
            availabilityBadge.textContent = 'Constraints Detected';
            availabilityBadge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
            availabilityBadge.style.color = 'var(--warning)';
        }
        
    } catch (error) {
        console.error('Failed to check availability:', error);
        if (availabilityDetails) availabilityDetails.textContent = 'Could not verify availability. Please proceed with caution.';
    }
}

// =============================================
// ASSIGNMENT SUMMARY FUNCTIONS
// =============================================
function updateAssignmentSummary() {
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const includeBreaksCheckbox = document.getElementById('include_breaks');
    
    const hasPost = postSelect?.value;
    const hasShift = shiftSelect?.value;
    const hasPersonnel = personnelSelect?.value;
    const hasDate = dateInput?.value;
    
    const assignmentSummary = document.getElementById('assignment-summary');
    
    if (hasPost && hasShift && hasPersonnel && hasDate && 
        postSelect.options[postSelect.selectedIndex] && 
        shiftSelect.options[shiftSelect.selectedIndex] && 
        personnelSelect.options[personnelSelect.selectedIndex]) {
            
        const postText = postSelect.options[postSelect.selectedIndex]?.text.split('-')[0].trim() || 'Not selected';
        const shiftText = shiftSelect.options[shiftSelect.selectedIndex]?.text.split('(')[0].trim() || 'Not selected';
        const personnelText = personnelSelect.options[personnelSelect.selectedIndex]?.text.split('-')[0].trim() || 'Not selected';
        const shiftDuration = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.duration || '0';
        const rotationType = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.rotationType || 'fixed';
        const hasBreaks = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.hasBreaks === 'true';
        
        const summaryPost = document.getElementById('summary-post');
        const summaryDatetime = document.getElementById('summary-datetime');
        const summaryPersonnel = document.getElementById('summary-personnel');
        const summaryDuration = document.getElementById('summary-duration');
        
        if (summaryPost) summaryPost.textContent = postText;
        if (summaryDatetime) summaryDatetime.textContent = `${new Date(dateInput.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${shiftText}`;
        if (summaryPersonnel) summaryPersonnel.textContent = personnelText;
        if (summaryDuration) summaryDuration.textContent = `${shiftDuration} hours`;
        
        // Update rotation summary
        const rotationSummary = document.getElementById('rotation-summary');
        const rotationSummaryText = document.getElementById('rotation-summary-text');
        
        if (rotationType === 'rotating') {
            if (rotationSummary) rotationSummary.classList.remove('hidden');
            if (rotationSummaryText) rotationSummaryText.textContent = 'Part of rotating shift schedule';
        } else {
            if (rotationSummary) rotationSummary.classList.add('hidden');
        }
        
        // Update break summary visibility based on include_breaks checkbox
        const breakSummary = document.getElementById('break-summary');
        if (includeBreaksCheckbox && includeBreaksCheckbox.checked && hasBreaks) {
            if (breakSummary) breakSummary.classList.remove('hidden');
        } else {
            if (breakSummary) breakSummary.classList.add('hidden');
        }
        
        // Update timeline
        updateTimelinePreview(shiftSelect.options[shiftSelect.selectedIndex], dateInput.value);
        
        if (assignmentSummary) assignmentSummary.classList.remove('hidden');
        
        // Validate form after summary update
        validateForm();
    } else {
        if (assignmentSummary) assignmentSummary.classList.add('hidden');
    }
}

function updateTimelinePreview(selectedShift, dateValue) {
    const timelineStart = document.getElementById('timeline-start');
    const timelineEnd = document.getElementById('timeline-end');
    const timelineProgress = document.getElementById('timeline-progress');
    const shiftTimelineStatus = document.getElementById('shift-timeline-status');
    
    if (selectedShift) {
        const startTime = selectedShift.dataset.startTime?.substring(0, 5) || '00:00';
        const endTime = selectedShift.dataset.endTime?.substring(0, 5) || '00:00';
        
        if (timelineStart) timelineStart.textContent = startTime;
        if (timelineEnd) timelineEnd.textContent = endTime;
        
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        const currentTimeDecimal = currentHour + currentMinute / 60;
        
        const [startHour, startMinute] = startTime.split(':').map(Number);
        const startDecimal = startHour + startMinute / 60;
        const [endHour, endMinute] = endTime.split(':').map(Number);
        let endDecimal = endHour + endMinute / 60;
        
        if (endDecimal < startDecimal) {
            endDecimal += 24;
        }
        
        let progress = 0;
        if (dateValue === new Date().toISOString().split('T')[0]) {
            if (currentTimeDecimal < startDecimal) {
                progress = 0;
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Not started';
            } else if (currentTimeDecimal > endDecimal) {
                progress = 100;
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Ended';
            } else {
                progress = ((currentTimeDecimal - startDecimal) / (endDecimal - startDecimal)) * 100;
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'In progress';
            }
        } else {
            const selectedDate = new Date(dateValue);
            const today = new Date();
            if (selectedDate > today) {
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Scheduled';
            } else if (selectedDate < today) {
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Past';
            }
        }
        
        if (timelineProgress) timelineProgress.style.width = `${Math.min(progress, 100)}%`;
    }
}

// =============================================
// FORM VALIDATION FUNCTIONS
// =============================================
function validateForm() {
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    
    let isValid = true;
    let warnings = [];
    
    if (!postSelect?.value) {
        isValid = false;
        warnings.push('Security Post is required');
    }
    
    if (!shiftSelect?.value) {
        isValid = false;
        warnings.push('Security Shift is required');
    }
    
    if (!personnelSelect?.value) {
        isValid = false;
        warnings.push('Security Personnel is required');
    }
    
    if (!dateInput?.value) {
        isValid = false;
        warnings.push('Assignment Date is required');
    }
    
    const maxPersonnel = document.getElementById('max-personnel');
    if (postSelect?.value && dateInput?.value && maxPersonnel) {
        const max = parseInt(maxPersonnel.textContent || '0');
        if (currentCapacity >= max && max > 0) {
            isValid = false;
            warnings.push('Selected post is at full capacity for this date');
        }
    }
    
    if (shiftSelect?.value && dateInput?.value && shiftSelect.options[shiftSelect.selectedIndex]) {
        const selectedShift = shiftSelect.options[shiftSelect.selectedIndex];
        if (selectedShift) {
            const applicableDays = JSON.parse(selectedShift.dataset.applicableDays || '[1,2,3,4,5]');
            const dayIso = getDayOfWeekIso(dateInput.value);
            
            if (!applicableDays.includes(dayIso)) {
                isValid = false;
                warnings.push('Selected shift is not applicable on this day');
            }
        }
    }
    
    if (personnelSelect?.value && dateInput?.value) {
        const restPeriodWarning = document.getElementById('rest-period-warning');
        const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
        
        if (restPeriodWarning && !restPeriodWarning.classList.contains('hidden')) {
            isValid = false;
            warnings.push('Insufficient rest period for selected personnel');
        }
        
        if (weeklyHoursWarning && !weeklyHoursWarning.classList.contains('hidden')) {
            isValid = false;
            warnings.push('Weekly hour limit would be exceeded');
        }
        
        const availabilitySpan = document.querySelector('#personnel-availability span');
        if (availabilitySpan && availabilitySpan.textContent === 'Unavailable') {
            isValid = false;
            warnings.push('Selected personnel is already assigned on this date');
        }
    }
    
    // Check for override reason when break is overridden
    if (isBreakOverridden) {
        const overrideReason = document.getElementById('break_override_reason');
        if (!overrideReason || !overrideReason.value.trim()) {
            isValid = false;
            warnings.push('Override reason is required when modifying break schedule');
        }
    }
    
    const submitWarnings = document.getElementById('submit-warnings');
    const submitWarningText = document.getElementById('submit-warning-text');
    const submitButton = document.getElementById('submit-button');
    const formStatus = document.getElementById('form-status');
    
    if (warnings.length > 0 && submitWarnings && submitWarningText) {
        submitWarnings.classList.remove('hidden');
        submitWarningText.textContent = warnings.join('. ') + '.';
        
        if (submitButton) {
            submitButton.disabled = !isValid;
            submitButton.classList.toggle('opacity-50', !isValid);
            submitButton.classList.toggle('cursor-not-allowed', !isValid);
        }
        
        if (formStatus) {
            if (!isValid) {
                formStatus.innerHTML = `
                    <div class="flex items-center text-red-600">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>Please fix validation errors before submitting</span>
                    </div>
                `;
            } else {
                formStatus.innerHTML = `
                    <div class="flex items-center text-yellow-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>Ready to submit (warnings only)</span>
                    </div>
                `;
            }
        }
    } else if (submitWarnings) {
        submitWarnings.classList.add('hidden');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (formStatus) {
            formStatus.innerHTML = `
                <div class="flex items-center text-green-600">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Ready to submit</span>
                </div>
            `;
        }
    }
    
    isFormValid = isValid;
    return isValid;
}

// =============================================
// BULK ASSIGN FUNCTIONS WITH OVERRIDE HANDLING
// =============================================
window.showBulkAssignModal = function() {
    updateBulkShiftDefaultIndicators();
    // FIXED: Automatically update weekly days based on selected shift
    updateWeeklyDaysFromShift();
    updateBulkAssignSummary();
    openModal('bulkAssignModal');
};

// =============================================
// FIXED: NEW FUNCTION TO UPDATE WEEKLY DAYS FROM SHIFT
// =============================================
function updateWeeklyDaysFromShift() {
    const shiftSelect = document.getElementById('bulk_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    const applicableDays = JSON.parse(selectedOption.dataset.applicableDays || '[]');
    
    // If the shift has specific applicable days, use them
    if (applicableDays && applicableDays.length > 0) {
        // First, uncheck all days
        document.querySelectorAll('input[name="recurrence_days[]"]').forEach(cb => {
            cb.checked = false;
        });
        
        // Then check only the days that are applicable
        applicableDays.forEach(day => {
            const checkbox = document.querySelector(`input[name="recurrence_days[]"][value="${day}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }
    // If no applicable days are set (for all_days, weekday, weekend types), 
    // we keep the default (all days checked) which is the new default
}

function updateBulkShiftDefaultIndicators() {
    const shiftSelect = document.getElementById('bulk_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    
    if (selectedOption.value) {
        const shiftHasBreaks = selectedOption.dataset.shiftBreakDefault === 'true';
        const shiftHasHandover = selectedOption.dataset.shiftHandoverDefault === 'true';
        
        // Update break indicator
        const breakIndicator = document.getElementById('bulk-shift-break-indicator');
        if (breakIndicator) {
            breakIndicator.textContent = shiftHasBreaks ? 'Default: Yes' : 'Default: No';
            breakIndicator.style.backgroundColor = shiftHasBreaks ? 
                'rgba(var(--success-rgb), 0.1)' : 
                'rgba(var(--secondary-rgb), 0.1)';
            breakIndicator.style.color = shiftHasBreaks ? 
                'var(--success)' : 
                'var(--text-secondary)';
        }
        
        // Update handover indicator
        const handoverIndicator = document.getElementById('bulk-shift-handover-indicator');
        if (handoverIndicator) {
            handoverIndicator.textContent = shiftHasHandover ? 'Default: Yes' : 'Default: No';
            handoverIndicator.style.backgroundColor = shiftHasHandover ? 
                'rgba(var(--success-rgb), 0.1)' : 
                'rgba(var(--secondary-rgb), 0.1)';
            handoverIndicator.style.color = shiftHasHandover ? 
                'var(--success)' : 
                'var(--text-secondary)';
        }
        
        // Auto-populate checkboxes based on defaults
        const breaksCheckbox = document.getElementById('bulk_include_breaks');
        const handoverCheckbox = document.getElementById('bulk_include_handover');
        
        if (breaksCheckbox && !breaksCheckbox.dataset.userModified) {
            breaksCheckbox.checked = shiftHasBreaks;
        }
        
        if (handoverCheckbox && !handoverCheckbox.dataset.userModified) {
            handoverCheckbox.checked = shiftHasHandover;
        }
        
        // Check for bulk overrides
        checkBulkOverrides(selectedOption);
    }
}

function checkBulkOverrides(selectedOption) {
    const breaksCheckbox = document.getElementById('bulk_include_breaks');
    const handoverCheckbox = document.getElementById('bulk_include_handover');
    
    if (!selectedOption || !breaksCheckbox || !handoverCheckbox) return;
    
    const shiftHasBreaks = selectedOption.dataset.shiftBreakDefault === 'true';
    const shiftHasHandover = selectedOption.dataset.shiftHandoverDefault === 'true';
    
    const breakOverrideWarning = document.getElementById('bulk-break-override-warning');
    const breakOverrideText = document.getElementById('bulk-break-override-text');
    const handoverOverrideWarning = document.getElementById('bulk-handover-override-warning');
    const handoverOverrideText = document.getElementById('bulk-handover-override-text');
    const overrideReasonContainer = document.getElementById('bulk-override-reason-container');
    const overrideSummaryLabel = document.getElementById('overrideSummaryLabel');
    const overrideSummaryValue = document.getElementById('overrideSummaryValue');
    
    let hasOverride = false;
    
    // Check break override
    if (breaksCheckbox.checked !== shiftHasBreaks) {
        if (breakOverrideWarning && breakOverrideText) {
            breakOverrideText.textContent = `You are ${breaksCheckbox.checked ? 'enabling' : 'disabling'} breaks for all assignments. Shift default: ${shiftHasBreaks ? 'has breaks' : 'no breaks'}.`;
            breakOverrideWarning.classList.remove('hidden');
        }
        hasOverride = true;
    } else {
        if (breakOverrideWarning) breakOverrideWarning.classList.add('hidden');
    }
    
    // Check handover override
    if (handoverCheckbox.checked !== shiftHasHandover) {
        if (handoverOverrideWarning && handoverOverrideText) {
            handoverOverrideText.textContent = `You are ${handoverCheckbox.checked ? 'enabling' : 'disabling'} handover for all assignments. Shift default: ${shiftHasHandover ? 'has handover' : 'no handover'}.`;
            handoverOverrideWarning.classList.remove('hidden');
        }
        hasOverride = true;
    } else {
        if (handoverOverrideWarning) handoverOverrideWarning.classList.add('hidden');
    }
    
    // Show/hide override reason container
    if (overrideReasonContainer) {
        if (hasOverride) {
            overrideReasonContainer.classList.remove('hidden');
            const reasonInput = document.getElementById('bulk_override_reason');
            if (reasonInput) reasonInput.setAttribute('required', 'required');
        } else {
            overrideReasonContainer.classList.add('hidden');
            const reasonInput = document.getElementById('bulk_override_reason');
            if (reasonInput) {
                reasonInput.removeAttribute('required');
                reasonInput.value = '';
            }
        }
    }
    
    // Update summary indicators
    if (overrideSummaryLabel && overrideSummaryValue) {
        if (hasOverride) {
            overrideSummaryLabel.classList.remove('hidden');
            overrideSummaryValue.classList.remove('hidden');
        } else {
            overrideSummaryLabel.classList.add('hidden');
            overrideSummaryValue.classList.add('hidden');
        }
    }
}

function updateBulkAssignSummary() {
    const postSelect = document.getElementById('bulk_post_id');
    const shiftSelect = document.getElementById('bulk_shift_id');
    const userSelect = document.getElementById('bulk_user_ids');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const recurrenceType = document.getElementById('recurrenceType');
    
    const summaryPersonnelCount = document.getElementById('summaryPersonnelCount');
    const summaryDateRange = document.getElementById('summaryDateRange');
    const summaryRecurrence = document.getElementById('summaryRecurrence');
    const summaryRotationPattern = document.getElementById('summaryRotationPattern');
    const summaryTotalAssignments = document.getElementById('summaryTotalAssignments');
    const bulkSummary = document.getElementById('bulkAssignSummary');
    
    if (!postSelect || !shiftSelect || !userSelect || !startDate || !endDate || !recurrenceType) return;
    
    const selectedCount = userSelect.selectedOptions.length;
    if (selectedCount > 0 && startDate.value && endDate.value) {
        if (summaryPersonnelCount) summaryPersonnelCount.textContent = selectedCount;
        if (summaryDateRange) summaryDateRange.textContent = startDate.value + ' to ' + endDate.value;
        
        const recurrenceText = recurrenceType.options[recurrenceType.selectedIndex]?.text || 'None';
        if (summaryRecurrence) summaryRecurrence.textContent = recurrenceText;
        
        // Get selected rotation pattern if any
        const selectedPattern = document.querySelector('input[name="rotation_pattern"]:checked');
        if (selectedPattern && recurrenceType.value === 'rotating') {
            const patternText = selectedPattern.parentElement?.querySelector('span')?.textContent || 'Custom';
            if (summaryRotationPattern) summaryRotationPattern.textContent = patternText;
        } else {
            if (summaryRotationPattern) summaryRotationPattern.textContent = 'None';
        }
        
        // Calculate approximate total assignments
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);
        const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        
        let multiplier = 1;
        if (recurrenceType.value === 'weekly') {
            const checkedDays = document.querySelectorAll('input[name="recurrence_days[]"]:checked').length;
            multiplier = checkedDays / 7;
        } else if (recurrenceType.value === 'monthly') {
            multiplier = 0.5; // Rough estimate
        }
        
        const totalAssignments = Math.ceil(selectedCount * daysDiff * multiplier);
        if (summaryTotalAssignments) summaryTotalAssignments.textContent = totalAssignments;
        
        if (bulkSummary) bulkSummary.classList.remove('hidden');
    } else {
        if (bulkSummary) bulkSummary.classList.add('hidden');
    }
}

// =============================================
// PREVIEW & SUBMIT FUNCTIONS
// =============================================
window.previewBulkAssign = function() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) {
        console.error('Bulk assign form not found');
        return;
    }
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Get form data
    const formData = new FormData(form);
    
    // Validate at least one personnel selected
    const userIds = formData.getAll('security_user_ids[]');
    if (userIds.length === 0) {
        showToast('Please select at least one security personnel', 'warning');
        return;
    }
    
    // Check for override reason when overrides exist
    const hasBreakOverride = document.getElementById('bulk-break-override-warning') && 
                            !document.getElementById('bulk-break-override-warning').classList.contains('hidden');
    const hasHandoverOverride = document.getElementById('bulk-handover-override-warning') && 
                               !document.getElementById('bulk-handover-override-warning').classList.contains('hidden');
    
    if ((hasBreakOverride || hasHandoverOverride)) {
        const overrideReason = document.getElementById('bulk_override_reason');
        if (!overrideReason || !overrideReason.value.trim()) {
            showToast('Override reason is required when modifying shift defaults', 'warning');
            overrideReason?.focus();
            return;
        }
    }
    
    // Build data object
    const data = {
        _token: formData.get('_token'),
        security_post_id: formData.get('security_post_id'),
        security_shift_id: formData.get('security_shift_id'),
        security_user_ids: userIds,
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        recurrence_type: formData.get('recurrence_type'),
        include_breaks: formData.get('include_breaks') === 'on',
        include_handover: formData.get('include_handover') === 'on',
        maintain_coverage: formData.get('maintain_coverage') === 'on',
        respect_preferences: formData.get('respect_preferences') === 'on',
        notify_personnel: formData.get('notify_personnel') === 'on',
        notes: formData.get('notes') || '',
        emergency_contact: formData.get('emergency_contact') || '',
        special_instructions: formData.get('special_instructions') || '',
        override_reason: document.getElementById('bulk_override_reason')?.value || ''
    };
    
    // Handle recurrence_days as array
    const recurrenceDays = formData.getAll('recurrence_days[]');
    if (recurrenceDays.length > 0) {
        data.recurrence_days = recurrenceDays.map(Number);
    }
    
    // Handle rotation_pattern if it exists
    const rotationPattern = formData.get('rotation_pattern');
    if (rotationPattern) {
        data.rotation_pattern = rotationPattern;
    }
    
    // Handle rotation group creation
    const createRotationGroup = formData.get('create_rotation_group') === 'on';
    if (createRotationGroup) {
        data.create_rotation_group = true;
        data.rotation_group_name = formData.get('rotation_group_name') || 'Auto Group';
    }
    
    // Show preview modal with loading
    const previewContent = document.getElementById('previewContent');
    if (previewContent) {
        previewContent.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Generating preview...</p>
            </div>
        `;
    }
    openModal('bulkAssignPreviewModal');
    
    // Fetch preview data
    fetch('{{ route("admin.security-schedules.bulk-assign-preview") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Server returned HTML instead of JSON');
            });
        }
        
        if (!response.ok) {
            return response.json().then(err => { 
                throw new Error(err.message || 'Server error');
            });
        }
        return response.json();
    })
    .then(responseData => {
        if (responseData.success) {
            displayPreviewResults(responseData);
        } else {
            let errorMessage = responseData.message || 'Failed to generate preview';
            if (responseData.errors) {
                errorMessage += '\n' + JSON.stringify(responseData.errors);
            }
            showToast(errorMessage, 'error');
            closeModal('bulkAssignPreviewModal');
        }
    })
    .catch(error => {
        console.error('Preview error:', error);
        showToast('Network error occurred: ' + error.message, 'error');
        closeModal('bulkAssignPreviewModal');
    });
};

function displayPreviewResults(data) {
    const previewContent = document.getElementById('previewContent');
    
    if (!previewContent) return;
    
    if (!data.preview || data.preview.length === 0) {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <div class="text-6xl mb-4" style="color: var(--warning);">📅</div>
                <p style="color: var(--text-primary); font-size: 1.1rem; font-weight: 500;">No assignments will be created</p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">Check your recurrence settings and date range</p>
                ${data.warnings && data.warnings.length > 0 ? `
                    <div class="mt-4 text-left p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <p class="text-sm font-medium mb-2" style="color: var(--warning);">⚠️ Warnings:</p>
                        <ul class="text-xs space-y-1">
                            ${data.warnings.map(w => `<li style="color: var(--text-secondary);">• ${w.message}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
        return;
    }

    // Build statistics summary
    let html = `
        <div class="mb-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--success);">${data.count || 0}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Total Assignments</div>
            </div>
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--info);">${data.statistics?.unique_personnel || 0}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Unique Personnel</div>
            </div>
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--warning);">${data.statistics?.avg_per_personnel || 0}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Avg per Person</div>
            </div>
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--primary);">${data.statistics?.coverage_rate || 0}%</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Coverage Rate</div>
            </div>
        </div>
    `;

    // Summary information
    if (data.summary) {
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Post</span>
                        <div class="font-medium" style="color: var(--text-primary);">${data.summary.post?.name || 'N/A'}</div>
                        <span class="text-xxs" style="color: var(--text-secondary);">Max: ${data.summary.post?.max_personnel || 0}</span>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Shift</span>
                        <div class="font-medium" style="color: var(--text-primary);">${data.summary.shift?.name || 'N/A'}</div>
                        <span class="text-xxs" style="color: var(--text-secondary);">${data.summary.shift?.time || ''}</span>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Date Range</span>
                        <div class="font-medium text-sm" style="color: var(--text-primary);">${data.summary.date_range?.start || ''}</div>
                        <span class="text-xxs" style="color: var(--text-secondary);">to ${data.summary.date_range?.end || ''}</span>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Recurrence</span>
                        <div class="font-medium" style="color: var(--text-primary);">${data.summary.recurrence?.type || 'N/A'}</div>
                        ${data.summary.recurrence?.days?.length ? 
                            `<span class="text-xxs" style="color: var(--text-secondary);">Days: ${data.summary.recurrence.days.join(', ')}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    // Rotation info
    if (data.summary?.rotation) {
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center mb-2">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--warning);"></i>
                    <span class="text-sm font-medium" style="color: var(--text-primary);">Rotation Pattern: ${data.summary.rotation.pattern || 'None'}</span>
                </div>
                ${data.summary.rotation.group ? `
                    <div class="text-xs pl-6" style="color: var(--text-secondary);">
                        <i class="fas fa-users mr-1"></i> Will create group: "${data.summary.rotation.group.name}" with ${data.summary.rotation.group.members} members
                    </div>
                ` : ''}
            </div>
        `;
    }

    // Options summary
    if (data.summary?.options) {
        const options = data.summary.options;
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                <div class="flex flex-wrap gap-3 text-xs">
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.include_breaks ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.include_breaks ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Breaks
                    </span>
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.include_handover ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.include_handover ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Handover
                    </span>
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.maintain_coverage ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.maintain_coverage ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Maintain Coverage
                    </span>
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.respect_preferences ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.respect_preferences ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Respect Preferences
                    </span>
                </div>
            </div>
        `;
    }

    // Warnings
    if (data.warnings && data.warnings.length > 0) {
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                <p class="text-xs font-medium mb-2" style="color: var(--warning);"><i class="fas fa-exclamation-triangle mr-1"></i> Warnings:</p>
                <ul class="text-xs space-y-1">
                    ${data.warnings.map(w => `<li style="color: var(--text-secondary);">• ${w.message}</li>`).join('')}
                </ul>
            </div>
        `;
    }

    // Assignments by date
    if (data.preview && data.preview.length > 0) {
        html += `
            <div class="mt-4">
                <h4 class="font-medium mb-3" style="color: var(--text-primary);">Assignment Details by Date:</h4>
                <div class="max-h-96 overflow-y-auto space-y-4">
        `;
        
        data.preview.forEach(day => {
            const isFullCapacity = day.available_slots === 0;
            const isPartial = day.slots_filled < day.available_slots;
            
            html += `
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.02); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="font-medium" style="color: var(--primary);">${day.date}</span>
                            <span class="text-xs ml-2" style="color: var(--text-secondary);">${day.day_of_week || ''}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs px-2 py-1 rounded-full" 
                                  style="background-color: ${isFullCapacity ? 'rgba(var(--danger-rgb), 0.1); color: var(--danger);' : 
                                                                   isPartial ? 'rgba(var(--warning-rgb), 0.1); color: var(--warning);' : 
                                                                   'rgba(var(--success-rgb), 0.1); color: var(--success);'}">
                                <i class="fas fa-users mr-1"></i>
                                ${day.slots_filled || 0}/${day.available_slots || 0} slots
                            </span>
                            ${day.has_handover ? `
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-handshake mr-1"></i> Handover
                                </span>
                            ` : ''}
                            ${day.has_breaks ? `
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-coffee mr-1"></i> Breaks
                                </span>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        ${day.assignments && day.assignments.length > 0 ? day.assignments.map(assignment => `
                            <div class="flex items-center justify-between p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.03);">
                                <div class="flex items-center">
                                    <i class="fas fa-user-circle mr-2" style="color: var(--info);"></i>
                                    <div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">${assignment.personnel_name}</span>
                                        ${assignment.badge_number ? `<span class="text-xs ml-2" style="color: var(--text-secondary);">#${assignment.badge_number}</span>` : ''}
                                        <div class="text-xxs" style="color: var(--text-secondary);">${assignment.personnel_phone || ''}</div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="text-right">
                                        <div class="flex items-center">
                                            <span class="text-xs mr-2" style="color: var(--text-secondary);">Preference:</span>
                                            <div class="w-16 h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                                <div class="h-2 rounded-full" style="width: ${(assignment.preference_score / 10) * 100}%; background-color: ${assignment.preference_score >= 7 ? 'var(--success)' : assignment.preference_score >= 5 ? 'var(--warning)' : 'var(--danger)'};"></div>
                                            </div>
                                            <span class="text-xs ml-2" style="color: ${assignment.preference_score >= 7 ? 'var(--success)' : assignment.preference_score >= 5 ? 'var(--warning)' : 'var(--danger)'};">${assignment.preference_score}/10</span>
                                        </div>
                                        ${assignment.seniority_years ? `
                                            <div class="text-xxs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar-alt mr-1"></i> ${assignment.seniority_years} years
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `).join('') : `
                            <div class="text-center py-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> No assignments for this date
                            </div>
                        `}
                    </div>
                </div>
            `;
        });
        
        html += `</div></div>`;
    }

    previewContent.innerHTML = html;
}

window.submitBulkAssignForm = function() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) {
        console.error('Bulk assign form not found');
        return;
    }
    
    // Prevent default form submission
    if (event) {
        event.preventDefault();
    }
    
    // Close the preview modal
    closeModal('bulkAssignPreviewModal');
    
    // Get form data
    const formData = new FormData(form);
    
    // Validate at least one personnel selected
    const userIds = formData.getAll('security_user_ids[]');
    if (userIds.length === 0) {
        showToast('Please select at least one security personnel', 'warning');
        return;
    }
    
    // Check for override reason when overrides exist
    const hasBreakOverride = document.getElementById('bulk-break-override-warning') && 
                            !document.getElementById('bulk-break-override-warning').classList.contains('hidden');
    const hasHandoverOverride = document.getElementById('bulk-handover-override-warning') && 
                               !document.getElementById('bulk-handover-override-warning').classList.contains('hidden');
    
    if ((hasBreakOverride || hasHandoverOverride)) {
        const overrideReason = document.getElementById('bulk_override_reason');
        if (!overrideReason || !overrideReason.value.trim()) {
            showToast('Override reason is required when modifying shift defaults', 'warning');
            return;
        }
    }
    
    // Build data object
    const data = {
        _token: formData.get('_token'),
        security_post_id: formData.get('security_post_id'),
        security_shift_id: formData.get('security_shift_id'),
        security_user_ids: userIds,
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        recurrence_type: formData.get('recurrence_type'),
        include_breaks: formData.get('include_breaks') === 'on',
        include_handover: formData.get('include_handover') === 'on',
        maintain_coverage: formData.get('maintain_coverage') === 'on',
        respect_preferences: formData.get('respect_preferences') === 'on',
        notify_personnel: formData.get('notify_personnel') === 'on',
        notes: formData.get('notes') || '',
        emergency_contact: formData.get('emergency_contact') || '',
        special_instructions: formData.get('special_instructions') || '',
        override_reason: document.getElementById('bulk_override_reason')?.value || ''
    };
    
    // Handle recurrence_days as array
    const recurrenceDays = formData.getAll('recurrence_days[]');
    if (recurrenceDays.length > 0) {
        data.recurrence_days = recurrenceDays.map(Number);
    }
    
    // Handle rotation_pattern if it exists
    const rotationPattern = formData.get('rotation_pattern');
    if (rotationPattern) {
        data.rotation_pattern = rotationPattern;
    }
    
    // Handle rotation group creation
    const createRotationGroup = formData.get('create_rotation_group') === 'on';
    if (createRotationGroup) {
        data.create_rotation_group = true;
        data.rotation_group_name = formData.get('rotation_group_name') || 'Auto Group';
    }
    
    // Show loading state on the submit button
    const submitBtn = document.getElementById('bulkAssignSubmitBtn');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
    }
    
    // Show loading overlay
    showLoading('Creating schedules...');
    
    // Submit via AJAX
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response received:', text.substring(0, 200));
                throw new Error('Server returned an invalid response. Please check your server configuration.');
            });
        }
        
        if (!response.ok) {
            return response.json().then(err => { 
                throw new Error(err.message || `Server error: ${response.status}`);
            });
        }
        
        return response.json();
    })
    .then(data => {
        // Reset button state
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
        
        hideLoading();
        
        if (data.success) {
            // Show success message with the count
            showToast(`✅ ${data.created_count} schedule(s) created successfully!`, 'success');
            
            // Close the bulk assign modal
            closeModal('bulkAssignModal');
            
            // Log summary for debugging
            if (data.summary) {
                console.log('Bulk assignment summary:', data.summary);
                if (data.summary.override_reason) {
                    console.log('Override reason provided:', data.summary.override_reason);
                }
            }
            
            // Redirect to the schedules index page after a short delay
            setTimeout(() => {
                window.location.href = '{{ route("admin.security-schedules.index") }}';
            }, 1500);
        } else {
            showToast('❌ ' + (data.message || 'Failed to create schedules'), 'error');
            
            // Log failed assignments for debugging
            if (data.failed_assignments && data.failed_assignments.length > 0) {
                console.warn('Failed assignments:', data.failed_assignments);
            }
        }
    })
    .catch(error => {
        // Reset button state
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
        
        hideLoading();
        
        console.error('Submission error:', error);
        
        if (error.message.includes('Failed to fetch')) {
            showToast('Network error. Please check your internet connection.', 'error');
        } else {
            showToast('❌ Error: ' + error.message, 'error');
        }
    });
};

// =============================================
// MODAL FUNCTIONS
// =============================================
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

window.viewRotationHistory = function(scheduleId) {
    const modal = document.getElementById('rotation-history-modal');
    const content = document.getElementById('rotationHistoryContent');
    
    // Fetch rotation history data
    if (content) {
        content.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);"><i class="fas fa-spinner fa-spin mr-2"></i> Loading rotation history...</p>';
    }
    
    fetch(`/admin/rotation-groups/${scheduleId}/history`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history.length > 0) {
                let historyHtml = '';
                data.history.forEach(entry => {
                    historyHtml += `
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
                            <div class="flex justify-between">
                                <span class="font-medium" style="color: var(--text-primary);">${entry.date}</span>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    ${entry.type}
                                </span>
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-primary);">From: ${entry.from} → To: ${entry.to}</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">${entry.reason || 'No reason provided'}</p>
                            <p class="text-xxs mt-1" style="color: var(--text-secondary);">By: ${entry.rotated_by}</p>
                        </div>
                    `;
                });
                content.innerHTML = historyHtml;
            } else {
                content.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);">No rotation history found for this group.</p>';
            }
        })
        .catch(error => {
            console.error('Failed to load rotation history:', error);
            content.innerHTML = '<p class="text-center py-4" style="color: var(--danger);">Failed to load rotation history.</p>';
        });
    
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
};

window.viewRotationHistoryForCurrent = function() {
    // Get current rotation group if any
    const groupId = document.getElementById('rotation_group_id')?.value;
    if (groupId) {
        // Fetch rotation history for this group
        window.viewRotationHistory(groupId);
    } else {
        showToast('No rotation group associated with this shift', 'info');
    }
};

// =============================================
// TOAST NOTIFICATION
// =============================================
window.showToast = function(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    toast.innerHTML = `
        <span class="text-sm font-medium flex-1">${message}</span>
        <button class="ml-4 transition-colors duration-200" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
};

// =============================================
// EVENT LISTENERS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    // Form elements
    const form = document.getElementById('security-schedule-form');
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const includeBreaksCheckbox = document.getElementById('include_breaks');
    
    // Post selection events
    if (postSelect) {
        postSelect.addEventListener('change', function() {
            updatePostDetails();
            updatePostCapacity();
            updateHandoverInfo();
            updateAssignmentSummary();
        });
    }
    
    // Date input events
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            selectedDate = this.value;
            updateDayOfWeek();
            updatePostCapacity();
            updateShiftDetails();
            updateHandoverInfo();
            checkPersonnelAvailability();
            updateAssignmentSummary();
        });
    }
    
    // Shift selection events
    if (shiftSelect) {
        shiftSelect.addEventListener('change', function() {
            updateShiftDetails();
            updateHandoverInfo();
            checkPersonnelAvailability();
            updateAssignmentSummary();
            updateShiftDefaultIndicators();
        });
    }
    
    // Personnel selection events
    if (personnelSelect) {
        personnelSelect.addEventListener('change', function() {
            updatePersonnelInfo();
            checkPersonnelAvailability();
            updateAssignmentSummary();
        });
    }
    
    // Include breaks checkbox events (for override detection)
    if (includeBreaksCheckbox) {
        includeBreaksCheckbox.addEventListener('change', function() {
            this.dataset.userModified = 'true';
            checkForOverrides();
            updateAssignmentSummary();
        });
    }
    
    // Override reason input - validate on input
    const overrideReason = document.getElementById('break_override_reason');
    if (overrideReason) {
        overrideReason.addEventListener('input', function() {
            validateForm();
        });
    }
    
    // Form submission validation
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                
                const firstError = document.querySelector('.border-red-500');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            } else {
                // Add override audit log to form data if overrides exist
                if (isBreakOverridden && overrideAuditLog.length > 0) {
                    const overrideReason = document.getElementById('break_override_reason');
                    const auditInput = document.createElement('input');
                    auditInput.type = 'hidden';
                    auditInput.name = 'override_audit_log';
                    auditInput.value = JSON.stringify({
                        timestamp: new Date().toISOString(),
                        overrides: overrideAuditLog,
                        reason: overrideReason?.value || '',
                        user_id: {{ auth()->id() }},
                        user_name: '{{ auth()->user()->name }}'
                    });
                    form.appendChild(auditInput);
                }
            }
        });
    }

    // Bulk assign form event listeners
    setupBulkAssignForm();
    
    // Close modal when clicking overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });

    // =============================================
    // INITIALIZATION
    // =============================================
    function updateDayOfWeek() {
        const dayOfWeekDisplay = document.getElementById('day-of-week-display');
        const dateValue = dateInput?.value;
        if (dateValue && dayOfWeekDisplay) {
            const dayName = getDayOfWeek(dateValue);
            dayOfWeekDisplay.textContent = `${dayName}, ${new Date(dateValue).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        } else if (dayOfWeekDisplay) {
            dayOfWeekDisplay.textContent = 'Select a date';
        }
    }

    // Set default date if not set
    if (dateInput && !dateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
        selectedDate = today;
    }
    
    // Initial updates
    updateDayOfWeek();
    
    if (postSelect?.value) {
        updatePostDetails();
        updatePostCapacity();
    }
    
    if (shiftSelect?.value) {
        updateShiftDetails();
        updateHandoverInfo();
        updateShiftDefaultIndicators();
    }
    
    if (personnelSelect?.value) {
        updatePersonnelInfo();
        checkPersonnelAvailability();
    }
    
    if (postSelect?.value && shiftSelect?.value && personnelSelect?.value && dateInput?.value) {
        updateAssignmentSummary();
    }
    
    // Auto-refresh capacity every 30 seconds
    setInterval(function() {
        if (postSelect?.value && dateInput?.value) {
            updatePostCapacity();
        }
    }, 30000);
});

function setupBulkAssignForm() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) return;
    
    // Prevent default form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        window.submitBulkAssignForm();
        return false;
    });
    
    // Personnel selection change
    const personnelSelect = form.querySelector('select[name="security_user_ids[]"]');
    if (personnelSelect) {
        personnelSelect.addEventListener('change', updateBulkAssignSummary);
    }
    
    // Post and shift selection
    const postSelect = document.getElementById('bulk_post_id');
    const shiftSelect = document.getElementById('bulk_shift_id');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (postSelect) {
        postSelect.addEventListener('change', updateBulkAssignSummary);
    }
    
    if (shiftSelect) {
        shiftSelect.addEventListener('change', function() {
            updateBulkShiftDefaultIndicators();
            // FIXED: Update weekly days when shift changes
            updateWeeklyDaysFromShift();
            updateBulkAssignSummary();
        });
    }
    
    // Date range validation
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
            if (endDate.value && endDate.value < this.value) {
                endDate.value = this.value;
            }
            updateBulkAssignSummary();
        });
        
        endDate.addEventListener('change', updateBulkAssignSummary);
    }
    
    // Recurrence type change
    const recurrenceType = document.getElementById('recurrenceType');
    if (recurrenceType) {
        recurrenceType.addEventListener('change', function() {
            toggleRecurrenceOptions(this.value);
            updateBulkAssignSummary();
        });
    }
    
    // Bulk options checkboxes
    const breaksCheckbox = document.getElementById('bulk_include_breaks');
    const handoverCheckbox = document.getElementById('bulk_include_handover');
    
    if (breaksCheckbox) {
        breaksCheckbox.addEventListener('change', function() {
            this.dataset.userModified = 'true';
            if (shiftSelect) {
                checkBulkOverrides(shiftSelect.options[shiftSelect.selectedIndex]);
            }
            updateBulkAssignSummary();
        });
    }
    
    if (handoverCheckbox) {
        handoverCheckbox.addEventListener('change', function() {
            this.dataset.userModified = 'true';
            if (shiftSelect) {
                checkBulkOverrides(shiftSelect.options[shiftSelect.selectedIndex]);
            }
            updateBulkAssignSummary();
        });
    }
    
    // Rotation pattern change
    document.querySelectorAll('input[name="rotation_pattern"]').forEach(radio => {
        radio.addEventListener('change', updateBulkAssignSummary);
    });
    
    // Weekly days checkboxes
    document.querySelectorAll('input[name="recurrence_days[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkAssignSummary);
    });
    
    // Create rotation group toggle
    const createGroupCheckbox = document.getElementById('create_rotation_group');
    const groupNameContainer = document.getElementById('rotationGroupNameContainer');
    
    if (createGroupCheckbox && groupNameContainer) {
        createGroupCheckbox.addEventListener('change', function() {
            if (this.checked) {
                groupNameContainer.classList.remove('hidden');
            } else {
                groupNameContainer.classList.add('hidden');
            }
        });
    }
    
    // Bulk override reason input
    const bulkOverrideReason = document.getElementById('bulk_override_reason');
    if (bulkOverrideReason) {
        bulkOverrideReason.addEventListener('input', function() {
            // Enable/disable submit button based on reason presence when overrides exist
            const hasOverride = document.getElementById('bulk-break-override-warning') && 
                               !document.getElementById('bulk-break-override-warning').classList.contains('hidden') ||
                               document.getElementById('bulk-handover-override-warning') && 
                               !document.getElementById('bulk-handover-override-warning').classList.contains('hidden');
            
            const submitBtn = document.getElementById('bulkAssignSubmitBtn');
            if (hasOverride && submitBtn) {
                if (this.value.trim()) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
        });
    }
}

function toggleRecurrenceOptions(type) {
    const weeklyContainer = document.getElementById('weeklyDaysContainer');
    const monthlyContainer = document.getElementById('monthlyDatesContainer');
    const rotationContainer = document.getElementById('rotationPatternContainer');
    
    if (weeklyContainer) weeklyContainer.classList.add('hidden');
    if (monthlyContainer) monthlyContainer.classList.add('hidden');
    if (rotationContainer) rotationContainer.classList.add('hidden');
    
    if (type === 'weekly' && weeklyContainer) {
        weeklyContainer.classList.remove('hidden');
    } else if (type === 'monthly' && monthlyContainer) {
        monthlyContainer.classList.remove('hidden');
    } else if (type === 'rotating' && rotationContainer) {
        rotationContainer.classList.remove('hidden');
    }
}
</script>
@section('scripts')
<script>
// =============================================
// GLOBAL VARIABLES & STATE
// =============================================
let currentCapacity = 0;
let selectedDate = document.getElementById('assignment_date')?.value || new Date().toISOString().split('T')[0];
let isFormValid = true;
let originalShiftConfig = {
    hasBreaks: false,
    hasHandover: false,
    handoverDuration: 30,
    handoverNotesRequired: false,
    breakSchedule: {}
};
let isBreakOverridden = false;
let isHandoverOverridden = false;

// Audit log for overrides
let overrideAuditLog = [];

// =============================================
// LOADING STATE FUNCTIONS
// =============================================
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
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

// =============================================
// DAY OF WEEK UTILITIES
// =============================================
const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const dayOfWeekIso = {1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday'};

function getDayOfWeek(dateString) {
    const date = new Date(dateString + 'T12:00:00');
    return daysOfWeek[date.getDay()];
}

function getDayOfWeekIso(dateString) {
    const date = new Date(dateString + 'T12:00:00');
    let day = date.getDay();
    return day === 0 ? 7 : day;
}

// =============================================
// SHIFT DEFAULT INDICATOR FUNCTIONS
// =============================================
function updateShiftDefaultIndicators() {
    const shiftSelect = document.getElementById('security_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    
    if (selectedOption.value) {
        // Get shift defaults
        const shiftHasBreaks = selectedOption.dataset.shiftBreakDefault === 'true';
        const shiftHasHandover = selectedOption.dataset.shiftHandoverDefault === 'true';
        const shiftBreakCount = selectedOption.dataset.breakCount || '0';
        const handoverDuration = selectedOption.dataset.handoverDuration || '30';
        
        // Store original config
        originalShiftConfig = {
            hasBreaks: shiftHasBreaks,
            hasHandover: shiftHasHandover,
            handoverDuration: handoverDuration,
            handoverNotesRequired: selectedOption.dataset.handoverNotesRequired === 'true',
            breakSchedule: JSON.parse(selectedOption.dataset.breakSchedule || '{}'),
            breakCount: shiftBreakCount
        };
        
        // Update break indicator
        const breakIndicator = document.getElementById('shift-break-indicator');
        if (breakIndicator) {
            breakIndicator.textContent = shiftHasBreaks ? 
                `Default: Yes (${shiftBreakCount} breaks)` : 
                'Default: No breaks';
            breakIndicator.style.backgroundColor = shiftHasBreaks ? 
                'rgba(var(--success-rgb), 0.1)' : 
                'rgba(var(--secondary-rgb), 0.1)';
            breakIndicator.style.color = shiftHasBreaks ? 
                'var(--success)' : 
                'var(--text-secondary)';
        }
        
        // Auto-populate include_breaks based on shift default
        const includeBreaksCheckbox = document.getElementById('include_breaks');
        if (includeBreaksCheckbox && !includeBreaksCheckbox.dataset.userModified) {
            includeBreaksCheckbox.checked = shiftHasBreaks;
        }
        
        // Reset override state when shift changes
        resetOverrideState();
        
        // Check if current selection matches default
        checkForOverrides();
    }
}

function resetOverrideState() {
    isBreakOverridden = false;
    isHandoverOverridden = false;
    
    const breakOverrideWarning = document.getElementById('break-override-warning');
    const overrideReasonContainer = document.getElementById('break-override-reason-container');
    const overrideSummary = document.getElementById('override-summary');
    
    if (breakOverrideWarning) breakOverrideWarning.classList.add('hidden');
    if (overrideReasonContainer) overrideReasonContainer.classList.add('hidden');
    if (overrideSummary) overrideSummary.classList.add('hidden');
    
    // Clear override reason
    const overrideReason = document.getElementById('break_override_reason');
    if (overrideReason) overrideReason.value = '';
}

function checkForOverrides() {
    const includeBreaksCheckbox = document.getElementById('include_breaks');
    if (!includeBreaksCheckbox || !originalShiftConfig) return;
    
    const currentBreakValue = includeBreaksCheckbox.checked;
    const defaultBreakValue = originalShiftConfig.hasBreaks;
    
    // Check if break setting is overridden
    if (currentBreakValue !== defaultBreakValue) {
        isBreakOverridden = true;
        showBreakOverrideWarning(currentBreakValue);
    } else {
        isBreakOverridden = false;
        hideBreakOverrideWarning();
    }
    
    // Update override summary
    updateOverrideSummary();
}

function showBreakOverrideWarning(currentValue) {
    const warningEl = document.getElementById('break-override-warning');
    const warningText = document.getElementById('break-override-text');
    const reasonContainer = document.getElementById('break-override-reason-container');
    
    if (warningEl && warningText) {
        const action = currentValue ? 'enabling' : 'disabling';
        const defaultState = originalShiftConfig.hasBreaks ? 'has' : 'does not have';
        
        warningText.innerHTML = `
            You are ${action} breaks for this assignment.<br>
            <strong>Shift default:</strong> This shift ${defaultState} breaks configured.<br>
            <strong>Reason required:</strong> Please explain why you're overriding the shift default.
        `;
        warningEl.classList.remove('hidden');
        
        // Show reason input
        if (reasonContainer) {
            reasonContainer.classList.remove('hidden');
            // Make reason required
            const reasonInput = document.getElementById('break_override_reason');
            if (reasonInput) reasonInput.setAttribute('required', 'required');
        }
        
        // Add to audit log
        overrideAuditLog.push({
            field: 'breaks',
            action: action,
            timestamp: new Date().toISOString(),
            from: originalShiftConfig.hasBreaks,
            to: currentValue
        });
    }
}

function hideBreakOverrideWarning() {
    const warningEl = document.getElementById('break-override-warning');
    const reasonContainer = document.getElementById('break-override-reason-container');
    
    if (warningEl) warningEl.classList.add('hidden');
    if (reasonContainer) {
        reasonContainer.classList.add('hidden');
        const reasonInput = document.getElementById('break_override_reason');
        if (reasonInput) {
            reasonInput.removeAttribute('required');
            reasonInput.value = '';
        }
    }
}

function updateOverrideSummary() {
    const overrideSummary = document.getElementById('override-summary');
    const overrideSummaryText = document.getElementById('override-summary-text');
    
    if (!overrideSummary || !overrideSummaryText) return;
    
    const overrides = [];
    
    if (isBreakOverridden) {
        overrides.push(`Break schedule: ${document.getElementById('include_breaks').checked ? 'Enabled' : 'Disabled'} (shift default: ${originalShiftConfig.hasBreaks ? 'Enabled' : 'Disabled'})`);
    }
    
    // Add handover override check here when implemented
    
    if (overrides.length > 0) {
        overrideSummaryText.innerHTML = overrides.map(o => `<div>• ${o}</div>`).join('');
        overrideSummary.classList.remove('hidden');
    } else {
        overrideSummary.classList.add('hidden');
    }
}

// =============================================
// POST CAPACITY FUNCTIONS
// =============================================
async function updatePostCapacity() {
    const postSelect = document.getElementById('security_post_id');
    const dateInput = document.getElementById('assignment_date');
    const postId = postSelect?.value;
    const date = dateInput?.value;
    
    const postCapacityInfo = document.getElementById('post-capacity-info');
    const postQuickStats = document.getElementById('post-quick-stats');
    const postEquipment = document.getElementById('post-equipment');
    
    if (!postId || !date) {
        if (postCapacityInfo) postCapacityInfo.classList.add('hidden');
        if (postQuickStats) postQuickStats.classList.add('hidden');
        if (postEquipment) postEquipment.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`/admin/security-schedules/post-capacity?post_id=${postId}&date=${date}`);
        const data = await response.json();
        
        if (data.success) {
            currentCapacity = data.currently_assigned || 0;
            const max = data.max_personnel || 0;
            const available = data.available_slots || 0;
            
            const currentAssignments = document.getElementById('current-assignments');
            const maxPersonnel = document.getElementById('max-personnel');
            const capacityProgress = document.getElementById('capacity-progress');
            
            if (currentAssignments) currentAssignments.textContent = currentCapacity;
            if (maxPersonnel) maxPersonnel.textContent = max;
            
            const capacityPercentage = max > 0 ? (currentCapacity / max) * 100 : 0;
            if (capacityProgress) capacityProgress.style.width = `${Math.min(capacityPercentage, 100)}%`;
            
            if (capacityProgress) {
                if (capacityPercentage >= 90) {
                    capacityProgress.style.backgroundColor = 'var(--danger)';
                } else if (capacityPercentage >= 75) {
                    capacityProgress.style.backgroundColor = 'var(--warning)';
                } else {
                    capacityProgress.style.backgroundColor = 'var(--success)';
                }
            }
            
            const capacityWarning = document.getElementById('capacity-warning');
            const capacityWarningText = document.getElementById('capacity-warning-text');
            
            if (capacityWarning && capacityWarningText) {
                if (available <= 0) {
                    capacityWarning.classList.remove('hidden');
                    capacityWarningText.textContent = 'This post is at full capacity for the selected date.';
                    if (capacityProgress) capacityProgress.style.backgroundColor = 'var(--danger)';
                } else if (available < 3) {
                    capacityWarning.classList.remove('hidden');
                    capacityWarningText.textContent = `Only ${available} slot(s) available for this post on the selected date.`;
                    if (capacityProgress) capacityProgress.style.backgroundColor = 'var(--warning)';
                } else {
                    capacityWarning.classList.add('hidden');
                }
            }
            
            if (postCapacityInfo) postCapacityInfo.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Failed to fetch post capacity:', error);
    }
}

function updatePostDetails() {
    const postSelect = document.getElementById('security_post_id');
    if (!postSelect || !postSelect.options[postSelect.selectedIndex]) return;
    
    const selectedOption = postSelect.options[postSelect.selectedIndex];
    
    if (selectedOption.value) {
        const postType = selectedOption.dataset.postType || 'Standard';
        const location = selectedOption.dataset.postLocation || 'N/A';
        const equipment = JSON.parse(selectedOption.dataset.postEquipment || '[]');
        
        const postTypeDisplay = document.getElementById('post-type-display');
        const postLocationDisplay = document.getElementById('post-location-display');
        const postEquipmentList = document.getElementById('post-equipment-list');
        const postEquipment = document.getElementById('post-equipment');
        const postQuickStats = document.getElementById('post-quick-stats');
        
        if (postTypeDisplay) postTypeDisplay.textContent = postType.replace('_', ' ').toUpperCase();
        if (postLocationDisplay) postLocationDisplay.textContent = location;
        
        if (equipment.length > 0 && postEquipmentList && postEquipment) {
            postEquipmentList.innerHTML = equipment.map(item => 
                `<span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">${item}</span>`
            ).join('');
            postEquipment.classList.remove('hidden');
        } else if (postEquipment) {
            postEquipment.classList.add('hidden');
        }
        
        if (postQuickStats) postQuickStats.classList.remove('hidden');
    } else {
        const postQuickStats = document.getElementById('post-quick-stats');
        const postEquipment = document.getElementById('post-equipment');
        if (postQuickStats) postQuickStats.classList.add('hidden');
        if (postEquipment) postEquipment.classList.add('hidden');
    }
}

// =============================================
// SHIFT FUNCTIONS
// =============================================
function updateShiftDetails() {
    const shiftSelect = document.getElementById('security_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    
    if (selectedOption.value) {
        const startTime = selectedOption.dataset.startTime?.substring(0, 5) || '--:--';
        const endTime = selectedOption.dataset.endTime?.substring(0, 5) || '--:--';
        const duration = selectedOption.dataset.duration || '0';
        const category = selectedOption.dataset.category || 'standard';
        const isOvernight = selectedOption.dataset.isOvernight === 'true';
        const hasHandover = selectedOption.dataset.hasHandover === 'true';
        const handoverDuration = selectedOption.dataset.handoverDuration || '30';
        const rotationType = selectedOption.dataset.rotationType || 'fixed';
        const applicableDays = JSON.parse(selectedOption.dataset.applicableDays || '[]');
        const rotationConfig = JSON.parse(selectedOption.dataset.rotationConfig || '{}');
        const breakSchedule = JSON.parse(selectedOption.dataset.breakSchedule || '{}');
        const hasBreaks = selectedOption.dataset.hasBreaks === 'true';
        
        const shiftTimeDisplay = document.getElementById('shift-time-display');
        const shiftDurationDisplay = document.getElementById('shift-duration-display');
        const shiftCategoryDisplay = document.getElementById('shift-category-display');
        const shiftHandoverDisplay = document.getElementById('shift-handover-display');
        const overnightWarning = document.getElementById('overnight-warning');
        const shiftDetails = document.getElementById('shift-details');
        
        if (shiftTimeDisplay) shiftTimeDisplay.textContent = `${startTime} - ${endTime}`;
        if (shiftDurationDisplay) shiftDurationDisplay.textContent = `${duration} hours`;
        if (shiftCategoryDisplay) shiftCategoryDisplay.textContent = category.charAt(0).toUpperCase() + category.slice(1);
        if (shiftHandoverDisplay) shiftHandoverDisplay.textContent = hasHandover ? `Yes (${handoverDuration}min)` : 'No';
        
        if (overnightWarning) {
            if (isOvernight) {
                overnightWarning.classList.remove('hidden');
            } else {
                overnightWarning.classList.add('hidden');
            }
        }
        
        // Update break information
        updateBreakInfo(hasBreaks, breakSchedule);
        
        // Check shift applicability - FIXED: Only check if applicableDays has values
        const dateInput = document.getElementById('assignment_date');
        const applicabilityWarning = document.getElementById('applicability-warning');
        const applicabilityWarningText = document.getElementById('applicability-warning-text');
        
        if (dateInput?.value && applicabilityWarning && applicabilityWarningText) {
            const dayIso = getDayOfWeekIso(dateInput.value);
            
            // Only show warning if applicableDays is not empty
            // Empty array or null means the shift is applicable on all days
            if (applicableDays && applicableDays.length > 0) {
                const isApplicable = applicableDays.includes(dayIso);
                
                if (!isApplicable) {
                    applicabilityWarning.classList.remove('hidden');
                    applicabilityWarningText.textContent = `This shift is not applicable on ${getDayOfWeek(dateInput.value)}s.`;
                } else {
                    applicabilityWarning.classList.add('hidden');
                }
            } else {
                // No applicable days specified - shift is applicable on all days
                applicabilityWarning.classList.add('hidden');
            }
        }
        
        // Update rotation options
        const rotationOptions = document.getElementById('rotation-options');
        if (rotationType === 'rotating') {
            if (rotationOptions) rotationOptions.classList.remove('hidden');
            updateRotationInfo(rotationConfig);
            
            const rotationGroupType = document.getElementById('rotation_group_type');
            const rotationSequenceNumber = document.getElementById('rotation_sequence_number');
            
            if (rotationGroupType) rotationGroupType.value = category;
            if (rotationSequenceNumber) rotationSequenceNumber.value = rotationConfig.current_sequence_index || 0;
        } else {
            if (rotationOptions) rotationOptions.classList.add('hidden');
            const rotationGroupType = document.getElementById('rotation_group_type');
            const rotationSequenceNumber = document.getElementById('rotation_sequence_number');
            const isRotated = document.getElementById('is_rotated');
            
            if (rotationGroupType) rotationGroupType.value = '';
            if (rotationSequenceNumber) rotationSequenceNumber.value = 0;
            if (isRotated) isRotated.value = 0;
        }
        
        if (shiftDetails) shiftDetails.classList.remove('hidden');
        
        // Update shift default indicators
        updateShiftDefaultIndicators();
        
        // Update handover info
        updateHandoverInfo();
    } else {
        const shiftDetails = document.getElementById('shift-details');
        if (shiftDetails) shiftDetails.classList.add('hidden');
        
        const rotationOptions = document.getElementById('rotation-options');
        if (rotationOptions) rotationOptions.classList.add('hidden');
    }
}

function updateBreakInfo(hasBreaks, breakSchedule) {
    const breakInfo = document.getElementById('break-info');
    const breakList = document.getElementById('break-list');
    
    if (hasBreaks && breakSchedule.breaks && breakSchedule.breaks.length > 0) {
        if (breakList) {
            breakList.innerHTML = breakSchedule.breaks.map((break_item, index) => 
                `<div class="text-xs">${break_item.name}: ${break_item.start_time} - ${break_item.end_time} (${break_item.duration_minutes} min) ${break_item.is_paid ? '💰' : ''}</div>`
            ).join('');
        }
        if (breakInfo) breakInfo.classList.remove('hidden');
        
        // Update break summary
        const totalBreakMinutes = breakSchedule.breaks.reduce((sum, b) => sum + (b.duration_minutes || 0), 0);
        const breakSummaryText = document.getElementById('break-summary-text');
        const breakSummary = document.getElementById('break-summary');
        
        if (breakSummaryText) breakSummaryText.textContent = `${breakSchedule.breaks.length} breaks, total ${Math.floor(totalBreakMinutes / 60)}h ${totalBreakMinutes % 60}m`;
        if (breakSummary) breakSummary.classList.remove('hidden');
    } else {
        if (breakInfo) breakInfo.classList.add('hidden');
        const breakSummary = document.getElementById('break-summary');
        if (breakSummary) breakSummary.classList.add('hidden');
    }
}

function updateRotationInfo(rotationConfig) {
    const sequence = rotationConfig.rotation_sequence || 'morning_evening';
    const sequenceMap = {
        'morning_evening': ['Morning', 'Evening', 'Night', 'Off'],
        'evening_morning': ['Evening', 'Morning', 'Night', 'Off'],
        'night_morning': ['Night', 'Morning', 'Evening', 'Off']
    };
    
    const sequenceDisplay = sequenceMap[sequence] || ['Morning', 'Evening', 'Night', 'Off'];
    const rotationDays = rotationConfig.rotation_days || 7;
    
    const currentRotationPosition = document.getElementById('current-rotation-position');
    if (currentRotationPosition) currentRotationPosition.textContent = sequenceDisplay[rotationConfig.current_sequence_index || 0];
    
    // Calculate next rotation date
    const today = new Date();
    const nextRotation = new Date(today);
    nextRotation.setDate(today.getDate() + rotationDays);
    
    const nextRotationDate = document.getElementById('next-rotation-date');
    if (nextRotationDate) {
        nextRotationDate.textContent = nextRotation.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }
}

async function updateHandoverInfo() {
    const shiftSelect = document.getElementById('security_shift_id');
    const dateInput = document.getElementById('assignment_date');
    const postSelect = document.getElementById('security_post_id');
    
    const shiftId = shiftSelect?.value;
    const date = dateInput?.value;
    const postId = postSelect?.value;
    
    const handoverPreview = document.getElementById('handover-preview-container');
    
    if (!shiftId || !date || !postId) {
        if (handoverPreview) handoverPreview.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`/admin/security-schedules/handover-info?shift_id=${shiftId}&date=${date}&post_id=${postId}`);
        const data = await response.json();
        
        if (data.success && data.handover_info) {
            const handoverInfo = data.handover_info;
            
            const handoverStartDisplay = document.getElementById('handover-start-display');
            const handoverEndDisplay = document.getElementById('handover-end-display');
            const handoverDurationDisplay = document.getElementById('handover-duration-display');
            const handoverWindowDisplay = document.getElementById('handover-window-display');
            const handoverNotesRequired = document.getElementById('handover-notes-required');
            const handoverChecklistPreview = document.getElementById('handover-checklist-preview');
            const checklistItems = document.getElementById('checklist-items');
            
            if (handoverStartDisplay) handoverStartDisplay.textContent = handoverInfo.handover_start || '--:--';
            if (handoverEndDisplay) handoverEndDisplay.textContent = handoverInfo.handover_end || '--:--';
            if (handoverDurationDisplay) handoverDurationDisplay.textContent = `${handoverInfo.handover_duration || 30} minutes`;
            if (handoverWindowDisplay) handoverWindowDisplay.textContent = handoverInfo.handover_window ? `${handoverInfo.handover_window} minutes` : '--';
            
            if (handoverNotesRequired) {
                if (handoverInfo.notes_required) {
                    handoverNotesRequired.classList.remove('hidden');
                } else {
                    handoverNotesRequired.classList.add('hidden');
                }
            }
            
            if (handoverInfo.checklist && handoverInfo.checklist.length > 0) {
                if (checklistItems) {
                    checklistItems.innerHTML = handoverInfo.checklist.map(item => 
                        `<div class="flex items-center"><i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> ${item}</div>`
                    ).join('');
                }
                if (handoverChecklistPreview) handoverChecklistPreview.classList.remove('hidden');
            } else {
                if (handoverChecklistPreview) handoverChecklistPreview.classList.add('hidden');
            }
            
            if (handoverPreview) handoverPreview.classList.remove('hidden');
        } else {
            if (handoverPreview) handoverPreview.classList.add('hidden');
        }
    } catch (error) {
        console.error('Failed to fetch handover info:', error);
        if (handoverPreview) handoverPreview.classList.add('hidden');
    }
}

// =============================================
// PERSONNEL FUNCTIONS
// =============================================
function updatePersonnelInfo() {
    const personnelSelect = document.getElementById('security_user_id');
    if (!personnelSelect || !personnelSelect.options[personnelSelect.selectedIndex]) return;
    
    const selectedOption = personnelSelect.options[personnelSelect.selectedIndex];
    
    if (selectedOption.value) {
        const phone = selectedOption.dataset.phone || 'N/A';
        const badge = selectedOption.dataset.badge || 'N/A';
        const lastShift = selectedOption.dataset.lastShift || 'No previous shifts';
        const totalShifts = selectedOption.dataset.shiftsCount || '0';
        const willingToRotate = selectedOption.dataset.willingToRotate === 'true';
        const rotationPref = selectedOption.dataset.rotationPreference || 'neutral';
        const prefersNight = selectedOption.dataset.prefersNight === 'true';
        const prefersDay = selectedOption.dataset.prefersDay === 'true';
        const willingDayToNight = selectedOption.dataset.willingDayToNight === 'true';
        const willingNightToDay = selectedOption.dataset.willingNightToDay === 'true';
        const joinedDate = selectedOption.dataset.joinedDate;
        const performance = selectedOption.dataset.performanceRating || '7';
        
        const personnelPhone = document.getElementById('personnel-phone');
        const personnelBadge = document.getElementById('personnel-badge');
        const personnelLastShift = document.getElementById('personnel-last-shift');
        const personnelTotalShifts = document.getElementById('personnel-total-shifts');
        const seniorityYears = document.getElementById('seniority-years');
        const performanceRating = document.getElementById('performance-rating');
        const seniorityPerformance = document.getElementById('seniority-performance');
        const rotationPreference = document.getElementById('rotation-preference');
        const rotationPreferenceText = document.getElementById('rotation-preference-text');
        const personnelInfo = document.getElementById('personnel-info');
        
        if (personnelPhone) personnelPhone.textContent = phone;
        if (personnelBadge) personnelBadge.textContent = badge;
        if (personnelLastShift) personnelLastShift.textContent = lastShift;
        if (personnelTotalShifts) personnelTotalShifts.textContent = totalShifts;
        
        // Update seniority and performance
        if (joinedDate && seniorityYears) {
            const joinDate = new Date(joinedDate);
            const today = new Date();
            const yearsEmployed = (today - joinDate) / (1000 * 60 * 60 * 24 * 365);
            seniorityYears.textContent = yearsEmployed.toFixed(1) + ' years';
        }
        
        if (performance && performanceRating) {
            performanceRating.textContent = performance + '/10';
        }
        
        if (seniorityPerformance) seniorityPerformance.classList.remove('hidden');
        
        // Update rotation preference
        if (willingToRotate) {
            if (rotationPreference) rotationPreference.classList.remove('hidden');
            let prefText = 'Willing to rotate';
            if (willingDayToNight) {
                prefText = 'Willing to rotate: Day → Night';
            } else if (willingNightToDay) {
                prefText = 'Willing to rotate: Night → Day';
            } else if (prefersNight) {
                prefText = 'Prefers night shifts';
            } else if (prefersDay) {
                prefText = 'Prefers day shifts';
            } else if (rotationPref === 'any') {
                prefText = 'Open to any rotation';
            }
            if (rotationPreferenceText) rotationPreferenceText.textContent = prefText;
        } else {
            if (rotationPreference) rotationPreference.classList.add('hidden');
        }
        
        if (personnelInfo) personnelInfo.classList.remove('hidden');
        
        // Calculate preference score
        calculatePreferenceScore(selectedOption);
        checkPersonnelAvailability();
    } else {
        const personnelInfo = document.getElementById('personnel-info');
        const rotationPreference = document.getElementById('rotation-preference');
        const availabilityChecker = document.getElementById('availability-checker');
        const restPeriodWarning = document.getElementById('rest-period-warning');
        const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
        const rotationScoreContainer = document.getElementById('rotation-score-container');
        const seniorityPerformance = document.getElementById('seniority-performance');
        
        if (personnelInfo) personnelInfo.classList.add('hidden');
        if (rotationPreference) rotationPreference.classList.add('hidden');
        if (availabilityChecker) availabilityChecker.classList.add('hidden');
        if (restPeriodWarning) restPeriodWarning.classList.add('hidden');
        if (weeklyHoursWarning) weeklyHoursWarning.classList.add('hidden');
        if (rotationScoreContainer) rotationScoreContainer.classList.add('hidden');
        if (seniorityPerformance) seniorityPerformance.classList.add('hidden');
    }
}

function calculatePreferenceScore(selectedOption) {
    const shiftSelect = document.getElementById('security_shift_id');
    const postSelect = document.getElementById('security_post_id');
    
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const shiftCategory = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.category || '';
    const preferredShifts = JSON.parse(selectedOption.dataset.preferredShifts || '[]');
    const preferredPosts = JSON.parse(selectedOption.dataset.preferredPosts || '[]');
    const willingToRotate = selectedOption.dataset.willingToRotate === 'true';
    const willingDayToNight = selectedOption.dataset.willingDayToNight === 'true';
    const willingNightToDay = selectedOption.dataset.willingNightToDay === 'true';
    const prefersNight = selectedOption.dataset.prefersNight === 'true';
    const prefersDay = selectedOption.dataset.prefersDay === 'true';
    const joinedDate = selectedOption.dataset.joinedDate;
    const performance = parseFloat(selectedOption.dataset.performanceRating || '7');
    const postId = postSelect?.value;
    
    // Calculate seniority score (0-2)
    let seniorityScore = 0;
    if (joinedDate) {
        const joinDate = new Date(joinedDate);
        const today = new Date();
        const yearsEmployed = (today - joinDate) / (1000 * 60 * 60 * 24 * 365);
        seniorityScore = Math.min(2, yearsEmployed);
    }
    
    // Calculate performance score (0-2)
    const performanceScore = (performance - 5) / 2.5; // 5=0, 7.5=1, 10=2
    
    // Calculate shift preference score (0-3)
    let shiftPreferenceScore = 0;
    if (preferredShifts.includes(shiftCategory)) {
        shiftPreferenceScore += 2;
    }
    if (preferredPosts.includes(parseInt(postId))) {
        shiftPreferenceScore += 1;
    }
    
    // Calculate rotation willingness score (0-3)
    let rotationScore = 0;
    if (willingToRotate) {
        rotationScore += 1;
        if (shiftCategory === 'night' && willingDayToNight) {
            rotationScore += 2;
        } else if (shiftCategory === 'day' && willingNightToDay) {
            rotationScore += 2;
        } else if (shiftCategory === 'night' && prefersNight) {
            rotationScore += 1;
        } else if (shiftCategory === 'day' && prefersDay) {
            rotationScore += 1;
        }
    }
    
    // Update score breakdown
    const scoreSeniority = document.getElementById('score-seniority');
    const scorePerformance = document.getElementById('score-performance');
    const scoreShiftPreference = document.getElementById('score-shift-preference');
    const scoreRotation = document.getElementById('score-rotation');
    const scoreBreakdown = document.getElementById('score-breakdown');
    
    if (scoreSeniority) scoreSeniority.textContent = '+' + seniorityScore.toFixed(1);
    if (scorePerformance) scorePerformance.textContent = '+' + performanceScore.toFixed(1);
    if (scoreShiftPreference) scoreShiftPreference.textContent = '+' + shiftPreferenceScore.toFixed(1);
    if (scoreRotation) scoreRotation.textContent = '+' + rotationScore.toFixed(1);
    
    if (scoreBreakdown) scoreBreakdown.classList.remove('hidden');
    
    // Calculate total score (base 5 + bonuses)
    let score = 5 + seniorityScore + performanceScore + shiftPreferenceScore + rotationScore;
    
    // Ensure score is between 1-10
    score = Math.max(1, Math.min(10, score));
    
    // Update UI
    const preferenceScoreBar = document.getElementById('preference-score-bar');
    const preferenceScoreValue = document.getElementById('preference-score-value');
    const preferenceScoreText = document.getElementById('preference-score-text');
    const rotationScoreContainer = document.getElementById('rotation-score-container');
    
    if (preferenceScoreBar && preferenceScoreValue) {
        const percentage = (score / 10) * 100;
        preferenceScoreBar.style.width = `${percentage}%`;
        preferenceScoreValue.textContent = `${score.toFixed(1)}/10`;
    }
    
    if (preferenceScoreText) {
        let scoreText = 'Based on personnel preferences';
        if (score >= 8) {
            if (preferenceScoreBar) preferenceScoreBar.style.backgroundColor = 'var(--success)';
            scoreText = 'High match - Personnel strongly prefers this assignment';
        } else if (score >= 5) {
            if (preferenceScoreBar) preferenceScoreBar.style.backgroundColor = 'var(--warning)';
            scoreText = 'Medium match - Personnel is neutral about this assignment';
        } else {
            if (preferenceScoreBar) preferenceScoreBar.style.backgroundColor = 'var(--danger)';
            scoreText = 'Low match - Personnel may not prefer this assignment';
        }
        preferenceScoreText.textContent = scoreText;
    }
    
    // Set hidden input value
    const rotationPreferenceScore = document.getElementById('rotation_preference_score');
    if (rotationPreferenceScore) rotationPreferenceScore.value = score.toFixed(1);
    
    if (rotationScoreContainer) rotationScoreContainer.classList.remove('hidden');
}

async function checkPersonnelAvailability() {
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const shiftSelect = document.getElementById('security_shift_id');
    
    const userId = personnelSelect?.value;
    const date = dateInput?.value;
    const shiftId = shiftSelect?.value;
    
    const availabilityChecker = document.getElementById('availability-checker');
    const availabilityBadge = document.getElementById('availability-badge');
    const availabilityDetails = document.getElementById('availability-details');
    const personnelAvailability = document.getElementById('personnel-availability');
    const personnelAvailabilityDetail = document.getElementById('personnel-availability-detail');
    const restPeriodWarning = document.getElementById('rest-period-warning');
    const restPeriodText = document.getElementById('rest-period-text');
    const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
    const weeklyHoursText = document.getElementById('weekly-hours-text');
    
    if (!userId || !date) {
        if (availabilityChecker) availabilityChecker.classList.add('hidden');
        return;
    }

    try {
        if (availabilityChecker) availabilityChecker.classList.remove('hidden');
        if (availabilityBadge) {
            availabilityBadge.textContent = 'Checking...';
            availabilityBadge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
            availabilityBadge.style.color = 'var(--warning)';
        }
        if (availabilityDetails) availabilityDetails.textContent = 'Verifying availability and constraints...';
        
        const existingResponse = await fetch(`/admin/security-schedules/check-availability?user_id=${userId}&date=${date}`);
        const existingData = await existingResponse.json();
        
        let isAvailable = true;
        
        if (existingData.has_assignment) {
            isAvailable = false;
            if (personnelAvailability) {
                personnelAvailability.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--danger)';
                const icon = document.querySelector('#personnel-availability i');
                if (icon) icon.style.color = 'var(--danger)';
                const span = document.querySelector('#personnel-availability span');
                if (span) {
                    span.textContent = 'Unavailable';
                    span.style.color = 'var(--danger)';
                }
            }
            if (personnelAvailabilityDetail) personnelAvailabilityDetail.textContent = 'Already assigned to another post on this date.';
            
            if (availabilityBadge) {
                availabilityBadge.textContent = 'Unavailable';
                availabilityBadge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                availabilityBadge.style.color = 'var(--danger)';
            }
            if (availabilityDetails) availabilityDetails.textContent = 'Personnel already has an assignment on this date.';
        } else {
            if (personnelAvailability) {
                personnelAvailability.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--success)';
                const icon = document.querySelector('#personnel-availability i');
                if (icon) icon.style.color = 'var(--success)';
                const span = document.querySelector('#personnel-availability span');
                if (span) {
                    span.textContent = 'Available';
                    span.style.color = 'var(--success)';
                }
            }
            if (personnelAvailabilityDetail) personnelAvailabilityDetail.textContent = 'No conflicting assignments found.';
            
            if (availabilityBadge) {
                availabilityBadge.textContent = 'Available';
                availabilityBadge.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                availabilityBadge.style.color = 'var(--success)';
            }
            if (availabilityDetails) availabilityDetails.textContent = 'Personnel is available for this date.';
        }
        
        // Check rest period
        if (shiftId && shiftSelect && shiftSelect.options[shiftSelect.selectedIndex]) {
            const selectedShift = shiftSelect.options[shiftSelect.selectedIndex];
            const shiftDuration = parseFloat(selectedShift.dataset.duration || '0');
            
            if (existingData.last_shift_date) {
                const lastShiftDate = new Date(existingData.last_shift_date + 'T12:00:00');
                const currentDate = new Date(date + 'T12:00:00');
                const hoursDiff = (currentDate - lastShiftDate) / (1000 * 60 * 60);
                const minRestHours = 12;
                
                if (hoursDiff < minRestHours) {
                    if (restPeriodWarning) restPeriodWarning.classList.remove('hidden');
                    if (restPeriodText) restPeriodText.textContent = `Only ${Math.round(hoursDiff)} hours since last shift. Minimum rest period is ${minRestHours} hours.`;
                    isAvailable = false;
                } else {
                    if (restPeriodWarning) restPeriodWarning.classList.add('hidden');
                }
            }
            
            // Check weekly hours
            if (existingData.weekly_hours !== undefined) {
                const weeklyHours = existingData.weekly_hours || 0;
                const maxWeeklyHours = 60;
                
                if ((weeklyHours + shiftDuration) > maxWeeklyHours) {
                    if (weeklyHoursWarning) weeklyHoursWarning.classList.remove('hidden');
                    if (weeklyHoursText) weeklyHoursText.textContent = `Current week: ${weeklyHours}h, Adding: ${shiftDuration}h, Total: ${weeklyHours + shiftDuration}h (Max: ${maxWeeklyHours}h)`;
                    isAvailable = false;
                } else {
                    if (weeklyHoursWarning) weeklyHoursWarning.classList.add('hidden');
                }
            }
        }
        
        if (!isAvailable && availabilityBadge) {
            availabilityBadge.textContent = 'Constraints Detected';
            availabilityBadge.style.backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
            availabilityBadge.style.color = 'var(--warning)';
        }
        
    } catch (error) {
        console.error('Failed to check availability:', error);
        if (availabilityDetails) availabilityDetails.textContent = 'Could not verify availability. Please proceed with caution.';
    }
}

// =============================================
// ASSIGNMENT SUMMARY FUNCTIONS
// =============================================
function updateAssignmentSummary() {
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const includeBreaksCheckbox = document.getElementById('include_breaks');
    
    const hasPost = postSelect?.value;
    const hasShift = shiftSelect?.value;
    const hasPersonnel = personnelSelect?.value;
    const hasDate = dateInput?.value;
    
    const assignmentSummary = document.getElementById('assignment-summary');
    
    if (hasPost && hasShift && hasPersonnel && hasDate && 
        postSelect.options[postSelect.selectedIndex] && 
        shiftSelect.options[shiftSelect.selectedIndex] && 
        personnelSelect.options[personnelSelect.selectedIndex]) {
            
        const postText = postSelect.options[postSelect.selectedIndex]?.text.split('-')[0].trim() || 'Not selected';
        const shiftText = shiftSelect.options[shiftSelect.selectedIndex]?.text.split('(')[0].trim() || 'Not selected';
        const personnelText = personnelSelect.options[personnelSelect.selectedIndex]?.text.split('-')[0].trim() || 'Not selected';
        const shiftDuration = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.duration || '0';
        const rotationType = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.rotationType || 'fixed';
        const hasBreaks = shiftSelect.options[shiftSelect.selectedIndex]?.dataset.hasBreaks === 'true';
        
        const summaryPost = document.getElementById('summary-post');
        const summaryDatetime = document.getElementById('summary-datetime');
        const summaryPersonnel = document.getElementById('summary-personnel');
        const summaryDuration = document.getElementById('summary-duration');
        
        if (summaryPost) summaryPost.textContent = postText;
        if (summaryDatetime) summaryDatetime.textContent = `${new Date(dateInput.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${shiftText}`;
        if (summaryPersonnel) summaryPersonnel.textContent = personnelText;
        if (summaryDuration) summaryDuration.textContent = `${shiftDuration} hours`;
        
        // Update rotation summary
        const rotationSummary = document.getElementById('rotation-summary');
        const rotationSummaryText = document.getElementById('rotation-summary-text');
        
        if (rotationType === 'rotating') {
            if (rotationSummary) rotationSummary.classList.remove('hidden');
            if (rotationSummaryText) rotationSummaryText.textContent = 'Part of rotating shift schedule';
        } else {
            if (rotationSummary) rotationSummary.classList.add('hidden');
        }
        
        // Update break summary visibility based on include_breaks checkbox
        const breakSummary = document.getElementById('break-summary');
        if (includeBreaksCheckbox && includeBreaksCheckbox.checked && hasBreaks) {
            if (breakSummary) breakSummary.classList.remove('hidden');
        } else {
            if (breakSummary) breakSummary.classList.add('hidden');
        }
        
        // Update timeline
        updateTimelinePreview(shiftSelect.options[shiftSelect.selectedIndex], dateInput.value);
        
        if (assignmentSummary) assignmentSummary.classList.remove('hidden');
        
        // Validate form after summary update
        validateForm();
    } else {
        if (assignmentSummary) assignmentSummary.classList.add('hidden');
    }
}

function updateTimelinePreview(selectedShift, dateValue) {
    const timelineStart = document.getElementById('timeline-start');
    const timelineEnd = document.getElementById('timeline-end');
    const timelineProgress = document.getElementById('timeline-progress');
    const shiftTimelineStatus = document.getElementById('shift-timeline-status');
    
    if (selectedShift) {
        const startTime = selectedShift.dataset.startTime?.substring(0, 5) || '00:00';
        const endTime = selectedShift.dataset.endTime?.substring(0, 5) || '00:00';
        
        if (timelineStart) timelineStart.textContent = startTime;
        if (timelineEnd) timelineEnd.textContent = endTime;
        
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        const currentTimeDecimal = currentHour + currentMinute / 60;
        
        const [startHour, startMinute] = startTime.split(':').map(Number);
        const startDecimal = startHour + startMinute / 60;
        const [endHour, endMinute] = endTime.split(':').map(Number);
        let endDecimal = endHour + endMinute / 60;
        
        if (endDecimal < startDecimal) {
            endDecimal += 24;
        }
        
        let progress = 0;
        if (dateValue === new Date().toISOString().split('T')[0]) {
            if (currentTimeDecimal < startDecimal) {
                progress = 0;
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Not started';
            } else if (currentTimeDecimal > endDecimal) {
                progress = 100;
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Ended';
            } else {
                progress = ((currentTimeDecimal - startDecimal) / (endDecimal - startDecimal)) * 100;
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'In progress';
            }
        } else {
            const selectedDate = new Date(dateValue);
            const today = new Date();
            if (selectedDate > today) {
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Scheduled';
            } else if (selectedDate < today) {
                if (shiftTimelineStatus) shiftTimelineStatus.textContent = 'Past';
            }
        }
        
        if (timelineProgress) timelineProgress.style.width = `${Math.min(progress, 100)}%`;
    }
}

// =============================================
// FORM VALIDATION FUNCTIONS
// =============================================
function validateForm() {
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    
    let isValid = true;
    let warnings = [];
    
    if (!postSelect?.value) {
        isValid = false;
        warnings.push('Security Post is required');
    }
    
    if (!shiftSelect?.value) {
        isValid = false;
        warnings.push('Security Shift is required');
    }
    
    if (!personnelSelect?.value) {
        isValid = false;
        warnings.push('Security Personnel is required');
    }
    
    if (!dateInput?.value) {
        isValid = false;
        warnings.push('Assignment Date is required');
    }
    
    const maxPersonnel = document.getElementById('max-personnel');
    if (postSelect?.value && dateInput?.value && maxPersonnel) {
        const max = parseInt(maxPersonnel.textContent || '0');
        if (currentCapacity >= max && max > 0) {
            isValid = false;
            warnings.push('Selected post is at full capacity for this date');
        }
    }
    
    if (shiftSelect?.value && dateInput?.value && shiftSelect.options[shiftSelect.selectedIndex]) {
        const selectedShift = shiftSelect.options[shiftSelect.selectedIndex];
        if (selectedShift) {
            // FIXED: Only validate applicability if applicableDays has values
            const applicableDays = JSON.parse(selectedShift.dataset.applicableDays || '[]');
            const dayIso = getDayOfWeekIso(dateInput.value);
            
            // Only check if applicableDays is not empty
            if (applicableDays && applicableDays.length > 0) {
                if (!applicableDays.includes(dayIso)) {
                    isValid = false;
                    warnings.push('Selected shift is not applicable on this day');
                }
            }
            // If applicableDays is empty, shift is applicable on all days - no validation needed
        }
    }
    
    if (personnelSelect?.value && dateInput?.value) {
        const restPeriodWarning = document.getElementById('rest-period-warning');
        const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
        
        if (restPeriodWarning && !restPeriodWarning.classList.contains('hidden')) {
            isValid = false;
            warnings.push('Insufficient rest period for selected personnel');
        }
        
        if (weeklyHoursWarning && !weeklyHoursWarning.classList.contains('hidden')) {
            isValid = false;
            warnings.push('Weekly hour limit would be exceeded');
        }
        
        const availabilitySpan = document.querySelector('#personnel-availability span');
        if (availabilitySpan && availabilitySpan.textContent === 'Unavailable') {
            isValid = false;
            warnings.push('Selected personnel is already assigned on this date');
        }
    }
    
    // Check for override reason when break is overridden
    if (isBreakOverridden) {
        const overrideReason = document.getElementById('break_override_reason');
        if (!overrideReason || !overrideReason.value.trim()) {
            isValid = false;
            warnings.push('Override reason is required when modifying break schedule');
        }
    }
    
    const submitWarnings = document.getElementById('submit-warnings');
    const submitWarningText = document.getElementById('submit-warning-text');
    const submitButton = document.getElementById('submit-button');
    const formStatus = document.getElementById('form-status');
    
    if (warnings.length > 0 && submitWarnings && submitWarningText) {
        submitWarnings.classList.remove('hidden');
        submitWarningText.textContent = warnings.join('. ') + '.';
        
        if (submitButton) {
            submitButton.disabled = !isValid;
            submitButton.classList.toggle('opacity-50', !isValid);
            submitButton.classList.toggle('cursor-not-allowed', !isValid);
        }
        
        if (formStatus) {
            if (!isValid) {
                formStatus.innerHTML = `
                    <div class="flex items-center text-red-600">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>Please fix validation errors before submitting</span>
                    </div>
                `;
            } else {
                formStatus.innerHTML = `
                    <div class="flex items-center text-yellow-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>Ready to submit (warnings only)</span>
                    </div>
                `;
            }
        }
    } else if (submitWarnings) {
        submitWarnings.classList.add('hidden');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (formStatus) {
            formStatus.innerHTML = `
                <div class="flex items-center text-green-600">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Ready to submit</span>
                </div>
            `;
        }
    }
    
    isFormValid = isValid;
    return isValid;
}

// =============================================
// BULK ASSIGN FUNCTIONS WITH OVERRIDE HANDLING
// =============================================
window.showBulkAssignModal = function() {
    updateBulkShiftDefaultIndicators();
    // FIXED: Automatically update weekly days based on selected shift
    updateWeeklyDaysFromShift();
    updateBulkAssignSummary();
    openModal('bulkAssignModal');
};

// =============================================
// FIXED: FUNCTION TO UPDATE WEEKLY DAYS FROM SHIFT
// =============================================
function updateWeeklyDaysFromShift() {
    const shiftSelect = document.getElementById('bulk_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    const applicableDays = JSON.parse(selectedOption.dataset.applicableDays || '[]');
    
    // If the shift has specific applicable days, use them
    if (applicableDays && applicableDays.length > 0) {
        // First, uncheck all days
        document.querySelectorAll('input[name="recurrence_days[]"]').forEach(cb => {
            cb.checked = false;
        });
        
        // Then check only the days that are applicable
        applicableDays.forEach(day => {
            const checkbox = document.querySelector(`input[name="recurrence_days[]"][value="${day}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }
    // If no applicable days are set (for all_days, weekday, weekend types), 
    // we keep the default (all days checked) which is the new default
}

function updateBulkShiftDefaultIndicators() {
    const shiftSelect = document.getElementById('bulk_shift_id');
    if (!shiftSelect || !shiftSelect.options[shiftSelect.selectedIndex]) return;
    
    const selectedOption = shiftSelect.options[shiftSelect.selectedIndex];
    
    if (selectedOption.value) {
        const shiftHasBreaks = selectedOption.dataset.shiftBreakDefault === 'true';
        const shiftHasHandover = selectedOption.dataset.shiftHandoverDefault === 'true';
        
        // Update break indicator
        const breakIndicator = document.getElementById('bulk-shift-break-indicator');
        if (breakIndicator) {
            breakIndicator.textContent = shiftHasBreaks ? 'Default: Yes' : 'Default: No';
            breakIndicator.style.backgroundColor = shiftHasBreaks ? 
                'rgba(var(--success-rgb), 0.1)' : 
                'rgba(var(--secondary-rgb), 0.1)';
            breakIndicator.style.color = shiftHasBreaks ? 
                'var(--success)' : 
                'var(--text-secondary)';
        }
        
        // Update handover indicator
        const handoverIndicator = document.getElementById('bulk-shift-handover-indicator');
        if (handoverIndicator) {
            handoverIndicator.textContent = shiftHasHandover ? 'Default: Yes' : 'Default: No';
            handoverIndicator.style.backgroundColor = shiftHasHandover ? 
                'rgba(var(--success-rgb), 0.1)' : 
                'rgba(var(--secondary-rgb), 0.1)';
            handoverIndicator.style.color = shiftHasHandover ? 
                'var(--success)' : 
                'var(--text-secondary)';
        }
        
        // Auto-populate checkboxes based on defaults
        const breaksCheckbox = document.getElementById('bulk_include_breaks');
        const handoverCheckbox = document.getElementById('bulk_include_handover');
        
        if (breaksCheckbox && !breaksCheckbox.dataset.userModified) {
            breaksCheckbox.checked = shiftHasBreaks;
        }
        
        if (handoverCheckbox && !handoverCheckbox.dataset.userModified) {
            handoverCheckbox.checked = shiftHasHandover;
        }
        
        // Check for bulk overrides
        checkBulkOverrides(selectedOption);
    }
}

function checkBulkOverrides(selectedOption) {
    const breaksCheckbox = document.getElementById('bulk_include_breaks');
    const handoverCheckbox = document.getElementById('bulk_include_handover');
    
    if (!selectedOption || !breaksCheckbox || !handoverCheckbox) return;
    
    const shiftHasBreaks = selectedOption.dataset.shiftBreakDefault === 'true';
    const shiftHasHandover = selectedOption.dataset.shiftHandoverDefault === 'true';
    
    const breakOverrideWarning = document.getElementById('bulk-break-override-warning');
    const breakOverrideText = document.getElementById('bulk-break-override-text');
    const handoverOverrideWarning = document.getElementById('bulk-handover-override-warning');
    const handoverOverrideText = document.getElementById('bulk-handover-override-text');
    const overrideReasonContainer = document.getElementById('bulk-override-reason-container');
    const overrideSummaryLabel = document.getElementById('overrideSummaryLabel');
    const overrideSummaryValue = document.getElementById('overrideSummaryValue');
    
    let hasOverride = false;
    
    // Check break override
    if (breaksCheckbox.checked !== shiftHasBreaks) {
        if (breakOverrideWarning && breakOverrideText) {
            breakOverrideText.textContent = `You are ${breaksCheckbox.checked ? 'enabling' : 'disabling'} breaks for all assignments. Shift default: ${shiftHasBreaks ? 'has breaks' : 'no breaks'}.`;
            breakOverrideWarning.classList.remove('hidden');
        }
        hasOverride = true;
    } else {
        if (breakOverrideWarning) breakOverrideWarning.classList.add('hidden');
    }
    
    // Check handover override
    if (handoverCheckbox.checked !== shiftHasHandover) {
        if (handoverOverrideWarning && handoverOverrideText) {
            handoverOverrideText.textContent = `You are ${handoverCheckbox.checked ? 'enabling' : 'disabling'} handover for all assignments. Shift default: ${shiftHasHandover ? 'has handover' : 'no handover'}.`;
            handoverOverrideWarning.classList.remove('hidden');
        }
        hasOverride = true;
    } else {
        if (handoverOverrideWarning) handoverOverrideWarning.classList.add('hidden');
    }
    
    // Show/hide override reason container
    if (overrideReasonContainer) {
        if (hasOverride) {
            overrideReasonContainer.classList.remove('hidden');
            const reasonInput = document.getElementById('bulk_override_reason');
            if (reasonInput) reasonInput.setAttribute('required', 'required');
        } else {
            overrideReasonContainer.classList.add('hidden');
            const reasonInput = document.getElementById('bulk_override_reason');
            if (reasonInput) {
                reasonInput.removeAttribute('required');
                reasonInput.value = '';
            }
        }
    }
    
    // Update summary indicators
    if (overrideSummaryLabel && overrideSummaryValue) {
        if (hasOverride) {
            overrideSummaryLabel.classList.remove('hidden');
            overrideSummaryValue.classList.remove('hidden');
        } else {
            overrideSummaryLabel.classList.add('hidden');
            overrideSummaryValue.classList.add('hidden');
        }
    }
}

function updateBulkAssignSummary() {
    const postSelect = document.getElementById('bulk_post_id');
    const shiftSelect = document.getElementById('bulk_shift_id');
    const userSelect = document.getElementById('bulk_user_ids');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const recurrenceType = document.getElementById('recurrenceType');
    
    const summaryPersonnelCount = document.getElementById('summaryPersonnelCount');
    const summaryDateRange = document.getElementById('summaryDateRange');
    const summaryRecurrence = document.getElementById('summaryRecurrence');
    const summaryRotationPattern = document.getElementById('summaryRotationPattern');
    const summaryTotalAssignments = document.getElementById('summaryTotalAssignments');
    const bulkSummary = document.getElementById('bulkAssignSummary');
    
    if (!postSelect || !shiftSelect || !userSelect || !startDate || !endDate || !recurrenceType) return;
    
    const selectedCount = userSelect.selectedOptions.length;
    if (selectedCount > 0 && startDate.value && endDate.value) {
        if (summaryPersonnelCount) summaryPersonnelCount.textContent = selectedCount;
        if (summaryDateRange) summaryDateRange.textContent = startDate.value + ' to ' + endDate.value;
        
        const recurrenceText = recurrenceType.options[recurrenceType.selectedIndex]?.text || 'None';
        if (summaryRecurrence) summaryRecurrence.textContent = recurrenceText;
        
        // Get selected rotation pattern if any
        const selectedPattern = document.querySelector('input[name="rotation_pattern"]:checked');
        if (selectedPattern && recurrenceType.value === 'rotating') {
            const patternText = selectedPattern.parentElement?.querySelector('span')?.textContent || 'Custom';
            if (summaryRotationPattern) summaryRotationPattern.textContent = patternText;
        } else {
            if (summaryRotationPattern) summaryRotationPattern.textContent = 'None';
        }
        
        // Calculate approximate total assignments
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);
        const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        
        let multiplier = 1;
        if (recurrenceType.value === 'weekly') {
            const checkedDays = document.querySelectorAll('input[name="recurrence_days[]"]:checked').length;
            multiplier = checkedDays / 7;
        } else if (recurrenceType.value === 'monthly') {
            multiplier = 0.5; // Rough estimate
        }
        
        const totalAssignments = Math.ceil(selectedCount * daysDiff * multiplier);
        if (summaryTotalAssignments) summaryTotalAssignments.textContent = totalAssignments;
        
        if (bulkSummary) bulkSummary.classList.remove('hidden');
    } else {
        if (bulkSummary) bulkSummary.classList.add('hidden');
    }
}

// =============================================
// PREVIEW & SUBMIT FUNCTIONS
// =============================================
window.previewBulkAssign = function() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) {
        console.error('Bulk assign form not found');
        return;
    }
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Get form data
    const formData = new FormData(form);
    
    // Validate at least one personnel selected
    const userIds = formData.getAll('security_user_ids[]');
    if (userIds.length === 0) {
        showToast('Please select at least one security personnel', 'warning');
        return;
    }
    
    // Check for override reason when overrides exist
    const hasBreakOverride = document.getElementById('bulk-break-override-warning') && 
                            !document.getElementById('bulk-break-override-warning').classList.contains('hidden');
    const hasHandoverOverride = document.getElementById('bulk-handover-override-warning') && 
                               !document.getElementById('bulk-handover-override-warning').classList.contains('hidden');
    
    if ((hasBreakOverride || hasHandoverOverride)) {
        const overrideReason = document.getElementById('bulk_override_reason');
        if (!overrideReason || !overrideReason.value.trim()) {
            showToast('Override reason is required when modifying shift defaults', 'warning');
            overrideReason?.focus();
            return;
        }
    }
    
    // Build data object
    const data = {
        _token: formData.get('_token'),
        security_post_id: formData.get('security_post_id'),
        security_shift_id: formData.get('security_shift_id'),
        security_user_ids: userIds,
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        recurrence_type: formData.get('recurrence_type'),
        include_breaks: formData.get('include_breaks') === 'on',
        include_handover: formData.get('include_handover') === 'on',
        maintain_coverage: formData.get('maintain_coverage') === 'on',
        respect_preferences: formData.get('respect_preferences') === 'on',
        notify_personnel: formData.get('notify_personnel') === 'on',
        notes: formData.get('notes') || '',
        emergency_contact: formData.get('emergency_contact') || '',
        special_instructions: formData.get('special_instructions') || '',
        override_reason: document.getElementById('bulk_override_reason')?.value || ''
    };
    
    // Handle recurrence_days as array
    const recurrenceDays = formData.getAll('recurrence_days[]');
    if (recurrenceDays.length > 0) {
        data.recurrence_days = recurrenceDays.map(Number);
    }
    
    // Handle rotation_pattern if it exists
    const rotationPattern = formData.get('rotation_pattern');
    if (rotationPattern) {
        data.rotation_pattern = rotationPattern;
    }
    
    // Handle rotation group creation
    const createRotationGroup = formData.get('create_rotation_group') === 'on';
    if (createRotationGroup) {
        data.create_rotation_group = true;
        data.rotation_group_name = formData.get('rotation_group_name') || 'Auto Group';
    }
    
    // Show preview modal with loading
    const previewContent = document.getElementById('previewContent');
    if (previewContent) {
        previewContent.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Generating preview...</p>
            </div>
        `;
    }
    openModal('bulkAssignPreviewModal');
    
    // Fetch preview data
    fetch('{{ route("admin.security-schedules.bulk-assign-preview") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Server returned HTML instead of JSON');
            });
        }
        
        if (!response.ok) {
            return response.json().then(err => { 
                throw new Error(err.message || 'Server error');
            });
        }
        return response.json();
    })
    .then(responseData => {
        if (responseData.success) {
            displayPreviewResults(responseData);
        } else {
            let errorMessage = responseData.message || 'Failed to generate preview';
            if (responseData.errors) {
                errorMessage += '\n' + JSON.stringify(responseData.errors);
            }
            showToast(errorMessage, 'error');
            closeModal('bulkAssignPreviewModal');
        }
    })
    .catch(error => {
        console.error('Preview error:', error);
        showToast('Network error occurred: ' + error.message, 'error');
        closeModal('bulkAssignPreviewModal');
    });
};

function displayPreviewResults(data) {
    const previewContent = document.getElementById('previewContent');
    
    if (!previewContent) return;
    
    if (!data.preview || data.preview.length === 0) {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <div class="text-6xl mb-4" style="color: var(--warning);">📅</div>
                <p style="color: var(--text-primary); font-size: 1.1rem; font-weight: 500;">No assignments will be created</p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">Check your recurrence settings and date range</p>
                ${data.warnings && data.warnings.length > 0 ? `
                    <div class="mt-4 text-left p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <p class="text-sm font-medium mb-2" style="color: var(--warning);">⚠️ Warnings:</p>
                        <ul class="text-xs space-y-1">
                            ${data.warnings.map(w => `<li style="color: var(--text-secondary);">• ${w.message}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
        return;
    }

    // Build statistics summary
    let html = `
        <div class="mb-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--success);">${data.count || 0}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Total Assignments</div>
            </div>
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--info);">${data.statistics?.unique_personnel || 0}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Unique Personnel</div>
            </div>
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--warning);">${data.statistics?.avg_per_personnel || 0}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Avg per Person</div>
            </div>
            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                <div class="text-2xl font-bold" style="color: var(--primary);">${data.statistics?.coverage_rate || 0}%</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Coverage Rate</div>
            </div>
        </div>
    `;

    // Summary information
    if (data.summary) {
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Post</span>
                        <div class="font-medium" style="color: var(--text-primary);">${data.summary.post?.name || 'N/A'}</div>
                        <span class="text-xxs" style="color: var(--text-secondary);">Max: ${data.summary.post?.max_personnel || 0}</span>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Shift</span>
                        <div class="font-medium" style="color: var(--text-primary);">${data.summary.shift?.name || 'N/A'}</div>
                        <span class="text-xxs" style="color: var(--text-secondary);">${data.summary.shift?.time || ''}</span>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Date Range</span>
                        <div class="font-medium text-sm" style="color: var(--text-primary);">${data.summary.date_range?.start || ''}</div>
                        <span class="text-xxs" style="color: var(--text-secondary);">to ${data.summary.date_range?.end || ''}</span>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Recurrence</span>
                        <div class="font-medium" style="color: var(--text-primary);">${data.summary.recurrence?.type || 'N/A'}</div>
                        ${data.summary.recurrence?.days?.length ? 
                            `<span class="text-xxs" style="color: var(--text-secondary);">Days: ${data.summary.recurrence.days.join(', ')}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    // Rotation info
    if (data.summary?.rotation) {
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center mb-2">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--warning);"></i>
                    <span class="text-sm font-medium" style="color: var(--text-primary);">Rotation Pattern: ${data.summary.rotation.pattern || 'None'}</span>
                </div>
                ${data.summary.rotation.group ? `
                    <div class="text-xs pl-6" style="color: var(--text-secondary);">
                        <i class="fas fa-users mr-1"></i> Will create group: "${data.summary.rotation.group.name}" with ${data.summary.rotation.group.members} members
                    </div>
                ` : ''}
            </div>
        `;
    }

    // Options summary
    if (data.summary?.options) {
        const options = data.summary.options;
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                <div class="flex flex-wrap gap-3 text-xs">
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.include_breaks ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.include_breaks ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Breaks
                    </span>
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.include_handover ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.include_handover ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Handover
                    </span>
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.maintain_coverage ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.maintain_coverage ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Maintain Coverage
                    </span>
                    <span class="px-2 py-1 rounded-full" style="background-color: ${options.respect_preferences ? 'rgba(var(--success-rgb), 0.1); color: var(--success);' : 'rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);'}">
                        <i class="fas ${options.respect_preferences ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                        Respect Preferences
                    </span>
                </div>
            </div>
        `;
    }

    // Warnings
    if (data.warnings && data.warnings.length > 0) {
        html += `
            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                <p class="text-xs font-medium mb-2" style="color: var(--warning);"><i class="fas fa-exclamation-triangle mr-1"></i> Warnings:</p>
                <ul class="text-xs space-y-1">
                    ${data.warnings.map(w => `<li style="color: var(--text-secondary);">• ${w.message}</li>`).join('')}
                </ul>
            </div>
        `;
    }

    // Assignments by date
    if (data.preview && data.preview.length > 0) {
        html += `
            <div class="mt-4">
                <h4 class="font-medium mb-3" style="color: var(--text-primary);">Assignment Details by Date:</h4>
                <div class="max-h-96 overflow-y-auto space-y-4">
        `;
        
        data.preview.forEach(day => {
            const isFullCapacity = day.available_slots === 0;
            const isPartial = day.slots_filled < day.available_slots;
            
            html += `
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.02); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="font-medium" style="color: var(--primary);">${day.date}</span>
                            <span class="text-xs ml-2" style="color: var(--text-secondary);">${day.day_of_week || ''}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs px-2 py-1 rounded-full" 
                                  style="background-color: ${isFullCapacity ? 'rgba(var(--danger-rgb), 0.1); color: var(--danger);' : 
                                                                   isPartial ? 'rgba(var(--warning-rgb), 0.1); color: var(--warning);' : 
                                                                   'rgba(var(--success-rgb), 0.1); color: var(--success);'}">
                                <i class="fas fa-users mr-1"></i>
                                ${day.slots_filled || 0}/${day.available_slots || 0} slots
                            </span>
                            ${day.has_handover ? `
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-handshake mr-1"></i> Handover
                                </span>
                            ` : ''}
                            ${day.has_breaks ? `
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-coffee mr-1"></i> Breaks
                                </span>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        ${day.assignments && day.assignments.length > 0 ? day.assignments.map(assignment => `
                            <div class="flex items-center justify-between p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.03);">
                                <div class="flex items-center">
                                    <i class="fas fa-user-circle mr-2" style="color: var(--info);"></i>
                                    <div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">${assignment.personnel_name}</span>
                                        ${assignment.badge_number ? `<span class="text-xs ml-2" style="color: var(--text-secondary);">#${assignment.badge_number}</span>` : ''}
                                        <div class="text-xxs" style="color: var(--text-secondary);">${assignment.personnel_phone || ''}</div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="text-right">
                                        <div class="flex items-center">
                                            <span class="text-xs mr-2" style="color: var(--text-secondary);">Preference:</span>
                                            <div class="w-16 h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                                <div class="h-2 rounded-full" style="width: ${(assignment.preference_score / 10) * 100}%; background-color: ${assignment.preference_score >= 7 ? 'var(--success)' : assignment.preference_score >= 5 ? 'var(--warning)' : 'var(--danger)'};"></div>
                                            </div>
                                            <span class="text-xs ml-2" style="color: ${assignment.preference_score >= 7 ? 'var(--success)' : assignment.preference_score >= 5 ? 'var(--warning)' : 'var(--danger)'};">${assignment.preference_score}/10</span>
                                        </div>
                                        ${assignment.seniority_years ? `
                                            <div class="text-xxs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar-alt mr-1"></i> ${assignment.seniority_years} years
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `).join('') : `
                            <div class="text-center py-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> No assignments for this date
                            </div>
                        `}
                    </div>
                </div>
            `;
        });
        
        html += `</div></div>`;
    }

    previewContent.innerHTML = html;
}

window.submitBulkAssignForm = function() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) {
        console.error('Bulk assign form not found');
        return;
    }
    
    // Prevent default form submission
    if (event) {
        event.preventDefault();
    }
    
    // Close the preview modal
    closeModal('bulkAssignPreviewModal');
    
    // Get form data
    const formData = new FormData(form);
    
    // Validate at least one personnel selected
    const userIds = formData.getAll('security_user_ids[]');
    if (userIds.length === 0) {
        showToast('Please select at least one security personnel', 'warning');
        return;
    }
    
    // Check for override reason when overrides exist
    const hasBreakOverride = document.getElementById('bulk-break-override-warning') && 
                            !document.getElementById('bulk-break-override-warning').classList.contains('hidden');
    const hasHandoverOverride = document.getElementById('bulk-handover-override-warning') && 
                               !document.getElementById('bulk-handover-override-warning').classList.contains('hidden');
    
    if ((hasBreakOverride || hasHandoverOverride)) {
        const overrideReason = document.getElementById('bulk_override_reason');
        if (!overrideReason || !overrideReason.value.trim()) {
            showToast('Override reason is required when modifying shift defaults', 'warning');
            return;
        }
    }
    
    // Build data object
    const data = {
        _token: formData.get('_token'),
        security_post_id: formData.get('security_post_id'),
        security_shift_id: formData.get('security_shift_id'),
        security_user_ids: userIds,
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        recurrence_type: formData.get('recurrence_type'),
        include_breaks: formData.get('include_breaks') === 'on',
        include_handover: formData.get('include_handover') === 'on',
        maintain_coverage: formData.get('maintain_coverage') === 'on',
        respect_preferences: formData.get('respect_preferences') === 'on',
        notify_personnel: formData.get('notify_personnel') === 'on',
        notes: formData.get('notes') || '',
        emergency_contact: formData.get('emergency_contact') || '',
        special_instructions: formData.get('special_instructions') || '',
        override_reason: document.getElementById('bulk_override_reason')?.value || ''
    };
    
    // Handle recurrence_days as array
    const recurrenceDays = formData.getAll('recurrence_days[]');
    if (recurrenceDays.length > 0) {
        data.recurrence_days = recurrenceDays.map(Number);
    }
    
    // Handle rotation_pattern if it exists
    const rotationPattern = formData.get('rotation_pattern');
    if (rotationPattern) {
        data.rotation_pattern = rotationPattern;
    }
    
    // Handle rotation group creation
    const createRotationGroup = formData.get('create_rotation_group') === 'on';
    if (createRotationGroup) {
        data.create_rotation_group = true;
        data.rotation_group_name = formData.get('rotation_group_name') || 'Auto Group';
    }
    
    // Show loading state on the submit button
    const submitBtn = document.getElementById('bulkAssignSubmitBtn');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
    }
    
    // Show loading overlay
    showLoading('Creating schedules...');
    
    // Submit via AJAX
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response received:', text.substring(0, 200));
                throw new Error('Server returned an invalid response. Please check your server configuration.');
            });
        }
        
        if (!response.ok) {
            return response.json().then(err => { 
                throw new Error(err.message || `Server error: ${response.status}`);
            });
        }
        
        return response.json();
    })
    .then(data => {
        // Reset button state
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
        
        hideLoading();
        
        if (data.success) {
            // Show success message with the count
            showToast(`✅ ${data.created_count} schedule(s) created successfully!`, 'success');
            
            // Close the bulk assign modal
            closeModal('bulkAssignModal');
            
            // Log summary for debugging
            if (data.summary) {
                console.log('Bulk assignment summary:', data.summary);
                if (data.summary.override_reason) {
                    console.log('Override reason provided:', data.summary.override_reason);
                }
            }
            
            // Redirect to the schedules index page after a short delay
            setTimeout(() => {
                window.location.href = '{{ route("admin.security-schedules.index") }}';
            }, 1500);
        } else {
            showToast('❌ ' + (data.message || 'Failed to create schedules'), 'error');
            
            // Log failed assignments for debugging
            if (data.failed_assignments && data.failed_assignments.length > 0) {
                console.warn('Failed assignments:', data.failed_assignments);
            }
        }
    })
    .catch(error => {
        // Reset button state
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
        
        hideLoading();
        
        console.error('Submission error:', error);
        
        if (error.message.includes('Failed to fetch')) {
            showToast('Network error. Please check your internet connection.', 'error');
        } else {
            showToast('❌ Error: ' + error.message, 'error');
        }
    });
};

// =============================================
// MODAL FUNCTIONS
// =============================================
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

window.viewRotationHistory = function(scheduleId) {
    const modal = document.getElementById('rotation-history-modal');
    const content = document.getElementById('rotationHistoryContent');
    
    // Fetch rotation history data
    if (content) {
        content.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);"><i class="fas fa-spinner fa-spin mr-2"></i> Loading rotation history...</p>';
    }
    
    fetch(`/admin/rotation-groups/${scheduleId}/history`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history.length > 0) {
                let historyHtml = '';
                data.history.forEach(entry => {
                    historyHtml += `
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
                            <div class="flex justify-between">
                                <span class="font-medium" style="color: var(--text-primary);">${entry.date}</span>
                                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    ${entry.type}
                                </span>
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-primary);">From: ${entry.from} → To: ${entry.to}</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">${entry.reason || 'No reason provided'}</p>
                            <p class="text-xxs mt-1" style="color: var(--text-secondary);">By: ${entry.rotated_by}</p>
                        </div>
                    `;
                });
                content.innerHTML = historyHtml;
            } else {
                content.innerHTML = '<p class="text-center py-4" style="color: var(--text-secondary);">No rotation history found for this group.</p>';
            }
        })
        .catch(error => {
            console.error('Failed to load rotation history:', error);
            content.innerHTML = '<p class="text-center py-4" style="color: var(--danger);">Failed to load rotation history.</p>';
        });
    
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
};

window.viewRotationHistoryForCurrent = function() {
    // Get current rotation group if any
    const groupId = document.getElementById('rotation_group_id')?.value;
    if (groupId) {
        // Fetch rotation history for this group
        window.viewRotationHistory(groupId);
    } else {
        showToast('No rotation group associated with this shift', 'info');
    }
};

// =============================================
// TOAST NOTIFICATION
// =============================================
window.showToast = function(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    toast.innerHTML = `
        <span class="text-sm font-medium flex-1">${message}</span>
        <button class="ml-4 transition-colors duration-200" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
};

// =============================================
// EVENT LISTENERS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    // Form elements
    const form = document.getElementById('security-schedule-form');
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const includeBreaksCheckbox = document.getElementById('include_breaks');
    
    // Post selection events
    if (postSelect) {
        postSelect.addEventListener('change', function() {
            updatePostDetails();
            updatePostCapacity();
            updateHandoverInfo();
            updateAssignmentSummary();
        });
    }
    
    // Date input events
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            selectedDate = this.value;
            updateDayOfWeek();
            updatePostCapacity();
            updateShiftDetails();
            updateHandoverInfo();
            checkPersonnelAvailability();
            updateAssignmentSummary();
        });
    }
    
    // Shift selection events
    if (shiftSelect) {
        shiftSelect.addEventListener('change', function() {
            updateShiftDetails();
            updateHandoverInfo();
            checkPersonnelAvailability();
            updateAssignmentSummary();
            updateShiftDefaultIndicators();
        });
    }
    
    // Personnel selection events
    if (personnelSelect) {
        personnelSelect.addEventListener('change', function() {
            updatePersonnelInfo();
            checkPersonnelAvailability();
            updateAssignmentSummary();
        });
    }
    
    // Include breaks checkbox events (for override detection)
    if (includeBreaksCheckbox) {
        includeBreaksCheckbox.addEventListener('change', function() {
            this.dataset.userModified = 'true';
            checkForOverrides();
            updateAssignmentSummary();
        });
    }
    
    // Override reason input - validate on input
    const overrideReason = document.getElementById('break_override_reason');
    if (overrideReason) {
        overrideReason.addEventListener('input', function() {
            validateForm();
        });
    }
    
    // Form submission validation
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                
                const firstError = document.querySelector('.border-red-500');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            } else {
                // Add override audit log to form data if overrides exist
                if (isBreakOverridden && overrideAuditLog.length > 0) {
                    const overrideReason = document.getElementById('break_override_reason');
                    const auditInput = document.createElement('input');
                    auditInput.type = 'hidden';
                    auditInput.name = 'override_audit_log';
                    auditInput.value = JSON.stringify({
                        timestamp: new Date().toISOString(),
                        overrides: overrideAuditLog,
                        reason: overrideReason?.value || '',
                        user_id: {{ auth()->id() }},
                        user_name: '{{ auth()->user()->name }}'
                    });
                    form.appendChild(auditInput);
                }
            }
        });
    }

    // Bulk assign form event listeners
    setupBulkAssignForm();
    
    // Close modal when clicking overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });

    // =============================================
    // INITIALIZATION
    // =============================================
    function updateDayOfWeek() {
        const dayOfWeekDisplay = document.getElementById('day-of-week-display');
        const dateValue = dateInput?.value;
        if (dateValue && dayOfWeekDisplay) {
            const dayName = getDayOfWeek(dateValue);
            dayOfWeekDisplay.textContent = `${dayName}, ${new Date(dateValue).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        } else if (dayOfWeekDisplay) {
            dayOfWeekDisplay.textContent = 'Select a date';
        }
    }

    // Set default date if not set
    if (dateInput && !dateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
        selectedDate = today;
    }
    
    // Initial updates
    updateDayOfWeek();
    
    if (postSelect?.value) {
        updatePostDetails();
        updatePostCapacity();
    }
    
    if (shiftSelect?.value) {
        updateShiftDetails();
        updateHandoverInfo();
        updateShiftDefaultIndicators();
    }
    
    if (personnelSelect?.value) {
        updatePersonnelInfo();
        checkPersonnelAvailability();
    }
    
    if (postSelect?.value && shiftSelect?.value && personnelSelect?.value && dateInput?.value) {
        updateAssignmentSummary();
    }
    
    // Auto-refresh capacity every 30 seconds
    setInterval(function() {
        if (postSelect?.value && dateInput?.value) {
            updatePostCapacity();
        }
    }, 30000);
});

function setupBulkAssignForm() {
    const form = document.getElementById('bulkAssignForm');
    if (!form) return;
    
    // Prevent default form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        window.submitBulkAssignForm();
        return false;
    });
    
    // Personnel selection change
    const personnelSelect = form.querySelector('select[name="security_user_ids[]"]');
    if (personnelSelect) {
        personnelSelect.addEventListener('change', updateBulkAssignSummary);
    }
    
    // Post and shift selection
    const postSelect = document.getElementById('bulk_post_id');
    const shiftSelect = document.getElementById('bulk_shift_id');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (postSelect) {
        postSelect.addEventListener('change', updateBulkAssignSummary);
    }
    
    if (shiftSelect) {
        shiftSelect.addEventListener('change', function() {
            updateBulkShiftDefaultIndicators();
            // FIXED: Update weekly days when shift changes
            updateWeeklyDaysFromShift();
            updateBulkAssignSummary();
        });
    }
    
    // Date range validation
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
            if (endDate.value && endDate.value < this.value) {
                endDate.value = this.value;
            }
            updateBulkAssignSummary();
        });
        
        endDate.addEventListener('change', updateBulkAssignSummary);
    }
    
    // Recurrence type change
    const recurrenceType = document.getElementById('recurrenceType');
    if (recurrenceType) {
        recurrenceType.addEventListener('change', function() {
            toggleRecurrenceOptions(this.value);
            updateBulkAssignSummary();
        });
    }
    
    // Bulk options checkboxes
    const breaksCheckbox = document.getElementById('bulk_include_breaks');
    const handoverCheckbox = document.getElementById('bulk_include_handover');
    
    if (breaksCheckbox) {
        breaksCheckbox.addEventListener('change', function() {
            this.dataset.userModified = 'true';
            if (shiftSelect) {
                checkBulkOverrides(shiftSelect.options[shiftSelect.selectedIndex]);
            }
            updateBulkAssignSummary();
        });
    }
    
    if (handoverCheckbox) {
        handoverCheckbox.addEventListener('change', function() {
            this.dataset.userModified = 'true';
            if (shiftSelect) {
                checkBulkOverrides(shiftSelect.options[shiftSelect.selectedIndex]);
            }
            updateBulkAssignSummary();
        });
    }
    
    // Rotation pattern change
    document.querySelectorAll('input[name="rotation_pattern"]').forEach(radio => {
        radio.addEventListener('change', updateBulkAssignSummary);
    });
    
    // Weekly days checkboxes
    document.querySelectorAll('input[name="recurrence_days[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkAssignSummary);
    });
    
    // Create rotation group toggle
    const createGroupCheckbox = document.getElementById('create_rotation_group');
    const groupNameContainer = document.getElementById('rotationGroupNameContainer');
    
    if (createGroupCheckbox && groupNameContainer) {
        createGroupCheckbox.addEventListener('change', function() {
            if (this.checked) {
                groupNameContainer.classList.remove('hidden');
            } else {
                groupNameContainer.classList.add('hidden');
            }
        });
    }
    
    // Bulk override reason input
    const bulkOverrideReason = document.getElementById('bulk_override_reason');
    if (bulkOverrideReason) {
        bulkOverrideReason.addEventListener('input', function() {
            // Enable/disable submit button based on reason presence when overrides exist
            const hasOverride = document.getElementById('bulk-break-override-warning') && 
                               !document.getElementById('bulk-break-override-warning').classList.contains('hidden') ||
                               document.getElementById('bulk-handover-override-warning') && 
                               !document.getElementById('bulk-handover-override-warning').classList.contains('hidden');
            
            const submitBtn = document.getElementById('bulkAssignSubmitBtn');
            if (hasOverride && submitBtn) {
                if (this.value.trim()) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
        });
    }
}

function toggleRecurrenceOptions(type) {
    const weeklyContainer = document.getElementById('weeklyDaysContainer');
    const monthlyContainer = document.getElementById('monthlyDatesContainer');
    const rotationContainer = document.getElementById('rotationPatternContainer');
    
    if (weeklyContainer) weeklyContainer.classList.add('hidden');
    if (monthlyContainer) monthlyContainer.classList.add('hidden');
    if (rotationContainer) rotationContainer.classList.add('hidden');
    
    if (type === 'weekly' && weeklyContainer) {
        weeklyContainer.classList.remove('hidden');
    } else if (type === 'monthly' && monthlyContainer) {
        monthlyContainer.classList.remove('hidden');
    } else if (type === 'rotating' && rotationContainer) {
        rotationContainer.classList.remove('hidden');
    }
}
</script>

<style>
/* =========================================
   SECURITY SCHEDULE CREATE - CUSTOM STYLES
   ========================================= */

.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background-color: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 0.5rem;
    overflow: hidden;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

input, select, textarea {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    background-color: var(--bg-secondary, #f9fafb);
    border: 1px solid var(--border-color, #d1d5db);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-primary {
    background-color: var(--primary, #3b82f6);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn-primary:hover {
    background-color: var(--primary-dark, #2563eb);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    background-color: var(--bg-tertiary, #f3f4f6);
    color: var(--text-primary, #1f2937);
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid var(--border-color, #d1d5db);
}

.btn-secondary:hover {
    background-color: var(--bg-secondary, #e5e7eb);
    text-decoration: none;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-width: 900px;
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
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.modal-body {
    padding: 1.5rem;
}

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
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Toggle Switch */
.peer:checked ~ .peer-checked\:bg-info {
    background-color: var(--info, #3b82f6) !important;
}

.peer:checked ~ .peer-checked\:after\:translate-x-full::after {
    transform: translateX(100%);
}

/* Animation */
@keyframes pulseWarning {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

#capacity-warning:not(.hidden),
#applicability-warning:not(.hidden),
#rest-period-warning:not(.hidden),
#weekly-hours-warning:not(.hidden),
#break-override-warning:not(.hidden),
#bulk-break-override-warning:not(.hidden),
#bulk-handover-override-warning:not(.hidden),
#submit-warnings:not(.hidden) {
    animation: pulseWarning 2s infinite;
}

/* Loading States */
.loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 1.5rem;
    height: 1.5rem;
    margin-top: -0.75rem;
    margin-left: -0.75rem;
    border: 2px solid var(--border-color);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .grid-cols-1.lg\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        margin: 1rem;
    }
    
    .btn-primary, .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}

/* Hidden Class */
.hidden {
    display: none !important;
}

.text-xxs {
    font-size: 0.65rem;
    line-height: 1rem;
}

/* Override Summary Styles */
.override-active {
    border-left-width: 4px;
    border-left-color: var(--warning);
}

/* Default Indicator Styles */
.default-indicator {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    line-height: 1rem;
    font-weight: 500;
}

.default-indicator.match {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.default-indicator.mismatch {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}
</style>
@endsection