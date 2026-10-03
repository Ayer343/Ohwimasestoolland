@extends('layouts.app')

@section('title', 'Create Rotation Group')

{{-- ============================================================================
     CRITICAL STYLES - Load immediately to prevent layout shifts
     ============================================================================ --}}
@push('critical-styles')
<style>
    /* ===== SIDEBAR DIMENSIONS - CRITICAL ===== */
    .sidebar {
        width: 280px;
        transition: none !important;
    }
    .sidebar.collapsed {
        width: 80px;
    }
    .content {
        margin-left: 280px;
        transition: none !important;
    }
    .content.collapsed {
        margin-left: 80px;
    }
    
    /* ===== RESERVE SPACE FOR DYNAMIC CONTENT - CRITICAL ===== */
    #rotationConfigCard {
        min-height: 300px;
        display: block;
    }
    #rotationConfigCard.hidden {
        display: none;
    }
    
    #shiftDetailsPreview {
        min-height: 80px;
        display: block;
    }
    #shiftDetailsPreview.hidden {
        display: none;
    }
    
    #autoRotateSettings {
        min-height: 100px;
        display: block;
    }
    #autoRotateSettings.hidden {
        display: none;
    }
    
    /* ===== CARD DIMENSIONS - CRITICAL ===== */
    .card {
        min-height: 100px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
    }
    
    /* ===== FORM ELEMENT DIMENSIONS - CRITICAL ===== */
    .index-custom-input,
    .index-custom-dropdown,
    .index-custom-textarea {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
    }
    
    /* ===== TABLE DIMENSIONS - CRITICAL ===== */
    .max-h-96 {
        max-height: 384px;
        overflow-y: auto;
    }
    
    /* ===== PREVENT FOUC ON PAGE LOAD ===== */
    body:not(.js-enabled) .sidebar {
        /* Default expanded state if JS fails */
        width: 280px;
    }
    body:not(.js-enabled) .content {
        margin-left: 280px;
    }
    
    /* Hide elements that depend on JS initially */
    .js-required {
        visibility: hidden;
    }
    .js-enabled .js-required {
        visibility: visible;
    }
</style>
@endpush

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-rotate text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-rotate mr-2" style="color: var(--primary);"></i>
                        Create New Rotation Group
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Configure group rotation patterns, assign members, and set preferences</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>Team Rotation Management</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route('admin.rotation-groups.index') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Groups
                </a>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <form id="rotationGroupForm" method="POST" action="{{ route('admin.rotation-groups.store') }}" class="space-y-6">
        @csrf
        
        <!-- Basic Information Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Basic Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Group Name -->
                <div>
                    <label for="name" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Group Name *
                    </label>
                    <input type="text" 
                           id="name"
                           name="name"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Alpha Patrol Team"
                           value="{{ old('name') }}"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Group Code -->
                <div>
                    <label for="code" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-barcode mr-1" style="color: var(--primary);"></i> Group Code
                    </label>
                    <input type="text" 
                           id="code"
                           name="code"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., APT-001 (Auto-generated if empty)"
                           value="{{ old('code') }}">
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Unique identifier for the group
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
                              placeholder="Brief description of the group's purpose and responsibilities">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Assignment Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-map-pin mr-2" style="color: var(--primary);"></i> Post & Shift Assignment
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Security Post -->
                <div>
                    <label for="security_post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-1" style="color: var(--primary);"></i> Security Post *
                    </label>
                    <select id="security_post_id"
                            name="security_post_id"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Security Post</option>
                        @foreach($posts as $post)
                            <option value="{{ $post->id }}" {{ old('security_post_id') == $post->id ? 'selected' : '' }}>
                                {{ $post->name }} ({{ $post->code }}) - Max: {{ $post->max_personnel }}
                                @if(!$post->is_active) [Inactive] @endif
                            </option>
                        @endforeach
                    </select>
                    @error('security_post_id')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Security Shift -->
                <div>
                    <label for="security_shift_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Security Shift *
                    </label>
                    <select id="security_shift_id"
                            name="security_shift_id"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Security Shift</option>
                        @foreach($shifts as $shift)
                            <option value="{{ $shift->id }}" 
                                    data-category="{{ $shift->category }}"
                                    data-start="{{ $shift->start_time }}"
                                    data-end="{{ $shift->end_time }}"
                                    data-overnight="{{ $shift->is_overnight }}"
                                    data-rotation="{{ $shift->rotation_type }}"
                                    data-personnel="{{ $shift->required_personnel }}"
                                    {{ old('security_shift_id') == $shift->id ? 'selected' : '' }}>
                                {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}-{{ substr($shift->end_time, 0, 5) }})
                                @if($shift->is_overnight) 🌙 @endif
                                - Req: {{ $shift->required_personnel }}
                            </option>
                        @endforeach
                    </select>
                    @error('security_shift_id')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Shift Details Preview - Now has reserved space -->
                <div id="shiftDetailsPreview" class="md:col-span-2 hidden">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Shift Category</p>
                                <p id="previewCategory" class="font-medium" style="color: var(--text-primary);">-</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Shift Time</p>
                                <p id="previewTime" class="font-medium" style="color: var(--text-primary);">-</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Required Personnel</p>
                                <p id="previewPersonnel" class="font-medium" style="color: var(--text-primary);">-</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Rotation Type</p>
                                <p id="previewRotation" class="font-medium" style="color: var(--text-primary);">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Configuration Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-cogs mr-2" style="color: var(--primary);"></i> Group Configuration
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Group Type -->
                <div>
                    <label for="group_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Group Type *
                    </label>
                    <select id="group_type"
                            name="group_type"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Group Type</option>
                        <option value="day" {{ old('group_type') == 'day' ? 'selected' : '' }}>Day Shift Group</option>
                        <option value="night" {{ old('group_type') == 'night' ? 'selected' : '' }}>Night Shift Group</option>
                        <option value="evening" {{ old('group_type') == 'evening' ? 'selected' : '' }}>Evening Shift Group</option>
                        <option value="rotating" {{ old('group_type') == 'rotating' ? 'selected' : '' }}>Rotating Group</option>
                        <option value="standby" {{ old('group_type') == 'standby' ? 'selected' : '' }}>Standby / Reserve</option>
                    </select>
                    @error('group_type')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Group Status -->
                <div>
                    <label class="block mb-3 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-power-off mr-1" style="color: var(--primary);"></i> Group Status
                    </label>
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center">
                            <input type="radio"
                                   id="status_active"
                                   name="status"
                                   class="form-radio h-4 w-4 transition-all duration-200"
                                   style="color: var(--success);"
                                   value="active"
                                   {{ old('status', 'active') == 'active' ? 'checked' : '' }}>
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
                                   name="status"
                                   class="form-radio h-4 w-4 transition-all duration-200"
                                   style="color: var(--warning);"
                                   value="inactive"
                                   {{ old('status') == 'inactive' ? 'checked' : '' }}>
                            <label for="status_inactive" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <span class="flex items-center">
                                    <span class="status-indicator status-occupied w-2 h-2 mr-2"></span>
                                    Inactive
                                </span>
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio"
                                   id="status_draft"
                                   name="status"
                                   class="form-radio h-4 w-4 transition-all duration-200"
                                   style="color: var(--info);"
                                   value="draft"
                                   {{ old('status') == 'draft' ? 'checked' : '' }}>
                            <label for="status_draft" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <span class="flex items-center">
                                    <span class="status-indicator status-draft w-2 h-2 mr-2" style="background-color: var(--info);"></span>
                                    Draft
                                </span>
                            </label>
                        </div>
                    </div>
                    <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Draft groups won't be used in scheduling until activated
                    </p>
                </div>
                
                <!-- Max Members -->
                <div>
                    <label for="max_members" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-1" style="color: var(--primary);"></i> Maximum Members
                    </label>
                    <input type="number"
                           id="max_members"
                           name="max_members"
                           min="1"
                           max="100"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('max_members', 10) }}">
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Maximum number of members allowed (1-100)
                    </p>
                    @error('max_members')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Min Members -->
                <div>
                    <label for="min_members" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-user-minus mr-1" style="color: var(--primary);"></i> Minimum Members
                    </label>
                    <input type="number"
                           id="min_members"
                           name="min_members"
                           min="1"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('min_members', 2) }}">
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Minimum members required for rotation
                    </p>
                    @error('min_members')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Rotation Configuration Card (Shown only for rotating groups) - Now has reserved space -->
        <div id="rotationConfigCard" class="card p-6 hidden">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i> Rotation Configuration
            </h3>
            
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Rotation Pattern Type -->
                    <div>
                        <label for="rotation_pattern_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-project-diagram mr-1" style="color: var(--primary);"></i> Rotation Pattern *
                        </label>
                        <select id="rotation_pattern_type"
                                name="rotation_pattern[type]"
                                class="index-custom-dropdown w-full"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="sequential" {{ old('rotation_pattern.type') == 'sequential' ? 'selected' : '' }}>Sequential</option>
                            <option value="alternating" {{ old('rotation_pattern.type') == 'alternating' ? 'selected' : '' }}>Alternating</option>
                            <option value="preference_based" {{ old('rotation_pattern.type') == 'preference_based' ? 'selected' : '' }}>Preference Based</option>
                            <option value="staggered" {{ old('rotation_pattern.type') == 'staggered' ? 'selected' : '' }}>Staggered</option>
                            <option value="full_swap" {{ old('rotation_pattern.type') == 'full_swap' ? 'selected' : '' }}>Full Swap</option>
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> How members rotate through positions
                        </p>
                        @error('rotation_pattern.type')
                            <p class="mt-1 text-sm" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>
                    
                    <!-- Rotation Interval -->
                    <div>
                        <label for="rotation_pattern_interval_days" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-week mr-1" style="color: var(--primary);"></i> Rotation Interval (Days) *
                        </label>
                        <input type="number"
                               id="rotation_pattern_interval_days"
                               name="rotation_pattern[interval_days]"
                               min="1"
                               max="365"
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('rotation_pattern.interval_days', 7) }}">
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> How often rotation occurs (1-365 days)
                        </p>
                        @error('rotation_pattern.interval_days')
                            <p class="mt-1 text-sm" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>
                    
                    <!-- Start Date -->
                    <div>
                        <label for="rotation_pattern_start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Start Date
                        </label>
                        <input type="date"
                               id="rotation_pattern_start_date"
                               name="rotation_pattern[start_date]"
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('rotation_pattern.start_date', now()->format('Y-m-d')) }}">
                        @error('rotation_pattern.start_date')
                            <p class="mt-1 text-sm" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <!-- Rotation Sequence -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sort-amount-down mr-1" style="color: var(--primary);"></i> Rotation Sequence
                    </label>
                    <p class="text-xs mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Define the sequence pattern (A, B, C, D for different rotation groups)
                    </p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach(['A', 'B', 'C', 'D'] as $seq)
                            <div>
                                <input type="text"
                                       name="rotation_pattern[sequence][]"
                                       class="index-custom-input w-full text-center font-bold"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('rotation_pattern.sequence.' . $loop->index, $seq) }}"
                                       placeholder="Group {{ $seq }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Preference Weights Card -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-weight-hanging mr-2" style="color: var(--primary);"></i> Preference Weights
                </h3>
                <button type="button" id="resetWeightsBtn" class="text-sm px-3 py-1 rounded-lg" style="color: var(--primary); background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-undo-alt mr-1"></i> Reset to Default
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Seniority Weight -->
                <div>
                    <label for="preference_weights_seniority" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-star mr-1" style="color: var(--primary);"></i> Seniority Weight
                    </label>
                    <div class="flex items-center space-x-2">
                        <input type="range"
                               id="preference_weights_seniority"
                               name="preference_weights[seniority]"
                               min="0"
                               max="100"
                               class="w-full"
                               value="{{ old('preference_weights.seniority', 25) }}">
                        <span id="seniorityValue" class="text-sm font-medium w-12 text-center" style="color: var(--primary);">25%</span>
                    </div>
                    @error('preference_weights.seniority')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Performance Weight -->
                <div>
                    <label for="preference_weights_performance" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-1" style="color: var(--primary);"></i> Performance Weight
                    </label>
                    <div class="flex items-center space-x-2">
                        <input type="range"
                               id="preference_weights_performance"
                               name="preference_weights[performance]"
                               min="0"
                               max="100"
                               class="w-full"
                               value="{{ old('preference_weights.performance', 25) }}">
                        <span id="performanceValue" class="text-sm font-medium w-12 text-center" style="color: var(--primary);">25%</span>
                    </div>
                    @error('preference_weights.performance')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Availability Weight -->
                <div>
                    <label for="preference_weights_availability" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-check mr-1" style="color: var(--primary);"></i> Availability Weight
                    </label>
                    <div class="flex items-center space-x-2">
                        <input type="range"
                               id="preference_weights_availability"
                               name="preference_weights[availability]"
                               min="0"
                               max="100"
                               class="w-full"
                               value="{{ old('preference_weights.availability', 20) }}">
                        <span id="availabilityValue" class="text-sm font-medium w-12 text-center" style="color: var(--primary);">20%</span>
                    </div>
                    @error('preference_weights.availability')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Preferred Shift Weight -->
                <div>
                    <label for="preference_weights_preferred_shift" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Preferred Shift Weight
                    </label>
                    <div class="flex items-center space-x-2">
                        <input type="range"
                               id="preference_weights_preferred_shift"
                               name="preference_weights[preferred_shift]"
                               min="0"
                               max="100"
                               class="w-full"
                               value="{{ old('preference_weights.preferred_shift', 15) }}">
                        <span id="preferredShiftValue" class="text-sm font-medium w-12 text-center" style="color: var(--primary);">15%</span>
                    </div>
                    @error('preference_weights.preferred_shift')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Rotation Willingness Weight -->
                <div>
                    <label for="preference_weights_rotation_willingness" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sync-alt mr-1" style="color: var(--primary);"></i> Rotation Willingness Weight
                    </label>
                    <div class="flex items-center space-x-2">
                        <input type="range"
                               id="preference_weights_rotation_willingness"
                               name="preference_weights[rotation_willingness]"
                               min="0"
                               max="100"
                               class="w-full"
                               value="{{ old('preference_weights.rotation_willingness', 15) }}">
                        <span id="willingnessValue" class="text-sm font-medium w-12 text-center" style="color: var(--primary);">15%</span>
                    </div>
                    @error('preference_weights.rotation_willingness')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Total Weight Display -->
                <div class="lg:col-span-3">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-calculator mr-1"></i> Total Weight
                                </p>
                                <p id="totalWeight" class="text-2xl font-bold" style="color: var(--info);">100%</p>
                            </div>
                            <div id="weightWarning" class="hidden">
                                <span class="px-3 py-1.5 rounded-full text-xs font-medium badge-warning">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Total must be 100%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auto Rotation Settings Card -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Auto Rotation Settings
                </h3>
                <div class="flex items-center">
                    <input type="hidden" name="auto_rotate" value="0">
                    <input type="checkbox"
                           id="auto_rotate"
                           name="auto_rotate"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('auto_rotate', '0') == '1' ? 'checked' : '' }}>
                    <label for="auto_rotate" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                        Enable Auto Rotation
                    </label>
                </div>
            </div>
            
            <div id="autoRotateSettings" class="grid grid-cols-1 md:grid-cols-2 gap-6 {{ old('auto_rotate', '0') == '1' ? '' : 'hidden' }}">
                <!-- Auto Rotate Schedule -->
                <div>
                    <label for="auto_rotate_schedule" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Rotation Schedule *
                    </label>
                    <select id="auto_rotate_schedule"
                            name="auto_rotate_schedule"
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">Select Schedule</option>
                        <option value="daily" {{ old('auto_rotate_schedule') == 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ old('auto_rotate_schedule') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="biweekly" {{ old('auto_rotate_schedule') == 'biweekly' ? 'selected' : '' }}>Bi-Weekly</option>
                        <option value="monthly" {{ old('auto_rotate_schedule') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                    @error('auto_rotate_schedule')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
                
                <!-- Auto Rotate Time -->
                <div>
                    <label for="auto_rotate_time" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Rotation Time
                    </label>
                    <input type="time"
                           id="auto_rotate_time"
                           name="auto_rotate_time"
                           class="index-custom-input w-full"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('auto_rotate_time', '00:00') }}">
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Time of day to perform rotation
                    </p>
                    @error('auto_rotate_time')
                        <p class="mt-1 text-sm" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Advanced Settings Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-tools mr-2" style="color: var(--primary);"></i> Advanced Settings
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Require Handover -->
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color);">
                    <input type="hidden" name="settings[require_handover]" value="0">
                    <input type="checkbox"
                           id="settings_require_handover"
                           name="settings[require_handover]"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('settings.require_handover', '1') == '1' ? 'checked' : '' }}>
                    <label for="settings_require_handover" class="ml-3 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-exchange-alt mr-1" style="color: var(--primary);"></i> Require Handover
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Members must complete handover notes</p>
                    </label>
                </div>
                
                <!-- Notify on Rotate -->
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color);">
                    <input type="hidden" name="settings[notify_on_rotate]" value="0">
                    <input type="checkbox"
                           id="settings_notify_on_rotate"
                           name="settings[notify_on_rotate]"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('settings.notify_on_rotate', '1') == '1' ? 'checked' : '' }}>
                    <label for="settings_notify_on_rotate" class="ml-3 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-bell mr-1" style="color: var(--primary);"></i> Notify on Rotation
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Send notifications when rotation occurs</p>
                    </label>
                </div>
                
                <!-- Maintain Coverage -->
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color);">
                    <input type="hidden" name="settings[maintain_coverage]" value="0">
                    <input type="checkbox"
                           id="settings_maintain_coverage"
                           name="settings[maintain_coverage]"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('settings.maintain_coverage', '1') == '1' ? 'checked' : '' }}>
                    <label for="settings_maintain_coverage" class="ml-3 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-1" style="color: var(--primary);"></i> Maintain Coverage
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Ensure post coverage during rotations</p>
                    </label>
                </div>
                
                <!-- Allow Swaps -->
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color);">
                    <input type="hidden" name="settings[allow_swaps]" value="0">
                    <input type="checkbox"
                           id="settings_allow_swaps"
                           name="settings[allow_swaps]"
                           class="index-custom-checkbox h-5 w-5"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                           value="1"
                           {{ old('settings.allow_swaps', '1') == '1' ? 'checked' : '' }}>
                    <label for="settings_allow_swaps" class="ml-3 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-arrows-rotate mr-1" style="color: var(--primary);"></i> Allow Swaps
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Members can request shift swaps</p>
                    </label>
                </div>
            </div>
        </div>

        <!-- Initial Members Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i> Initial Members
            </h3>
            
            <div class="mb-4">
                <div class="flex justify-between items-center">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Select members to assign to this group
                    </p>
                    <span id="selectedCount" class="text-sm font-medium" style="color: var(--primary);">0 selected</span>
                </div>
                <div class="mt-2 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <div class="flex items-center">
                        <i class="fas fa-search mr-2" style="color: var(--text-secondary);"></i>
                        <input type="text"
                               id="memberSearch"
                               class="index-custom-input flex-1"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Search members...">
                    </div>
                </div>
            </div>
            
            <div class="max-h-96 overflow-y-auto border rounded-lg" style="border-color: var(--border-color);">
                <table class="min-w-full">
                    <thead class="sticky top-0" style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium" style="color: var(--text-secondary); width: 50px;">
                                <input type="checkbox"
                                       id="selectAll"
                                       class="index-custom-checkbox h-4 w-4"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium" style="color: var(--text-secondary);">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium" style="color: var(--text-secondary);">Badge #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium" style="color: var(--text-secondary);">Phone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium" style="color: var(--text-secondary);">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium" style="color: var(--text-secondary);">Role</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody">
                        @forelse($availablePersonnel as $user)
                            <tr class="border-t member-row" style="border-color: var(--border-color);">
                                <td class="px-4 py-3">
                                    <input type="checkbox"
                                           name="initial_members[]"
                                           value="{{ $user->id }}"
                                           class="member-checkbox index-custom-checkbox h-4 w-4"
                                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);"
                                           {{ is_array(old('initial_members')) && in_array($user->id, old('initial_members')) ? 'checked' : '' }}>
                                </td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-primary);">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                        {{ $user->name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-primary);">{{ $user->badge_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-primary);">{{ $user->phone }}</td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-primary);">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <select name="member_roles[{{ $user->id }}]" class="index-custom-dropdown text-sm" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                        <option value="member">Member</option>
                                        <option value="deputy">Deputy</option>
                                        <option value="leader">Leader</option>
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-users text-3xl mb-2 opacity-50"></i>
                                    <p>No available personnel found</p>
                                    <p class="text-xs mt-1">All active personnel are already assigned to groups</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Actions Card -->
        <div class="card p-6">
            <div class="flex justify-end items-center space-x-3">
                <a href="{{ route('admin.rotation-groups.index') }}"
                   class="btn-secondary px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit"
                        class="btn-primary px-6 py-3 rounded-lg font-medium text-white inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Create Group
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

{{-- ============================================================================
     NON-CRITICAL STYLES - Load after content (animations, hover effects, etc.)
     ============================================================================ --}}
@push('styles')
<style>
/* ========== INDEX-SPECIFIC FORM CONTROL STYLES ========== */
.index-custom-dropdown {
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
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-checkbox {
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

.status-draft {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

.status-draft::before {
    background-color: var(--info);
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

/* ========== TABLE STYLES ========== */
.member-row {
    transition: background-color 0.2s ease;
}

.member-row:hover {
    background-color: rgba(var(--primary-rgb), 0.03);
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

#rotationConfigCard {
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
    
    .max-h-96 {
        max-height: 400px;
    }
}

/* ========== RANGE INPUT STYLES ========== */
input[type=range] {
    height: 6px;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary) var(--value-percent), var(--border-color) var(--value-percent), var(--border-color) 100%);
}

input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid var(--card-bg);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

input[type=range]::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid var(--card-bg);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
</style>
@endpush

{{-- ============================================================================
     OPTIMIZED SCRIPTS - With flicker prevention
     ============================================================================ --}}
@push('scripts')
<script>
// Enable JS on page load to show JS-dependent elements
document.documentElement.classList.add('js-enabled');

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ========== GROUP TYPE TOGGLE ==========
    const groupTypeSelect = document.getElementById('group_type');
    const rotationConfigCard = document.getElementById('rotationConfigCard');
    const rotationConfigInputs = rotationConfigCard?.querySelectorAll('input, select');
    
    function toggleRotationConfig() {
        if (groupTypeSelect?.value === 'rotating') {
            rotationConfigCard?.classList.remove('hidden');
            rotationConfigInputs?.forEach(input => input.removeAttribute('disabled'));
        } else {
            rotationConfigCard?.classList.add('hidden');
            rotationConfigInputs?.forEach(input => input.setAttribute('disabled', 'disabled'));
        }
    }
    
    if (groupTypeSelect) {
        groupTypeSelect.addEventListener('change', toggleRotationConfig);
        // Use requestAnimationFrame for initial toggle to avoid layout thrashing
        requestAnimationFrame(toggleRotationConfig);
    }
    
    // ========== SHIFT DETAILS PREVIEW ==========
    const shiftSelect = document.getElementById('security_shift_id');
    const shiftDetailsPreview = document.getElementById('shiftDetailsPreview');
    const previewCategory = document.getElementById('previewCategory');
    const previewTime = document.getElementById('previewTime');
    const previewPersonnel = document.getElementById('previewPersonnel');
    const previewRotation = document.getElementById('previewRotation');
    
    function updateShiftPreview() {
        const selectedOption = shiftSelect?.selectedOptions[0];
        
        if (selectedOption && selectedOption.value) {
            const category = selectedOption.dataset.category;
            const start = selectedOption.dataset.start;
            const end = selectedOption.dataset.end;
            const overnight = selectedOption.dataset.overnight === '1';
            const personnel = selectedOption.dataset.personnel;
            const rotation = selectedOption.dataset.rotation;
            
            // Batch DOM updates
            requestAnimationFrame(() => {
                previewCategory.textContent = category ? category.charAt(0).toUpperCase() + category.slice(1) : '-';
                previewTime.textContent = start && end ? 
                    `${start.substring(0,5)} - ${end.substring(0,5)} ${overnight ? '🌙' : ''}` : '-';
                previewPersonnel.textContent = personnel || '-';
                previewRotation.textContent = rotation ? 
                    rotation.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') : '-';
                
                shiftDetailsPreview?.classList.remove('hidden');
            });
        } else {
            shiftDetailsPreview?.classList.add('hidden');
        }
    }
    
    if (shiftSelect) {
        shiftSelect.addEventListener('change', updateShiftPreview);
        requestAnimationFrame(updateShiftPreview);
    }
    
    // ========== AUTO ROTATION TOGGLE ==========
    const autoRotateCheckbox = document.getElementById('auto_rotate');
    const autoRotateSettings = document.getElementById('autoRotateSettings');
    const autoRotateInputs = autoRotateSettings?.querySelectorAll('input, select');
    
    function toggleAutoRotate() {
        requestAnimationFrame(() => {
            if (autoRotateCheckbox?.checked) {
                autoRotateSettings?.classList.remove('hidden');
                autoRotateInputs?.forEach(input => input.removeAttribute('disabled'));
            } else {
                autoRotateSettings?.classList.add('hidden');
                autoRotateInputs?.forEach(input => input.setAttribute('disabled', 'disabled'));
            }
        });
    }
    
    if (autoRotateCheckbox) {
        autoRotateCheckbox.addEventListener('change', toggleAutoRotate);
        requestAnimationFrame(toggleAutoRotate);
    }
    
    // ========== PREFERENCE WEIGHTS ==========
    const weightInputs = {
        seniority: document.getElementById('preference_weights_seniority'),
        performance: document.getElementById('preference_weights_performance'),
        availability: document.getElementById('preference_weights_availability'),
        preferred_shift: document.getElementById('preference_weights_preferred_shift'),
        rotation_willingness: document.getElementById('preference_weights_rotation_willingness')
    };
    
    const weightSpans = {
        seniority: document.getElementById('seniorityValue'),
        performance: document.getElementById('performanceValue'),
        availability: document.getElementById('availabilityValue'),
        preferred_shift: document.getElementById('preferredShiftValue'),
        rotation_willingness: document.getElementById('willingnessValue')
    };
    
    const totalWeightSpan = document.getElementById('totalWeight');
    const weightWarning = document.getElementById('weightWarning');
    
    let weightUpdateTimer;
    
    function updateWeightDisplay(input, span) {
        span.textContent = input.value + '%';
        
        // Update range background gradient
        const percent = (input.value - input.min) / (input.max - input.min) * 100;
        input.style.setProperty('--value-percent', percent + '%');
    }
    
    function calculateTotalWeight() {
        let total = 0;
        for (let key in weightInputs) {
            total += parseInt(weightInputs[key]?.value || 0);
        }
        
        totalWeightSpan.textContent = total + '%';
        
        if (total !== 100) {
            weightWarning?.classList.remove('hidden');
            totalWeightSpan.style.color = 'var(--warning)';
        } else {
            weightWarning?.classList.add('hidden');
            totalWeightSpan.style.color = 'var(--info)';
        }
        
        return total;
    }
    
    // Debounced weight calculation
    function debouncedCalculate() {
        clearTimeout(weightUpdateTimer);
        weightUpdateTimer = setTimeout(() => {
            requestAnimationFrame(calculateTotalWeight);
        }, 16);
    }
    
    // Initialize range inputs
    for (let key in weightInputs) {
        if (weightInputs[key]) {
            updateWeightDisplay(weightInputs[key], weightSpans[key]);
            
            weightInputs[key].addEventListener('input', function() {
                updateWeightDisplay(this, weightSpans[key]);
                debouncedCalculate();
            });
        }
    }
    
    requestAnimationFrame(calculateTotalWeight);
    
    // Reset weights to default
    document.getElementById('resetWeightsBtn')?.addEventListener('click', function() {
        const defaults = {
            seniority: 25,
            performance: 25,
            availability: 20,
            preferred_shift: 15,
            rotation_willingness: 15
        };
        
        for (let key in defaults) {
            if (weightInputs[key]) {
                weightInputs[key].value = defaults[key];
                updateWeightDisplay(weightInputs[key], weightSpans[key]);
            }
        }
        
        requestAnimationFrame(calculateTotalWeight);
    });
    
    // ========== MEMBER SELECTION ==========
    const selectAllCheckbox = document.getElementById('selectAll');
    const memberCheckboxes = document.querySelectorAll('.member-checkbox');
    const selectedCountSpan = document.getElementById('selectedCount');
    const memberSearch = document.getElementById('memberSearch');
    const memberRows = document.querySelectorAll('.member-row');
    
    let searchTimer;
    
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.member-checkbox:checked').length;
        selectedCountSpan.textContent = checked + ' selected';
    }
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            memberCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                
                // Show/hide role selects based on selection
                const row = checkbox.closest('tr');
                const roleSelect = row?.querySelector('select[name^="member_roles"]');
                if (roleSelect) {
                    roleSelect.disabled = !this.checked;
                }
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox changes
    memberCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(memberCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(memberCheckboxes).some(cb => cb.checked);
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = !allChecked && someChecked;
            }
            
            // Enable/disable role select
            const row = this.closest('tr');
            const roleSelect = row?.querySelector('select[name^="member_roles"]');
            if (roleSelect) {
                roleSelect.disabled = !this.checked;
            }
            
            updateSelectedCount();
        });
    });
    
    // Debounced member search
    if (memberSearch) {
        memberSearch.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const searchTerm = this.value.toLowerCase();
            
            searchTimer = setTimeout(() => {
                requestAnimationFrame(() => {
                    memberRows.forEach(row => {
                        const nameCell = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                        const badgeCell = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                        const phoneCell = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                        const emailCell = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
                        
                        const matches = nameCell.includes(searchTerm) || 
                                       badgeCell.includes(searchTerm) || 
                                       phoneCell.includes(searchTerm) || 
                                       emailCell.includes(searchTerm);
                        
                        row.style.display = matches ? '' : 'none';
                    });
                });
            }, 150);
        });
    }
    
    // Initial count
    updateSelectedCount();
    
    // ========== FORM VALIDATION ==========
    document.getElementById('rotationGroupForm')?.addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessage = '';
        
        // Validate min/max members
        const maxMembers = parseInt(document.getElementById('max_members')?.value);
        const minMembers = parseInt(document.getElementById('min_members')?.value);
        
        if (minMembers && maxMembers && minMembers > maxMembers) {
            errorMessage += '• Minimum members cannot exceed maximum members.\n';
            isValid = false;
        }
        
        // Validate rotation configuration for rotating groups
        if (groupTypeSelect?.value === 'rotating') {
            const intervalDays = document.getElementById('rotation_pattern_interval_days')?.value;
            if (!intervalDays || parseInt(intervalDays) < 1) {
                errorMessage += '• Rotation interval must be at least 1 day.\n';
                isValid = false;
            }
        }
        
        // Validate auto rotation settings
        if (autoRotateCheckbox?.checked) {
            const schedule = document.getElementById('auto_rotate_schedule')?.value;
            if (!schedule) {
                errorMessage += '• Please select an auto-rotation schedule.\n';
                isValid = false;
            }
        }
        
        // Validate preference weights total
        const totalWeight = calculateTotalWeight();
        if (totalWeight !== 100) {
            errorMessage += '• Preference weights must total exactly 100%.\n';
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errorMessage);
        }
    });
});
</script>
@endpush