@extends('layouts.secu')

@section('title', 'Team Attendance')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-calendar-check text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Team Attendance
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - Attendance Overview</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>{{ Carbon\Carbon::create($year ?? now()->year, $month ?? now()->month)->format('F Y') }}</span>
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

   <!-- Month Navigation -->
<div class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
            @php
                $currentDate = Carbon\Carbon::create($year ?? now()->year, $month ?? now()->month, 1);
                $prevMonth = $prevMonth ?? $currentDate->copy()->subMonth();
                $nextMonth = $nextMonth ?? $currentDate->copy()->addMonth();
            @endphp
            <a href="{{ route('security.supervisor.team.attendance', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}" 
               class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                <i class="fas fa-chevron-left mr-2"></i> Previous Month
            </a>
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                {{ Carbon\Carbon::create($year ?? now()->year, $month ?? now()->month)->format('F Y') }}
            </h3>
            <a href="{{ route('security.supervisor.team.attendance', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}" 
               class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                Next Month <i class="fas fa-chevron-right ml-2"></i>
            </a>
        </div>
        <a href="{{ route('security.supervisor.team.attendance') }}" 
           class="px-4 py-2 rounded-lg text-sm font-medium"
           style="background-color: var(--bg-secondary); color: var(--text-primary);">
            <i class="fas fa-calendar-alt mr-2"></i> Current Month
        </a>
    </div>
</div>

    <!-- Attendance Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar-alt text-xl" style="color: var(--info);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $summary['total_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Total Shifts</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Scheduled this month</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $summary['completed_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Completed</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Shifts completed</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $summary['late_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Late Arrivals</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Shifts with late check-in</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-times-circle text-xl" style="color: var(--danger);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $summary['absent_shifts'] ?? 0 }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Absences</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Missed shifts</p>
        </div>
    </div>

    <!-- Attendance Rate Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--success);"></i>
                Overall Attendance Rate
            </h3>
            <span class="text-3xl font-bold" style="color: {{ ($summary['attendance_rate'] ?? 0) >= 90 ? 'var(--success)' : (($summary['attendance_rate'] ?? 0) >= 75 ? 'var(--warning)' : 'var(--danger)') }};">
                {{ $summary['attendance_rate'] ?? 0 }}%
            </span>
        </div>
        <div class="w-full h-4 rounded-full" style="background-color: var(--bg-secondary);">
            <div class="h-4 rounded-full" 
                 style="width: {{ $summary['attendance_rate'] ?? 0 }}%; background-color: {{ ($summary['attendance_rate'] ?? 0) >= 90 ? 'var(--success)' : (($summary['attendance_rate'] ?? 0) >= 75 ? 'var(--warning)' : 'var(--danger)') }};">
            </div>
        </div>
        <div class="flex justify-between mt-2 text-sm" style="color: var(--text-secondary);">
            <span>0%</span>
            <span>50%</span>
            <span>100%</span>
        </div>
    </div>

    <!-- Attendance Matrix -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--info);"></i>
                Monthly Attendance Matrix
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                <span class="inline-block w-3 h-3 rounded-sm mr-1" style="background-color: var(--success);"></span> Present
                <span class="inline-block w-3 h-3 rounded-sm mr-1 ml-3" style="background-color: var(--warning);"></span> Late
                <span class="inline-block w-3 h-3 rounded-sm mr-1 ml-3" style="background-color: var(--danger);"></span> Absent
                <span class="inline-block w-3 h-3 rounded-sm mr-1 ml-3" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"></span> No Shift
            </p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full min-w-max">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider sticky left-0" style="background-color: var(--bg-secondary); color: var(--text-secondary); min-width: 200px;">
                            Personnel
                        </th>
                        @for($day = 1; $day <= $startDate->daysInMonth; $day++)
                            <th class="text-center py-4 px-2 text-xs font-medium" style="color: var(--text-secondary); min-width: 40px;">
                                {{ $day }}
                                <div class="text-xs" style="color: var(--text-secondary);">{{ Carbon\Carbon::create($year, $month, $day)->format('D') }}</div>
                            </th>
                        @endfor
                        <th class="text-center py-4 px-4 text-xs font-medium" style="color: var(--text-secondary); min-width: 100px;">Stats</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendance ?? [] as $user)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-3 px-6 sticky left-0" style="background-color: var(--card-bg);">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $user['user_name'] }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $user['badge_number'] }}</div>
                            </td>
                            
                            @for($day = 1; $day <= $startDate->daysInMonth; $day++)
                                @php
                                    $dayData = $user['matrix'][$day] ?? null;
                                    $status = $dayData['status'] ?? null;
                                    $lateMinutes = $dayData['late_minutes'] ?? 0;
                                    
                                    $bgColor = 'var(--bg-secondary)';
                                    $title = 'No shift scheduled';
                                    $textColor = 'var(--text-secondary)';
                                    
                                    if ($status === 'completed') {
                                        if ($lateMinutes > 0) {
                                            $bgColor = 'var(--warning)';
                                            $title = 'Late by ' . $lateMinutes . ' minutes';
                                            $textColor = 'white';
                                        } else {
                                            $bgColor = 'var(--success)';
                                            $title = 'Present - On time';
                                            $textColor = 'white';
                                        }
                                    } elseif ($status === 'absent') {
                                        $bgColor = 'var(--danger)';
                                        $title = 'Absent';
                                        $textColor = 'white';
                                    }
                                @endphp
                                <td class="text-center py-3 px-2">
                                    <div class="w-8 h-8 mx-auto rounded-lg flex items-center justify-center text-xs font-medium"
                                         style="background-color: {{ $bgColor }}; color: {{ $textColor }};"
                                         title="{{ $title }}">
                                        @if($status === 'completed' && $lateMinutes == 0)
                                            <i class="fas fa-check text-xs"></i>
                                        @elseif($status === 'completed' && $lateMinutes > 0)
                                            <i class="fas fa-clock text-xs"></i>
                                        @elseif($status === 'absent')
                                            <i class="fas fa-times text-xs"></i>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                            @endfor
                            
                            <td class="text-center py-3 px-4">
                                <div class="text-sm font-medium" style="color: var(--text-primary);">{{ $user['attendance_rate'] }}%</div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $user['completed_shifts'] }}/{{ $user['total_shifts'] }}
                                </div>
                                @if($user['late_shifts'] > 0)
                                    <div class="text-xs mt-1" style="color: var(--warning);">
                                        <i class="fas fa-clock mr-1"></i>{{ $user['late_shifts'] }} late
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $startDate->daysInMonth + 2 }}" class="py-8 text-center" style="color: var(--text-secondary);">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-calendar-times text-4xl mb-3"></i>
                                    <p>No attendance data available for this month</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legend and Notes -->
    <div class="card p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Attendance Legend</h4>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded mr-3" style="background-color: var(--success);"></div>
                        <span style="color: var(--text-primary);">Present - Checked in on time</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded mr-3" style="background-color: var(--warning);"></div>
                        <span style="color: var(--text-primary);">Late - Checked in after shift start</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded mr-3" style="background-color: var(--danger);"></div>
                        <span style="color: var(--text-primary);">Absent - Did not show up</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded mr-3" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"></div>
                        <span style="color: var(--text-primary);">No shift scheduled for this day</span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Quick Statistics</h4>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Total Personnel:</span>
                        <span class="font-medium" style="color: var(--text-primary);">{{ count($attendance ?? []) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Average Attendance Rate:</span>
                        <span class="font-medium" style="color: var(--text-primary);">{{ $summary['attendance_rate'] ?? 0 }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Perfect Attendance:</span>
                        <span class="font-medium" style="color: var(--success);">
                            {{ collect($attendance ?? [])->where('attendance_rate', 100)->count() }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Needs Improvement (&lt;75%):</span>
                        <span class="font-medium" style="color: var(--warning);">
                            {{ collect($attendance ?? [])->where('attendance_rate', '<', 75)->count() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Sticky first column */
.sticky-left {
    position: sticky;
    left: 0;
    z-index: 10;
}

/* Badge styles */
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>
@endsection