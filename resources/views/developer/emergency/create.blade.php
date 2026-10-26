@extends('layouts.dev')

@section('title', 'Create New Emergency Mode')

@section('content')
@php
    use App\Models\EmergencyMode;

    $defaultAllowedOps = ['view_dashboard', 'basic_read'];
    $defaultChannels   = ['email', 'in_app'];
@endphp

<div class="max-w-6xl mx-auto">

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Create New Emergency Mode
        </h1>
        <p class="text-sm" style="color: var(--text-secondary);">
            Configure emergency response procedures to handle system incidents and disruptions.
        </p>
    </div>

    <form action="{{ route('developer.emergency.store') }}" method="POST" id="emergencyForm">
        @csrf

        {{-- ============================================================
             | BASIC INFORMATION
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--danger);"></i> Basic Information
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Provide essential details about this emergency mode
                </p>
            </div>

            <div class="p-6 space-y-6">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Emergency Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name"
                           value="{{ old('name') }}"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Security Breach Response, System Outage Protocol"
                           required maxlength="255">
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Description <span class="text-xs">(Optional)</span>
                    </label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Detailed description of this emergency mode configuration...">{{ old('description') }}</textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Internal documentation only
                    </div>
                </div>

                {{-- Reason --}}
                <div>
                    <label for="reason" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Emergency Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="reason" name="reason" rows="3" maxlength="500"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Explain why this emergency mode is necessary..."
                              required minlength="10">{{ old('reason') }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">Minimum 10 characters</div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="reasonCounter">
                            {{ strlen(old('reason') ?? '') }}/500
                        </div>
                    </div>
                    @error('reason')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Severity Level --}}
                <div>
                    <label for="severity_level" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Severity Level <span class="text-red-500">*</span>
                    </label>
                    <select id="severity_level" name="severity_level"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Severity Level</option>
                        @foreach($severityLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('severity_level') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('severity_level')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="mt-4">
                        <div class="text-xs" id="severityDescription"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | SYSTEM IMPACT
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-cogs mr-2" style="color: var(--danger);"></i> System Impact
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Define which system modules will be affected
                </p>
            </div>

            <div class="p-6">
                {{-- Affected Modules --}}
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected Modules <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($defaultModules as $value => $label)
                            <div class="flex items-center p-2 rounded-lg border transition-colors hover:border-red-300"
                                 style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <input type="checkbox" id="module_{{ $value }}" name="affected_modules[]"
                                       value="{{ $value }}"
                                       {{ in_array($value, old('affected_modules', []), true) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="module_{{ $value }}" class="ml-2 text-sm flex-1" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                                @if(!empty($moduleDescriptions[$value] ?? null))
                                    <i class="fas fa-info-circle text-xs"
                                       style="color: var(--text-secondary);"
                                       title="{{ $moduleDescriptions[$value] }}"
                                       aria-label="{{ $moduleDescriptions[$value] }}"></i>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @error('affected_modules')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" onclick="EmergencyCreate.selectAllModules()"
                                class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-check-square mr-1"></i> Select All
                        </button>
                        <button type="button" onclick="EmergencyCreate.deselectAllModules()"
                                class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-times-circle mr-1"></i> Deselect All
                        </button>
                        <button type="button" onclick="EmergencyCreate.selectCriticalModules()"
                                class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Critical Systems Only
                        </button>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Restricted Features --}}
                    <div>
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Restricted Features <span class="text-xs">(Optional)</span>
                        </label>
                        <div class="space-y-2">
                            @php
                                $restrictedFeatures = [
                                    'user_registration'    => 'Disable User Registration',
                                    'property_creation'    => 'Disable Property Creation',
                                    'payment_processing'   => 'Disable Payment Processing',
                                    'email_notifications'  => 'Disable Non-Critical Emails',
                                    'api_write_operations' => 'Disable API Write Operations',
                                ];
                                $oldRestricted = old('restricted_features', []);
                            @endphp
                            @foreach($restrictedFeatures as $key => $label)
                                <div class="flex items-center">
                                    <input type="checkbox" id="restrict_{{ $key }}" name="restricted_features[]"
                                           value="{{ $key }}"
                                           {{ in_array($key, $oldRestricted, true) ? 'checked' : '' }}
                                           class="h-4 w-4 rounded focus:ring-red-500"
                                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <label for="restrict_{{ $key }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Allowed Operations --}}
                    <div>
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Allowed Operations <span class="text-xs">(Optional)</span>
                        </label>
                        <div class="space-y-2">
                            @php
                                $allowedOps = [
                                    'view_dashboard'  => 'View Dashboard',
                                    'basic_read'      => 'Basic Read Operations',
                                    'read_profile'    => 'Read User Profile',
                                    'read_only'       => 'Read-Only Access',
                                    'emergency_read'  => 'Emergency Read Access',
                                ];
                                $oldAllowed = old('allowed_operations', $defaultAllowedOps);
                            @endphp
                            @foreach($allowedOps as $key => $label)
                                <div class="flex items-center">
                                    <input type="checkbox" id="allow_{{ $key }}" name="allowed_operations[]"
                                           value="{{ $key }}"
                                           {{ in_array($key, $oldAllowed, true) ? 'checked' : '' }}
                                           class="h-4 w-4 rounded focus:ring-red-500"
                                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <label for="allow_{{ $key }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | ACTIVATION SETTINGS
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Activation Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure when and how this emergency mode should be activated
                </p>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Activation Method <span class="text-red-500">*</span>
                    </label>

                    <div class="space-y-4">
                        {{-- Immediate --}}
                        <div class="p-4 rounded-lg border transition-colors" id="immediateOption"
                             style="background-color: {{ old('activate_immediately') ? 'rgba(var(--warning-rgb), 0.05)' : 'var(--bg-secondary)' }};
                                    border-color: {{ old('activate_immediately') ? 'rgba(var(--warning-rgb), 0.3)' : 'var(--border-color)' }};">
                            <div class="flex items-start">
                                <input type="checkbox" id="activate_immediately" name="activate_immediately" value="1"
                                       {{ old('activate_immediately') ? 'checked' : '' }}
                                       class="h-4 w-4 mt-1 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <label for="activate_immediately" class="text-sm font-medium" style="color: var(--warning);">
                                            Activate Immediately
                                        </label>
                                        <span class="badge" style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                            Recommended for emergencies
                                        </span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Emergency mode will be activated immediately after saving. All system restrictions will be applied.
                                    </p>

                                    <div id="immediateWarning" class="mt-3 p-3 rounded-lg {{ old('activate_immediately') ? '' : 'hidden' }}"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                        <div class="flex">
                                            <i class="fas fa-exclamation-triangle mt-0.5 mr-2" style="color: var(--warning);"></i>
                                            <div>
                                                <h4 class="text-sm font-medium mb-1" style="color: var(--warning);">Warning</h4>
                                                <p class="text-xs" style="color: var(--text-secondary);">
                                                    This will immediately put the system into emergency mode. Users will be notified and system features will be restricted.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Scheduled --}}
                        <div class="p-4 rounded-lg border transition-colors" id="scheduledOption"
                             style="background-color: {{ old('schedule_for_later') ? 'rgba(var(--info-rgb), 0.05)' : 'var(--bg-secondary)' }};
                                    border-color: {{ old('schedule_for_later') ? 'rgba(var(--info-rgb), 0.3)' : 'var(--border-color)' }};">
                            <div class="flex items-start">
                                <input type="checkbox" id="schedule_for_later" name="schedule_for_later" value="1"
                                       {{ old('schedule_for_later') ? 'checked' : '' }}
                                       class="h-4 w-4 mt-1 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="ml-3 flex-1">
                                    <label for="schedule_for_later" class="text-sm font-medium" style="color: var(--info);">
                                        Schedule for Later
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Schedule emergency mode activation for a future date and time.
                                    </p>

                                    <div id="scheduleFields" class="mt-3 space-y-3 {{ old('schedule_for_later') ? '' : 'hidden' }}">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="scheduled_start" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                    Start Date &amp; Time <span class="text-red-500">*</span>
                                                </label>
                                                <input type="datetime-local" id="scheduled_start" name="scheduled_start"
                                                       value="{{ old('scheduled_start') }}"
                                                       class="w-full p-2 text-sm border rounded-lg"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                @error('scheduled_start')
                                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div>
                                                <label for="scheduled_end" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                    End Date &amp; Time <span class="text-xs">(Optional)</span>
                                                </label>
                                                <input type="datetime-local" id="scheduled_end" name="scheduled_end"
                                                       value="{{ old('scheduled_end') }}"
                                                       class="w-full p-2 text-sm border rounded-lg"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                @error('scheduled_end')
                                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Scheduled emergency modes can be cancelled before activation.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Inactive --}}
                        <div class="p-4 rounded-lg border" id="inactiveOption"
                             style="background-color: {{ !old('activate_immediately') && !old('schedule_for_later') ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-secondary)' }};
                                    border-color: {{ !old('activate_immediately') && !old('schedule_for_later') ? 'rgba(var(--primary-rgb), 0.3)' : 'var(--border-color)' }};">
                            <div class="flex items-start">
                                <div class="flex-1">
                                    <label class="text-sm font-medium" style="color: var(--primary);">
                                        Save as Inactive Configuration
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Save the configuration without activating it. You can activate it manually later.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Auto Recovery --}}
                    <div class="mt-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="auto_recovery" name="auto_recovery" value="1"
                                   {{ old('auto_recovery', true) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-red-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="auto_recovery" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                                Enable Auto-Recovery
                            </label>
                        </div>
                        <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                            System will run recovery checks before deactivating emergency mode
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | NOTIFICATIONS
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2" style="color: var(--danger);"></i> Notification Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure how users will be notified about this emergency
                </p>
            </div>

            <div class="p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_users" name="notify_users" value="1"
                               {{ old('notify_users', true) ? 'checked' : '' }}
                               class="h-4 w-4 rounded focus:ring-red-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="notify_users" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            Notify users about this emergency
                        </label>
                    </div>
                    <div class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        Recommended
                    </div>
                </div>

                <div id="notificationChannels" class="{{ old('notify_users', true) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Notification Channels
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        @php
                            $channelOptions = [
                                'email'    => ['label' => 'Email',    'icon' => 'fa-envelope'],
                                'sms'      => ['label' => 'SMS',      'icon' => 'fa-comment-alt'],
                                'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp'],
                                'in_app'   => ['label' => 'In-App',   'icon' => 'fa-mobile-alt'],
                            ];
                            $oldChannels = old('notification_channels', $defaultChannels);
                        @endphp
                        @foreach($channelOptions as $key => $meta)
                            <div class="flex items-center">
                                <input type="checkbox" id="channel_{{ $key }}" name="notification_channels[]"
                                       value="{{ $key }}"
                                       {{ in_array($key, $oldChannels, true) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="channel_{{ $key }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    <i class="{{ $meta['icon'] }} mr-1"></i> {{ $meta['label'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>

                    {{-- Preview --}}
                    <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                        <h4 class="text-sm font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-eye mr-2"></i> Notification Preview
                        </h4>
                        <div class="text-sm space-y-2" style="color: var(--text-secondary);">
                            <div><span class="font-medium">Title:</span> <span id="previewTitle">System Emergency</span></div>
                            <div><span class="font-medium">Severity:</span> <span id="previewSeverity">Not specified</span></div>
                            <div><span class="font-medium">Channels:</span> <span id="previewChannels">Email, In-App</span></div>
                            <div><span class="font-medium">Status:</span> <span id="previewStatus">Saving configuration</span></div>
                        </div>
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
                    <i class="fas fa-clipboard-check mr-2" style="color: var(--danger);"></i> Summary
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Review all information before submitting
                </p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Emergency Details</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Name:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryName">{{ old('name') ?: 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Severity:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summarySeverity">
                                    {{ old('severity_level') ? ucfirst(old('severity_level')) : 'Not set' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Modules:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryModules">0 selected</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Activation</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Method:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryMethod">
                                    @if(old('activate_immediately'))
                                        Immediate Activation
                                    @elseif(old('schedule_for_later'))
                                        Scheduled
                                    @else
                                        Inactive
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Auto-Recovery:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryRecovery">
                                    {{ old('auto_recovery', true) ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Notifications:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryNotifications">
                                    {{ old('notify_users', true) ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-medium mb-2" style="color: var(--warning);">Important Notice</h4>
                            <p class="text-sm" id="summaryNotice" style="color: var(--text-secondary);">
                                @if(old('activate_immediately'))
                                    <strong>This emergency mode will be activated immediately after saving.</strong> System restrictions will be applied and users will be notified.
                                @elseif(old('schedule_for_later'))
                                    This emergency mode is scheduled for activation. You can cancel it before the scheduled time.
                                @else
                                    This emergency mode will be saved as an inactive configuration. You can activate it manually when needed.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | FORM ACTIONS
             * ============================================================ --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 flex justify-between items-center flex-wrap gap-3">
                <a href="{{ route('developer.emergency.index') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                <div class="flex space-x-3 flex-wrap">
                    <button type="button" onclick="EmergencyCreate.resetForm()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-save mr-2"></i> Create Emergency Mode
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- ============================================================
     | CONFIRMATION MODAL — immediate activation
     * ============================================================ --}}
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="confirmationTitle">
    <div class="card w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-red-500 text-white mr-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 id="confirmationTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    Confirm Immediate Activation
                </h3>
            </div>

            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <p class="text-sm" style="color: var(--text-secondary);">
                    You are about to activate emergency mode immediately. This will:
                </p>
                <ul class="text-sm mt-2 space-y-1" style="color: var(--text-secondary);">
                    <li class="flex items-start">
                        <i class="fas fa-ban mt-0.5 mr-2 text-red-500"></i>
                        <span>Apply system restrictions to selected modules</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-bell mt-0.5 mr-2 text-red-500"></i>
                        <span>Notify affected users</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-2 text-red-500"></i>
                        <span>Potentially disrupt normal system operations</span>
                    </li>
                </ul>
            </div>

            <div class="flex justify-end space-x-2">
                <button type="button" onclick="EmergencyCreate.closeModal('confirmationModal')"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    Cancel
                </button>
                <button type="button" onclick="EmergencyCreate.submitForm()"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                        style="background-color: var(--danger); color: white;">
                    <i class="fas fa-bolt mr-2"></i> Confirm &amp; Activate
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.4px;
    }

    input[type="checkbox"] { transition: all 0.2s ease; }
    input[type="checkbox"]:checked {
        background-color: var(--danger);
        border-color: var(--danger);
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.15);
    }

    #immediateOption, #scheduledOption, #inactiveOption {
        transition: all 0.2s ease;
    }
    #immediateOption:hover,
    #scheduledOption:hover,
    #inactiveOption:hover {
        border-color: rgba(var(--primary-rgb), 0.3);
    }

    .card { transition: box-shadow 0.2s ease; }
    .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }

    @media (max-width: 768px) {
        .card { border-radius: 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---------- State ---------- */
    const DEFAULTS = {
        allowedOps: @json($defaultAllowedOps),
        channels:   @json($defaultChannels),
        criticalModules: ['user_management', 'authentication', 'payment_processing', 'database'],
    };

    /* ---------- Utils ---------- */
    function $(id) { return document.getElementById(id); }
    function $$(sel) { return document.querySelectorAll(sel); }

    function updateCounters() {
        const reason = $('reason');
        if (reason) {
            $('reasonCounter').textContent = reason.value.length + '/500';
        }
    }

    function updateSeverityDescription() {
        const select = $('severity_level');
        const el     = $('severityDescription');
        if (!select || !el) return;

        const descriptions = {
            low:      'Minor impact. Limited features affected. Users may experience reduced functionality.',
            medium:   'Moderate impact. Several features affected. Users should expect some disruption.',
            high:     'Significant impact. Major features affected. System performance will be degraded.',
            critical: 'Critical impact. System-wide disruption. Essential operations only.',
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

    function updateSummary() {
        const name = $('name')?.value || 'Not set';
        $('summaryName').textContent = name;
        $('previewTitle').textContent = name;

        const severitySelect = $('severity_level');
        const severityLabel = severitySelect?.options[severitySelect.selectedIndex]?.text || 'Not set';
        $('summarySeverity').textContent = severityLabel;
        $('previewSeverity').textContent = severityLabel;

        const moduleCount = $$('input[name="affected_modules[]"]:checked').length;
        $('summaryModules').textContent = moduleCount + ' selected';

        const immediate = $('activate_immediately')?.checked;
        const scheduled = $('schedule_for_later')?.checked;

        let method = 'Inactive';
        let status = 'Saving configuration';
        let notice = 'This emergency mode will be saved as an inactive configuration. You can activate it manually when needed.';

        if (immediate) {
            method = 'Immediate Activation';
            status = 'Activating immediately';
            notice = '<strong>This emergency mode will be activated immediately after saving.</strong> System restrictions will be applied and users will be notified.';
        } else if (scheduled) {
            const start = $('scheduled_start')?.value;
            if (start) {
                method = 'Scheduled for ' + new Date(start).toLocaleString('en-US', {
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
                });
            } else {
                method = 'Scheduled (no date)';
            }
            status = 'Scheduled activation';
            notice = 'This emergency mode is scheduled for activation. You can cancel it before the scheduled time.';
        }

        $('summaryMethod').textContent = method;
        $('previewStatus').textContent = status;
        $('summaryNotice').innerHTML = notice;

        $('summaryRecovery').textContent = $('auto_recovery')?.checked ? 'Enabled' : 'Disabled';
        $('summaryNotifications').textContent = $('notify_users')?.checked ? 'Enabled' : 'Disabled';
    }

    function updateNotificationPreview() {
        const notify = $('notify_users')?.checked;
        const labels = Array.from($$('input[name="notification_channels[]"]:checked'))
            .map(cb => cb.nextElementSibling?.textContent?.trim() || cb.value)
            .map(t => t.replace(/^\s*\S+\s+/, ''));

        $('previewChannels').textContent = notify ? (labels.join(', ') || 'None selected') : 'No notifications';
    }

    /* ---------- Quick-select ---------- */
    function selectAllModules() {
        $$('input[name="affected_modules[]"]').forEach(cb => cb.checked = true);
        updateSummary();
    }
    function deselectAllModules() {
        $$('input[name="affected_modules[]"]').forEach(cb => cb.checked = false);
        updateSummary();
    }
    function selectCriticalModules() {
        $$('input[name="affected_modules[]"]').forEach(cb => {
            cb.checked = DEFAULTS.criticalModules.includes(cb.value);
        });
        updateSummary();
    }

    /* ---------- Activation toggle visual ---------- */
    function syncActivationVisual() {
        const immediate       = $('activate_immediately');
        const scheduled       = $('schedule_for_later');
        const immediateOption = $('immediateOption');
        const scheduledOption = $('scheduledOption');
        const inactiveOption  = $('inactiveOption');
        const immediateWarning= $('immediateWarning');
        const scheduleFields  = $('scheduleFields');

        if (!immediate || !scheduled) return;

        // Mutual exclusion
        if (immediate.checked && scheduled.checked) {
            scheduled.checked = false;
        }

        // Reset visual states
        [immediateOption, scheduledOption, inactiveOption].forEach(el => {
            if (!el) return;
            el.style.backgroundColor = 'var(--bg-secondary)';
            el.style.borderColor     = 'var(--border-color)';
        });

        if (immediate.checked) {
            immediateOption.style.backgroundColor = 'rgba(var(--warning-rgb), 0.05)';
            immediateOption.style.borderColor     = 'rgba(var(--warning-rgb), 0.3)';
            immediateWarning?.classList.remove('hidden');
            scheduleFields?.classList.add('hidden');
        } else if (scheduled.checked) {
            scheduledOption.style.backgroundColor = 'rgba(var(--info-rgb), 0.05)';
            scheduledOption.style.borderColor     = 'rgba(var(--info-rgb), 0.3)';
            immediateWarning?.classList.add('hidden');
            scheduleFields?.classList.remove('hidden');
        } else {
            inactiveOption.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            inactiveOption.style.borderColor     = 'rgba(var(--primary-rgb), 0.3)';
            immediateWarning?.classList.add('hidden');
            scheduleFields?.classList.add('hidden');
        }
    }

    /* ---------- Validation ---------- */
    function validateForm() {
        const firstError = { el: null, msg: '' };

        function fail(el, msg) {
            if (!firstError.el) { firstError.el = el; firstError.msg = msg; }
        }

        if (!($('name')?.value || '').trim()) fail($('name'), 'Please enter an emergency name.');
        if (!($('reason')?.value || '').trim()) fail($('reason'), 'Please enter an emergency reason.');
        else if ($('reason').value.trim().length < 10) fail($('reason'), 'Emergency reason must be at least 10 characters.');
        if (!($('severity_level')?.value || '').trim()) fail($('severity_level'), 'Please select a severity level.');

        if ($$('input[name="affected_modules[]"]:checked').length === 0) {
            fail($$('input[name="affected_modules[]"]')[0], 'Please select at least one affected module.');
        }

        if ($('schedule_for_later')?.checked) {
            const start = $('scheduled_start')?.value;
            if (!start) {
                fail($('scheduled_start'), 'Please select a start date and time for scheduled activation.');
            } else if (new Date(start) <= new Date()) {
                fail($('scheduled_start'), 'Scheduled start time must be in the future.');
            }

            const end = $('scheduled_end')?.value;
            if (end && start && new Date(end) <= new Date(start)) {
                fail($('scheduled_end'), 'End time must be after start time.');
            }
        }

        if (firstError.el) {
            alert(firstError.msg);
            firstError.el.focus();
            firstError.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        return true;
    }

    /* ---------- Submit flow ---------- */
    let confirmedSubmit = false;

    function submitForm() {
        confirmedSubmit = true;
        $('emergencyForm').submit();
    }

    function handleSubmit(event) {
        if (confirmedSubmit) return; // allow the modal-triggered submit

        if (!validateForm()) {
            event.preventDefault();
            return;
        }

        if ($('activate_immediately')?.checked) {
            event.preventDefault();
            $('confirmationModal').classList.remove('hidden');
        }
    }

    /* ---------- Reset ---------- */
    function resetForm() {
        if (!confirm('Are you sure you want to reset the form? All entered data will be lost.')) return;

        $('emergencyForm').reset();

        // Reset defaults that the browser may keep
        $$('input[name="allowed_operations[]"]').forEach(cb => {
            cb.checked = DEFAULTS.allowedOps.includes(cb.value);
        });
        $$('input[name="notification_channels[]"]').forEach(cb => {
            cb.checked = DEFAULTS.channels.includes(cb.value);
        });
        if ($('auto_recovery')) $('auto_recovery').checked = true;
        if ($('notify_users')) $('notify_users').checked = true;

        $('notificationChannels')?.classList.remove('hidden');

        updateCounters();
        updateSeverityDescription();
        syncActivationVisual();
        updateSummary();
        updateNotificationPreview();
    }

    function closeModal(id) {
        const el = $(id);
        if (el) el.classList.add('hidden');
    }

    /* ---------- Wire up ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        // Counters
        $('reason')?.addEventListener('input', function () {
            $('reasonCounter').textContent = this.value.length + '/500';
            updateSummary();
        });

        $('name')?.addEventListener('input', () => { updateSummary(); updateNotificationPreview(); });

        // Severity
        $('severity_level')?.addEventListener('change', function () {
            updateSeverityDescription();
            updateSummary();
            updateNotificationPreview();
        });
        updateSeverityDescription();

        // Module checkboxes
        $$('input[name="affected_modules[]"]').forEach(cb => cb.addEventListener('change', updateSummary));

        // Activation toggles
        $('activate_immediately')?.addEventListener('change', function () {
            if (this.checked) $('schedule_for_later').checked = false;
            syncActivationVisual();
            updateSummary();
        });
        $('schedule_for_later')?.addEventListener('change', function () {
            if (this.checked) $('activate_immediately').checked = false;
            syncActivationVisual();
            updateSummary();
        });
        syncActivationVisual();

        // Date inputs
        const start = $('scheduled_start');
        if (start) {
            const now = new Date();
            start.min = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            start.addEventListener('change', function () {
                const end = $('scheduled_end');
                if (end) end.min = this.value;
                updateSummary();
            });
        }

        // Notifications
        $('notify_users')?.addEventListener('change', function () {
            $('notificationChannels')?.classList.toggle('hidden', !this.checked);
            updateSummary();
            updateNotificationPreview();
        });
        $$('input[name="notification_channels[]"]').forEach(cb => cb.addEventListener('change', updateNotificationPreview));
        $('auto_recovery')?.addEventListener('change', updateSummary);

        // Form submit
        $('emergencyForm')?.addEventListener('submit', handleSubmit);

        // Modals — backdrop + escape
        document.querySelectorAll('.fixed.inset-0').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) this.classList.add('hidden');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal('confirmationModal');
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                if (validateForm()) submitForm();
            }
        });

        // Initial render
        updateCounters();
        updateSummary();
        updateNotificationPreview();
    });

    /* ---------- Public API ---------- */
    window.EmergencyCreate = {
        selectAllModules,
        deselectAllModules,
        selectCriticalModules,
        resetForm,
        submitForm,
        closeModal,
    };
})();
</script>
@endpush