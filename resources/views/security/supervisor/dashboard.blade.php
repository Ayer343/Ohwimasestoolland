@extends('layouts.secu')

@section('title', 'Supervisor Dashboard')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
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
                        <i class="fas fa-tachometer-alt mr-2" style="color: var(--primary);"></i>
                        Supervisor Dashboard
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} {{ auth()->user()->badge_number ? ' - ' . auth()->user()->badge_number : '' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                        <span class="mx-2">•</span>
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-layer-group mr-1"></i>
                            {{ $assignments->count() ?? 0 }} posts supervised
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor.team.today') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-users mr-2"></i> Team Today
                </a>
                <a href="{{ route('security.supervisor.assignments.current') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tasks mr-2"></i> My Assignments
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Overview -->
    @if(isset($assignments) && $assignments->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Active Assignments -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $assignments->count() }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Assignments</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-tasks" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <!-- Total Posts Supervising -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $assignments->pluck('security_post_id')->unique()->count() }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Posts Supervising</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-building" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending Approvals -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $pending_counts['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending Approvals</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <!-- Team Today -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $team_today_count ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Team Today</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Dashboard Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Active Assignments -->
        <div class="lg:col-span-1">
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i>
                        My Active Assignments
                        <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                            {{ $assignments->count() ?? 0 }}
                        </span>
                    </h3>
                </div>
                <div class="p-4">
                    @if(isset($assignments) && $assignments->count() > 0)
                        @foreach($assignments as $assignment)
                        <div class="p-4 rounded-lg mb-3 transition-all duration-200 hover:shadow-md"
                             style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600;">
                                            {{ isset($assignment->post) && $assignment->post ? substr($assignment->post->name, 0, 1) : 'P' }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ isset($assignment->post) && $assignment->post ? $assignment->post->name : 'No Post Assigned' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-tag mr-1"></i>
                                                {{ $assignment->supervisor_type_name ?? 'Supervisor' }}
                                                @if($assignment->is_primary_supervisor)
                                                    <span class="ml-2 px-1.5 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                        Primary
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs flex flex-wrap gap-3" style="color: var(--text-secondary);">
                                        <span>
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            {{ $assignment->start_date ? $assignment->start_date->format('M d, Y') : 'N/A' }}
                                            @if($assignment->end_date)
                                                - {{ $assignment->end_date->format('M d, Y') }}
                                            @else
                                                - Ongoing
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <a href="{{ route('security.supervisor.assignments.show', $assignment->id) }}"
                                   class="text-xs px-3 py-1 rounded-full transition-all duration-200 hover:scale-105"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    View
                                </a>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-clipboard-list text-2xl" style="color: var(--info);"></i>
                            </div>
                            <p style="color: var(--text-secondary);">No active assignments</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">You are not currently assigned as a supervisor</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Center Column: Team Today & Pending Approvals -->
        <div class="lg:col-span-2">
            <!-- Team Today Preview -->
            <div class="card mb-6">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Team Today
                        <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                            {{ $team_today_count ?? 0 }} personnel
                        </span>
                    </h3>
                    <a href="{{ route('security.supervisor.team.today') }}" 
                       class="text-sm font-medium transition-colors duration-200"
                       style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="p-4">
                    @if(isset($team_today) && $team_today->count() > 0)
                        <div class="space-y-3">
                            @foreach($team_today as $postName => $schedules)
                                <div class="p-3 rounded-lg transition-all duration-200"
                                     style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-medium" style="color: var(--text-primary);">
                                            <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                                            {{ $postName ?? 'Unknown Post' }}
                                        </span>
                                        <div class="flex items-center space-x-3 text-xs">
                                            <span style="color: var(--success);">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                {{ $schedules->where('check_in_status', 'verified')->count() }}
                                            </span>
                                            <span style="color: var(--warning);">
                                                <i class="fas fa-hourglass-half mr-1"></i>
                                                {{ $schedules->where('check_in_status', 'pending')->count() }}
                                            </span>
                                            <span style="color: var(--danger);">
                                                <i class="fas fa-user-slash mr-1"></i>
                                                {{ $schedules->where('status', 'absent')->count() }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($schedules->take(5) as $schedule)
                                            <div class="flex items-center px-2 py-1 rounded-full text-xs"
                                                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                                <span style="color: var(--text-primary);">
                                                    {{ isset($schedule->securityUser) && $schedule->securityUser ? $schedule->securityUser->name : 'Unknown' }}
                                                </span>
                                                @if($schedule->check_in_status == 'verified')
                                                    <i class="fas fa-check-circle ml-1" style="color: var(--success);"></i>
                                                @elseif($schedule->check_in_status == 'pending')
                                                    <i class="fas fa-clock ml-1" style="color: var(--warning);"></i>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if($schedules->count() > 5)
                                            <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                +{{ $schedules->count() - 5 }} more
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-calendar-times text-xl" style="color: var(--info);"></i>
                            </div>
                            <p style="color: var(--text-secondary);">No team members scheduled today</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                        Pending Approvals
                        <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-warning">
                            {{ $pending_counts['total'] ?? 0 }}
                        </span>
                    </h3>
                    <a href="{{ route('security.supervisor.actions.pending') }}" 
                       class="text-sm font-medium transition-colors duration-200"
                       style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="p-4">
                    @if(isset($pending_counts) && $pending_counts['total'] > 0)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Swap Requests -->
                            <div class="p-4 rounded-lg text-center transition-all duration-200 hover:shadow-md"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                                    <i class="fas fa-exchange-alt" style="color: var(--info);"></i>
                                </div>
                                <div class="text-2xl font-bold" style="color: var(--info);">{{ $pending_counts['swaps'] ?? 0 }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Swap Requests</div>
                                @if(($pending_counts['swaps'] ?? 0) > 0)
                                    <a href="{{ route('security.supervisor.actions.pending') }}#swaps"
                                       class="text-xs mt-2 inline-block transition-colors duration-200"
                                       style="color: var(--primary);">
                                        Review <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                @endif
                            </div>

                            <!-- Overtime Requests -->
                            <div class="p-4 rounded-lg text-center transition-all duration-200 hover:shadow-md"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                                </div>
                                <div class="text-2xl font-bold" style="color: var(--warning);">{{ $pending_counts['overtime'] ?? 0 }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Overtime Requests</div>
                                @if(($pending_counts['overtime'] ?? 0) > 0)
                                    <a href="{{ route('security.supervisor.actions.pending') }}#overtime"
                                       class="text-xs mt-2 inline-block transition-colors duration-200"
                                       style="color: var(--primary);">
                                        Review <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                @endif
                            </div>

                            <!-- Verifications -->
                            <div class="p-4 rounded-lg text-center transition-all duration-200 hover:shadow-md"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                </div>
                                <div class="text-2xl font-bold" style="color: var(--success);">{{ $pending_counts['verifications'] ?? 0 }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Pending Verifications</div>
                                @if(($pending_counts['verifications'] ?? 0) > 0)
                                    <a href="{{ route('security.supervisor.team.today') }}"
                                       class="text-xs mt-2 inline-block transition-colors duration-200"
                                       style="color: var(--primary);">
                                        Review <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-6">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                                <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                            </div>
                            <p style="color: var(--text-secondary);">No pending approvals</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">All requests are up to date</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Staffing Alerts -->
    @if(isset($staffing_alerts) && $staffing_alerts->count() > 0)
    <div class="card" style="border-color: rgba(var(--warning-rgb), 0.3); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="p-6">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                Staffing Alerts
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-warning">
                    {{ $staffing_alerts->count() }}
                </span>
            </h3>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($staffing_alerts as $alert)
                    <div class="p-3 rounded-lg flex items-center justify-between"
                         style="background-color: var(--card-bg); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ $alert->name ?? 'Unknown Post' }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <span style="color: var(--warning);">
                                    {{ $alert->current_personnel ?? 0 }}
                                </span>
                                / {{ $alert->max_personnel ?? 0 }} personnel assigned
                            </div>
                        </div>
                        <a href="{{ route('security.supervisor.posts.schedule', ['postId' => $alert->id]) }}"
                           class="text-xs px-3 py-1 rounded-full transition-all duration-200"
                           style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                            View Schedule
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <a href="{{ route('security.supervisor.team.today') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users text-xl" style="color: var(--primary);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Team Today</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">View today's team</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>

        <a href="{{ route('security.supervisor.team.schedule') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar-alt text-xl" style="color: var(--info);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Team Schedule</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">View full schedule</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>

        <a href="{{ route('security.supervisor.team.performance') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-chart-line text-xl" style="color: var(--success);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Performance</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">Team metrics</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>

        <a href="{{ route('security.supervisor.assignments.current') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-tasks text-xl" style="color: var(--warning);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Assignments</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">View your posts</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>
    </div>
</div>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }

.card {
    transition: all 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}
</style>
@endsection