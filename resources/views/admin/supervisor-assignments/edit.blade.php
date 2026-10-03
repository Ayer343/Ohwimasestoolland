@extends('layouts.app')

@section('title', 'Edit Supervisor Assignment')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--primary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-edit mr-2" style="color: var(--primary);"></i>
                        Edit Supervisor Assignment
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Modify supervisor assignment details for</span>
                        <span class="mx-2 font-semibold" style="color: var(--primary);">
                            {{ optional($assignment->user)->name ?? 'Deleted User' }}
                        </span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>Created: {{ $assignment->created_at->format('M d, Y') }}</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-user-shield mr-1"></i> Admin
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.supervisor-assignments.show', $assignment) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Details
                </a>
            </div>
        </div>
    </div>

    <!-- Status Banner for Inactive/Expired -->
    @if(!$assignment->is_active)
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-lg mr-3" style="color: var(--danger);"></i>
                <div>
                    <span class="font-medium" style="color: var(--danger);">This assignment is currently inactive.</span>
                    <span class="text-sm ml-2" style="color: var(--text-secondary);">You can reactivate it by checking the "Active" status below.</span>
                </div>
            </div>
        </div>
    @endif

    @if($assignment->is_expired)
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-clock text-lg mr-3" style="color: var(--warning);"></i>
                <div>
                    <span class="font-medium" style="color: var(--warning);">This assignment has expired.</span>
                    <span class="text-sm ml-2" style="color: var(--text-secondary);">Update the end date to extend it.</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Alert -->
    @if($errors->any())
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--danger);">Validation Error!</h3>
                    <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.supervisor-assignments.update', $assignment) }}" method="POST" id="assignmentForm">
        @csrf
        @method('PUT')
        
        <!-- Basic Information Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Supervisor Selection -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                        Supervisor Information
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="user_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-user-tie mr-1" style="color: var(--primary);"></i>
                            Select Supervisor <span class="text-danger" style="color: var(--danger);">*</span>
                        </label>
                        <select name="user_id" id="user_id" 
                                class="index-custom-dropdown w-full @error('user_id') is-invalid @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">-- Select Supervisor --</option>
                            @foreach($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}" 
                                        data-eligible="{{ $supervisor->can_be_supervisor ? 'true' : 'false' }}"
                                        data-name="{{ $supervisor->name }}"
                                        data-email="{{ $supervisor->email }}"
                                        data-status="{{ $supervisor->status }}"
                                        {{ old('user_id', $assignment->user_id) == $supervisor->id ? 'selected' : '' }}>
                                    {{ $supervisor->name }} 
                                    @if($supervisor->can_be_supervisor)
                                        <span class="text-xs text-green-500">(Eligible)</span>
                                    @else
                                        <span class="text-xs text-yellow-500">(Not Eligible)</span>
                                    @endif
                                    @if($supervisor->status != 'active')
                                        - [{{ ucfirst($supervisor->status) }}]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        
                        <!-- Supervisor Preview -->
                        <div id="supervisorPreview" class="mt-3 p-3 rounded-lg {{ $assignment->user_id ? '' : 'hidden' }}" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">Selected Supervisor:</span>
                                <div class="flex space-x-2">
                                    <span class="px-2 py-1 text-xs rounded-full badge-info" id="previewName">
                                        {{ optional($assignment->user)->name ?? 'Deleted User' }}
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full badge-success" id="previewEligibility">
                                        {{ optional($assignment->user)->can_be_supervisor ? '✅ Eligible' : '❌ Not Eligible' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Only users with <strong>can_be_supervisor = true</strong> can be assigned as supervisors.
                            Manage eligibility in <a href="{{ route('admin.users.index') }}" class="text-primary hover:underline">User Management</a>.
                        </div>
                    </div>

                    <!-- Security Post Selection -->
                    <div>
                        <label for="security_post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-building mr-1" style="color: var(--primary);"></i>
                            Security Post
                        </label>
                        <select name="security_post_id" id="security_post_id" 
                                class="index-custom-dropdown w-full @error('security_post_id') is-invalid @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">-- Role Only (No Post Assignment) --</option>
                            @foreach($posts as $post)
                                <option value="{{ $post->id }}" 
                                        data-max="{{ $post->max_personnel }}"
                                        data-name="{{ $post->name }}"
                                        data-code="{{ $post->code }}"
                                        data-active="{{ $post->is_active ? 'yes' : 'no' }}"
                                        {{ old('security_post_id', $assignment->security_post_id) == $post->id ? 'selected' : '' }}>
                                    {{ $post->name }} ({{ $post->code }})
                                    @if(isset($post->max_personnel))
                                        <span class="text-xs text-gray-500">- Max: {{ $post->max_personnel }}</span>
                                    @endif
                                    @if(!$post->is_active)
                                        - [Inactive]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select <strong>"Role Only"</strong> to assign the supervisor role without assigning to a specific post.
                        </p>
                        
                        <!-- Post Preview -->
                        <div id="postPreview" class="mt-3 p-3 rounded-lg {{ $assignment->security_post_id ? '' : 'hidden' }}" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); border-left: 3px solid var(--warning);">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">Post Details:</span>
                                <span class="px-2 py-1 text-xs rounded-full badge-warning" id="postCapacity">
                                    Max Personnel: {{ optional($assignment->post)->max_personnel ?? 'N/A' }}
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full badge-secondary" id="postCode">
                                    {{ optional($assignment->post)->code ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Assignment Status -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex items-start">
                                <i class="fas fa-power-off mr-3 mt-0.5" style="color: var(--{{ $assignment->is_active ? 'success' : 'danger' }});"></i>
                                <div>
                                    <label for="is_active" class="text-sm font-medium cursor-pointer" style="color: var(--text-primary);">
                                        Assignment Active
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Toggle to activate/deactivate this assignment</p>
                                </div>
                            </div>
                            <div class="toggle-modern">
                                <input type="checkbox" name="is_active" id="is_active" value="1" 
                                       {{ old('is_active', $assignment->is_active) ? 'checked' : '' }} class="sr-only">
                                <label for="is_active" class="toggle-slider"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Duration -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                        Assignment Duration
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-play-circle mr-1" style="color: var(--success);"></i>
                                Start Date <span class="text-danger" style="color: var(--danger);">*</span>
                            </label>
                            <input type="date" name="start_date" id="start_date" 
                                   class="index-custom-input w-full @error('start_date') is-invalid @enderror" 
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ old('start_date', $assignment->start_date->format('Y-m-d')) }}" 
                                   required>
                        </div>
                        <div>
                            <label for="end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-stop-circle mr-1" style="color: var(--warning);"></i>
                                End Date
                            </label>
                            <input type="date" name="end_date" id="end_date" 
                                   class="index-custom-input w-full @error('end_date') is-invalid @enderror" 
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ old('end_date', $assignment->end_date?->format('Y-m-d')) }}"
                                   min="{{ old('start_date', $assignment->start_date->format('Y-m-d')) }}">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">Leave empty for indefinite</p>
                        </div>
                    </div>

                    <!-- Duration Display -->
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-primary);" id="durationDisplay">
                                @php
                                    $start = $assignment->start_date;
                                    $end = $assignment->end_date;
                                    if ($start && $end) {
                                        $days = $start->diffInDays($end);
                                        echo "Duration: {$days} day(s) ({$start->format('Y-m-d')} to {$end->format('Y-m-d')})";
                                    } else {
                                        echo "Duration: Indefinite (no end date)";
                                    }
                                @endphp
                            </span>
                        </div>
                    </div>

                    <!-- Time Elapsed -->
                    @if($assignment->start_date->isPast())
                        <div class="mt-2 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <i class="fas fa-hourglass-half mr-2" style="color: var(--success);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    Active for: {{ $assignment->start_date->diffForHumans(now(), true) }}
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Quick Duration Presets -->
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="text-xs" style="color: var(--text-secondary);">Quick set:</span>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="7" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            1 Week
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="30"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            1 Month
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="90"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            3 Months
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="365"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            1 Year
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="0"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            Indefinite
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supervisor Type & Scope Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Type & Scope -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-2" style="color: var(--primary);"></i>
                        Supervisor Type & Scope
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Supervisor Type -->
                    <div>
                        <label for="supervisor_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-user-tag mr-1" style="color: var(--primary);"></i>
                            Supervisor Type <span class="text-danger" style="color: var(--danger);">*</span>
                        </label>
                        <select name="supervisor_type" id="supervisor_type" 
                                class="index-custom-dropdown w-full @error('supervisor_type') is-invalid @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            @foreach($supervisorTypes as $value => $label)
                                <option value="{{ $value }}" 
                                        {{ old('supervisor_type', $assignment->supervisor_type) == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Changing the type will update permissions to defaults
                        </p>
                    </div>

                    <!-- Shifts -->
                    <div>
                        <label for="shift_ids" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-1" style="color: var(--primary);"></i>
                            Specific Shifts to Supervise
                        </label>
                        <select name="shift_ids[]" id="shift_ids" 
                                class="index-custom-dropdown w-full" multiple 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}" 
                                        {{ in_array($shift->id, old('shift_ids', $assignment->shift_ids ?? [])) ? 'selected' : '' }}>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                    @if(!$shift->is_active)
                                        - [Inactive]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">Leave empty to supervise all shifts</p>
                    </div>

                    <!-- Applicable Days -->
                    <div>
                        <label for="applicable_days" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-day mr-1" style="color: var(--primary);"></i>
                            Applicable Days
                        </label>
                        <select name="applicable_days[]" id="applicable_days" 
                                class="index-custom-dropdown w-full" multiple 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @foreach($daysOfWeek as $dayNum => $dayName)
                                <option value="{{ $dayNum }}" 
                                        {{ in_array($dayNum, old('applicable_days', $assignment->applicable_days ?? [])) ? 'selected' : '' }}>
                                    {{ $dayName }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">Leave empty to cover all days</p>
                    </div>

                    <!-- Primary Supervisor Toggle -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex items-start">
                                <i class="fas fa-star mr-3 mt-0.5" style="color: var(--warning);"></i>
                                <div>
                                    <label for="is_primary_supervisor" class="text-sm font-medium cursor-pointer" style="color: var(--text-primary);">
                                        Set as Primary Supervisor
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Primary supervisor will be the main point of contact for this post</p>
                                </div>
                            </div>
                            <div class="toggle-modern">
                                <input type="checkbox" name="is_primary_supervisor" id="is_primary_supervisor" value="1" 
                                       {{ old('is_primary_supervisor', $assignment->is_primary_supervisor) ? 'checked' : '' }} class="sr-only">
                                <label for="is_primary_supervisor" class="toggle-slider"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                        Supervisor Permissions
                    </h3>
                    <div class="flex space-x-2">
                        <button type="button" id="selectAllPermissions"
                                class="px-3 py-1 text-xs rounded-lg btn-secondary"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            Select All
                        </button>
                        <button type="button" id="deselectAllPermissions"
                                class="px-3 py-1 text-xs rounded-lg btn-secondary"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                            Deselect All
                        </button>
                        <button type="button" id="resetToTypeDefaults"
                                class="px-3 py-1 text-xs rounded-lg btn-secondary"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            Type Defaults
                        </button>
                    </div>
                </div>
                <div class="p-6" style="max-height: 350px; overflow-y: auto;">
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($permissionOptions as $permission => $label)
                            <div class="flex items-center justify-between p-3 rounded-lg permission-item" style="background-color: var(--bg-secondary);">
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle mr-3 mt-0.5" style="color: var(--primary); opacity: 0.7;"></i>
                                    <div>
                                        <label for="{{ $permission }}" class="text-sm font-medium cursor-pointer" style="color: var(--text-primary);">
                                            {{ $label }}
                                        </label>
                                        <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            @switch($permission)
                                                @case('can_override_checkins')
                                                    Allow supervisor to override check-in/check-out times
                                                    @break
                                                @case('can_approve_swaps')
                                                    Allow supervisor to approve shift swap requests
                                                    @break
                                                @case('can_approve_overtime')
                                                    Allow supervisor to approve overtime requests
                                                    @break
                                                @case('can_review_incidents')
                                                    Allow supervisor to review and resolve incidents
                                                    @break
                                                @case('can_verify_checkins')
                                                    Allow supervisor to verify check-in/out
                                                    @break
                                                @case('can_request_backup')
                                                    Allow supervisor to request backup
                                                    @break
                                                @case('can_approve_breaks')
                                                    Allow supervisor to approve break requests
                                                    @break
                                                @case('can_escalate_issues')
                                                    Allow supervisor to escalate issues
                                                    @break
                                                @case('can_view_all_schedules')
                                                    Allow supervisor to view all schedules
                                                    @break
                                                @case('can_edit_schedules')
                                                    Allow supervisor to edit schedules
                                                    @break
                                                @default
                                                    Additional permission
                                            @endswitch
                                        </p>
                                    </div>
                                </div>
                                <div class="toggle-modern">
                                    <input type="checkbox" name="{{ $permission }}" id="{{ $permission }}" value="1" 
                                           {{ old($permission, $assignment->$permission ?? true) ? 'checked' : '' }} 
                                           class="permission-checkbox sr-only">
                                    <label for="{{ $permission }}" class="toggle-slider"></label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Custom Permissions JSON -->
                    <div class="mt-4">
                        <label for="permissions" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-code mr-1" style="color: var(--primary);"></i>
                            Additional Permissions (JSON)
                            <span class="text-xs text-muted" style="color: var(--text-secondary);">(Admin Only)</span>
                        </label>
                        <textarea name="permissions" id="permissions" rows="2"
                                  class="index-custom-input w-full @error('permissions') is-invalid @enderror font-mono text-xs"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder='["approve_leave", "manage_equipment"]'>{{ old('permissions', json_encode($assignment->permissions ?? [])) }}</textarea>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">Enter custom permissions as JSON array. This is an admin-only feature.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="card mt-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i>
                    Notes & Additional Information
                </h3>
            </div>
            <div class="p-6">
                <div>
                    <label for="notes" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-pen mr-1" style="color: var(--primary);"></i>
                        Assignment Notes
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              class="index-custom-input w-full @error('notes') is-invalid @enderror"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Enter any special instructions or notes for this supervisor...">{{ old('notes', $assignment->notes) }}</textarea>
                </div>

                <!-- Admin Notes -->
                <div class="mt-3">
                    <label for="admin_notes" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-1" style="color: var(--primary);"></i>
                        Admin Notes <span class="text-xs text-muted" style="color: var(--text-secondary);">(Internal)</span>
                    </label>
                    <textarea name="admin_notes" id="admin_notes" rows="2"
                              class="index-custom-input w-full"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); border-style: dashed;"
                              placeholder="Internal notes visible only to administrators...">{{ old('admin_notes', $assignment->metadata['admin_notes'] ?? '') }}</textarea>
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">These notes are only visible to administrators and will be stored in metadata.</p>
                </div>

                <!-- Assignment Summary -->
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <h4 class="text-sm font-semibold mb-2 flex items-center" style="color: var(--info);">
                        <i class="fas fa-info-circle mr-2"></i>
                        Assignment Summary
                    </h4>
                    <div id="assignmentSummary" class="text-sm space-y-1" style="color: var(--text-secondary);">
                        <!-- Filled by JavaScript -->
                    </div>
                </div>

                <!-- Update History (if exists) -->
                @if(!empty($assignment->metadata['update_history']))
                    <div class="mt-4">
                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium" style="color: var(--text-secondary);">
                                <i class="fas fa-history mr-1"></i> Update History ({{ count($assignment->metadata['update_history']) }})
                            </summary>
                            <div class="mt-2 space-y-2 pl-2">
                                @foreach(array_reverse($assignment->metadata['update_history']) as $update)
                                    <div class="p-2 rounded text-xs" style="background-color: var(--bg-secondary);">
                                        <div class="flex justify-between">
                                            <span class="font-medium">{{ $update['updated_by_name'] ?? 'Unknown' }}</span>
                                            <span style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($update['updated_at'])->diffForHumans() }}</span>
                                        </div>
                                        <div class="mt-1" style="color: var(--text-secondary);">
                                            Updated {{ count($update['changes']) }} field(s)
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                @endif
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card mt-6">
            <div class="p-6 flex justify-end space-x-3">
                <a href="{{ route('admin.supervisor-assignments.show', $assignment) }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="button" id="resetBtn"
                        class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: var(--bg-secondary); color: var(--text-primary);"
                        onclick="resetToOriginal()">
                    <i class="fas fa-undo mr-2"></i> Reset to Original
                </button>
                <button type="submit" id="submitBtn"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Update Assignment
                </button>
            </div>
        </div>
    </form>
</div>

<style>
/* Modern Toggle Switch Styles */
.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-modern input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    border: 2px solid #d1d5db;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-modern input:checked + .toggle-slider {
    background-color: var(--success);
    border-color: var(--success);
}

.toggle-modern input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

/* Permission item hover effect */
.permission-item {
    transition: all 0.2s ease;
}

.permission-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Duration preset buttons */
.duration-preset:hover {
    background-color: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
}

.duration-preset:active {
    transform: scale(0.95);
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
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

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    .toggle-modern {
        width: 40px;
        height: 20px;
    }
    
    .toggle-slider:before {
        height: 14px;
        width: 14px;
    }
    
    .toggle-modern input:checked + .toggle-slider:before {
        transform: translateX(18px);
    }
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store original values for reset functionality
    const originalValues = {
        user_id: '{{ $assignment->user_id }}',
        security_post_id: '{{ $assignment->security_post_id }}',
        start_date: '{{ $assignment->start_date->format('Y-m-d') }}',
        end_date: '{{ $assignment->end_date?->format('Y-m-d') }}',
        supervisor_type: '{{ $assignment->supervisor_type }}',
        is_active: {{ $assignment->is_active ? 'true' : 'false' }},
        is_primary_supervisor: {{ $assignment->is_primary_supervisor ? 'true' : 'false' }},
        shift_ids: {!! json_encode($assignment->shift_ids ?? []) !!},
        applicable_days: {!! json_encode($assignment->applicable_days ?? []) !!},
        notes: {{ json_encode($assignment->notes) }},
        permissions: {
            @foreach($permissionOptions as $permission => $label)
                '{{ $permission }}': {{ $assignment->$permission ?? 'true' }},
            @endforeach
        }
    };

    // Initialize Select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.index-custom-dropdown').select2({
            theme: 'default',
            placeholder: 'Select options',
            allowClear: true,
            dropdownParent: $(document.body)
        });
    }

    // Initialize toggle switches
    const toggleSwitches = document.querySelectorAll('.toggle-modern input[type="checkbox"]');
    toggleSwitches.forEach(switchEl => {
        updateToggleSwitch(switchEl);
        switchEl.addEventListener('change', function() {
            updateToggleSwitch(this);
        });
    });

    function updateToggleSwitch(checkbox) {
        const slider = checkbox.nextElementSibling;
        if (checkbox.checked) {
            slider.style.backgroundColor = 'var(--success)';
            slider.style.borderColor = 'var(--success)';
        } else {
            slider.style.backgroundColor = '#d1d5db';
            slider.style.borderColor = '#d1d5db';
        }
    }

    // ✅ UPDATED: Supervisor preview with eligibility
    const userSelect = document.getElementById('user_id');
    const supervisorPreview = document.getElementById('supervisorPreview');
    const previewName = document.getElementById('previewName');
    const previewEligibility = document.getElementById('previewEligibility');

    userSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            const name = selected.dataset.name || 'Unknown';
            const isEligible = selected.dataset.eligible === 'true';
            const email = selected.dataset.email || 'No email';
            
            previewName.textContent = name;
            previewEligibility.textContent = isEligible ? '✅ Eligible' : '❌ Not Eligible';
            previewEligibility.style.backgroundColor = isEligible ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)';
            previewEligibility.style.color = isEligible ? 'var(--success)' : 'var(--danger)';
            supervisorPreview.classList.remove('hidden');
        } else {
            supervisorPreview.classList.add('hidden');
        }
        updateAssignmentSummary();
    });

    // Post preview
    const postSelect = document.getElementById('security_post_id');
    const postPreview = document.getElementById('postPreview');
    const postCapacity = document.getElementById('postCapacity');
    const postCode = document.getElementById('postCode');

    postSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            const maxPersonnel = selected.dataset.max;
            const code = selected.dataset.code;
            postCapacity.textContent = 'Max Personnel: ' + (maxPersonnel || 'N/A');
            postCode.textContent = 'Code: ' + (code || 'N/A');
            postPreview.classList.remove('hidden');
        } else {
            postPreview.classList.add('hidden');
        }
        updateAssignmentSummary();
    });

    // Date handling with presets
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const durationDisplay = document.getElementById('durationDisplay');

    function updateDurationDisplay() {
        const start = startDate.value;
        const end = endDate.value;
        
        if (start && end) {
            const startMoment = new Date(start);
            const endMoment = new Date(end);
            const days = Math.round((endMoment - startMoment) / (1000 * 60 * 60 * 24));
            durationDisplay.textContent = `Duration: ${days} day(s) (${start} to ${end})`;
        } else if (start && !end) {
            durationDisplay.textContent = 'Duration: Indefinite (no end date)';
        } else {
            durationDisplay.textContent = 'Duration: Indefinite';
        }
    }

    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        updateDurationDisplay();
    });

    endDate.addEventListener('change', updateDurationDisplay);

    // Duration presets
    document.querySelectorAll('.duration-preset').forEach(button => {
        button.addEventListener('click', function() {
            const days = parseInt(this.dataset.days);
            if (days === 0) {
                endDate.value = '';
            } else {
                const start = new Date(startDate.value || new Date());
                const end = new Date(start);
                end.setDate(end.getDate() + days);
                endDate.value = end.toISOString().split('T')[0];
            }
            updateDurationDisplay();
        });
    });

    // Permission toggles
    const selectAllBtn = document.getElementById('selectAllPermissions');
    const deselectAllBtn = document.getElementById('deselectAllPermissions');
    const resetToDefaultsBtn = document.getElementById('resetToTypeDefaults');
    const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');

    selectAllBtn.addEventListener('click', function() {
        permissionCheckboxes.forEach(cb => {
            cb.checked = true;
            updateToggleSwitch(cb);
        });
    });

    deselectAllBtn.addEventListener('click', function() {
        permissionCheckboxes.forEach(cb => {
            cb.checked = false;
            updateToggleSwitch(cb);
        });
    });

    resetToDefaultsBtn.addEventListener('click', function() {
        const type = document.getElementById('supervisor_type').value;
        updatePermissionsByType(type);
    });

    permissionCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateToggleSwitch(this);
        });
    });

    // Supervisor type permissions
    const supervisorType = document.getElementById('supervisor_type');
    
    function updatePermissionsByType(type) {
        const defaultPermissions = {
            'post_supervisor': {
                'can_override_checkins': true,
                'can_approve_swaps': true,
                'can_approve_overtime': true,
                'can_review_incidents': true,
                'can_verify_checkins': true,
                'can_request_backup': true,
                'can_approve_breaks': true,
                'can_escalate_issues': true,
                'can_view_all_schedules': true,
                'can_edit_schedules': false
            },
            'shift_supervisor': {
                'can_override_checkins': true,
                'can_approve_swaps': true,
                'can_approve_overtime': true,
                'can_review_incidents': false,
                'can_verify_checkins': true,
                'can_request_backup': true,
                'can_approve_breaks': true,
                'can_escalate_issues': true,
                'can_view_all_schedules': true,
                'can_edit_schedules': false
            },
            'area_supervisor': {
                'can_override_checkins': true,
                'can_approve_swaps': true,
                'can_approve_overtime': true,
                'can_review_incidents': true,
                'can_verify_checkins': true,
                'can_request_backup': true,
                'can_approve_breaks': true,
                'can_escalate_issues': true,
                'can_view_all_schedules': true,
                'can_edit_schedules': true
            },
            'relief_supervisor': {
                'can_override_checkins': true,
                'can_approve_swaps': false,
                'can_approve_overtime': false,
                'can_review_incidents': false,
                'can_verify_checkins': true,
                'can_request_backup': true,
                'can_approve_breaks': true,
                'can_escalate_issues': true,
                'can_view_all_schedules': true,
                'can_edit_schedules': false
            },
            'training_supervisor': {
                'can_override_checkins': false,
                'can_approve_swaps': false,
                'can_approve_overtime': false,
                'can_review_incidents': false,
                'can_verify_checkins': true,
                'can_request_backup': false,
                'can_approve_breaks': true,
                'can_escalate_issues': true,
                'can_view_all_schedules': true,
                'can_edit_schedules': false
            }
        };

        if (defaultPermissions[type]) {
            const perms = defaultPermissions[type];
            permissionCheckboxes.forEach(cb => {
                const permName = cb.name;
                if (perms.hasOwnProperty(permName)) {
                    cb.checked = perms[permName];
                    updateToggleSwitch(cb);
                }
            });
        }
    }

    supervisorType.addEventListener('change', function() {
        updateAssignmentSummary();
    });

    // Assignment summary
    function updateAssignmentSummary() {
        const supervisor = userSelect.options[userSelect.selectedIndex]?.text.split('(')[0] || 'Not selected';
        const post = postSelect.options[postSelect.selectedIndex]?.text || 'Role Only';
        const type = supervisorType.options[supervisorType.selectedIndex]?.text || 'Not selected';
        const start = startDate.value || 'Not set';
        const end = endDate.value || 'Indefinite';
        const active = document.getElementById('is_active').checked ? '✅ Active' : '❌ Inactive';
        const primary = document.getElementById('is_primary_supervisor').checked ? '⭐ Yes' : 'No';
        
        let checkedPermissions = 0;
        permissionCheckboxes.forEach(cb => {
            if (cb.checked) checkedPermissions++;
        });
        
        let summary = '';
        summary += `<div class="flex justify-between"><span class="font-medium">Supervisor:</span> <span>${supervisor}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Post:</span> <span>${post}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Type:</span> <span>${type}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Status:</span> <span>${active}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Primary:</span> <span>${primary}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Start Date:</span> <span>${start}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">End Date:</span> <span>${end}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Permissions:</span> <span>${checkedPermissions} of ${permissionCheckboxes.length} enabled</span></div>`;
        
        document.getElementById('assignmentSummary').innerHTML = summary;
    }

    document.querySelectorAll('#is_active, #is_primary_supervisor, .permission-checkbox').forEach(el => {
        el.addEventListener('change', updateAssignmentSummary);
    });

    window.resetToOriginal = function() {
        if (confirm('Reset all fields to their original values? Any unsaved changes will be lost.')) {
            userSelect.value = originalValues.user_id;
            $(userSelect).trigger('change');
            
            postSelect.value = originalValues.security_post_id;
            $(postSelect).trigger('change');
            
            startDate.value = originalValues.start_date;
            endDate.value = originalValues.end_date;
            supervisorType.value = originalValues.supervisor_type;
            $(supervisorType).trigger('change');
            
            document.getElementById('is_active').checked = originalValues.is_active;
            document.getElementById('is_primary_supervisor').checked = originalValues.is_primary_supervisor;
            document.getElementById('notes').value = originalValues.notes;
            
            if (window.$ && $.fn.select2) {
                $('#shift_ids').val(originalValues.shift_ids).trigger('change');
                $('#applicable_days').val(originalValues.applicable_days).trigger('change');
            }
            
            permissionCheckboxes.forEach(cb => {
                const permName = cb.name;
                if (originalValues.permissions.hasOwnProperty(permName)) {
                    cb.checked = originalValues.permissions[permName];
                    updateToggleSwitch(cb);
                }
            });
            
            userSelect.dispatchEvent(new Event('change'));
            postSelect.dispatchEvent(new Event('change'));
            updateDurationDisplay();
            updateAssignmentSummary();
            
            formChanged = false;
        }
    };

    const form = document.getElementById('assignmentForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        if (!userSelect.value) {
            e.preventDefault();
            alert('Please select a supervisor.');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    });

    if (userSelect.value) {
        userSelect.dispatchEvent(new Event('change'));
    }
    if (postSelect.value) {
        postSelect.dispatchEvent(new Event('change'));
    }
    updateDurationDisplay();
    updateAssignmentSummary();

    let formChanged = false;
    form.addEventListener('input', function() {
        formChanged = true;
    });
    
    permissionCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            formChanged = true;
        });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});
</script>
@endsection