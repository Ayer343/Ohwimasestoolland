@extends('layouts.app')

@section('title', 'Edit Registration Plan')

@php
    /*
     |--------------------------------------------------------------------------
     | Communication-channel availability
     |--------------------------------------------------------------------------
     | Priority for each channel:
     |   1. The status array passed by the controller (preferred).
     |   2. A live service call, if the variable wasn't passed.
     |   3. A per-service fallback key (e.g. `configured`) when
     |      `system_ready` is missing or null.
     |
     | NOTE: the codebase has THREE different key conventions:
     |   - SmsService::getSystemStatus()                 → `system_ready`
     |   - WhatsAppService::getSystemStatus()            → `system_ready`
     |   - SystemSetting::getEmailConfigurationStatus()  → `configured`
     */

    // ---------- SMS ----------
    if (!isset($smsStatus) || !is_array($smsStatus)) {
        try {
            $smsStatus = app(\App\Services\SmsService::class)->getSystemStatus();
        } catch (\Throwable $e) {
            $smsStatus = ['system_ready' => false];
        }
    }
    $smsReady = (bool) (
        $smsStatus['system_ready']
        ?? $smsStatus['can_send_sms']
        ?? $smsStatus['ready']
        ?? false
    );

    // ---------- WhatsApp ----------
    if (!isset($whatsappStatus) || !is_array($whatsappStatus)) {
        try {
            $whatsappStatus = app(\App\Services\WhatsAppService::class)->getSystemStatus();
        } catch (\Throwable $e) {
            $whatsappStatus = ['system_ready' => false];
        }
    }
    $whatsappReady = (bool) (
        $whatsappStatus['system_ready']
        ?? $whatsappStatus['can_send']
        ?? $whatsappStatus['configured']
        ?? false
    );

    // ---------- Email ----------
    if (!isset($emailStatus) || !is_array($emailStatus)) {
        try {
            $emailStatus = \App\Models\SystemSetting::getSettings()
                ->getEmailConfigurationStatus();
            if (!is_array($emailStatus)) {
                $emailStatus = [];
            }
        } catch (\Throwable $e) {
            $emailStatus = [];
        }
    }

    $emailReady = (bool) (
        $emailStatus['system_ready']
        ?? $emailStatus['can_send']
        ?? $emailStatus['configured']
        ?? false
    );

    // Normalize so downstream markup / JS always sees a consistent shape.
    $emailStatus['system_ready'] = $emailReady;
    $emailStatus['can_send']     = $emailReady;
    if (!isset($emailStatus['health'])) {
        $emailStatus['health'] = $emailReady ? 'healthy' : 'degraded';
    }
    if (!isset($emailStatus['message'])) {
        $emailStatus['message'] = $emailReady
            ? 'Email service is operational'
            : 'Email service is not configured or not ready';
    }
    if (!isset($emailStatus['provider'])) {
        $emailStatus['provider'] = $emailStatus['system_email'] ?? 'N/A';
    }

    // ---------- Rollup ----------
    $anyChannelReady = $smsReady || $whatsappReady || $emailReady;

    $oldInvitationChannels = old('invitation_channels', []);
    if (!is_array($oldInvitationChannels)) {
        $oldInvitationChannels = [];
    }
    $oldInvitationChannels = array_values(array_filter(
        $oldInvitationChannels,
        function ($c) use ($smsReady, $whatsappReady, $emailReady) {
            if ($c === 'sms')      return $smsReady;
            if ($c === 'whatsapp') return $whatsappReady;
            if ($c === 'email')    return $emailReady;
            return false;
        }
    ));

    /*
     |--------------------------------------------------------------------------
     | FIX: status select stale-old-input guard
     |--------------------------------------------------------------------------
     | If the last submission errored, old('status') may be 'draft' even
     | though the DB now holds 'assigned' (or vice versa). Trust the DB
     | unless the requested value is a deliberate downgrade by the admin.
     |
     | Priority:
     |   1. If validation errors exist AND old('status') is one of the
     |      allowed values, use old('status').
     |   2. Otherwise use the DB value.
     */
    $allowedStatuses = ['draft', 'assigned', 'in_progress'];

    $dbStatus = $registrationPlan->status;
    if (!in_array($dbStatus, $allowedStatuses, true)) {
        $dbStatus = 'draft';
    }

    $requestedOldStatus = old('status');
    $statusValue = $dbStatus;

    if ($errors->any() && $requestedOldStatus && in_array($requestedOldStatus, $allowedStatuses, true)) {
        $statusValue = $requestedOldStatus;
    }
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Edit Registration Plan</h2>
            <div class="flex items-center space-x-3">
                <!-- Communication Service Status Indicators -->
                <div class="flex items-center space-x-2">
                    <!-- WhatsApp Status Indicator -->
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $whatsappReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $whatsappReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fab fa-whatsapp mr-1"></i>
                        WhatsApp: {{ $whatsappReady ? 'Ready' : 'Limited' }}
                    </div>

                    <!-- SMS Status Indicator -->
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $smsReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $smsReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas fa-comment-alt mr-1"></i>
                        SMS: {{ $smsReady ? 'Ready' : 'Limited' }}
                    </div>

                    <!-- Email Status Indicator -->
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $emailReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $emailReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas {{ $emailReady ? 'fa-envelope' : 'fa-envelope-open' }} mr-1"></i>
                        Email: {{ $emailReady ? 'Ready' : 'Limited' }}
                    </div>
                </div>

                <a href="{{ route('registration-plans.show', $registrationPlan->id) }}" class="btn-secondary flex items-center">
                    <i class="fas fa-eye mr-2"></i> View Plan
                </a>
                <a href="{{ route('registration-plans.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('registration-plans.update', $registrationPlan->id) }}" method="POST" id="registration-plan-form">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6">
            @if($errors->any())
                <div class="card p-6">
                    <div class="alert alert-danger">
                        <h4 class="font-bold mb-2">Validation Errors:</h4>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="card p-6">
                    <div class="alert alert-danger">
                        <h4 class="font-bold">Error:</h4>
                        <p>{{ session('error') }}</p>
                    </div>
                </div>
            @endif

            @if(session('success'))
                <div class="card p-6">
                    <div class="alert alert-success">
                        <h4 class="font-bold">Success:</h4>
                        <p>{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            @if(!$registrationPlan->can_be_edited)
                <div class="card p-6">
                    <div class="alert alert-warning">
                        <h4 class="font-bold">Notice:</h4>
                        <p>This plan cannot be edited because it is {{ $registrationPlan->status }}.</p>
                        @if($registrationPlan->status === 'completed')
                            <p class="mt-1">All {{ $registrationPlan->estimated_houses }} houses have been registered.</p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Enhanced Communication System Status Card -->
            <div class="card p-6 border-l-4" style="border-left-color: var(--success);">
                <div class="flex items-start">
                    <i class="fas fa-comments text-xl mr-3 mt-1" style="color: var(--success);"></i>
                    <div class="flex-1">
                        <h3 class="font-semibold mb-2" style="color: var(--text-primary);">Communication System Status</h3>
                        <p class="mb-2" style="color: var(--text-secondary);">
                            Invitation links will be sent automatically via the channels you select.
                        </p>

                        <!-- Enhanced Service Status Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <!-- WhatsApp Status Card -->
                            <div class="p-4 rounded border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: {{ $whatsappReady ? 'var(--success)' : 'var(--warning)' }};">
                                <div class="flex items-center mb-2">
                                    <i class="fab fa-whatsapp mr-2 text-lg" style="color: #25D366;"></i>
                                    <span class="font-semibold" style="color: var(--text-primary);">WhatsApp</span>
                                </div>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Status:</span>
                                        <span class="font-medium {{ $whatsappReady ? 'text-green-600' : 'text-yellow-600' }}">
                                            {{ $whatsappReady ? 'Ready' : 'Limited' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Provider:</span>
                                        <span style="color: var(--text-primary);">{{ $whatsappStatus['provider'] ?? 'Not Configured' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Health:</span>
                                        <span class="capitalize font-medium {{ ($whatsappStatus['health'] ?? '') === 'healthy' ? 'text-green-600' : (($whatsappStatus['health'] ?? '') === 'degraded' ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $whatsappStatus['health'] ?? 'unknown' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- SMS Status Card -->
                            <div class="p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: {{ $smsReady ? 'var(--info)' : 'var(--warning)' }};">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-comment-alt mr-2 text-lg" style="color: var(--info);"></i>
                                    <span class="font-semibold" style="color: var(--text-primary);">SMS</span>
                                </div>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Status:</span>
                                        <span class="font-medium {{ $smsReady ? 'text-green-600' : 'text-yellow-600' }}">
                                            {{ $smsReady ? 'Ready' : 'Limited' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Active Provider:</span>
                                        <span style="color: var(--text-primary);">{{ $smsStatus['default_provider'] ?? ($smsStatus['provider'] ?? 'None') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Enabled Providers:</span>
                                        <span style="color: var(--text-primary);">{{ $smsStatus['enabled_count'] ?? ($smsStatus['configured_providers'] ?? '0') }}/5</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Status Card -->
                            <div class="p-4 rounded border" style="background-color: rgba(var(--primary-rgb), 0.05); border-color: {{ $emailReady ? 'var(--primary)' : 'var(--warning)' }};">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-envelope mr-2 text-lg" style="color: var(--primary);"></i>
                                    <span class="font-semibold" style="color: var(--text-primary);">Email</span>
                                </div>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Status:</span>
                                        <span class="font-medium {{ $emailReady ? 'text-green-600' : 'text-yellow-600' }}">
                                            {{ $emailReady ? 'Ready' : 'Limited' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Provider:</span>
                                        <span style="color: var(--text-primary);">{{ $emailStatus['provider'] ?? 'Unknown' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Success Rate:</span>
                                        <span style="color: var(--text-primary);">{{ $emailStatus['statistics']['success_rate'] ?? 0 }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Overall System Status -->
                        <div class="flex items-center justify-between p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="flex items-center">
                                <i class="fas fa-signal mr-2" style="color: var(--success);"></i>
                                <span class="font-medium" style="color: var(--text-primary);">Overall System Status:</span>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium
                                {{ ($smsReady && $whatsappReady && $emailReady) ? 'bg-green-100 text-green-800' :
                                   (($smsReady || $whatsappReady || $emailReady) ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ($smsReady && $whatsappReady && $emailReady) ? 'Fully Operational' :
                                   (($smsReady || $whatsappReady || $emailReady) ? 'Partially Operational' : 'Not Operational') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Assignment Type Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Agent Assignment Type</h3>
                    <i class="fas fa-users text-2xl opacity-70" style="color: var(--info);"></i>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="agent_assignment_type" class="block mb-2 font-medium" style="color: var(--text-primary);">Assignment Type *</label>
                        <select class="w-full p-2 border rounded @error('agent_assignment_type') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                id="agent_assignment_type" name="agent_assignment_type" required
                                @if(!$registrationPlan->can_be_edited) disabled @endif>
                            <option value="single" {{ old('agent_assignment_type', $registrationPlan->agent_assignment_type) == 'single' ? 'selected' : '' }}>Single Agent</option>
                            <option value="multiple" {{ old('agent_assignment_type', $registrationPlan->agent_assignment_type) == 'multiple' ? 'selected' : '' }}>Multiple Agents</option>
                        </select>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="assignment-type-help">
                                Single agent: One agent handles the entire area. Multiple agents: Multiple agents can work on different parts.
                            </span>
                        </p>
                        @error('agent_assignment_type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Geographical Area Card -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Geographical Area</h3>
                        <i class="fas fa-map-marker-alt text-2xl opacity-70" style="color: var(--primary);"></i>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="zone" class="block mb-2 font-medium" style="color: var(--text-primary);">Zone *</label>
                            <input type="text" class="w-full p-2 border rounded @error('zone') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="zone" name="zone" value="{{ old('zone', $registrationPlan->zone) }}" required
                                   placeholder="e.g., Zone A, North Sector"
                                   @if(!$registrationPlan->can_be_edited) disabled @endif>
                            @error('zone')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="section" class="block mb-2 font-medium" style="color: var(--text-primary);">Section</label>
                            <input type="text" class="w-full p-2 border rounded @error('section') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="section" name="section" value="{{ old('section', $registrationPlan->section) }}"
                                   placeholder="e.g., Section 1, Residential Block"
                                   @if(!$registrationPlan->can_be_edited) disabled @endif>
                            @error('section')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="boundaries_description" class="block mb-2 font-medium" style="color: var(--text-primary);">Boundaries Description</label>
                            <textarea class="w-full p-2 border rounded @error('boundaries_description') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="boundaries_description" name="boundaries_description" rows="3"
                                      placeholder="Describe the physical boundaries of this area..."
                                      @if(!$registrationPlan->can_be_edited) disabled @endif>{{ old('boundaries_description', $registrationPlan->boundaries_description) }}</textarea>
                            @error('boundaries_description')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Naming Convention Card -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Naming Convention</h3>
                        <i class="fas fa-tag text-2xl opacity-70" style="color: var(--info);"></i>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="naming_pattern" class="block mb-2 font-medium" style="color: var(--text-primary);">Naming Pattern *</label>
                            <select class="w-full p-2 border rounded @error('naming_pattern') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="naming_pattern" name="naming_pattern" required
                                    @if(!$registrationPlan->can_be_edited) disabled @endif>
                                <option value="">Select a pattern...</option>
                                @foreach($namingPatterns as $value => $label)
                                    <option value="{{ $value }}" {{ old('naming_pattern', $registrationPlan->naming_pattern) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div id="custom_pattern_container" class="mt-2 {{ (old('naming_pattern', $registrationPlan->naming_pattern) == 'custom') ? '' : 'hidden' }}">
                                <input type="text" class="w-full p-2 border rounded @error('custom_pattern') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="custom_pattern" name="custom_pattern" value="{{ old('custom_pattern', $registrationPlan->naming_pattern == 'custom' ? $registrationPlan->naming_pattern : '') }}"
                                       placeholder="e.g., APT-{letter}{number}, BLOCK-{number}-UNIT-{letter}"
                                       @if(!$registrationPlan->can_be_edited) disabled @endif>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    Use <code class="px-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">{letter}</code> for alphabetical sequencing,
                                    <code class="px-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">{number}</code> for numerical sequencing, or combine both
                                </p>
                                @error('custom_pattern')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            @error('naming_pattern')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Enhanced Starting Point Section -->
                        <div>
                            <label for="starting_point" class="block mb-2 font-medium" style="color: var(--text-primary);">Starting Point *</label>
                            <input type="text" class="w-full p-2 border rounded @error('starting_point') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="starting_point" name="starting_point" value="{{ old('starting_point', $registrationPlan->starting_point) }}" required
                                   placeholder="e.g., A, 1, A1, B5"
                                   @if(!$registrationPlan->can_be_edited) disabled @endif>

                            <!-- Enhanced Helper Text with Sequence Type Information -->
                            <div class="mt-2 space-y-1">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                    <div>
                                        <span class="text-sm" style="color: var(--text-secondary);" id="starting_point_helper">
                                            Select a pattern first to see format requirements
                                        </span>
                                        <div id="sequence_type_requirements" class="text-xs mt-1 hidden">
                                            <span class="font-medium" style="color: var(--text-primary);">Sequence Requirements:</span>
                                            <span id="sequence_requirements_text" class="ml-1" style="color: var(--text-secondary);"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sequence Type Examples -->
                                <div id="sequence_examples" class="text-xs space-y-1 hidden">
                                    <div class="font-medium" style="color: var(--text-primary);">Examples for your sequence type:</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 mt-1">
                                        <div id="valid_examples" class="space-y-1"></div>
                                        <div id="invalid_examples" class="space-y-1"></div>
                                    </div>
                                </div>

                                <!-- Current Next Available Name -->
                                @if($registrationPlan->next_available_name && $registrationPlan->next_available_name !== $registrationPlan->starting_point)
                                <div class="flex items-center text-sm mt-2" style="color: var(--success);">
                                    <i class="fas fa-arrow-right mr-2"></i>
                                    <span>Next available name: <strong>{{ $registrationPlan->next_available_name }}</strong></span>
                                </div>
                                @endif
                            </div>
                            @error('starting_point')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="estimated_houses" class="block mb-2 font-medium" style="color: var(--text-primary);">Estimated Houses *</label>
                                <input type="number" class="w-full p-2 border rounded @error('estimated_houses') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="estimated_houses" name="estimated_houses" value="{{ old('estimated_houses', $registrationPlan->estimated_houses) }}" min="1" max="1000" required
                                       @if(!$registrationPlan->can_be_edited) disabled @endif>
                                @error('estimated_houses')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="sequence_type" class="block mb-2 font-medium" style="color: var(--text-primary);">Sequence Type *</label>
                                <select class="w-full p-2 border rounded @error('sequence_type') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="sequence_type" name="sequence_type" required
                                        @if(!$registrationPlan->can_be_edited) disabled @endif>
                                    <option value="sequential" {{ old('sequence_type', $registrationPlan->sequence_type) == 'sequential' ? 'selected' : '' }}>Sequential (1,2,3...)</option>
                                    <option value="even_only" {{ old('sequence_type', $registrationPlan->sequence_type) == 'even_only' ? 'selected' : '' }}>Even Numbers Only (2,4,6...)</option>
                                    <option value="odd_only" {{ old('sequence_type', $registrationPlan->sequence_type) == 'odd_only' ? 'selected' : '' }}>Odd Numbers Only (1,3,5...)</option>
                                </select>
                                <div id="sequence-type-note" class="text-xs mt-1 hidden">
                                    <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                                    <span id="sequence-type-note-text" style="color: var(--info);"></span>
                                </div>
                                @error('sequence_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Progress Information -->
                        <div class="p-4 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="flex justify-between items-center mb-2">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Current Progress</p>
                                <span class="text-xs px-2 py-1 rounded {{ $registrationPlan->progress_percentage == 100 ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $registrationPlan->progress_percentage }}% Complete
                                </span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Registered Houses:</span>
                                    <span class="font-semibold">{{ $registrationPlan->properties_count ?? 0 }} / {{ $registrationPlan->estimated_houses }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Remaining:</span>
                                    <span class="font-semibold">{{ max(0, $registrationPlan->estimated_houses - ($registrationPlan->properties_count ?? 0)) }} houses</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $registrationPlan->progress_percentage }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Pattern Preview Section -->
                        <div id="pattern-preview-container" class="p-4 rounded border hidden" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="flex justify-between items-center mb-2">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Pattern Preview:</p>
                                <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);" id="pattern-type-badge">
                                    Sequential
                                </span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span style="color: var(--text-secondary);">Selected Pattern:</span>
                                    <code class="font-mono font-bold" id="selected-pattern-display" style="color: var(--primary);"></code>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span style="color: var(--text-secondary);">Starting Point:</span>
                                    <span class="font-mono font-bold" id="starting-point-display" style="color: var(--primary);"></span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span style="color: var(--text-secondary);">Sequence Type:</span>
                                    <span class="font-medium" id="sequence-type-display" style="color: var(--primary);"></span>
                                </div>
                                <div class="border-t pt-2 mt-2" style="border-color: var(--border-color);">
                                    <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Sequence Preview:</p>
                                    <div class="flex flex-wrap gap-2" id="sequence-preview">
                                        <!-- Sequence items will be generated here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pattern Validation Message -->
                        <div id="pattern-validation" class="hidden">
                            <div class="flex items-center p-3 rounded border" style="background-color: rgba(var(--warning-rgb), 0.1); border-color: rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                                <span class="text-sm" style="color: var(--warning);" id="validation-message"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Assignment & Invitation Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Agent Assignment & Invitation</h3>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-shield text-lg" style="color: var(--info);" title="Secure invitation system"></i>
                        <i class="fas fa-calendar-alt text-2xl opacity-70" style="color: var(--success);"></i>
                    </div>
                </div>
                <div class="space-y-4">
                    <!-- Secure Invitation System Notice -->
                    <div class="rounded p-3" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-shield-alt mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div class="flex-1">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    Secure Invitation System: Agents will receive secure links to set up their accounts
                                </span>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mt-2 text-xs" style="color: var(--text-secondary);">
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>No passwords sent via SMS</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>Agents set their own secure passwords</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>7-day invitation expiry</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>Multi-channel delivery</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ALL CHANNELS UNAVAILABLE BANNER --}}
                    @if(!$anyChannelReady)
                    <div class="rounded p-4 border-l-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left-color: var(--danger);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--danger);">No Delivery Channels Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    None of the communication services (SMS, WhatsApp, Email) are configured.
                                    Field agent invitations cannot be sent until at least one channel is set up.
                                    You can still save the plan without sending invitations.
                                </p>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <a href="{{ route('admin.system-settings.index') }}#whatsapp-configuration" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure WhatsApp
                                    </a>
                                    <a href="{{ route('admin.system-settings.index') }}#sms-configuration" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure SMS
                                    </a>
                                    <a href="{{ route('admin.email-config.index') }}" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure Email
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- WhatsApp Service Alert -->
                    @if(!$whatsappReady && $anyChannelReady)
                    <div class="rounded p-3 border-l-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                        <div class="flex items-start">
                            <i class="fab fa-whatsapp mr-2 mt-0.5" style="color: #25D366;"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--text-primary);">WhatsApp Service Not Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    WhatsApp invitations cannot be sent. The system will use the other selected channels.
                                </p>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <strong>Current Status:</strong> {{ $whatsappStatus['health'] ?? 'Not Configured' }} - {{ $whatsappStatus['message'] ?? 'WhatsApp service not configured' }}
                                </div>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2">
                                    <a href="{{ route('admin.system-settings.index') }}#whatsapp-configuration" class="btn-primary btn-sm flex items-center w-fit">
                                        <i class="fas fa-cog mr-2"></i> Configure WhatsApp Service
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Email Service Alert -->
                    @if(!$emailReady && $anyChannelReady)
                    <div class="rounded p-3 border-l-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--text-primary);">Email Service Limited</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    Email invitations may not be delivered. The system will use the other selected channels.
                                </p>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <strong>Current Status:</strong> {{ $emailStatus['health'] }} - {{ $emailStatus['message'] }}
                                </div>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2">
                                    <a href="{{ route('admin.email-config.index') }}" class="btn-primary btn-sm flex items-center w-fit">
                                        <i class="fas fa-cog mr-2"></i> Configure Email Service
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Multiple Agent Assignment Section -->
                    <div id="multiple-agents-section" class="space-y-4 {{ old('agent_assignment_type', $registrationPlan->agent_assignment_type) == 'multiple' ? '' : 'hidden' }}">
                        <div class="flex justify-between items-center">
                            <h4 class="font-medium" style="color: var(--text-primary);">Assign Multiple Agents</h4>
                            <button type="button" id="add-agent-btn" class="btn-primary btn-sm flex items-center"
                                    @if(!$registrationPlan->can_be_edited) disabled @endif>
                                <i class="fas fa-plus mr-1"></i> Add Agent
                            </button>
                        </div>

                        <div id="agents-container">
                            @php
                                $existingAssignments = $registrationPlan->assignedAgents->where('is_active', true);
                            @endphp

                            @if($existingAssignments->count() > 0)
                                @foreach($existingAssignments as $index => $assignment)
                                <div class="agent-row border rounded p-4 mb-3" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <div class="flex justify-between items-center mb-3">
                                        <h5 class="font-medium" style="color: var(--text-primary);">Agent #{{ $loop->iteration }}</h5>
                                        @if($registrationPlan->can_be_edited)
                                        <button type="button" class="remove-agent-btn text-red-500 hover:text-red-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                        <div>
                                            <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Agent Type</label>
                                            <select name="agent_types[]" class="w-full p-2 border rounded text-sm agent-type-select"
                                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                    @if(!$registrationPlan->can_be_edited) disabled @endif>
                                                <option value="existing" selected>Existing Agent</option>
                                                <option value="new">New Agent</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">
                                                Invitation Channels *
                                            </label>
                                            <div class="flex space-x-3 text-xs">
                                                <label class="flex items-center space-x-1">
                                                    <input type="checkbox"
                                                           name="agent_invitation_channels[{{ $assignment->agent_id }}][]"
                                                           value="sms"
                                                           class="mr-1 multiple-agent-channel"
                                                           data-agent-id="{{ $assignment->agent_id }}"
                                                           {{ $smsReady ? '' : 'disabled' }}>
                                                    <span style="color: var(--text-primary);">SMS{{ $smsReady ? '' : ' (N/A)' }}</span>
                                                </label>
                                                <label class="flex items-center space-x-1">
                                                    <input type="checkbox"
                                                           name="agent_invitation_channels[{{ $assignment->agent_id }}][]"
                                                           value="whatsapp"
                                                           class="mr-1 multiple-agent-channel"
                                                           data-agent-id="{{ $assignment->agent_id }}"
                                                           {{ $whatsappReady ? '' : 'disabled' }}>
                                                    <span style="color: var(--text-primary);">WhatsApp{{ $whatsappReady ? '' : ' (N/A)' }}</span>
                                                </label>
                                                <label class="flex items-center space-x-1">
                                                    <input type="checkbox"
                                                           name="agent_invitation_channels[{{ $assignment->agent_id }}][]"
                                                           value="email"
                                                           class="mr-1 multiple-agent-channel"
                                                           data-agent-id="{{ $assignment->agent_id }}"
                                                           {{ $emailReady ? '' : 'disabled' }}>
                                                    <span style="color: var(--text-primary);">Email{{ $emailReady ? '' : ' (N/A)' }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="existing-agent-fields">
                                        <div class="mb-3">
                                            <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Select Existing Agent</label>
                                            <select name="assigned_agent_ids[]" class="w-full p-2 border rounded text-sm existing-agent-select"
                                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                    @if(!$registrationPlan->can_be_edited) disabled @endif>
                                                <option value="">Select Agent</option>
                                                @foreach($fieldAgents as $agent)
                                                    <option value="{{ $agent->id }}"
                                                            {{ $assignment->agent_id == $agent->id ? 'selected' : '' }}
                                                            data-phone="{{ $agent->phone }}"
                                                            data-email="{{ $agent->email }}">
                                                        {{ $agent->name }} @if($agent->phone)({{ $agent->phone }})@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="new-agent-fields hidden">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                            <div>
                                                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Name *</label>
                                                <input type="text" name="agent_names[]" class="w-full p-2 border rounded text-sm"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="Agent Name">
                                            </div>
                                            <div>
                                                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Phone *</label>
                                                <input type="tel" name="agent_phones[]" class="w-full p-2 border rounded text-sm"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="+233XXXXXXXXX">
                                            </div>
                                            <div>
                                                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Email</label>
                                                <input type="email" name="agent_emails[]" class="w-full p-2 border rounded text-sm"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="agent@example.com">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>

                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            You can assign multiple agents to work on different parts of this area.
                        </div>

                        <!-- Multiple Agent Conditional Fields -->
                        <div id="multiple-agent-conditional-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6 {{ ($existingAssignments->count() > 0) ? '' : 'hidden' }}">
                            <div>
                                <label for="registration_start_date_multiple" class="block mb-2 font-medium" style="color: var(--text-primary);">Start Date *</label>
                                <input type="date" class="w-full p-2 border rounded @error('registration_start_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="registration_start_date_multiple" name="registration_start_date"
                                       value="{{ old('registration_start_date', $registrationPlan->registration_start_date ? $registrationPlan->registration_start_date->format('Y-m-d') : '') }}"
                                       @if(!$registrationPlan->can_be_edited) disabled @endif>
                                @error('registration_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="registration_end_date_multiple" class="block mb-2 font-medium" style="color: var(--text-primary);">End Date *</label>
                                <input type="date" class="w-full p-2 border rounded @error('registration_end_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="registration_end_date_multiple" name="registration_end_date"
                                       value="{{ old('registration_end_date', $registrationPlan->registration_end_date ? $registrationPlan->registration_end_date->format('Y-m-d') : '') }}"
                                       @if(!$registrationPlan->can_be_edited) disabled @endif>
                                @error('registration_end_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="instructions_multiple" class="block mb-2 font-medium" style="color: var(--text-primary);">Instructions for Field Agents</label>
                                <textarea class="w-full p-2 border rounded @error('instructions') border-red-500 @enderror"
                                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                          id="instructions_multiple" name="instructions" rows="4"
                                          placeholder="Provide specific instructions for all field agents..."
                                          @if(!$registrationPlan->can_be_edited) disabled @endif>{{ old('instructions', $registrationPlan->instructions) }}</textarea>
                                @error('instructions')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Single Agent Assignment Section -->
                    <div id="single-agent-section" class="space-y-4 {{ old('agent_assignment_type', $registrationPlan->agent_assignment_type) == 'single' ? '' : 'hidden' }}">
                        <!-- Multi-channel Invitation Section -->
                        <div id="invitation-method-section" class="{{ ($registrationPlan->assignedAgents->where('is_active', true)->count() > 0) ? '' : 'hidden' }}">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Invitation Channels *
                                <span class="text-xs font-normal text-gray-500 ml-1">(Select one or more channels)</span>
                            </label>

                            @if(!$anyChannelReady)
                            <div class="mb-3 p-3 rounded border" style="background-color: rgba(var(--danger-rgb), 0.08); border-color: rgba(var(--danger-rgb), 0.3);">
                                <div class="flex items-center text-sm" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <span>No delivery channels available. Please configure at least one service first.</span>
                                </div>
                            </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3" id="invitation-channels-options">
                                {{-- SMS --}}
                                <label class="flex items-start p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option {{ !$smsReady ? 'channel-disabled' : '' }}"
                                       style="background-color: {{ $smsReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }}; border-color: var(--border-color);"
                                       data-channel="sms"
                                       @if(!$smsReady) aria-disabled="true" title="SMS service not configured" @endif>
                                    <input type="checkbox"
                                           name="invitation_channels[]"
                                           value="sms"
                                           class="mr-2 invitation-channel-checkbox"
                                           style="color: var(--primary);"
                                           {{ $smsReady ? '' : 'disabled' }}
                                           @if($registrationPlan->can_be_edited) {{ in_array('sms', $oldInvitationChannels, true) ? 'checked' : '' }} @endif>
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-comment-alt mr-2 text-lg" style="color: var(--info);"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">SMS</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">Send invitation link via SMS</div>
                                                <div class="text-xs mt-1 {{ $smsReady ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ $smsReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $smsReady ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- WhatsApp --}}
                                <label class="flex items-start p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option {{ !$whatsappReady ? 'channel-disabled' : '' }}"
                                       style="background-color: {{ $whatsappReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }}; border-color: var(--border-color);"
                                       data-channel="whatsapp"
                                       @if(!$whatsappReady) aria-disabled="true" title="WhatsApp service not configured" @endif>
                                    <input type="checkbox"
                                           name="invitation_channels[]"
                                           value="whatsapp"
                                           class="mr-2 invitation-channel-checkbox"
                                           style="color: var(--primary);"
                                           {{ $whatsappReady ? '' : 'disabled' }}
                                           @if($registrationPlan->can_be_edited) {{ in_array('whatsapp', $oldInvitationChannels, true) ? 'checked' : '' }} @endif>
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <i class="fab fa-whatsapp mr-2 text-lg" style="color: #25D366;"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">WhatsApp</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">Send via WhatsApp</div>
                                                <div class="text-xs mt-1 {{ $whatsappReady ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ $whatsappReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $whatsappReady ? 'Available' : 'Not Available' }}
                                                </div>
                                                @if(!$whatsappReady && auth()->user()->isAdmin())
                                                <div class="text-xs mt-1" style="color: var(--warning);">
                                                    <a href="{{ route('admin.system-settings.index') }}#whatsapp-configuration" class="underline">
                                                        <i class="fas fa-cog mr-1"></i>Configure
                                                    </a>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- Email --}}
                                <label class="flex items-start p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option {{ !$emailReady ? 'channel-disabled' : '' }}"
                                       style="background-color: {{ $emailReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }}; border-color: var(--border-color);"
                                       data-channel="email"
                                       @if(!$emailReady) aria-disabled="true" title="Email service not configured" @endif>
                                    <input type="checkbox"
                                           name="invitation_channels[]"
                                           value="email"
                                           class="mr-2 invitation-channel-checkbox"
                                           style="color: var(--primary);"
                                           {{ $emailReady ? '' : 'disabled' }}
                                           @if($registrationPlan->can_be_edited) {{ in_array('email', $oldInvitationChannels, true) ? 'checked' : '' }} @endif>
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-envelope mr-2 text-lg" style="color: var(--primary);"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">Email</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">Send invitation via email</div>
                                                <div class="text-xs mt-1 {{ $emailReady ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ $emailReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $emailReady ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Enhanced Channel Requirements Information -->
                            <div id="channel-requirements" class="mt-3 p-3 rounded border hidden" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                    <span id="requirements-text"></span>
                                </div>
                            </div>

                            <!-- Email Service Status for Selected Channel -->
                            <div id="email-service-details" class="mt-3 p-3 rounded border hidden" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div class="flex items-center">
                                        <i class="fas fa-server mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Provider:</span>
                                            <span class="ml-1" style="color: var(--text-primary);">{{ $emailStatus['provider'] ?? 'Unknown' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-heartbeat mr-2" style="color: {{ ($emailStatus['health'] ?? '') === 'healthy' ? 'var(--success)' : (($emailStatus['health'] ?? '') === 'degraded' ? 'var(--warning)' : 'var(--danger)') }};"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Status:</span>
                                            <span class="ml-1 capitalize {{ ($emailStatus['health'] ?? '') === 'healthy' ? 'text-green-600' : (($emailStatus['health'] ?? '') === 'degraded' ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ $emailStatus['health'] ?? 'unknown' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Success Rate:</span>
                                            <span class="ml-1" style="color: var(--text-primary);">{{ $emailStatus['statistics']['success_rate'] ?? 0 }}%</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope-open mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Today's Usage:</span>
                                            <span class="ml-1" style="color: var(--text-primary);">{{ $emailStatus['limits']['daily_limit']['used_today'] ?? 0 }}/{{ $emailStatus['limits']['daily_limit']['max_emails_per_day'] ?? 0 }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agent Selection Options -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Existing Agent Selection -->
                            <div>
                                <label for="assigned_agent_id" class="block mb-2 font-medium" style="color: var(--text-primary);">Assign Existing Field Agent</label>
                                <select class="w-full p-2 border rounded @error('assigned_agent_id') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="assigned_agent_id" name="assigned_agent_id"
                                        @if(!$registrationPlan->can_be_edited) disabled @endif>
                                    <option value="">Select Existing Agent</option>
                                    @foreach($fieldAgents as $agent)
                                        @php
                                            $isAssigned = $registrationPlan->assignedAgents->where('agent_id', $agent->id)->where('is_active', true)->first();
                                        @endphp
                                        <option value="{{ $agent->id }}"
                                                {{ (old('assigned_agent_id') == $agent->id || $isAssigned) ? 'selected' : '' }}
                                                data-phone="{{ $agent->phone }}"
                                                data-email="{{ $agent->email }}"
                                                data-status="{{ $agent->status }}"
                                                data-verified="{{ $agent->phone_verified_at ? 'true' : 'false' }}"
                                                data-invitation-accepted="{{ $agent->invitation_accepted_at ? 'true' : 'false' }}">
                                            {{ $agent->name }}
                                            @if($agent->phone)
                                                ({{ $agent->phone }})
                                            @endif
                                            - {{ ucfirst($agent->status) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div id="existing-agent-info" class="mt-2 text-sm space-y-1 hidden">
                                    <div class="flex items-center" id="agent-phone-info">
                                        <i class="fas fa-phone mr-2" style="color: var(--text-secondary);"></i>
                                        <span id="agent-phone-text"></span>
                                    </div>
                                    <div class="flex items-center" id="agent-email-info">
                                        <i class="fas fa-envelope mr-2" style="color: var(--text-secondary);"></i>
                                        <span id="agent-email-text"></span>
                                    </div>
                                    <div class="flex items-center" id="agent-status-info">
                                        <i class="fas fa-circle mr-2" style="color: var(--text-secondary);"></i>
                                        <span id="agent-status-text"></span>
                                    </div>
                                    <div class="flex items-center" id="agent-verification-info">
                                        <i class="fas fa-shield-alt mr-2" style="color: var(--text-secondary);"></i>
                                        <span id="agent-verification-text"></span>
                                    </div>
                                </div>
                                @error('assigned_agent_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- New Agent Creation -->
                            <div>
                                <label class="block mb-2 font-medium" style="color: var(--text-primary);">Create New Field Agent</label>
                                <div class="space-y-3">
                                    <div>
                                        <input type="text" class="w-full p-2 border rounded @error('agent_name') border-red-500 @enderror"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               id="agent_name" name="agent_name" value="{{ old('agent_name') }}"
                                               placeholder="Agent Full Name *"
                                               @if(!$registrationPlan->can_be_edited) disabled @endif>
                                        @error('agent_name')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <input type="email" class="w-full p-2 border rounded @error('agent_email') border-red-500 @enderror"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               id="agent_email" name="agent_email" value="{{ old('agent_email') }}"
                                               placeholder="Email Address (required for email invitations)"
                                               @if(!$registrationPlan->can_be_edited) disabled @endif>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);" id="email-requirement-note">
                                            Required if selecting email channel
                                        </div>
                                        @error('agent_email')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <div class="relative">
                                            <input type="tel" class="w-full p-2 border rounded @error('agent_phone') border-red-500 @enderror"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   id="agent_phone" name="agent_phone" value="{{ old('agent_phone') }}"
                                                   placeholder="Phone Number with Country Code *"
                                                   @if(!$registrationPlan->can_be_edited) disabled @endif>
                                            <div id="phone-validation" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                                <i class="fas fa-check" id="phone-valid-icon" style="color: var(--success);"></i>
                                                <i class="fas fa-times" id="phone-invalid-icon" style="color: var(--danger);"></i>
                                            </div>
                                        </div>
                                        <div id="phone-helper" class="text-sm mt-1 space-y-1">
                                            <div class="flex items-center" id="phone-format-info">
                                                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                                <span style="color: var(--text-secondary);">
                                                    Format: +[country code][number] (e.g., +233595652410)
                                                </span>
                                            </div>
                                            @if($smsReady || $whatsappReady)
                                                <div class="flex items-center" id="sms-ready-info" style="color: var(--success);">
                                                    <i class="fas fa-comment-alt mr-2"></i>
                                                    <span>Invitation can be sent via SMS / WhatsApp</span>
                                                </div>
                                            @else
                                                <div class="flex items-center" id="sms-not-ready-info" style="color: var(--warning);">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <span>SMS / WhatsApp not configured</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center mt-2 hidden" id="phone-formatted-info" style="color: var(--success);">
                                                <i class="fas fa-check-circle mr-2"></i>
                                                <span>Formatted as: <span id="formatted-phone" class="font-mono"></span></span>
                                            </div>
                                        </div>
                                        @error('agent_phone')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <span class="text-sm" style="color: var(--text-secondary);">OR</span>
                        </div>

                        <!-- No Agent Option -->
                        <div class="text-center">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Leave both fields empty to keep as draft plan without agent assignment
                            </p>
                        </div>
                    </div>

                    <!-- Single Agent Conditional Fields Container -->
                    <div id="single-agent-conditional-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6 {{ ($registrationPlan->assignedAgents->where('is_active', true)->count() > 0) ? '' : 'hidden' }}">
                        <div>
                            <label for="registration_start_date" class="block mb-2 font-medium" style="color: var(--text-primary);">Start Date *</label>
                            <input type="date" class="w-full p-2 border rounded @error('registration_start_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="registration_start_date" name="registration_start_date"
                                   value="{{ old('registration_start_date', $registrationPlan->registration_start_date ? $registrationPlan->registration_start_date->format('Y-m-d') : '') }}"
                                   @if(!$registrationPlan->can_be_edited) disabled @endif>
                            @error('registration_start_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="registration_end_date" class="block mb-2 font-medium" style="color: var(--text-primary);">End Date *</label>
                            <input type="date" class="w-full p-2 border rounded @error('registration_end_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="registration_end_date" name="registration_end_date"
                                   value="{{ old('registration_end_date', $registrationPlan->registration_end_date ? $registrationPlan->registration_end_date->format('Y-m-d') : '') }}"
                                   @if(!$registrationPlan->can_be_edited) disabled @endif>
                            @error('registration_end_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="instructions" class="block mb-2 font-medium" style="color: var(--text-primary);">Instructions for Field Agent</label>
                            <textarea class="w-full p-2 border rounded @error('instructions') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="instructions" name="instructions" rows="4"
                                      placeholder="Provide specific instructions for the field agent..."
                                      @if(!$registrationPlan->can_be_edited) disabled @endif>{{ old('instructions', $registrationPlan->instructions) }}</textarea>
                            @error('instructions')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Enhanced Invitation Preview -->
                    <div id="invitation-preview-container" class="rounded p-4 hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-semibold" style="color: var(--text-primary);">Invitation Preview</h4>
                            <div class="flex items-center space-x-2">
                                <span id="invitation-channel-badge" class="px-2 py-1 rounded text-xs font-medium"
                                      style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                    Select channels
                                </span>
                                <i class="fas fa-envelope" style="color: var(--info);"></i>
                            </div>
                        </div>
                        <div class="rounded p-3 text-sm font-mono whitespace-pre-wrap" id="invitation-preview-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <!-- Invitation content will be generated here -->
                        </div>
                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="invitation-preview-note">This preview shows the invitation that will be sent to the field agent</span>
                        </div>

                        <!-- Email Delivery Information -->
                        <div id="email-delivery-info" class="mt-3 p-2 rounded border hidden" style="background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3);">
                            <div class="flex items-center text-sm">
                                <i class="fas fa-paper-plane mr-2" style="color: var(--success);"></i>
                                <span style="color: var(--text-primary);">Email will be sent with automatic retry logic (up to 3 attempts)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Field -->
            @if($registrationPlan->can_be_edited)
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Plan Status</h3>
                    <i class="fas fa-info-circle text-2xl opacity-70" style="color: var(--info);"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select class="w-full p-2 border rounded @error('status') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                id="status" name="status">
                            <option value="draft"       {{ $statusValue === 'draft'       ? 'selected' : '' }}>Draft</option>
                            <option value="assigned"    {{ $statusValue === 'assigned'    ? 'selected' : '' }}>Assigned</option>
                            <option value="in_progress" {{ $statusValue === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        </select>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Status will auto-update based on agent assignment
                        </p>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-end">
                        <div class="rounded p-3" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                                @if($registrationPlan->assignedAgents->where('is_active', true)->count() > 0)
                                    Currently assigned to: <strong>{{ $registrationPlan->assignedAgents->where('is_active', true)->count() }} agent(s)</strong>
                                @else
                                    No agents currently assigned
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="status_mirror" id="status-mirror" value="{{ $statusValue }}">
            </div>
            @endif

            <!-- Form Actions -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status">
                        <p>Created: {{ $registrationPlan->created_at->format('M d, Y H:i') }} by {{ $registrationPlan->creator->name }}</p>
                        <p class="mt-1">Last Updated: {{ $registrationPlan->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="flex space-x-4">
                        <a href="{{ route('registration-plans.show', $registrationPlan->id) }}" class="btn-secondary">Cancel</a>
                        @if($registrationPlan->can_be_edited)
                            <button type="submit" class="btn-primary flex items-center" id="submit-button">
                                <i class="fas fa-save mr-2"></i> Update Registration Plan
                            </button>
                        @else
                            <button type="button" class="btn-disabled opacity-50 cursor-not-allowed" disabled>
                                <i class="fas fa-ban mr-2"></i> Cannot Edit {{ ucfirst($registrationPlan->status) }} Plan
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
// ==================== GLOBAL CHANNEL AVAILABILITY ====================
const SMS_SYSTEM_READY      = {{ $smsReady ? 'true' : 'false' }};
const WHATSAPP_SYSTEM_READY = {{ $whatsappReady ? 'true' : 'false' }};
const EMAIL_SYSTEM_READY    = {{ $emailReady ? 'true' : 'false' }};
const ANY_CHANNEL_READY     = SMS_SYSTEM_READY || WHATSAPP_SYSTEM_READY || EMAIL_SYSTEM_READY;

function isChannelGloballyReady(channel) {
    if (channel === 'sms')      return SMS_SYSTEM_READY;
    if (channel === 'whatsapp') return WHATSAPP_SYSTEM_READY;
    if (channel === 'email')    return EMAIL_SYSTEM_READY;
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    // Keep the hidden status mirror in sync with the select
    const statusSelect = document.getElementById('status');
    const statusMirror = document.getElementById('status-mirror');
    if (statusSelect && statusMirror) {
        statusSelect.addEventListener('change', function () {
            statusMirror.value = this.value;
        });
        const form = document.getElementById('registration-plan-form');
        if (form) {
            form.addEventListener('submit', function () {
                statusMirror.value = statusSelect.value;
            });
        }
    }

    // Helpers
    function isNumberThenLetterPattern(pattern) {
        return pattern.includes('{number}') && pattern.includes('{letter}') &&
               pattern.indexOf('{number}') < pattern.indexOf('{letter}');
    }

    function isLetterThenNumberPattern(pattern) {
        return pattern.includes('{letter}') && pattern.includes('{number}') &&
               pattern.indexOf('{letter}') < pattern.indexOf('{number}');
    }

    function validatePhoneNumber(phone) {
        const cleaned = phone.trim();
        const plus233Regex = /^\+\d{1,4}\d{6,}$/;
        const localFormatRegex = /^0\d{9,}$/;
        const countryCodeNoPlusRegex = /^233\d{9,}$/;
        return plus233Regex.test(cleaned) || localFormatRegex.test(cleaned) || countryCodeNoPlusRegex.test(cleaned);
    }

    function formatPhoneNumber(phone) {
        const cleaned = phone.trim().replace(/\s+/g, '');
        if (cleaned.startsWith('+233') && cleaned.length === 13) return cleaned;
        if (cleaned.startsWith('233') && cleaned.length === 12) return '+' + cleaned;
        if (cleaned.startsWith('0') && cleaned.length === 10) return '+233' + cleaned.substring(1);
        if (cleaned.startsWith('+') && cleaned.length >= 11) return cleaned;
        return phone;
    }

    // Elements
    const namingPatternSelect = document.getElementById('naming_pattern');
    const customPatternContainer = document.getElementById('custom_pattern_container');
    const customPatternInput = document.getElementById('custom_pattern');
    const startingPointInput = document.getElementById('starting_point');
    const startingPointHelper = document.getElementById('starting_point_helper');
    const sequenceTypeRequirements = document.getElementById('sequence_type_requirements');
    const sequenceRequirementsText = document.getElementById('sequence_requirements_text');
    const sequenceExamples = document.getElementById('sequence_examples');
    const validExamples = document.getElementById('valid_examples');
    const invalidExamples = document.getElementById('invalid_examples');
    const sequenceTypeInput = document.getElementById('sequence_type');
    const patternPreviewContainer = document.getElementById('pattern-preview-container');
    const selectedPatternDisplay = document.getElementById('selected-pattern-display');
    const startingPointDisplay = document.getElementById('starting-point-display');
    const sequenceTypeDisplay = document.getElementById('sequence-type-display');
    const sequencePreview = document.getElementById('sequence-preview');
    const patternTypeBadge = document.getElementById('pattern-type-badge');
    const patternValidation = document.getElementById('pattern-validation');
    const validationMessage = document.getElementById('validation-message');

    const agentSelect = document.getElementById('assigned_agent_id');
    const agentPhoneInput = document.getElementById('agent_phone');
    const agentNameInput = document.getElementById('agent_name');
    const agentEmailInput = document.getElementById('agent_email');
    const existingAgentInfo = document.getElementById('existing-agent-info');
    const agentPhoneInfo = document.getElementById('agent-phone-info');
    const agentPhoneText = document.getElementById('agent-phone-text');
    const agentEmailInfo = document.getElementById('agent-email-info');
    const agentEmailText = document.getElementById('agent-email-text');
    const agentStatusInfo = document.getElementById('agent-status-info');
    const agentStatusText = document.getElementById('agent-status-text');
    const agentVerificationInfo = document.getElementById('agent-verification-info');
    const agentVerificationText = document.getElementById('agent-verification-text');
    const phoneValidation = document.getElementById('phone-validation');
    const phoneValidIcon = document.getElementById('phone-valid-icon');
    const phoneInvalidIcon = document.getElementById('phone-invalid-icon');
    const invitationPreviewContainer = document.getElementById('invitation-preview-container');
    const invitationPreviewContent = document.getElementById('invitation-preview-content');
    const invitationMethodSection = document.getElementById('invitation-method-section');
    const channelRequirements = document.getElementById('channel-requirements');
    const requirementsText = document.getElementById('requirements-text');
    const invitationChannelBadge = document.getElementById('invitation-channel-badge');
    const invitationPreviewNote = document.getElementById('invitation-preview-note');
    const emailRequirementNote = document.getElementById('email-requirement-note');
    const emailServiceDetails = document.getElementById('email-service-details');
    const emailDeliveryInfo = document.getElementById('email-delivery-info');
    const phoneFormattedInfo = document.getElementById('phone-formatted-info');
    const formattedPhoneSpan = document.getElementById('formatted-phone');

    // ============================================================
    // MULTI-CHANNEL HELPERS
    // ============================================================
    function getSelectedChannels() {
        return Array.from(
            document.querySelectorAll('input[name="invitation_channels[]"]:checked')
        ).filter(cb => !cb.disabled && isChannelGloballyReady(cb.value)).map(cb => cb.value);
    }

    function updateChannelVisualState(checkbox) {
        const option = checkbox.closest('.invitation-channel-option');
        if (!option) return;
        if (checkbox.disabled) {
            option.classList.remove('channel-selected');
            return;
        }
        if (checkbox.checked) {
            option.classList.add('channel-selected');
            option.style.borderColor = 'var(--primary)';
            option.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        } else {
            option.classList.remove('channel-selected');
            option.style.borderColor = 'var(--border-color)';
            option.style.backgroundColor = 'var(--bg-secondary)';
        }
    }

    function updateInvitationChannelsAvailability() {
        document.querySelectorAll('.invitation-channel-option').forEach(option => {
            const channel = option.dataset.channel;
            const checkbox = option.querySelector('.invitation-channel-checkbox');
            if (!checkbox) return;

            const globallyReady = isChannelGloballyReady(channel);

            if (!globallyReady) {
                checkbox.disabled = true;
                checkbox.checked = false;
                option.classList.add('channel-disabled');
                option.setAttribute('aria-disabled', 'true');
                option.style.opacity = '0.5';
                option.style.cursor = 'not-allowed';
                option.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
            } else {
                checkbox.disabled = false;
                option.classList.remove('channel-disabled');
                option.removeAttribute('aria-disabled');
                option.style.opacity = '1';
                option.style.cursor = 'pointer';
                option.style.backgroundColor = 'var(--bg-secondary)';
            }

            updateChannelVisualState(checkbox);
        });
    }

    function updatePhoneValidation() {
        const phone = agentPhoneInput.value.trim();
        if (phone === '') {
            phoneValidation.classList.add('hidden');
            phoneFormattedInfo.classList.add('hidden');
            updateInvitationChannelsUI();
            return;
        }

        phoneValidation.classList.remove('hidden');

        if (validatePhoneNumber(phone)) {
            phoneValidIcon.classList.remove('hidden');
            phoneInvalidIcon.classList.add('hidden');
            agentPhoneInput.classList.remove('border-red-500');
            agentPhoneInput.classList.add('border-green-500');
            formattedPhoneSpan.textContent = formatPhoneNumber(phone);
            phoneFormattedInfo.classList.remove('hidden');
        } else {
            phoneValidIcon.classList.add('hidden');
            phoneInvalidIcon.classList.remove('hidden');
            agentPhoneInput.classList.remove('border-green-500');
            agentPhoneInput.classList.add('border-red-500');
            phoneFormattedInfo.classList.add('hidden');
        }
        updateInvitationChannelsUI();
    }

    function updateExistingAgentInfo() {
        const selectedOption = agentSelect.options[agentSelect.selectedIndex];
        if (selectedOption.value) {
            const phone = selectedOption.getAttribute('data-phone');
            const email = selectedOption.getAttribute('data-email');
            const status = selectedOption.getAttribute('data-status');
            const verified = selectedOption.getAttribute('data-verified') === 'true';
            const invitationAccepted = selectedOption.getAttribute('data-invitation-accepted') === 'true';

            existingAgentInfo.classList.remove('hidden');

            if (phone) {
                agentPhoneInfo.classList.remove('hidden');
                agentPhoneText.textContent = phone;
                agentPhoneText.className = verified ? 'text-green-600' : 'text-yellow-600';
            } else {
                agentPhoneInfo.classList.add('hidden');
            }

            if (email) {
                agentEmailInfo.classList.remove('hidden');
                agentEmailText.textContent = email;
            } else {
                agentEmailInfo.classList.add('hidden');
            }

            agentStatusInfo.classList.remove('hidden');
            let statusColor = 'text-gray-600';
            let statusText = status;
            if (status === 'active') { statusColor = 'text-green-600'; statusText = 'Active'; }
            else if (status === 'pending') { statusColor = 'text-yellow-600'; statusText = 'Pending Invitation'; }
            else if (status === 'suspended') { statusColor = 'text-red-600'; statusText = 'Suspended'; }
            agentStatusText.innerHTML = `<span class="${statusColor}">${statusText}</span>`;

            agentVerificationInfo.classList.remove('hidden');
            if (invitationAccepted) {
                agentVerificationText.innerHTML = '<span class="text-green-600">Invitation Accepted</span>';
            } else if (verified) {
                agentVerificationText.innerHTML = '<span class="text-blue-600">Phone Verified - Pending Invitation</span>';
            } else {
                agentVerificationText.innerHTML = '<span class="text-yellow-600">Pending Verification</span>';
            }
        } else {
            existingAgentInfo.classList.add('hidden');
        }
        updateInvitationChannelsUI();
    }

    function updateInvitationChannelsUI() {
        const selectedChannels = getSelectedChannels();
        const agentPhone = agentPhoneInput.value.trim();
        const agentEmail = agentEmailInput.value.trim();
        const hasExistingAgent = agentSelect.value;

        document.querySelectorAll('.invitation-channel-option').forEach(option => {
            const checkbox = option.querySelector('.invitation-channel-checkbox');
            if (checkbox) updateChannelVisualState(checkbox);
        });

        if (selectedChannels.includes('email')) {
            emailServiceDetails.classList.remove('hidden');
            emailDeliveryInfo.classList.remove('hidden');
        } else {
            emailServiceDetails.classList.add('hidden');
            emailDeliveryInfo.classList.add('hidden');
        }

        if (selectedChannels.length > 0 && !hasExistingAgent) {
            const needsPhone = selectedChannels.some(c => ['sms', 'whatsapp'].includes(c));
            const needsEmail = selectedChannels.includes('email');

            if ((needsPhone && !agentPhone) || (needsEmail && !agentEmail)) {
                channelRequirements.classList.remove('hidden');
                const missing = [];
                if (needsPhone && !agentPhone) missing.push('phone number');
                if (needsEmail && !agentEmail) missing.push('email address');
                requirementsText.textContent = `A valid ${missing.join(' and ')} is required for the selected channels.`;
            } else {
                channelRequirements.classList.add('hidden');
            }
        } else {
            channelRequirements.classList.add('hidden');
        }

        if (selectedChannels.length === 0) {
            invitationChannelBadge.textContent = 'Select channels';
            invitationChannelBadge.style.backgroundColor = 'rgba(var(--primary-rgb), 0.2)';
            invitationChannelBadge.style.color = 'var(--primary)';
        } else {
            invitationChannelBadge.textContent = selectedChannels.map(c => c.toUpperCase()).join(' + ');
            if (selectedChannels.length === 1 && selectedChannels[0] === 'whatsapp') {
                invitationChannelBadge.style.backgroundColor = 'rgba(37, 211, 102, 0.2)';
                invitationChannelBadge.style.color = '#25D366';
            } else if (selectedChannels.length === 1 && selectedChannels[0] === 'email') {
                invitationChannelBadge.style.backgroundColor = 'rgba(var(--info-rgb), 0.2)';
                invitationChannelBadge.style.color = 'var(--info)';
            } else {
                invitationChannelBadge.style.backgroundColor = 'rgba(var(--primary-rgb), 0.2)';
                invitationChannelBadge.style.color = 'var(--primary)';
            }
        }

        if (selectedChannels.includes('email')) {
            emailRequirementNote.style.color = 'var(--warning)';
            emailRequirementNote.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>Required for email invitations';
        } else {
            emailRequirementNote.style.color = 'var(--text-secondary)';
            emailRequirementNote.textContent = 'Required if selecting email channel';
        }

        updateInvitationPreview();
    }

    function updateInvitationPreview() {
        const hasAgent = agentSelect.value || (agentPhoneInput.value.trim() && agentNameInput.value.trim());
        if (!hasAgent) {
            invitationPreviewContainer.classList.add('hidden');
            return;
        }

        invitationPreviewContainer.classList.remove('hidden');

        let agentName = 'Field Agent';
        let agentPhone = '';
        let agentEmail = '';

        if (agentSelect.value) {
            const selectedOption = agentSelect.options[agentSelect.selectedIndex];
            agentName = selectedOption.text.split(' (')[0];
            agentPhone = selectedOption.getAttribute('data-phone') || '';
            agentEmail = selectedOption.getAttribute('data-email') || '';
        } else if (agentPhoneInput.value.trim() && agentNameInput.value.trim()) {
            agentName = agentNameInput.value.trim();
            agentPhone = formatPhoneNumber(agentPhoneInput.value.trim());
            agentEmail = agentEmailInput.value.trim();
        }

        const zone = document.getElementById('zone').value || 'Zone';
        const section = document.getElementById('section').value;
        const startDate = document.getElementById('registration_start_date')?.value || '';
        const endDate = document.getElementById('registration_end_date')?.value || '';
        const estimatedHouses = document.getElementById('estimated_houses').value || '10';
        const instructions = document.getElementById('instructions')?.value || 'Follow the assigned route';

        const selectedChannels = getSelectedChannels();

        let invitationContent = `FIELD AGENT INVITATION\n\nHello ${agentName}!\n\nYou have been assigned as a Field Agent.\n\n📍 Zone: ${zone}${section ? ', ' + section : ''}\n📅 Period: ${startDate} to ${endDate}\n🏠 Properties: ${estimatedHouses}\n📨 Channels: ${selectedChannels.map(c => c.toUpperCase()).join(', ') || 'None selected'}\n\n🔐 Setup: You will receive a secure link to create your account.\n\n📋 Instructions:\n${instructions}\n\nThank you!`;

        invitationPreviewContent.textContent = invitationContent;
    }

    // ============================================================
    // PATTERN LOGIC
    // ============================================================
    function getCombinedStartingExample(pattern, sequenceType) {
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        if (isNumberThenLetter) {
            switch (sequenceType) {
                case 'even_only': return '2A';
                case 'odd_only': return '1A';
                default: return '1A';
            }
        } else {
            switch (sequenceType) {
                case 'even_only': return 'A2';
                case 'odd_only': return 'A1';
                default: return 'A1';
            }
        }
    }

    function getNumberStartingExample(sequenceType) {
        switch (sequenceType) {
            case 'even_only': return '2';
            case 'odd_only': return '1';
            default: return '1';
        }
    }

    function updatePatternHelper(pattern) {
        let helperText = '';
        let placeholder = '';
        let example = '';

        if (pattern === 'custom') pattern = customPatternInput.value;

        const sequenceType = sequenceTypeInput.value;
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        const isLetterThenNumber = isLetterThenNumberPattern(pattern);

        if (isNumberThenLetter) {
            helperText = 'Enter starting point with number followed by letter';
            placeholder = 'e.g., 1A, 2B, 5C';
            example = getCombinedStartingExample(pattern, sequenceType);
        } else if (isLetterThenNumber) {
            helperText = 'Enter starting point with letter followed by number';
            placeholder = 'e.g., A1, B2, C5';
            example = getCombinedStartingExample(pattern, sequenceType);
        } else if (pattern.includes('{letter}')) {
            helperText = 'Enter starting letter';
            placeholder = 'e.g., A, B, C';
            example = 'A';
        } else if (pattern.includes('{number}')) {
            helperText = 'Enter starting number';
            placeholder = 'e.g., 1, 2, 3';
            example = getNumberStartingExample(sequenceType);
        } else {
            helperText = 'Enter a starting point for the sequence';
            placeholder = 'e.g., A, 1, A1, 1A';
            example = 'A1';
        }

        startingPointHelper.textContent = helperText;
        startingPointInput.placeholder = placeholder;
        updateSequenceTypeRequirements(pattern, sequenceType);
        updateSequenceExamples(pattern, sequenceType);

        if (!startingPointInput.value || !validateStartingPoint(pattern, startingPointInput.value)) {
            startingPointInput.value = example;
        }
    }

    function updateSequenceTypeRequirements(pattern, sequenceType) {
        let requirementsText = '';
        if (pattern.includes('{number}')) {
            switch (sequenceType) {
                case 'even_only': requirementsText = 'Numbers will follow even sequence (2, 4, 6, 8...)'; break;
                case 'odd_only': requirementsText = 'Numbers will follow odd sequence (1, 3, 5, 7...)'; break;
                default: requirementsText = 'Numbers will follow sequential order (1, 2, 3, 4...)';
            }
            sequenceRequirementsText.textContent = requirementsText;
            sequenceTypeRequirements.classList.remove('hidden');
        } else {
            sequenceTypeRequirements.classList.add('hidden');
        }
    }

    function updateSequenceExamples(pattern, sequenceType) {
        if (!pattern.includes('{number}')) {
            sequenceExamples.classList.add('hidden');
            return;
        }

        sequenceExamples.classList.remove('hidden');
        validExamples.innerHTML = '';
        invalidExamples.innerHTML = '';

        let validItems = [], invalidItems = [];
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        const isLetterThenNumber = isLetterThenNumberPattern(pattern);

        if (isNumberThenLetter) {
            switch (sequenceType) {
                case 'even_only': validItems = ['2A', '4B', '6C', '8D']; invalidItems = ['1A', '3B', '5C', '7D', 'A2', 'B4']; break;
                case 'odd_only': validItems = ['1A', '3B', '5C', '7D']; invalidItems = ['2A', '4B', '6C', '8D', 'A1', 'B3']; break;
                default: validItems = ['1A', '2A', '3B', '4B']; invalidItems = ['A', '1', 'A1', '1A1', 'A1A'];
            }
        } else if (isLetterThenNumber) {
            switch (sequenceType) {
                case 'even_only': validItems = ['A2', 'B4', 'C6', 'D8']; invalidItems = ['A1', 'B3', 'C5', 'D7', '2A', '4B']; break;
                case 'odd_only': validItems = ['A1', 'B3', 'C5', 'D7']; invalidItems = ['A2', 'B4', 'C6', 'D8', '1A', '3B']; break;
                default: validItems = ['A1', 'A2', 'B1', 'B2']; invalidItems = ['A', '1', 'AB', '12', '1A'];
            }
        } else if (pattern.includes('{number}')) {
            switch (sequenceType) {
                case 'even_only': validItems = ['2', '4', '6', '8']; invalidItems = ['1', '3', '5', '7']; break;
                case 'odd_only': validItems = ['1', '3', '5', '7']; invalidItems = ['2', '4', '6', '8']; break;
                default: validItems = ['1', '2', '3', '4']; invalidItems = ['A', 'B', '1A', 'A1'];
            }
        }

        validItems.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex items-center';
            div.innerHTML = `<i class="fas fa-check text-green-500 mr-1 text-xs"></i><span class="font-mono text-xs">${item}</span>`;
            validExamples.appendChild(div);
        });

        invalidItems.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex items-center';
            div.innerHTML = `<i class="fas fa-times text-red-500 mr-1 text-xs"></i><span class="font-mono text-xs opacity-70">${item}</span>`;
            invalidExamples.appendChild(div);
        });
    }

    function validateStartingPoint(pattern, startingPoint) {
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        const isLetterThenNumber = isLetterThenNumberPattern(pattern);
        const sequenceType = sequenceTypeInput.value;

        if (isNumberThenLetter) {
            if (!/^\d+[A-Za-z]+$/.test(startingPoint)) return false;
            const number = parseInt(startingPoint.match(/^\d+/)[0]);
            if (sequenceType === 'even_only') return number % 2 === 0;
            if (sequenceType === 'odd_only') return number % 2 === 1;
            return true;
        } else if (isLetterThenNumber) {
            if (!/^[A-Za-z]+\d+$/.test(startingPoint)) return false;
            const number = parseInt(startingPoint.replace(/[A-Za-z]/g, ''));
            if (sequenceType === 'even_only') return number % 2 === 0;
            if (sequenceType === 'odd_only') return number % 2 === 1;
            return true;
        } else if (pattern.includes('{letter}') && pattern.includes('{number}')) {
            if (/^[A-Za-z]+\d+$/.test(startingPoint)) {
                const number = parseInt(startingPoint.replace(/[A-Za-z]/g, ''));
                if (sequenceType === 'even_only') return number % 2 === 0;
                if (sequenceType === 'odd_only') return number % 2 === 1;
                return true;
            } else if (/^\d+[A-Za-z]+$/.test(startingPoint)) {
                const number = parseInt(startingPoint.match(/^\d+/)[0]);
                if (sequenceType === 'even_only') return number % 2 === 0;
                if (sequenceType === 'odd_only') return number % 2 === 1;
                return true;
            }
            return false;
        } else if (pattern.includes('{letter}')) {
            return /^[A-Za-z]+$/.test(startingPoint);
        } else if (pattern.includes('{number}')) {
            if (!/^\d+$/.test(startingPoint)) return false;
            const number = parseInt(startingPoint);
            if (sequenceType === 'even_only') return number % 2 === 0;
            if (sequenceType === 'odd_only') return number % 2 === 1;
            return true;
        }
        return true;
    }

    function generateNextName(currentName, pattern, sequenceType) {
        if (!currentName) return getStartingName(pattern, sequenceType);
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        const isLetterThenNumber = isLetterThenNumberPattern(pattern);
        if (isNumberThenLetter || isLetterThenNumber) return generateCombinedNextName(currentName, pattern, sequenceType);
        if (pattern.includes('{letter}')) return incrementLetters(currentName);
        if (pattern.includes('{number}')) return generateNumberNextName(currentName, sequenceType);
        return generateSimpleNextName(currentName);
    }

    function getStartingName(pattern, sequenceType) {
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        const isLetterThenNumber = isLetterThenNumberPattern(pattern);
        if (isNumberThenLetter || isLetterThenNumber) return getCombinedStartingExample(pattern, sequenceType);
        if (pattern.includes('{letter}')) return 'A';
        if (pattern.includes('{number}')) return getNumberStartingExample(sequenceType);
        return '001';
    }

    function generateCombinedNextName(currentName, pattern, sequenceType) {
        const isNumberThenLetter = isNumberThenLetterPattern(pattern);
        if (isNumberThenLetter) {
            const match = currentName.match(/^(\d+)([A-Za-z]+)$/);
            if (match) {
                let number = parseInt(match[1]), letter = match[2];
                number += 1;
                if (sequenceType === 'even_only' && number % 2 !== 0) number += 1;
                if (sequenceType === 'odd_only' && number % 2 === 0) number += 1;
                if (number > 99) {
                    letter = incrementLetters(letter);
                    number = sequenceType === 'even_only' ? 2 : (sequenceType === 'odd_only' ? 1 : 1);
                }
                return number + letter;
            }
        } else {
            const match = currentName.match(/^([A-Za-z]+)(\d+)$/);
            if (match) {
                let letter = match[1], number = parseInt(match[2]);
                number += 1;
                if (sequenceType === 'even_only' && number % 2 !== 0) number += 1;
                if (sequenceType === 'odd_only' && number % 2 === 0) number += 1;
                if (number > 99) {
                    letter = incrementLetters(letter);
                    number = sequenceType === 'even_only' ? 2 : (sequenceType === 'odd_only' ? 1 : 1);
                }
                return letter + number;
            }
        }
        return currentName + '-next';
    }

    function incrementLetters(letters) {
        let length = letters.length;
        for (let i = length - 1; i >= 0; i--) {
            if (letters[i] !== 'Z') {
                letters = letters.substring(0, i) + String.fromCharCode(letters.charCodeAt(i) + 1) + letters.substring(i + 1);
                return letters;
            }
            letters = letters.substring(0, i) + 'A' + letters.substring(i + 1);
        }
        return 'A' + letters;
    }

    function generateNumberNextName(currentName, sequenceType) {
        let number = parseInt(currentName);
        if (sequenceType === 'even_only') return number % 2 === 0 ? number + 2 : number + 1;
        if (sequenceType === 'odd_only') return number % 2 === 1 ? number + 2 : number + 1;
        return number + 1;
    }

    function generateSimpleNextName(currentName) {
        const match = currentName.match(/^(.*?)(\d+)$/);
        if (match) return match[1] + (parseInt(match[2]) + 1);
        return currentName + '-1';
    }

    function updatePatternPreview() {
        let pattern = namingPatternSelect.value === 'custom' ? customPatternInput.value : namingPatternSelect.value;
        const startPoint = startingPointInput.value;
        const sequenceType = sequenceTypeInput.value;

        if (!pattern) {
            patternPreviewContainer.classList.add('hidden');
            patternValidation.classList.add('hidden');
            return;
        }

        const isValid = validateStartingPoint(pattern, startPoint);

        if (!isValid) {
            patternPreviewContainer.classList.add('hidden');
            patternValidation.classList.remove('hidden');
            const isNumberThenLetter = isNumberThenLetterPattern(pattern);
            const isLetterThenNumber = isLetterThenNumberPattern(pattern);
            if (isNumberThenLetter) {
                validationMessage.textContent = 'Starting point must be in format like 1A, 2B for number+letter patterns.';
                if (sequenceType === 'even_only') validationMessage.textContent += ' For even-only sequences, use even numbers (2A, 4B, etc.).';
                else if (sequenceType === 'odd_only') validationMessage.textContent += ' For odd-only sequences, use odd numbers (1A, 3B, etc.).';
            } else if (isLetterThenNumber) {
                validationMessage.textContent = 'Starting point must be in format like A1, B2 for letter+number patterns.';
                if (sequenceType === 'even_only') validationMessage.textContent += ' For even-only sequences, use even numbers (A2, B4, etc.).';
                else if (sequenceType === 'odd_only') validationMessage.textContent += ' For odd-only sequences, use odd numbers (A1, B3, etc.).';
            } else if (pattern.includes('{letter}')) {
                validationMessage.textContent = 'Starting point must contain only letters for letter-only patterns.';
            } else if (pattern.includes('{number}')) {
                validationMessage.textContent = 'Starting point must be a number for number-only patterns.';
                if (sequenceType === 'even_only') validationMessage.textContent += ' For even-only sequences, use even numbers (2, 4, 6, etc.).';
                else if (sequenceType === 'odd_only') validationMessage.textContent += ' For odd-only sequences, use odd numbers (1, 3, 5, etc.).';
            } else {
                validationMessage.textContent = 'Invalid starting point for the selected pattern.';
            }
            return;
        }

        patternValidation.classList.add('hidden');
        patternPreviewContainer.classList.remove('hidden');

        selectedPatternDisplay.textContent = pattern;
        startingPointDisplay.textContent = startPoint;
        let seqDisplay = sequenceType === 'sequential' ? 'Sequential' : (sequenceType === 'even_only' ? 'Even Numbers Only' : 'Odd Numbers Only');
        sequenceTypeDisplay.textContent = seqDisplay;
        patternTypeBadge.textContent = seqDisplay;

        sequencePreview.innerHTML = '';
        let currentName = startPoint;
        for (let i = 0; i < 5; i++) {
            const badge = document.createElement('span');
            badge.className = 'px-3 py-1 rounded-full text-xs font-medium';
            if (i === 0) {
                badge.style.backgroundColor = 'rgba(var(--primary-rgb), 0.2)';
                badge.style.color = 'var(--primary)';
                badge.innerHTML = `<i class="fas fa-play mr-1"></i> ${currentName}`;
            } else {
                badge.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
                badge.style.color = 'var(--secondary)';
                badge.textContent = currentName;
            }
            sequencePreview.appendChild(badge);
            currentName = generateNextName(currentName, pattern, sequenceType);
        }
    }

    function toggleCustomPattern() {
        if (namingPatternSelect.value === 'custom') {
            customPatternContainer.classList.remove('hidden');
            updatePatternHelper(customPatternInput.value);
        } else {
            customPatternContainer.classList.add('hidden');
            updatePatternHelper(namingPatternSelect.value);
        }
        updatePatternPreview();
    }

    // Initialize
    updateInvitationChannelsAvailability();

    const isEditable = !namingPatternSelect.disabled;

    if (isEditable) {
        namingPatternSelect.addEventListener('change', toggleCustomPattern);
        customPatternInput?.addEventListener('input', function() { updatePatternHelper('custom'); updatePatternPreview(); });
        startingPointInput.addEventListener('input', updatePatternPreview);
        sequenceTypeInput.addEventListener('change', function() {
            updatePatternHelper(namingPatternSelect.value === 'custom' ? customPatternInput.value : namingPatternSelect.value);
            updatePatternPreview();
        });

        toggleCustomPattern();
        updatePatternPreview();
    }

    // Agent listeners
    agentSelect?.addEventListener('change', function() {
        updateExistingAgentInfo();
        agentPhoneInput.value = '';
        agentNameInput.value = '';
        agentEmailInput.value = '';
        updatePhoneValidation();
    });

    agentPhoneInput?.addEventListener('input', function() {
        updatePhoneValidation();
        if (this.value.trim()) {
            agentSelect.value = '';
            updateExistingAgentInfo();
        }
    });

    agentNameInput?.addEventListener('input', function() {
        if (this.value.trim()) {
            agentSelect.value = '';
            updateExistingAgentInfo();
        }
    });

    agentEmailInput?.addEventListener('input', updateInvitationChannelsUI);

    document.querySelectorAll('input[name="invitation_channels[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateChannelVisualState(this);
            updateInvitationChannelsUI();
        });
    });

    document.getElementById('zone')?.addEventListener('input', updateInvitationPreview);
    document.getElementById('section')?.addEventListener('input', updateInvitationPreview);
    document.getElementById('registration_start_date')?.addEventListener('change', updateInvitationPreview);
    document.getElementById('registration_end_date')?.addEventListener('change', updateInvitationPreview);
    document.getElementById('instructions')?.addEventListener('input', updateInvitationPreview);

    updateExistingAgentInfo();
    updatePhoneValidation();
    updateInvitationChannelsUI();
});
</script>

<style>
/* Channel option styling */
.invitation-channel-option {
    transition: all 0.3s ease;
    cursor: pointer;
}

.invitation-channel-option:hover:not(.channel-disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

.channel-selected {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}

/* Disabled/unavailable channel */
.invitation-channel-option.channel-disabled {
    cursor: not-allowed !important;
    opacity: 0.55;
    background-color: rgba(var(--secondary-rgb, 148, 163, 184), 0.08) !important;
    border-style: dashed !important;
    position: relative;
}

.invitation-channel-option.channel-disabled::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: repeating-linear-gradient(
        45deg,
        rgba(0, 0, 0, 0.03),
        rgba(0, 0, 0, 0.03) 10px,
        transparent 10px,
        transparent 20px
    );
    pointer-events: none;
    border-radius: inherit;
}

.invitation-channel-option.channel-disabled input {
    cursor: not-allowed;
}

.fa-whatsapp { color: #25D366; }
.text-green-600 { color: #16a34a; }
.text-red-600 { color: #dc2626; }
.text-yellow-600 { color: #ca8a04; }
.text-blue-600 { color: #2563eb; }

/* Section styling */
#email-service-details,
#channel-requirements {
    border-left: 4px solid var(--info);
}

#email-delivery-info {
    border-left: 4px solid var(--success);
}

#sequence-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

code {
    font-family: 'Courier New', monospace;
    background-color: rgba(var(--primary-rgb), 0.1);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: var(--primary);
}

#invitation-preview-content {
    font-family: 'Courier New', monospace;
    line-height: 1.4;
    max-height: 200px;
    overflow-y: auto;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.agent-row {
    transition: all 0.3s ease;
    border-left: 4px solid var(--info);
}

.agent-row:hover {
    transform: translateX(2px);
}

.remove-agent-btn { transition: all 0.2s ease; }
.remove-agent-btn:hover { transform: scale(1.1); }

#phone-validation { pointer-events: none; }

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    #sequence-preview { justify-content: center; }
    .agent-row .grid { grid-template-columns: 1fr; }
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }

.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] #invitation-preview-content {
    background-color: var(--bg-primary);
    border-color: var(--border-color);
}

[data-theme="dark"] .invitation-channel-option.channel-disabled {
    background-color: rgba(255, 255, 255, 0.03) !important;
    border-color: #374151 !important;
}

.hidden { display: none; }
</style>
@endsection