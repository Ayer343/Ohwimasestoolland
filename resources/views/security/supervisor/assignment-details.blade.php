@extends('layouts.secu')

@section('title', 'Assignment Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-tasks text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                        Assignment Details
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - {{ auth()->user()->badge_number ?? 'No Badge' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor.assignments.current') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    @if(isset($assignment))
    <!-- Assignment Details Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2">
            <div class="card p-6">
                <div class="flex items-start mb-4">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center mr-4 flex-shrink-0"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 22px; font-weight: 600;">
                        {{ $assignment->post ? substr($assignment->post->name, 0, 1) : 'P' }}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold" style="color: var(--text-primary);">
                            {{ $assignment->post->name ?? 'No Post Assigned' }}
                        </h3>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-tag mr-1"></i>
                                {{ $assignment->supervisor_type_name ?? 'Supervisor' }}
                            </span>
                            @if($assignment->is_primary_supervisor)
                                <span class="px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                    <i class="fas fa-star mr-1"></i> Primary Supervisor
                                </span>
                            @endif
                            @if($assignment->is_active)
                                <span class="px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                </span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                    <i class="fas fa-times-circle mr-1"></i> Inactive
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-xs uppercase tracking-wider" style="color: var(--text-secondary);">Start Date</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                            {{ $assignment->start_date->format('l, F j, Y') }}
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-xs uppercase tracking-wider" style="color: var(--text-secondary);">End Date</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--warning);"></i>
                            {{ $assignment->end_date ? $assignment->end_date->format('l, F j, Y') : 'Ongoing' }}
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-xs uppercase tracking-wider" style="color: var(--text-secondary);">Assigned By</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-2" style="color: var(--info);"></i>
                            {{ $assignment->assignedBy->name ?? 'N/A' }}
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-xs uppercase tracking-wider" style="color: var(--text-secondary);">Duration</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                            @php
                                $start = \Carbon\Carbon::parse($assignment->start_date);
                                $end = $assignment->end_date ? \Carbon\Carbon::parse($assignment->end_date) : now();
                                $days = $start->diffInDays($end);
                            @endphp
                            {{ $days }} day{{ $days !== 1 ? 's' : '' }}
                            @if(!$assignment->end_date)
                                <span class="text-xs ml-1" style="color: var(--success);">(Active)</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                @if($assignment->notes)
                    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="text-xs uppercase tracking-wider" style="color: var(--text-secondary);">Notes</div>
                        <div class="mt-1" style="color: var(--text-primary);">{{ $assignment->notes }}</div>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="mt-4 flex flex-wrap gap-3">
                    @if($assignment->security_post_id)
                        <a href="{{ route('security.supervisor.posts.schedule', ['postId' => $assignment->security_post_id]) }}"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-calendar-alt mr-2"></i> View Post Schedule
                        </a>
                    @endif
                    <a href="{{ route('security.supervisor.team.today') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-users mr-2"></i> View Team Today
                    </a>
                    <a href="{{ route('security.supervisor.actions.pending') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-clock mr-2"></i> Pending Approvals
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar - Permissions & Stats -->
        <div class="lg:col-span-1">
            <!-- Permissions -->
            <div class="card p-6 mb-4">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-lock mr-2"></i> Permissions
                </h4>
                <div class="space-y-2">
                    @php
                        $permissions = [
                            'can_override_checkins' => 'Override Check-ins',
                            'can_approve_swaps' => 'Approve Shift Swaps',
                            'can_approve_overtime' => 'Approve Overtime',
                            'can_review_incidents' => 'Review Incidents',
                            'can_verify_checkins' => 'Verify Check-ins',
                            'can_request_backup' => 'Request Backup',
                            'can_approve_breaks' => 'Approve Breaks',
                            'can_escalate_issues' => 'Escalate Issues',
                            'can_view_all_schedules' => 'View All Schedules',
                            'can_edit_schedules' => 'Edit Schedules',
                        ];
                    @endphp
                    @foreach($permissions as $field => $label)
                        <div class="flex items-center justify-between py-1.5 px-2 rounded-lg"
                             style="background-color: var(--bg-secondary);">
                            <span class="text-sm" style="color: var(--text-secondary);">{{ $label }}</span>
                            @if($assignment->$field)
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-check"></i>
                                </span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                    <i class="fas fa-times"></i>
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-chart-simple mr-2"></i> Quick Stats
                </h4>
                <div class="space-y-3">
                    @if(isset($supervisionStats))
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary);">Total Days</span>
                            <span class="font-bold" style="color: var(--text-primary);">{{ $supervisionStats['total_days'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary);">Schedules Overseen</span>
                            <span class="font-bold" style="color: var(--text-primary);">{{ $supervisionStats['schedules_overseen'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary);">Verifications Performed</span>
                            <span class="font-bold" style="color: var(--text-primary);">{{ $supervisionStats['verifications_performed'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary);">Approvals Given</span>
                            <span class="font-bold" style="color: var(--text-primary);">{{ $supervisionStats['approvals_given'] ?? 0 }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @else
        <!-- Assignment Not Found -->
        <div class="card p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-exclamation-triangle text-4xl" style="color: var(--warning);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">Assignment Not Found</h3>
            <p class="mb-6" style="color: var(--text-secondary);">The requested assignment could not be found or you don't have permission to view it.</p>
            <a href="{{ route('security.supervisor.assignments.index') }}" 
               class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                <i class="fas fa-arrow-left mr-2"></i> Back to Assignments
            </a>
        </div>
    @endif
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

.card {
    transition: all 0.2s ease;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-2px);
}
</style>
@endsection