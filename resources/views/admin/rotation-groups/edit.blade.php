@extends('layouts.app')

@section('title', 'Edit Rotation Group: ' . $group->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-edit mr-2" style="color: var(--primary);"></i>
                        Edit Rotation Group: {{ $group->name }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Update group configuration, rotation settings, and member assignments</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.rotation-groups.show', $group->id) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-info">
                    <i class="fas fa-eye mr-2"></i> View Group
                </a>
                <a href="{{ route('admin.rotation-groups.index') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <form action="{{ route('admin.rotation-groups.update', $group->id) }}" 
          method="POST" 
          id="editGroupForm"
          enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Basic Info & Configuration -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information Card -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                            Basic Information
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <!-- Name and Code Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-tag mr-1" style="color: var(--primary);"></i>
                                    Group Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="form-control w-full @error('name') is-invalid @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('name', $group->name) }}"
                                       required
                                       placeholder="e.g., Alpha Team - Morning Shift">
                                @error('name')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="code" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-barcode mr-1" style="color: var(--primary);"></i>
                                    Group Code
                                </label>
                                <input type="text" 
                                       id="code" 
                                       name="code" 
                                       class="form-control w-full @error('code') is-invalid @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('code', $group->code) }}"
                                       placeholder="e.g., AT-01">
                                @error('code')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Auto-generated if left empty</p>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label for="description" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-align-left mr-1" style="color: var(--primary);"></i>
                                Description
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="3"
                                      class="form-control w-full @error('description') is-invalid @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Describe the purpose of this rotation group...">{{ old('description', $group->description) }}</textarea>
                            @error('description')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Post and Shift Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="security_post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-building mr-1" style="color: var(--primary);"></i>
                                    Security Post <span class="text-danger">*</span>
                                </label>
                                <select id="security_post_id" 
                                        name="security_post_id" 
                                        class="form-control w-full @error('security_post_id') is-invalid @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        required>
                                    <option value="">Select a post</option>
                                    @foreach($posts as $post)
                                        <option value="{{ $post->id }}" 
                                            {{ old('security_post_id', $group->security_post_id) == $post->id ? 'selected' : '' }}
                                            data-max-personnel="{{ $post->max_personnel }}">
                                            {{ $post->name }} ({{ $post->code }}) - {{ $post->is_active ? 'Active' : 'Inactive' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('security_post_id')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="security_shift_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-clock mr-1" style="color: var(--primary);"></i>
                                    Security Shift <span class="text-danger">*</span>
                                </label>
                                <select id="security_shift_id" 
                                        name="security_shift_id" 
                                        class="form-control w-full @error('security_shift_id') is-invalid @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        required>
                                    <option value="">Select a shift</option>
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}" 
                                            {{ old('security_shift_id', $group->security_shift_id) == $shift->id ? 'selected' : '' }}
                                            data-rotation-type="{{ $shift->rotation_type }}"
                                            data-is-overnight="{{ $shift->is_overnight }}">
                                            {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                            @if($shift->is_overnight) 🌙 @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('security_shift_id')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Group Type and Status Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="group_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-layer-group mr-1" style="color: var(--primary);"></i>
                                    Group Type <span class="text-danger">*</span>
                                </label>
                                <select id="group_type" 
                                        name="group_type" 
                                        class="form-control w-full @error('group_type') is-invalid @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        required>
                                    <option value="day" {{ old('group_type', $group->group_type) == 'day' ? 'selected' : '' }}>Day Shift</option>
                                    <option value="night" {{ old('group_type', $group->group_type) == 'night' ? 'selected' : '' }}>Night Shift</option>
                                    <option value="evening" {{ old('group_type', $group->group_type) == 'evening' ? 'selected' : '' }}>Evening Shift</option>
                                    <option value="rotating" {{ old('group_type', $group->group_type) == 'rotating' ? 'selected' : '' }}>Rotating</option>
                                    <option value="standby" {{ old('group_type', $group->group_type) == 'standby' ? 'selected' : '' }}>Standby</option>
                                </select>
                                @error('group_type')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="status" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-power-off mr-1" style="color: var(--primary);"></i>
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select id="status" 
                                        name="status" 
                                        class="form-control w-full @error('status') is-invalid @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        required>
                                    <option value="active" {{ old('status', $group->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $group->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="draft" {{ old('status', $group->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                                </select>
                                @error('status')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Member Limits Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="min_members" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-arrow-down mr-1" style="color: var(--primary);"></i>
                                    Minimum Members
                                </label>
                                <input type="number" 
                                       id="min_members" 
                                       name="min_members" 
                                       class="form-control w-full @error('min_members') is-invalid @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('min_members', $group->min_members) }}"
                                       min="1"
                                       placeholder="Minimum required">
                                @error('min_members')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="max_members" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-arrow-up mr-1" style="color: var(--primary);"></i>
                                    Maximum Members
                                </label>
                                <input type="number" 
                                       id="max_members" 
                                       name="max_members" 
                                       class="form-control w-full @error('max_members') is-invalid @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('max_members', $group->max_members) }}"
                                       min="1"
                                       placeholder="Maximum capacity">
                                @error('max_members')
                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rotation Configuration Card (for rotating groups) -->
                <div id="rotationConfigCard" class="card" style="{{ $group->group_type !== 'rotating' ? 'display: none;' : '' }}">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i>
                            Rotation Configuration
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        @php
                            $rotationConfig = $group->rotation_config ?? [];
                            $rotationPattern = old('rotation_pattern', $rotationConfig);
                        @endphp
                        
                        <!-- Rotation Pattern Type -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="rotation_pattern_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-project-diagram mr-1" style="color: var(--primary);"></i>
                                    Rotation Pattern
                                </label>
                                <select id="rotation_pattern_type" 
                                        name="rotation_pattern[type]" 
                                        class="form-control w-full"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="sequential" {{ ($rotationPattern['type'] ?? 'sequential') == 'sequential' ? 'selected' : '' }}>Sequential</option>
                                    <option value="alternating" {{ ($rotationPattern['type'] ?? '') == 'alternating' ? 'selected' : '' }}>Alternating</option>
                                    <option value="preference_based" {{ ($rotationPattern['type'] ?? '') == 'preference_based' ? 'selected' : '' }}>Preference Based</option>
                                    <option value="staggered" {{ ($rotationPattern['type'] ?? '') == 'staggered' ? 'selected' : '' }}>Staggered</option>
                                    <option value="full_swap" {{ ($rotationPattern['type'] ?? '') == 'full_swap' ? 'selected' : '' }}>Full Swap</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="rotation_pattern_interval_days" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i>
                                    Interval (Days)
                                </label>
                                <input type="number" 
                                       id="rotation_pattern_interval_days" 
                                       name="rotation_pattern[interval_days]" 
                                       class="form-control w-full"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ $rotationPattern['interval_days'] ?? 7 }}"
                                       min="1"
                                       max="365">
                            </div>
                        </div>
                        
                        <!-- Start Date -->
                        <div>
                            <label for="rotation_pattern_start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-plus mr-1" style="color: var(--primary);"></i>
                                Start Date
                            </label>
                            <input type="date" 
                                   id="rotation_pattern_start_date" 
                                   name="rotation_pattern[start_date]" 
                                   class="form-control w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ $rotationPattern['start_date'] ?? now()->format('Y-m-d') }}">
                        </div>
                        
                        <!-- Sequence Pattern -->
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-list-ol mr-1" style="color: var(--primary);"></i>
                                Rotation Sequence
                            </label>
                            <div class="grid grid-cols-4 gap-2">
                                @php
                                    $sequence = $rotationPattern['sequence'] ?? ['A', 'B', 'C', 'D'];
                                @endphp
                                @foreach(range(0, 3) as $index)
                                    <input type="text" 
                                           name="rotation_pattern[sequence][]" 
                                           class="form-control text-center"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           value="{{ $sequence[$index] ?? chr(65 + $index) }}"
                                           maxlength="1"
                                           placeholder="{{ chr(65 + $index) }}">
                                @endforeach
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Enter single letters for sequence pattern (e.g., A, B, C, D)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Auto Rotation Settings Card -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                            Auto-Rotation Settings
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <!-- Auto Rotate Toggle -->
                        <div class="flex items-center">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       name="auto_rotate" 
                                       class="sr-only peer"
                                       value="1"
                                       {{ old('auto_rotate', $group->auto_rotate) ? 'checked' : '' }}
                                       onchange="toggleAutoRotateFields(this)">
                                <div class="w-11 h-6 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"
                                     style="background-color: {{ old('auto_rotate', $group->auto_rotate) ? 'var(--primary)' : 'var(--border-color)' }};"></div>
                                <span class="ml-3 text-sm font-medium" style="color: var(--text-primary);">Enable Auto Rotation</span>
                            </label>
                        </div>
                        
                        <div id="autoRotateFields" style="{{ old('auto_rotate', $group->auto_rotate) ? '' : 'display: none;' }}">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="auto_rotate_schedule" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i>
                                        Rotation Schedule
                                    </label>
                                    <select id="auto_rotate_schedule" 
                                            name="auto_rotate_schedule" 
                                            class="form-control w-full"
                                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                        <option value="daily" {{ old('auto_rotate_schedule', $group->auto_rotate_schedule) == 'daily' ? 'selected' : '' }}>Daily</option>
                                        <option value="weekly" {{ old('auto_rotate_schedule', $group->auto_rotate_schedule) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                        <option value="biweekly" {{ old('auto_rotate_schedule', $group->auto_rotate_schedule) == 'biweekly' ? 'selected' : '' }}>Bi-Weekly</option>
                                        <option value="monthly" {{ old('auto_rotate_schedule', $group->auto_rotate_schedule) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="auto_rotate_time" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-clock mr-1" style="color: var(--primary);"></i>
                                        Rotation Time
                                    </label>
                                    <input type="time" 
                                           id="auto_rotate_time" 
                                           name="auto_rotate_time" 
                                           class="form-control w-full"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           value="{{ old('auto_rotate_time', substr($group->auto_rotate_time ?? '00:00', 0, 5)) }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Preferences & Settings -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Current Members Card -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                            Current Members ({{ $group->members->count() }})
                        </h3>
                    </div>
                    
                    <div class="p-4 max-h-60 overflow-y-auto">
                        @if($group->members->count() > 0)
                            <div class="space-y-2">
                                @foreach($group->members->sortByDesc('preference_score')->take(5) as $member)
                                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                        <div class="flex items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center mr-2"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                <i class="fas fa-user text-xs"></i>
                                            </div>
                                            <div>
                                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $member->user->name }}</span>
                                                <span class="text-xs ml-1 badge-{{ $member->role == 'leader' ? 'primary' : ($member->role == 'deputy' ? 'info' : 'secondary') }} px-1.5 py-0.5 rounded-full">
                                                    {{ ucfirst($member->role) }}
                                                </span>
                                            </div>
                                        </div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Score: {{ $member->preference_score }}</span>
                                    </div>
                                @endforeach
                                
                                @if($group->members->count() > 5)
                                    <div class="text-center mt-2">
                                        <a href="{{ route('admin.rotation-groups.show', $group->id) }}" class="text-xs" style="color: var(--info);">
                                            +{{ $group->members->count() - 5 }} more members
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="fas fa-users text-2xl mb-2" style="color: var(--text-secondary);"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">No members assigned yet</p>
                                <a href="{{ route('admin.rotation-groups.show', $group->id) }}" class="text-xs mt-2 inline-block" style="color: var(--info);">
                                    Assign members from group view
                                </a>
                            </div>
                        @endif
                    </div>
                    
                    <div class="p-3 border-t" style="border-color: var(--border-color); background-color: rgba(var(--info-rgb), 0.03);">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Current Count:</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $group->members->count() }}</span>
                        </div>
                        @if($group->max_members)
                            <div class="flex justify-between text-sm mt-1">
                                <span style="color: var(--text-secondary);">Available Slots:</span>
                                <span class="font-medium" style="color: {{ ($group->members->count() < $group->max_members) ? 'var(--success)' : 'var(--danger)' }};">
                                    {{ max(0, $group->max_members - $group->members->count()) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Preference Weights Card -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-weight-hanging mr-2" style="color: var(--primary);"></i>
                            Preference Weights
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        @php
                            $weights = old('preference_weights', $group->preference_weights ?? [
                                'seniority' => 25,
                                'performance' => 25,
                                'availability' => 20,
                                'preferred_shift' => 15,
                                'rotation_willingness' => 15
                            ]);
                        @endphp
                        
                        <div>
                            <label for="preference_weights_seniority" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Seniority Weight
                            </label>
                            <input type="number" 
                                   id="preference_weights_seniority" 
                                   name="preference_weights[seniority]" 
                                   class="form-control w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ $weights['seniority'] ?? 25 }}"
                                   min="0"
                                   max="100"
                                   step="1">
                        </div>
                        
                        <div>
                            <label for="preference_weights_performance" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Performance Weight
                            </label>
                            <input type="number" 
                                   id="preference_weights_performance" 
                                   name="preference_weights[performance]" 
                                   class="form-control w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ $weights['performance'] ?? 25 }}"
                                   min="0"
                                   max="100"
                                   step="1">
                        </div>
                        
                        <div>
                            <label for="preference_weights_availability" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Availability Weight
                            </label>
                            <input type="number" 
                                   id="preference_weights_availability" 
                                   name="preference_weights[availability]" 
                                   class="form-control w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ $weights['availability'] ?? 20 }}"
                                   min="0"
                                   max="100"
                                   step="1">
                        </div>
                        
                        <div>
                            <label for="preference_weights_preferred_shift" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Preferred Shift Weight
                            </label>
                            <input type="number" 
                                   id="preference_weights_preferred_shift" 
                                   name="preference_weights[preferred_shift]" 
                                   class="form-control w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ $weights['preferred_shift'] ?? 15 }}"
                                   min="0"
                                   max="100"
                                   step="1">
                        </div>
                        
                        <div>
                            <label for="preference_weights_rotation_willingness" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Rotation Willingness Weight
                            </label>
                            <input type="number" 
                                   id="preference_weights_rotation_willingness" 
                                   name="preference_weights[rotation_willingness]" 
                                   class="form-control w-full"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ $weights['rotation_willingness'] ?? 15 }}"
                                   min="0"
                                   max="100"
                                   step="1">
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-primary);">Total Weight:</span>
                                <span id="totalWeight" class="font-bold" style="color: var(--info);">0</span>
                            </div>
                            <p id="weightWarning" class="text-xs mt-1 hidden" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Total should be 100 for optimal scoring
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Group Settings Card -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-sliders-h mr-2" style="color: var(--primary);"></i>
                            Group Settings
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        @php
                            $settings = old('settings', $group->settings ?? []);
                        @endphp
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="settings[require_handover]" 
                                   class="form-checkbox mr-3"
                                   value="1"
                                   {{ ($settings['require_handover'] ?? true) ? 'checked' : '' }}>
                            <span style="color: var(--text-primary);">Require Handover</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="settings[notify_on_rotate]" 
                                   class="form-checkbox mr-3"
                                   value="1"
                                   {{ ($settings['notify_on_rotate'] ?? true) ? 'checked' : '' }}>
                            <span style="color: var(--text-primary);">Notify on Rotation</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="settings[maintain_coverage]" 
                                   class="form-checkbox mr-3"
                                   value="1"
                                   {{ ($settings['maintain_coverage'] ?? true) ? 'checked' : '' }}>
                            <span style="color: var(--text-primary);">Maintain Coverage</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="settings[allow_swaps]" 
                                   class="form-checkbox mr-3"
                                   value="1"
                                   {{ ($settings['allow_swaps'] ?? true) ? 'checked' : '' }}>
                            <span style="color: var(--text-primary);">Allow Swaps</span>
                        </label>
                    </div>
                </div>
                
                <!-- Rotation Info Card -->
                @if($group->group_type === 'rotating' && $group->rotation_config)
                    <div class="card">
                        <div class="p-4 border-b" style="border-color: var(--border-color);">
                            <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                                Rotation Statistics
                            </h3>
                        </div>
                        
                        <div class="p-4 space-y-3">
                            @php
                                $stats = $group->rotation_config['rotation_stats'] ?? [];
                                $lastRotation = $group->last_rotated_at;
                                $nextRotation = $group->rotation_config['next_rotation_date'] ?? null;
                            @endphp
                            
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">Total Rotations:</span>
                                <span class="font-medium" style="color: var(--text-primary);">{{ $stats['total_rotations'] ?? 0 }}</span>
                            </div>
                            
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">Successful:</span>
                                <span class="font-medium" style="color: var(--success);">{{ $stats['successful_rotations'] ?? 0 }}</span>
                            </div>
                            
                            @if($lastRotation)
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--text-secondary);">Last Rotation:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $lastRotation->format('M j, Y') }}</span>
                                </div>
                            @endif
                            
                            @if($nextRotation)
                                @php
                                    $nextDate = \Carbon\Carbon::parse($nextRotation);
                                    $daysUntil = now()->diffInDays($nextDate, false);
                                @endphp
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--text-secondary);">Next Rotation:</span>
                                    <span class="font-medium" style="color: {{ $daysUntil <= 2 ? 'var(--warning)' : 'var(--text-primary)' }};">
                                        {{ $nextDate->format('M j, Y') }}
                                        @if($daysUntil <= 2)
                                            <i class="fas fa-exclamation-circle ml-1" style="color: var(--warning);"></i>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex justify-end space-x-3 mt-6">
            <a href="{{ route('admin.rotation-groups.show', $group->id) }}" 
               class="btn-secondary px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center">
                <i class="fas fa-times mr-2"></i> Cancel
            </a>
            <button type="submit" 
                    class="btn-primary px-6 py-3 rounded-lg text-sm font-medium text-white inline-flex items-center"
                    id="submitBtn">
                <i class="fas fa-save mr-2"></i> Update Group
            </button>
        </div>
    </form>
</div>

<style>
/* Form control styles */
.form-control {
    padding: 0.625rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-control.is-invalid {
    border-color: var(--danger);
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1);
}

/* Checkbox styles */
.form-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    cursor: pointer;
}

.form-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Toggle switch styles */
.peer:checked ~ div {
    background-color: var(--primary);
}

/* Card styles */
.card {
    transition: box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border: 1px solid rgba(var(--primary-rgb), 0.3);
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
}
</style>

<script>
// Toggle rotation config card based on group type
document.getElementById('group_type').addEventListener('change', function() {
    const rotationCard = document.getElementById('rotationConfigCard');
    if (this.value === 'rotating') {
        rotationCard.style.display = 'block';
    } else {
        rotationCard.style.display = 'none';
    }
});

// Toggle auto-rotate fields
function toggleAutoRotateFields(checkbox) {
    const autoRotateFields = document.getElementById('autoRotateFields');
    autoRotateFields.style.display = checkbox.checked ? 'block' : 'none';
}

// Calculate total weight and show warning
function calculateTotalWeight() {
    const seniority = parseInt(document.getElementById('preference_weights_seniority').value) || 0;
    const performance = parseInt(document.getElementById('preference_weights_performance').value) || 0;
    const availability = parseInt(document.getElementById('preference_weights_availability').value) || 0;
    const preferredShift = parseInt(document.getElementById('preference_weights_preferred_shift').value) || 0;
    const willingness = parseInt(document.getElementById('preference_weights_rotation_willingness').value) || 0;
    
    const total = seniority + performance + availability + preferredShift + willingness;
    document.getElementById('totalWeight').textContent = total;
    
    const warning = document.getElementById('weightWarning');
    if (total !== 100) {
        warning.classList.remove('hidden');
    } else {
        warning.classList.add('hidden');
    }
}

// Add event listeners to weight inputs
document.querySelectorAll('[id^="preference_weights_"]').forEach(input => {
    input.addEventListener('input', calculateTotalWeight);
});

// Calculate initial total
document.addEventListener('DOMContentLoaded', function() {
    calculateTotalWeight();
    
    // Update max members based on post selection
    document.getElementById('security_post_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const maxPersonnel = selected.dataset.maxPersonnel;
        const maxMembersInput = document.getElementById('max_members');
        
        if (maxPersonnel && maxMembersInput && !maxMembersInput.value) {
            maxMembersInput.value = maxPersonnel;
        }
    });
    
    // Form validation
    document.getElementById('editGroupForm').addEventListener('submit', function(e) {
        const minMembers = parseInt(document.getElementById('min_members').value) || 0;
        const maxMembers = parseInt(document.getElementById('max_members').value) || 0;
        
        if (maxMembers > 0 && minMembers > maxMembers) {
            e.preventDefault();
            alert('Minimum members cannot be greater than maximum members');
            return;
        }
        
        const totalWeight = parseInt(document.getElementById('totalWeight').textContent);
        if (totalWeight !== 100 && totalWeight > 0) {
            if (!confirm('Preference weights total is ' + totalWeight + '. Recommended total is 100. Continue anyway?')) {
                e.preventDefault();
            }
        }
    });
});

// Confirm before leaving with unsaved changes
let formChanged = false;
document.getElementById('editGroupForm').addEventListener('input', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Handle button loading state
document.getElementById('editGroupForm').addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    submitBtn.disabled = true;
});
</script>
@endsection