@extends('layouts.app')

@section('title', 'Create Registration Plan')

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
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create Registration Plan</h2>
            <div class="flex items-center space-x-3">
                <!-- Communication Service Status Indicators -->
                <div class="flex items-center space-x-2">
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $whatsappReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $whatsappReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fab fa-whatsapp mr-1"></i>
                        WhatsApp: {{ $whatsappReady ? 'Ready' : 'Limited' }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $smsReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $smsReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas fa-comment-alt mr-1"></i>
                        SMS: {{ $smsReady ? 'Ready' : 'Limited' }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $emailReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $emailReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas {{ $emailReady ? 'fa-envelope' : 'fa-envelope-open' }} mr-1"></i>
                        Email: {{ $emailReady ? 'Ready' : 'Limited' }}
                    </div>
                </div>

                <a href="{{ route('registration-plans.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
            </div>
        </div>
    </div>

    <!-- SUCCESS MESSAGE SECTION -->
    @if(session('success'))
    <div class="card p-6 animate-slide-down" id="success-message-container">
        <div class="alert alert-success">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-2xl mr-3 mt-1" style="color: var(--success);"></i>
                    <div class="flex-1">
                        <h4 class="font-bold text-lg mb-2" style="color: var(--success);">✓ Registration Plan Created Successfully!</h4>
                        <div class="whitespace-pre-line text-sm" style="color: var(--text-primary);">
                            {{ session('success') }}
                        </div>

                        @if(session('created_plan_id'))
                        <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div class="flex items-center p-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                    <i class="fas fa-hashtag mr-2" style="color: var(--primary);"></i>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Plan ID</span>
                                        <p class="font-semibold" style="color: var(--text-primary);">#{{ session('created_plan_id') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center p-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                    <i class="fas fa-map-marker-alt mr-2" style="color: var(--primary);"></i>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Zone</span>
                                        <p class="font-semibold" style="color: var(--text-primary);">{{ session('created_plan_zone') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center p-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                    <i class="fas fa-flag-checkered mr-2" style="color: var(--primary);"></i>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Status</span>
                                        <p class="font-semibold" style="color: {{ session('created_plan_status') == 'assigned' ? 'var(--success)' : 'var(--warning)' }};">
                                            {{ ucfirst(session('created_plan_status')) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center p-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                                    <div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Agents Assigned</span>
                                        <p class="font-semibold" style="color: var(--text-primary);">{{ session('agent_count', 0) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(session('invitation_count', 0) > 0)
                            <div class="mt-3 pt-2">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-envelope-open-text mr-2" style="color: var(--success);"></i>
                                    <span style="color: var(--text-secondary);">
                                        Invitations sent successfully to {{ session('invitation_count') }} agent(s)
                                    </span>
                                </div>
                            </div>
                            @endif

                            @if(session('new_agents_created') && count(session('new_agents_created')) > 0)
                            <div class="mt-3 pt-2">
                                <div class="flex items-start text-sm">
                                    <i class="fas fa-user-plus mr-2 mt-0.5" style="color: var(--info);"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">New Agents Created:</span>
                                        <div class="mt-1 space-y-1">
                                            @foreach(session('new_agents_created') as $agentId => $agentData)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                • {{ $agentData['name'] }} - {{ $agentData['phone'] }}
                                                @if($agentData['email'] ?? false) ({{ $agentData['email'] }}) @endif
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif

                        <div class="mt-4 pt-3 border-t flex flex-wrap gap-3" style="border-color: var(--border-color);">
                            @if(session('created_plan_id'))
                            <a href="{{ route('registration-plans.show', session('created_plan_id')) }}" class="btn-primary flex items-center">
                                <i class="fas fa-eye mr-2"></i> View Created Plan
                            </a>
                            @endif
                            <a href="{{ route('registration-plans.create') }}" class="btn-primary flex items-center" style="background-color: var(--success); border-color: var(--success);">
                                <i class="fas fa-plus mr-2"></i> Create Another Plan
                            </a>
                            <a href="{{ route('registration-plans.index') }}" class="btn-secondary flex items-center">
                                <i class="fas fa-list mr-2"></i> View All Plans
                            </a>
                            <button type="button" onclick="document.getElementById('success-message-container').remove()" class="btn-secondary flex items-center">
                                <i class="fas fa-times mr-2"></i> Dismiss
                            </button>
                        </div>
                    </div>
                </div>
                <button onclick="document.getElementById('success-message-container').remove()" class="text-gray-500 hover:text-gray-700 transition-colors ml-4">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('registration-plans.store') }}" method="POST" id="registration-plan-form">
        @csrf

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
                                   id="zone" name="zone" value="{{ old('zone') }}" required
                                   placeholder="e.g., Zone A, North Sector">
                            @error('zone')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="section" class="block mb-2 font-medium" style="color: var(--text-primary);">Section</label>
                            <input type="text" class="w-full p-2 border rounded @error('section') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="section" name="section" value="{{ old('section') }}"
                                   placeholder="e.g., Section 1, Residential Block">
                            @error('section')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="boundaries_description" class="block mb-2 font-medium" style="color: var(--text-primary);">Boundaries Description</label>
                            <textarea class="w-full p-2 border rounded @error('boundaries_description') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="boundaries_description" name="boundaries_description" rows="3"
                                      placeholder="Describe the physical boundaries of this area...">{{ old('boundaries_description') }}</textarea>
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
                                    id="naming_pattern" name="naming_pattern" required>
                                <option value="">Select a naming pattern</option>
                                @foreach($namingPatterns as $value => $label)
                                    <option value="{{ $value }}" {{ old('naming_pattern') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div id="custom_pattern_container" class="mt-2 hidden">
                                <input type="text" class="w-full p-2 border rounded @error('custom_pattern') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="custom_pattern" name="custom_pattern" value="{{ old('custom_pattern') }}"
                                       placeholder="e.g., APT-{letter}{number}, BLOCK-{number}-UNIT-{letter}">
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

                        <div>
                            <label for="starting_point" class="block mb-2 font-medium" style="color: var(--text-primary);">Starting Point *</label>
                            <input type="text" class="w-full p-2 border rounded @error('starting_point') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="starting_point" name="starting_point" value="{{ old('starting_point', 'A1') }}" required
                                   placeholder="e.g., A, 1, A1, B5">

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

                                <div id="sequence_examples" class="text-xs space-y-1 hidden">
                                    <div class="font-medium" style="color: var(--text-primary);">Examples for your sequence type:</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 mt-1">
                                        <div id="valid_examples" class="space-y-1"></div>
                                        <div id="invalid_examples" class="space-y-1"></div>
                                    </div>
                                </div>
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
                                       id="estimated_houses" name="estimated_houses" value="{{ old('estimated_houses', 10) }}" min="1" max="1000" required>
                                @error('estimated_houses')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="sequence_type" class="block mb-2 font-medium" style="color: var(--text-primary);">Sequence Type *</label>
                                <select class="w-full p-2 border rounded @error('sequence_type') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="sequence_type" name="sequence_type" required>
                                    <option value="sequential" {{ old('sequence_type', 'sequential') == 'sequential' ? 'selected' : '' }}>Sequential (1,2,3...)</option>
                                    <option value="even_only" {{ old('sequence_type') == 'even_only' ? 'selected' : '' }}>Even Numbers Only (2,4,6...)</option>
                                    <option value="odd_only" {{ old('sequence_type') == 'odd_only' ? 'selected' : '' }}>Odd Numbers Only (1,3,5...)</option>
                                </select>
                                @error('sequence_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="agent_assignment_type" class="block mb-2 font-medium" style="color: var(--text-primary);">Agent Assignment Type *</label>
                            <select class="w-full p-2 border rounded @error('agent_assignment_type') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="agent_assignment_type" name="agent_assignment_type" required>
                                <option value="single" {{ old('agent_assignment_type', 'single') == 'single' ? 'selected' : '' }}>Single Agent</option>
                                <option value="multiple" {{ old('agent_assignment_type') == 'multiple' ? 'selected' : '' }}>Multiple Agents</option>
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
                                    </div>
                                </div>
                            </div>
                        </div>

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

                    @if(!$anyChannelReady)
                    <div class="rounded p-4 border-l-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left-color: var(--danger);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--danger);">No Delivery Channels Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    None of the communication services (SMS, WhatsApp, Email) are configured.
                                    Field agent invitations cannot be sent until at least one channel is set up.
                                    You can still create the plan without sending invitations.
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
                    <div id="multiple-agents-section" class="space-y-4 hidden">
                        <div class="flex justify-between items-center">
                            <h4 class="font-medium" style="color: var(--text-primary);">Assign Multiple Agents</h4>
                            <button type="button" id="add-agent-btn" class="btn-primary btn-sm flex items-center">
                                <i class="fas fa-plus mr-1"></i> Add Agent
                            </button>
                        </div>

                        <div id="agents-container">
                        </div>

                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            You can assign multiple agents to work on different parts of this area.
                        </div>

                        <div id="multiple-agent-conditional-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                            <div>
                                <label for="registration_start_date" class="block mb-2 font-medium" style="color: var(--text-primary);">Start Date *</label>
                                <input type="date" class="w-full p-2 border rounded @error('registration_start_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="registration_start_date_multiple" name="registration_start_date" value="{{ old('registration_start_date') }}">
                                @error('registration_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="registration_end_date" class="block mb-2 font-medium" style="color: var(--text-primary);">End Date *</label>
                                <input type="date" class="w-full p-2 border rounded @error('registration_end_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="registration_end_date_multiple" name="registration_end_date" value="{{ old('registration_end_date') }}">
                                @error('registration_end_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="instructions" class="block mb-2 font-medium" style="color: var(--text-primary);">Instructions for Field Agents</label>
                                <textarea class="w-full p-2 border rounded @error('instructions') border-red-500 @enderror"
                                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                          id="instructions_multiple" name="instructions" rows="4"
                                          placeholder="Provide specific instructions for all field agents...">{{ old('instructions') }}</textarea>
                                @error('instructions')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Single Agent Assignment Section -->
                    <div id="single-agent-section" class="space-y-4">
                        <!-- Multi-channel Invitation Section -->
                        <div id="invitation-method-section" class="hidden">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Invitation Channels
                                <span class="text-xs font-normal text-gray-500 ml-1">(Optional for existing agents — select one or more to also send SMS / Email / WhatsApp)</span>
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
                                           {{ in_array('sms', $oldInvitationChannels, true) ? 'checked' : '' }}>
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
                                           {{ in_array('whatsapp', $oldInvitationChannels, true) ? 'checked' : '' }}>
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
                                           {{ in_array('email', $oldInvitationChannels, true) ? 'checked' : '' }}>
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

                            <div id="channel-requirements" class="mt-3 p-3 rounded border hidden" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                    <span id="requirements-text"></span>
                                </div>
                            </div>

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
                                        id="assigned_agent_id" name="assigned_agent_id">
                                    <option value="">Select Existing Agent</option>
                                    @foreach($fieldAgents as $agent)
                                        <option value="{{ $agent->id }}" {{ old('assigned_agent_id') == $agent->id ? 'selected' : '' }}
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
                                               placeholder="Agent Full Name *">
                                        @error('agent_name')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <input type="email" class="w-full p-2 border rounded @error('agent_email') border-red-500 @enderror"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               id="agent_email" name="agent_email" value="{{ old('agent_email') }}"
                                               placeholder="Email Address (required for email invitations)">
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
                                                   placeholder="Phone Number (with or without +233) *">
                                            <div id="phone-validation" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                                <i class="fas fa-check" id="phone-valid-icon" style="color: var(--success);"></i>
                                                <i class="fas fa-times" id="phone-invalid-icon" style="color: var(--danger);"></i>
                                            </div>
                                        </div>
                                        <div id="phone-helper" class="text-sm mt-1 space-y-1">
                                            <div class="flex items-center" id="phone-format-info">
                                                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                                <span style="color: var(--text-secondary);">
                                                    Format: +233XXXXXXXXX or 0XXXXXXXXX (e.g., +233595652410 or 0595652410)
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

                        <div class="text-center">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Leave both fields empty to create a draft plan without agent assignment
                            </p>
                        </div>
                    </div>

                    <!-- Single Agent Conditional Fields Container -->
                    <div id="single-agent-conditional-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                        <div>
                            <label for="registration_start_date" class="block mb-2 font-medium" style="color: var(--text-primary);">Start Date *</label>
                            <input type="date" class="w-full p-2 border rounded @error('registration_start_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="registration_start_date" name="registration_start_date" value="{{ old('registration_start_date') }}">
                            @error('registration_start_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="registration_end_date" class="block mb-2 font-medium" style="color: var(--text-primary);">End Date *</label>
                            <input type="date" class="w-full p-2 border rounded @error('registration_end_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="registration_end_date" name="registration_end_date" value="{{ old('registration_end_date') }}">
                            @error('registration_end_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="instructions" class="block mb-2 font-medium" style="color: var(--text-primary);">Instructions for Field Agent</label>
                            <textarea class="w-full p-2 border rounded @error('instructions') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="instructions" name="instructions" rows="4"
                                      placeholder="Provide specific instructions for the field agent...">{{ old('instructions') }}</textarea>
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
                        </div>
                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="invitation-preview-note">This preview shows the invitation that will be sent to the field agent</span>
                        </div>

                        <div id="email-delivery-info" class="mt-3 p-2 rounded border hidden" style="background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3);">
                            <div class="flex items-center text-sm">
                                <i class="fas fa-paper-plane mr-2" style="color: var(--success);"></i>
                                <span style="color: var(--text-primary);">Email will be sent with automatic retry logic (up to 3 attempts)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status">
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Create Registration Plan
                        </button>
                        <a href="{{ route('registration-plans.index') }}" class="btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

{{-- ============================================================ --}}
{{-- SCRIPTS — the layout already has @yield('scripts'), so the --}}
{{-- @section('scripts') block above is preserved (kept as-is). --}}
{{-- We do NOT change it here — only the <style> moved. --}}
{{-- ============================================================ --}}

@push('styles')
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
.text-red-600   { color: #dc2626; }
.text-yellow-600{ color: #ca8a04; }
.text-blue-600  { color: #2563eb; }

#email-service-details,
#channel-requirements {
    border-left: 4px solid var(--info);
}

#email-delivery-info {
    border-left: 4px solid var(--success);
}

.animate-slide-down {
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to   { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background-color: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 0.5rem;
    padding: 1rem;
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

#phone-validation { pointer-events: none; }

.agent-row {
    transition: all 0.3s ease;
    border-left: 4px solid var(--info);
}

.agent-row:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.remove-agent-btn { transition: all 0.2s ease; }
.remove-agent-btn:hover { transform: scale(1.1); }

/* ============================================================ */
/* SCOPED .hidden OVERRIDE                                      */
/* Prevents this page's styles from hijacking .hidden elsewhere */
/* (e.g. the header search bar) — this is the fix for the       */
/* disappearing-header-search-bar bug.                          */
/* ============================================================ */
#custom_pattern_container.hidden,
#pattern-preview-container.hidden,
#pattern-validation.hidden,
#sequence_type_requirements.hidden,
#sequence_examples.hidden,
#multiple-agents-section.hidden,
#multiple-agent-conditional-fields.hidden,
#single-agent-section.hidden,
#single-agent-conditional-fields.hidden,
#invitation-method-section.hidden,
#invitation-preview-container.hidden,
#channel-requirements.hidden,
#email-service-details.hidden,
#email-delivery-info.hidden,
#existing-agent-info.hidden,
#phone-validation.hidden,
#phone-formatted-info.hidden,
#agent-phone-info.hidden,
#agent-email-info.hidden,
#agent-status-info.hidden,
#agent-verification-info.hidden,
.new-agent-fields.hidden,
.existing-agent-fields.hidden {
    display: none !important;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-2.md\:grid-cols-4 { grid-template-columns: 1fr 1fr; }
    #sequence-preview { justify-content: center; }
    .agent-row .grid { grid-template-columns: 1fr; }
}

/* Focus styles — scoped to this form so they don't affect the header */
#registration-plan-form input:focus,
#registration-plan-form select:focus,
#registration-plan-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.btn-primary.btn-sm:hover { transform: translateY(-1px); }

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

[data-theme="dark"] .card {
    background-color: var(--card-bg);
    border-color: var(--border-color);
}

[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border-color: var(--border-color);
}

[data-theme="dark"] .invitation-channel-option {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
}

[data-theme="dark"] .invitation-channel-option.channel-disabled {
    background-color: rgba(255, 255, 255, 0.03) !important;
    border-color: #374151 !important;
}

[data-theme="dark"] .agent-row {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
}

/* NOTE: the previous global `.hidden { display: none; }` rule has been
 * replaced with the scoped list above. The global rule was leaking to
 * the header and hiding the search bar. */
</style>
@endpush