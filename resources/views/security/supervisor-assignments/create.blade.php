@extends('layouts.secu')

@section('title', 'Assign Supervisor')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-tie text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                        Assign Supervisor
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Assign supervisors within your area of responsibility</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $assignableSupervisors->count() ?? 0 }} eligible supervisors</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Area Supervisor
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Info Alert - What Area Supervisors Can Do -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-lg mr-3 mt-0.5" style="color: var(--info);"></i>
            <div>
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Area Supervisor Access</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    As an Area Supervisor, you can assign <strong>Post Supervisors, Shift Supervisors, Relief Supervisors, and Training Supervisors</strong> 
                    within your area of responsibility. Area Supervisors can only be assigned by Administrators.
                </p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                    Only users marked as <strong>"Eligible"</strong> (<i class="fas fa-check-circle" style="color: var(--success);"></i>) can be assigned as supervisors.
                    Manage eligibility in User Management.
                </p>
            </div>
        </div>
    </div>

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

    <form action="{{ route('security.supervisor-assignments.store') }}" method="POST" id="assignmentForm">
        @csrf
        
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
                            @foreach($assignableSupervisors as $supervisor)
                                <option value="{{ $supervisor->id }}" 
                                        data-eligible="{{ $supervisor->can_be_supervisor ? 'true' : 'false' }}"
                                        data-name="{{ $supervisor->name }}"
                                        {{ old('user_id') == $supervisor->id ? 'selected' : '' }}>
                                    {{ $supervisor->name }} 
                                    @if($supervisor->can_be_supervisor)
                                        <span class="text-xs text-green-500">(✅ Eligible)</span>
                                    @else
                                        <span class="text-xs text-yellow-500">(⚠️ Not Eligible)</span>
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        
                        <!-- Supervisor Preview -->
                        <div id="supervisorPreview" class="mt-3 p-3 rounded-lg hidden" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">Selected Supervisor:</span>
                                <div class="flex space-x-2">
                                    <span class="px-2 py-1 text-xs rounded-full badge-info" id="previewName"></span>
                                    <span class="px-2 py-1 text-xs rounded-full badge-success" id="previewEligibility"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Only users marked as <strong>Eligible</strong> can be assigned as supervisors.
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
                                        data-code="{{ $post->code }}"
                                        {{ old('security_post_id') == $post->id ? 'selected' : '' }}>
                                    {{ $post->name }} ({{ $post->code }})
                                    @if(isset($post->max_personnel))
                                        <span class="text-xs text-gray-500">- Max: {{ $post->max_personnel }}</span>
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select <strong>"Role Only"</strong> to assign the supervisor role without assigning to a specific post.
                            Select a post to assign the supervisor to that specific post.
                        </p>
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
                                   value="{{ old('start_date', now()->format('Y-m-d')) }}" 
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
                                   value="{{ old('end_date') }}"
                                   min="{{ old('start_date', now()->format('Y-m-d')) }}">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">Leave empty for indefinite</p>
                        </div>
                    </div>

                    <!-- Duration Display -->
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-primary);" id="durationDisplay">Duration: Indefinite</span>
                        </div>
                    </div>

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
                                        {{ old('supervisor_type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Area Supervisor is only available to Administrators
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
                                        {{ in_array($shift->id, old('shift_ids', [])) ? 'selected' : '' }}>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                    @if($shift->category)
                                        - {{ ucfirst($shift->category) }}
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
                                        {{ in_array($dayNum, old('applicable_days', [])) ? 'selected' : '' }}>
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
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Primary supervisor will be the main point of contact</p>
                                </div>
                            </div>
                            <div class="toggle-modern">
                                <input type="checkbox" name="is_primary_supervisor" id="is_primary_supervisor" value="1" 
                                       {{ old('is_primary_supervisor') ? 'checked' : '' }} class="sr-only">
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
                        <button type="button" id="selectAllPermissions" class="px-3 py-1 text-xs rounded-lg btn-secondary"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">Select All</button>
                        <button type="button" id="deselectAllPermissions" class="px-3 py-1 text-xs rounded-lg btn-secondary"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">Deselect All</button>
                        <button type="button" id="resetToTypeDefaults" class="px-3 py-1 text-xs rounded-lg btn-secondary"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">Type Defaults</button>
                    </div>
                </div>
                <div class="p-6" style="max-height: 350px; overflow-y: auto;">
                    @foreach($permissionOptions as $permission => $label)
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div>
                                <label for="{{ $permission }}" class="text-sm font-medium cursor-pointer" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                    @switch($permission)
                                        @case('can_override_checkins') Allow supervisor to override check-in/check-out times @break
                                        @case('can_approve_swaps') Allow supervisor to approve shift swap requests @break
                                        @case('can_approve_overtime') Allow supervisor to approve overtime requests @break
                                        @case('can_review_incidents') Allow supervisor to review and resolve incidents @break
                                        @case('can_verify_checkins') Allow supervisor to verify check-in/out @break
                                        @case('can_request_backup') Allow supervisor to request backup @break
                                        @case('can_approve_breaks') Allow supervisor to approve break requests @break
                                        @case('can_escalate_issues') Allow supervisor to escalate issues @break
                                        @case('can_view_all_schedules') Allow supervisor to view all schedules @break
                                        @case('can_edit_schedules') Allow supervisor to edit schedules @break
                                        @default Additional permission
                                    @endswitch
                                </p>
                            </div>
                            <div class="toggle-modern">
                                <input type="checkbox" name="{{ $permission }}" id="{{ $permission }}" value="1" 
                                       {{ old($permission, true) ? 'checked' : '' }} class="permission-checkbox sr-only">
                                <label for="{{ $permission }}" class="toggle-slider"></label>
                            </div>
                        </div>
                    @endforeach
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
                              placeholder="Enter any special instructions...">{{ old('notes') }}</textarea>
                </div>

                <!-- No Schedule Auto-Created Note -->
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-clock mr-2 mt-0.5" style="color: var(--warning);"></i>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-primary);">
                                <strong>Note:</strong> Assigning a supervisor role does not automatically create a schedule.
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                You will need to create schedules separately for this personnel.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Assignment Summary -->
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <h4 class="text-sm font-semibold mb-2 flex items-center" style="color: var(--info);">
                        <i class="fas fa-info-circle mr-2"></i> Assignment Summary
                    </h4>
                    <div id="assignmentSummary" class="text-sm space-y-1" style="color: var(--text-secondary);">
                        <p>Fill in the form to see a summary of this assignment.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card mt-6">
            <div class="p-6 flex justify-end space-x-3">
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="reset" class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button type="submit" id="submitBtn"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Assign Supervisor
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.toggle-modern input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
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
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.toggle-modern input:checked + .toggle-slider {
    background-color: var(--success);
    border-color: var(--success);
}
.toggle-modern input:checked + .toggle-slider:before {
    transform: translateX(24px);
}
.toggle-modern input:focus + .toggle-slider {
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.3);
}

.duration-preset:hover {
    background-color: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
}
.duration-preset:active {
    transform: scale(0.95);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s;
    color: white;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}
.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}
.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

@media (max-width: 768px) {
    .toggle-modern { width: 40px; height: 20px; }
    .toggle-slider:before { height: 14px; width: 14px; left: 1px; bottom: 1px; }
    .toggle-modern input:checked + .toggle-slider:before { transform: translateX(18px); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize toggle switches
    document.querySelectorAll('.toggle-modern input[type="checkbox"]').forEach(el => {
        updateToggleSwitch(el);
        el.addEventListener('change', function() { updateToggleSwitch(this); });
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

    // ✅ UPDATED: Supervisor preview using eligibility
    const userSelect = document.getElementById('user_id');
    const supervisorPreview = document.getElementById('supervisorPreview');
    const previewName = document.getElementById('previewName');
    const previewEligibility = document.getElementById('previewEligibility');

    userSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            const name = selected.dataset.name || 'Unknown';
            const isEligible = selected.dataset.eligible === 'true';
            
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
    postSelect.addEventListener('change', function() {
        updateAssignmentSummary();
    });

    // Date handling
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const durationDisplay = document.getElementById('durationDisplay');

    function updateDurationDisplay() {
        const start = startDate.value;
        const end = endDate.value;
        if (start && end) {
            const days = Math.round((new Date(end) - new Date(start)) / (1000 * 60 * 60 * 24));
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
    document.getElementById('selectAllPermissions').addEventListener('click', function() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = true; updateToggleSwitch(cb); });
    });
    document.getElementById('deselectAllPermissions').addEventListener('click', function() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = false; updateToggleSwitch(cb); });
    });

    // Reset to type defaults
    document.getElementById('resetToTypeDefaults').addEventListener('click', function() {
        const type = document.getElementById('supervisor_type').value;
        updatePermissionsByType(type);
    });

    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        cb.addEventListener('change', function() { updateToggleSwitch(this); });
    });

    // Supervisor type permissions
    const supervisorType = document.getElementById('supervisor_type');
    
    function updatePermissionsByType(type) {
        const defaultPermissions = {
            'post_supervisor': {
                'can_override_checkins': true, 'can_approve_swaps': true, 'can_approve_overtime': true,
                'can_review_incidents': true, 'can_verify_checkins': true, 'can_request_backup': true,
                'can_approve_breaks': true, 'can_escalate_issues': true, 'can_view_all_schedules': true,
                'can_edit_schedules': false
            },
            'shift_supervisor': {
                'can_override_checkins': true, 'can_approve_swaps': true, 'can_approve_overtime': true,
                'can_review_incidents': false, 'can_verify_checkins': true, 'can_request_backup': true,
                'can_approve_breaks': true, 'can_escalate_issues': true, 'can_view_all_schedules': true,
                'can_edit_schedules': false
            },
            'relief_supervisor': {
                'can_override_checkins': true, 'can_approve_swaps': false, 'can_approve_overtime': false,
                'can_review_incidents': false, 'can_verify_checkins': true, 'can_request_backup': true,
                'can_approve_breaks': true, 'can_escalate_issues': true, 'can_view_all_schedules': true,
                'can_edit_schedules': false
            },
            'training_supervisor': {
                'can_override_checkins': false, 'can_approve_swaps': false, 'can_approve_overtime': false,
                'can_review_incidents': false, 'can_verify_checkins': true, 'can_request_backup': false,
                'can_approve_breaks': true, 'can_escalate_issues': true, 'can_view_all_schedules': true,
                'can_edit_schedules': false
            }
        };

        if (defaultPermissions[type]) {
            const perms = defaultPermissions[type];
            document.querySelectorAll('.permission-checkbox').forEach(cb => {
                if (perms.hasOwnProperty(cb.name)) {
                    cb.checked = perms[cb.name];
                    updateToggleSwitch(cb);
                }
            });
        }
    }

    supervisorType.addEventListener('change', function() {
        updatePermissionsByType(this.value);
        updateAssignmentSummary();
    });

    // Assignment summary
    function updateAssignmentSummary() {
        const selectedSupervisor = userSelect.options[userSelect.selectedIndex];
        const supervisor = selectedSupervisor?.text.split('(')[0] || 'Not selected';
        const isEligible = selectedSupervisor?.dataset?.eligible === 'true';
        const eligibilityText = isEligible ? '✅ Eligible' : '❌ Not Eligible';
        const post = postSelect.options[postSelect.selectedIndex]?.text || 'Role Only';
        const type = supervisorType.options[supervisorType.selectedIndex]?.text || 'Not selected';
        const start = startDate.value || 'Not set';
        const end = endDate.value || 'Indefinite';
        const primary = document.getElementById('is_primary_supervisor').checked ? '⭐ Yes' : 'No';
        
        let summary = '';
        summary += `<div class="flex justify-between"><span class="font-medium">Supervisor:</span> <span>${supervisor}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Eligibility:</span> <span>${eligibilityText}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Post:</span> <span>${post}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Type:</span> <span>${type}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Primary:</span> <span>${primary}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">Start Date:</span> <span>${start}</span></div>`;
        summary += `<div class="flex justify-between"><span class="font-medium">End Date:</span> <span>${end}</span></div>`;
        
        document.getElementById('assignmentSummary').innerHTML = summary;
    }

    // Add listeners for summary updates
    document.querySelectorAll('#is_primary_supervisor, .permission-checkbox').forEach(el => {
        el.addEventListener('change', updateAssignmentSummary);
    });

    // Form submission
    const form = document.getElementById('assignmentForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        if (!userSelect.value) {
            e.preventDefault();
            alert('Please select a supervisor.');
            return;
        }
        
        // Check if selected user is eligible
        const selected = userSelect.options[userSelect.selectedIndex];
        if (selected.value && selected.dataset.eligible === 'false') {
            e.preventDefault();
            alert('Selected user is not eligible to be a supervisor. Please select an eligible user.');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Assigning...';
    });

    // Initialize
    updateDurationDisplay();
    updateAssignmentSummary();
    updatePermissionsByType(supervisorType.value);
});
</script>
@endsection