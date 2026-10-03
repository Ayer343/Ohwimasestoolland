@extends('layouts.app')

@section('title', 'Edit Shift: ' . $securityShift->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-edit mr-2" style="color: var(--primary);"></i> 
                        Edit Shift: {{ $securityShift->name }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag mr-2"></i>
                        <code>{{ $securityShift->code }}</code>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-2"></i>
                        <span>{{ \Carbon\Carbon::parse($securityShift->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($securityShift->end_time)->format('h:i A') }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ $securityShift->required_personnel }} personnel</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.security-shifts.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Shifts
                </a>
                <a href="{{ route('admin.security-shifts.show', $securityShift) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-eye mr-2"></i> View Details
                </a>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <form id="shiftForm" method="POST" action="{{ route('admin.security-shifts.update', $securityShift) }}" class="space-y-6">
        @csrf
        @method('PUT')
        
        <!-- Basic Information Card -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Basic Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Shift Name -->
                <div>
                    <label for="name" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Shift Name *
                    </label>
                    <input type="text" 
                           id="name"
                           name="name"
                           class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Morning Patrol Shift"
                           value="{{ old('name', $securityShift->name) }}"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Shift Code -->
                <div>
                    <label for="code" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Shift Code *
                    </label>
                    <input type="text" 
                           id="code"
                           name="code"
                           class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., MORN-PATROL"
                           value="{{ old('code', $securityShift->code) }}"
                           required>
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">Unique identifier for the shift</p>
                    @error('code')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Description
                    </label>
                    <textarea id="description"
                              name="description"
                              rows="2"
                              class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Brief description of the shift duties and requirements">{{ old('description', $securityShift->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Shift Timing & Category Card -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Shift Timing & Category
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Category -->
                <div>
                    <label for="category" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Shift Category *
                    </label>
                    <select id="category"
                            name="category"
                            class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Category</option>
                        @foreach($shiftCategories as $value => $label)
                            <option value="{{ $value }}" {{ old('category', $securityShift->category) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('category')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Required Personnel -->
                <div>
                    <label for="required_personnel" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Required Personnel *
                    </label>
                    <select id="required_personnel"
                            name="required_personnel"
                            class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ old('required_personnel', $securityShift->required_personnel) == $i ? 'selected' : '' }}>
                                {{ $i }} {{ $i == 1 ? 'person' : 'people' }}
                            </option>
                        @endfor
                    </select>
                    @error('required_personnel')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Start Time -->
                <div>
                    <label for="start_time" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Start Time *
                    </label>
                    <input type="time" 
                           id="start_time"
                           name="start_time"
                           class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('start_time', $securityShift->start_time) }}"
                           required>
                    @error('start_time')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- End Time -->
                <div>
                    <label for="end_time" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        End Time *
                    </label>
                    <input type="time" 
                           id="end_time"
                           name="end_time"
                           class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('end_time', $securityShift->end_time) }}"
                           required>
                    @error('end_time')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Duration Display -->
                <div class="md:col-span-2">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary);">Shift Duration</p>
                                <p id="durationDisplay" class="text-lg font-semibold" style="color: var(--primary);">{{ $securityShift->duration_hours }} hours</p>
                            </div>
                            <div id="overnightIndicator" class="{{ $securityShift->is_overnight ? '' : 'hidden' }}">
                                <span class="px-3 py-1 rounded-full text-xs font-medium" 
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-moon mr-1"></i> Overnight Shift
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Type Card -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> Schedule Type
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Day Type -->
                <div>
                    <label for="day_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Applicable Days *
                    </label>
                    <select id="day_type"
                            name="day_type"
                            class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Day Type</option>
                        @foreach($dayTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('day_type', $securityShift->day_type) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('day_type')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    
                    <!-- Custom Days Selection -->
                    <div id="customDaysContainer" class="mt-4 {{ old('day_type', $securityShift->day_type) === 'custom' ? '' : 'hidden' }}">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Select Specific Days *
                        </label>
                        <div class="grid grid-cols-4 md:grid-cols-7 gap-2">
                            @foreach($daysOfWeek as $value => $label)
                                @php
                                    $oldDays = old('applicable_days', $securityShift->applicable_days ?? []);
                                    $isChecked = in_array($value, (array)$oldDays);
                                @endphp
                                <div>
                                    <input type="checkbox"
                                           id="day_{{ $value }}"
                                           name="applicable_days[]"
                                           value="{{ $value }}"
                                           class="hidden peer"
                                           {{ $isChecked ? 'checked' : '' }}>
                                    <label for="day_{{ $value }}"
                                           class="block p-2 text-center text-sm rounded-lg border cursor-pointer transition-all duration-200 peer-checked:border-primary peer-checked:bg-primary/10"
                                           style="border-color: var(--border-color); color: var(--text-primary);">
                                        {{ substr($label, 0, 3) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('applicable_days')
                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Rotation Type -->
                <div>
                    <label for="rotation_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Rotation Type *
                    </label>
                    <select id="rotation_type"
                            name="rotation_type"
                            class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Rotation Type</option>
                        @foreach($rotationTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('rotation_type', $securityShift->rotation_type) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('rotation_type')
                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    
                    <!-- Rotation Configuration -->
                    <div id="rotationConfigContainer" class="mt-4 space-y-4 {{ old('rotation_type', $securityShift->rotation_type) === 'rotating' ? '' : 'hidden' }}">
                        <!-- Rotation Days -->
                        <div>
                            <label for="rotation_days" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Rotation Cycle (Days) *
                            </label>
                            <input type="number"
                                   id="rotation_days"
                                   name="rotation_days"
                                   min="1"
                                   max="30"
                                   class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ old('rotation_days', $securityShift->rotation_config['rotation_days'] ?? 7) }}">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">How many days before rotating to next shift</p>
                            @error('rotation_days')
                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Rotation Sequence -->
                        <div>
                            <label for="rotation_sequence" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Rotation Sequence *
                            </label>
                            <select id="rotation_sequence"
                                    name="rotation_sequence"
                                    class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @foreach($rotationSequences as $value => $label)
                                    <option value="{{ $value }}" {{ old('rotation_sequence', $securityShift->rotation_config['rotation_sequence'] ?? '') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">Order in which shifts rotate</p>
                            @error('rotation_sequence')
                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Break Schedule Card -->
        <div class="card p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-coffee mr-2" style="color: var(--primary);"></i> Break Schedule
                </h3>
                <div class="flex items-center">
                    <input type="checkbox"
                           id="has_break"
                           name="has_break"
                           class="form-checkbox h-4 w-4 rounded transition-all duration-200"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           {{ old('has_break', $securityShift->break_schedule && $securityShift->break_schedule['has_break']) ? 'checked' : '' }}>
                    <label for="has_break" class="ml-2 text-sm" style="color: var(--text-primary);">
                        Include Breaks
                    </label>
                </div>
            </div>
            
            <div id="breakScheduleContainer" class="space-y-4 {{ old('has_break', $securityShift->break_schedule && $securityShift->break_schedule['has_break']) ? '' : 'hidden' }}">
                <!-- Break Schedule Template (Hidden) -->
                <template id="breakTemplate">
                    <div class="break-item p-4 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-medium" style="color: var(--text-primary);">Break <span class="break-index">1</span></h4>
                            <button type="button" class="remove-break-btn text-sm hover:text-danger transition-colors duration-200" style="color: var(--text-secondary);">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Break Name -->
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    Break Name *
                                </label>
                                <input type="text"
                                       name="break_names[]"
                                       class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., Lunch Break"
                                       required>
                            </div>
                            
                            <!-- Start Time -->
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    Start Time *
                                </label>
                                <input type="time"
                                       name="break_start_times[]"
                                       class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       required>
                            </div>
                            
                            <!-- End Time -->
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    End Time *
                                </label>
                                <input type="time"
                                       name="break_end_times[]"
                                       class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       required>
                            </div>
                            
                            <!-- Duration -->
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    Duration (min) *
                                </label>
                                <input type="number"
                                       name="break_durations[]"
                                       min="1"
                                       max="240"
                                       class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., 30"
                                       required>
                            </div>
                        </div>
                        
                        <!-- Additional Options -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <!-- Paid Break -->
                            <div class="flex items-center">
                                <input type="checkbox"
                                       name="break_paid[]"
                                       class="form-checkbox h-4 w-4 rounded transition-all duration-200"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                       value="1">
                                <label class="ml-2 text-xs" style="color: var(--text-primary);">
                                    Paid Break
                                </label>
                            </div>
                            
                            <!-- Description -->
                            <div>
                                <input type="text"
                                       name="break_descriptions[]"
                                       class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Optional description">
                            </div>
                        </div>
                    </div>
                </template>
                
                <!-- Break List Container -->
                <div id="breakListContainer">
                    <!-- Existing breaks will be added here dynamically -->
                    @if($securityShift->break_schedule && !empty($securityShift->break_schedule['breaks']))
                        @foreach($securityShift->break_schedule['breaks'] as $index => $break)
                            <div class="break-item p-4 rounded-lg border" style="border-color: var(--border-color);">
                                <div class="flex justify-between items-start mb-3">
                                    <h4 class="font-medium" style="color: var(--text-primary);">Break {{ $index + 1 }}</h4>
                                    <button type="button" class="remove-break-btn text-sm hover:text-danger transition-colors duration-200" style="color: var(--text-secondary);">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <!-- Break Name -->
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                            Break Name *
                                        </label>
                                        <input type="text"
                                               name="break_names[]"
                                               class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               placeholder="e.g., Lunch Break"
                                               value="{{ old("break_names.$index", $break['name']) }}"
                                               required>
                                    </div>
                                    
                                    <!-- Start Time -->
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                            Start Time *
                                        </label>
                                        <input type="time"
                                               name="break_start_times[]"
                                               class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ old("break_start_times.$index", $break['start_time']) }}"
                                               required>
                                    </div>
                                    
                                    <!-- End Time -->
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                            End Time *
                                        </label>
                                        <input type="time"
                                               name="break_end_times[]"
                                               class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ old("break_end_times.$index", $break['end_time']) }}"
                                               required>
                                    </div>
                                    
                                    <!-- Duration -->
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                            Duration (min) *
                                        </label>
                                        <input type="number"
                                               name="break_durations[]"
                                               min="1"
                                               max="240"
                                               class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               placeholder="e.g., 30"
                                               value="{{ old("break_durations.$index", $break['duration_minutes']) }}"
                                               required>
                                    </div>
                                </div>
                                
                                <!-- Additional Options -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <!-- Paid Break -->
                                    <div class="flex items-center">
                                        <input type="checkbox"
                                               name="break_paid[]"
                                               class="form-checkbox h-4 w-4 rounded transition-all duration-200"
                                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                               value="1"
                                               {{ old("break_paid.$index", $break['is_paid'] ?? false) ? 'checked' : '' }}>
                                        <label class="ml-2 text-xs" style="color: var(--text-primary);">
                                            Paid Break
                                        </label>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div>
                                        <input type="text"
                                               name="break_descriptions[]"
                                               class="w-full p-2 border rounded transition-all duration-200 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               placeholder="Optional description"
                                               value="{{ old("break_descriptions.$index", $break['description'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                
                <!-- Add Break Button -->
                <button type="button" 
                        id="addBreakBtn"
                        class="w-full py-3 border-2 border-dashed rounded-lg hover:border-primary transition-all duration-200 hover:bg-primary/5 flex items-center justify-center"
                        style="border-color: var(--border-color); color: var(--text-secondary);">
                    <i class="fas fa-plus-circle mr-2"></i> Add Break
                </button>
                
                <!-- Total Breaks Summary -->
                <div id="breaksSummary" class="p-3 rounded-lg {{ ($securityShift->break_schedule && !empty($securityShift->break_schedule['breaks'])) ? '' : 'hidden' }}" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Total Break Time</p>
                            <p id="totalBreakTime" class="text-lg font-semibold" style="color: var(--info);">
                                {{ $securityShift->break_schedule['total_break_minutes'] ?? 0 }} minutes
                            </p>
                        </div>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <span id="totalBreaksCount">{{ $securityShift->break_schedule ? count($securityShift->break_schedule['breaks']) : 0 }}</span> breaks
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Handover Configuration Card -->
        <div class="card p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i> Handover Configuration
                </h3>
                <div class="flex items-center">
                    <input type="checkbox"
                           id="has_handover"
                           name="has_handover"
                           class="form-checkbox h-4 w-4 rounded transition-all duration-200"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           {{ old('has_handover', $securityShift->handover_config && $securityShift->handover_config['has_handover']) ? 'checked' : '' }}>
                    <label for="has_handover" class="ml-2 text-sm" style="color: var(--text-primary);">
                        Include Handover Period
                    </label>
                </div>
            </div>
            
            <div id="handoverConfigContainer" class="space-y-4 {{ old('has_handover', $securityShift->handover_config && $securityShift->handover_config['has_handover']) ? '' : 'hidden' }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Handover Duration -->
                    <div>
                        <label for="handover_duration" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Handover Duration (minutes) *
                        </label>
                        <input type="number"
                               id="handover_duration"
                               name="handover_duration"
                               min="5"
                               max="120"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('handover_duration', $securityShift->handover_config['handover_duration'] ?? 30) }}">
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">Overlap period between shifts</p>
                        @error('handover_duration')
                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Handover Notes Required -->
                    <div class="flex items-center pt-6">
                        <input type="checkbox"
                               id="handover_notes_required"
                               name="handover_notes_required"
                               class="form-checkbox h-4 w-4 rounded transition-all duration-200"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                               value="1"
                               {{ old('handover_notes_required', $securityShift->handover_config['handover_notes_required'] ?? true) ? 'checked' : '' }}>
                        <label for="handover_notes_required" class="ml-2 text-sm" style="color: var(--text-primary);">
                            Handover Notes Required
                        </label>
                    </div>
                </div>
                
                <!-- Handover Checklist -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Handover Checklist
                    </label>
                    <div class="space-y-2">
                        @php
                            $defaultChecklist = [
                                'equipment_check' => 'Equipment Check',
                                'incident_report' => 'Incident Reports',
                                'visitor_logs' => 'Visitor Logs',
                                'key_handover' => 'Key Handover',
                                'patrol_report' => 'Patrol Report',
                                'special_instructions' => 'Special Instructions'
                            ];
                            
                            $oldChecklist = old('handover_checklist', $securityShift->handover_config['handover_checklist'] ?? array_keys($defaultChecklist));
                        @endphp
                        
                        @foreach($defaultChecklist as $value => $label)
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="checklist_{{ $value }}"
                                       name="handover_checklist[]"
                                       class="form-checkbox h-4 w-4 rounded transition-all duration-200"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                       value="{{ $value }}"
                                       {{ in_array($value, (array)$oldChecklist) ? 'checked' : '' }}>
                                <label for="checklist_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Status & Actions Card -->
        <div class="card p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Shift Status -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Shift Status
                    </label>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <input type="radio"
                                   id="status_active"
                                   name="is_active"
                                   class="form-radio h-4 w-4 transition-all duration-200"
                                   style="color: var(--success);"
                                   value="1"
                                   {{ old('is_active', $securityShift->is_active) ? 'checked' : '' }}>
                            <label for="status_active" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <span class="flex items-center">
                                    <span class="w-2 h-2 rounded-full mr-2" style="background-color: var(--success);"></span>
                                    Active
                                </span>
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio"
                                   id="status_inactive"
                                   name="is_active"
                                   class="form-radio h-4 w-4 transition-all duration-200"
                                   style="color: var(--danger);"
                                   value="0"
                                   {{ !old('is_active', $securityShift->is_active) ? 'checked' : '' }}>
                            <label for="status_inactive" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <span class="flex items-center">
                                    <span class="w-2 h-2 rounded-full mr-2" style="background-color: var(--danger);"></span>
                                    Inactive
                                </span>
                            </label>
                        </div>
                    </div>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        Inactive shifts won't be available for scheduling
                    </p>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-end items-end space-x-3">
                    <a href="{{ route('admin.security-shifts.show', $securityShift) }}"
                       class="px-6 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 flex items-center"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-save mr-2"></i> Update Shift
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('styles')
<style>
.action-btn {
    @apply w-8 h-8 rounded-lg flex items-center justify-center transition-all duration-200 hover:transform hover:-translate-y-1;
}

.break-item {
    transition: all 0.3s ease;
}

.break-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.break-item.removing {
    opacity: 0;
    transform: translateX(-20px);
}

.form-checkbox:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M5.707 7.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L7 8.586 5.707 7.293z'/%3e%3c/svg%3e");
}

.form-radio:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3ccircle cx='8' cy='8' r='3'/%3e%3c/svg%3e");
}

/* Custom checkbox/radio styling */
.form-checkbox, .form-radio {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    border: 2px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
}

.form-checkbox {
    border-radius: 4px;
}

.form-radio {
    border-radius: 50%;
}

.form-checkbox:checked, .form-radio:checked {
    border-color: var(--primary);
    background-color: var(--primary);
}

.form-checkbox:checked:hover, .form-radio:checked:hover {
    border-color: var(--secondary);
    background-color: var(--secondary);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate shift duration
    function calculateDuration() {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (!startTime || !endTime) return;
        
        const start = new Date(`2000-01-01T${startTime}:00`);
        let end = new Date(`2000-01-01T${endTime}:00`);
        
        // Handle overnight shifts
        if (end <= start) {
            end.setDate(end.getDate() + 1);
            document.getElementById('overnightIndicator').classList.remove('hidden');
        } else {
            document.getElementById('overnightIndicator').classList.add('hidden');
        }
        
        const diffMs = end - start;
        const diffHours = diffMs / (1000 * 60 * 60);
        
        document.getElementById('durationDisplay').textContent = diffHours.toFixed(2) + ' hours';
    }
    
    // Initialize duration calculation
    calculateDuration();
    document.getElementById('start_time').addEventListener('change', calculateDuration);
    document.getElementById('end_time').addEventListener('change', calculateDuration);
    
    // Toggle custom days selection
    const dayTypeSelect = document.getElementById('day_type');
    const customDaysContainer = document.getElementById('customDaysContainer');
    
    function toggleCustomDays() {
        if (dayTypeSelect.value === 'custom') {
            customDaysContainer.classList.remove('hidden');
            // Make checkboxes required
            document.querySelectorAll('#customDaysContainer input[type="checkbox"]').forEach(cb => {
                cb.required = true;
            });
        } else {
            customDaysContainer.classList.add('hidden');
            // Remove required attribute
            document.querySelectorAll('#customDaysContainer input[type="checkbox"]').forEach(cb => {
                cb.required = false;
            });
        }
    }
    
    dayTypeSelect.addEventListener('change', toggleCustomDays);
    toggleCustomDays(); // Initial check
    
    // Toggle rotation configuration
    const rotationTypeSelect = document.getElementById('rotation_type');
    const rotationConfigContainer = document.getElementById('rotationConfigContainer');
    
    function toggleRotationConfig() {
        if (rotationTypeSelect.value === 'rotating') {
            rotationConfigContainer.classList.remove('hidden');
            // Make rotation fields required
            document.getElementById('rotation_days').required = true;
            document.getElementById('rotation_sequence').required = true;
        } else {
            rotationConfigContainer.classList.add('hidden');
            // Remove required attribute
            document.getElementById('rotation_days').required = false;
            document.getElementById('rotation_sequence').required = false;
        }
    }
    
    rotationTypeSelect.addEventListener('change', toggleRotationConfig);
    toggleRotationConfig(); // Initial check
    
    // Break Schedule Management
    const hasBreakCheckbox = document.getElementById('has_break');
    const breakScheduleContainer = document.getElementById('breakScheduleContainer');
    const breakListContainer = document.getElementById('breakListContainer');
    const addBreakBtn = document.getElementById('addBreakBtn');
    const breakTemplate = document.getElementById('breakTemplate');
    const breaksSummary = document.getElementById('breaksSummary');
    let breakCounter = {{ $securityShift->break_schedule ? count($securityShift->break_schedule['breaks']) : 0 }};
    
    // Toggle break schedule
    function toggleBreakSchedule() {
        if (hasBreakCheckbox.checked) {
            breakScheduleContainer.classList.remove('hidden');
            if (breakCounter === 0) {
                addBreak(); // Add first break automatically
            }
        } else {
            breakScheduleContainer.classList.add('hidden');
        }
    }
    
    hasBreakCheckbox.addEventListener('change', toggleBreakSchedule);
    toggleBreakSchedule(); // Initial check
    
    // Add break function
    function addBreak() {
        breakCounter++;
        const clone = breakTemplate.content.cloneNode(true);
        const breakItem = clone.querySelector('.break-item');
        breakItem.setAttribute('data-index', breakCounter);
        
        // Update break index
        const indexSpan = breakItem.querySelector('.break-index');
        indexSpan.textContent = breakCounter;
        
        // Add remove functionality
        const removeBtn = breakItem.querySelector('.remove-break-btn');
        removeBtn.addEventListener('click', function() {
            removeBreak(breakItem);
        });
        
        // Add input event listeners for break time validation
        const startInput = breakItem.querySelector('input[name="break_start_times[]"]');
        const endInput = breakItem.querySelector('input[name="break_end_times[]"]');
        const durationInput = breakItem.querySelector('input[name="break_durations[]"]');
        
        function updateBreakDuration() {
            if (startInput.value && endInput.value) {
                const start = new Date(`2000-01-01T${startInput.value}:00`);
                let end = new Date(`2000-01-01T${endInput.value}:00`);
                
                if (end <= start) {
                    end.setDate(end.getDate() + 1);
                }
                
                const diffMs = end - start;
                const diffMinutes = Math.round(diffMs / (1000 * 60));
                durationInput.value = diffMinutes;
                updateBreaksSummary();
            }
        }
        
        startInput.addEventListener('change', updateBreakDuration);
        endInput.addEventListener('change', updateBreakDuration);
        durationInput.addEventListener('input', updateBreaksSummary);
        
        breakListContainer.appendChild(clone);
        updateBreaksSummary();
    }
    
    // Remove break function
    function removeBreak(breakItem) {
        breakItem.classList.add('removing');
        setTimeout(() => {
            breakItem.remove();
            updateBreaksSummary();
            reindexBreaks();
        }, 300);
    }
    
    // Reindex breaks
    function reindexBreaks() {
        const breaks = document.querySelectorAll('.break-item');
        breaks.forEach((breakItem, index) => {
            const indexSpan = breakItem.querySelector('.break-index');
            if (indexSpan) {
                indexSpan.textContent = index + 1;
            }
            breakItem.setAttribute('data-index', index + 1);
        });
        breakCounter = breaks.length;
    }
    
    // Update breaks summary
    function updateBreaksSummary() {
        const breaks = document.querySelectorAll('.break-item');
        const totalDuration = Array.from(breaks).reduce((total, breakItem) => {
            const durationInput = breakItem.querySelector('input[name="break_durations[]"]');
            return total + (parseInt(durationInput.value) || 0);
        }, 0);
        
        if (breaks.length > 0) {
            breaksSummary.classList.remove('hidden');
            document.getElementById('totalBreakTime').textContent = totalDuration + ' minutes';
            document.getElementById('totalBreaksCount').textContent = breaks.length;
        } else {
            breaksSummary.classList.add('hidden');
        }
    }
    
    // Add break button click event
    addBreakBtn.addEventListener('click', addBreak);
    
    // Add remove event listeners to existing breaks
    document.querySelectorAll('.remove-break-btn').forEach(button => {
        button.addEventListener('click', function() {
            const breakItem = this.closest('.break-item');
            removeBreak(breakItem);
        });
    });
    
    // Add duration update listeners to existing breaks
    document.querySelectorAll('.break-item').forEach(breakItem => {
        const startInput = breakItem.querySelector('input[name="break_start_times[]"]');
        const endInput = breakItem.querySelector('input[name="break_end_times[]"]');
        const durationInput = breakItem.querySelector('input[name="break_durations[]"]');
        
        function updateBreakDuration() {
            if (startInput.value && endInput.value) {
                const start = new Date(`2000-01-01T${startInput.value}:00`);
                let end = new Date(`2000-01-01T${endInput.value}:00`);
                
                if (end <= start) {
                    end.setDate(end.getDate() + 1);
                }
                
                const diffMs = end - start;
                const diffMinutes = Math.round(diffMs / (1000 * 60));
                durationInput.value = diffMinutes;
                updateBreaksSummary();
            }
        }
        
        if (startInput) startInput.addEventListener('change', updateBreakDuration);
        if (endInput) endInput.addEventListener('change', updateBreakDuration);
        if (durationInput) durationInput.addEventListener('input', updateBreaksSummary);
    });
    
    // Initialize breaks summary
    updateBreaksSummary();
    
    // Toggle handover configuration
    const hasHandoverCheckbox = document.getElementById('has_handover');
    const handoverConfigContainer = document.getElementById('handoverConfigContainer');
    
    function toggleHandoverConfig() {
        if (hasHandoverCheckbox.checked) {
            handoverConfigContainer.classList.remove('hidden');
            document.getElementById('handover_duration').required = true;
        } else {
            handoverConfigContainer.classList.add('hidden');
            document.getElementById('handover_duration').required = false;
        }
    }
    
    hasHandoverCheckbox.addEventListener('change', toggleHandoverConfig);
    toggleHandoverConfig(); // Initial check
    
    // Form validation
    document.getElementById('shiftForm').addEventListener('submit', function(e) {
        // Validate break times are within shift hours
        if (hasBreakCheckbox.checked) {
            const shiftStart = document.getElementById('start_time').value;
            const shiftEnd = document.getElementById('end_time').value;
            
            const breakItems = document.querySelectorAll('.break-item');
            for (const breakItem of breakItems) {
                const breakStart = breakItem.querySelector('input[name="break_start_times[]"]').value;
                const breakEnd = breakItem.querySelector('input[name="break_end_times[]"]').value;
                
                if (breakStart && breakEnd) {
                    // Simple validation - could be enhanced
                    if (breakStart < shiftStart || breakEnd > shiftEnd) {
                        if (!confirm('Some breaks are outside shift hours. Continue anyway?')) {
                            e.preventDefault();
                            breakItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            breakItem.style.borderColor = 'var(--danger)';
                            setTimeout(() => {
                                breakItem.style.borderColor = '';
                            }, 2000);
                            break;
                        }
                    }
                }
            }
        }
    });
    
    // Initialize tooltips if needed
    initializeTooltips();
});

// Toast notification function
function showToast(message, type = 'info') {
    // Check if toast container exists, create if not
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const colors = {
        success: { bg: 'var(--success)', text: 'white', icon: 'fa-check-circle' },
        error: { bg: 'var(--danger)', text: 'white', icon: 'fa-exclamation-circle' },
        info: { bg: 'var(--info)', text: 'white', icon: 'fa-info-circle' },
        warning: { bg: 'var(--warning)', text: 'white', icon: 'fa-exclamation-triangle' }
    };
    
    const color = colors[type] || colors.info;
    
    const toast = document.createElement('div');
    toast.className = 'toast-message p-4 rounded-lg shadow-lg transform transition-all duration-300';
    toast.style.backgroundColor = color.bg;
    toast.style.color = color.text;
    toast.style.minWidth = '300px';
    toast.style.transform = 'translateX(400px)';
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${color.icon} mr-3 text-lg"></i>
            <div class="flex-1">${message}</div>
            <button class="ml-3 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 10);
    
    // Close button
    toast.querySelector('button').addEventListener('click', () => {
        removeToast(toast);
    });
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        removeToast(toast);
    }, 5000);
}

function removeToast(toast) {
    toast.style.transform = 'translateX(400px)';
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 300);
}

function initializeTooltips() {
    // Add any tooltip initialization here if needed
}
</script>
@endpush