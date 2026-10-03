@extends('layouts.app')

@section('title', 'Edit Security Post')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        @php
                            $typeIcons = [
                                'main_gate' => 'fa-door-closed',
                                'internal_gate' => 'fa-door-open',
                                'checkpoint' => 'fa-shield-alt',
                                'patrol_route' => 'fa-route',
                                'observation_post' => 'fa-binoculars',
                                'control_room' => 'fa-tv',
                                'access_point' => 'fa-key',
                            ];
                            $typeIcon = $typeIcons[$securityPost->type] ?? 'fa-map-marker-alt';
                        @endphp
                        <i class="fas {{ $typeIcon }} text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-edit mr-2" style="color: var(--primary);"></i> 
                        Edit Security Post
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span>Editing: {{ $securityPost->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-hashtag mr-2"></i>
                        <span>Code: {{ $securityPost->code }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span>Last updated: {{ $securityPost->updated_at->format('M j, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button type="button" onclick="showPreview()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-eye mr-2"></i> Preview
                </button>
                <a href="{{ route('admin.security-posts.show', $securityPost) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" form="editPostForm" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
                
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> All Posts
                </a>
                
                <a href="{{ route('admin.security-posts.show', $securityPost) }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-eye mr-1"></i> View Post
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}?post_id={{ $securityPost->id }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Schedules
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-user-shield mr-1"></i> Admin: {{ auth()->user()->name }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Assignments -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $securityPost->schedules()->count() }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Assignments</div>
                </div>
            </div>
        </div>
        
        <!-- Active Today -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
                <div>
                    @php
                        $activeToday = $securityPost->currentSchedules()->where('status', 'active')->count();
                    @endphp
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $activeToday }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Today</div>
                </div>
            </div>
        </div>
        
        <!-- Staffing Rate -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div>
                    @php
                        $currentPersonnel = $securityPost->current_personnel ?? 0;
                        $maxPersonnel = $securityPost->max_personnel ?? 1;
                        $staffingRate = $maxPersonnel > 0 ? round(($currentPersonnel / $maxPersonnel) * 100, 1) : 0;
                    @endphp
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $staffingRate }}%</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Staffing Rate</div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div class="h-2 rounded-full" 
                             style="width: {{ min(100, $staffingRate) }}%; 
                                    background-color: var(--warning);">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Current Personnel -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: {{ $currentPersonnel >= $maxPersonnel ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                color: {{ $currentPersonnel >= $maxPersonnel ? 'var(--success)' : 'var(--danger)' }};">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" 
                         style="color: {{ $currentPersonnel >= $maxPersonnel ? 'var(--success)' : 'var(--danger)' }};">
                        {{ $currentPersonnel }}/{{ $maxPersonnel }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ $currentPersonnel >= $maxPersonnel ? 'Fully Staffed' : 'Understaffed' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> 
                Edit Security Post Configuration
            </h3>
        </div>
        <form id="editPostForm" method="POST" action="{{ route('admin.security-posts.update', $securityPost) }}" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-8">
                <!-- Basic Information -->
                <div>
                    <div class="mb-6 pb-4 border-b" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            Basic Information
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Essential details for identifying and categorizing the security post
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="form-label">
                                Post Name <span class="required-field">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $securityPost->name) }}"
                                   class="form-input w-full p-3 rounded-lg border @error('name') error-field @enderror"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="e.g., Main Entrance Gate"
                                   required
                                   maxlength="255">
                            @error('name')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                A descriptive name for the security post
                            </p>
                        </div>
                        
                        <!-- Code -->
                        <div>
                            <label for="code" class="form-label">
                                Post Code <span class="required-field">*</span>
                            </label>
                            <input type="text" 
                                   id="code" 
                                   name="code" 
                                   value="{{ old('code', $securityPost->code) }}"
                                   class="form-input w-full p-3 rounded-lg border @error('code') error-field @enderror"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="e.g., GATE-001"
                                   required
                                   maxlength="50">
                            @error('code')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                Unique identifier for the post
                            </p>
                        </div>
                        
                        <!-- Type -->
                        <div>
                            <label for="type" class="form-label">
                                Post Type <span class="required-field">*</span>
                            </label>
                            <select id="type" 
                                    name="type" 
                                    class="form-select w-full p-3 rounded-lg border @error('type') error-field @enderror"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                    required>
                                <option value="">Select Post Type</option>
                                @foreach($postTypes as $value => $label)
                                    <option value="{{ $value }}" 
                                            {{ old('type', $securityPost->type) == $value ? 'selected' : '' }}
                                            style="color: var(--text-primary);">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                The type of security post
                            </p>
                        </div>
                        
                        <!-- Max Personnel -->
                        <div>
                            <label for="max_personnel" class="form-label">
                                Maximum Personnel <span class="required-field">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       id="max_personnel" 
                                       name="max_personnel" 
                                       value="{{ old('max_personnel', $securityPost->max_personnel) }}"
                                       min="1" 
                                       max="10"
                                       class="form-input w-full p-3 rounded-lg border @error('max_personnel') error-field @enderror"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       required>
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <span class="text-sm" style="color: var(--text-secondary);">persons</span>
                                </div>
                            </div>
                            @error('max_personnel')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                Maximum number of personnel that can be assigned (1-10)
                            </p>
                        </div>
                        
                        <!-- Status Indicator -->
                        <div class="md:col-span-2">
                            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-bell mr-2" style="color: var(--warning);"></i>
                                            Post Update Impact
                                        </div>
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            Changes will affect scheduling and assignments immediately
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium" 
                                          style="background-color: {{ $securityPost->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                                 color: {{ $securityPost->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                        <i class="fas {{ $securityPost->is_active ? 'fa-bolt' : 'fa-power-off' }} mr-1"></i> 
                                        {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location Details -->
                <div>
                    <div class="mb-6 pb-4 border-b" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-map-marker-alt mr-2" style="color: var(--primary);"></i>
                            Location Details
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Physical location and addressing information
                        </p>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Location -->
                        <div>
                            <label for="location" class="form-label">
                                Location Description <span class="required-field">*</span>
                            </label>
                            <textarea id="location" 
                                      name="location" 
                                      rows="2"
                                      class="form-textarea w-full p-3 rounded-lg border @error('location') error-field @enderror"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                      placeholder="e.g., Building A, Ground Floor, Near Reception"
                                      required
                                      maxlength="500">{{ old('location', $securityPost->location) }}</textarea>
                            <div class="mt-1 flex justify-between text-xs">
                                <span style="color: var(--text-secondary);">
                                    Detailed physical location description
                                </span>
                                <span id="locationCounter" style="color: var(--text-secondary);">
                                    {{ strlen(old('location', $securityPost->location)) }}/500 characters
                                </span>
                            </div>
                            @error('location')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Digital Address -->
                        <div>
                            <label for="digital_address" class="form-label">
                                Digital Address / GPS Coordinates
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="text" 
                                       id="digital_address" 
                                       name="digital_address" 
                                       value="{{ old('digital_address', $securityPost->digital_address) }}"
                                       class="form-input flex-grow p-3 rounded-lg border @error('digital_address') error-field @enderror"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       placeholder="e.g., GPS: 5.6037, -0.1870"
                                       maxlength="255">
                                <button type="button" onclick="getCurrentLocation()" 
                                        class="btn-secondary whitespace-nowrap px-4 py-3">
                                    <i class="fas fa-location-arrow mr-1"></i> Get Location
                                </button>
                            </div>
                            @error('digital_address')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                GPS coordinates or digital location reference
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Working Hours with Overnight Support -->
                <div>
                    <div class="mb-6 pb-4 border-b" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                            Working Hours
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Define when this post is operational (leave empty for 24/7 operation)
                        </p>
                        <div class="mt-2 flex items-center text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                            <span>Supports overnight shifts (e.g., 6:00 PM to 6:00 AM)</span>
                        </div>
                        @if(isset($isOvernight) && $isOvernight)
                            <div class="mt-2 p-2 rounded-lg inline-flex items-center" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                <i class="fas fa-moon mr-2" style="color: var(--warning);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">Currently configured as overnight shift</span>
                            </div>
                        @endif
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Start Time -->
                        <div>
                            <label for="working_hours_start" class="form-label">
                                Start Time
                            </label>
                            <input type="time" 
                                   id="working_hours_start" 
                                   name="working_hours_start" 
                                   value="{{ old('working_hours_start', $workingHoursStart) }}"
                                   class="form-input w-full p-3 rounded-lg border @error('working_hours_start') error-field @enderror"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <div id="startTimeDisplay" class="text-xs mt-1" style="color: var(--text-secondary);"></div>
                            @error('working_hours_start')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                When the post becomes operational
                            </p>
                        </div>
                        
                        <!-- End Time -->
                        <div>
                            <label for="working_hours_end" class="form-label">
                                End Time
                            </label>
                            <input type="time" 
                                   id="working_hours_end" 
                                   name="working_hours_end" 
                                   value="{{ old('working_hours_end', $workingHoursEnd) }}"
                                   class="form-input w-full p-3 rounded-lg border @error('working_hours_end') error-field @enderror"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <div id="endTimeDisplay" class="text-xs mt-1" style="color: var(--text-secondary);"></div>
                            @error('working_hours_end')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                When the post ceases operation
                            </p>
                        </div>
                    </div>
                    
                    <!-- Overnight Status Display -->
                    <div id="overnightStatus" class="mt-4 p-3 rounded-lg {{ isset($isOvernight) && $isOvernight ? '' : 'hidden' }}" 
                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-moon mr-2" style="color: var(--warning);"></i>
                            <span style="color: var(--text-primary); font-weight: 500;">Overnight Shift Detected</span>
                            <span class="ml-2 text-xs" style="color: var(--text-secondary);">(End time is before start time)</span>
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            Duration: <span id="durationDisplay" class="font-medium" style="color: var(--text-primary);">
                                @if($workingHoursStart && $workingHoursEnd)
                                    @php
                                        $start = \Carbon\Carbon::parse($workingHoursStart);
                                        $end = \Carbon\Carbon::parse($workingHoursEnd);
                                        if ($end->lessThan($start)) {
                                            $end->addDay();
                                        }
                                        $hours = $start->diffInHours($end);
                                        $minutes = $start->diffInMinutes($end) % 60;
                                        $durationText = $hours . 'h';
                                        if ($minutes > 0) {
                                            $durationText .= ' ' . $minutes . 'm';
                                        }
                                        echo $durationText;
                                    @endphp
                                @else
                                    0
                                @endif
                            </span> hours
                        </div>
                    </div>
                    
                    <!-- 24/7 Option -->
                    <div class="mt-4">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="twentyFourSeven" class="form-checkbox rounded"
                                   {{ (!$workingHoursStart && !$workingHoursEnd) ? 'checked' : '' }}>
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-clock mr-1" style="color: var(--success);"></i>
                                Operate 24/7 (No specific working hours)
                            </span>
                        </label>
                    </div>
                    
                    <!-- Working Hours Preview -->
                    <div id="workingHoursPreview" class="mt-4 p-4 rounded-lg border {{ ($workingHoursStart || $workingHoursEnd) ? '' : 'hidden' }}" 
                         style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-secondary);">Working Hours Preview</div>
                                <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                                    <span id="previewStartTime">
                                        @if($workingHoursStart)
                                            {{ \Carbon\Carbon::parse($workingHoursStart)->format('g:i A') }}
                                        @else
                                            --:--
                                        @endif
                                    </span> - 
                                    <span id="previewEndTime">
                                        @if($workingHoursEnd)
                                            {{ \Carbon\Carbon::parse($workingHoursEnd)->format('g:i A') }}
                                        @else
                                            --:--
                                        @endif
                                    </span>
                                    <span id="previewOvernightBadge" class="{{ isset($isOvernight) && $isOvernight ? '' : 'hidden' }} ml-2 px-2 py-1 text-xs rounded-full" 
                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                        🌙 Overnight
                                    </span>
                                    <span id="previewDuration" class="ml-2 text-sm font-normal" style="color: var(--text-secondary);">
                                        @if($workingHoursStart && $workingHoursEnd)
                                            @php
                                                $start = \Carbon\Carbon::parse($workingHoursStart);
                                                $end = \Carbon\Carbon::parse($workingHoursEnd);
                                                if ($end->lessThan($start)) {
                                                    $end->addDay();
                                                }
                                                $hours = $start->diffInHours($end);
                                                $minutes = $start->diffInMinutes($end) % 60;
                                                $durationText = '(' . $hours . 'h';
                                                if ($minutes > 0) {
                                                    $durationText .= ' ' . $minutes . 'm';
                                                }
                                                $durationText .= ')';
                                                echo $durationText;
                                            @endphp
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="text-xs px-3 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-info-circle mr-1"></i> Updated live
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipment -->
                <div>
                    <div class="mb-6 pb-4 border-b" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-tools mr-2" style="color: var(--success);"></i>
                            Equipment
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Equipment required or available at this post
                        </p>
                    </div>
                    
                    <div>
                        <label class="form-label">
                            Available Equipment (Optional)
                        </label>
                        
                        <!-- Default Equipment Suggestions -->
                        <div class="mb-4">
                            <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                Common equipment items (click to add):
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $defaultEquipment = [
                                        'Communication Radio',
                                        'Flashlight',
                                        'First Aid Kit',
                                        'Visitor Logbook',
                                        'Security Barrier',
                                        'CCTV Monitor',
                                        'Fire Extinguisher',
                                        'Metal Detector',
                                        'Handcuffs',
                                        'Protective Gear',
                                    ];
                                @endphp
                                @foreach($defaultEquipment as $item)
                                    <button type="button" 
                                            class="equipment-tag"
                                            onclick="addEquipmentItem('{{ $item }}')">
                                        {{ $item }}
                                        <i class="fas fa-plus ml-1 text-xs"></i>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        
                        <!-- Equipment Input -->
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="text" 
                                       id="equipmentInput" 
                                       placeholder="Type equipment name and press Enter..."
                                       class="form-input flex-grow p-3 rounded-lg border"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       maxlength="100">
                                <button type="button" 
                                        onclick="addEquipmentFromInput()"
                                        class="ml-2 px-4 py-3 btn-primary whitespace-nowrap">
                                    <i class="fas fa-plus mr-1"></i> Add
                                </button>
                            </div>
                        </div>
                        
                        <!-- Selected Equipment -->
                        <div id="equipmentList" class="space-y-2 mb-4">
                            @if(is_array($equipment) && count($equipment) > 0)
                                @foreach($equipment as $index => $item)
                                    <div class="equipment-item flex items-center justify-between p-3 rounded-lg border hover:shadow-sm transition-all duration-200"
                                         style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3" 
                                                 style="background-color: rgba(var(--success-rgb), 0.1);">
                                                <i class="fas fa-toolbox" style="color: var(--success);"></i>
                                            </div>
                                            <div>
                                                <span style="color: var(--text-primary); font-weight: 500;">{{ $item }}</span>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    Equipment Item {{ $index + 1 }}
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" onclick="removeEquipmentItem({{ $index }})" 
                                                class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 transition-colors duration-200">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div class="empty-state text-center py-8 border-2 border-dashed rounded-lg"
                                     style="border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-tools text-2xl mb-2"></i>
                                    <p class="text-sm">No equipment added yet</p>
                                    <p class="text-xs mt-1">Click on the suggestions above or type to add equipment</p>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Hidden inputs for equipment (will be populated dynamically) -->
                        <div id="equipmentFields">
                            @if(is_array($equipment) && count($equipment) > 0)
                                @foreach($equipment as $index => $item)
                                    <input type="hidden" 
                                           name="equipment[{{ $index }}]" 
                                           value="{{ old('equipment.' . $index, $item) }}">
                                @endforeach
                            @endif
                        </div>
                        
                        @error('equipment')
                            <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                        @enderror
                        @error('equipment.*')
                            <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Restrictions -->
                <div>
                    <div class="mb-6 pb-4 border-b" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-ban mr-2" style="color: var(--danger);"></i>
                            Restrictions
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Special rules or limitations for this post (Optional)
                        </p>
                    </div>
                    
                    <div>
                        <label class="form-label">
                            Post Restrictions
                        </label>
                        
                        <!-- Common Restrictions -->
                        <div class="mb-4">
                            <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                Common restrictions (click to add):
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" 
                                        class="restriction-tag"
                                        onclick="addRestrictionItem('No vehicles allowed after 10 PM')">
                                    No vehicles after 10 PM
                                    <i class="fas fa-plus ml-1 text-xs"></i>
                                </button>
                                <button type="button" 
                                        class="restriction-tag"
                                        onclick="addRestrictionItem('Authorized personnel only')">
                                    Authorized personnel only
                                    <i class="fas fa-plus ml-1 text-xs"></i>
                                </button>
                                <button type="button" 
                                        class="restriction-tag"
                                        onclick="addRestrictionItem('No photography allowed')">
                                    No photography
                                    <i class="fas fa-plus ml-1 text-xs"></i>
                                </button>
                                <button type="button" 
                                        class="restriction-tag"
                                        onclick="addRestrictionItem('ID verification required')">
                                    ID verification required
                                    <i class="fas fa-plus ml-1 text-xs"></i>
                                </button>
                                <button type="button" 
                                        class="restriction-tag"
                                        onclick="addRestrictionItem('Access control enforced')">
                                    Access control enforced
                                    <i class="fas fa-plus ml-1 text-xs"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Restrictions Input -->
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="text" 
                                       id="restrictionInput" 
                                       placeholder="Type restriction and press Enter..."
                                       class="form-input flex-grow p-3 rounded-lg border"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       maxlength="255">
                                <button type="button" 
                                        onclick="addRestrictionFromInput()"
                                        class="ml-2 px-4 py-3 btn-primary whitespace-nowrap">
                                    <i class="fas fa-plus mr-1"></i> Add
                                </button>
                            </div>
                        </div>
                        
                        <!-- Selected Restrictions -->
                        <div id="restrictionsList" class="space-y-2 mb-4">
                            @if(is_array($restrictions) && count($restrictions) > 0)
                                @foreach($restrictions as $index => $restriction)
                                    <div class="restriction-item flex items-center justify-between p-3 rounded-lg border hover:shadow-sm transition-all duration-200"
                                         style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3" 
                                                 style="background-color: rgba(var(--danger-rgb), 0.1);">
                                                <i class="fas fa-ban" style="color: var(--danger);"></i>
                                            </div>
                                            <div>
                                                <span style="color: var(--text-primary); font-weight: 500;">{{ $restriction }}</span>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    Restriction {{ $index + 1 }}
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" onclick="removeRestrictionItem({{ $index }})" 
                                                class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 transition-colors duration-200">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div class="empty-state text-center py-8 border-2 border-dashed rounded-lg"
                                     style="border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-ban text-2xl mb-2"></i>
                                    <p class="text-sm">No restrictions added</p>
                                    <p class="text-xs mt-1">Click on the suggestions above or type to add restrictions</p>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Hidden inputs for restrictions (will be populated dynamically) -->
                        <div id="restrictionFields">
                            @if(is_array($restrictions) && count($restrictions) > 0)
                                @foreach($restrictions as $index => $restriction)
                                    <input type="hidden" 
                                           name="restrictions[{{ $index }}]" 
                                           value="{{ old('restrictions.' . $index, $restriction) }}">
                                @endforeach
                            @endif
                        </div>
                        
                        @error('restrictions')
                            <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                        @enderror
                        @error('restrictions.*')
                            <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Additional Settings -->
                <div>
                    <div class="mb-6 pb-4 border-b" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-cog mr-2" style="color: var(--secondary);"></i>
                            Additional Settings
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Configuration options for the security post
                        </p>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Description -->
                        <div>
                            <label for="description" class="form-label">
                                Description (Optional)
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="3"
                                      class="form-textarea w-full p-3 rounded-lg border @error('description') error-field @enderror"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                      placeholder="Optional description or additional notes..."
                                      maxlength="1000">{{ old('description', $securityPost->description) }}</textarea>
                            <div class="mt-1 flex justify-between text-xs">
                                <span style="color: var(--text-secondary);">
                                    Additional notes or special instructions
                                </span>
                                <span id="descriptionCounter" style="color: var(--text-secondary);">
                                    {{ strlen(old('description', $securityPost->description ?? '')) }}/1000 characters
                                </span>
                            </div>
                            @error('description')
                                <div class="text-sm mt-1 validation-message">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Toggle Switches -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center justify-between p-4 rounded-lg border hover-lift" 
                                 style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div>
                                    <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                        <i class="fas fa-toggle-on mr-2" style="color: var(--primary);"></i> Active Status
                                    </div>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Enable or disable this security post
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           name="is_active" 
                                           value="1" 
                                           class="sr-only peer"
                                           {{ old('is_active', $securityPost->is_active) ? 'checked' : '' }}>
                                    <div class="toggle-switch"></div>
                                </label>
                                <input type="hidden" name="is_active" value="0">
                            </div>
                            
                            <div class="flex items-center justify-between p-4 rounded-lg border hover-lift" 
                                 style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div>
                                    <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                        <i class="fas fa-user-check mr-2" style="color: var(--success);"></i> Require Check-in
                                    </div>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Personnel must check in when arriving
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           name="requires_checkin" 
                                           value="1" 
                                           class="sr-only peer"
                                           {{ old('requires_checkin', $securityPost->requires_checkin) ? 'checked' : '' }}>
                                    <div class="toggle-switch"></div>
                                </label>
                                <input type="hidden" name="requires_checkin" value="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Footer -->
            <div class="mt-8 pt-6 border-t" 
                 style="border-color: var(--border-color);">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        All required fields (<span class="text-red-500">*</span>) must be completed
                    </div>
                    <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3">
                        <button type="button" onclick="showPreview()" class="btn-secondary px-4 py-2">
                            <i class="fas fa-eye mr-2"></i> Preview
                        </button>
                        <a href="{{ route('admin.security-posts.index') }}" class="btn-secondary px-4 py-2 text-center">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn-primary px-4 py-2">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Action Buttons Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Post Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Additional actions for this security post
                    </p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <a href="{{ route('admin.security-posts.show', $securityPost) }}" 
                   class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200"
                   style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="font-medium" style="color: var(--text-primary);">View Post</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Return to post details</div>
                </a>
                
                <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $securityPost->id }}" 
                   class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200"
                   style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="font-medium" style="color: var(--text-primary);">Assign Personnel</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Schedule security personnel</div>
                </a>
                
                <button onclick="showDeleteModal()"
                        class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200 w-full"
                        style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash"></i>
                    </div>
                    <div class="font-medium" style="color: var(--danger);">Delete Post</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Permanently remove this post</div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal hidden">
    <div class="modal-overlay" onclick="closePreview()"></div>
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title flex items-center">
                <i class="fas fa-eye mr-2"></i> Security Post Preview
            </h3>
            <button type="button" class="modal-close" onclick="closePreview()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="previewContent">
                <!-- Preview content will be generated here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closePreview()">
                <i class="fas fa-edit mr-2"></i> Continue Editing
            </button>
            <button type="button" 
                    onclick="document.getElementById('editPostForm').submit()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                <i class="fas fa-check mr-2"></i> Confirm & Update
            </button>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Security Post</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    Delete {{ $securityPost->name }}?
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to delete this security post? This action cannot be undone.
                </p>
                <div class="p-3 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                        <span style="color: var(--danger);">All associated data will be permanently removed</span>
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
            <form method="POST" action="{{ route('admin.security-posts.destroy', $securityPost) }}" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger); border: 1px solid var(--danger);">
                    <i class="fas fa-trash mr-2"></i> Delete Post
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// Initialize arrays from existing data
let equipmentItems = @json($equipment);
let restrictionItems = @json($restrictions);

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Initialize character counters
    const locationTextarea = document.getElementById('location');
    const descriptionTextarea = document.getElementById('description');
    
    if (locationTextarea) {
        updateCharCounter('locationCounter', locationTextarea.value.length, 500);
        locationTextarea.addEventListener('input', function() {
            updateCharCounter('locationCounter', this.value.length, 500);
        });
    }
    
    if (descriptionTextarea) {
        updateCharCounter('descriptionCounter', descriptionTextarea.value.length, 1000);
        descriptionTextarea.addEventListener('input', function() {
            updateCharCounter('descriptionCounter', this.value.length, 1000);
        });
    }
    
    // Working hours with overnight support
    const startTime = document.getElementById('working_hours_start');
    const endTime = document.getElementById('working_hours_end');
    const twentyFourSeven = document.getElementById('twentyFourSeven');
    
    if (startTime && endTime && twentyFourSeven) {
        // Check if hours should be disabled (24/7)
        if (!startTime.value && !endTime.value) {
            twentyFourSeven.checked = true;
            startTime.disabled = true;
            endTime.disabled = true;
        }
        
        // Add real-time preview for working hours
        startTime.addEventListener('change', updateWorkingHoursPreview);
        startTime.addEventListener('input', updateWorkingHoursPreview);
        endTime.addEventListener('change', updateWorkingHoursPreview);
        endTime.addEventListener('input', updateWorkingHoursPreview);
        
        // Initialize preview if values exist
        if (startTime.value || endTime.value) {
            updateWorkingHoursPreview();
        }
        
        twentyFourSeven.addEventListener('change', function() {
            if (this.checked) {
                startTime.value = '';
                endTime.value = '';
                startTime.disabled = true;
                endTime.disabled = true;
                document.getElementById('workingHoursPreview').classList.add('hidden');
                document.getElementById('overnightStatus').classList.add('hidden');
            } else {
                startTime.disabled = false;
                endTime.disabled = false;
                if (startTime.value || endTime.value) {
                    updateWorkingHoursPreview();
                }
            }
        });
    }
    
    // Enter key for equipment and restrictions
    document.getElementById('equipmentInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addEquipmentFromInput();
        }
    });
    
    document.getElementById('restrictionInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addRestrictionFromInput();
        }
    });
    
    // Form validation
    const form = document.getElementById('editPostForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate working hours if provided
            if (!validateWorkingHours()) {
                e.preventDefault();
                showToast('Error: Please provide valid working hours', 'error');
                return false;
            }
            
            // Update hidden fields before submission
            updateEquipmentHiddenFields();
            updateRestrictionHiddenFields();
        });
    }
    
    // Auto-generate code based on name
    document.getElementById('name')?.addEventListener('blur', function() {
        const codeInput = document.getElementById('code');
        const nameInput = this.value.trim();
        
        // Only auto-generate if code is empty and name has value
        if (!codeInput.value && nameInput) {
            const words = nameInput.split(' ').filter(word => word.length > 0);
            let generatedCode = '';
            
            if (words.length === 1) {
                generatedCode = words[0].substring(0, 6).toUpperCase();
            } else {
                generatedCode = words.slice(0, 2)
                    .map(word => word.substring(0, 3).toUpperCase())
                    .join('-');
            }
            
            const timestamp = new Date().getTime().toString().slice(-4);
            codeInput.value = `${generatedCode}-${timestamp}`;
            showToast('Code auto-generated from name', 'info');
        }
    });
});

function updateWorkingHoursPreview() {
    const startTime = document.getElementById('working_hours_start');
    const endTime = document.getElementById('working_hours_end');
    const previewContainer = document.getElementById('workingHoursPreview');
    const previewStart = document.getElementById('previewStartTime');
    const previewEnd = document.getElementById('previewEndTime');
    const previewOvernight = document.getElementById('previewOvernightBadge');
    const previewDuration = document.getElementById('previewDuration');
    const overnightStatus = document.getElementById('overnightStatus');
    const durationDisplay = document.getElementById('durationDisplay');
    
    // Check if both times are set
    if (startTime.value && endTime.value) {
        previewContainer.classList.remove('hidden');
        
        // Format times for display
        const startFormatted = formatTimeDisplay(startTime.value);
        const endFormatted = formatTimeDisplay(endTime.value);
        
        previewStart.textContent = startFormatted;
        previewEnd.textContent = endFormatted;
        
        // Check if overnight
        const start = startTime.value;
        const end = endTime.value;
        const isOvernight = end < start;
        
        if (isOvernight) {
            previewOvernight.classList.remove('hidden');
            overnightStatus.classList.remove('hidden');
            
            // Calculate duration
            const startParts = start.split(':');
            const endParts = end.split(':');
            let startHour = parseInt(startParts[0]);
            let endHour = parseInt(endParts[0]);
            const startMin = parseInt(startParts[1]);
            const endMin = parseInt(endParts[1]);
            
            // If end is before start, add 24 hours
            if (endHour < startHour || (endHour === startHour && endMin < startMin)) {
                endHour += 24;
            }
            
            const totalMinutes = (endHour * 60 + endMin) - (startHour * 60 + startMin);
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            
            let durationText = `${hours}h`;
            if (minutes > 0) {
                durationText += ` ${minutes}m`;
            }
            
            durationDisplay.textContent = durationText;
            previewDuration.textContent = `(${durationText})`;
        } else {
            previewOvernight.classList.add('hidden');
            overnightStatus.classList.add('hidden');
            
            // Calculate same-day duration
            const startParts = start.split(':');
            const endParts = end.split(':');
            const startTotal = parseInt(startParts[0]) * 60 + parseInt(startParts[1]);
            const endTotal = parseInt(endParts[0]) * 60 + parseInt(endParts[1]);
            const diffMinutes = endTotal - startTotal;
            const hours = Math.floor(diffMinutes / 60);
            const minutes = diffMinutes % 60;
            
            let durationText = `${hours}h`;
            if (minutes > 0) {
                durationText += ` ${minutes}m`;
            }
            
            previewDuration.textContent = `(${durationText})`;
        }
    } else if (startTime.value || endTime.value) {
        // Only one time set
        previewContainer.classList.remove('hidden');
        if (startTime.value) {
            previewStart.textContent = formatTimeDisplay(startTime.value);
            previewEnd.textContent = '--:--';
        } else {
            previewStart.textContent = '--:--';
            previewEnd.textContent = formatTimeDisplay(endTime.value);
        }
        previewOvernight.classList.add('hidden');
        previewDuration.textContent = '';
        overnightStatus.classList.add('hidden');
    } else {
        previewContainer.classList.add('hidden');
        overnightStatus.classList.add('hidden');
    }
}

function formatTimeDisplay(timeString) {
    if (!timeString) return '--:--';
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function updateCharCounter(elementId, current, max) {
    const counter = document.getElementById(elementId);
    if (counter) {
        counter.textContent = `${current}/${max} characters`;
        if (current > max * 0.9) {
            counter.style.color = 'var(--danger)';
        } else {
            counter.style.color = 'var(--text-secondary)';
        }
    }
}

function addEquipmentItem(item, fromButton = true) {
    item = item.trim();
    if (!item) {
        showToast('Please enter equipment name', 'warning');
        return;
    }
    
    if (item.length > 100) {
        showToast('Equipment name must be 100 characters or less', 'error');
        return;
    }
    
    if (equipmentItems.includes(item)) {
        showToast('This equipment is already added', 'warning');
        return;
    }
    
    equipmentItems.push(item);
    updateEquipmentList();
    updateEquipmentHiddenFields();
    showToast(`Added: ${item}`, 'success');
    
    if (fromButton) {
        const input = document.getElementById('equipmentInput');
        input.value = '';
        input.focus();
    }
}

function addEquipmentFromInput() {
    const input = document.getElementById('equipmentInput');
    addEquipmentItem(input.value);
}

function removeEquipmentItem(index) {
    const removedItem = equipmentItems[index];
    equipmentItems.splice(index, 1);
    updateEquipmentList();
    updateEquipmentHiddenFields();
    showToast(`Removed: ${removedItem}`, 'info');
}

function updateEquipmentList() {
    const container = document.getElementById('equipmentList');
    
    container.innerHTML = '';
    
    if (equipmentItems.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state text-center py-8 border-2 border-dashed rounded-lg';
        emptyState.style.cssText = 'border-color: var(--border-color); color: var(--text-secondary);';
        emptyState.innerHTML = `
            <i class="fas fa-tools text-2xl mb-2"></i>
            <p class="text-sm">No equipment added yet</p>
            <p class="text-xs mt-1">Click on the suggestions above or type to add equipment</p>
        `;
        container.appendChild(emptyState);
    } else {
        equipmentItems.forEach((item, index) => {
            const itemElement = document.createElement('div');
            itemElement.className = 'equipment-item flex items-center justify-between p-3 rounded-lg border hover:shadow-sm transition-all duration-200';
            itemElement.style.cssText = 'background-color: var(--bg-secondary); border-color: var(--border-color);';
            itemElement.innerHTML = `
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3" 
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-toolbox" style="color: var(--success);"></i>
                    </div>
                    <div>
                        <span style="color: var(--text-primary); font-weight: 500;">${item}</span>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Equipment Item ${index + 1}
                        </div>
                    </div>
                </div>
                <button type="button" onclick="removeEquipmentItem(${index})" 
                        class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 transition-colors duration-200">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(itemElement);
        });
    }
}

function updateEquipmentHiddenFields() {
    const container = document.getElementById('equipmentFields');
    container.innerHTML = '';
    
    equipmentItems.forEach((item, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `equipment[${index}]`;
        input.value = item;
        container.appendChild(input);
    });
}

function addRestrictionItem(item, fromButton = true) {
    item = item.trim();
    if (!item) {
        showToast('Please enter a restriction', 'warning');
        return;
    }
    
    if (item.length > 255) {
        showToast('Restriction must be 255 characters or less', 'error');
        return;
    }
    
    if (restrictionItems.includes(item)) {
        showToast('This restriction is already added', 'warning');
        return;
    }
    
    restrictionItems.push(item);
    updateRestrictionsList();
    updateRestrictionHiddenFields();
    showToast(`Added restriction: ${item}`, 'success');
    
    if (fromButton) {
        const input = document.getElementById('restrictionInput');
        input.value = '';
        input.focus();
    }
}

function addRestrictionFromInput() {
    const input = document.getElementById('restrictionInput');
    addRestrictionItem(input.value);
}

function removeRestrictionItem(index) {
    const removedItem = restrictionItems[index];
    restrictionItems.splice(index, 1);
    updateRestrictionsList();
    updateRestrictionHiddenFields();
    showToast(`Removed restriction: ${removedItem}`, 'info');
}

function updateRestrictionsList() {
    const container = document.getElementById('restrictionsList');
    
    container.innerHTML = '';
    
    if (restrictionItems.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state text-center py-8 border-2 border-dashed rounded-lg';
        emptyState.style.cssText = 'border-color: var(--border-color); color: var(--text-secondary);';
        emptyState.innerHTML = `
            <i class="fas fa-ban text-2xl mb-2"></i>
            <p class="text-sm">No restrictions added</p>
            <p class="text-xs mt-1">Click on the suggestions above or type to add restrictions</p>
        `;
        container.appendChild(emptyState);
    } else {
        restrictionItems.forEach((item, index) => {
            const itemElement = document.createElement('div');
            itemElement.className = 'restriction-item flex items-center justify-between p-3 rounded-lg border hover:shadow-sm transition-all duration-200';
            itemElement.style.cssText = 'background-color: var(--bg-secondary); border-color: var(--border-color);';
            itemElement.innerHTML = `
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3" 
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-ban" style="color: var(--danger);"></i>
                    </div>
                    <div>
                        <span style="color: var(--text-primary); font-weight: 500;">${item}</span>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Restriction ${index + 1}
                        </div>
                    </div>
                </div>
                <button type="button" onclick="removeRestrictionItem(${index})" 
                        class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 transition-colors duration-200">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(itemElement);
        });
    }
}

function updateRestrictionHiddenFields() {
    const container = document.getElementById('restrictionFields');
    container.innerHTML = '';
    
    restrictionItems.forEach((item, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `restrictions[${index}]`;
        input.value = item;
        container.appendChild(input);
    });
}

function validateWorkingHours() {
    const start = document.getElementById('working_hours_start')?.value;
    const end = document.getElementById('working_hours_end')?.value;
    
    // If both are empty, that's okay (24/7)
    if (!start && !end) return true;
    
    // If one is set but not the other
    if ((start && !end) || (!start && end)) {
        showToast('Please provide both start and end times, or leave both empty for 24/7 operation', 'warning');
        return false;
    }
    
    // If both are set
    if (start && end) {
        // Overnight shifts are allowed (end < start)
        // Same-day shifts require end > start
        if (start === end) {
            showToast('Start and end times cannot be the same', 'error');
            return false;
        }
        // If it's not overnight, end must be after start
        if (end < start) {
            // Overnight shift - allowed
            return true;
        }
        // Same-day shift - end must be after start
        if (start >= end) {
            showToast('End time must be after start time for same-day shifts', 'error');
            return false;
        }
    }
    
    return true;
}

function getCurrentLocation() {
    if (!navigator.geolocation) {
        showToast('Geolocation is not supported by your browser', 'error');
        return;
    }
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Getting location...';
    button.disabled = true;
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude.toFixed(6);
            const lng = position.coords.longitude.toFixed(6);
            const address = `GPS: ${lat}, ${lng}`;
            
            document.getElementById('digital_address').value = address;
            showToast('Location retrieved successfully!', 'success');
            
            button.innerHTML = originalHTML;
            button.disabled = false;
        },
        function(error) {
            let message = 'Unable to retrieve location';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = 'Location access was denied';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'Location information is unavailable';
                    break;
                case error.TIMEOUT:
                    message = 'Location request timed out';
                    break;
            }
            showToast(message, 'error');
            
            button.innerHTML = originalHTML;
            button.disabled = false;
        },
        { timeout: 10000 }
    );
}

function showPreview() {
    // Validate required fields
    const requiredFields = ['name', 'code', 'type', 'location', 'max_personnel'];
    let missingFields = [];
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (element && !element.value.trim()) {
            missingFields.push(field.replace('_', ' '));
        }
    });
    
    if (missingFields.length > 0) {
        showToast(`Please fill in required fields: ${missingFields.join(', ')}`, 'error');
        return;
    }
    
    // Validate working hours
    if (!validateWorkingHours()) {
        return;
    }
    
    // Collect form data
    const formData = {
        name: document.getElementById('name').value,
        code: document.getElementById('code').value,
        type: document.querySelector('#type option:checked').text,
        location: document.getElementById('location').value,
        digital_address: document.getElementById('digital_address').value || 'Not specified',
        max_personnel: document.getElementById('max_personnel').value,
        working_hours_start: document.getElementById('working_hours_start').value,
        working_hours_end: document.getElementById('working_hours_end').value,
        description: document.getElementById('description').value,
        equipment: equipmentItems,
        restrictions: restrictionItems,
        is_active: document.querySelector('input[name="is_active"]').checked ? 'Active' : 'Inactive',
        requires_checkin: document.querySelector('input[name="requires_checkin"]').checked ? 'Yes' : 'No'
    };
    
    // Generate preview HTML
    const preview = document.getElementById('previewContent');
    
    let workingHoursText = '24/7 Operation';
    let isOvernight = false;
    let durationText = '';
    
    if (formData.working_hours_start && formData.working_hours_end) {
        const startTime = formatTimeDisplay(formData.working_hours_start);
        const endTime = formatTimeDisplay(formData.working_hours_end);
        const start = formData.working_hours_start;
        const end = formData.working_hours_end;
        isOvernight = end < start;
        
        workingHoursText = `${startTime} - ${endTime}`;
        
        // Calculate duration
        const startParts = start.split(':');
        const endParts = end.split(':');
        let startHour = parseInt(startParts[0]);
        let endHour = parseInt(endParts[0]);
        const startMin = parseInt(startParts[1]);
        const endMin = parseInt(endParts[1]);
        
        if (isOvernight) {
            endHour += 24;
        }
        
        const totalMinutes = (endHour * 60 + endMin) - (startHour * 60 + startMin);
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        
        durationText = `${hours}h`;
        if (minutes > 0) {
            durationText += ` ${minutes}m`;
        }
        
        if (isOvernight) {
            workingHoursText += ' 🌙 (Overnight)';
        }
    }
    
    preview.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                <div>
                    <h4 class="font-bold text-lg" style="color: var(--text-primary);">${formData.name}</h4>
                    <div class="flex items-center mt-1">
                        <span class="px-2 py-1 text-xs rounded-full mr-2" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            ${formData.code}
                        </span>
                        <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            ${formData.type}
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm" style="color: var(--text-secondary);">Status</div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium" 
                          style="background-color: ${formData.is_active === 'Active' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)'}; 
                                 color: ${formData.is_active === 'Active' ? 'var(--success)' : 'var(--danger)'};">
                        ${formData.is_active}
                    </span>
                </div>
            </div>
            
            <!-- Location Section -->
            <div>
                <h5 class="text-sm font-semibold mb-3 uppercase tracking-wider" style="color: var(--text-secondary);">
                    <i class="fas fa-map-marker-alt mr-2"></i> Location Details
                </h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Location</div>
                        <div style="color: var(--text-primary);">${formData.location}</div>
                    </div>
                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Digital Address</div>
                        <div class="flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-map-pin mr-2 text-red-500"></i>
                            ${formData.digital_address}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration -->
            <div>
                <h5 class="text-sm font-semibold mb-3 uppercase tracking-wider" style="color: var(--text-secondary);">
                    <i class="fas fa-cog mr-2"></i> Configuration
                </h5>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Max Personnel</div>
                        <div class="flex items-center">
                            <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                            <span class="text-xl font-bold" style="color: var(--text-primary);">${formData.max_personnel}</span>
                            <span class="ml-1 text-sm" style="color: var(--text-secondary);">persons</span>
                        </div>
                    </div>
                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Working Hours</div>
                        <div class="flex items-center">
                            <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                            <span style="color: var(--text-primary);">${workingHoursText}</span>
                        </div>
                        ${durationText ? `<div class="text-xs mt-1" style="color: var(--text-secondary);">Duration: ${durationText}</div>` : ''}
                    </div>
                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Check-in Required</div>
                        <div class="flex items-center">
                            <i class="fas fa-user-check mr-2" style="color: ${formData.requires_checkin === 'Yes' ? 'var(--success)' : 'var(--danger)'}"></i>
                            <span style="color: var(--text-primary);">${formData.requires_checkin}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Equipment -->
            ${formData.equipment.length > 0 ? `
            <div>
                <h5 class="text-sm font-semibold mb-3 uppercase tracking-wider" style="color: var(--text-secondary);">
                    <i class="fas fa-tools mr-2"></i> Equipment (${formData.equipment.length})
                </h5>
                <div class="flex flex-wrap gap-2">
                    ${formData.equipment.map(item => `
                        <span class="px-3 py-2 rounded-lg border flex items-center" 
                              style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                            <i class="fas fa-check-circle mr-2 text-green-500"></i>${item}
                        </span>
                    `).join('')}
                </div>
            </div>` : ''}
            
            <!-- Restrictions -->
            ${formData.restrictions.length > 0 ? `
            <div>
                <h5 class="text-sm font-semibold mb-3 uppercase tracking-wider" style="color: var(--text-secondary);">
                    <i class="fas fa-ban mr-2"></i> Restrictions (${formData.restrictions.length})
                </h5>
                <div class="space-y-2">
                    ${formData.restrictions.map(item => `
                        <div class="p-3 rounded-lg border flex items-start" 
                             style="border-color: var(--border-color); background-color: rgba(var(--danger-rgb), 0.05);">
                            <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                            <div>
                                <div style="color: var(--text-primary);">${item}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>` : ''}
            
            <!-- Description -->
            ${formData.description ? `
            <div>
                <h5 class="text-sm font-semibold mb-3 uppercase tracking-wider" style="color: var(--text-secondary);">
                    <i class="fas fa-align-left mr-2"></i> Description
                </h5>
                <div class="p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                    <div style="color: var(--text-primary); line-height: 1.6;">${formData.description}</div>
                </div>
            </div>` : ''}
        </div>
    `;
    
    // Show modal
    openModal('previewModal');
}

function showDeleteModal() {
    openModal('deleteModal');
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

function closePreview() {
    closeModal('previewModal');
}

function showToast(message, type = 'info') {
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
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
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
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (!document.getElementById('previewModal').classList.contains('hidden')) {
            document.getElementById('editPostForm').submit();
        } else {
            document.getElementById('editPostForm').submit();
        }
    }
    
    // Escape to close preview
    if (e.key === 'Escape' && !document.getElementById('previewModal').classList.contains('hidden')) {
        closePreview();
    }
    
    // Ctrl/Cmd + P for preview
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        showPreview();
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});
</script>

<style>
/* Apply the same CSS styles as index blade */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* Button styles */
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
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Form elements */
.form-input, .form-select, .form-textarea {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

.form-label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-primary);
}

/* Toggle Switch */
.toggle-switch {
    width: 3.5rem;
    height: 2rem;
    background-color: var(--border-color);
    border-radius: 9999px;
    position: relative;
    transition: background-color 0.2s;
}

.toggle-switch:after {
    content: '';
    position: absolute;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    background-color: white;
    top: 0.25rem;
    left: 0.25rem;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.peer:checked ~ .toggle-switch {
    background-color: var(--success);
}

.peer:checked ~ .toggle-switch:after {
    transform: translateX(1.5rem);
}

/* Equipment and Restriction Tags */
.equipment-tag,
.restriction-tag {
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    user-select: none;
}

.equipment-tag {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.2);
}

.equipment-tag:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.restriction-tag {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.2);
}

.restriction-tag:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-secondary {
    padding: 0.625rem 1.25rem;
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border-radius: 0.5rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    border: 1px solid var(--border-color);
    cursor: pointer;
    text-decoration: none;
}

.btn-secondary:hover {
    background-color: var(--border-color);
    transform: translateY(-1px);
}

/* Modal styles */
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

/* Validation message */
.validation-message {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: block;
    color: var(--danger);
}

/* Error field styling */
.error-field {
    border-color: var(--danger) !important;
    background-color: rgba(var(--danger-rgb), 0.05) !important;
}

.error-field:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

/* Required field indicator */
.required-field::after {
    content: " *";
    color: var(--danger);
}

/* Empty State */
.empty-state {
    border: 2px dashed var(--border-color);
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

/* Hover Effects */
.hover-lift {
    transition: transform 0.2s, box-shadow 0.2s;
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.05);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.3);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2,
    .grid.grid-cols-1.md\:grid-cols-3,
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        margin: 1rem;
    }
}

@media (max-width: 640px) {
    .p-6 {
        padding: 1rem !important;
    }
}
</style>
@endsection