@extends('layouts.dev')

@section('title', 'Schedule New Maintenance')

@section('content')
<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            <i class="fas fa-calendar-plus mr-2" style="color: var(--primary);"></i> Schedule New Maintenance
        </h1>
        <p class="text-sm" style="color: var(--text-secondary);">
            Plan and schedule system maintenance with detailed impact analysis and user notifications.
        </p>
    </div>

    {{-- Progress Steps --}}
    <div class="card mb-8" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center bg-blue-500 text-white mr-3">
                        <i class="fas fa-pencil-alt text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold" style="color: var(--text-primary);">Maintenance Planning</h3>
                        <p class="text-xs" style="color: var(--text-secondary);">Step 1 of 4: Basic Information</p>
                    </div>
                </div>

                <div class="flex space-x-4">
                    @foreach([
                        ['n' => 1, 'label' => 'Plan',    'active' => true],
                        ['n' => 2, 'label' => 'Schedule','active' => false],
                        ['n' => 3, 'label' => 'Impact',  'active' => false],
                        ['n' => 4, 'label' => 'Review',  'active' => false],
                    ] as $step)
                        <div class="text-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold mb-1"
                                 style="
                                    background-color: {{ $step['active'] ? 'rgba(var(--primary-rgb), 0.1)' : 'rgba(var(--text-secondary), 0.1)' }};
                                    color: {{ $step['active'] ? 'var(--primary)' : 'var(--text-secondary)' }};
                                    border: 2px solid {{ $step['active'] ? 'var(--primary)' : 'var(--border-color)' }};">
                                {{ $step['n'] }}
                            </div>
                            <span class="text-xs" style="color: {{ $step['active'] ? 'var(--primary)' : 'var(--text-secondary)' }};">
                                {{ $step['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="h-2 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                <div class="h-full rounded-full" style="background-color: var(--primary); width: 25%;"></div>
            </div>
        </div>
    </div>

    <form action="{{ route('developer.maintenance.store') }}" method="POST" id="maintenanceForm">
        @csrf

        {{-- ============================================================
             | BASIC INFORMATION
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Basic Information
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Provide essential details about this maintenance event
                </p>
            </div>

            <div class="p-6 space-y-6">
                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Maintenance Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title"
                           value="{{ old('title') }}"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Database Optimization, Security Patch Update, System Upgrade"
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
                    <textarea id="description" name="description" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Provide a brief description of what this maintenance entails..."
                              required minlength="20" maxlength="500">{{ old('description') }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">Minimum 20 characters</div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="descriptionCounter">
                            {{ strlen(old('description') ?? '') }}/500
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
                              placeholder="Technical specifications, database changes, API modifications, etc.">{{ old('technical_details') }}</textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Internal use only — not shown to users
                    </div>
                </div>

                {{-- Maintenance Type + Impact --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Maintenance Type --}}
                    <div>
                        <label for="maintenance_type" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Maintenance Type <span class="text-red-500">*</span>
                        </label>
                        <select id="maintenance_type" name="maintenance_type"
                                class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">Select Type</option>
                            @foreach($maintenanceTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('maintenance_type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('maintenance_type')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror

                        {{-- Emergency sub-fields --}}
                        <div id="emergencyOptions"
                             class="mt-4 p-4 rounded-lg border {{ old('is_emergency') ? '' : 'hidden' }}"
                             style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-start">
                                <input type="checkbox" id="is_emergency" name="is_emergency" value="1"
                                       {{ old('is_emergency') ? 'checked' : '' }}
                                       class="h-4 w-4 mt-1 rounded"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="ml-3 flex-1">
                                    <label for="is_emergency" class="text-sm font-medium" style="color: var(--warning);">
                                        Emergency Maintenance
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Check this if this is unplanned emergency maintenance
                                    </p>

                                    <div id="emergencyFields" class="mt-3 space-y-3 {{ old('is_emergency') ? '' : 'hidden' }}">
                                        <div>
                                            <label for="emergency_reason" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                Emergency Reason <span class="text-red-500">*</span>
                                            </label>
                                            <textarea id="emergency_reason" name="emergency_reason" rows="2"
                                                      class="w-full p-2 text-sm border rounded"
                                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                      placeholder="Why is this emergency maintenance necessary? (min 10 chars)">{{ old('emergency_reason') }}</textarea>
                                            @error('emergency_reason')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="related_emergency_id" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                Link to Active Emergency <span class="text-xs">(Optional)</span>
                                            </label>
                                            <select id="related_emergency_id" name="related_emergency_id"
                                                    class="w-full p-2 text-sm border rounded"
                                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="">No Emergency Link</option>
                                                @foreach($activeEmergencies as $emergency)
                                                    <option value="{{ $emergency->id }}" {{ old('related_emergency_id') == $emergency->id ? 'selected' : '' }}>
                                                        {{ $emergency->reference_id }} — {{ $emergency->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Impact Level --}}
                    <div>
                        <label for="impact_level" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Impact Level <span class="text-red-500">*</span>
                        </label>
                        <select id="impact_level" name="impact_level"
                                class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">Select Impact Level</option>
                            @foreach($impactLevels as $value => $label)
                                <option value="{{ $value }}" {{ old('impact_level') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('impact_level')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 space-y-2">
                            <div class="text-xs" id="impactDescription"></div>
                        </div>
                    </div>
                </div>
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
                    Set the maintenance window and estimated duration
                </p>
            </div>

            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Scheduled Start --}}
                    <div>
                        <label for="scheduled_start" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Start Date &amp; Time <span class="text-red-500">*</span>
                        </label>
                        <input type="datetime-local" id="scheduled_start" name="scheduled_start"
                               value="{{ old('scheduled_start') }}"
                               class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Must be in the future</div>
                        @error('scheduled_start')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Scheduled End --}}
                    <div>
                        <label for="scheduled_end" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            End Date &amp; Time <span class="text-red-500">*</span>
                        </label>
                        <input type="datetime-local" id="scheduled_end" name="scheduled_end"
                               value="{{ old('scheduled_end') }}"
                               class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Must be after start time</div>
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
                    <div class="flex items-center space-x-4">
                        <input type="range" id="duration_slider" name="duration_slider"
                               min="1" max="1440" value="{{ old('estimated_duration_minutes', 60) }}"
                               class="flex-1 h-2 rounded-lg appearance-none cursor-pointer"
                               style="background-color: var(--border-color);">
                        <input type="number" id="estimated_duration_minutes" name="estimated_duration_minutes"
                               value="{{ old('estimated_duration_minutes', 60) }}"
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

                    @error('estimated_duration_minutes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Timezone Info --}}
                <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-globe-americas mr-2" style="color: var(--info);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            All times are displayed in <span class="font-medium">{{ config('app.timezone') }}</span> timezone
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
                    Define what will be affected during maintenance
                </p>
            </div>

            <div class="p-6 space-y-6">
                {{-- User Impact Description --}}
                <div>
                    <label for="user_impact_description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        User Impact Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="user_impact_description" name="user_impact_description" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Describe the impact from the user's perspective. What will they experience?"
                              required minlength="20" maxlength="500">{{ old('user_impact_description') }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Minimum 20 characters — This will be shown to users
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="impactCounter">
                            {{ strlen(old('user_impact_description') ?? '') }}/500
                        </div>
                    </div>
                    @error('user_impact_description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Affected Modules --}}
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected Modules <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($defaultModules as $value => $label)
                            <div class="flex items-center">
                                <input type="checkbox" id="module_{{ $value }}" name="affected_modules[]"
                                       value="{{ $value }}"
                                       {{ in_array($value, old('affected_modules', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="module_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('affected_modules')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" onclick="selectAllModules()"
                                class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-check-square mr-1"></i> Select All
                        </button>
                        <button type="button" onclick="deselectAllModules()"
                                class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-times-circle mr-1"></i> Deselect All
                        </button>
                        <button type="button" onclick="selectCriticalModules()"
                                class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Critical Only
                        </button>
                    </div>
                </div>

                {{-- Affected User Types --}}
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected User Types <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($userTypes as $value => $label)
                            <div class="flex items-center">
                                <input type="checkbox" id="user_type_{{ $value }}" name="affected_user_types[]"
                                       value="{{ $value }}"
                                       data-user-type="{{ $value }}"
                                       {{ in_array((string) $value, old('affected_user_types', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="user_type_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('affected_user_types')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Selected user types: <span id="estimatedUsersCount">0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | NOTIFICATION SETTINGS
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2" style="color: var(--primary);"></i> Notification Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure how users will be notified about this maintenance
                </p>
            </div>

            <div class="p-6 space-y-6">
                {{-- Notify Users --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_users" name="notify_users" value="1"
                               {{ old('notify_users', true) ? 'checked' : '' }}
                               class="h-4 w-4 rounded focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="notify_users" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            Notify users about this maintenance
                        </label>
                    </div>
                    <div class="text-xs px-2 py-1 rounded"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        Recommended
                    </div>
                </div>

                {{-- Channels --}}
                <div id="notificationChannels" class="{{ old('notify_users', true) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Notification Channels
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        @php
                            $channels = [
                                'email'    => ['label' => 'Email',    'icon' => 'fa-envelope',    'default' => true],
                                'sms'      => ['label' => 'SMS',      'icon' => 'fa-comment-alt', 'default' => false],
                                'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp','default' => false],
                                'in_app'   => ['label' => 'In-App',   'icon' => 'fa-mobile-alt',  'default' => true],
                            ];
                            $oldChannels = old('notification_channels', ['email', 'in_app']);
                        @endphp
                        @foreach($channels as $key => $meta)
                            <div class="flex items-center">
                                <input type="checkbox" id="channel_{{ $key }}" name="notification_channels[]"
                                       value="{{ $key }}"
                                       {{ in_array($key, $oldChannels) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="channel_{{ $key }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    <i class="{{ $meta['icon'] }} mr-1"></i> {{ $meta['label'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('notification_channels')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    {{-- Preview --}}
                    <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                        <h4 class="text-sm font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-eye mr-2"></i> Notification Preview
                        </h4>
                        <div class="text-sm space-y-2" style="color: var(--text-secondary);">
                            <div><span class="font-medium">Title:</span> <span id="previewTitle">System Maintenance</span></div>
                            <div><span class="font-medium">Schedule:</span> <span id="previewSchedule">Not set</span></div>
                            <div><span class="font-medium">Impact:</span> <span id="previewImpact">Not specified</span></div>
                            <div><span class="font-medium">Channels:</span> <span id="previewChannels">Email, In-App</span></div>
                        </div>
                    </div>
                </div>

                {{-- Rollback Plan --}}
                <div>
                    <div class="flex items-center">
                        <input type="checkbox" id="has_rollback_plan" name="has_rollback_plan" value="1"
                               {{ old('has_rollback_plan') ? 'checked' : '' }}
                               class="h-4 w-4 rounded focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="has_rollback_plan" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            Has rollback plan
                        </label>
                    </div>
                    <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                        Check this if there's a documented plan to rollback changes in case of issues.
                    </p>
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
                    Review all information before submitting
                </p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Maintenance Details</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Title:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryTitle">
                                    {{ old('title') ?: 'Not set' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Type:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryType">
                                    {{ old('maintenance_type') ? ucfirst(old('maintenance_type')) : 'Not set' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Impact Level:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryImpact">
                                    {{ old('impact_level') ? ucfirst(old('impact_level')) : 'Not set' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Modules:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryModules">
                                    0 modules
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
                                    {{ old('scheduled_start') ? date('M d, Y H:i', strtotime(old('scheduled_start'))) : 'Not set' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Duration:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryDuration">
                                    {{ old('estimated_duration_minutes', 60) }} minutes
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Affected User Types:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryUsers">0 selected</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 rounded-lg border"
                     style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-medium mb-2" style="color: var(--warning);">Important Notice</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                After creating this maintenance schedule, it will need to be approved before it becomes active.
                                You will be able to manage and monitor it from the maintenance dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | ACTIONS
             * ============================================================ --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 flex justify-between items-center flex-wrap gap-3">
                <a href="{{ route('developer.maintenance.index') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                <div class="flex space-x-3">
                    <button type="button" onclick="resetForm()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-save mr-2"></i> Create Maintenance Schedule
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    /* Range slider thumb styling */
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

    /* Focus ring */
    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    }

    /* Checkbox accent */
    input[type="checkbox"] {
        transition: all 0.2s ease;
    }
    input[type="checkbox"]:checked {
        background-color: var(--primary);
        border-color: var(--primary);
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

    /* ---------- Config from server ---------- */
    const DEFAULT_MODULE_LABELS = @json($defaultModules);
    const USER_TYPE_LABELS      = @json($userTypes);

    /* ---------- DOM ready ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        updateCounters();
        setupEventListeners();
        updateSummary();
        updateNotificationPreview();
        updateSelectedUsersCount();
    });

    /* ---------- Event listeners ---------- */
    function setupEventListeners() {
        // Description counter
        const descriptionEl = document.getElementById('description');
        if (descriptionEl) {
            descriptionEl.addEventListener('input', function () {
                document.getElementById('descriptionCounter').textContent = this.value.length + '/500';
                updateSummary();
            });
        }

        // Impact description counter
        const impactEl = document.getElementById('user_impact_description');
        if (impactEl) {
            impactEl.addEventListener('input', function () {
                document.getElementById('impactCounter').textContent = this.value.length + '/500';
                updateSummary();
            });
        }

        // Emergency checkbox toggles sub-fields
        const emergencyCheckbox = document.getElementById('is_emergency');
        const emergencyFields   = document.getElementById('emergencyFields');
        if (emergencyCheckbox && emergencyFields) {
            emergencyCheckbox.addEventListener('change', function () {
                emergencyFields.classList.toggle('hidden', !this.checked);
            });
        }

        // Impact level → description + summary
        const impactLevelSelect = document.getElementById('impact_level');
        if (impactLevelSelect) {
            impactLevelSelect.addEventListener('change', function () {
                updateImpactDescription();
                updateSummary();
                updateNotificationPreview();
            });
            updateImpactDescription();
        }

        // Maintenance type → toggle emergency block
        const maintenanceTypeSelect = document.getElementById('maintenance_type');
        const emergencyOptions      = document.getElementById('emergencyOptions');
        if (maintenanceTypeSelect && emergencyOptions) {
            maintenanceTypeSelect.addEventListener('change', function () {
                const isEmergency = this.value === 'emergency';
                emergencyOptions.classList.toggle('hidden', !isEmergency);

                const checkbox = document.getElementById('is_emergency');
                if (checkbox) {
                    checkbox.checked = isEmergency;
                    emergencyFields?.classList.toggle('hidden', !isEmergency);
                }
                updateSummary();
                updateNotificationPreview();
            });
        }

        // Date / time sync
        const startInput = document.getElementById('scheduled_start');
        const endInput   = document.getElementById('scheduled_end');
        if (startInput && endInput) {
            const now = new Date();
            startInput.min = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

            startInput.addEventListener('change', function () {
                endInput.min = this.value;
                syncDurationFromDates();
                updateSummary();
                updateNotificationPreview();
            });

            endInput.addEventListener('change', function () {
                syncDurationFromDates();
                updateSummary();
                updateNotificationPreview();
            });
        }

        // Duration slider ↔ input
        const durationSlider = document.getElementById('duration_slider');
        const durationInput  = document.getElementById('estimated_duration_minutes');
        if (durationSlider && durationInput) {
            durationSlider.addEventListener('input', function () {
                durationInput.value = this.value;
                syncEndFromDuration();
                updateSummary();
                updateNotificationPreview();
            });
            durationInput.addEventListener('input', function () {
                durationSlider.value = this.value;
                syncEndFromDuration();
                updateSummary();
                updateNotificationPreview();
            });
        }

        // Notify users toggle
        const notifyCheckbox = document.getElementById('notify_users');
        const channelsDiv    = document.getElementById('notificationChannels');
        if (notifyCheckbox && channelsDiv) {
            notifyCheckbox.addEventListener('change', function () {
                channelsDiv.classList.toggle('hidden', !this.checked);
                updateNotificationPreview();
            });
        }

        // Channel checkboxes
        document.querySelectorAll('input[name="notification_channels[]"]').forEach(cb => {
            cb.addEventListener('change', updateNotificationPreview);
        });

        // Any form input affects summary
        document.querySelectorAll('#maintenanceForm input, #maintenanceForm select, #maintenanceForm textarea').forEach(el => {
            if (!el.type || !['checkbox', 'radio'].includes(el.type)) {
                el.addEventListener('input', updateSummary);
            }
        });

        // Affected user type checkboxes → count
        document.querySelectorAll('input[name="affected_user_types[]"]').forEach(cb => {
            cb.addEventListener('change', updateSelectedUsersCount);
        });

        // Affected module checkboxes → summary
        document.querySelectorAll('input[name="affected_modules[]"]').forEach(cb => {
            cb.addEventListener('change', updateSummary);
        });
    }

    /* ---------- Counters ---------- */
    function updateCounters() {
        const d = document.getElementById('description');
        const i = document.getElementById('user_impact_description');

        if (d) document.getElementById('descriptionCounter').textContent = d.value.length + '/500';
        if (i) document.getElementById('impactCounter').textContent = i.value.length + '/500';
    }

    /* ---------- Impact description ---------- */
    function updateImpactDescription() {
        const level = document.getElementById('impact_level').value;
        const el    = document.getElementById('impactDescription');

        const descriptions = {
            low:      'Minimal impact. Most features remain available. Users may experience minor performance issues.',
            medium:   'Moderate impact. Some features will be unavailable. Users should plan accordingly.',
            high:     'Significant impact. Many features will be unavailable. Consider rescheduling critical tasks.',
            critical: 'Maximum impact. System will be mostly unavailable. Essential operations only.',
        };

        if (!descriptions[level]) {
            el.innerHTML = '';
            return;
        }

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

    /* ---------- Duration helpers ---------- */
    window.setDuration = function (minutes) {
        document.getElementById('duration_slider').value = minutes;
        document.getElementById('estimated_duration_minutes').value = minutes;
        syncEndFromDuration();
        updateSummary();
        updateNotificationPreview();
    };

    function syncDurationFromDates() {
        const start = document.getElementById('scheduled_start').value;
        const end   = document.getElementById('scheduled_end').value;
        if (!start || !end) return;

        const minutes = Math.round((new Date(end) - new Date(start)) / 60000);
        if (minutes > 0) {
            document.getElementById('estimated_duration_minutes').value = minutes;
            document.getElementById('duration_slider').value = minutes;
        }
    }

    function syncEndFromDuration() {
        const start    = document.getElementById('scheduled_start').value;
        const duration = parseInt(document.getElementById('estimated_duration_minutes').value, 10) || 0;
        if (!start || duration <= 0) return;

        const end = new Date(new Date(start).getTime() + duration * 60000);
        document.getElementById('scheduled_end').value =
            new Date(end.getTime() - end.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    }

    /* ---------- Module quick-select ---------- */
    window.selectAllModules = function () {
        document.querySelectorAll('input[name="affected_modules[]"]').forEach(cb => cb.checked = true);
        updateSummary();
    };
    window.deselectAllModules = function () {
        document.querySelectorAll('input[name="affected_modules[]"]').forEach(cb => cb.checked = false);
        updateSummary();
    };
    window.selectCriticalModules = function () {
        const critical = ['user_management', 'authentication', 'database', 'payment_processing'];
        document.querySelectorAll('input[name="affected_modules[]"]').forEach(cb => {
            cb.checked = critical.includes(cb.value);
        });
        updateSummary();
    };

    /* ---------- Selected users count ---------- */
    function updateSelectedUsersCount() {
        const count = document.querySelectorAll('input[name="affected_user_types[]"]:checked').length;
        document.getElementById('estimatedUsersCount').textContent = count;
        document.getElementById('summaryUsers').textContent = count + ' selected';
    }

    /* ---------- Summary ---------- */
    function updateSummary() {
        const title = document.getElementById('title').value || 'Not set';
        document.getElementById('summaryTitle').textContent = title;
        document.getElementById('previewTitle').textContent = title;

        const typeSelect = document.getElementById('maintenance_type');
        document.getElementById('summaryType').textContent =
            typeSelect.options[typeSelect.selectedIndex]?.text || 'Not set';

        const impactSelect = document.getElementById('impact_level');
        const impactLabel  = impactSelect.options[impactSelect.selectedIndex]?.text || 'Not set';
        document.getElementById('summaryImpact').textContent = impactLabel;
        document.getElementById('previewImpact').textContent = impactLabel;

        const start    = document.getElementById('scheduled_start').value;
        const duration = document.getElementById('estimated_duration_minutes').value;

        if (start) {
            const formatted = new Date(start).toLocaleString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
            document.getElementById('summaryStart').textContent = formatted;
            document.getElementById('previewSchedule').textContent = `${formatted} for ${duration} minutes`;
        } else {
            document.getElementById('summaryStart').textContent = 'Not set';
            document.getElementById('previewSchedule').textContent = 'Not set';
        }

        document.getElementById('summaryDuration').textContent = duration + ' minutes';

        const moduleCount = document.querySelectorAll('input[name="affected_modules[]"]:checked').length;
        document.getElementById('summaryModules').textContent = moduleCount + ' modules';
    }

    /* ---------- Notification preview ---------- */
    function updateNotificationPreview() {
        const notify = document.getElementById('notify_users').checked;
        const labels = Array.from(document.querySelectorAll('input[name="notification_channels[]"]:checked'))
            .map(cb => cb.nextElementSibling?.textContent?.trim() || cb.value)
            .map(t => t.replace(/^\s*\S+\s+/, '')); // strip leading icon name

        document.getElementById('previewChannels').textContent =
            notify ? (labels.join(', ') || 'None selected') : 'No notifications';
    }

    /* ---------- Reset ---------- */
    window.resetForm = function () {
        if (!confirm('Are you sure you want to reset the form? All entered data will be lost.')) return;

        document.getElementById('maintenanceForm').reset();
        updateCounters();
        updateSummary();
        updateNotificationPreview();
        updateSelectedUsersCount();
        updateImpactDescription();

        const emergencyOptions = document.getElementById('emergencyOptions');
        const emergencyFields  = document.getElementById('emergencyFields');
        emergencyOptions?.classList.add('hidden');
        emergencyFields?.classList.add('hidden');

        document.getElementById('notificationChannels').classList.remove('hidden');
    };

    /* ---------- Native validation then submit ---------- */
    window.submitForm = function () {
        const form = document.getElementById('maintenanceForm');
        if (form.reportValidity()) form.submit();
    };

    // Ctrl+Enter submits
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            submitForm();
        }
    });
})();
</script>
@endpush