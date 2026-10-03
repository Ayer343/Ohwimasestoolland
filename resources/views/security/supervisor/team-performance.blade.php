@extends('layouts.secu')

@section('title', 'Team Performance')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Team Performance
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - Performance Overview</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>{{ $startDate ?? now()->subDays(30)->format('M j, Y') }} - {{ $endDate ?? now()->format('M j, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor.team.today') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-day mr-2"></i> Today's Team
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card p-6">
        <form method="GET" action="{{ route('security.supervisor.team.performance') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate ?? now()->subDays(30)->format('Y-m-d') }}" 
                       class="rounded-lg px-4 py-2" style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate ?? now()->format('Y-m-d') }}" 
                       class="rounded-lg px-4 py-2" style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
            </div>
            <div>
                <button type="submit" class="px-6 py-2 rounded-lg text-sm font-medium text-white btn-primary">
                    <i class="fas fa-filter mr-2"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Performance Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar-check text-xl" style="color: var(--info);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $performance['total_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Total Shifts</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Scheduled in this period</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $performance['completed_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Completed Shifts</h3>
            <p class="text-sm" style="color: var(--text-secondary);">{{ $performance['attendance_rate'] ?? 0 }}% attendance rate</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $performance['late_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Late Arrivals</h3>
            <p class="text-sm" style="color: var(--text-secondary);">{{ $performance['punctuality_rate'] ?? 0 }}% punctuality rate</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-hourglass-end text-xl" style="color: var(--danger);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $performance['absent_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Absences</h3>
            <p class="text-sm" style="color: var(--text-secondary);">{{ $performance['overtime_shifts'] ?? 0 }} shifts with overtime</p>
        </div>
    </div>

    <!-- Detailed Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Late & Overtime Stats -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-bar mr-2" style="color: var(--warning);"></i>
                Late & Overtime Metrics
            </h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between mb-2">
                        <span style="color: var(--text-secondary);">Total Late Minutes</span>
                        <span class="font-semibold" style="color: var(--warning);">{{ $performance['total_late_minutes'] ?? 0 }} mins</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                        <div class="h-2 rounded-full" style="width: {{ min(100, ($performance['total_late_minutes'] ?? 0) / 10) }}%; background-color: var(--warning);"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-2">
                        <span style="color: var(--text-secondary);">Average Late Minutes</span>
                        <span class="font-semibold" style="color: var(--warning);">{{ $performance['average_late_minutes'] ?? 0 }} mins</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-2">
                        <span style="color: var(--text-secondary);">Total Overtime Minutes</span>
                        <span class="font-semibold" style="color: var(--success);">{{ $performance['total_overtime_minutes'] ?? 0 }} mins</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                        <div class="h-2 rounded-full" style="width: {{ min(100, ($performance['total_overtime_minutes'] ?? 0) / 10) }}%; background-color: var(--success);"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-2">
                        <span style="color: var(--text-secondary);">Average Overtime Minutes</span>
                        <span class="font-semibold" style="color: var(--success);">{{ $performance['average_overtime_minutes'] ?? 0 }} mins</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Trends -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i>
                Daily Performance Trends
            </h3>
            <div class="space-y-3 max-h-64 overflow-y-auto pr-2">
                @forelse($trends ?? [] as $trend)
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium" style="color: var(--text-primary);">{{ Carbon\Carbon::parse($trend['date'])->format('M j, Y') }}</span>
                            <span class="text-sm px-2 py-1 rounded-full badge-{{ $trend['attendance_rate'] >= 90 ? 'success' : ($trend['attendance_rate'] >= 75 ? 'warning' : 'danger') }}">
                                {{ $trend['attendance_rate'] }}%
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <span style="color: var(--text-secondary);">Shifts:</span>
                                <span class="ml-1 font-medium" style="color: var(--text-primary);">{{ $trend['total_shifts'] }}</span>
                            </div>
                            <div>
                                <span style="color: var(--text-secondary);">Late:</span>
                                <span class="ml-1 font-medium" style="color: var(--warning);">{{ $trend['late_count'] }}</span>
                            </div>
                            <div>
                                <span style="color: var(--text-secondary);">OT:</span>
                                <span class="ml-1 font-medium" style="color: var(--success);">{{ $trend['overtime_count'] }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8" style="color: var(--text-secondary);">
                        <i class="fas fa-chart-line text-3xl mb-3"></i>
                        <p>No trend data available</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Performance by User -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-check mr-2" style="color: var(--success);"></i>
                Performance by Personnel
            </h3>
            <span class="text-sm" style="color: var(--text-secondary);">Sorted by attendance rate</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Badge</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Total Shifts</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completed</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Attendance</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Late</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Punctuality</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">OT Shifts</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Late Mins</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">OT Mins</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userPerformance ?? [] as $user)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $user['user_name'] }}</div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-xs" style="color: var(--text-secondary);">{{ $user['badge_number'] }}</span>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $user['total_shifts'] }}</td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $user['completed_shifts'] }}</td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-{{ $user['attendance_rate'] >= 90 ? 'success' : ($user['attendance_rate'] >= 75 ? 'warning' : 'danger') }}">
                                    {{ $user['attendance_rate'] }}%
                                </span>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $user['late_shifts'] }}</td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-{{ $user['punctuality_rate'] >= 90 ? 'success' : ($user['punctuality_rate'] >= 75 ? 'warning' : 'danger') }}">
                                    {{ $user['punctuality_rate'] }}%
                                </span>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $user['overtime_shifts'] }}</td>
                            <td class="py-4 px-6" style="color: var(--warning);">{{ $user['total_late_minutes'] }}</td>
                            <td class="py-4 px-6" style="color: var(--success);">{{ $user['total_overtime_minutes'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center" style="color: var(--text-secondary);">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users text-4xl mb-3"></i>
                                    <p>No performance data available for this period</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Performance by Post -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-building mr-2" style="color: var(--info);"></i>
                Performance by Security Post
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Security Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Total Shifts</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completed</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Attendance Rate</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Late Count</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Overtime Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($postPerformance ?? [] as $post)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $post['post_name'] }}</div>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $post['total_shifts'] }}</td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $post['completed_shifts'] }}</td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-{{ $post['attendance_rate'] >= 90 ? 'success' : ($post['attendance_rate'] >= 75 ? 'warning' : 'danger') }}">
                                    {{ $post['attendance_rate'] }}%
                                </span>
                            </td>
                            <td class="py-4 px-6" style="color: var(--warning);">{{ $post['late_count'] }}</td>
                            <td class="py-4 px-6" style="color: var(--success);">{{ $post['overtime_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center" style="color: var(--text-secondary);">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-building text-4xl mb-3"></i>
                                    <p>No post performance data available</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>
@endsection