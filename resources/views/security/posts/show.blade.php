@extends('layouts.secu')

@section('title', $securityPost->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-building text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        {{ $securityPost->name }}
                        <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                            {{ $securityPost->code ?? 'N/A' }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        {{ $securityPost->location ?? 'No location specified' }}
                        <span class="mx-2">•</span>
                        <i class="fas fa-tag mr-1"></i>
                        {{ ucfirst(str_replace('_', ' ', $securityPost->type)) }}
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.posts.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <a href="{{ route('security.posts.schedule', ['securityPost' => $securityPost->id]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-calendar-alt mr-2"></i> View Schedule
                </a>
            </div>
        </div>
    </div>

    <!-- Post Info Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Staffing Card -->
        <div class="card p-6">
            <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-users mr-2"></i> Staffing
            </h4>
            <div class="flex items-center justify-between mb-2">
                <span style="color: var(--text-secondary);">Current Personnel</span>
                <span class="text-xl font-bold" style="color: var(--text-primary);">
                    {{ $todaySchedules->count() }}/{{ $securityPost->max_personnel }}
                </span>
            </div>
            <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                @php
                    $percentage = $securityPost->max_personnel > 0 
                        ? min(100, ($todaySchedules->count() / $securityPost->max_personnel) * 100) 
                        : 0;
                    $color = $percentage >= 100 ? 'var(--success)' : ($percentage > 0 ? 'var(--warning)' : 'var(--danger)');
                @endphp
                <div class="rounded-full h-2 transition-all duration-500" 
                     style="width: {{ $percentage }}%; background-color: {{ $color }};"></div>
            </div>
            <div class="mt-3 flex justify-between text-xs" style="color: var(--text-secondary);">
                <span>{{ $stats['staffing_rate'] ?? 0 }}% Staffed</span>
                <span>Max: {{ $securityPost->max_personnel }}</span>
            </div>
        </div>

        <!-- Working Hours Card -->
        <div class="card p-6">
            <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-2"></i> Working Hours
            </h4>
            @if($securityPost->working_hours)
                <div class="text-lg font-semibold" style="color: var(--text-primary);">
                    {{ $securityPost->working_hours['start'] ?? 'N/A' }} - {{ $securityPost->working_hours['end'] ?? 'N/A' }}
                </div>
            @else
                <div class="text-lg font-semibold" style="color: var(--text-primary);">24/7</div>
            @endif
            <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-{{ $securityPost->requires_checkin ? 'check-circle' : 'times-circle' }} mr-1" 
                   style="color: {{ $securityPost->requires_checkin ? 'var(--success)' : 'var(--danger)' }};"></i>
                {{ $securityPost->requires_checkin ? 'Requires' : 'Does not require' }} check-in
            </div>
        </div>

        <!-- Status Card -->
        <div class="card p-6">
            <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-2"></i> Status
            </h4>
            <div class="flex items-center">
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    {{ $securityPost->is_active ? 'badge-success' : 'badge-danger' }}">
                    {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                </span>
                @php
                    $status = $todaySchedules->count() >= $securityPost->max_personnel 
                        ? 'Fully Staffed' 
                        : ($todaySchedules->count() > 0 ? 'Understaffed' : 'Unstaffed');
                    $statusColor = $todaySchedules->count() >= $securityPost->max_personnel 
                        ? 'success' 
                        : ($todaySchedules->count() > 0 ? 'warning' : 'danger');
                @endphp
                <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium badge-{{ $statusColor }}">
                    {{ $status }}
                </span>
            </div>
            <div class="mt-3 text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-day mr-1"></i>
                {{ $todaySchedules->count() }} personnel today
            </div>
        </div>
    </div>

    <!-- Equipment & Restrictions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @if(!empty($securityPost->equipment))
        <div class="card p-6">
            <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-tools mr-2"></i> Equipment
            </h4>
            <div class="flex flex-wrap gap-2">
                @foreach(is_array($securityPost->equipment) ? $securityPost->equipment : [] as $item)
                    <span class="px-3 py-1 rounded-full text-xs badge-info">
                        {{ $item }}
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($securityPost->restrictions))
        <div class="card p-6">
            <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-exclamation-circle mr-2"></i> Restrictions
            </h4>
            <ul class="space-y-2">
                @foreach(is_array($securityPost->restrictions) ? $securityPost->restrictions : [] as $restriction)
                    <li class="flex items-start">
                        <i class="fas fa-circle text-xs mt-1.5 mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--text-secondary);">{{ $restriction }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <!-- Today's Personnel -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-clock mr-2" style="color: var(--primary);"></i>
                Today's Personnel
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                    {{ $todaySchedules->count() }}
                </span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            @if($todaySchedules->count() > 0)
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Time</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todaySchedules as $schedule)
                            <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                                style="border-color: var(--border-color); background-color: var(--card-bg);">
                                <td class="py-4 px-6">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600;">
                                            {{ $schedule->securityUser ? substr($schedule->securityUser->name, 0, 1) : '?' }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->securityUser->name ?? 'Unknown' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $schedule->securityUser->badge_number ?? 'No Badge' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    @if($schedule->shift)
                                        <span class="px-2 py-1 text-xs rounded-full badge-info">
                                            {{ $schedule->shift->name }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @if($schedule->shift)
                                        <span style="color: var(--text-primary);">
                                            {{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @php
                                        $statusColors = [
                                            'active' => 'success',
                                            'scheduled' => 'info',
                                            'completed' => 'primary',
                                            'absent' => 'danger',
                                            'pending' => 'warning'
                                        ];
                                        $color = $statusColors[$schedule->status] ?? 'secondary';
                                    @endphp
                                    <span class="px-2 py-1 text-xs rounded-full badge-{{ $color }}">
                                        {{ ucfirst($schedule->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-8 text-center">
                    <i class="fas fa-user-slash text-2xl mb-2" style="color: var(--text-secondary);"></i>
                    <p style="color: var(--text-secondary);">No personnel scheduled for today.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Upcoming Schedule Preview -->
    @if(isset($upcomingSchedules) && $upcomingSchedules->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-calendar-week mr-2" style="color: var(--primary);"></i>
                Upcoming Schedule (Next 7 Days)
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                    {{ $upcomingSchedules->count() }}
                </span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($upcomingSchedules as $schedule)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6" style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($schedule->assignment_date)->format('D, M d') }}
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600;">
                                        {{ $schedule->securityUser ? substr($schedule->securityUser->name, 0, 1) : '?' }}
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->name ?? 'Unknown' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                @if($schedule->shift)
                                    <span class="px-2 py-1 text-xs rounded-full badge-info">
                                        {{ $schedule->shift->name }}
                                    </span>
                                @else
                                    <span style="color: var(--text-secondary);">N/A</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-info">
                                    {{ ucfirst($schedule->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('security.posts.schedule', ['securityPost' => $securityPost->id]) }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-calendar-alt text-xl" style="color: var(--primary);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">View Schedule</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">See full post schedule</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>

        @if(isset($isSupervisorForPost) && $isSupervisorForPost)
            <a href="{{ route('security.supervisor.team.today') }}" 
               class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-users text-xl" style="color: var(--success);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Team Today</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">View your team</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
            </a>
        @endif

        <a href="{{ route('security.posts.index') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-building text-xl" style="color: var(--info);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">All Posts</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">Back to all posts</p>
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

.card {
    transition: all 0.2s ease;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-2px);
}

.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
</style>
@endsection