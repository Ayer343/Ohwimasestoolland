@extends('layouts.app')

@section('title', 'Create Security Shift')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-shield-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                        Create New Security Shift
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Configure shift patterns, rotations, breaks, and personnel requirements</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-1"></i>
                        <span>24/7 Security Operations</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route('admin.security-shifts.index') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Shifts
                </a>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <form id="shiftForm" method="POST" action="{{ route('admin.security-shifts.store') }}" class="space-y-6">
        @csrf
        
        <!-- Basic Information Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Basic Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Shift Name -->
                <div>
                    <label for="name" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Shift Name *
                    </label>
                    <input type="text" 
                           id="name"
                           name="name"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Morning Patrol Shift"
                           value="{{ old('name') }}"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Shift Code -->
                <div>
                    <label for="code" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-barcode mr-1" style="color: var(--primary);"></i> Shift Code *
                    </label>
                    <input type="text" 
                           id="code"
                           name="code"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., MORN-PATROL"
                           value="{{ old('code') }}"
                           required>
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Unique identifier for the shift
                    </p>
                    @error('code')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-align-left mr-1" style="color: var(--primary);"></i> Description
                    </label>
                    <textarea id="description"
                              name="description"
                              rows="3"
                              class="index-custom-textarea w-full"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Brief description of the shift duties and requirements">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Shift Timing & Category Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Shift Timing & Category
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Category -->
                <div>
                    <label for="category" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Shift Category *
                    </label>
                    <select id="category"
                            name="category"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Category</option>
                        @foreach($shiftCategories as $value => $label)
                            <option value="{{ $value }}" {{ old('category') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('category')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Required Personnel -->
                <div>
                    <label for="required_personnel" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-1" style="color: var(--primary);"></i> Required Personnel *
                    </label>
                    <select id="required_personnel"
                            name="required_personnel"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ old('required_personnel') == $i ? 'selected' : '' }}>
                                {{ $i }} {{ $i == 1 ? 'Guard' : 'Guards' }}
                            </option>
                        @endfor
                    </select>
                    @error('required_personnel')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Start Time -->
                <div>
                    <label for="start_time" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-play mr-1" style="color: var(--success);"></i> Start Time *
                    </label>
                    <input type="time" 
                           id="start_time"
                           name="start_time"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('start_time', '08:00') }}"
                           required>
                    @error('start_time')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- End Time -->
                <div>
                    <label for="end_time" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-stop mr-1" style="color: var(--danger);"></i> End Time *
                    </label>
                    <input type="time" 
                           id="end_time"
                           name="end_time"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('end_time', '16:00') }}"
                           required>
                    @error('end_time')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Duration Display -->
                <div class="md:col-span-2">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-hourglass-half mr-1"></i> Shift Duration
                                </p>
                                <p id="durationDisplay" class="text-2xl font-bold" style="color: var(--primary);">8.00 hours</p>
                            </div>
                            <div id="overnightIndicator" class="hidden">
                                <span class="px-3 py-1.5 rounded-full text-xs font-medium badge-primary">
                                    <i class="fas fa-moon mr-1"></i> Overnight Shift
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Type Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> Schedule Type
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Day Type -->
                <div>
                    <label for="day_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-day mr-1" style="color: var(--primary);"></i> Applicable Days *
                    </label>
                    <select id="day_type"
                            name="day_type"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Day Type</option>
                        @foreach($dayTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('day_type') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('day_type')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                    
                    <!-- Custom Days Selection (Initially Hidden) -->
                    <div id="customDaysContainer" class="mt-4 hidden">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-check-square mr-1" style="color: var(--primary);"></i> Select Specific Days *
                        </label>
                        <div class="grid grid-cols-4 md:grid-cols-7 gap-2">
                            @foreach($daysOfWeek as $value => $label)
                                <div>
                                    <input type="checkbox"
                                           id="day_{{ $value }}"
                                           name="applicable_days[]"
                                           value="{{ $value }}"
                                           class="hidden peer"
                                           {{ is_array(old('applicable_days')) && in_array($value, old('applicable_days')) ? 'checked' : '' }}>
                                    <label for="day_{{ $value }}"
                                           class="block p-2 text-center text-sm rounded-lg border cursor-pointer transition-all duration-200 peer-checked:border-primary peer-checked:bg-primary/10"
                                           style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                                        {{ substr($label, 0, 3) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('applicable_days')
                            <p class="mt-1 text-sm" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
                
                <!-- Rotation Type -->
                <div>
                    <label for="rotation_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Type *
                    </label>
                    <select id="rotation_type"
                            name="rotation_type"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Rotation Type</option>
                        @foreach($rotationTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('rotation_type') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('rotation_type')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                    
                    <!-- Rotation Configuration (Initially Hidden) -->
                    <div id="rotationConfigContainer" class="mt-4 space-y-4 hidden">
                        <div>
                            <label for="rotation_days" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-week mr-1" style="color: var(--primary);"></i> Rotation Cycle (Days) *
                            </label>
                            <input type="number"
                                   id="rotation_days"
                                   name="rotation_days"
                                   min="1"
                                   max="30"
                                   class="index-custom-input w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ old('rotation_days', 7) }}">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> How many days before rotating to next shift (1-30)
                            </p>
                            @error('rotation_days')
                                <p class="mt-1 text-sm" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                                </p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="rotation_sequence" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-sort-amount-down mr-1" style="color: var(--primary);"></i> Rotation Sequence *
                            </label>
                            <select id="rotation_sequence"
                                    name="rotation_sequence"
                                    class="index-custom-dropdown w-full"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @foreach($rotationSequences as $value => $label)
                                    <option value="{{ $value }}" {{ old('rotation_sequence') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> Order in which shifts rotate
                            </p>
                            @error('rotation_sequence')
                                <p class="mt-1 text-sm" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Break Schedule Card -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-coffee mr-2" style="color: var(--primary);"></i> Break Schedule
                </h3>
                <div class="flex items-center">
                    <!-- FIXED: Hidden field ensures value is always sent -->
                    <input type="hidden" name="has_break" value="0">
                    
                    <input type="checkbox"
                           id="has_break"
                           name="has_break"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('has_break', '0') == '1' ? 'checked' : '' }}>
                    <label for="has_break" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                        Include Breaks
                    </label>
                </div>
            </div>
            
            <div id="breakScheduleContainer" class="space-y-4 {{ old('has_break', '0') == '1' ? '' : 'hidden' }}">
                <!-- Break Schedule Template (Hidden) -->
                <template id="breakTemplate">
                    <div class="break-item p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg);">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-coffee mr-2" style="color: var(--primary);"></i> Break <span class="break-index">1</span>
                            </h4>
                            <button type="button" class="remove-break-btn text-sm hover:text-danger transition-colors duration-200 px-2 py-1 rounded-lg" 
                                    style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05);">
                                <i class="fas fa-trash-alt mr-1"></i> Remove
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    <i class="fas fa-tag mr-1"></i> Break Name *
                                </label>
                                <input type="text"
                                       name="break_names[]"
                                       class="break-name index-custom-input w-full"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., Lunch Break">
                            </div>
                            
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    <i class="fas fa-play mr-1" style="color: var(--success);"></i> Start Time *
                                </label>
                                <input type="time"
                                       name="break_start_times[]"
                                       class="break-start-time index-custom-input w-full"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            </div>
                            
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    <i class="fas fa-stop mr-1" style="color: var(--danger);"></i> End Time *
                                </label>
                                <input type="time"
                                       name="break_end_times[]"
                                       class="break-end-time index-custom-input w-full"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            </div>
                            
                            <div>
                                <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">
                                    <i class="fas fa-hourglass-half mr-1"></i> Duration (min) *
                                </label>
                                <input type="number"
                                       name="break_durations[]"
                                       min="1"
                                       max="240"
                                       class="break-duration index-custom-input w-full bg-gray-100 dark:bg-gray-700"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Auto-calc"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="flex items-center">
                                <input type="checkbox"
                                       name="break_paid[]"
                                       class="break-paid index-custom-checkbox h-4 w-4"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                       value="1">
                                <label class="ml-2 text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-money-bill-wave mr-1" style="color: var(--success);"></i> Paid Break
                                </label>
                            </div>
                            
                            <div>
                                <input type="text"
                                       name="break_descriptions[]"
                                       class="break-description index-custom-input w-full"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Optional description">
                            </div>
                        </div>
                    </div>
                </template>
                
                <!-- Break List Container -->
                <div id="breakListContainer" class="space-y-4">
                    @if(old('break_names') && is_array(old('break_names')) && count(array_filter(old('break_names'))) > 0)
                        @foreach(old('break_names') as $index => $breakName)
                            @if(!empty($breakName) || !empty(old('break_start_times')[$index]) || !empty(old('break_end_times')[$index]))
                            <div class="break-item p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg);" data-index="{{ $loop->iteration }}">
                                <div class="flex justify-between items-start mb-3">
                                    <h4 class="font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-coffee mr-2" style="color: var(--primary);"></i> Break {{ $loop->iteration }}
                                    </h4>
                                    <button type="button" class="remove-break-btn text-sm hover:text-danger transition-colors duration-200 px-2 py-1 rounded-lg"
                                            style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05);">
                                        <i class="fas fa-trash-alt mr-1"></i> Remove
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Break Name *</label>
                                        <input type="text"
                                               name="break_names[]"
                                               class="break-name index-custom-input w-full"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ $breakName }}"
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Start Time *</label>
                                        <input type="time"
                                               name="break_start_times[]"
                                               class="break-start-time index-custom-input w-full"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ old('break_start_times')[$index] ?? '' }}"
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">End Time *</label>
                                        <input type="time"
                                               name="break_end_times[]"
                                               class="break-end-time index-custom-input w-full"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ old('break_end_times')[$index] ?? '' }}"
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Duration (min) *</label>
                                        <input type="number"
                                               name="break_durations[]"
                                               min="1"
                                               max="240"
                                               class="break-duration index-custom-input w-full bg-gray-100 dark:bg-gray-700"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ old('break_durations')[$index] ?? '' }}"
                                               readonly>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div class="flex items-center">
                                        <input type="checkbox"
                                               name="break_paid[]"
                                               class="break-paid index-custom-checkbox h-4 w-4"
                                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                               value="1"
                                               {{ isset(old('break_paid')[$index]) && old('break_paid')[$index] == '1' ? 'checked' : '' }}>
                                        <label class="ml-2 text-sm" style="color: var(--text-primary);">Paid Break</label>
                                    </div>
                                    
                                    <div>
                                        <input type="text"
                                               name="break_descriptions[]"
                                               class="break-description index-custom-input w-full"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               value="{{ old('break_descriptions')[$index] ?? '' }}"
                                               placeholder="Optional description">
                                    </div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    @endif
                </div>
                
                <!-- Add Break Button -->
                <button type="button" 
                        id="addBreakBtn"
                        class="w-full py-3 border-2 border-dashed rounded-lg hover:border-primary transition-all duration-200 hover:bg-primary/5 flex items-center justify-center"
                        style="border-color: var(--border-color); color: var(--text-secondary); background-color: var(--card-bg);">
                    <i class="fas fa-plus-circle mr-2" style="color: var(--primary);"></i> Add Break
                </button>
                
                <!-- Total Breaks Summary -->
                <div id="breaksSummary" class="p-4 rounded-lg {{ old('break_names') && count(array_filter(old('break_names'))) > 0 ? '' : 'hidden' }}" 
                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i> Total Break Time
                            </p>
                            <p id="totalBreakTime" class="text-xl font-bold" style="color: var(--info);">0 minutes</p>
                        </div>
                        <div>
                            <span class="px-3 py-1.5 rounded-full text-xs font-medium badge-info">
                                <i class="fas fa-coffee mr-1"></i> <span id="totalBreaksCount">0</span> breaks
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Handover Configuration Card -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i> Handover Configuration
                </h3>
                <div class="flex items-center">
                    <!-- FIXED: Hidden field ensures value is always sent -->
                    <input type="hidden" name="has_handover" value="0">
                    
                    <input type="checkbox"
                           id="has_handover"
                           name="has_handover"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('has_handover', '0') == '1' ? 'checked' : '' }}>
                    <label for="has_handover" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                        Include Handover Period
                    </label>
                </div>
            </div>
            
            <div id="handoverConfigContainer" class="space-y-4 {{ old('has_handover', '0') == '1' ? '' : 'hidden' }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="handover_duration" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-hourglass-half mr-1" style="color: var(--primary);"></i> Handover Duration (minutes)
                        </label>
                        <input type="number"
                               id="handover_duration"
                               name="handover_duration"
                               min="5"
                               max="120"
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('handover_duration', 30) }}">
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Overlap period between shifts (5-120 minutes)
                        </p>
                        @error('handover_duration')
                            <p class="mt-1 text-sm" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>
                    
                    <div class="flex items-center pt-8">
                        <input type="checkbox"
                               id="handover_notes_required"
                               name="handover_notes_required"
                               class="index-custom-checkbox h-5 w-5"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                               value="1"
                               {{ old('handover_notes_required', '1') == '1' ? 'checked' : '' }}>
                        <label for="handover_notes_required" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-clipboard-check mr-1" style="color: var(--success);"></i> Require handover notes
                        </label>
                    </div>
                </div>
                
                <!-- Handover Checklist -->
                <div class="mt-4">
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-list-check mr-1" style="color: var(--primary);"></i> Handover Checklist Items
                    </label>
                    <p class="text-xs mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Select items that must be checked during handover
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4 rounded-lg" 
                         style="background-color: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color);">
                        @php
                            $checklistItems = [
                                'equipment_check' => 'Equipment Check',
                                'incident_report' => 'Incident Reports',
                                'visitor_logs' => 'Visitor Logs',
                                'key_handover' => 'Key Handover',
                                'patrol_report' => 'Patrol Report',
                                'special_instructions' => 'Special Instructions'
                            ];
                            $oldChecklist = old('handover_checklist', array_keys($checklistItems));
                        @endphp
                        
                        @foreach($checklistItems as $value => $label)
                            <div class="flex items-center p-2 rounded-lg hover:bg-primary/5 transition-colors duration-200">
                                <input type="checkbox"
                                       id="checklist_{{ $value }}"
                                       name="handover_checklist[]"
                                       class="index-custom-checkbox h-4 w-4"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                       value="{{ $value }}"
                                       {{ in_array($value, $oldChecklist) ? 'checked' : '' }}>
                                <label for="checklist_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('handover_checklist')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Status & Actions Card -->
        <div class="card p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Shift Status -->
                <div>
                    <label class="block mb-3 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-power-off mr-1" style="color: var(--primary);"></i> Shift Status
                    </label>
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center">
                            <input type="radio"
                                   id="status_active"
                                   name="is_active"
                                   class="form-radio h-4 w-4 transition-all duration-200"
                                   style="color: var(--success);"
                                   value="1"
                                   {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                            <label for="status_active" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <span class="flex items-center">
                                    <span class="status-indicator status-available w-2 h-2 mr-2"></span>
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
                                   {{ old('is_active') == '0' ? 'checked' : '' }}>
                            <label for="status_inactive" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <span class="flex items-center">
                                    <span class="status-indicator status-unavailable w-2 h-2 mr-2"></span>
                                    Inactive
                                </span>
                            </label>
                        </div>
                    </div>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Inactive shifts won't be available for scheduling
                    </p>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-end items-end space-x-3">
                    <a href="{{ route('admin.security-shifts.index') }}"
                       class="btn-secondary px-6 py-3 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <button type="submit"
                            class="btn-primary px-6 py-3 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-save mr-2"></i> Create Shift
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<!-- Keep all your existing styles - they're perfect -->
<style>
/* ========== INDEX-SPECIFIC FORM CONTROL STYLES ========== */
/* These match exactly the property_units/index.blade.php styling */

.index-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

[data-theme="dark"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

[data-theme="light"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.index-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown option {
    background-color: var(--card-bg);
    color: var(--text-primary);
}

[data-theme="dark"] .index-custom-dropdown option {
    background-color: #2a2a3c;
    color: #e4e4e4;
}

[data-theme="light"] .index-custom-dropdown option {
    background-color: #ffffff;
    color: #4b4b4b;
}

.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-input:disabled {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.index-custom-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.index-custom-input::placeholder,
.index-custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.index-custom-dropdown:focus-visible,
.index-custom-input:focus-visible,
.index-custom-textarea:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* ========== CARD STYLES ========== */
.card {
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

/* ========== BUTTON STYLES ========== */
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

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #dc3545 !important;
    transform: translateY(-1px);
}

/* ========== BADGE STYLES ========== */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

/* ========== STATUS INDICATORS ========== */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-indicator::before {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    display: inline-block;
}

.status-available {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-available::before {
    background-color: var(--success);
}

.status-occupied {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.status-occupied::before {
    background-color: var(--primary);
}

.status-unavailable {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-unavailable::before {
    background-color: var(--danger);
}

/* ========== BREAK ITEM STYLES ========== */
.break-item {
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.break-item:hover {
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.1);
}

.break-item.removing {
    opacity: 0;
    transform: translateX(-20px);
}

/* ========== CUSTOM CHECKBOX PEER STYLES ========== */
.peer:checked + label {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
}

/* ========== ANIMATIONS ========== */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.break-item {
    animation: fadeIn 0.3s ease-out;
}

/* ========== RESPONSIVE ADJUSTMENTS ========== */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .btn-primary, .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .flex.justify-end {
        justify-content: flex-start;
        margin-top: 1rem;
    }
}

/* ========== FORM RADIO STYLES ========== */
.form-radio {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 50%;
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.form-radio:checked {
    border-color: currentColor;
    box-shadow: inset 0 0 0 3px var(--card-bg), inset 0 0 0 6px currentColor;
}

.form-radio:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ========== SHIFT DURATION CALCULATION ==========
    function calculateDuration() {
        const startTime = document.getElementById('start_time');
        const endTime = document.getElementById('end_time');
        
        if (!startTime?.value || !endTime?.value) return;
        
        const start = new Date(`2000-01-01T${startTime.value}:00`);
        let end = new Date(`2000-01-01T${endTime.value}:00`);
        
        const overnightIndicator = document.getElementById('overnightIndicator');
        if (end <= start) {
            end.setDate(end.getDate() + 1);
            overnightIndicator?.classList.remove('hidden');
        } else {
            overnightIndicator?.classList.add('hidden');
        }
        
        const diffMs = end - start;
        const diffHours = diffMs / (1000 * 60 * 60);
        document.getElementById('durationDisplay').textContent = diffHours.toFixed(2) + ' hours';
    }
    
    // Initialize duration calculation
    calculateDuration();
    document.getElementById('start_time')?.addEventListener('change', calculateDuration);
    document.getElementById('end_time')?.addEventListener('change', calculateDuration);
    
    // ========== CUSTOM DAYS TOGGLE ==========
    const dayTypeSelect = document.getElementById('day_type');
    const customDaysContainer = document.getElementById('customDaysContainer');
    
    function toggleCustomDays() {
        if (dayTypeSelect?.value === 'custom') {
            customDaysContainer?.classList.remove('hidden');
        } else {
            customDaysContainer?.classList.add('hidden');
        }
    }
    
    dayTypeSelect?.addEventListener('change', toggleCustomDays);
    toggleCustomDays();
    
    // ========== ROTATION CONFIG TOGGLE ==========
    const rotationTypeSelect = document.getElementById('rotation_type');
    const rotationConfigContainer = document.getElementById('rotationConfigContainer');
    
    function toggleRotationConfig() {
        if (rotationTypeSelect?.value === 'rotating') {
            rotationConfigContainer?.classList.remove('hidden');
            document.getElementById('rotation_days')?.setAttribute('required', 'required');
            document.getElementById('rotation_sequence')?.setAttribute('required', 'required');
        } else {
            rotationConfigContainer?.classList.add('hidden');
            document.getElementById('rotation_days')?.removeAttribute('required');
            document.getElementById('rotation_sequence')?.removeAttribute('required');
        }
    }
    
    rotationTypeSelect?.addEventListener('change', toggleRotationConfig);
    toggleRotationConfig();
    
    // ========== BREAK SCHEDULE MANAGEMENT ==========
    const hasBreakCheckbox = document.getElementById('has_break');
    const breakScheduleContainer = document.getElementById('breakScheduleContainer');
    const breakListContainer = document.getElementById('breakListContainer');
    const addBreakBtn = document.getElementById('addBreakBtn');
    const breakTemplate = document.getElementById('breakTemplate');
    const breaksSummary = document.getElementById('breaksSummary');
    
    // Initialize break counter
    let breakCounter = document.querySelectorAll('.break-item').length;
    
    // Calculate break duration
    function calculateBreakDuration(startInput, endInput, durationInput) {
        if (startInput?.value && endInput?.value) {
            const start = new Date(`2000-01-01T${startInput.value}:00`);
            let end = new Date(`2000-01-01T${endInput.value}:00`);
            
            if (end <= start) {
                end.setDate(end.getDate() + 1);
            }
            
            const diffMs = end - start;
            const diffMinutes = Math.round(diffMs / (1000 * 60));
            if (durationInput) {
                durationInput.value = diffMinutes;
                updateBreaksSummary();
            }
        }
    }
    
    // Update breaks summary
    function updateBreaksSummary() {
        const breaks = document.querySelectorAll('.break-item');
        let totalDuration = 0;
        
        breaks.forEach(breakItem => {
            const durationInput = breakItem.querySelector('.break-duration');
            if (durationInput?.value) {
                totalDuration += parseInt(durationInput.value) || 0;
            }
        });
        
        if (breaks.length > 0) {
            breaksSummary?.classList.remove('hidden');
            document.getElementById('totalBreakTime').textContent = totalDuration + ' minutes';
            document.getElementById('totalBreaksCount').textContent = breaks.length;
        } else {
            breaksSummary?.classList.add('hidden');
        }
    }
    
    // Remove break
    function removeBreak(breakItem) {
        breakItem.classList.add('removing');
        setTimeout(() => {
            breakItem.remove();
            breakCounter = document.querySelectorAll('.break-item').length;
            updateBreaksSummary();
        }, 300);
    }
    
    // Add break
    function addBreak() {
        if (!breakTemplate) return;
        
        breakCounter++;
        const clone = breakTemplate.content.cloneNode(true);
        const breakItem = clone.querySelector('.break-item');
        breakItem.setAttribute('data-index', breakCounter);
        
        // Update break index
        const indexSpan = breakItem.querySelector('.break-index');
        if (indexSpan) indexSpan.textContent = breakCounter;
        
        // Add remove functionality
        const removeBtn = breakItem.querySelector('.remove-break-btn');
        removeBtn?.addEventListener('click', function() {
            removeBreak(breakItem);
        });
        
        // Add duration calculation
        const startInput = breakItem.querySelector('.break-start-time');
        const endInput = breakItem.querySelector('.break-end-time');
        const durationInput = breakItem.querySelector('.break-duration');
        
        if (startInput && endInput && durationInput) {
            startInput.addEventListener('change', function() {
                calculateBreakDuration(startInput, endInput, durationInput);
            });
            
            endInput.addEventListener('change', function() {
                calculateBreakDuration(startInput, endInput, durationInput);
            });
        }
        
        breakListContainer?.appendChild(clone);
        updateBreaksSummary();
    }
    
    // Toggle break schedule
    function toggleBreakSchedule() {
        if (hasBreakCheckbox?.checked) {
            breakScheduleContainer?.classList.remove('hidden');
            if (breakCounter === 0) addBreak();
        } else {
            breakScheduleContainer?.classList.add('hidden');
            if (breakListContainer) breakListContainer.innerHTML = '';
            breakCounter = 0;
            updateBreaksSummary();
        }
    }
    
    hasBreakCheckbox?.addEventListener('change', toggleBreakSchedule);
    addBreakBtn?.addEventListener('click', addBreak);
    
    // Initialize existing breaks
    document.querySelectorAll('.break-item').forEach(breakItem => {
        const removeBtn = breakItem.querySelector('.remove-break-btn');
        removeBtn?.addEventListener('click', function() {
            removeBreak(breakItem);
        });
        
        const startInput = breakItem.querySelector('.break-start-time');
        const endInput = breakItem.querySelector('.break-end-time');
        const durationInput = breakItem.querySelector('.break-duration');
        
        if (startInput && endInput && durationInput) {
            startInput.addEventListener('change', function() {
                calculateBreakDuration(startInput, endInput, durationInput);
            });
            
            endInput.addEventListener('change', function() {
                calculateBreakDuration(startInput, endInput, durationInput);
            });
            
            if (startInput.value && endInput.value) {
                calculateBreakDuration(startInput, endInput, durationInput);
            }
        }
    });
    
    updateBreaksSummary();
    
    // ========== HANDOVER CONFIG TOGGLE ==========
    const hasHandoverCheckbox = document.getElementById('has_handover');
    const handoverConfigContainer = document.getElementById('handoverConfigContainer');
    
    function toggleHandoverConfig() {
        if (hasHandoverCheckbox?.checked) {
            handoverConfigContainer?.classList.remove('hidden');
        } else {
            handoverConfigContainer?.classList.add('hidden');
        }
    }
    
    hasHandoverCheckbox?.addEventListener('change', toggleHandoverConfig);
    toggleHandoverConfig();
    
    // ========== FORM SUBMIT VALIDATION ==========
    document.getElementById('shiftForm')?.addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessage = '';
        
        // Validate custom days
        if (dayTypeSelect?.value === 'custom') {
            const checkedDays = document.querySelectorAll('#customDaysContainer input[type="checkbox"]:checked');
            if (checkedDays.length === 0) {
                errorMessage += '• Please select at least one day for custom schedule.\n';
                isValid = false;
                customDaysContainer?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // Validate breaks
        if (hasBreakCheckbox?.checked) {
            const shiftStart = document.getElementById('start_time')?.value;
            const shiftEnd = document.getElementById('end_time')?.value;
            const breakItems = document.querySelectorAll('.break-item');
            
            if (breakItems.length === 0) {
                errorMessage += '• Please add at least one break or uncheck "Include Breaks".\n';
                isValid = false;
            }
            
            breakItems.forEach((breakItem, index) => {
                const breakName = breakItem.querySelector('.break-name')?.value || `Break ${index + 1}`;
                const breakStart = breakItem.querySelector('.break-start-time')?.value;
                const breakEnd = breakItem.querySelector('.break-end-time')?.value;
                const breakDuration = breakItem.querySelector('.break-duration')?.value;
                
                if (!breakName?.trim()) {
                    errorMessage += `• Break ${index + 1}: Name is required.\n`;
                    isValid = false;
                }
                
                if (!breakStart) {
                    errorMessage += `• ${breakName}: Start time is required.\n`;
                    isValid = false;
                }
                
                if (!breakEnd) {
                    errorMessage += `• ${breakName}: End time is required.\n`;
                    isValid = false;
                }
                
                if (breakStart && breakEnd && shiftStart && shiftEnd) {
                    if (breakStart < shiftStart || breakEnd > shiftEnd) {
                        errorMessage += `• ${breakName} (${breakStart}-${breakEnd}) is outside shift hours (${shiftStart}-${shiftEnd}).\n`;
                        isValid = false;
                        breakItem.style.borderColor = 'var(--warning)';
                        setTimeout(() => {
                            breakItem.style.borderColor = '';
                        }, 2000);
                    }
                }
                
                if (!breakDuration || parseInt(breakDuration) <= 0) {
                    errorMessage += `• ${breakName}: Duration must be greater than 0.\n`;
                    isValid = false;
                }
            });
        }
        
        // Validate handover
        if (hasHandoverCheckbox?.checked) {
            const handoverDuration = document.getElementById('handover_duration')?.value;
            if (!handoverDuration || parseInt(handoverDuration) < 5) {
                errorMessage += '• Handover duration must be at least 5 minutes.\n';
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errorMessage);
        }
    });
});
</script>
@endpush