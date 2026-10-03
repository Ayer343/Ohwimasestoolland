@extends('layouts.' . (auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT ? 'field' : 'app'))

@section('title', 'Registration Plans')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="mb-4 md:mb-0">
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                        My Assigned Plans
                    @else
                        Registration Plans
                    @endif
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                        View and manage your assigned property registration plans
                    @else
                        Manage property registration plans and assign agents for field operations
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
                    <!-- Trash Button -->
                    <a href="{{ route('registration-plans.trash') }}" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                        <i class="fas fa-trash-alt mr-2"></i> View Trash
                        @if($trashedCount > 0)
                            <span class="ml-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs font-medium">
                                {{ $trashedCount }}
                            </span>
                        @endif
                    </a>
                    
                    <!-- Analytics Button -->
                    <a href="{{ route('registration-plans.analytics') }}" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                        <i class="fas fa-chart-bar mr-2"></i> Analytics
                    </a>
                    
                    <!-- Export Button -->
                    <a href="{{ route('registration-plans.export') }}" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                        <i class="fas fa-download mr-2"></i> Export
                    </a>
                    
                    <!-- Create Plan Button -->
                    <a href="{{ route('registration-plans.create') }}" class="btn-modern flex items-center px-4 py-3">
                        <i class="fas fa-plus mr-2"></i> Create Plan
                    </a>
                @else
                    <!-- Field Agent Specific Actions -->
                    <div class="flex items-center px-3 py-2 rounded-lg text-sm font-medium" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                        <i class="fas fa-user-shield mr-2"></i>
                        Field Agent - View Only
                    </div>
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

    <!-- Communication Status for Admins -->
    @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
    <div class="card p-6 mb-6">
        <div class="flex items-start">
            <i class="fas fa-comment-alt text-xl mr-3 mt-1" style="color: {{ $smsStatus['system_ready'] ? 'var(--success)' : 'var(--warning)' }};"></i>
            <div class="flex-1">
                <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    {{ $smsStatus['system_ready'] ? 'Communication System Ready' : 'Limited Communication Channels' }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium" style="color: var(--text-secondary);">Default Provider</span>
                        <span class="text-lg font-semibold capitalize mt-1" style="color: {{ $smsStatus['system_ready'] ? 'var(--success)' : 'var(--warning)' }};">
                            {{ $smsStatus['default_provider'] ?? 'None' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium" style="color: var(--text-secondary);">Available Channels</span>
                        <span class="text-lg font-semibold mt-1" style="color: var(--text-primary);">{{ count($smsStatus['available_providers'] ?? []) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium" style="color: var(--text-secondary);">Ready Providers</span>
                        <span class="text-lg font-semibold mt-1" style="color: var(--success);">{{ $smsStatus['ready_providers'] ?? 0 }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium" style="color: var(--text-secondary);">System Status</span>
                        <span class="text-lg font-semibold capitalize mt-1" style="color: {{ ($smsStatus['health_status'] ?? 'unknown') === 'healthy' ? 'var(--success)' : 'var(--warning)' }};">
                            {{ $smsStatus['health_status'] ?? 'unknown' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card p-6 mb-6">
        <form method="GET" action="{{ 
            auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT 
                ? route('field-agent.registration-plans.index')
                : route('registration-plans.index')
        }}">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Status</label>
                    <select name="status" class="form-select w-full p-3 rounded-lg border"
                            style="background-color: white; color: black; border-color: #e5e7eb;">
                        <option value="all">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Zone</label>
                    <input type="text" name="zone" value="{{ request('zone') }}" placeholder="Search zone..." 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: #e5e7eb;">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Section</label>
                    <input type="text" name="section" value="{{ request('section') }}" placeholder="Search section..." 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: #e5e7eb;">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Agent</label>
                    <select name="agent_id" class="form-select w-full p-3 rounded-lg border"
                            style="background-color: white; color: black; border-color: #e5e7eb;"
                            {{ auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT ? 'disabled' : '' }}>
                        @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                            <option value="{{ auth()->id() }}" selected>My Plans Only</option>
                        @else
                            <option value="">All Agents</option>
                            <option value="unassigned" {{ request('agent_id') == 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="flex items-end">
                    <div class="flex space-x-2 w-full">
                        <button type="submit" class="btn-modern flex-1 flex items-center justify-center">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ 
                            auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT 
                                ? route('field-agent.registration-plans.index')
                                : route('registration-plans.index')
                        }}" class="btn-secondary flex items-center px-3 py-3" title="Reset Filters">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="stat-card users-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">{{ $plans->total() }}</div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">
                @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                    My Plans
                @else
                    Total Plans
                @endif
            </div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                Filtered results
            </div>
        </div>
        <div class="stat-card revenue-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                {{ $plans->whereIn('status', ['assigned', 'in_progress'])->count() }}
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">Active Plans</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                Assigned & In Progress
            </div>
        </div>
        <div class="stat-card conversion-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                {{ $plans->where('status', 'completed')->count() }}
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">Completed</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                Successfully finished
            </div>
        </div>
        <div class="stat-card bounce-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                {{ $overduePlansCount }}
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">Overdue</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                Past end date
            </div>
        </div>
        <div class="stat-card" style="border-left-color: var(--danger);">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                    {{ $plans->where('status', 'cancelled')->count() }}
                @else
                    {{ $trashedCount }}
                @endif
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">
                @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                    Cancelled
                @else
                    In Trash
                @endif
            </div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                    Deactivated plans
                @else
                    Awaiting cleanup
                @endif
            </div>
        </div>
    </div>

    <!-- Plans List Card -->
    <div class="card p-6">
        @if($plans->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-full">
                    <thead>
                        <tr class="border-b" style="border-bottom-color: #e5e7eb;">
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">ID</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">Zone/Section</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">Naming Pattern</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">
                                @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                                    My Assignment
                                @else
                                    Assigned Agents
                                @endif
                            </th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">Progress</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">Status</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">Timeline</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary); border-bottom: 2px solid #e5e7eb;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $plan)
                            @php
                                // Check if current user is assigned to this plan (for field agents)
                                $isAssignedToMe = auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT && 
                                                 $plan->assignedAgents->where('agent_id', auth()->id())->where('is_active', true)->count() > 0;
                                
                                // Status color mapping
                                $statusColors = [
                                    'completed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'border' => 'var(--success)'],
                                    'in_progress' => ['bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => 'var(--primary)', 'border' => 'var(--primary)'],
                                    'assigned' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'border' => 'var(--warning)'],
                                    'cancelled' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'border' => 'var(--danger)'],
                                    'draft' => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'border' => 'var(--secondary)'],
                                ];
                                $statusConfig = $statusColors[$plan->status] ?? $statusColors['draft'];
                            @endphp
                            
                            @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT && !$isAssignedToMe)
                                @continue
                            @endif
                            
                            <tr class="border-b hover:bg-opacity-50 transition-colors duration-150" 
                                style="border-bottom-color: #e5e7eb; {{ $isAssignedToMe ? 'background-color: rgba(var(--primary-rgb), 0.05);' : '' }}">
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    <div class="flex flex-col space-y-1">
                                        <span class="text-sm font-mono font-medium" style="color: var(--text-primary);">#{{ $plan->id }}</span>
                                        <div class="flex flex-wrap gap-1">
                                            @if($plan->is_global_sequence)
                                                <span class="text-xs px-2 py-1 rounded-full font-medium" 
                                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                                    <i class="fas fa-link mr-1 text-xs"></i>Global
                                                </span>
                                            @endif
                                            @if($plan->agent_assignment_type === 'multiple')
                                                <span class="text-xs px-2 py-1 rounded-full font-medium" 
                                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                                    <i class="fas fa-users mr-1 text-xs"></i>Multiple
                                                </span>
                                            @else
                                                <span class="text-xs px-2 py-1 rounded-full font-medium" 
                                                      style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                                    <i class="fas fa-user mr-1 text-xs"></i>Single
                                                </span>
                                            @endif
                                            @if($isAssignedToMe)
                                                <span class="text-xs px-2 py-1 rounded-full font-medium" 
                                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                                    <i class="fas fa-user-check mr-1 text-xs"></i>Me
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    <div class="font-medium text-sm" style="color: var(--text-primary);">
                                        {{ $plan->zone }}
                                        @if($plan->section)
                                            <span class="text-sm font-normal ml-1" style="color: var(--text-secondary);">- {{ $plan->section }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-map-marker-alt mr-1 text-xs"></i>
                                            Start: {{ $plan->starting_point }}
                                        </span>
                                        <span class="inline-flex items-center ml-3">
                                            <i class="fas fa-home mr-1 text-xs"></i>
                                            Est: {{ $plan->estimated_houses }} houses
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        <code class="px-2 py-1 rounded text-xs" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid #e5e7eb;">
                                            {{ $plan->naming_pattern }}
                                        </code>
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-sort-amount-down mr-1"></i>
                                        {{ ucfirst($plan->sequence_type) }} sequence
                                    </div>
                                </td>
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    @if($plan->assignedAgents->where('is_active', true)->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($plan->assignedAgents->where('is_active', true) as $assignment)
                                                <div class="flex items-center justify-between group">
                                                    <div class="flex items-center">
                                                        <div class="w-6 h-6 rounded-full flex items-center justify-center mr-2 text-xs font-medium"
                                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                                            {{ substr($assignment->agent->name, 0, 1) }}
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium" style="color: var(--text-primary);">
                                                                {{ $assignment->agent->name }}
                                                                @if($assignment->agent_id === auth()->id())
                                                                    <span class="ml-1 text-xs px-1 py-0.5 rounded" 
                                                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                                                        You
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                                {{ $assignment->agent->email }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
                                                        <form action="{{ route('registration-plans.agents.remove', [$plan->id, $assignment->agent_id]) }}" 
                                                              method="POST" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-xs p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                                                                    onclick="return confirm('Remove {{ $assignment->agent->name }} from this plan?')"
                                                                    title="Remove Agent" style="color: var(--danger);">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-sm italic py-2" style="color: var(--text-secondary); background-color: rgba(var(--secondary-rgb), 0.05); border-radius: 6px; padding: 8px; text-align: center; border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                            <i class="fas fa-user-slash mr-1"></i> No agents assigned
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    @php
                                        $percentage = $plan->progress_percentage;
                                        $progressColor = match(true) {
                                            $percentage >= 80 => 'var(--success)',
                                            $percentage >= 50 => 'var(--primary)',
                                            $percentage >= 25 => 'var(--warning)',
                                            default => 'var(--danger)',
                                        };
                                    @endphp
                                    <div class="mb-2">
                                        <div class="text-lg font-bold" style="color: var(--text-primary);">{{ $percentage }}%</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Complete</div>
                                    </div>
                                    <div class="progress-bar" style="height: 6px; border-radius: 3px; background-color: rgba(var(--primary-rgb), 0.1); overflow: hidden; border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                        <div class="progress-bar-fill" style="height: 100%; border-radius: 3px; background-color: {{ $progressColor }}; width: {{ min($percentage, 100) }}%; transition: width 0.3s ease;"></div>
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $plan->properties_count }}/{{ $plan->estimated_houses }} houses registered
                                    </div>
                                </td>
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium"
                                          style="background-color: {{ $statusConfig['bg'] }}; 
                                                 color: {{ $statusConfig['text'] }};
                                                 border: 1px solid {{ $statusConfig['border'] }};">
                                        <i class="fas 
                                            {{ $plan->status == 'completed' ? 'fa-check-circle' : 
                                               ($plan->status == 'in_progress' ? 'fa-spinner fa-spin' : 
                                               ($plan->status == 'assigned' ? 'fa-user-clock' : 
                                               ($plan->status == 'cancelled' ? 'fa-times-circle' : 'fa-edit'))) }} 
                                            mr-1.5"></i>
                                        {{ ucfirst(str_replace('_', ' ', $plan->status)) }}
                                    </span>
                                </td>
                                <td class="p-3 align-top border-r" style="border-right-color: #e5e7eb;">
                                    @if($plan->registration_start_date && $plan->registration_end_date)
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-calendar-start mr-2 text-xs" style="color: var(--primary);"></i>
                                                {{ $plan->registration_start_date->format('M d, Y') }}
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-end mr-2 text-xs" style="color: var(--primary);"></i>
                                                {{ $plan->registration_end_date->format('M d, Y') }}
                                            </div>
                                        </div>
                                        @php
                                            $now = now();
                                            $isOverdue = $plan->registration_end_date && $plan->registration_end_date->lt($now);
                                        @endphp
                                        @if($isOverdue && !in_array($plan->status, ['completed', 'cancelled']))
                                            <div class="text-xs mt-1 px-2 py-1 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-sm italic py-2" style="color: var(--text-secondary); background-color: rgba(var(--secondary-rgb), 0.05); border-radius: 6px; border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                            <i class="fas fa-calendar-times mr-1"></i> No dates set
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3 align-top">
                                    <div class="flex flex-wrap gap-1">
                                        <!-- View Details Button -->
                                        <a href="{{ route('registration-plans.show', $plan->id) }}" 
                                           class="btn-sm btn-secondary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
                                            <!-- Admin Only Actions -->
                                            @if(in_array($plan->status, ['draft', 'assigned', 'in_progress']))
                                                <a href="{{ route('registration-plans.edit', $plan->id) }}" 
                                                   class="btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endif

                                            @if($plan->status == 'assigned')
                                                <form action="{{ route('registration-plans.mark-in-progress', $plan->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn-sm btn-info" title="Mark as In Progress"
                                                            onclick="return confirm('Mark this plan as in progress?\n\nAssigned agents will receive SMS notifications.')">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if(in_array($plan->status, ['assigned', 'in_progress']))
                                                <!-- FIXED: Mark as Complete form - using POST method -->
                                                <form action="{{ route('registration-plans.mark-completed', $plan->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn-sm btn-success" title="Mark as Completed"
                                                            onclick="return confirmMarkCompleted({{ $plan->progress_percentage }}, {{ $plan->properties_count }}, {{ $plan->estimated_houses }})">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if(!in_array($plan->status, ['completed', 'cancelled']))
                                                <form action="{{ route('registration-plans.cancel', $plan->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn-sm btn-danger" title="Cancel Plan" 
                                                            onclick="return confirm('Are you sure you want to cancel this plan?\n\nAssigned agents will receive in-app notifications.')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if($plan->status == 'cancelled')
                                                <form action="{{ route('registration-plans.reactivate', $plan->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn-sm btn-warning" title="Reactivate Plan"
                                                            onclick="return confirm('Reactivate this plan?')">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- Delete Button -->
                                            @if($plan->can_be_deleted)
                                                <form action="{{ route('registration-plans.destroy', $plan->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-sm btn-danger" title="Move to Trash"
                                                            onclick="return confirm('Move this plan to trash?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <!-- Field Agent Actions -->
                                            @if($isAssignedToMe)
                                                <span class="btn-sm btn-info cursor-default" title="Assigned to You">
                                                    <i class="fas fa-user-check"></i>
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-6 pt-4 border-t" style="border-top-color: #e5e7eb;">
                {{ $plans->withQueryString()->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" 
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-map-marked-alt text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary);">
                    @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                        No Assigned Plans Found
                    @else
                        No Registration Plans Found
                    @endif
                </h3>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    @if(request()->anyFilled(['status', 'zone', 'section', 'agent_id']))
                        Try adjusting your filters to see more results.
                    @else
                        @if(auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT)
                            You don't have any assigned registration plans yet. Plans will appear here once an administrator assigns them to you.
                        @else
                            Create your first registration plan to start organizing property registration in different zones and sections.
                        @endif
                    @endif
                </p>
                
                @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT && !request()->anyFilled(['status', 'zone', 'section', 'agent_id']))
                    <a href="{{ route('registration-plans.create') }}" class="btn-modern inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create Your First Plan
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    function confirmMarkCompleted(progress, registered, estimated) {
        if (progress < 100) {
            return confirm(`This plan is only ${progress}% complete (${registered}/${estimated} houses). Are you sure you want to mark it as completed?\n\nAssigned agents will receive in-app notifications.`);
        }
        return confirm('Mark this plan as completed?\n\nAssigned agents will receive in-app notifications.');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Apply light mode specific styles for form elements
        const applyLightModeStyles = () => {
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            
            if (!isDarkMode) {
                // Update form selects to have white background and black text
                document.querySelectorAll('.form-select').forEach(select => {
                    select.style.backgroundColor = 'white';
                    select.style.color = 'black';
                    select.style.borderColor = '#e5e7eb';
                });
                
                // Update form inputs to have visible borders
                document.querySelectorAll('.form-input').forEach(input => {
                    input.style.borderColor = '#e5e7eb';
                });
                
                // Update table borders to be visible
                document.querySelectorAll('table thead tr th').forEach(th => {
                    th.style.borderBottomColor = '#e5e7eb';
                    th.style.borderBottomWidth = '2px';
                    th.style.borderBottomStyle = 'solid';
                });
                
                document.querySelectorAll('table tbody tr td').forEach(td => {
                    td.style.borderRightColor = '#e5e7eb';
                    td.style.borderRightWidth = '1px';
                    td.style.borderRightStyle = 'solid';
                });
                
                // Update card borders
                document.querySelectorAll('.card').forEach(card => {
                    card.style.border = '1px solid #e5e7eb';
                });
            }
        };

        // Apply styles initially
        applyLightModeStyles();

        // Observe theme changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'data-theme') {
                    applyLightModeStyles();
                }
            });
        });

        observer.observe(document.documentElement, { attributes: true });

        // Auto-submit filter form when select changes
        const filterSelects = document.querySelectorAll('select[name="status"], select[name="agent_id"]');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });

        // Enhanced confirmation dialogs (Admin only)
        @if(auth()->user()->type !== \App\Models\User::TYPE_FIELD_AGENT)
            // Debug: Log form submissions
            document.querySelectorAll('form[action*="mark-completed"]').forEach(form => {
                console.log('Found mark-completed form:', form.action);
                form.addEventListener('submit', function(e) {
                    console.log('Mark as completed form submitted');
                });
            });

            document.querySelectorAll('form[action*="/cancel"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to cancel this plan?\n\nAssigned agents will receive in-app notifications.')) {
                        e.preventDefault();
                    }
                });
            });

            document.querySelectorAll('form[action*="/mark-in-progress"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Mark this plan as in progress?\n\nAssigned agents will receive SMS notifications.')) {
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
        toast.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full`;
        
        switch (type) {
            case 'success':
                toast.style.backgroundColor = 'var(--success)';
                break;
            case 'error':
                toast.style.backgroundColor = 'var(--danger)';
                break;
            case 'warning':
                toast.style.backgroundColor = 'var(--warning)';
                break;
            default:
                toast.style.backgroundColor = 'var(--info)';
        }
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }
</script>

<style>
/* Enhanced Table Styling */
table {
    border-collapse: separate;
    border-spacing: 0;
}

table thead tr th {
    border-bottom: 2px solid #e5e7eb;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
}

table tbody tr {
    transition: all 0.2s ease;
}

table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03);
}

table tbody tr:last-child {
    border-bottom: none;
}

table tbody tr td {
    border-right: 1px solid #e5e7eb;
}

table tbody tr td:last-child {
    border-right: none;
}

/* Form elements styling for light mode */
.form-select {
    background-color: white !important;
    color: black !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    padding: 0.75rem !important;
    width: 100% !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
    background-position: right 0.5rem center !important;
    background-repeat: no-repeat !important;
    background-size: 1.5em 1.5em !important;
}

.form-input {
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    padding: 0.75rem !important;
    width: 100% !important;
    background-color: white !important;
    color: black !important;
}

/* Button styles matching SMS configuration */
.btn-sm {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    min-width: 32px !important;
    min-height: 32px !important;
}

.btn-sm.btn-primary {
    background: linear-gradient(to right, var(--primary), var(--secondary)) !important;
    color: white !important;
    border-color: var(--primary) !important;
    box-shadow: 0 2px 4px rgba(114, 103, 240, 0.2) !important;
}

.btn-sm.btn-secondary {
    background-color: white !important;
    color: black !important;
    border-color: #e5e7eb !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}

.btn-sm.btn-success {
    background: linear-gradient(to right, var(--success), #20b86d) !important;
    color: white !important;
    border-color: var(--success) !important;
    box-shadow: 0 2px 4px rgba(40, 199, 111, 0.2) !important;
}

.btn-sm.btn-danger {
    background: linear-gradient(to right, var(--danger), #e53935) !important;
    color: white !important;
    border-color: var(--danger) !important;
    box-shadow: 0 2px 4px rgba(234, 84, 85, 0.2) !important;
}

.btn-sm.btn-warning {
    background: linear-gradient(to right, var(--warning), #ff8c00) !important;
    color: white !important;
    border-color: var(--warning) !important;
    box-shadow: 0 2px 4px rgba(255, 159, 67, 0.2) !important;
}

.btn-sm.btn-info {
    background: linear-gradient(to right, var(--info), #00b5cc) !important;
    color: white !important;
    border-color: var(--info) !important;
    box-shadow: 0 2px 4px rgba(0, 207, 232, 0.2) !important;
}

.btn-sm:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    opacity: 0.9 !important;
}

.btn-sm:active {
    transform: translateY(0) !important;
}

/* Agent avatar styling */
.agent-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    flex-shrink: 0;
    border: 1px solid rgba(var(--primary-rgb), 0.3);
}

/* Progress bar animation */
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

/* Status badge animation */
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

/* Form input focus styles */
.form-select:focus,
.form-input:focus,
.form-textarea:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card {
        padding: 1rem !important;
        border: 1px solid #e5e7eb !important;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.75rem !important;
        min-width: 28px !important;
        min-height: 28px !important;
    }
    
    .agent-avatar {
        width: 24px;
        height: 24px;
        font-size: 0.75rem;
    }
    
    /* Remove side borders on mobile for better display */
    table tbody tr td {
        border-right: none !important;
    }
}

/* Custom scrollbar for table */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
    border: 1px solid #9ca3af;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Loading spinner animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Badge styling for counts */
.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    background-color: var(--danger);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Hover effects for table rows */
.table-row-hover {
    position: relative;
}

.table-row-hover::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(var(--primary-rgb), 0.03), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.table-row-hover:hover::after {
    opacity: 1;
}

/* Dark mode specific adjustments */
[data-theme="dark"] .form-select,
[data-theme="dark"] .form-input,
[data-theme="dark"] .form-textarea {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

[data-theme="dark"] .form-select:focus,
[data-theme="dark"] .form-input:focus,
[data-theme="dark"] .form-textarea:focus {
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2) !important;
}

[data-theme="dark"] code {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

[data-theme="dark"] table thead tr th {
    border-bottom-color: var(--border-color) !important;
}

[data-theme="dark"] table tbody tr td {
    border-right-color: var(--border-color) !important;
}

[data-theme="dark"] table tbody tr {
    border-bottom-color: var(--border-color) !important;
}

[data-theme="dark"] .card {
    border-color: var(--border-color) !important;
}

/* Light mode specific enhancements */
[data-theme="light"] .btn-sm.btn-secondary {
    background-color: white !important;
    color: #374151 !important;
    border-color: #d1d5db !important;
}

[data-theme="light"] .form-select option {
    background-color: white !important;
    color: black !important;
}

[data-theme="light"] .dropdown-menu {
    background-color: white !important;
    color: black !important;
    border: 1px solid #e5e7eb !important;
}

[data-theme="light"] .dropdown-item {
    color: #374151 !important;
}

[data-theme="light"] .dropdown-item:hover {
    background-color: #f9fafb !important;
}
</style>
@endsection