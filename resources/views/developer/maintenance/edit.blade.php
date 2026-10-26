@extends('layouts.dev')

@section('title', 'Edit Maintenance Schedule')

@section('content')
@php
    use App\Models\Maintenance;

    $editable = in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED], true);

    $statusStyle = match ($maintenance->status) {
        Maintenance::STATUS_DRAFT       => ['info',    'var(--info)'],
        Maintenance::STATUS_SCHEDULED   => ['primary', 'var(--primary)'],
        Maintenance::STATUS_IN_PROGRESS => ['warning', 'var(--warning)'],
        Maintenance::STATUS_COMPLETED   => ['success', 'var(--success)'],
        Maintenance::STATUS_CANCELLED   => ['danger',  'var(--danger)'],
        default                         => ['info',    'var(--info)'],
    };
@endphp

<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-edit mr-2" style="color: var(--primary);"></i> Edit Maintenance Schedule
                </h1>
                <div class="flex items-center space-x-4 text-sm flex-wrap gap-2">
                    <span class="badge" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        ID: {{ $maintenance->reference_id }}
                    </span>
                    <span class="badge"
                          style="background-color: rgba(var(--{{ $statusStyle[0] }}-rgb), 0.15); color: {{ $statusStyle[1] }}; border: 1px solid rgba(var(--{{ $statusStyle[0] }}-rgb), 0.3);">
                        {{ str_replace('_', ' ', $maintenance->status) }}
                    </span>
                    @if($maintenance->is_emergency)
                        <span class="badge" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Emergency
                        </span>
                    @endif
                </div>
            </div>

            <div class="text-sm text-right" style="color: var(--text-secondary);">
                Created: {{ $maintenance->created_at->format('M d, Y H:i') }}
                @if($maintenance->updated_at)
                    <br>Last Updated: {{ $maintenance->updated_at->format('M d, Y H:i') }}
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    @if($editable)
    <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center">
                <i class="fas fa-lightbulb mr-3 text-lg" style="color: var(--warning);"></i>
                <div>
                    <h3 class="font-medium" style="color: var(--text-primary);">Quick Actions</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Available actions for this maintenance schedule
                    </p>
                </div>
            </div>

            <div class="flex space-x-2 flex-wrap gap-2">
                @if($maintenance->status === Maintenance::STATUS_DRAFT)
                    <form action="{{ route('developer.maintenance.approve', $maintenance->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Are you sure you want to approve this maintenance schedule?')"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--success); color: white;">
                            <i class="fas fa-check-circle mr-2"></i> Approve
                        </button>
                    </form>
                @endif

                @if($maintenance->status === Maintenance::STATUS_SCHEDULED)
                    <form action="{{ route('developer.maintenance.start', $maintenance->id) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="confirm_pre_checks" value="1">
                        <input type="hidden" name="start_notes" value="Started from edit page">
                        <button type="submit"
                                onclick="return confirm('Are you sure you want to start this maintenance? This will notify users.')"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--warning); color: white;">
                            <i class="fas fa-play mr-2"></i> Start Now
                        </button>
                    </form>
                @endif

                <button type="button" onclick="openCancelModal()"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                        style="background-color: var(--danger); color: white;">
                    <i class="fas fa-times-circle mr-2"></i> Cancel
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Limited Editing Warning --}}
    @if(!$editable)
    <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; border-left: 4px solid var(--warning);">
        <div class="p-4 flex items-center">
            <i class="fas fa-exclamation-circle mr-3 text-lg" style="color: var(--warning);"></i>
            <div>
                <h3 class="font-medium" style="color: var(--warning);">Limited Editing</h3>
                <p class="text-sm" style="color: var(--text-secondary);">
                    This maintenance schedule is {{ str_replace('_', ' ', $maintenance->status) }} and cannot be edited.
                    You can only view the details.
                </p>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('developer.maintenance.update', $maintenance->id) }}" method="POST" id="maintenanceForm">
        @csrf
        @method('PUT')

        {{-- ============================================================
             | UPDATE NOTES
             * ============================================================ --}}
        @if($editable)
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i> Update Notes
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Explain what changes you're making (required for audit trail)
                </p>
            </div>
            <div class="p-6">
                <textarea id="update_notes" name="update_notes" rows="3" maxlength="500"
                          class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                          placeholder="Describe the changes you're making and why..."
                          required minlength="10">{{ old('update_notes') }}</textarea>
                <div class="flex justify-between mt-1">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        Minimum 10 characters — logged in the maintenance history
                    </div>
                    <div class="text-xs" style="color: var(--text-secondary);" id="notesCounter">
                        {{ strlen(old('update_notes') ?? '') }}/500
                    </div>
                </div>
                @error('update_notes')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
        @endif

        {{-- ============================================================
             | BASIC INFORMATION
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Basic Information
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Update essential details about this maintenance event
                </p>
            </div>

            <div class="p-6 space-y-6">
                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Maintenance Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title"
                           value="{{ old('title', $maintenance->title) }}"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Database Optimization, Security Patch Update"
                           {{ $editable ? '' : 'disabled' }}
                           required maxlength="255">
                    @error('title')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" name="description" rows="3" maxlength="500"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Provide a brief description of what this maintenance entails..."
                              {{ $editable ? '' : 'disabled' }}
                              required minlength="20">{{ old('description', $maintenance->description) }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">Minimum 20 characters</div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="descriptionCounter">
                            {{ strlen(old('description', $maintenance->description) ?? '') }}/500
                        </div>
                    </div>
                    @error('description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Technical Details --}}
                <div>
                    <label for="technical_details" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Technical Details <span class="text-xs">(Optional)</span>
                    </label>
                    <textarea id="technical_details" name="technical_details" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Technical specifications, database changes, API modifications, etc."
                              {{ $editable ? '' : 'disabled' }}>{{ old('technical_details', $maintenance->technical_details) }}</textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Internal use only — not shown to users
                    </div>
                </div>

                {{-- Type + Impact (read-only in edit) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Maintenance Type
                        </label>
                        <div class="p-3 border rounded-lg"
                             style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            {{ $maintenanceTypes[$maintenance->maintenance_type] ?? ucfirst($maintenance->maintenance_type) }}
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-lock mr-1"></i> Type cannot be changed after creation
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Impact Level
                        </label>
                        <div class="p-3 border rounded-lg capitalize"
                             style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            {{ $impactLevels[$maintenance->impact_level] ?? ucfirst($maintenance->impact_level) }}
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-lock mr-1"></i> Impact cannot be changed after creation
                        </div>
                    </div>
                </div>

                {{-- Emergency Status --}}
                @if($maintenance->is_emergency)
                <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--danger);"></i>
                        <div>
                            <h4 class="font-medium mb-2" style="color: var(--danger);">
                                <i class="fas fa-bolt mr-1"></i> Emergency Maintenance
                            </h4>
                            @if($maintenance->emergency_reason)
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <strong>Reason:</strong> {{ $maintenance->emergency_reason }}
                                </p>
                            @endif
                            @if($maintenance->emergencyMode)
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    <strong>Linked to Emergency:</strong>
                                    {{ $maintenance->emergencyMode->reference_id }} — {{ $maintenance->emergencyMode->name }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- ============================================================
             | SCHEDULING
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Scheduling
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Update the maintenance window and estimated duration
                </p>
            </div>

            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Start --}}
                    <div>
                        <label for="scheduled_start" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Start Date &amp; Time <span class="text-red-500">*</span>
                        </label>
                        @if($editable)
                            <input type="datetime-local" id="scheduled_start" name="scheduled_start"
                                   value="{{ old('scheduled_start', optional($maintenance->scheduled_start)->format('Y-m-d\TH:i')) }}"
                                   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Current: {{ optional($maintenance->scheduled_start)->format('M d, Y H:i') }}
                            </div>
                        @else
                            <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                {{ optional($maintenance->scheduled_start)->format('M d, Y H:i') }}
                                @if($maintenance->actual_start)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Actual Start: {{ $maintenance->actual_start->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('scheduled_start')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- End --}}
                    <div>
                        <label for="scheduled_end" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            End Date &amp; Time <span class="text-red-500">*</span>
                        </label>
                        @if($editable)
                            <input type="datetime-local" id="scheduled_end" name="scheduled_end"
                                   value="{{ old('scheduled_end', optional($maintenance->scheduled_end)->format('Y-m-d\TH:i')) }}"
                                   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Current: {{ optional($maintenance->scheduled_end)->format('M d, Y H:i') }}
                            </div>
                        @else
                            <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                {{ optional($maintenance->scheduled_end)->format('M d, Y H:i') }}
                                @if($maintenance->actual_end)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Actual End: {{ $maintenance->actual_end->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('scheduled_end')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Duration --}}
                <div>
                    <label for="estimated_duration_minutes" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Estimated Duration (minutes) <span class="text-red-500">*</span>
                    </label>
                    @if($editable)
                        <div class="flex items-center space-x-4">
                            <input type="range" id="duration_slider" name="duration_slider"
                                   min="1" max="1440"
                                   value="{{ old('estimated_duration_minutes', $maintenance->estimated_duration_minutes) }}"
                                   class="flex-1 h-2 rounded-lg appearance-none cursor-pointer"
                                   style="background-color: var(--border-color);">
                            <input type="number" id="estimated_duration_minutes" name="estimated_duration_minutes"
                                   value="{{ old('estimated_duration_minutes', $maintenance->estimated_duration_minutes) }}"
                                   class="w-24 p-2 border rounded-lg text-center"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   min="1" max="1440" required>
                            <span class="text-sm" style="color: var(--text-secondary);">minutes</span>
                        </div>
                        <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                            <span class="text-xs" style="color: var(--text-secondary);">Quick select:</span>
                            <div class="space-x-2">
                                @foreach([30 => '30 min', 60 => '1 hour', 120 => '2 hours', 240 => '4 hours'] as $mins => $label)
                                    <button type="button" onclick="setDuration({{ $mins }})"
                                            class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <div class="text-sm" style="color: var(--text-secondary);">Estimated</div>
                                <div class="font-medium">{{ $maintenance->estimated_duration_minutes }} minutes</div>
                            </div>
                            @if($maintenance->actual_duration_minutes)
                                <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <div class="text-sm" style="color: var(--text-secondary);">Actual</div>
                                    <div class="font-medium">{{ $maintenance->actual_duration_minutes }} minutes</div>
                                    @if($maintenance->completed_within_estimate)
                                        <div class="text-xs mt-1" style="color: var(--success);">Within estimate</div>
                                    @else
                                        <div class="text-xs mt-1" style="color: var(--danger);">Outside estimate</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                    @error('estimated_duration_minutes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Timezone --}}
                <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-globe-americas mr-2" style="color: var(--info);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            All times displayed in <span class="font-medium">{{ config('app.timezone') }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | IMPACT ASSESSMENT
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i> Impact Assessment
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    What will be affected during maintenance
                </p>
            </div>

            <div class="p-6 space-y-6">
                {{-- User Impact Description --}}
                <div>
                    <label for="user_impact_description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        User Impact Description <span class="text-red-500">*</span>
                    </label>
                    @if($editable)
                        <textarea id="user_impact_description" name="user_impact_description" rows="3" maxlength="500"
                                  class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe the impact from the user's perspective."
                                  required minlength="20">{{ old('user_impact_description', $maintenance->user_impact_description) }}</textarea>
                        <div class="flex justify-between mt-1">
                            <div class="text-xs" style="color: var(--text-secondary);">
                                Minimum 20 characters — shown to users
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);" id="impactCounter">
                                {{ strlen(old('user_impact_description', $maintenance->user_impact_description) ?? '') }}/500
                            </div>
                        </div>
                    @else
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            {{ $maintenance->user_impact_description }}
                        </div>
                    @endif
                    @error('user_impact_description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Affected Modules (read-only) --}}
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected Modules
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($defaultModules as $value => $label)
                            @if(in_array($value, $maintenance->affected_modules ?? [], true))
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                    <span style="color: var(--text-primary);">{{ $label }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-lock mr-1"></i> Modules cannot be changed after creation
                    </div>
                </div>

                {{-- Affected User Types (read-only) --}}
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected User Types
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($userTypes as $value => $label)
                            @if(in_array((string) $value, array_map('strval', $maintenance->affected_user_types ?? []), true))
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);">{{ $label }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-lock mr-1"></i> User types cannot be changed after creation
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | NOTIFICATION SETTINGS (read-only)
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2" style="color: var(--primary);"></i> Notification Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Notification configuration for this maintenance
                </p>
            </div>

            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            User Notifications
                        </label>
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @if($maintenance->notify_users)
                                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Users will be notified
                            @else
                                <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Users will not be notified
                            @endif
                            @if($maintenance->notification_sent_at)
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Sent: {{ $maintenance->notification_sent_at->format('M d, Y H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Channels
                        </label>
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @if(!empty($maintenance->notification_channels))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($maintenance->notification_channels as $channel)
                                        <span class="badge" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                            @switch($channel)
                                                @case('email') <i class="fas fa-envelope mr-1"></i> Email @break
                                                @case('sms') <i class="fas fa-comment-alt mr-1"></i> SMS @break
                                                @case('whatsapp') <i class="fab fa-whatsapp mr-1"></i> WhatsApp @break
                                                @case('in_app') <i class="fas fa-mobile-alt mr-1"></i> In-App @break
                                                @default {{ $channel }}
                                            @endswitch
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-sm" style="color: var(--text-secondary);">No channels configured</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Rollback Plan
                    </label>
                    <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        @if($maintenance->has_rollback_plan)
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Rollback plan exists
                        @else
                            <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> No rollback plan
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | SUMMARY
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-check mr-2" style="color: var(--primary);"></i> Summary
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Review before saving
                </p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Maintenance Details</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Reference:</span>
                                <span style="color: var(--text-primary); font-weight: 500;">{{ $maintenance->reference_id }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Status:</span>
                                <span class="badge" style="background-color: rgba(var(--{{ $statusStyle[0] }}-rgb), 0.15); color: {{ $statusStyle[1] }};">
                                    {{ str_replace('_', ' ', $maintenance->status) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Type:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryType">
                                    {{ $maintenanceTypes[$maintenance->maintenance_type] ?? ucfirst($maintenance->maintenance_type) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Impact:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryImpact">
                                    {{ $impactLevels[$maintenance->impact_level] ?? ucfirst($maintenance->impact_level) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Schedule</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Start:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryStart">
                                    {{ optional($maintenance->scheduled_start)->format('M d, Y H:i') }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Duration:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryDuration">
                                    {{ $maintenance->estimated_duration_minutes }} minutes
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Estimated Users:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryUsers">
                                    {{ number_format((int) $maintenance->estimated_affected_users) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($maintenance->status === Maintenance::STATUS_SCHEDULED)
                    <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                            <div>
                                <h4 class="font-medium mb-2" style="color: var(--warning);">Update Warning</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Changing a scheduled maintenance will trigger notifications to affected users about the schedule change.
                                    Only make changes if absolutely necessary.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============================================================
             | FORM ACTIONS
             * ============================================================ --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 flex justify-between items-center flex-wrap gap-3">
                <div class="flex space-x-3 flex-wrap">
                    <a href="{{ route('developer.maintenance.show', $maintenance->id) }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-eye mr-2"></i> View Details
                    </a>
                    <a href="{{ route('developer.maintenance.index') }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-list mr-2"></i> Back to List
                    </a>
                </div>

                @if($editable)
                    <div class="flex space-x-3">
                        <button type="button" onclick="showPreview()"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-eye mr-2"></i> Preview Changes
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-save mr-2"></i> Update Maintenance
                        </button>
                    </div>
                @else
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-lock mr-1"></i> Editing disabled for {{ str_replace('_', ' ', $maintenance->status) }} maintenance
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>

{{-- ============================================================
     | CANCEL MODAL
     * ============================================================ --}}
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Maintenance
                </h3>
                <button type="button" onclick="closeModal('cancelModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This action cannot be undone. Users will be notified if this was a scheduled maintenance.
            </p>

            <form action="{{ route('developer.maintenance.cancel', $maintenance->id) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="cancellation_reason" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Cancellation Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3" maxlength="500"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Why are you cancelling this maintenance?"
                              required minlength="10"></textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('cancelModal')"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Keep Schedule
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-times mr-2"></i> Cancel Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     | PREVIEW MODAL
     * ============================================================ --}}
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-eye mr-2" style="color: var(--primary);"></i> Changes Preview
                </h3>
                <button type="button" onclick="closeModal('previewModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="previewContent" class="space-y-4"></div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('previewModal')"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    Back to Editing
                </button>
                <button type="button" onclick="submitForm()"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-check mr-2"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Range slider */
    input[type="range"] {
        -webkit-appearance: none;
        height: 6px;
        border-radius: 3px;
        outline: none;
    }
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: var(--primary);
        cursor: pointer;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    input[type="range"]::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: var(--primary);
        cursor: pointer;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Badge base */
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Disabled fields */
    input:disabled, select:disabled, textarea:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        background-color: rgba(var(--text-secondary), 0.05);
    }

    /* Focus ring */
    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    }

    /* Card hover */
    .card {
        transition: box-shadow 0.2s ease;
    }
    .card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    }

    @media (max-width: 768px) {
        .card { border-radius: 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const ORIGINAL = {
        title:                     @json($maintenance->title),
        description:               @json($maintenance->description),
        user_impact_description:   @json($maintenance->user_impact_description),
        scheduled_start:           @json(optional($maintenance->scheduled_start)->format('Y-m-d\TH:i')),
        scheduled_end:             @json(optional($maintenance->scheduled_end)->format('Y-m-d\TH:i')),
        estimated_duration_minutes:{{ (int) $maintenance->estimated_duration_minutes }},
    };

    /* ---------- Init ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        updateCounters();
        bindCounters();
        bindDuration();
        bindDates();
        updateSummary();

        if (document.getElementById('impact_level')) {
            updateImpactDescription();
        }
    });

    /* ---------- Counters ---------- */
    function updateCounters() {
        const notes = document.getElementById('update_notes');
        const desc  = document.getElementById('description');
        const imp   = document.getElementById('user_impact_description');

        if (notes) document.getElementById('notesCounter').textContent = notes.value.length + '/500';
        if (desc)  document.getElementById('descriptionCounter').textContent = desc.value.length + '/500';
        if (imp)   document.getElementById('impactCounter').textContent = imp.value.length + '/500';
    }

    function bindCounters() {
        const notes = document.getElementById('update_notes');
        if (notes) {
            notes.addEventListener('input', function () {
                document.getElementById('notesCounter').textContent = this.value.length + '/500';
            });
        }

        const desc = document.getElementById('description');
        if (desc) {
            desc.addEventListener('input', function () {
                document.getElementById('descriptionCounter').textContent = this.value.length + '/500';
                updateSummary();
            });
        }

        const imp = document.getElementById('user_impact_description');
        if (imp) {
            imp.addEventListener('input', function () {
                document.getElementById('impactCounter').textContent = this.value.length + '/500';
                updateSummary();
            });
        }

        const title = document.getElementById('title');
        if (title) title.addEventListener('input', updateSummary);
    }

    /* ---------- Duration ---------- */
    window.setDuration = function (minutes) {
        const slider = document.getElementById('duration_slider');
        const input  = document.getElementById('estimated_duration_minutes');
        if (!slider || !input) return;

        slider.value = minutes;
        input.value = minutes;
        syncEndFromDuration();
        updateSummary();
    };

    function bindDuration() {
        const slider = document.getElementById('duration_slider');
        const input  = document.getElementById('estimated_duration_minutes');
        if (!slider || !input) return;

        slider.addEventListener('input', function () {
            input.value = this.value;
            syncEndFromDuration();
            updateSummary();
        });

        input.addEventListener('input', function () {
            slider.value = this.value;
            syncEndFromDuration();
            updateSummary();
        });
    }

    /* ---------- Dates ---------- */
    function bindDates() {
        const start = document.getElementById('scheduled_start');
        const end   = document.getElementById('scheduled_end');
        if (!start || !end) return;

        start.addEventListener('change', function () {
            end.min = this.value;
            syncDurationFromDates();
            updateSummary();
        });

        end.addEventListener('change', function () {
            syncDurationFromDates();
            updateSummary();
        });
    }

    function syncDurationFromDates() {
        const start = document.getElementById('scheduled_start');
        const end   = document.getElementById('scheduled_end');
        const input = document.getElementById('estimated_duration_minutes');
        const slider= document.getElementById('duration_slider');
        if (!start || !end || !input || !slider || !start.value || !end.value) return;

        const minutes = Math.round((new Date(end.value) - new Date(start.value)) / 60000);
        if (minutes > 0) {
            input.value = minutes;
            slider.value = minutes;
        }
    }

    function syncEndFromDuration() {
        const start = document.getElementById('scheduled_start');
        const end   = document.getElementById('scheduled_end');
        const input = document.getElementById('estimated_duration_minutes');
        if (!start || !end || !input || !start.value || !input.value) return;

        const duration = parseInt(input.value, 10) || 0;
        const endTime  = new Date(new Date(start.value).getTime() + duration * 60000);
        // timezone-safe datetime-local format
        const offset = endTime.getTimezoneOffset() * 60000;
        end.value = new Date(endTime.getTime() - offset).toISOString().slice(0, 16);
    }

    /* ---------- Impact description ---------- */
    function updateImpactDescription() {
        const select = document.getElementById('impact_level');
        const el     = document.getElementById('impactDescription');
        if (!select || !el) return;

        const descriptions = {
            low:      'Minimal impact. Most features remain available.',
            medium:   'Moderate impact. Some features will be unavailable.',
            high:     'Significant impact. Many features will be unavailable.',
            critical: 'Maximum impact. System mostly unavailable.',
        };

        const level = select.value;
        if (!descriptions[level]) { el.innerHTML = ''; return; }

        const color = {
            low: 'var(--success)',
            medium: 'var(--warning)',
            high: 'var(--warning)',
            critical: 'var(--danger)',
        }[level];

        const bg = {
            low: 'rgba(var(--success-rgb), 0.05)',
            medium: 'rgba(var(--warning-rgb), 0.05)',
            high: 'rgba(var(--warning-rgb), 0.05)',
            critical: 'rgba(var(--danger-rgb), 0.05)',
        }[level];

        el.innerHTML = `
            <div class="flex items-start p-3 rounded-lg" style="background-color:${bg}; border-left:4px solid ${color};">
                <i class="fas fa-info-circle mt-0.5 mr-2" style="color:${color};"></i>
                <span style="color: var(--text-secondary);">${descriptions[level]}</span>
            </div>`;
    }

    /* ---------- Summary ---------- */
    function updateSummary() {
        const title = document.getElementById('title')?.value || '';
        const start = document.getElementById('scheduled_start')?.value;
        const dur   = document.getElementById('estimated_duration_minutes')?.value || 0;

        const summaryStart = document.getElementById('summaryStart');
        if (summaryStart && start) {
            summaryStart.textContent = new Date(start).toLocaleString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        }

        const summaryDuration = document.getElementById('summaryDuration');
        if (summaryDuration) summaryDuration.textContent = dur + ' minutes';
    }

    /* ---------- Modal helpers ---------- */
    window.openCancelModal = function () {
        document.getElementById('cancelModal').classList.remove('hidden');
    };

    window.closeModal = function (id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    };

    /* ---------- Preview ---------- */
    window.showPreview = function () {
        if (!validateForm()) return;

        const current = {
            title:       document.getElementById('title')?.value || '',
            description: document.getElementById('description')?.value || '',
            start:       document.getElementById('scheduled_start')?.value || '',
            end:         document.getElementById('scheduled_end')?.value || '',
            duration:    document.getElementById('estimated_duration_minutes')?.value || '',
            impact:      document.getElementById('user_impact_description')?.value || '',
            notes:       document.getElementById('update_notes')?.value || '',
        };

        const rows = [
            ['Title',                     ORIGINAL.title,                current.title],
            ['Description',               ORIGINAL.description,          current.description],
            ['Start Date',                ORIGINAL.scheduled_start,      current.start],
            ['End Date',                  ORIGINAL.scheduled_end,        current.end],
            ['Duration (min)',            String(ORIGINAL.estimated_duration_minutes), current.duration],
            ['User Impact Description',   ORIGINAL.user_impact_description, current.impact],
        ];

        const html = `
            <div class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background-color: var(--bg-secondary);">
                                <th class="p-3 text-left" style="color: var(--text-secondary);">Field</th>
                                <th class="p-3 text-left" style="color: var(--text-secondary);">Current</th>
                                <th class="p-3 text-left" style="color: var(--text-secondary);">New</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(([field, before, after]) => {
                                const changed = String(before) !== String(after);
                                return `
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td class="p-3" style="color: var(--text-primary);">${escapeHtml(field)}</td>
                                        <td class="p-3" style="color: var(--text-secondary);">${escapeHtml(before || '—')}</td>
                                        <td class="p-3" style="color: ${changed ? 'var(--success)' : 'var(--text-primary)'}; font-weight: ${changed ? 500 : 400};">
                                            ${escapeHtml(after || '—')}
                                        </td>
                                    </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
                <div>
                    <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Update Notes</h5>
                    <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <p class="text-sm whitespace-pre-line">${escapeHtml(current.notes || 'No notes provided')}</p>
                    </div>
                </div>
                <div class="p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            These changes will be logged in the maintenance history.
                        </p>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('previewContent').innerHTML = html;
        document.getElementById('previewModal').classList.remove('hidden');
    };

    /* ---------- Validation ---------- */
    function validateForm() {
        const notes = document.getElementById('update_notes');
        if (notes && notes.value.trim().length < 10) {
            alert('Update notes are required and must be at least 10 characters.');
            notes.focus();
            return false;
        }

        const start = document.getElementById('scheduled_start');
        const end   = document.getElementById('scheduled_end');
        if (start && end && start.value && end.value && new Date(end.value) <= new Date(start.value)) {
            alert('End time must be after start time.');
            end.focus();
            return false;
        }

        return true;
    }

    window.submitForm = function () {
        if (validateForm()) {
            document.getElementById('maintenanceForm').submit();
        }
    };

    /* ---------- Escape helper ---------- */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    /* ---------- Global key handlers ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal('previewModal');
            closeModal('cancelModal');
        }
    });

    document.querySelectorAll('.fixed.inset-0').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });
})();
</script>
@endpush