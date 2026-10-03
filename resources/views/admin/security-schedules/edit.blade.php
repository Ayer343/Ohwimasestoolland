@extends('layouts.app')

@section('title', 'Edit Security Schedule')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card with Status Indicators & Schedule Info -->
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

                <!-- Action Buttons -->
                <div class="flex items-center space-x-2">
                    <a href="{{ route('admin.security-schedules.show', $schedule->id) }}" 
                       class="btn-secondary flex items-center">
                        <i class="fas fa-eye mr-2"></i> View
                    </a>
                    <a href="{{ route('admin.security-schedules.index') }}" 
                       class="btn-secondary flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Schedule Quick Info Bar -->
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
                    <div class="font-medium" style="color: var(--text-primary);">
                        {{ $schedule->assignment_date->format('M j, Y') }}
                    </div>
                    <div class="text-xs" style="color: var(--text-secondary);">
                        {{ $schedule->assignment_date->format('l') }}
                    </div>
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
                        @if($schedule->shift->is_overnight)
                            <span class="ml-1">🌙</span>
                        @endif
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
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Badge: #{{ $schedule->securityUser->badge_number }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Main Edit Form -->
    <form action="{{ route('admin.security-schedules.update', $schedule->id) }}" 
          method="POST" 
          id="security-schedule-form"
          class="space-y-6">
        @csrf
        @method('PUT')
        
        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT COLUMN - Main Schedule Info (2/3 width) -->
            <div class="lg:col-span-2 space-y-6">
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
                        <!-- Post Selection -->
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
                            
                            <!-- Post Capacity & Status Display -->
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
                                
                                <!-- Capacity Progress Bar -->
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span style="color: var(--text-secondary);">Capacity Utilization</span>
                                        <span id="capacity-percentage" style="color: var(--text-primary);">0%</span>
                                    </div>
                                    <div class="w-full h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                        <div id="capacity-progress" class="h-2 rounded-full" style="width: 0%; transition: width 0.3s ease;"></div>
                                    </div>
                                </div>
                                
                                <!-- Capacity Warning -->
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

                <!-- Date & Shift Card -->
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
                        <!-- Assignment Date -->
                        <div>
                            <label for="assignment_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Assignment Date <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="date" 
                                       class="w-full p-2 border rounded @error('assignment_date') border-red-500 @enderror" 
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                       id="assignment_date" 
                                       name="assignment_date" 
                                       value="{{ old('assignment_date', $schedule->assignment_date->format('Y-m-d')) }}" 
                                       required
                                       min="{{ date('Y-m-d') }}">
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-calendar-day" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                            
                            <!-- Day Information -->
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
                        
                        <!-- Shift Selection -->
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
                            
                            <!-- Shift Details Panel -->
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
                                
                                <!-- Overnight Warning -->
                                <div id="overnight-warning" class="mt-2 p-2 rounded text-xs {{ $schedule->shift->is_overnight ? '' : 'hidden' }}"
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
                            </div>
                            
                            @error('security_shift_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    
                    <!-- Handover Information Preview -->
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
                        
                        <!-- Handover Checklist Preview -->
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

                <!-- Status Management Card -->
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
                        <!-- Status Selection -->
                        <div>
                            <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Current Status <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full p-2 border rounded @error('status') border-red-500 @enderror" 
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                    id="status" name="status" required>
                                <option value="scheduled" {{ old('status', $schedule->status) == 'scheduled' ? 'selected' : '' }}>
                                    🕐 Scheduled
                                </option>
                                <option value="active" {{ old('status', $schedule->status) == 'active' ? 'selected' : '' }}>
                                    ✅ Active (Checked In)
                                </option>
                                <option value="completed" {{ old('status', $schedule->status) == 'completed' ? 'selected' : '' }}>
                                    ✔️ Completed
                                </option>
                                <option value="absent" {{ old('status', $schedule->status) == 'absent' ? 'selected' : '' }}>
                                    ❌ Absent
                                </option>
                                <option value="cancelled" {{ old('status', $schedule->status) == 'cancelled' ? 'selected' : '' }}>
                                    🚫 Cancelled
                                </option>
                            </select>
                            
                            @error('status')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Status Timeline (Read-only) -->
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Schedule Timeline
                            </label>
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
                    
                    <!-- Status Transition Warnings -->
                    <div id="status-warning" class="mt-3 p-3 rounded text-sm hidden"
                         style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                        <span id="status-warning-text" style="color: var(--text-primary);"></span>
                    </div>
                </div>

                <!-- Rotation Settings (Conditional) -->
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
                                <span class="px-2 py-1 rounded text-xs" 
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $schedule->rotation_data['current_position'] ?? 'Morning' }}
                                </span>
                                <i class="fas fa-arrow-right text-xs" style="color: var(--text-secondary);"></i>
                                <span class="px-2 py-1 rounded text-xs" 
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
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
                    
                    <!-- Rotation History -->
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

            <!-- RIGHT COLUMN - Personnel & Assignment Details (1/3 width) -->
            <div class="lg:col-span-1 space-y-6">
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
                        <!-- Personnel Selection -->
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
                            
                            <!-- Personnel Profile Card -->
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
                                
                                <!-- Attendance Rate -->
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
                            
                            <!-- Availability Status -->
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
                            <input type="text" 
                                   class="w-full p-2 border rounded @error('emergency_contact') border-red-500 @enderror" 
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                   id="emergency_contact" 
                                   name="emergency_contact" 
                                   value="{{ old('emergency_contact', $schedule->emergency_contact) }}" 
                                   placeholder="e.g., +233 XXX XXX XXX / Supervisor Name">
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
                                      id="special_instructions" 
                                      name="special_instructions" 
                                      rows="4" 
                                      placeholder="Specific instructions for this assignment...">{{ old('special_instructions', $schedule->special_instructions) }}</textarea>
                            <div class="flex justify-between mt-1">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Max 1000 characters. Include any site-specific requirements.
                                </p>
                                <span id="special-instructions-counter" class="text-xs" style="color: var(--text-secondary);">
                                    0/1000
                                </span>
                            </div>
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
                                      id="notes" 
                                      name="notes" 
                                      rows="3" 
                                      placeholder="Any additional notes about this assignment...">{{ old('notes', $schedule->notes) }}</textarea>
                            <div class="flex justify-between mt-1">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Max 500 characters. Internal notes not visible to security personnel.
                                </p>
                                <span id="notes-counter" class="text-xs" style="color: var(--text-secondary);">
                                    0/500
                                </span>
                            </div>
                            @error('notes')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Audit Information -->
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
                
                <!-- Quick Actions Card -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i>
                        Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        <!-- Send Notification -->
                        <button type="button" 
                                onclick="sendAssignmentNotification({{ $schedule->id }})"
                                class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <span class="flex items-center">
                                <i class="fas fa-bell mr-2"></i>
                                Send Assignment Notification
                            </span>
                            <i class="fas fa-chevron-right text-xs"></i>
                        </button>
                        
                        <!-- Mark as Complete (if active) -->
                        @if($schedule->status === 'active')
                            <button type="button" 
                                    onclick="quickCompleteSchedule({{ $schedule->id }})"
                                    class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <span class="flex items-center">
                                    <i class="fas fa-check-double mr-2"></i>
                                    Mark as Completed
                                </span>
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        @endif
                        
                        <!-- Mark as Absent (if scheduled) -->
                        @if($schedule->status === 'scheduled')
                            <button type="button" 
                                    onclick="quickMarkAbsent({{ $schedule->id }})"
                                    class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <span class="flex items-center">
                                    <i class="fas fa-user-slash mr-2"></i>
                                    Mark as Absent
                                </span>
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        @endif
                        
                        <!-- Cancel Schedule -->
                        @if(!in_array($schedule->status, ['completed', 'cancelled']))
                            <button type="button" 
                                    onclick="confirmCancelSchedule({{ $schedule->id }})"
                                    class="w-full p-3 rounded flex items-center justify-between hover:bg-opacity-80 transition-all"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <span class="flex items-center">
                                    <i class="fas fa-ban mr-2"></i>
                                    Cancel Schedule
                                </span>
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FORM ACTIONS -->
        <div class="card p-6">
            <div class="flex flex-col lg:flex-row justify-between items-center gap-4">
                <!-- Delete Button (Left) -->
                <button type="button" 
                        onclick="confirmDelete({{ $schedule->id }})"
                        class="px-6 py-2 rounded flex items-center order-2 lg:order-1"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i>
                    Delete Schedule
                </button>
                
                <!-- Form Status & Submit (Right) -->
                <div class="flex flex-col sm:flex-row items-center gap-4 order-1 lg:order-2 w-full lg:w-auto">
                    <div id="form-status" class="text-sm" style="color: var(--text-secondary);">
                        <!-- Dynamic form status messages -->
                    </div>
                    
                    <div class="flex space-x-4 w-full sm:w-auto">
                        <button type="submit" 
                                class="btn-primary flex items-center justify-center px-6 py-2 w-full sm:w-auto"
                                id="submit-button">
                            <i class="fas fa-save mr-2"></i> 
                            Update Schedule
                        </button>
                        <a href="{{ route('admin.security-schedules.index') }}" 
                           class="btn-secondary flex items-center justify-center px-6 py-2 w-full sm:w-auto">
                            <i class="fas fa-times mr-2"></i> 
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Submission Warnings -->
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

<!-- Delete Confirmation Modal -->
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
                        <h3 class="text-lg leading-6 font-medium" style="color: var(--text-primary);">
                            Delete Security Schedule
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Are you sure you want to delete this schedule? 
                                This action cannot be undone. This will permanently remove the assignment for 
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
                <button type="button" 
                        onclick="closeDeleteModal()"
                        class="px-4 py-2 rounded text-sm font-medium"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    Cancel
                </button>
                <form id="delete-form" action="{{ route('admin.security-schedules.destroy', $schedule->id) }}" method="POST" class="inline">
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
                        <h3 class="text-lg leading-6 font-medium" style="color: var(--text-primary);">
                            Send Assignment Notification
                        </h3>
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
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Send to {{ $schedule->securityUser->phone }}
                                    </p>
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
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Send to {{ $schedule->securityUser->email }}
                                    </p>
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
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Send to {{ $schedule->securityUser->phone }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="mt-4">
                            <label for="notification_message" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Additional Message (Optional)
                            </label>
                            <textarea id="notification_message" 
                                      rows="3"
                                      class="w-full p-2 border rounded text-sm"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Add any additional instructions..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 flex justify-end space-x-3" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                <button type="button" 
                        onclick="closeNotificationModal()"
                        class="px-4 py-2 rounded text-sm font-medium"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    Cancel
                </button>
                <button type="button" 
                        onclick="sendNotification({{ $schedule->id }})"
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
document.addEventListener('DOMContentLoaded', function() {
    // =========================================
    // FORM ELEMENTS
    // =========================================
    const form = document.getElementById('security-schedule-form');
    const postSelect = document.getElementById('security_post_id');
    const shiftSelect = document.getElementById('security_shift_id');
    const personnelSelect = document.getElementById('security_user_id');
    const dateInput = document.getElementById('assignment_date');
    const statusSelect = document.getElementById('status');
    const submitButton = document.getElementById('submit-button');
    const formStatus = document.getElementById('form-status');
    
    // =========================================
    // UI CONTAINERS
    // =========================================
    const postCapacityInfo = document.getElementById('post-capacity-info');
    const shiftDetails = document.getElementById('shift-details');
    const handoverPreview = document.getElementById('handover-preview-container');
    const personnelProfile = document.getElementById('personnel-profile');
    const personnelAvailability = document.getElementById('personnel-availability-status');
    const restPeriodWarning = document.getElementById('rest-period-warning');
    const weeklyHoursWarning = document.getElementById('weekly-hours-warning');
    const rotationOptions = document.getElementById('rotation-options');
    const statusWarning = document.getElementById('status-warning');
    const submitWarnings = document.getElementById('submit-warnings');
    
    // =========================================
    // DISPLAY ELEMENTS
    // =========================================
    const currentAssignments = document.getElementById('current-assignments');
    const maxPersonnel = document.getElementById('max-personnel');
    const availableSlots = document.getElementById('available-slots');
    const capacityProgress = document.getElementById('capacity-progress');
    const capacityPercentage = document.getElementById('capacity-percentage');
    const capacityWarning = document.getElementById('capacity-warning');
    const capacityWarningText = document.getElementById('capacity-warning-text');
    const postActiveStatus = document.getElementById('post-active-status');
    const postTypeDisplay = document.getElementById('post-type-display');
    const dayOfWeekDisplay = document.getElementById('day-of-week-display');
    const shiftTimeDisplay = document.getElementById('shift-time-display');
    const shiftDurationDisplay = document.getElementById('shift-duration-display');
    const shiftCategoryDisplay = document.getElementById('shift-category-display');
    const shiftHandoverDisplay = document.getElementById('shift-handover-display');
    const overnightWarning = document.getElementById('overnight-warning');
    const applicabilityWarning = document.getElementById('applicability-warning');
    const applicabilityWarningText = document.getElementById('applicability-warning-text');
    const handoverStartDisplay = document.getElementById('handover-start-display');
    const handoverEndDisplay = document.getElementById('handover-end-display');
    const handoverDurationDisplay = document.getElementById('handover-duration-display');
    const previousShiftDisplay = document.getElementById('previous-shift-display');
    const personnelName = document.getElementById('personnel-name');
    const personnelBadge = document.getElementById('personnel-badge');
    const personnelPhone = document.getElementById('personnel-phone');
    const personnelEmail = document.getElementById('personnel-email');
    const personnelLastShift = document.getElementById('personnel-last-shift');
    const personnelTotalShifts = document.getElementById('personnel-total-shifts');
    const availabilityDetail = document.getElementById('availability-detail');
    const restPeriodText = document.getElementById('rest-period-text');
    const weeklyHoursText = document.getElementById('weekly-hours-text');
    const currentRotationPosition = document.getElementById('current-rotation-position');
    const nextRotationDate = document.getElementById('next-rotation-date');
    const statusWarningText = document.getElementById('status-warning-text');
    const submitWarningText = document.getElementById('submit-warning-text');
    const specialInstructionsCounter = document.getElementById('special-instructions-counter');
    const notesCounter = document.getElementById('notes-counter');
    
    // =========================================
    // STATE MANAGEMENT
    // =========================================
    let originalPostId = '{{ $schedule->security_post_id }}';
    let originalShiftId = '{{ $schedule->security_shift_id }}';
    let originalPersonnelId = '{{ $schedule->security_user_id }}';
    let originalDate = '{{ $schedule->assignment_date->format('Y-m-d') }}';
    let originalStatus = '{{ $schedule->status }}';
    let currentCapacity = 0;
    let isFormValid = true;

    // =========================================
    // DAY OF WEEK UTILITIES
    // =========================================
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

    // =========================================
    // TEXT COUNTERS
    // =========================================
    function updateTextCounters() {
        const specialInstructions = document.getElementById('special_instructions');
        const notes = document.getElementById('notes');
        
        if (specialInstructionsCounter) {
            specialInstructionsCounter.textContent = `${specialInstructions.value.length}/1000`;
            
            if (specialInstructions.value.length > 950) {
                specialInstructionsCounter.style.color = 'var(--warning)';
            } else if (specialInstructions.value.length > 1000) {
                specialInstructionsCounter.style.color = 'var(--danger)';
            } else {
                specialInstructionsCounter.style.color = 'var(--text-secondary)';
            }
        }
        
        if (notesCounter) {
            notesCounter.textContent = `${notes.value.length}/500`;
            
            if (notes.value.length > 450) {
                notesCounter.style.color = 'var(--warning)';
            } else if (notes.value.length > 500) {
                notesCounter.style.color = 'var(--danger)';
            } else {
                notesCounter.style.color = 'var(--text-secondary)';
            }
        }
    }

    // =========================================
    // POST CAPACITY MANAGEMENT
    // =========================================
    async function updatePostCapacity() {
        const postId = postSelect.value;
        const date = dateInput.value;
        
        if (!postId || !date) {
            postCapacityInfo?.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch(`/admin/security-schedules/post-capacity?post_id=${postId}&date=${date}`);
            const data = await response.json();
            
            if (data.success) {
                currentCapacity = data.currently_assigned || 0;
                const max = data.max_personnel || 0;
                const available = data.available_slots || 0;
                
                // Check if current schedule is counted in capacity
                const isCurrentScheduleIncluded = await checkIfCurrentScheduleIncluded(postId, date);
                const adjustedCapacity = isCurrentScheduleIncluded ? currentCapacity - 1 : currentCapacity;
                
                // Update UI
                currentAssignments.textContent = adjustedCapacity;
                maxPersonnel.textContent = max;
                availableSlots.textContent = max - adjustedCapacity;
                
                const capacityPercent = max > 0 ? (adjustedCapacity / max) * 100 : 0;
                capacityProgress.style.width = `${Math.min(capacityPercent, 100)}%`;
                capacityPercentage.textContent = `${Math.round(capacityPercent)}%`;
                
                // Color code based on capacity
                if (capacityPercent >= 90) {
                    capacityProgress.style.backgroundColor = 'var(--danger)';
                } else if (capacityPercent >= 75) {
                    capacityProgress.style.backgroundColor = 'var(--warning)';
                } else {
                    capacityProgress.style.backgroundColor = 'var(--success)';
                }
                
                // Show capacity warning
                if (max - adjustedCapacity <= 0 && !isCurrentScheduleIncluded) {
                    capacityWarning.classList.remove('hidden');
                    capacityWarningText.textContent = 'This post is at full capacity for the selected date.';
                    capacityProgress.style.backgroundColor = 'var(--danger)';
                } else if (max - adjustedCapacity < 3 && !isCurrentScheduleIncluded) {
                    capacityWarning.classList.remove('hidden');
                    capacityWarningText.textContent = `Only ${max - adjustedCapacity} slot(s) available for this post on the selected date.`;
                    capacityProgress.style.backgroundColor = 'var(--warning)';
                } else {
                    capacityWarning.classList.add('hidden');
                }
                
                postCapacityInfo?.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Failed to fetch post capacity:', error);
        }
    }

    async function checkIfCurrentScheduleIncluded(postId, date) {
        try {
            const response = await fetch(`/admin/security-schedules/check-schedule?schedule_id={{ $schedule->id }}&post_id=${postId}&date=${date}`);
            const data = await response.json();
            return data.is_included || false;
        } catch (error) {
            console.error('Failed to check if schedule is included:', error);
            return false;
        }
    }

    function updatePostDetails() {
        const selectedOption = postSelect.options[postSelect.selectedIndex];
        
        if (selectedOption.value) {
            const max = selectedOption.dataset.maxPersonnel || 0;
            const postType = selectedOption.dataset.postType || 'Standard';
            const isActive = selectedOption.dataset.isActive === 'true';
            const location = selectedOption.dataset.postLocation || 'N/A';
            
            postTypeDisplay.textContent = postType.replace('_', ' ').toUpperCase();
            
            if (postActiveStatus) {
                postActiveStatus.textContent = isActive ? 'Active' : 'Inactive';
                postActiveStatus.style.backgroundColor = isActive 
                    ? 'rgba(var(--success-rgb), 0.1)' 
                    : 'rgba(var(--secondary-rgb), 0.1)';
                postActiveStatus.style.color = isActive ? 'var(--success)' : 'var(--text-secondary)';
            }
        }
    }

    // =========================================
    // SHIFT MANAGEMENT
    // =========================================
    function updateShiftDetails() {
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
            
            shiftTimeDisplay.textContent = `${startTime} - ${endTime}`;
            shiftDurationDisplay.textContent = `${duration} hours`;
            shiftCategoryDisplay.textContent = category.charAt(0).toUpperCase() + category.slice(1);
            shiftHandoverDisplay.textContent = hasHandover ? `Yes (${handoverDuration}min)` : 'No';
            
            if (isOvernight) {
                overnightWarning?.classList.remove('hidden');
            } else {
                overnightWarning?.classList.add('hidden');
            }
            
            // Check shift applicability
            const dateValue = dateInput.value;
            if (dateValue) {
                const dayIso = getDayOfWeekIso(dateValue);
                const isApplicable = applicableDays.includes(dayIso);
                
                if (!isApplicable) {
                    applicabilityWarning?.classList.remove('hidden');
                    applicabilityWarningText.textContent = `This shift is not applicable on ${getDayOfWeek(dateValue)}s.`;
                } else {
                    applicabilityWarning?.classList.add('hidden');
                }
            }
            
            // Show/hide rotation options
            if (rotationType === 'rotating') {
                rotationOptions?.classList.remove('hidden');
                updateRotationInfo(selectedOption);
            } else {
                rotationOptions?.classList.add('hidden');
            }
            
            shiftDetails?.classList.remove('hidden');
            
            // Update handover info
            updateHandoverInfo();
        }
    }

    async function updateHandoverInfo() {
        const shiftId = shiftSelect.value;
        const date = dateInput.value;
        const postId = postSelect.value;
        
        if (!shiftId || !date || !postId) {
            handoverPreview?.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch(`/admin/security-schedules/handover-info?shift_id=${shiftId}&date=${date}&post_id=${postId}`);
            const data = await response.json();
            
            if (data.success && data.handover_info) {
                const handoverInfo = data.handover_info;
                
                handoverStartDisplay.textContent = handoverInfo.handover_start || '--:--';
                handoverEndDisplay.textContent = handoverInfo.handover_end || '--:--';
                handoverDurationDisplay.textContent = `${handoverInfo.handover_duration || 30} minutes`;
                previousShiftDisplay.textContent = handoverInfo.previous_shift_id ? 'Shift #' + handoverInfo.previous_shift_id : 'None';
                
                handoverPreview?.classList.remove('hidden');
            } else {
                handoverPreview?.classList.add('hidden');
            }
        } catch (error) {
            console.error('Failed to fetch handover info:', error);
            handoverPreview?.classList.add('hidden');
        }
    }

    function updateRotationInfo(shiftOption) {
        // Use existing rotation data from the schedule
        @if($schedule->rotation_data)
            const rotationData = @json($schedule->rotation_data);
            if (currentRotationPosition) {
                currentRotationPosition.textContent = rotationData.current_position || 'Morning';
            }
            if (nextRotationDate && rotationData.next_rotation_date) {
                nextRotationDate.textContent = new Date(rotationData.next_rotation_date).toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            }
        @endif
    }

    // =========================================
    // PERSONNEL MANAGEMENT
    // =========================================
    function updatePersonnelInfo() {
        const selectedOption = personnelSelect.options[personnelSelect.selectedIndex];
        
        if (selectedOption.value) {
            const name = selectedOption.text.split('-')[0].trim();
            const badge = selectedOption.dataset.badge || 'N/A';
            const phone = selectedOption.dataset.phone || 'N/A';
            const email = selectedOption.dataset.email || 'N/A';
            const lastShift = selectedOption.dataset.lastShift || 'No previous shifts';
            const totalShifts = selectedOption.dataset.shiftsCount || '0';
            const attendanceRate = selectedOption.dataset.attendanceRate || '100';
            
            personnelName.textContent = name;
            personnelBadge.textContent = `Badge: #${badge}`;
            personnelPhone.textContent = phone;
            personnelEmail.textContent = email;
            personnelLastShift.textContent = lastShift !== 'No previous shifts' 
                ? new Date(lastShift).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : 'No previous shifts';
            personnelTotalShifts.textContent = totalShifts;
            
            // Check availability
            checkPersonnelAvailability();
        }
    }

    async function checkPersonnelAvailability() {
        const userId = personnelSelect.value;
        const date = dateInput.value;
        const shiftId = shiftSelect.value;
        
        if (!userId || !date) {
            return;
        }

        try {
            const response = await fetch(`/admin/security-schedules/check-availability?user_id=${userId}&date=${date}`);
            const data = await response.json();
            
            let isAvailable = true;
            let availabilityMessages = [];
            
            // Check if this is the current schedule
            const isCurrentSchedule = (parseInt(userId) === {{ $schedule->security_user_id }} && 
                                      date === '{{ $schedule->assignment_date->format('Y-m-d') }}');
            
            if (data.has_assignment && !isCurrentSchedule) {
                isAvailable = false;
                personnelAvailability.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--danger)';
                personnelAvailability.querySelector('i').style.color = 'var(--danger)';
                personnelAvailability.querySelector('span').textContent = 'Unavailable';
                personnelAvailability.querySelector('span').style.color = 'var(--danger)';
                availabilityDetail.textContent = 'Already assigned to another post on this date.';
                
                availabilityMessages.push('Already assigned');
            } else {
                personnelAvailability.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                personnelAvailability.style.borderLeftColor = 'var(--success)';
                personnelAvailability.querySelector('i').style.color = 'var(--success)';
                personnelAvailability.querySelector('span').textContent = 'Available';
                personnelAvailability.querySelector('span').style.color = 'var(--success)';
                availabilityDetail.textContent = 'No conflicting assignments found.';
            }
            
            // Check rest period
            if (shiftId && data.last_shift_date && !isCurrentSchedule) {
                const lastShiftDate = new Date(data.last_shift_date + 'T12:00:00');
                const currentDate = new Date(date + 'T12:00:00');
                const hoursDiff = (currentDate - lastShiftDate) / (1000 * 60 * 60);
                const minRestHours = 12;
                
                if (hoursDiff < minRestHours) {
                    restPeriodWarning?.classList.remove('hidden');
                    restPeriodText.textContent = `Only ${Math.round(hoursDiff)} hours since last shift. Minimum rest period is ${minRestHours} hours.`;
                    isAvailable = false;
                    availabilityMessages.push('Insufficient rest period');
                } else {
                    restPeriodWarning?.classList.add('hidden');
                }
            } else {
                restPeriodWarning?.classList.add('hidden');
            }
            
            // Check weekly hours
            if (shiftId && data.weekly_hours && !isCurrentSchedule) {
                const selectedShift = shiftSelect.options[shiftSelect.selectedIndex];
                const shiftDuration = parseFloat(selectedShift.dataset.duration || '0');
                const weeklyHours = data.weekly_hours || 0;
                const maxWeeklyHours = 60;
                
                if ((weeklyHours + shiftDuration) > maxWeeklyHours) {
                    weeklyHoursWarning?.classList.remove('hidden');
                    weeklyHoursText.textContent = `Current week: ${weeklyHours}h, Adding: ${shiftDuration}h, Total: ${weeklyHours + shiftDuration}h (Max: ${maxWeeklyHours}h)`;
                    isAvailable = false;
                    availabilityMessages.push('Weekly hour limit exceeded');
                } else {
                    weeklyHoursWarning?.classList.add('hidden');
                }
            } else {
                weeklyHoursWarning?.classList.add('hidden');
            }
            
            // Update availability detail with all messages
            if (!isAvailable && availabilityMessages.length > 0) {
                availabilityDetail.textContent = availabilityMessages.join('. ') + '.';
            }
            
        } catch (error) {
            console.error('Failed to check availability:', error);
        }
    }

    // =========================================
    // STATUS MANAGEMENT
    // =========================================
    function updateStatusWarning() {
        const newStatus = statusSelect.value;
        const currentStatus = '{{ $schedule->status }}';
        
        if (newStatus === currentStatus) {
            statusWarning?.classList.add('hidden');
            return;
        }
        
        let warningMessage = '';
        
        switch(newStatus) {
            case 'completed':
                if (currentStatus !== 'active') {
                    warningMessage = 'Marking as completed without check-in/out data. Consider marking as "Active" first.';
                }
                break;
            case 'absent':
                warningMessage = 'Marking personnel as absent. This will affect their attendance record.';
                break;
            case 'cancelled':
                warningMessage = 'Cancelling this schedule. This action can be reversed by reassigning.';
                break;
            case 'active':
                if (currentStatus === 'scheduled') {
                    warningMessage = 'Manually activating shift. Ensure personnel has checked in.';
                }
                break;
        }
        
        if (warningMessage) {
            statusWarningText.textContent = warningMessage;
            statusWarning?.classList.remove('hidden');
        } else {
            statusWarning?.classList.add('hidden');
        }
    }

    // =========================================
    // FORM VALIDATION
    // =========================================
    async function validateForm() {
        let isValid = true;
        let warnings = [];
        
        // Check required fields
        if (!postSelect.value) {
            isValid = false;
            warnings.push('Security Post is required');
        }
        
        if (!shiftSelect.value) {
            isValid = false;
            warnings.push('Security Shift is required');
        }
        
        if (!personnelSelect.value) {
            isValid = false;
            warnings.push('Security Personnel is required');
        }
        
        if (!dateInput.value) {
            isValid = false;
            warnings.push('Assignment Date is required');
        }
        
        // Check if changes were made
        const hasChanges = (
            postSelect.value !== originalPostId ||
            shiftSelect.value !== originalShiftId ||
            personnelSelect.value !== originalPersonnelId ||
            dateInput.value !== originalDate ||
            statusSelect.value !== originalStatus ||
            document.getElementById('emergency_contact').value !== '{{ $schedule->emergency_contact }}' ||
            document.getElementById('special_instructions').value !== '{{ $schedule->special_instructions }}' ||
            document.getElementById('notes').value !== '{{ $schedule->notes }}'
        );
        
        if (!hasChanges) {
            warnings.push('No changes detected');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            formStatus.innerHTML = `
                <div class="flex items-center text-warning">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>No changes to save</span>
                </div>
            `;
        } else {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        
        // Check capacity (only if post or date changed)
        if ((postSelect.value !== originalPostId || dateInput.value !== originalDate) && postSelect.value && dateInput.value) {
            const max = parseInt(maxPersonnel.textContent || '0');
            const available = parseInt(availableSlots.textContent || '0');
            
            if (available <= 0) {
                isValid = false;
                warnings.push('Selected post is at full capacity for this date');
            }
        }
        
        // Check shift applicability (only if shift or date changed)
        if ((shiftSelect.value !== originalShiftId || dateInput.value !== originalDate) && shiftSelect.value && dateInput.value) {
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
        
        // Check personnel constraints (only if personnel or date changed)
        if ((personnelSelect.value !== originalPersonnelId || dateInput.value !== originalDate) && personnelSelect.value && dateInput.value) {
            if (!restPeriodWarning?.classList.contains('hidden')) {
                isValid = false;
                warnings.push('Insufficient rest period for selected personnel');
            }
            
            if (!weeklyHoursWarning?.classList.contains('hidden')) {
                isValid = false;
                warnings.push('Weekly hour limit would be exceeded');
            }
            
            // Check if already assigned elsewhere
            const availabilitySpan = personnelAvailability?.querySelector('span');
            if (availabilitySpan && availabilitySpan.textContent === 'Unavailable') {
                isValid = false;
                warnings.push('Selected personnel is already assigned on this date');
            }
        }
        
        // Check character limits
        const specialInstructions = document.getElementById('special_instructions');
        const notes = document.getElementById('notes');
        
        if (specialInstructions.value.length > 1000) {
            isValid = false;
            warnings.push('Special instructions exceed 1000 characters');
        }
        
        if (notes.value.length > 500) {
            isValid = false;
            warnings.push('Notes exceed 500 characters');
        }
        
        // Update UI
        if (warnings.length > 0) {
            submitWarnings?.classList.remove('hidden');
            submitWarningText.textContent = warnings.join('. ') + '.';
        } else {
            submitWarnings?.classList.add('hidden');
        }
        
        if (!isValid) {
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            formStatus.innerHTML = `
                <div class="flex items-center text-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span>Please fix validation errors</span>
                </div>
            `;
        } else if (hasChanges) {
            formStatus.innerHTML = `
                <div class="flex items-center text-success">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Ready to update</span>
                </div>
            `;
        }
        
        isFormValid = isValid;
        return isValid;
    }

    // =========================================
    // EVENT LISTENERS
    // =========================================
    
    // Post selection
    postSelect.addEventListener('change', function() {
        updatePostDetails();
        updatePostCapacity();
        updateHandoverInfo();
        validateForm();
    });
    
    // Date change
    dateInput.addEventListener('change', function() {
        dayOfWeekDisplay.textContent = `${getDayOfWeek(this.value)}, ${new Date(this.value).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        updatePostCapacity();
        updateShiftDetails();
        updateHandoverInfo();
        checkPersonnelAvailability();
        validateForm();
    });
    
    // Shift selection
    shiftSelect.addEventListener('change', function() {
        updateShiftDetails();
        updateHandoverInfo();
        checkPersonnelAvailability();
        validateForm();
    });
    
    // Personnel selection
    personnelSelect.addEventListener('change', function() {
        updatePersonnelInfo();
        checkPersonnelAvailability();
        validateForm();
    });
    
    // Status change
    statusSelect.addEventListener('change', function() {
        updateStatusWarning();
        validateForm();
    });
    
    // Input events
    document.getElementById('emergency_contact').addEventListener('input', validateForm);
    document.getElementById('special_instructions').addEventListener('input', function() {
        updateTextCounters();
        validateForm();
    });
    document.getElementById('notes').addEventListener('input', function() {
        updateTextCounters();
        validateForm();
    });
    
    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (await validateForm()) {
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            
            // Submit the form
            this.submit();
        } else {
            // Scroll to first error
            const firstError = document.querySelector('.border-red-500');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
    });

    // =========================================
    // INITIALIZATION
    // =========================================
    
    // Initialize text counters
    updateTextCounters();
    
    // Initialize post capacity
    updatePostCapacity();
    
    // Initialize day display
    dayOfWeekDisplay.textContent = '{{ $schedule->assignment_date->format("l, F j, Y") }}';
    
    // Set min date to today
    dateInput.min = new Date().toISOString().split('T')[0];
    
    // Auto-refresh capacity every 30 seconds
    setInterval(function() {
        if (postSelect.value && dateInput.value) {
            updatePostCapacity();
        }
    }, 30000);
});

// =========================================
// QUICK ACTION FUNCTIONS
// =========================================

function sendAssignmentNotification(scheduleId) {
    const personnelName = '{{ $schedule->securityUser->name }}';
    document.getElementById('notification-personnel-name').textContent = personnelName;
    document.getElementById('notification-modal').classList.remove('hidden');
}

function closeNotificationModal() {
    document.getElementById('notification-modal').classList.add('hidden');
}

async function sendNotification(scheduleId) {
    const channel = document.querySelector('input[name="notification_channel"]:checked')?.value;
    const message = document.getElementById('notification_message')?.value;
    
    if (!channel) {
        alert('Please select a notification channel');
        return;
    }
    
    const button = document.querySelector('#notification-modal button[onclick="sendNotification(' + scheduleId + ')"]');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    
    try {
        const response = await fetch(`/admin/security-schedules/${scheduleId}/send-notification`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ channel, message })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Notification sent successfully!');
            closeNotificationModal();
        } else {
            alert('Failed to send notification: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Failed to send notification:', error);
        alert('Failed to send notification. Please try again.');
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Notification';
    }
}

function quickCompleteSchedule(scheduleId) {
    if (confirm('Mark this schedule as completed? This will set check-out time to now.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/security-schedules/${scheduleId}/status`;
        
        const csrf = document.createElement('input');
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        csrf.type = 'hidden';
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.name = '_method';
        method.value = 'PATCH';
        method.type = 'hidden';
        form.appendChild(method);
        
        const action = document.createElement('input');
        action.name = 'action';
        action.value = 'mark_complete';
        action.type = 'hidden';
        form.appendChild(action);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function quickMarkAbsent(scheduleId) {
    if (confirm('Mark this personnel as absent? This will affect their attendance record.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/security-schedules/${scheduleId}/status`;
        
        const csrf = document.createElement('input');
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        csrf.type = 'hidden';
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.name = '_method';
        method.value = 'PATCH';
        method.type = 'hidden';
        form.appendChild(method);
        
        const action = document.createElement('input');
        action.name = 'action';
        action.value = 'mark_absent';
        action.type = 'hidden';
        form.appendChild(action);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmCancelSchedule(scheduleId) {
    if (confirm('Are you sure you want to cancel this schedule? This will notify the security personnel.')) {
        const select = document.getElementById('status');
        select.value = 'cancelled';
        document.getElementById('security-schedule-form').submit();
    }
}

// =========================================
// DELETE MODAL FUNCTIONS
// =========================================
function confirmDelete(scheduleId) {
    document.getElementById('delete-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const deleteModal = document.getElementById('delete-modal');
    const notificationModal = document.getElementById('notification-modal');
    
    if (event.target === deleteModal) {
        deleteModal.classList.add('hidden');
    }
    
    if (event.target === notificationModal) {
        notificationModal.classList.add('hidden');
    }
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<style>
/* =========================================
   SECURITY SCHEDULE EDIT - CUSTOM STYLES
   Extends the create blade styling
   ========================================= */

/* Status-specific card borders */
.card.status-scheduled {
    border-left: 4px solid var(--info);
}

.card.status-active {
    border-left: 4px solid var(--success);
}

.card.status-completed {
    border-left: 4px solid var(--primary);
}

.card.status-absent {
    border-left: 4px solid var(--danger);
}

.card.status-cancelled {
    border-left: 4px solid var(--secondary);
}

/* Quick info bar styling */
.quick-info-item {
    transition: transform 0.2s ease;
}

.quick-info-item:hover {
    transform: translateY(-2px);
}

/* Personnel profile avatar */
.personnel-avatar {
    transition: all 0.3s ease;
}

.personnel-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}

/* Modal animations */
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#delete-modal .sm\:align-middle,
#notification-modal .sm\:align-middle {
    animation: modalSlideIn 0.3s ease-out;
}

/* Form field transitions */
input, select, textarea {
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

input:disabled, select:disabled, textarea:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Quick action buttons */
.quick-action-btn {
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.quick-action-btn:hover {
    transform: translateX(4px);
}

.quick-action-btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.quick-action-btn:focus:not(:active)::after {
    animation: ripple 0.6s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    20% {
        transform: scale(25, 25);
        opacity: 0.3;
    }
    100% {
        opacity: 0;
        transform: scale(40, 40);
    }
}

/* Capacity progress bar animation */
#capacity-progress {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                background-color 0.3s ease;
}

/* Status warning pulse animation */
@keyframes softPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

#status-warning:not(.hidden) {
    animation: softPulse 2s infinite;
}

/* Character counter transitions */
#special-instructions-counter,
#notes-counter {
    transition: color 0.2s ease;
}

/* Timeline indicator */
#shift-timeline {
    position: relative;
}

#shift-timeline::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: rgba(var(--secondary-rgb), 0.2);
    transform: translateY(-50%);
}

/* Print styles */
@media print {
    .btn-primary,
    .btn-secondary,
    .quick-action-btn,
    #delete-modal,
    #notification-modal,
    button[type="submit"] {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #000;
    }
    
    #post-capacity-info,
    #shift-details,
    #handover-preview-container {
        break-inside: avoid;
    }
}

/* Dark mode adjustments */
[data-theme="dark"] .card {
    background: linear-gradient(145deg, var(--card-bg), rgba(var(--primary-rgb), 0.02));
}

[data-theme="dark"] #post-capacity-info,
[data-theme="dark"] #shift-details,
[data-theme="dark"] #handover-preview-container {
    background-color: rgba(255, 255, 255, 0.02);
}

[data-theme="dark"] select option {
    background-color: var(--bg-primary);
    color: var(--text-primary);
}

/* Loading spinner animation */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .lg\:col-span-2,
    .lg\:col-span-1 {
        grid-column: span 1 / span 1;
    }
    
    .grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .card .flex.justify-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    #shift-details .grid-cols-2.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
    
    .personnel-profile .flex {
        flex-direction: column;
        text-align: center;
    }
    
    .personnel-profile .ml-3 {
        margin-left: 0;
        margin-top: 0.5rem;
    }
}

/* Focus styles for accessibility */
:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Custom scrollbar for modals */
.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Success state for form fields */
input.success,
select.success,
textarea.success {
    border-color: var(--success);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2310b981' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
    padding-right: 2.5rem;
}

/* Error state for form fields */
input.error,
select.error,
textarea.error {
    border-color: var(--danger);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23ef4444' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='12' y1='8' x2='12' y2='12'%3E%3C/line%3E%3Cline x1='12' y1='16' x2='12.01' y2='16'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
    padding-right: 2.5rem;
}

/* Warning state for form fields */
input.warning,
select.warning,
textarea.warning {
    border-color: var(--warning);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23f59e0b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'%3E%3C/path%3E%3Cline x1='12' y1='8' x2='12' y2='12'%3E%3C/line%3E%3Cline x1='12' y1='16' x2='12.01' y2='16'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
    padding-right: 2.5rem;
}

/* Disabled state styling */
input:disabled,
select:disabled,
textarea:disabled {
    background-color: rgba(var(--secondary-rgb), 0.05);
    border-color: rgba(var(--border-color), 0.5);
    color: var(--text-secondary);
    cursor: not-allowed;
}

/* Tooltip enhancements */
[data-tooltip] {
    position: relative;
    cursor: help;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem 0.75rem;
    background-color: var(--text-primary);
    color: var(--bg-primary);
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    border-radius: 0.375rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 50;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

[data-tooltip]:hover:before {
    opacity: 1;
    transform: translateX(-50%) translateY(-4px);
}

[data-tooltip]:after {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    border-width: 4px;
    border-style: solid;
    border-color: var(--text-primary) transparent transparent transparent;
    opacity: 0;
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
    z-index: 50;
}

[data-tooltip]:hover:after {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* Glass morphism effect for modals */
@supports (backdrop-filter: blur(8px)) {
    #delete-modal,
    #notification-modal {
        background-color: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(8px);
    }
}

/* Custom radio button styling for notification modal */
input[type="radio"] {
    appearance: none;
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid var(--border-color);
    border-radius: 50%;
    transition: all 0.2s ease;
    position: relative;
    cursor: pointer;
}

input[type="radio"]:checked {
    border-color: var(--primary);
    background-color: var(--primary);
    box-shadow: inset 0 0 0 3px white;
}

input[type="radio"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* RTL Support */
[dir="rtl"] .mr-2 {
    margin-right: 0;
    margin-left: 0.5rem;
}

[dir="rtl"] .ml-3 {
    margin-left: 0;
    margin-right: 0.75rem;
}

[dir="rtl"] .space-x-3 > * + * {
    margin-left: 0;
    margin-right: 0.75rem;
}

[dir="rtl"] input.error,
[dir="rtl"] input.success,
[dir="rtl"] input.warning {
    background-position: left 0.75rem center;
    padding-right: 0.75rem;
    padding-left: 2.5rem;
}

/* Reduced motion preferences */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* High contrast mode support */
@media (forced-colors: active) {
    .card,
    button,
    input,
    select,
    textarea {
        border: 1px solid CanvasText;
    }
    
    .btn-primary,
    .btn-secondary {
        border: 1px solid CanvasText;
    }
}

/* Loading skeleton animation */
@keyframes skeleton-loading {
    0% {
        background-position: -200px 0;
    }
    100% {
        background-position: calc(200px + 100%) 0;
    }
}

.skeleton {
    background: linear-gradient(90deg, 
        rgba(var(--secondary-rgb), 0.1) 25%, 
        rgba(var(--secondary-rgb), 0.2) 50%, 
        rgba(var(--secondary-rgb), 0.1) 75%);
    background-size: 200px 100%;
    animation: skeleton-loading 1.5s infinite;
}

/* Custom badge for rotation indicators */
.rotation-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.5;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.rotation-badge.morning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.rotation-badge.evening {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

.rotation-badge.night {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.rotation-badge.off {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--text-secondary);
}

/* Timeline markers */
.timeline-marker {
    position: relative;
}

.timeline-marker::before {
    content: '';
    position: absolute;
    top: -0.5rem;
    left: 50%;
    transform: translateX(-50%);
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background-color: var(--primary);
    border: 2px solid var(--card-bg);
}

.timeline-marker.start::before {
    background-color: var(--success);
}

.timeline-marker.end::before {
    background-color: var(--danger);
}

.timeline-marker.handover::before {
    background-color: var(--warning);
}
</style>
@endsection