@extends('layouts.app')

@section('title', 'Create Registration Plan')

@php
    /*
     |--------------------------------------------------------------------------
     | Communication-channel availability (mirrors the controller)
     |--------------------------------------------------------------------------
     */
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
    $smsStatus['system_ready'] = $smsReady;
    $smsStatus['can_send']     = $smsStatus['can_send'] ?? $smsReady;
    $smsStatus['health']       = $smsStatus['health']  ?? ($smsReady ? 'healthy' : 'degraded');
    $smsStatus['message']      = $smsStatus['message'] ?? ($smsReady
        ? 'SMS service is operational'
        : 'SMS service is not configured or not ready');

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
    $whatsappStatus['system_ready'] = $whatsappReady;
    $whatsappStatus['can_send']     = $whatsappStatus['can_send'] ?? $whatsappReady;
    $whatsappStatus['health']       = $whatsappStatus['health']  ?? ($whatsappReady ? 'healthy' : 'not_configured');
    $whatsappStatus['message']      = $whatsappStatus['message'] ?? ($whatsappReady
        ? 'WhatsApp service is operational'
        : 'WhatsApp service is not configured');

    if (!isset($emailStatus) || !is_array($emailStatus)) {
        try {
            $emailStatus = \App\Models\SystemSetting::getSettings()->getEmailConfigurationStatus();
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
    $emailStatus['system_ready'] = $emailReady;
    $emailStatus['can_send']     = $emailStatus['can_send'] ?? $emailReady;
    $emailStatus['configured']   = $emailStatus['configured'] ?? $emailReady;
    $emailStatus['health']       = $emailStatus['health']  ?? ($emailReady ? 'healthy' : 'degraded');
    $emailStatus['message']      = $emailStatus['message'] ?? ($emailReady
        ? 'Email service is operational'
        : 'Email service is not configured or not ready');
    $emailStatus['provider']     = $emailStatus['provider'] ?? ($emailStatus['system_email'] ?? 'N/A');

    $anyChannelReady = $smsReady || $whatsappReady || $emailReady;

    // ---------- Channel filter helper (no arrow functions) ----------
    if (!function_exists('_regPlanFilterReadyChannels')) {
        function _regPlanFilterReadyChannels($channels, $smsReady, $whatsappReady, $emailReady)
        {
            $out = [];
            foreach ((array) $channels as $c) {
                if ($c === 'sms'      && $smsReady)      { $out[] = $c; }
                if ($c === 'whatsapp' && $whatsappReady) { $out[] = $c; }
                if ($c === 'email'    && $emailReady)    { $out[] = $c; }
            }
            return array_values(array_unique($out));
        }
    }

    $oldInvitationChannels = old('invitation_channels', []);
    if (!is_array($oldInvitationChannels)) {
        $oldInvitationChannels = [];
    }
    $oldInvitationChannels = _regPlanFilterReadyChannels(
        $oldInvitationChannels, $smsReady, $whatsappReady, $emailReady
    );

    $oldAgentTypes       = old('agent_types', []);
    $oldAssignedAgentIds = old('assigned_agent_ids', []);
    $oldAgentPhones      = old('agent_phones', []);
    $oldAgentNames       = old('agent_names', []);
    $oldAgentEmails      = old('agent_emails', []);
    $oldAgentChannels    = old('agent_invitation_channels', []);
    $oldAssignmentType   = old('agent_assignment_type', 'single');

    // ------------------------------------------------------------------
    // Idempotency token — regenerated per page load. The controller
    // stores it on the new plan; a resubmitted form (double-click,
    // browser back, network retry) re-uses the same token and is
    // rejected server-side instead of creating a duplicate plan.
    // ------------------------------------------------------------------
    $submissionToken = old('submission_token')
        ?: (string) \Illuminate\Support\Str::uuid();

    // Precompute JSON payloads for the JS block (no arrow fns in @json).
    $fieldAgentsPayload = [];
    foreach ($fieldAgents as $agent) {
        $fieldAgentsPayload[] = [
            'id'     => $agent->id,
            'name'   => $agent->name,
            'phone'  => $agent->phone,
            'email'  => $agent->email,
            'status' => $agent->status,
        ];
    }

    $oldPayload = [
        'agentTypes'       => is_array($oldAgentTypes) ? $oldAgentTypes : [],
        'assignedAgentIds' => is_array($oldAssignedAgentIds) ? array_values($oldAssignedAgentIds) : [],
        'agentPhones'      => is_array($oldAgentPhones) ? array_values($oldAgentPhones) : [],
        'agentNames'       => is_array($oldAgentNames) ? array_values($oldAgentNames) : [],
        'agentEmails'      => is_array($oldAgentEmails) ? array_values($oldAgentEmails) : [],
        'agentChannels'    => is_array($oldAgentChannels) ? $oldAgentChannels : [],
        'assignmentType'   => $oldAssignmentType,
    ];

    $channelsPayload = [
        'sms'      => $smsReady,
        'whatsapp' => $whatsappReady,
        'email'    => $emailReady,
    ];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- HEADER --}}
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create Registration Plan</h2>
            <div class="flex items-center space-x-3">
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

    {{-- INFO FLASH — shown when a duplicate submission is redirected --}}
    @if(session('info'))
    <div class="card p-6 animate-slide-down" id="info-message-container">
        <div class="alert" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-2xl mr-3 mt-1" style="color: var(--info);"></i>
                    <div class="flex-1">
                        <h4 class="font-bold text-lg mb-1" style="color: var(--info);">Duplicate Submission Blocked</h4>
                        <div class="text-sm" style="color: var(--text-primary);">
                            {{ session('info') }}
                        </div>
                    </div>
                </div>
                <button onclick="document.getElementById('info-message-container').remove()" class="text-gray-500 hover:text-gray-700 transition-colors ml-4">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- SUCCESS --}}
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

    {{-- FORM --}}
    <form action="{{ route('registration-plans.store') }}" method="POST" id="registration-plan-form" novalidate>
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

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
                                    Use <code>{letter}</code> for alphabetical sequencing,
                                    <code>{number}</code> for numerical sequencing, or combine both
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

                        <!-- Pattern Preview -->
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
                                    <div class="flex flex-wrap gap-2" id="sequence-preview"></div>
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

            <!-- AGENT ASSIGNMENT & INVITATION -->
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

                    <!-- MULTIPLE AGENTS -->
                    <div id="multiple-agents-section" class="space-y-4 hidden">
                        <div class="flex justify-between items-center">
                            <h4 class="font-medium" style="color: var(--text-primary);">Assign Multiple Agents</h4>
                            <button type="button" id="add-agent-btn" class="btn-primary btn-sm flex items-center">
                                <i class="fas fa-plus mr-1"></i> Add Agent
                            </button>
                        </div>

                        <div id="agents-container"></div>

                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            You can assign multiple agents to work on different parts of this area.
                        </div>

                        <div id="multiple-agent-conditional-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                            <div>
                                <label for="registration_start_date_multiple" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Start Date <span class="text-xs font-normal text-gray-500">(optional)</span>
                                </label>
                                <input type="date" class="w-full p-2 border rounded @error('registration_start_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="registration_start_date_multiple" name="registration_start_date" value="{{ old('registration_start_date') }}">
                                @error('registration_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="registration_end_date_multiple" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    End Date <span class="text-xs font-normal text-gray-500">(optional)</span>
                                </label>
                                <input type="date" class="w-full p-2 border rounded @error('registration_end_date') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="registration_end_date_multiple" name="registration_end_date" value="{{ old('registration_end_date') }}">
                                @error('registration_end_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="instructions_multiple" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Instructions for Field Agents <span class="text-xs font-normal text-gray-500">(optional)</span>
                                </label>
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

                    <!-- SINGLE AGENT -->
                    <div id="single-agent-section" class="space-y-4">
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                    <!-- Single Agent Conditional Fields (dates optional) -->
                    <div id="single-agent-conditional-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                        <div>
                            <label for="registration_start_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Start Date <span class="text-xs font-normal text-gray-500">(optional)</span>
                            </label>
                            <input type="date" class="w-full p-2 border rounded @error('registration_start_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="registration_start_date" name="registration_start_date" value="{{ old('registration_start_date') }}">
                            @error('registration_start_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="registration_end_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                End Date <span class="text-xs font-normal text-gray-500">(optional)</span>
                            </label>
                            <input type="date" class="w-full p-2 border rounded @error('registration_end_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="registration_end_date" name="registration_end_date" value="{{ old('registration_end_date') }}">
                            @error('registration_end_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="instructions" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Instructions for Field Agent <span class="text-xs font-normal text-gray-500">(optional)</span>
                            </label>
                            <textarea class="w-full p-2 border rounded @error('instructions') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="instructions" name="instructions" rows="4"
                                      placeholder="Provide specific instructions for the field agent...">{{ old('instructions') }}</textarea>
                            @error('instructions')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Invitation Preview -->
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
                        <div class="rounded p-3 text-sm font-mono whitespace-pre-wrap" id="invitation-preview-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"></div>
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
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status"></div>
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
{{-- SCRIPTS                                                      --}}
{{-- ============================================================ --}}
@section('scripts')
<script>
(function () {
    'use strict';

    const CHANNELS     = {!! json_encode($channelsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const FIELD_AGENTS = {!! json_encode($fieldAgentsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const OLD          = {!! json_encode($oldPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

    const PatternKind = {
        NUMBER_LETTER: 'number_letter',
        LETTER_NUMBER: 'letter_number',
        LETTER_ONLY:   'letter_only',
        NUMBER_ONLY:   'number_only',
        CUSTOM:        'custom',
    };

    function kindOfPattern(pattern, customPattern) {
        var p = pattern;
        if (p === 'custom') p = (customPattern || '').trim();
        if (!p) return PatternKind.CUSTOM;

        var hasNum = p.indexOf('{number}') !== -1;
        var hasLet = p.indexOf('{letter}') !== -1;

        if (hasNum && hasLet) {
            return p.indexOf('{number}') < p.indexOf('{letter}')
                ? PatternKind.NUMBER_LETTER
                : PatternKind.LETTER_NUMBER;
        }
        if (hasLet) return PatternKind.LETTER_ONLY;
        if (hasNum) return PatternKind.NUMBER_ONLY;
        return PatternKind.CUSTOM;
    }

    function describeKind(kind) {
        switch (kind) {
            case PatternKind.NUMBER_LETTER: return 'Number + Letter (e.g. 1A, 2A)';
            case PatternKind.LETTER_NUMBER: return 'Letter + Number (e.g. A1, A2)';
            case PatternKind.LETTER_ONLY:   return 'Letter only (A, B, C)';
            case PatternKind.NUMBER_ONLY:   return 'Number only (1, 2, 3)';
            default:                        return 'Custom';
        }
    }

    function incrementLetters(letters) {
        var arr = letters.split('');
        for (var i = arr.length - 1; i >= 0; i--) {
            if (arr[i] !== 'Z') {
                arr[i] = String.fromCharCode(arr[i].charCodeAt(0) + 1);
                return arr.join('');
            }
            arr[i] = 'A';
        }
        return 'A' + arr.join('');
    }

    function nextNumber(n, seq) {
        if (seq === 'even_only') return n % 2 === 0 ? n + 2 : n + 1;
        if (seq === 'odd_only')  return n % 2 === 1 ? n + 2 : n + 1;
        return n + 1;
    }

    function nextName(current, kind, seq) {
        if (!current) current = '1';

        switch (kind) {
            case PatternKind.NUMBER_LETTER: {
                var m1 = current.match(/^(\d+)([A-Za-z]+)$/);
                if (!m1) return current;
                var num1 = parseInt(m1[1], 10);
                var letters1 = m1[2];
                num1 = nextNumber(num1, seq);
                if (num1 > 99) { letters1 = incrementLetters(letters1); num1 = seq === 'even_only' ? 2 : 1; }
                return num1 + letters1;
            }
            case PatternKind.LETTER_NUMBER: {
                var m2 = current.match(/^([A-Za-z]+)(\d+)$/);
                if (!m2) return current;
                var letters2 = m2[1];
                var num2 = parseInt(m2[2], 10);
                num2 = nextNumber(num2, seq);
                if (num2 > 99) { letters2 = incrementLetters(letters2); num2 = seq === 'even_only' ? 2 : 1; }
                return letters2 + num2;
            }
            case PatternKind.LETTER_ONLY:
                return incrementLetters(current);
            case PatternKind.NUMBER_ONLY: {
                var n = parseInt(current, 10) || 0;
                return String(nextNumber(n, seq));
            }
            default: {
                var m3 = current.match(/^(.*?)(\d+)$/);
                if (m3) return m3[1] + (parseInt(m3[2], 10) + 1);
                return current + '-1';
            }
        }
    }

    function startRules(kind, seq) {
        switch (kind) {
            case PatternKind.NUMBER_LETTER:
                return {
                    placeholder: 'e.g. 1A, 2B',
                    sample: seq === 'even_only' ? '2A' : (seq === 'odd_only' ? '1A' : '1A'),
                    regex: /^\d+[A-Za-z]+$/,
                    hint: 'Must be a number followed by a letter (1A, 2B, …).',
                    evenMsg: 'Number must be even for “even only” sequences.',
                    oddMsg:  'Number must be odd for “odd only” sequences.',
                };
            case PatternKind.LETTER_NUMBER:
                return {
                    placeholder: 'e.g. A1, B2',
                    sample: seq === 'even_only' ? 'A2' : (seq === 'odd_only' ? 'A1' : 'A1'),
                    regex: /^[A-Za-z]+\d+$/,
                    hint: 'Must be a letter followed by a number (A1, B2, …).',
                    evenMsg: 'Number must be even for “even only” sequences.',
                    oddMsg:  'Number must be odd for “odd only” sequences.',
                };
            case PatternKind.LETTER_ONLY:
                return {
                    placeholder: 'e.g. A, B, C',
                    sample: 'A',
                    regex: /^[A-Za-z]+$/,
                    hint: 'Must contain letters only.',
                };
            case PatternKind.NUMBER_ONLY:
                return {
                    placeholder: 'e.g. 1, 2, 3',
                    sample: seq === 'even_only' ? '2' : '1',
                    regex: /^\d+$/,
                    hint: 'Must be a number.',
                    evenMsg: 'Number must be even for “even only” sequences.',
                    oddMsg:  'Number must be odd for “odd only” sequences.',
                };
            default:
                return {
                    placeholder: 'e.g. 001',
                    sample: '001',
                    regex: /^.+$/,
                    hint: 'Custom pattern starting point.',
                };
        }
    }

    function validateStart(value, kind, seq) {
        var rule = startRules(kind, seq);
        if (!value) return { ok: false, msg: 'Starting point is required.' };
        if (!rule.regex.test(value)) return { ok: false, msg: rule.hint };

        var numMatch = value.match(/\d+/);
        if (numMatch) {
            var n = parseInt(numMatch[0], 10);
            if (seq === 'even_only' && n % 2 !== 0) {
                return { ok: false, msg: rule.evenMsg || 'Number must be even for “even only” sequences.' };
            }
            if (seq === 'odd_only' && n % 2 === 0) {
                return { ok: false, msg: rule.oddMsg || 'Number must be odd for “odd only” sequences.' };
            }
        }
        return { ok: true };
    }

    var el = {
        form:                   document.getElementById('registration-plan-form'),
        namingPattern:          document.getElementById('naming_pattern'),
        customPattern:          document.getElementById('custom_pattern'),
        customPatternContainer: document.getElementById('custom_pattern_container'),
        startingPoint:          document.getElementById('starting_point'),
        startingHelper:         document.getElementById('starting_point_helper'),
        seqType:                document.getElementById('sequence_type'),
        seqReqBlock:            document.getElementById('sequence_type_requirements'),
        seqReqText:             document.getElementById('sequence_requirements_text'),
        seqExamples:            document.getElementById('sequence_examples'),
        validExamples:          document.getElementById('valid_examples'),
        invalidExamples:        document.getElementById('invalid_examples'),
        previewContainer:       document.getElementById('pattern-preview-container'),
        patternBadge:           document.getElementById('pattern-type-badge'),
        selectedPatternDisplay: document.getElementById('selected-pattern-display'),
        startingPointDisplay:   document.getElementById('starting-point-display'),
        seqTypeDisplay:         document.getElementById('sequence-type-display'),
        seqPreview:             document.getElementById('sequence-preview'),
        validationBox:          document.getElementById('pattern-validation'),
        validationMsg:          document.getElementById('validation-message'),
        assignmentType:         document.getElementById('agent_assignment_type'),
        singleSection:          document.getElementById('single-agent-section'),
        multipleSection:        document.getElementById('multiple-agents-section'),
        singleConditional:      document.getElementById('single-agent-conditional-fields'),
        multipleConditional:    document.getElementById('multiple-agent-conditional-fields'),
        invitationSection:      document.getElementById('invitation-method-section'),
        assignedAgent:          document.getElementById('assigned_agent_id'),
        existingAgentInfo:      document.getElementById('existing-agent-info'),
        agentName:              document.getElementById('agent_name'),
        agentEmail:             document.getElementById('agent_email'),
        agentPhone:             document.getElementById('agent_phone'),
        phoneValidation:        document.getElementById('phone-validation'),
        phoneValidIcon:         document.getElementById('phone-valid-icon'),
        phoneInvalidIcon:       document.getElementById('phone-invalid-icon'),
        phoneFormattedInfo:     document.getElementById('phone-formatted-info'),
        formattedPhone:         document.getElementById('formatted-phone'),
        invitationPreview:      document.getElementById('invitation-preview-container'),
        invitationPreviewBody:  document.getElementById('invitation-preview-content'),
        invitationChannelBadge: document.getElementById('invitation-channel-badge'),
        emailDeliveryInfo:      document.getElementById('email-delivery-info'),
        agentsContainer:        document.getElementById('agents-container'),
        addAgentBtn:            document.getElementById('add-agent-btn'),
        submitBtn:              document.getElementById('submit-button'),
        formStatus:             document.getElementById('form-status'),
    };

    function readCustomPattern() {
        return el.customPattern && el.customPattern.value
            ? el.customPattern.value.trim()
            : '';
    }

    function currentPattern() {
        var sel = el.namingPattern.value;
        return sel === 'custom' ? (readCustomPattern() || 'custom') : sel;
    }

    function refreshPatternUI(opts) {
        opts = opts || {};
        var rewriteStartingPoint = !!opts.rewriteStartingPoint;

        var pattern = currentPattern();
        var kind    = kindOfPattern(el.namingPattern.value, readCustomPattern());
        var seq     = el.seqType.value;
        var rule    = startRules(kind, seq);

        el.startingPoint.placeholder = rule.placeholder;
        el.startingHelper.textContent = rule.hint;

        if (rewriteStartingPoint || !el.startingPoint.value) {
            el.startingPoint.value = rule.sample;
        }

        if (kind === PatternKind.CUSTOM) {
            el.seqReqBlock.classList.add('hidden');
            el.seqExamples.classList.add('hidden');
        } else {
            el.seqReqBlock.classList.remove('hidden');

            var reqParts = [rule.hint];
            if (seq === 'even_only') reqParts.push('Numbers will be even-only (2, 4, 6, …).');
            if (seq === 'odd_only')  reqParts.push('Numbers will be odd-only (1, 3, 5, …).');
            el.seqReqText.textContent = reqParts.join(' ');

            renderExamples(kind, seq);
        }

        var v = validateStart(el.startingPoint.value, kind, seq);
        if (!v.ok && el.startingPoint.value) {
            el.validationBox.classList.remove('hidden');
            el.validationMsg.textContent = v.msg;
        } else {
            el.validationBox.classList.add('hidden');
        }

        renderPreview(kind, seq, pattern);
        updateFormStatus();
    }

    function renderExamples(kind, seq) {
        el.seqExamples.classList.remove('hidden');
        el.validExamples.innerHTML = '';
        el.invalidExamples.innerHTML = '';

        var valid = [];
        var invalid = [];

        switch (kind) {
            case PatternKind.NUMBER_LETTER:
                if (seq === 'even_only') {
                    valid = ['2A', '4A', '10B'];
                    invalid = ['1A', '3A', '11B'];
                } else if (seq === 'odd_only') {
                    valid = ['1A', '3A', '9B'];
                    invalid = ['2A', '4A', '10B'];
                } else {
                    valid = ['1A', '2A', '3B'];
                    invalid = ['A1', 'A2', 'B3'];
                }
                break;
            case PatternKind.LETTER_NUMBER:
                if (seq === 'even_only') {
                    valid = ['A2', 'B4', 'C10'];
                    invalid = ['A1', 'B3', 'C11'];
                } else if (seq === 'odd_only') {
                    valid = ['A1', 'B3', 'C9'];
                    invalid = ['A2', 'B4', 'C10'];
                } else {
                    valid = ['A1', 'A2', 'B1'];
                    invalid = ['1A', '2A', '1B'];
                }
                break;
            case PatternKind.LETTER_ONLY:
                valid = ['A', 'B', 'Z'];
                invalid = ['1', 'A1', '11'];
                break;
            case PatternKind.NUMBER_ONLY:
                if (seq === 'even_only') {
                    valid = ['2', '4', '10'];
                    invalid = ['1', '3', '11'];
                } else if (seq === 'odd_only') {
                    valid = ['1', '3', '9'];
                    invalid = ['2', '4', '10'];
                } else {
                    valid = ['1', '2', '3'];
                    invalid = ['A', 'B', 'C'];
                }
                break;
        }

        function mk(txt, ok) {
            var row = document.createElement('div');
            row.className = 'flex items-center';
            row.innerHTML =
                '<i class="fas ' + (ok ? 'fa-check-circle' : 'fa-times-circle') + ' mr-1" ' +
                'style="color: ' + (ok ? 'var(--success)' : 'var(--danger)') + ';"></i>' +
                '<span style="color: var(--text-secondary);">' + txt + '</span>';
            return row;
        }

        valid.forEach(function (v) { el.validExamples.appendChild(mk(v, true)); });
        invalid.forEach(function (v) { el.invalidExamples.appendChild(mk(v, false)); });
    }

    function renderPreview(kind, seq, pattern) {
        if (!el.namingPattern.value) {
            el.previewContainer.classList.add('hidden');
            return;
        }
        el.previewContainer.classList.remove('hidden');

        var patternLabel = el.namingPattern.value === 'custom'
            ? (readCustomPattern() || '(no custom pattern)')
            : el.namingPattern.value;

        el.selectedPatternDisplay.textContent = patternLabel;
        el.startingPointDisplay.textContent   = el.startingPoint.value || '—';

        var seqLabels = { sequential: 'Sequential', even_only: 'Even only', odd_only: 'Odd only' };
        el.seqTypeDisplay.textContent = seqLabels[seq] || seq;
        el.patternBadge.textContent = describeKind(kind);

        el.seqPreview.innerHTML = '';
        var current = el.startingPoint.value;
        if (!current) {
            el.seqPreview.innerHTML = '<span class="text-xs" style="color: var(--text-secondary);">Enter a starting point to see the sequence.</span>';
            return;
        }

        for (var i = 0; i < 6; i++) {
            var pill = document.createElement('span');
            pill.className = 'px-2 py-1 rounded text-sm font-mono';
            pill.style.backgroundColor = 'rgba(var(--primary-rgb), 0.15)';
            pill.style.color = 'var(--primary)';
            pill.textContent = current;
            el.seqPreview.appendChild(pill);

            current = nextName(current, kind, seq);
        }
    }

    function refreshAssignmentMode() {
        var mode = el.assignmentType.value;

        if (mode === 'multiple') {
            el.singleSection.classList.add('hidden');
            el.multipleSection.classList.remove('hidden');
            el.invitationSection.classList.add('hidden');
            el.singleConditional.classList.add('hidden');
            el.multipleConditional.classList.remove('hidden');

            if (el.agentsContainer.children.length === 0) {
                addAgentRow();
            }
        } else {
            el.singleSection.classList.remove('hidden');
            el.multipleSection.classList.add('hidden');
            el.multipleConditional.classList.add('hidden');

            refreshInvitationVisibility();
        }
        updateFormStatus();
    }

    function refreshInvitationVisibility() {
        var hasExisting = el.assignedAgent.value !== '';
        var hasNew      = (el.agentName.value.trim() !== '') || (el.agentPhone.value.trim() !== '');
        var anyAgent    = hasExisting || hasNew;

        if (anyAgent && el.assignmentType.value === 'single') {
            el.invitationSection.classList.remove('hidden');
        } else {
            el.invitationSection.classList.add('hidden');
        }
        refreshEmailRequirementNote();
    }

    function refreshEmailRequirementNote() {
        var note = document.getElementById('email-requirement-note');
        if (!note) return;
        var emailChecked = !!document.querySelector('.invitation-channel-checkbox[value="email"]:checked');
        note.textContent = emailChecked
            ? 'Required — you selected the email channel'
            : 'Required if selecting email channel';
        note.style.color = emailChecked ? 'var(--warning)' : 'var(--text-secondary)';
    }

    function refreshExistingAgentInfo() {
        var opt = el.assignedAgent.options[el.assignedAgent.selectedIndex];
        if (!opt || !el.assignedAgent.value) {
            el.existingAgentInfo.classList.add('hidden');
            refreshInvitationVisibility();
            return;
        }
        el.existingAgentInfo.classList.remove('hidden');

        var phone    = opt.dataset.phone || '—';
        var email    = opt.dataset.email || '—';
        var status   = (opt.dataset.status || '').toLowerCase();
        var verified = opt.dataset.verified === 'true';
        var accepted = opt.dataset.invitationAccepted === 'true';

        document.getElementById('agent-phone-text').textContent  = phone;
        document.getElementById('agent-email-text').textContent  = email;
        document.getElementById('agent-status-text').textContent =
            status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';

        var ver = 'Invitation pending';
        if (accepted || verified) ver = 'Already active — channel invitations will be skipped';
        document.getElementById('agent-verification-text').textContent = ver;

        refreshInvitationVisibility();
    }

    function normalizeGhanaPhone(raw) {
        if (!raw) return null;
        var digits = raw.replace(/[^\d+]/g, '');
        if (/^\+233\d{9}$/.test(digits)) return digits;
        if (/^0\d{9}$/.test(digits))     return '+233' + digits.slice(1);
        if (/^233\d{9}$/.test(digits))   return '+' + digits;
        if (/^\+?\d{9,15}$/.test(digits)) return digits.charAt(0) === '+' ? digits : '+' + digits;
        return null;
    }

    function refreshPhoneUI() {
        var raw = el.agentPhone.value.trim();
        if (!raw) {
            el.phoneValidation.classList.add('hidden');
            el.phoneFormattedInfo.classList.add('hidden');
            return;
        }
        var normalized = normalizeGhanaPhone(raw);
        el.phoneValidation.classList.remove('hidden');
        if (normalized) {
            el.phoneValidIcon.classList.remove('hidden');
            el.phoneInvalidIcon.classList.add('hidden');
            el.phoneFormattedInfo.classList.remove('hidden');
            el.formattedPhone.textContent = normalized;
        } else {
            el.phoneValidIcon.classList.add('hidden');
            el.phoneInvalidIcon.classList.remove('hidden');
            el.phoneFormattedInfo.classList.add('hidden');
        }
        refreshInvitationVisibility();
    }

    function selectedChannels(scope) {
        var root = scope || document;
        return Array.prototype.slice.call(
            root.querySelectorAll('.invitation-channel-checkbox:checked')
        ).map(function (c) { return c.value; });
    }

    function renderInvitationPreview() {
        var channels = selectedChannels(el.singleSection);
        if (channels.length === 0) {
            el.invitationPreview.classList.add('hidden');
            return;
        }
        el.invitationPreview.classList.remove('hidden');
        el.invitationChannelBadge.textContent = channels.map(function (c) {
            return c.toUpperCase();
        }).join(' + ');

        var agentName = el.agentName.value.trim()
            || (el.assignedAgent.value
                ? el.assignedAgent.options[el.assignedAgent.selectedIndex].text
                : '');
        var zone    = document.getElementById('zone').value || '—';
        var section = document.getElementById('section').value || '';
        var start   = document.getElementById('registration_start_date').value || 'Not specified';
        var end     = document.getElementById('registration_end_date').value   || 'Not specified';

        var lines = [
            'Hello ' + (agentName || '(Agent Name)') + ',',
            '',
            'You have been assigned a registration plan.',
            '',
            'Zone: ' + zone + (section ? ' / ' + section : ''),
            'Start date: ' + start,
            'End date:   ' + end,
            '',
            'You will receive a secure link to set up your field-agent account.',
            'This link expires in 7 days.',
            '',
            '— Registration System',
        ];

        el.invitationPreviewBody.textContent = lines.join('\n');
        el.emailDeliveryInfo.classList.toggle('hidden', channels.indexOf('email') === -1);
    }

    var agentRowSeq = 0;

    function addAgentRow(prefill) {
        agentRowSeq += 1;
        var idx = agentRowSeq;

        var wrapper = document.createElement('div');
        wrapper.className = 'agent-row rounded border p-4 space-y-3';
        wrapper.style.backgroundColor = 'var(--bg-secondary)';
        wrapper.style.borderColor = 'var(--border-color)';
        wrapper.dataset.rowIndex = String(idx);

        var agentOptionsHtml = FIELD_AGENTS.map(function (a) {
            return '<option value="' + a.id + '">' +
                a.name +
                (a.phone ? ' (' + a.phone + ')' : '') +
                ' - ' + a.status +
                '</option>';
        }).join('');

        wrapper.innerHTML =
            '<div class="flex justify-between items-center">' +
                '<span class="font-medium text-sm" style="color: var(--text-primary);">Agent #<span class="agent-number">' + idx + '</span></span>' +
                '<button type="button" class="remove-agent-btn text-sm" style="color: var(--danger);">' +
                    '<i class="fas fa-trash mr-1"></i> Remove' +
                '</button>' +
            '</div>' +

            '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">' +
                '<div>' +
                    '<label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Agent Type</label>' +
                    '<select name="agent_types[' + idx + ']" class="agent-type-select w-full p-2 border rounded" ' +
                        'style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">' +
                        '<option value="existing">Existing Agent</option>' +
                        '<option value="new">New Agent</option>' +
                    '</select>' +
                '</div>' +

                '<div class="existing-agent-fields">' +
                    '<label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Select Agent</label>' +
                    '<select name="assigned_agent_ids[' + idx + ']" class="w-full p-2 border rounded" ' +
                        'style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">' +
                        '<option value="">— Select —</option>' +
                        agentOptionsHtml +
                    '</select>' +
                '</div>' +

                '<div class="new-agent-fields hidden md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-2">' +
                    '<input type="text" name="agent_names[' + idx + ']" placeholder="Name" class="w-full p-2 border rounded" ' +
                        'style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">' +
                    '<input type="tel" name="agent_phones[' + idx + ']" placeholder="Phone (e.g. 0595652410)" class="w-full p-2 border rounded" ' +
                        'style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">' +
                    '<input type="email" name="agent_emails[' + idx + ']" placeholder="Email (optional)" class="w-full p-2 border rounded" ' +
                        'style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">' +
                '</div>' +
            '</div>' +

            '<div>' +
                '<label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Invitation Channels (optional)</label>' +
                '<div class="flex flex-wrap gap-3">' +
                    '<label class="flex items-center text-sm ' + (CHANNELS.sms ? '' : 'opacity-50') + '" style="color: var(--text-primary);">' +
                        '<input type="checkbox" name="agent_invitation_channels[' + idx + '][]" value="sms" class="mr-1" ' + (CHANNELS.sms ? '' : 'disabled') + '>' +
                        'SMS' +
                    '</label>' +
                    '<label class="flex items-center text-sm ' + (CHANNELS.whatsapp ? '' : 'opacity-50') + '" style="color: var(--text-primary);">' +
                        '<input type="checkbox" name="agent_invitation_channels[' + idx + '][]" value="whatsapp" class="mr-1" ' + (CHANNELS.whatsapp ? '' : 'disabled') + '>' +
                        'WhatsApp' +
                    '</label>' +
                    '<label class="flex items-center text-sm ' + (CHANNELS.email ? '' : 'opacity-50') + '" style="color: var(--text-primary);">' +
                        '<input type="checkbox" name="agent_invitation_channels[' + idx + '][]" value="email" class="mr-1" ' + (CHANNELS.email ? '' : 'disabled') + '>' +
                        'Email' +
                    '</label>' +
                '</div>' +
            '</div>';

        var typeSelect     = wrapper.querySelector('.agent-type-select');
        var existingFields = wrapper.querySelector('.existing-agent-fields');
        var newFields      = wrapper.querySelector('.new-agent-fields');

        typeSelect.addEventListener('change', function () {
            var isNew = typeSelect.value === 'new';
            existingFields.classList.toggle('hidden', isNew);
            newFields.classList.toggle('hidden', !isNew);
        });

        wrapper.querySelector('.remove-agent-btn').addEventListener('click', function () {
            wrapper.remove();
            renumberAgentRows();
            updateFormStatus();
        });

        el.agentsContainer.appendChild(wrapper);

        if (prefill) {
            if (prefill.type) {
                typeSelect.value = prefill.type;
                typeSelect.dispatchEvent(new Event('change'));
            }
            if (prefill.agentId) {
                var sel = wrapper.querySelector('select[name^="assigned_agent_ids"]');
                if (sel) sel.value = prefill.agentId;
            }
            if (prefill.name)  wrapper.querySelector('[name^="agent_names"]').value  = prefill.name;
            if (prefill.phone) wrapper.querySelector('[name^="agent_phones"]').value = prefill.phone;
            if (prefill.email) wrapper.querySelector('[name^="agent_emails"]').value = prefill.email;

            if (Array.isArray(prefill.channels)) {
                prefill.channels.forEach(function (ch) {
                    var cb = wrapper.querySelector('input[value="' + ch + '"]');
                    if (cb && !cb.disabled) cb.checked = true;
                });
            }
        }

        updateFormStatus();
    }

    function renumberAgentRows() {
        Array.prototype.slice.call(el.agentsContainer.children).forEach(function (row, i) {
            var n = row.querySelector('.agent-number');
            if (n) n.textContent = String(i + 1);
        });
    }

    function updateFormStatus() {
        var mode = el.assignmentType.value;
        var msg = '';
        if (mode === 'single') {
            var hasExisting = el.assignedAgent.value !== '';
            var hasNew      = (el.agentName.value.trim() !== '') || (el.agentPhone.value.trim() !== '');
            msg = (hasExisting || hasNew)
                ? 'Single agent assignment — plan will be saved as "assigned".'
                : 'No agent selected — plan will be saved as "draft".';
        } else {
            var rows = el.agentsContainer.children.length;
            msg = rows > 0
                ? rows + ' agent row(s) — plan will be saved as "assigned".'
                : 'Add at least one agent row — plan will be saved as "draft".';
        }
        el.formStatus.textContent = msg;
    }

    function validateOnSubmit(evt) {
        var mode = el.assignmentType.value;
        var kind = kindOfPattern(el.namingPattern.value, readCustomPattern());
        var seq  = el.seqType.value;

        if (!el.namingPattern.value) {
            evt.preventDefault();
            alert('Please select a naming pattern.');
            el.namingPattern.focus();
            return;
        }
        if (el.namingPattern.value === 'custom' && !readCustomPattern()) {
            evt.preventDefault();
            alert('Please enter a custom naming pattern.');
            el.customPattern.focus();
            return;
        }

        var v = validateStart(el.startingPoint.value, kind, seq);
        if (!v.ok) {
            evt.preventDefault();
            alert('Starting point: ' + v.msg);
            el.startingPoint.focus();
            return;
        }

        if (mode === 'single') {
            var hasExisting  = el.assignedAgent.value !== '';
            var hasName      = el.agentName.value.trim() !== '';
            var hasPhone     = el.agentPhone.value.trim() !== '';
            var hasPartialNew = hasName !== hasPhone;

            if (hasPartialNew) {
                evt.preventDefault();
                alert('To create a new agent, please provide both name and phone number.');
                return;
            }

            if (hasName && hasPhone) {
                var channels = selectedChannels(el.singleSection);
                if (channels.length > 0) {
                    if (channels.indexOf('email') !== -1 && !el.agentEmail.value.trim()) {
                        evt.preventDefault();
                        alert('Email is required for the selected email channel.');
                        el.agentEmail.focus();
                        return;
                    }
                    if ((channels.indexOf('sms') !== -1 || channels.indexOf('whatsapp') !== -1)
                        && !normalizeGhanaPhone(el.agentPhone.value)) {
                        evt.preventDefault();
                        alert('A valid phone number is required for SMS / WhatsApp channels.');
                        el.agentPhone.focus();
                        return;
                    }
                }
            }
        } else {
            var rows = Array.prototype.slice.call(el.agentsContainer.children);
            if (rows.length === 0) return;

            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var type = row.querySelector('.agent-type-select').value;
                if (type === 'existing') {
                    var agentId = row.querySelector('select[name^="assigned_agent_ids"]').value;
                    if (!agentId) {
                        evt.preventDefault();
                        alert('Each existing-agent row must have an agent selected.');
                        return;
                    }
                } else {
                    var name  = row.querySelector('[name^="agent_names"]').value.trim();
                    var phone = row.querySelector('[name^="agent_phones"]').value.trim();
                    if (!name || !phone) {
                        evt.preventDefault();
                        alert('Each new-agent row must have a name and phone number.');
                        return;
                    }
                }
            }
        }

        el.submitBtn.disabled = true;
        el.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating…';
    }

    function wireEvents() {
        el.namingPattern.addEventListener('change', function () {
            var isCustom = el.namingPattern.value === 'custom';
            el.customPatternContainer.classList.toggle('hidden', !isCustom);
            refreshPatternUI({ rewriteStartingPoint: true });
        });

        el.customPattern.addEventListener('input', function () {
            refreshPatternUI({ rewriteStartingPoint: true });
        });

        el.startingPoint.addEventListener('input', function () {
            refreshPatternUI();
        });

        el.seqType.addEventListener('change', function () {
            refreshPatternUI({ rewriteStartingPoint: true });
        });

        el.assignmentType.addEventListener('change', refreshAssignmentMode);
        el.assignedAgent.addEventListener('change', refreshExistingAgentInfo);

        [el.agentName, el.agentPhone, el.agentEmail].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                refreshInvitationVisibility();
                renderInvitationPreview();
            });
        });

        if (el.agentPhone) el.agentPhone.addEventListener('input', refreshPhoneUI);

        Array.prototype.slice.call(
            document.querySelectorAll('.invitation-channel-checkbox')
        ).forEach(function (cb) {
            cb.addEventListener('change', function () {
                var card = cb.closest('.invitation-channel-option');
                if (card) card.classList.toggle('channel-selected', cb.checked);
                refreshEmailRequirementNote();
                renderInvitationPreview();
            });
        });

        Array.prototype.slice.call(
            document.querySelectorAll('.invitation-channel-option.channel-disabled')
        ).forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        el.addAgentBtn.addEventListener('click', function () { addAgentRow(); });
        el.form.addEventListener('submit', validateOnSubmit);
    }

    function bootstrap() {
        wireEvents();

        var oldTypes = Array.isArray(OLD.agentTypes) ? OLD.agentTypes : [];
        if (oldTypes.length > 0) {
            el.assignmentType.value = 'multiple';
            oldTypes.forEach(function (type, idx) {
                addAgentRow({
                    type:     type,
                    agentId:  (OLD.assignedAgentIds || [])[idx],
                    name:     (OLD.agentNames || [])[idx],
                    phone:    (OLD.agentPhones || [])[idx],
                    email:    (OLD.agentEmails || [])[idx],
                    channels: (OLD.agentChannels || {})[idx] || [],
                });
            });
        }

        if (el.namingPattern.value === 'custom') {
            el.customPatternContainer.classList.remove('hidden');
        }

        refreshAssignmentMode();
        refreshExistingAgentInfo();
        refreshPhoneUI();
        refreshPatternUI({ rewriteStartingPoint: !el.startingPoint.value });

        Array.prototype.slice.call(
            document.querySelectorAll('.invitation-channel-checkbox:checked')
        ).forEach(function (cb) {
            var card = cb.closest('.invitation-channel-option');
            if (card) card.classList.add('channel-selected');
        });

        refreshEmailRequirementNote();
        renderInvitationPreview();
        updateFormStatus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
</script>
@endsection

{{-- ============================================================ --}}
{{-- STYLES                                                       --}}
{{-- ============================================================ --}}
@push('styles')
<style>
.invitation-channel-option { transition: all 0.3s ease; cursor: pointer; }
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
        rgba(0, 0, 0, 0.03), rgba(0, 0, 0, 0.03) 10px,
        transparent 10px, transparent 20px
    );
    pointer-events: none;
    border-radius: inherit;
}
.invitation-channel-option.channel-disabled input { cursor: not-allowed; }

.fa-whatsapp { color: #25D366; }
.text-green-600 { color: #16a34a; }
.text-red-600   { color: #dc2626; }
.text-yellow-600{ color: #ca8a04; }
.text-blue-600  { color: #2563eb; }

#email-service-details, #channel-requirements { border-left: 4px solid var(--info); }
#email-delivery-info { border-left: 4px solid var(--success); }

.animate-slide-down { animation: slideDown 0.5s ease-out; }
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

#sequence-preview { display: flex; flex-wrap: wrap; gap: 0.5rem; }

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

.agent-row { transition: all 0.3s ease; border-left: 4px solid var(--info); }
.agent-row:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }

.remove-agent-btn { transition: all 0.2s ease; }
.remove-agent-btn:hover { transform: scale(1.1); }

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

#registration-plan-form input:focus,
#registration-plan-form select:focus,
#registration-plan-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.btn-primary.btn-sm:hover { transform: translateY(-1px); }

.card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); }

[data-theme="dark"] #invitation-preview-content { background-color: var(--bg-primary); border-color: var(--border-color); }
[data-theme="dark"] .card { background-color: var(--card-bg); border-color: var(--border-color); }
[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea { background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); }
[data-theme="dark"] .invitation-channel-option { background-color: var(--bg-secondary); border-color: var(--border-color); }
[data-theme="dark"] .invitation-channel-option.channel-disabled { background-color: rgba(255, 255, 255, 0.03) !important; border-color: #374151 !important; }
[data-theme="dark"] .agent-row { background-color: var(--bg-secondary); border-color: var(--border-color); }
</style>
@endpush