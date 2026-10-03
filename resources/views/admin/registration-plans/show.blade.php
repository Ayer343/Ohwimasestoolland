@extends('layouts.' . (auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT ? 'field' : 'app'))

@section('title', 'Registration Plan Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="mb-4 md:mb-0">
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                        My Assigned Plan Details
                    @else
                        Registration Plan Details
                    @endif
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                        View details of your assigned property registration plan
                    @else
                        View and manage registration plan details, assignments, and progress
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <!-- FIXED: Back to Plans button with proper routing for field agents -->
                <a href="{{ 
                    auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT 
                        ? route('field-agent.registration-plans.index')
                        : route('registration-plans.index')
                }}" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
                @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT && ($registrationPlan->can_be_edited ?? false))
                    <a href="{{ route('registration-plans.edit', $registrationPlan->id) }}" class="btn-modern flex items-center px-4 py-3">
                        <i class="fas fa-edit mr-2"></i> Edit Plan
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert" style="background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3); color: var(--success);">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline ml-2">{{ session('success') }}</span>
            </div>
            <button type="button" class="text-green-700 hover:text-green-900" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert" style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3); color: var(--danger);">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline ml-2">{{ session('error') }}</span>
            </div>
            <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Field Agent Notice -->
    @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
    <div class="card p-6 mb-6" style="border-left: 4px solid var(--primary); background-color: rgba(var(--primary-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3" style="color: var(--primary); font-size: 1.25rem;"></i>
            <div>
                <h3 class="font-semibold text-sm mb-1" style="color: var(--text-primary);">Read-Only Access</h3>
                <p class="text-sm" style="color: var(--text-secondary);">
                    You are viewing this plan as a field agent. You can see all details but cannot make changes.
                    @if($isAssignedToMe ?? false)
                        <span class="font-medium ml-1" style="color: var(--success);">This plan is assigned to you.</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Overview Card -->
            <div class="card p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Plan Overview</h3>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $statusColors = [
                                'completed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'border' => 'var(--success)'],
                                'in_progress' => ['bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => 'var(--primary)', 'border' => 'var(--primary)'],
                                'assigned' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'border' => 'var(--warning)'],
                                'cancelled' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'border' => 'var(--danger)'],
                                'draft' => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'border' => 'var(--secondary)'],
                            ];
                            $statusConfig = $statusColors[$registrationPlan->status] ?? $statusColors['draft'];
                        @endphp
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium"
                              style="background-color: {{ $statusConfig['bg'] }}; 
                                     color: {{ $statusConfig['text'] }};
                                     border: 1px solid {{ $statusConfig['border'] }};">
                            <i class="fas 
                                {{ $registrationPlan->status == 'completed' ? 'fa-check-circle' : 
                                   ($registrationPlan->status == 'in_progress' ? 'fa-spinner fa-spin' : 
                                   ($registrationPlan->status == 'assigned' ? 'fa-user-clock' : 
                                   ($registrationPlan->status == 'cancelled' ? 'fa-times-circle' : 'fa-edit'))) }} 
                                mr-1.5"></i>
                            {{ ucfirst(str_replace('_', ' ', $registrationPlan->status)) }}
                        </span>
                        @if($registrationPlan->is_global_sequence)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                <i class="fas fa-link mr-1"></i> Global Sequence
                            </span>
                        @endif
                        @if($registrationPlan->agent_assignment_type === 'multiple')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <i class="fas fa-users mr-1"></i> Multiple Agents
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                <i class="fas fa-user mr-1"></i> Single Agent
                            </span>
                        @endif
                        @if($registrationPlan->is_overdue)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                            </span>
                        @endif
                        @if($registrationPlan->is_active)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                <i class="fas fa-play-circle mr-1"></i> Active
                            </span>
                        @endif
                        @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT && ($isAssignedToMe ?? false))
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                <i class="fas fa-user-check mr-1"></i> Assigned to You
                            </span>
                        @endif
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Zone</label>
                        <p class="font-semibold" style="color: var(--text-primary);">{{ $registrationPlan->zone }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Section</label>
                        <p class="font-semibold" style="color: var(--text-primary);">{{ $registrationPlan->section ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Naming Pattern</label>
                        <p class="font-semibold" style="color: var(--text-primary);">
                            <code class="px-2 py-1 rounded text-sm" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                {{ $registrationPlan->naming_pattern }}
                            </code>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Sequence Type</label>
                        <p class="font-semibold" style="color: var(--text-primary);">
                            {{ ucfirst(str_replace('_', ' ', $registrationPlan->sequence_type)) }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Starting Point</label>
                        <p class="font-semibold" style="color: var(--text-primary);">{{ $registrationPlan->starting_point }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Estimated Houses</label>
                        <p class="font-semibold" style="color: var(--text-primary);">{{ $registrationPlan->estimated_houses }}</p>
                    </div>
                </div>

                <!-- Global Sequence Information -->
                @if($registrationPlan->is_global_sequence && $globalSequenceInfo)
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-link mt-1 mr-3" style="color: var(--primary);"></i>
                        <div class="flex-1">
                            <h4 class="font-semibold text-sm mb-2" style="color: var(--text-primary);">Global Sequence Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                @if($registrationPlan->continues_from_plan_id)
                                <div>
                                    <span class="font-medium text-sm mb-1 block" style="color: var(--text-secondary);">Continues From:</span>
                                    <div class="mt-1">
                                        <a href="{{ route('registration-plans.show', $registrationPlan->continues_from_plan_id) }}" 
                                           class="text-sm font-medium hover:underline" style="color: var(--primary);">
                                            Plan #{{ $registrationPlan->continues_from_plan_id }}
                                        </a>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            {{ $registrationPlan->continuedFromPlan->zone ?? 'Unknown' }}
                                            @if($registrationPlan->continuedFromPlan->section ?? false)
                                            , {{ $registrationPlan->continuedFromPlan->section }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @endif
                                <div>
                                    <span class="font-medium text-sm mb-1 block" style="color: var(--text-secondary);">Next Available Name:</span>
                                    <p class="mt-1 font-mono font-bold text-sm" style="color: var(--primary);">
                                        {{ $registrationPlan->next_available_name ?? $registrationPlan->starting_point }}
                                    </p>
                                </div>
                                <div>
                                    <span class="font-medium text-sm mb-1 block" style="color: var(--text-secondary);">Total Properties in Sequence:</span>
                                    <p class="mt-1 text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $globalSequenceInfo['total_properties_in_sequence'] }}
                                    </p>
                                </div>
                                @if($globalSequenceInfo['sequence_plans']->count() > 1)
                                <div class="md:col-span-2">
                                    <span class="font-medium text-sm mb-1 block" style="color: var(--text-secondary);">Sequence Plans ({{ $globalSequenceInfo['sequence_plans']->count() }}):</span>
                                    <div class="mt-1 space-y-1 max-h-24 overflow-y-auto">
                                        @foreach($globalSequenceInfo['sequence_plans'] as $sequencePlan)
                                        <div class="flex items-center justify-between text-xs p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <div>
                                                <a href="{{ route('registration-plans.show', $sequencePlan->id) }}" 
                                                   class="text-sm hover:underline" style="color: var(--primary);">
                                                    Plan #{{ $sequencePlan->id }} ({{ $sequencePlan->zone }})
                                                </a>
                                            </div>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                  style="background-color: {{ ($statusColors[$sequencePlan->status] ?? $statusColors['draft'])['bg'] }}; 
                                                         color: {{ ($statusColors[$sequencePlan->status] ?? $statusColors['draft'])['text'] }};
                                                         border: 1px solid {{ ($statusColors[$sequencePlan->status] ?? $statusColors['draft'])['border'] }};">
                                                {{ ucfirst($sequencePlan->status) }}
                                            </span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Plan ID</label>
                        <p class="font-mono text-sm" style="color: var(--text-secondary);">#{{ $registrationPlan->id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Created</label>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            {{ $registrationPlan->created_at->format('M d, Y H:i') }}<br>
                            <span class="text-xs">by {{ $registrationPlan->creator->name }}</span>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Last Updated</label>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $registrationPlan->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <!-- Progress Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Registration Progress</h3>
                
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium" style="color: var(--text-primary);">Completion Progress</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $progressPercentage }}%</span>
                    </div>
                    <div class="progress-bar" style="height: 8px; border-radius: 4px; background-color: rgba(var(--primary-rgb), 0.1); overflow: hidden;">
                        <div class="progress-bar-fill" style="height: 100%; border-radius: 4px; background-color: 
                            @if($progressPercentage >= 80) var(--success)
                            @elseif($progressPercentage >= 50) var(--primary)
                            @elseif($progressPercentage >= 25) var(--warning)
                            @else var(--danger) @endif; 
                            width: {{ min($progressPercentage, 100) }}%;"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="text-2xl font-bold" style="color: var(--primary);">{{ $registrationPlan->properties_count }}</div>
                        <div class="text-sm" style="color: var(--text-secondary);">Registered</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                        <div class="text-2xl font-bold" style="color: var(--info);">{{ $registrationPlan->estimated_houses }}</div>
                        <div class="text-sm" style="color: var(--text-secondary);">Target</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ max(0, $registrationPlan->estimated_houses - $registrationPlan->properties_count) }}</div>
                        <div class="text-sm" style="color: var(--text-secondary);">Remaining</div>
                    </div>
                </div>

                @if($registrationPlan->estimated_houses > 0)
                    <div class="text-center">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if(($registrationPlan->estimated_houses - $registrationPlan->properties_count) > 0)
                                {{ $registrationPlan->estimated_houses - $registrationPlan->properties_count }} houses remaining to reach target
                            @else
                                <span style="color: var(--success);">🎉 Target achieved!</span>
                            @endif
                        </p>
                    </div>
                @endif

                <!-- Pattern Preview -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-medium mb-3 text-sm" style="color: var(--text-primary);">Naming Sequence Preview</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Current Pattern:</span>
                            <code class="font-mono font-bold">{{ $registrationPlan->naming_pattern }}</code>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Next Available:</span>
                            <span class="font-mono font-bold" style="color: var(--success);">
                                {{ $registrationPlan->next_available_name ?? $registrationPlan->starting_point }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Sequence Preview:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($sequencePreview as $index => $name)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" 
                                    style="@if($index === 0) 
                                            background-color: rgba(var(--success-rgb), 0.1); 
                                            color: var(--success);
                                            border: 1px solid rgba(var(--success-rgb), 0.2);
                                          @else 
                                            background-color: rgba(var(--secondary-rgb), 0.1); 
                                            color: var(--text-secondary);
                                            border: 1px solid rgba(var(--secondary-rgb), 0.2);
                                          @endif">
                                    @if($index === 0)
                                        <i class="fas fa-play mr-1 text-xs"></i> {{ $name }}
                                    @else
                                        {{ $name }}
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Performance Card -->
            @if($agentPerformance && count($agentPerformance) > 0)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Agent Performance</h3>
                
                <div class="space-y-4">
                    @foreach($agentPerformance as $performance)
                    <div class="p-4 rounded-lg transition-all duration-200 hover:transform hover:scale-[1.02]" 
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 gap-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold mr-3"
                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                    {{ substr($performance['agent']->name, 0, 1) }}
                                </div>
                                <div>
                                    <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                                        {{ $performance['agent']->name }}
                                        @if($performance['assignment']->agent_id === auth()->id())
                                            <span class="ml-2 text-xs px-2 py-1 rounded" 
                                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                You
                                            </span>
                                        @endif
                                    </h4>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $performance['agent']->email }}
                                        @if($performance['agent']->phone)
                                            • {{ $performance['agent']->phone }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                                      style="@if($performance['is_active']) 
                                                background-color: rgba(var(--success-rgb), 0.1); 
                                                color: var(--success);
                                                border: 1px solid rgba(var(--success-rgb), 0.2);
                                             @else 
                                                background-color: rgba(var(--secondary-rgb), 0.1); 
                                                color: var(--text-secondary);
                                                border: 1px solid rgba(var(--secondary-rgb), 0.2);
                                             @endif">
                                    {{ $performance['is_active'] ? 'Active' : 'Inactive' }}
                                </span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Assigned: {{ $performance['assignment_date']->format('M d, Y') }}
                                </p>
                            </div>
                        </div>

                        <!-- Performance Metrics -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
                            <div class="text-center">
                                <div class="text-lg font-bold" style="color: var(--primary);">
                                    {{ $performance['properties_registered'] }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Properties</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold" style="color: var(--success);">
                                    {{ $performance['performance_metrics']['completion_rate'] ?? 0 }}%
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Completion</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold" style="color: var(--info);">
                                    {{ $performance['performance_metrics']['average_per_day'] ?? 0 }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Per Day</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold" style="color: var(--warning);">
                                    {{ $performance['performance_metrics']['days_active'] ?? 0 }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Days Active</div>
                            </div>
                        </div>

                        <!-- Assignment Summary -->
                        @if($performance['assignment_summary'])
                        <div class="text-xs" style="color: var(--text-secondary);">
                            <div class="flex justify-between">
                                <span>Last Activity:</span>
                                <span>{{ $performance['assignment_summary']['last_activity'] ?? 'No activity' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Status:</span>
                                {{-- FIXED: Safely access needs_attention key --}}
                                <span style="color: {{ ($performance['assignment_summary']['needs_attention'] ?? false) ? 'var(--danger)' : 'var(--success)' }};">
                                    {{ $performance['assignment_summary']['status'] ?? 'Unknown' }}
                                </span>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons for Admin -->
                        @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
                        <div class="flex justify-end space-x-2 mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                            <button onclick="showAssignmentDetails({{ $registrationPlan->id }}, {{ $performance['assignment']->id }})"
                                    class="btn-sm btn-secondary py-1 px-2 text-xs">
                                <i class="fas fa-chart-bar mr-1"></i> Details
                            </button>
                            <form action="{{ route('registration-plans.send-invitation', $registrationPlan->id) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="agent_id" value="{{ $performance['agent']->id }}">
                                <input type="hidden" name="invitation_method" value="sms">
                                <button type="submit" class="btn-sm btn-primary py-1 px-2 text-xs"
                                        onclick="return confirm('Send SMS invitation to {{ $performance['agent']->name }}?')">
                                    <i class="fas fa-sms mr-1"></i> Resend SMS
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Timeline Card -->
            <div class="card p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Timeline</h3>
                    @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT && !$registrationPlan->registration_start_date && ($registrationPlan->can_be_edited ?? false))
                        <a href="{{ route('registration-plans.edit', $registrationPlan->id) }}" class="btn-sm btn-primary">
                            <i class="fas fa-calendar-plus mr-1"></i> Set Timeline
                        </a>
                    @endif
                </div>
                
                @if($registrationPlan->registration_start_date && $registrationPlan->registration_end_date)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Start Date</label>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ $registrationPlan->registration_start_date->format('M d, Y') }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">End Date</label>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ $registrationPlan->registration_end_date->format('M d, Y') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Duration</label>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ $registrationPlan->duration ?? $registrationPlan->registration_start_date->diffInDays($registrationPlan->registration_end_date) }} days
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Days Remaining</label>
                            <p class="font-semibold @if($registrationPlan->is_overdue) text-red-600 dark:text-red-400 @else text-green-600 dark:text-green-400 @endif">
                                @if($registrationPlan->is_overdue)
                                    <i class="fas fa-clock mr-1"></i> Ended {{ $registrationPlan->registration_end_date->diffForHumans() }}
                                @elseif(($registrationPlan->days_remaining ?? 0) > 0)
                                    <i class="fas fa-clock mr-1"></i> {{ $registrationPlan->days_remaining }} days left
                                @else
                                    <i class="fas fa-check-circle mr-1"></i> Completed
                                @endif
                            </p>
                        </div>
                    </div>
                @else
                    <div class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" 
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-calendar-times text-xl"></i>
                        </div>
                        <p class="text-sm mb-2" style="color: var(--text-secondary);">No timeline set for this plan</p>
                        <p class="text-xs mb-4" style="color: var(--text-secondary);">
                            Set start and end dates to track progress and deadlines
                        </p>
                        @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT && ($registrationPlan->can_be_edited ?? false))
                            <a href="{{ route('registration-plans.edit', $registrationPlan->id) }}" class="btn-modern inline-flex items-center">
                                <i class="fas fa-calendar-plus mr-2"></i> Set Timeline
                            </a>
                        @endif
                    </div>
                @endif

                @if($registrationPlan->is_overdue)
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                            <span class="font-medium text-sm" style="color: var(--danger);">This plan is overdue</span>
                        </div>
                    </div>
                @endif

                @if($registrationPlan->completed_at)
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            <span class="font-medium text-sm" style="color: var(--success);">
                                Completed on {{ $registrationPlan->completed_at->format('M d, Y \a\t H:i') }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Instructions Card -->
            @if($registrationPlan->instructions)
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Agent Instructions</h3>
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <p class="whitespace-pre-wrap text-sm" style="color: var(--text-primary);">{{ $registrationPlan->instructions }}</p>
                    </div>
                </div>
            @endif

            <!-- Boundaries Card -->
            @if($registrationPlan->boundaries_description)
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Boundaries Description</h3>
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <p class="whitespace-pre-wrap text-sm" style="color: var(--text-primary);">{{ $registrationPlan->boundaries_description }}</p>
                    </div>
                </div>
            @endif

            <!-- Recent Properties Card -->
            @if($recentProperties && $recentProperties->count() > 0)
            <div class="card p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Recent Properties</h3>
                    <a href="{{ route('properties.index', ['registration_plan_id' => $registrationPlan->id]) }}" 
                       class="btn-sm btn-secondary">
                        View All ({{ $registrationPlan->properties_count }})
                    </a>
                </div>
                
                <div class="space-y-3">
                    @foreach($recentProperties as $property)
                    <div class="p-4 rounded-lg transition-all duration-200 hover:transform hover:scale-[1.02]" 
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-2 gap-2">
                            <div>
                                <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                                    {{ $property->house_name ?: 'Property #' . $property->id }}
                                </h4>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $property->street_name ?? '' }} {{ $property->house_number ?? '' }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: {{ $statusColors[$property->status] ?? $statusColors['draft']['bg'] }}; 
                                         color: {{ $statusColors[$property->status] ?? $statusColors['draft']['text'] }};
                                         border: 1px solid {{ $statusColors[$property->status] ?? $statusColors['draft']['border'] }};">
                                {{ ucfirst(str_replace('_', ' ', $property->status)) }}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs mb-2">
                            <div>
                                <span style="color: var(--text-secondary);">Registration Pattern:</span>
                                <p class="font-mono font-semibold" style="color: var(--text-primary);">
                                    {{ $property->registration_pattern }}
                                </p>
                            </div>
                            <div>
                                <span style="color: var(--text-secondary);">Registered:</span>
                                <p style="color: var(--text-primary);">
                                    {{ $property->created_at->format('M d, Y') }}
                                </p>
                            </div>
                        </div>

                        @if($property->landlord)
                        <div class="flex items-center text-xs">
                            <i class="fas fa-user mr-2" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-secondary);">Landlord:</span>
                            <span class="ml-1 font-medium" style="color: var(--text-primary);">
                                {{ $property->landlord->name }}
                            </span>
                            @if($property->landlord->phone)
                            <span class="ml-3" style="color: var(--text-secondary);">
                                {{ $property->landlord->phone }}
                            </span>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Enhanced Invitations Card -->
            @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT && $invitationStats['total'] > 0)
            <div class="card p-6" id="invitations">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Invitation History</h3>
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <i class="fas fa-envelope mr-1"></i> {{ $invitationStats['total'] }} Total
                        </span>
                    </div>
                </div>
                
                <!-- Invitation Statistics -->
                <div class="mb-6">
                    <h4 class="font-medium mb-3 text-sm" style="color: var(--text-primary);">Invitation Overview</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                            <div class="text-lg font-bold" style="color: var(--primary);">{{ $invitationStats['total'] }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Total Sent</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                            <div class="text-lg font-bold" style="color: var(--warning);">{{ $invitationStats['pending'] }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                            <div class="text-lg font-bold" style="color: var(--success);">{{ $invitationStats['accepted'] }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Accepted</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                            <div class="text-lg font-bold" style="color: var(--danger);">{{ $invitationStats['expired'] }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Expired</div>
                        </div>
                    </div>
                </div>

                @if($registrationPlan->invitations->count() > 0)
                <div class="space-y-3">
                    <h4 class="font-medium text-sm" style="color: var(--text-primary);">Recent Invitations</h4>
                    @foreach($registrationPlan->invitations->take(5) as $invitation)
                    <div class="p-4 rounded-lg transition-all duration-200 hover:transform hover:scale-[1.02]" 
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 gap-3">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="font-medium text-sm" style="color: var(--text-primary);">
                                        {{ $invitation->agent->name ?? 'Unknown Agent' }}
                                    </span>
                                    <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                          style="@if($invitation->status == 'accepted') 
                                                    background-color: rgba(var(--success-rgb), 0.1); 
                                                    color: var(--success);
                                                    border: 1px solid rgba(var(--success-rgb), 0.2);
                                                 @elseif($invitation->status == 'sent') 
                                                    background-color: rgba(var(--warning-rgb), 0.1); 
                                                    color: var(--warning);
                                                    border: 1px solid rgba(var(--warning-rgb), 0.2);
                                                 @elseif($invitation->status == 'expired') 
                                                    background-color: rgba(var(--danger-rgb), 0.1); 
                                                    color: var(--danger);
                                                    border: 1px solid rgba(var(--danger-rgb), 0.2);
                                                 @elseif($invitation->status == 'failed') 
                                                    background-color: rgba(var(--danger-rgb), 0.1); 
                                                    color: var(--danger);
                                                    border: 1px solid rgba(var(--danger-rgb), 0.2);
                                                 @else 
                                                    background-color: rgba(var(--secondary-rgb), 0.1); 
                                                    color: var(--text-secondary);
                                                    border: 1px solid rgba(var(--secondary-rgb), 0.2);
                                                 @endif">
                                        {{ ucfirst($invitation->status) }}
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" style="color: var(--text-secondary);">Method:</span>
                                        <div class="flex items-center space-x-1">
                                            @switch($invitation->invitation_method)
                                                @case('sms')
                                                    <i class="fas fa-comment-alt" style="color: var(--primary);"></i>
                                                    <span style="color: var(--text-primary);">SMS</span>
                                                    @break
                                                @case('whatsapp')
                                                    <i class="fab fa-whatsapp" style="color: var(--success);"></i>
                                                    <span style="color: var(--text-primary);">WhatsApp</span>
                                                    @break
                                                @case('email')
                                                    <i class="fas fa-envelope" style="color: var(--info);"></i>
                                                    <span style="color: var(--text-primary);">Email</span>
                                                    @break
                                                @case('all_channels')
                                                    <i class="fas fa-broadcast-tower" style="color: var(--warning);"></i>
                                                    <span style="color: var(--text-primary);">All Channels</span>
                                                    @break
                                                @default
                                                    <i class="fas fa-question-circle" style="color: var(--secondary);"></i>
                                                    <span style="color: var(--text-primary);">{{ ucfirst($invitation->invitation_method) }}</span>
                                            @endswitch
                                        </div>
                                    </div>
                                    
                                    @if($invitation->sent_via)
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" style="color: var(--text-secondary);">Provider:</span>
                                        <span style="color: var(--text-primary);">{{ $invitation->sent_via }}</span>
                                    </div>
                                    @endif
                                </div>

                                @if($invitation->delivery_status)
                                <div class="mt-2">
                                    <span class="text-xs font-medium mr-2" style="color: var(--text-secondary);">Delivery:</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                                          style="@if($invitation->delivery_status == 'delivered') 
                                                    background-color: rgba(var(--success-rgb), 0.1); 
                                                    color: var(--success);
                                                 @elseif($invitation->delivery_status == 'failed') 
                                                    background-color: rgba(var(--danger-rgb), 0.1); 
                                                    color: var(--danger);
                                                 @elseif($invitation->delivery_status == 'pending') 
                                                    background-color: rgba(var(--warning-rgb), 0.1); 
                                                    color: var(--warning);
                                                 @else 
                                                    background-color: rgba(var(--secondary-rgb), 0.1); 
                                                    color: var(--text-secondary);
                                                 @endif">
                                        {{ ucfirst($invitation->delivery_status) }}
                                    </span>
                                    @if($invitation->delivery_message)
                                    <span class="text-xs ml-2" style="color: var(--text-secondary);">- {{ $invitation->delivery_message }}</span>
                                    @endif
                                </div>
                                @endif
                            </div>
                            
                            <div class="text-right">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    {{ $invitation->created_at->format('M d, Y') }}
                                </span>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $invitation->created_at->format('H:i') }}
                                </div>
                                @if($invitation->expires_at)
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Expires: {{ $invitation->expires_at->format('M d') }}
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center text-xs gap-2" style="color: var(--text-secondary);">
                            <div>
                                @if($invitation->sent_by)
                                <span>Sent by: {{ $invitation->sentBy->name ?? 'System' }}</span>
                                @endif
                            </div>
                            <div class="flex space-x-2">
                                @if($invitation->message_id)
                                <span title="Message ID">{{ Str::limit($invitation->message_id, 8) }}</span>
                                @endif
                                @if($invitation->retry_count > 0)
                                <span title="Retry attempts">Retries: {{ $invitation->retry_count }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach

                    @if($registrationPlan->invitations->count() > 5)
                    <div class="text-center mt-4">
                        <a href="{{ route('invitations.index', ['registration_plan_id' => $registrationPlan->id]) }}" 
                           class="btn-secondary inline-flex items-center text-sm px-4 py-2">
                            View all {{ $registrationPlan->invitations->count() }} invitations
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @endif

            <!-- Communication Channels Summary Card -->
            @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Communication Channels</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Channel Status -->
                    <div>
                        <h4 class="font-medium mb-3 text-sm" style="color: var(--text-primary);">Channel Status</h4>
                        <div class="space-y-3">
                            @php
                                $channels = [
                                    'sms' => [
                                        'name' => 'SMS',
                                        'icon' => 'fas fa-comment-alt',
                                        'color' => 'var(--primary)',
                                        'status' => $smsStatus['system_ready'] ?? false,
                                        'provider' => $smsStatus['default_provider'] ?? 'Not configured'
                                    ],
                                    'whatsapp' => [
                                        'name' => 'WhatsApp',
                                        'icon' => 'fab fa-whatsapp',
                                        'color' => 'var(--success)',
                                        'status' => $whatsappStatus['system_ready'] ?? false,
                                        'provider' => $whatsappStatus['default_provider'] ?? 'Not configured'
                                    ],
                                    'email' => [
                                        'name' => 'Email',
                                        'icon' => 'fas fa-envelope',
                                        'color' => 'var(--info)',
                                        'status' => $emailStatus['system_ready'] ?? false,
                                        'provider' => $emailStatus['provider'] ?? 'Not configured'
                                    ]
                                ];
                            @endphp

                            @foreach($channels as $channel => $data)
                            <div class="flex items-center justify-between p-3 rounded-lg" 
                                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                <div class="flex items-center">
                                    <i class="{{ $data['icon'] }} mr-3 text-lg" style="color: {{ $data['color'] }};"></i>
                                    <div>
                                        <div class="font-medium text-sm" style="color: var(--text-primary);">{{ $data['name'] }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">{{ $data['provider'] }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                          style="@if($data['status']) 
                                                    background-color: rgba(var(--success-rgb), 0.1); 
                                                    color: var(--success);
                                                    border: 1px solid rgba(var(--success-rgb), 0.2);
                                                 @else 
                                                    background-color: rgba(var(--warning-rgb), 0.1); 
                                                    color: var(--warning);
                                                    border: 1px solid rgba(var(--warning-rgb), 0.2);
                                                 @endif">
                                        @if($data['status'])
                                            <i class="fas fa-check-circle mr-1"></i> Ready
                                        @else
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Limited
                                        @endif
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div>
                        <h4 class="font-medium mb-3 text-sm" style="color: var(--text-primary);">Quick Actions</h4>
                        <div class="space-y-3">
                            @if($registrationPlan->assignedAgents->where('is_active', true)->count() > 0 && in_array($registrationPlan->status, ['assigned', 'in_progress']))
                            <button onclick="document.getElementById('bulk-invitation-modal').classList.remove('hidden')"
                                    class="w-full btn-modern flex items-center justify-center py-3">
                                <i class="fas fa-paper-plane mr-2"></i> Send New Invitations
                            </button>
                            
                            <a href="{{ route('registration-plans.edit', $registrationPlan->id) }}#agent-assignment" 
                               class="w-full btn-secondary flex items-center justify-center py-3">
                                <i class="fas fa-user-plus mr-2"></i> Manage Agents
                            </a>
                            @endif
                            
                            <a href="{{ route('properties.create', ['registration_plan_id' => $registrationPlan->id]) }}" 
                               class="w-full btn-modern flex items-center justify-center py-3"
                               style="background: linear-gradient(to right, var(--success), #20b86d);">
                                <i class="fas fa-plus-circle mr-2"></i> Register Property
                            </a>
                        </div>

                        <!-- Service Status Summary -->
                        <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                            <h5 class="font-medium mb-2 text-xs" style="color: var(--text-primary);">Service Status</h5>
                            <div class="space-y-1 text-xs">
                                @foreach($channels as $channel => $data)
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">{{ $data['name'] }}:</span>
                                    <span style="color: {{ $data['status'] ? 'var(--success)' : 'var(--warning)' }};">
                                        {{ $data['status'] ? 'Operational' : 'Limited' }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar Column -->
        <div class="space-y-6">
            <!-- Actions Card (Admin Only) -->
            @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Plan Actions</h3>
                
                <div class="space-y-3">
                    @if($registrationPlan->status == 'assigned')
                        <form action="{{ route('registration-plans.mark-in-progress', $registrationPlan->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full btn-modern flex items-center justify-center py-3"
                                    style="background: linear-gradient(to right, var(--info), #00b5cc);"
                                    onclick="return confirm('Mark this plan as in progress? All assigned agents will be notified via SMS.')">
                                <i class="fas fa-play-circle mr-2"></i> Mark as In Progress
                            </button>
                        </form>
                    @endif

                    @if(in_array($registrationPlan->status, ['assigned', 'in_progress']))
                        <form action="{{ route('registration-plans.mark-completed', $registrationPlan->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full btn-modern flex items-center justify-center py-3"
                                    style="background: linear-gradient(to right, var(--success), #20b86d);"
                                    onclick="return confirmMarkCompleted({{ $progressPercentage }}, {{ $registrationPlan->properties_count }}, {{ $registrationPlan->estimated_houses }})">
                                <i class="fas fa-check-circle mr-2"></i> Mark as Completed
                            </button>
                        </form>
                    @endif

                    @if(!in_array($registrationPlan->status, ['completed', 'cancelled']))
                        <form action="{{ route('registration-plans.cancel', $registrationPlan->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full btn-modern flex items-center justify-center py-3"
                                    style="background: linear-gradient(to right, var(--danger), #e53935);" 
                                    onclick="return confirm('Are you sure you want to cancel this plan? This will stop all ongoing registration activities and notify all assigned agents.')">
                                <i class="fas fa-times-circle mr-2"></i> Cancel Plan
                            </button>
                        </form>
                    @endif

                    @if($registrationPlan->status == 'cancelled')
                        <form action="{{ route('registration-plans.reactivate', $registrationPlan->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full btn-modern flex items-center justify-center py-3"
                                    style="background: linear-gradient(to right, var(--warning), #ff8c00);"
                                    onclick="return confirm('Reactivate this plan? This will make it available for assignment again.')">
                                <i class="fas fa-redo mr-2"></i> Reactivate Plan
                            </button>
                        </form>
                    @endif

                    <!-- Send Invitations -->
                    @if($registrationPlan->assignedAgents->where('is_active', true)->count() > 0 && in_array($registrationPlan->status, ['assigned', 'in_progress']))
                        <button onclick="document.getElementById('bulk-invitation-modal').classList.remove('hidden')"
                           class="w-full btn-modern flex items-center justify-center py-3"
                           style="background: linear-gradient(to right, var(--info), #00b5cc);">
                            <i class="fas fa-envelope mr-2"></i> Send Invitations
                        </button>
                    @endif

                    <!-- Add Agent (for multiple assignment) -->
                    @if($registrationPlan->agent_assignment_type === 'multiple' && ($registrationPlan->can_be_edited ?? false))
                        <a href="{{ route('registration-plans.edit', $registrationPlan->id) }}#agent-assignment" 
                           class="w-full btn-modern flex items-center justify-center py-3">
                            <i class="fas fa-user-plus mr-2"></i> Add Agent
                        </a>
                    @endif

                    @if($registrationPlan->properties_count > 0)
                        <a href="{{ route('properties.index', ['registration_plan_id' => $registrationPlan->id]) }}" 
                           class="w-full btn-secondary flex items-center justify-center py-3">
                            <i class="fas fa-building mr-2"></i> View Properties ({{ $registrationPlan->properties_count }})
                        </a>
                    @endif

                    @if($registrationPlan->can_be_edited ?? false)
                        <a href="{{ route('registration-plans.edit', $registrationPlan->id) }}" 
                           class="w-full btn-modern flex items-center justify-center py-3">
                            <i class="fas fa-edit mr-2"></i> Edit Plan Details
                        </a>
                    @endif
                </div>

                <!-- Quick Stats -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-medium mb-3 text-sm" style="color: var(--text-primary);">Quick Stats</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Progress:</span>
                            <span style="color: var(--text-primary);">{{ $progressPercentage }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Status:</span>
                            <span class="font-medium">{{ ucfirst($registrationPlan->status) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Agents:</span>
                            <span style="color: var(--text-primary);">
                                {{ $registrationPlan->assignedAgents->where('is_active', true)->count() }} assigned
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Assignment Type:</span>
                            <span class="font-medium">{{ ucfirst($registrationPlan->agent_assignment_type) }}</span>
                        </div>
                        @if($registrationPlan->registration_start_date && $registrationPlan->registration_end_date)
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Time Left:</span>
                                <span style="color: var(--text-primary);">{{ $registrationPlan->days_remaining ?? $registrationPlan->registration_end_date->diffInDays(now()) }} days</span>
                            </div>
                        @endif
                        @if($registrationPlan->is_global_sequence)
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Sequence:</span>
                                <span style="color: var(--primary);">Global</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @else
            <!-- Field Agent Quick Stats Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">My Assignment Summary</h3>
                
                <div class="space-y-4">
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="text-2xl font-bold" style="color: var(--primary);">{{ $progressPercentage }}%</div>
                        <div class="text-sm" style="color: var(--text-secondary);">Overall Progress</div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Your Status:</span>
                            <span class="font-medium">
                                @if($isAssignedToMe ?? false)
                                    <span style="color: var(--success);">Active</span>
                                @else
                                    <span style="color: var(--text-secondary);">Not Assigned</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Plan Status:</span>
                            <span class="font-medium">{{ ucfirst($registrationPlan->status) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Total Agents:</span>
                            <span style="color: var(--text-primary);">
                                {{ $registrationPlan->assignedAgents->where('is_active', true)->count() }}
                            </span>
                        </div>
                        @if($registrationPlan->registration_start_date && $registrationPlan->registration_end_date)
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">Time Left:</span>
                                <span style="color: var(--text-primary);">{{ $registrationPlan->days_remaining ?? $registrationPlan->registration_end_date->diffInDays(now()) }} days</span>
                            </div>
                        @endif
                        @if($registrationPlan->is_overdue)
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">Status:</span>
                                <span class="font-medium" style="color: var(--danger);">Overdue</span>
                            </div>
                        @endif
                    </div>

                    <!-- Field Agent Actions -->
                    @if($isAssignedToMe ?? false)
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <a href="{{ route('field-agent.properties.create', ['registration_plan_id' => $registrationPlan->id]) }}" 
                           class="w-full btn-modern flex items-center justify-center py-3"
                           style="background: linear-gradient(to right, var(--success), #20b86d);">
                            <i class="fas fa-plus-circle mr-2"></i> Register New Property
                        </a>
                        <a href="{{ route('field-agent.properties.index', ['registration_plan_id' => $registrationPlan->id]) }}" 
                           class="w-full btn-secondary flex items-center justify-center py-3 mt-2">
                            <i class="fas fa-list mr-2"></i> View My Properties
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Assignment Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Assignment Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Created By</label>
                        <p class="font-semibold text-sm" style="color: var(--text-primary);">
                            {{ $registrationPlan->creator->name }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            {{ $registrationPlan->creator->email }}
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ $registrationPlan->created_at->format('M d, Y H:i') }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                            Assigned Agents ({{ $registrationPlan->assignedAgents->where('is_active', true)->count() }})
                        </label>
                        @if($registrationPlan->assignedAgents->where('is_active', true)->count() > 0)
                            <div class="space-y-3">
                                @foreach($registrationPlan->assignedAgents->where('is_active', true) as $assignment)
                                <div class="p-3 rounded-lg {{ $assignment->agent_id === auth()->id() ? 'border-green-200' : '' }}" 
                                     style="background-color: {{ $assignment->agent_id === auth()->id() ? 'rgba(var(--success-rgb), 0.05)' : 'var(--card-bg)' }};
                                            border: 1px solid {{ $assignment->agent_id === auth()->id() ? 'rgba(var(--success-rgb), 0.2)' : 'var(--border-color)' }};">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-semibold text-sm" style="color: var(--text-primary);">
                                                {{ $assignment->agent->name }}
                                                @if($assignment->agent_id === auth()->id())
                                                    <span class="ml-1 text-xs px-2 py-1 rounded" 
                                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        You
                                                    </span>
                                                @endif
                                            </p>
                                            <p class="text-xs" style="color: var(--text-secondary);">
                                                {{ $assignment->agent->email }}
                                            </p>
                                            @if($assignment->agent->phone)
                                            <p class="text-xs" style="color: var(--text-secondary);">
                                                {{ $assignment->agent->phone }}
                                            </p>
                                            @endif
                                        </div>
                                        @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
                                        <form action="{{ route('registration-plans.remove-agent', [$registrationPlan->id, $assignment->agent_id]) }}" 
                                              method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs p-1 rounded hover:bg-red-50 transition-colors"
                                                    onclick="return confirm('Remove {{ $assignment->agent->name }} from this plan?')"
                                                    title="Remove Agent" style="color: var(--danger);">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                        Assigned: {{ $assignment->assigned_at->format('M d, Y') }}
                                        @if($assignment->assigner)
                                            by {{ $assignment->assigner->name }}
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm italic p-3 rounded-lg" 
                               style="color: var(--text-secondary); background-color: rgba(var(--secondary-rgb), 0.05); text-align: center;">
                                <i class="fas fa-user-slash mr-1"></i> No agents assigned
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Properties Summary Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Properties Summary</h3>
                
                @if($registrationPlan->properties_count > 0)
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <div class="text-center p-2 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div class="text-lg font-bold" style="color: var(--primary);">{{ $registrationPlan->properties_count }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Total</div>
                            </div>
                            <div class="text-center p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                <div class="text-lg font-bold" style="color: var(--info);">
                                    {{ $recentProperties->where('status', 'active')->count() }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Active</div>
                            </div>
                            <div class="text-center p-2 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                <div class="text-lg font-bold" style="color: var(--success);">
                                    {{ $recentProperties->where('status', 'occupied')->count() }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Occupied</div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <h4 class="font-medium text-xs mb-2" style="color: var(--text-primary);">Recent Properties:</h4>
                            @foreach($recentProperties as $property)
                                <div class="p-2 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                                    <div class="font-medium text-xs" style="color: var(--text-primary);">
                                        {{ $property->house_name ?: 'Property #' . $property->id }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $property->street_name ?? '' }}, {{ $property->house_number ?? '' }}
                                    </div>
                                    @if($property->landlord)
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        Owner: {{ $property->landlord->name }}
                                    </div>
                                    @endif
                                    <div class="text-xs mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs"
                                              style="background-color: {{ $statusColors[$property->status] ?? $statusColors['draft']['bg'] }}; 
                                                     color: {{ $statusColors[$property->status] ?? $statusColors['draft']['text'] }};
                                                     border: 1px solid {{ $statusColors[$property->status] ?? $statusColors['draft']['border'] }};">
                                            {{ ucfirst(str_replace('_', ' ', $property->status)) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @if($registrationPlan->properties_count > 3)
                            <div class="text-center mt-3">
                                <a href="{{ route('properties.index', ['registration_plan_id' => $registrationPlan->id]) }}" 
                                   class="btn-secondary inline-flex items-center text-xs px-3 py-2">
                                    View all {{ $registrationPlan->properties_count }} properties
                                    <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-full mb-2" 
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-building"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">No properties registered yet</p>
                        @if($registrationPlan->is_active)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Properties will appear here as they are registered
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Global Sequence Card -->
            @if($registrationPlan->is_global_sequence && $globalSequenceInfo)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Global Sequence</h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Pattern:</span>
                        <code class="font-mono font-bold">{{ $registrationPlan->naming_pattern }}</code>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Next Available:</span>
                        <span class="font-mono font-bold" style="color: var(--success);">
                            {{ $registrationPlan->next_available_name ?? $registrationPlan->starting_point }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Total in Sequence:</span>
                        <span style="color: var(--text-primary);">{{ $globalSequenceInfo['total_properties_in_sequence'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Sequence Plans:</span>
                        <span style="color: var(--text-primary);">{{ $globalSequenceInfo['sequence_plans']->count() }}</span>
                    </div>
                    
                    @if($registrationPlan->continues_from_plan_id)
                    <div class="pt-2 border-t" style="border-color: var(--border-color);">
                        <p class="text-xs mb-1" style="color: var(--text-secondary);">Continues from:</p>
                        <a href="{{ route('registration-plans.show', $registrationPlan->continues_from_plan_id) }}" 
                           class="text-sm font-medium hover:underline" style="color: var(--primary);">
                            Plan #{{ $registrationPlan->continues_from_plan_id }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Bulk Invitation Modal (Admin Only) -->
@if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
<div id="bulk-invitation-modal" class="modal-overlay hidden">
    <div class="modal-container">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h3 class="modal-title">Send New Invitations</h3>
                <button type="button" 
                        onclick="closeModal('bulk-invitation-modal')"
                        class="modal-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="{{ route('registration-plans.send-bulk-invitations', $registrationPlan->id) }}" method="POST" id="bulk-invitation-form">
                @csrf
                
                <!-- Recipient Selection -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-users mr-2"></i>Select Recipients
                    </label>
                    
                    <!-- Recipient Type Selection -->
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" 
                                id="select-all-btn"
                                class="recipient-type-btn recipient-type-btn-active">
                            <i class="fas fa-users mr-1"></i> All Agents
                        </button>
                        <button type="button" 
                                id="select-specific-btn"
                                class="recipient-type-btn">
                            <i class="fas fa-user-check mr-1"></i> Specific Agents
                        </button>
                    </div>

                    <!-- Specific Agents Selection (Hidden by Default) -->
                    <div id="specific-agents-section" class="hidden specific-agents-section">
                        @foreach($registrationPlan->assignedAgents->where('is_active', true) as $assignment)
                        <div class="specific-agent-item">
                            <input type="checkbox" 
                                   name="selected_agents[]" 
                                   value="{{ $assignment->agent_id }}" 
                                   class="agent-checkbox"
                                   data-agent-name="{{ $assignment->agent->name }}">
                            <div class="specific-agent-info">
                                <span class="specific-agent-name">
                                    {{ $assignment->agent->name }}
                                </span>
                                @if($assignment->agent->phone)
                                <span class="specific-agent-phone">
                                    {{ $assignment->agent->phone }}
                                </span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Selected Agents Summary -->
                    <div id="selected-agents-summary" class="selected-agents-summary">
                        <span id="selected-count">0</span> agents selected
                    </div>
                </div>
                
                <!-- Invitation Method -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-broadcast-tower mr-2"></i>Invitation Method
                    </label>
                    <select name="invitation_method" class="form-select">
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="all_channels">All Channels</option>
                    </select>
                </div>
                
                <!-- Channel Status Indicators -->
                <div class="mb-4 channel-status-section">
                    <h4 class="channel-status-title">Channel Status:</h4>
                    <div class="grid grid-cols-2 gap-2 channel-status-grid">
                        <div class="channel-status-item">
                            <i class="fas fa-comment-alt" style="color: var(--primary);"></i>
                            <span class="channel-status-label">SMS:</span>
                            <span class="channel-status-value {{ $smsStatus['system_ready'] ?? false ? 'status-ready' : 'status-limited' }}">
                                {{ $smsStatus['system_ready'] ?? false ? 'Ready' : 'Limited' }}
                            </span>
                        </div>
                        <div class="channel-status-item">
                            <i class="fab fa-whatsapp" style="color: var(--success);"></i>
                            <span class="channel-status-label">WhatsApp:</span>
                            <span class="channel-status-value {{ $whatsappStatus['system_ready'] ?? false ? 'status-ready' : 'status-limited' }}">
                                {{ $whatsappStatus['system_ready'] ?? false ? 'Ready' : 'Limited' }}
                            </span>
                        </div>
                        <div class="channel-status-item">
                            <i class="fas fa-envelope" style="color: var(--info);"></i>
                            <span class="channel-status-label">Email:</span>
                            <span class="channel-status-value {{ $emailStatus['system_ready'] ?? false ? 'status-ready' : 'status-limited' }}">
                                {{ $emailStatus['system_ready'] ?? false ? 'Ready' : 'Limited' }}
                            </span>
                        </div>
                        <div class="channel-status-item">
                            <i class="fas fa-users" style="color: var(--primary);"></i>
                            <span class="channel-status-label">Agents:</span>
                            <span class="channel-status-value" id="total-agents-count">{{ $registrationPlan->assignedAgents->where('is_active', true)->count() }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Custom Message -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-edit mr-2"></i>Custom Message (Optional)
                    </label>
                    <textarea name="message" rows="3" 
                              class="form-textarea" 
                              placeholder="Add a custom message to include with the invitation..."></textarea>
                    <p class="form-help-text">
                        <i class="fas fa-info-circle mr-1"></i>This message will be appended to the standard invitation template.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="modal-footer">
                    <button type="button" 
                            onclick="closeModal('bulk-invitation-modal')"
                            class="modal-cancel-btn">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" 
                            id="send-invitations-btn"
                            class="modal-confirm-btn">
                        <i class="fas fa-paper-plane mr-2"></i>Send Invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assignment Details Modal -->
<div id="assignment-details-modal" class="modal-overlay hidden">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assignment Details</h3>
                <button type="button" 
                        onclick="closeModal('assignment-details-modal')"
                        class="modal-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="assignment-details-content">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    function confirmMarkCompleted(progress, registered, estimated) {
        if (progress < 100) {
            return confirm(`This plan is only ${progress}% complete (${registered}/${estimated} houses). Are you sure you want to mark it as completed?`);
        }
        return confirm('Mark this plan as completed? All assigned agents will be notified via SMS and this action cannot be undone.');
    }

    // Show assignment details modal
    function showAssignmentDetails(planId, assignmentId) {
        fetch(`/admin/registration-plans/${planId}/assignments/${assignmentId}/details`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('assignment-details-modal');
                    const content = document.getElementById('assignment-details-content');
                    
                    content.innerHTML = `
                        <div class="modal-body">
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <div class="agent-avatar">
                                        ${data.assignment.agent.name.charAt(0)}
                                    </div>
                                    <div>
                                        <h4 class="agent-name">${data.assignment.agent.name}</h4>
                                        <p class="agent-email">${data.assignment.agent.email}</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="stat-card" style="border-left-color: var(--primary);">
                                        <div class="stat-value" style="color: var(--primary);">${data.assignment.properties_registered}</div>
                                        <div class="stat-label">Properties</div>
                                    </div>
                                    <div class="stat-card" style="border-left-color: var(--success);">
                                        <div class="stat-value" style="color: var(--success);">${data.performance_metrics.completion_rate}%</div>
                                        <div class="stat-label">Completion</div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="detail-label">Assigned:</span>
                                        <span class="detail-value">${new Date(data.assignment.assigned_at).toLocaleDateString()}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="detail-label">Status:</span>
                                        <span class="detail-value ${data.assignment.is_active ? 'status-active' : 'status-inactive'}">
                                            ${data.assignment.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="detail-label">Last Activity:</span>
                                        <span class="detail-value">${data.assignment_summary.last_activity || 'No activity'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    openModal('assignment-details-modal');
                }
            })
            .catch(error => {
                console.error('Error loading assignment details:', error);
                showToast('Failed to load assignment details', 'error');
            });
    }

    // Modal functions
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Toast notifications for actions
        @if(session('success'))
            showToast('{{ session('success') }}', 'success');
        @endif
        
        @if(session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif
        
        @if(session('warning'))
            showToast('{{ session('warning') }}', 'warning');
        @endif
        
        @if(session('info'))
            showToast('{{ session('info') }}', 'info');
        @endif

        // Close modals when clicking outside (Admin only)
        @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
            const modals = ['bulk-invitation-modal', 'assignment-details-modal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target.id === modalId) {
                            closeModal(modalId);
                        }
                    });
                }
            });

            // Initialize invitation modal functionality
            initializeInvitationModal();
        @endif

        // Add loading state to buttons when clicked
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"], input[type="submit"]');
                if (submitButton && !submitButton.classList.contains('no-loading')) {
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                    submitButton.disabled = true;
                    
                    // Revert after 10 seconds (in case of error)
                    setTimeout(() => {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }, 10000);
                }
            });
        });
    });

    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.add('toast-show');
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('toast-show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }

    // Enhanced confirmation for destructive actions (Admin only)
    @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
        document.querySelectorAll('form[action*="/cancel"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to cancel this plan? This will stop all ongoing registration activities and notify all assigned agents via SMS.')) {
                    e.preventDefault();
                }
            });
        });

        // Remove agent confirmation
        document.querySelectorAll('form[action*="/remove-agent"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to remove this agent from the plan?')) {
                    e.preventDefault();
                }
            });
        });

        // Invitation Modal Functionality
        function initializeInvitationModal() {
            const selectAllBtn = document.getElementById('select-all-btn');
            const selectSpecificBtn = document.getElementById('select-specific-btn');
            const specificAgentsSection = document.getElementById('specific-agents-section');
            const agentCheckboxes = document.querySelectorAll('.agent-checkbox');
            const selectedCount = document.getElementById('selected-count');
            const sendInvitationsBtn = document.getElementById('send-invitations-btn');
            const totalAgentsCount = document.getElementById('total-agents-count').textContent;
            const bulkInvitationForm = document.getElementById('bulk-invitation-form');

            let isAllSelected = true;

            // Initialize - select all by default
            updateSelectedCount();

            // Select All button
            selectAllBtn.addEventListener('click', function() {
                isAllSelected = true;
                specificAgentsSection.classList.add('hidden');
                selectAllBtn.classList.add('recipient-type-btn-active');
                selectAllBtn.classList.remove('recipient-type-btn');
                selectSpecificBtn.classList.remove('recipient-type-btn-active');
                selectSpecificBtn.classList.add('recipient-type-btn');
                
                // Uncheck all specific checkboxes
                agentCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                updateSelectedCount();
            });

            // Select Specific button
            selectSpecificBtn.addEventListener('click', function() {
                isAllSelected = false;
                specificAgentsSection.classList.remove('hidden');
                selectSpecificBtn.classList.add('recipient-type-btn-active');
                selectSpecificBtn.classList.remove('recipient-type-btn');
                selectAllBtn.classList.remove('recipient-type-btn-active');
                selectAllBtn.classList.add('recipient-type-btn');
                
                updateSelectedCount();
            });

            // Checkbox change events
            agentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Update selected count
            function updateSelectedCount() {
                let count;
                if (isAllSelected) {
                    count = parseInt(totalAgentsCount);
                    selectedCount.textContent = count;
                    sendInvitationsBtn.disabled = false;
                    sendInvitationsBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send to All Agents';
                } else {
                    count = Array.from(agentCheckboxes).filter(cb => cb.checked).length;
                    selectedCount.textContent = count;
                    sendInvitationsBtn.disabled = count === 0;
                    
                    if (count === 0) {
                        sendInvitationsBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Select Agents';
                    } else if (count === 1) {
                        const selectedAgent = Array.from(agentCheckboxes).find(cb => cb.checked);
                        const agentName = selectedAgent ? selectedAgent.getAttribute('data-agent-name') : 'Agent';
                        sendInvitationsBtn.innerHTML = `<i class="fas fa-paper-plane mr-2"></i>Send to ${agentName}`;
                    } else {
                        sendInvitationsBtn.innerHTML = `<i class="fas fa-paper-plane mr-2"></i>Send to ${count} Agents`;
                    }
                }
            }

            // Form submission
            bulkInvitationForm.addEventListener('submit', function(e) {
                if (!isAllSelected) {
                    // Add hidden input to indicate specific selection
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'recipient_type';
                    hiddenInput.value = 'specific';
                    this.appendChild(hiddenInput);
                } else {
                    // Add hidden input to indicate all selection
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'recipient_type';
                    hiddenInput.value = 'all';
                    this.appendChild(hiddenInput);
                }

                // Show confirmation
                let message;
                if (isAllSelected) {
                    message = `Send invitations to all ${totalAgentsCount} agents?`;
                } else {
                    const count = Array.from(agentCheckboxes).filter(cb => cb.checked).length;
                    message = `Send invitations to ${count} selected agents?`;
                }

                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        }
    @endif
</script>

<style>
/* Toast notifications */
.toast-notification {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 9999;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    color: white;
    font-weight: 500;
    display: flex;
    align-items: center;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    max-width: 350px;
}

.toast-notification.toast-show {
    transform: translateX(0);
}

.toast-notification.success {
    background-color: var(--success);
}

.toast-notification.error {
    background-color: var(--danger);
}

.toast-notification.warning {
    background-color: var(--warning);
}

.toast-notification.info {
    background-color: var(--info);
}

/* Modal styles */
.modal-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-container {
    width: 100%;
    max-width: 28rem;
}

.modal-content {
    background-color: var(--card-bg);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border-color);
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 1.125rem;
    margin: 0;
}

.modal-close-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close-btn:hover {
    color: var(--text-primary);
    background-color: rgba(var(--primary-rgb), 0.1);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.modal-cancel-btn {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.modal-cancel-btn:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.modal-confirm-btn {
    background: linear-gradient(to right, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.modal-confirm-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.modal-confirm-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Form elements inside modal */
.form-label {
    display: block;
    font-weight: 500;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
}

.form-select,
.form-textarea {
    width: 100%;
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

.form-help-text {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
}

/* Recipient selection styles */
.recipient-type-btn {
    padding: 0.5rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.recipient-type-btn-active {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

.recipient-type-btn:hover:not(.recipient-type-btn-active) {
    background-color: rgba(var(--primary-rgb), 0.05);
}

.specific-agents-section {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.75rem;
    background-color: var(--bg-secondary);
    max-height: 12rem;
    overflow-y: auto;
}

.specific-agent-item {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 6px;
    transition: background-color 0.2s;
}

.specific-agent-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

.agent-checkbox {
    margin-right: 0.75rem;
    width: 1rem;
    height: 1rem;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    cursor: pointer;
}

.agent-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.specific-agent-info {
    flex: 1;
}

.specific-agent-name {
    display: block;
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.specific-agent-phone {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.125rem;
}

.selected-agents-summary {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
}

/* Channel status styles */
.channel-status-section {
    padding: 1rem;
    border-radius: 8px;
    background-color: rgba(var(--secondary-rgb), 0.05);
    border: 1px solid var(--border-color);
}

.channel-status-title {
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
}

.channel-status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.channel-status-item {
    display: flex;
    align-items: center;
    font-size: 0.75rem;
}

.channel-status-label {
    margin-left: 0.5rem;
    color: var(--text-secondary);
}

.channel-status-value {
    margin-left: 0.25rem;
    font-weight: 500;
}

.status-ready {
    color: var(--success);
}

.status-limited {
    color: var(--warning);
}

/* Assignment details modal styles */
.agent-avatar {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-right: 1rem;
}

.agent-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
    margin: 0;
}

.agent-email {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.detail-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.detail-value {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

.status-active {
    color: var(--success);
}

.status-inactive {
    color: var(--text-secondary);
}

/* Agent avatar styling */
.agent-avatar-small {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

/* Progress bar animations */
.progress-bar-fill {
    position: relative;
    overflow: hidden;
}

.progress-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background-image: linear-gradient(
        -45deg,
        rgba(255, 255, 255, 0.2) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0.2) 75%,
        transparent 75%,
        transparent
    );
    background-size: 1rem 1rem;
    animation: progressStripes 1s linear infinite;
}

@keyframes progressStripes {
    0% {
        background-position: 1rem 0;
    }
    100% {
        background-position: 0 0;
    }
}

/* Hover effects for interactive elements */
.card.hoverable {
    transition: all 0.2s ease;
}

.card.hoverable:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Status badge animations */
@keyframes pulseStatus {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.in_progress .status-badge {
    animation: pulseStatus 2s infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-container {
        width: 95%;
        max-width: none;
    }
    
    .grid-cols-1 {
        grid-template-columns: 1fr !important;
    }
    
    .grid-cols-2,
    .grid-cols-3 {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .channel-status-grid {
        grid-template-columns: 1fr !important;
    }
    
    .agent-avatar {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1rem;
    }
}

/* Dark mode specific adjustments */
[data-theme="dark"] .modal-overlay {
    background-color: rgba(0, 0, 0, 0.7);
}

[data-theme="dark"] .form-select,
[data-theme="dark"] .form-textarea {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border-color: var(--border-color);
}

[data-theme="dark"] .specific-agents-section {
    background-color: rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .agent-checkbox {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
}

[data-theme="dark"] .agent-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Custom scrollbar */
.specific-agents-section::-webkit-scrollbar {
    width: 6px;
}

.specific-agents-section::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

.specific-agents-section::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.specific-agents-section::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Loading spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced invitation styling */
.invitation-channel-sms {
    color: var(--primary);
}

.invitation-channel-whatsapp {
    color: var(--success);
}

.invitation-channel-email {
    color: var(--info);
}

.invitation-channel-all {
    color: var(--warning);
}

/* Field agent specific styling */
.field-agent-notice {
    border-left: 4px solid var(--primary);
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.05) 0%, rgba(var(--primary-rgb), 0.02) 100%);
}

.assigned-to-me-highlight {
    background: linear-gradient(135deg, rgba(var(--success-rgb), 0.05) 0%, rgba(var(--success-rgb), 0.02) 100%);
    border-color: rgba(var(--success-rgb), 0.3);
}

/* Performance metrics styling */
.performance-metric {
    transition: all 0.3s ease;
}

.performance-metric:hover {
    transform: scale(1.05);
}

/* Global sequence styling */
.global-sequence-badge {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}
</style>