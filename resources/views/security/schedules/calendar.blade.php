@extends('layouts.secu')

@section('title', 'Security Schedule Calendar')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Schedule Calendar</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Monthly view of security schedules and assignments</p>
            </div>
            <div class="flex items-center space-x-2 flex-wrap gap-2">
                <a href="{{ route('security.schedules.index') }}"
                   class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list mr-2"></i> List View
                </a>
                <a href="{{ route('security.schedules.create') }}"
                   class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-plus mr-2"></i> Create
                </a>
                <a href="{{ route('security.schedules.trash') }}"
                   class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium"
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                </a>
            </div>
        </div>
    </div>

    <!-- Calendar Navigation Card -->
    <div class="card p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div class="flex items-center space-x-2 flex-wrap gap-2">
                <a href="{{ route('security.schedules.calendar', [
                    'start_date' => $startDate->copy()->subMonth()->format('Y-m-d'),
                    'end_date' => $endDate->copy()->subMonth()->format('Y-m-d')
                ]) }}"
                   class="btn-secondary flex items-center px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-0.5">
                    <i class="fas fa-chevron-left mr-2"></i> Previous Month
                </a>

                <div class="px-4 py-2 rounded-lg border"
                     style="background-color: rgba(var(--primary-rgb), 0.05); border-color: rgba(var(--primary-rgb), 0.2);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        {{ $startDate->format('F Y') }}
                    </h3>
                </div>

                <a href="{{ route('security.schedules.calendar', [
                    'start_date' => $startDate->copy()->addMonth()->format('Y-m-d'),
                    'end_date' => $endDate->copy()->addMonth()->format('Y-m-d')
                ]) }}"
                   class="btn-secondary flex items-center px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-0.5">
                    Next Month <i class="fas fa-chevron-right ml-2"></i>
                </a>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('security.schedules.calendar', [
                    'start_date' => today()->format('Y-m-d'),
                    'end_date' => today()->format('Y-m-d')
                ]) }}"
                   class="btn-primary flex items-center px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-0.5">
                    <i class="fas fa-calendar-day mr-2"></i> Today
                </a>

                <div class="relative group">
                    <button class="btn-modern flex items-center px-4 py-2 rounded-lg"
                            style="background: linear-gradient(to right, var(--info), var(--info)); color: white;">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <div class="absolute right-0 mt-2 w-64 rounded-lg shadow-xl border z-50 hidden group-hover:block"
                         style="background-color: var(--card-bg); border-color: var(--border-color);">
                        <div class="p-4">
                            <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Filter Options</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Post</label>
                                    <select class="w-full p-2 text-sm rounded border"
                                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                        <option value="">All Posts</option>
                                        @foreach($securityPosts as $post)
                                            <option value="{{ $post->id }}">{{ $post->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                                    <select class="w-full p-2 text-sm rounded border"
                                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                        <option value="">All Status</option>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="absent">Absent</option>
                                    </select>
                                </div>
                                <button type="button" class="w-full btn-primary btn-sm mt-2">
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--primary-rgb), 0.05); border-color: rgba(var(--primary-rgb), 0.2);">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-calendar-check" style="color: var(--primary);"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Schedules</p>
                        <p class="text-xl font-bold mt-1" style="color: var(--text-primary);">
                            {{ $totalSchedules ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-user-check" style="color: var(--success);"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Active Today</p>
                        <p class="text-xl font-bold mt-1" style="color: var(--text-primary);">
                            {{ $activeToday ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Absent Today</p>
                        <p class="text-xl font-bold mt-1" style="color: var(--text-primary);">
                            {{ $absentToday ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-users" style="color: var(--info);"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Personnel</p>
                        <p class="text-xl font-bold mt-1" style="color: var(--text-primary);">
                            {{ $totalPersonnel ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mb-6 p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
            <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Schedule Status Legend</h4>
            <div class="flex flex-wrap gap-3">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: var(--success);"></div>
                    <span class="text-sm" style="color: var(--text-secondary);">Active</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: var(--info);"></div>
                    <span class="text-sm" style="color: var(--text-secondary);">Completed</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: var(--secondary);"></div>
                    <span class="text-sm" style="color: var(--text-secondary);">Scheduled</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></div>
                    <span class="text-sm" style="color: var(--text-secondary);">Absent</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: var(--warning);"></div>
                    <span class="text-sm" style="color: var(--text-secondary);">Today</span>
                </div>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid border rounded-xl overflow-hidden" style="border-color: var(--border-color); background-color: var(--card-bg);">
            <!-- Day Headers -->
            <div class="calendar-row header" style="border-color: var(--border-color);">
                @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                    <div class="calendar-cell day-header" style="border-color: var(--border-color);">
                        <span class="font-semibold" style="color: var(--text-primary);">{{ substr($day, 0, 3) }}</span>
                    </div>
                @endforeach
            </div>

            <!-- Calendar Weeks -->
            @php
                $currentDate = $startDate->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
                $calendarEnd = $endDate->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
                $weekNumber = 0;
            @endphp

            @while($currentDate->lte($calendarEnd))
                <div class="calendar-row {{ $weekNumber % 2 === 0 ? 'bg-opacity-50' : '' }}"
                     style="border-color: var(--border-color); {{ $weekNumber % 2 === 0 ? 'background-color: rgba(var(--primary-rgb), 0.02);' : '' }}">
                    @for($i = 0; $i < 7; $i++)
                        @php
                            $dateKey = $currentDate->format('Y-m-d');
                            $dateSchedules = $schedules[$dateKey] ?? collect();
                            $isToday = $currentDate->isToday();
                            $isCurrentMonth = $currentDate->month == $startDate->month;
                            $isWeekend = $currentDate->isWeekend();
                        @endphp

                        <div class="calendar-cell {{ $isToday ? 'today' : '' }} {{ !$isCurrentMonth ? 'other-month' : '' }}"
                             style="border-color: var(--border-color);"
                             data-date="{{ $dateKey }}"
                             onclick="showDateDetails('{{ $dateKey }}')">

                            <!-- Date Header -->
                            <div class="date-header" style="border-color: var(--border-color);">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="text-lg font-bold mr-2 {{ $isToday ? 'today-date' : '' }}"
                                              style="color: {{ $isToday ? 'var(--warning)' : 'var(--text-primary)' }};">
                                            {{ $currentDate->format('j') }}
                                        </span>
                                        @if($isToday)
                                            <span class="text-xs px-2 py-1 rounded-full font-medium"
                                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                Today
                                            </span>
                                        @endif
                                    </div>
                                    @if($dateSchedules->count() > 0)
                                        <span class="text-xs px-2 py-1 rounded-full font-medium"
                                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            {{ $dateSchedules->count() }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-1">
                                    <span class="text-xs {{ !$isCurrentMonth ? 'other-month-text' : '' }}"
                                          style="color: {{ !$isCurrentMonth ? 'var(--text-secondary)' : ($isWeekend ? 'var(--danger)' : 'var(--text-secondary)') }};">
                                        {{ $currentDate->format('D') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Schedule Items -->
                            <div class="schedule-list">
                                @foreach($dateSchedules->take(3) as $schedule)
                                    @php
                                        // Guard against missing relations — a schedule without a user/post/shift
                                        // shouldn't blow up the whole calendar.
                                        $userName = $schedule->securityUser->name ?? 'Unknown';
                                        $postName = $schedule->post->name ?? 'Unknown Post';
                                        $timeRange = $schedule->shift && method_exists($schedule->shift, 'getTimeRange')
                                            ? $schedule->shift->getTimeRange()
                                            : (($schedule->shift->start_time ?? '?') . ' - ' . ($schedule->shift->end_time ?? '?'));
                                        $status = $schedule->status ?? 'scheduled';
                                    @endphp
                                    <div class="schedule-item"
                                         onclick="event.stopPropagation(); showScheduleDetails({{ $schedule->id }})"
                                         style="border-left-color: {{ $schedule->post->color ?? 'var(--primary)' }};
                                                background-color: {{
                                                    $status == 'active' ? 'rgba(var(--success-rgb), 0.05)' :
                                                    ($status == 'completed' ? 'rgba(var(--info-rgb), 0.05)' :
                                                    ($status == 'absent' ? 'rgba(var(--danger-rgb), 0.05)' : 'rgba(var(--secondary-rgb), 0.05)'))
                                                }};
                                                border-color: {{
                                                    $status == 'active' ? 'rgba(var(--success-rgb), 0.2)' :
                                                    ($status == 'completed' ? 'rgba(var(--info-rgb), 0.2)' :
                                                    ($status == 'absent' ? 'rgba(var(--danger-rgb), 0.2)' : 'rgba(var(--secondary-rgb), 0.2)'))
                                                }};">
                                        <div class="schedule-content">
                                            <div class="flex items-center justify-between mb-1">
                                                <div class="flex items-center">
                                                    <div class="avatar-xs mr-2">
                                                        {{ strtoupper(substr($userName, 0, 2)) }}
                                                    </div>
                                                    <span class="text-xs font-medium truncate" style="color: var(--text-primary);">
                                                        {{ \Illuminate\Support\Str::limit($userName, 12) }}
                                                    </span>
                                                </div>
                                                <span class="status-badge {{ $status }}">
                                                    {{ substr(ucfirst($status), 0, 1) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                <span class="truncate">{{ \Illuminate\Support\Str::limit($postName, 15) }}</span>
                                            </div>
                                            <div class="flex items-center text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-clock mr-1"></i>
                                                <span>{{ $timeRange }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if($dateSchedules->count() > 3)
                                    <div class="text-center mt-2">
                                        <button onclick="event.stopPropagation(); showDateDetails('{{ $dateKey }}')"
                                                class="text-xs font-medium px-2 py-1 rounded transition-colors duration-150"
                                                style="color: var(--primary); background-color: rgba(var(--primary-rgb), 0.1);">
                                            +{{ $dateSchedules->count() - 3 }} more
                                        </button>
                                    </div>
                                @endif

                                @if($dateSchedules->isEmpty())
                                    <div class="text-center py-3">
                                        <i class="fas fa-calendar-times text-xl mb-2" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                        <p class="text-xs" style="color: var(--text-secondary);">No schedules</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @php $currentDate->addDay(); @endphp
                    @endfor
                </div>
                @php $weekNumber++; @endphp
            @endwhile
        </div>
    </div>
</div>

<!-- Date Details Modal -->
<div id="dateDetailsModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeModal('dateDetailsModal')"></div>
    <div class="modal-container" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalDateTitle">Date Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('dateDetailsModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4" id="dateDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('dateDetailsModal')">
                Close
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Blade-injected routes — avoids hardcoding admin paths in JS
const CALENDAR_ROUTES = {
    dateDetails: "{{ route('security.schedules.date-details') }}",
    show:        (id) => "{{ url('security/schedules') }}/" + id,
    create:      "{{ route('security.schedules.create') }}",
};

document.addEventListener('DOMContentLoaded', function() {
    // Add hover hint to calendar cells
    const calendarCells = document.querySelectorAll('.calendar-cell:not(.day-header)');
    calendarCells.forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            if (!this.classList.contains('today') && !this.classList.contains('other-month')) {
                this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.03)';
            }
        });

        cell.addEventListener('mouseleave', function() {
            if (!this.classList.contains('today') && !this.classList.contains('other-month')) {
                this.style.backgroundColor = '';
            }
        });
    });
});

async function showDateDetails(date) {
    try {
        const response = await fetch(`${CALENDAR_ROUTES.dateDetails}?date=${date}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            const modalTitle = document.getElementById('modalDateTitle');
            const modalContent = document.getElementById('dateDetailsContent');

            const formattedDate = new Date(date + 'T12:00:00').toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            modalTitle.textContent = `Schedules for ${formattedDate}`;

            let html = '';

            if (data.schedules && data.schedules.length > 0) {
                html += `
                    <div class="mb-4 p-4 rounded-lg border" style="background-color: rgba(var(--primary-rgb), 0.05); border-color: rgba(var(--primary-rgb), 0.2);">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-semibold" style="color: var(--text-primary);">Summary</h4>
                            <span class="px-3 py-1 rounded-full text-sm font-medium"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                ${data.schedules.length} schedule${data.schedules.length !== 1 ? 's' : ''}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="text-center p-3 rounded" style="background-color: var(--bg-secondary);">
                                <p class="text-sm" style="color: var(--text-secondary);">Active</p>
                                <p class="text-xl font-bold mt-1" style="color: var(--success);">${data.summary?.active || 0}</p>
                            </div>
                            <div class="text-center p-3 rounded" style="background-color: var(--bg-secondary);">
                                <p class="text-sm" style="color: var(--text-secondary);">Scheduled</p>
                                <p class="text-xl font-bold mt-1" style="color: var(--secondary);">${data.summary?.scheduled || 0}</p>
                            </div>
                            <div class="text-center p-3 rounded" style="background-color: var(--bg-secondary);">
                                <p class="text-sm" style="color: var(--text-secondary);">Completed</p>
                                <p class="text-xl font-bold mt-1" style="color: var(--info);">${data.summary?.completed || 0}</p>
                            </div>
                            <div class="text-center p-3 rounded" style="background-color: var(--bg-secondary);">
                                <p class="text-sm" style="color: var(--text-secondary);">Absent</p>
                                <p class="text-xl font-bold mt-1" style="color: var(--danger);">${data.summary?.absent || 0}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="font-semibold" style="color: var(--text-primary);">Schedule Details</h4>
                `;

                data.schedules.forEach(schedule => {
                    const statusColors = {
                        'active': 'success',
                        'scheduled': 'secondary',
                        'completed': 'info',
                        'absent': 'danger'
                    };
                    const colorKey = statusColors[schedule.status] || 'secondary';

                    html += `
                        <div class="p-4 rounded-lg border hover:transform hover:-translate-y-1 transition-all duration-200 cursor-pointer"
                             style="background-color: var(--bg-secondary); border-color: var(--border-color);"
                             onclick="showScheduleDetails(${schedule.id})">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-3">
                                    <div class="avatar">
                                        ${(schedule.security_user?.name || '--').substring(0, 2).toUpperCase()}
                                    </div>
                                    <div>
                                        <h5 class="font-semibold" style="color: var(--text-primary);">
                                            ${schedule.security_user?.name || 'Unknown'}
                                        </h5>
                                        <div class="flex items-center space-x-4 mt-2 flex-wrap gap-2">
                                            <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-map-marker-alt mr-2"></i>
                                                ${schedule.post?.name || 'Unknown Post'}
                                            </div>
                                            <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-clock mr-2"></i>
                                                ${schedule.shift?.time_range || 'Unknown Time'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: rgba(var(--${colorKey}-rgb), 0.1);
                                                 color: var(--${colorKey});">
                                        ${(schedule.status || 'unknown').charAt(0).toUpperCase() + (schedule.status || 'unknown').slice(1)}
                                    </span>
                                    <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i>
                                        ${schedule.assigned_by?.name || 'System'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
            } else {
                html = `
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-5xl mb-4" style="color: var(--text-secondary); opacity: 0.3;"></i>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No schedules for this date</h4>
                        <p style="color: var(--text-secondary);">No security schedules are assigned for ${formattedDate}</p>
                        <div class="mt-6">
                            <a href="${CALENDAR_ROUTES.create}" class="btn-primary inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i> Create Schedule
                            </a>
                        </div>
                    </div>
                `;
            }

            modalContent.innerHTML = html;
            openModal('dateDetailsModal');
        } else {
            showNotification(data.message || 'Failed to load date details', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Network error occurred', 'error');
    }
}

// The supervisor `show()` method renders a Blade view, not JSON.
// Navigate to it directly instead of trying to parse it as JSON.
function showScheduleDetails(scheduleId) {
    window.location.href = CALENDAR_ROUTES.show(scheduleId);
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function showNotification(message, type = 'info') {
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notification => notification.remove());

    const notification = document.createElement('div');
    notification.className = `notification-toast fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-y-0 opacity-100`;

    let backgroundColor, textColor, icon;
    switch(type) {
        case 'success':
            backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            textColor = 'var(--success)';
            icon = 'fa-check-circle';
            break;
        case 'error':
            backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            textColor = 'var(--danger)';
            icon = 'fa-times-circle';
            break;
        case 'warning':
            backgroundColor = 'rgba(var(--warning-rgb), 0.1)';
            textColor = 'var(--warning)';
            icon = 'fa-exclamation-triangle';
            break;
        default:
            backgroundColor = 'rgba(var(--info-rgb), 0.1)';
            textColor = 'var(--info)';
            icon = 'fa-info-circle';
    }

    notification.style.backgroundColor = backgroundColor;
    notification.style.color = textColor;
    notification.style.borderLeft = `4px solid ${textColor}`;

    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-3 text-lg"></i>
            <span class="font-medium">${message}</span>
            <button class="ml-4 text-gray-500 hover:text-gray-700" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.transform = 'translateY(-20px)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Close modal with Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => {
            if (m.style.display === 'block') {
                m.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});
</script>
@endpush

@push('styles')
<style>
/* Calendar Grid */
.calendar-grid {
    border-radius: 12px;
    overflow: hidden;
}

.calendar-row {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.calendar-row:last-child {
    border-bottom: none;
}

.calendar-row.header {
    background-color: var(--bg-secondary);
}

.calendar-cell {
    flex: 1;
    min-height: 140px;
    padding: 12px;
    border-right: 1px solid var(--border-color);
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-cell:last-child {
    border-right: none;
}

.calendar-cell.day-header {
    min-height: auto;
    padding: 16px 12px;
    text-align: center;
    cursor: default;
}

.calendar-cell.today {
    background-color: rgba(var(--warning-rgb), 0.05);
}

.calendar-cell.other-month {
    background-color: rgba(var(--secondary-rgb), 0.03);
}

.calendar-cell:hover:not(.day-header):not(.other-month) {
    background-color: rgba(var(--primary-rgb), 0.03);
}

.date-header {
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.schedule-list {
    max-height: 100px;
    overflow-y: auto;
    padding-right: 4px;
}

.schedule-list::-webkit-scrollbar {
    width: 4px;
}

.schedule-list::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.1);
    border-radius: 2px;
}

.schedule-list::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 2px;
}

.schedule-item {
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 6px;
    border-left-width: 3px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.schedule-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Avatar Sizes */
.avatar-xs {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 11px;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}

/* Status Badges */
.status-badge {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
    color: white;
}

.status-badge.active { background-color: var(--success); }
.status-badge.scheduled { background-color: var(--secondary); }
.status-badge.completed { background-color: var(--info); }
.status-badge.absent { background-color: var(--danger); }

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .calendar-cell { min-height: 100px; padding: 8px; }
    .calendar-cell.day-header { padding: 12px 6px; font-size: 12px; }
    .date-header strong { font-size: 14px; }
    .schedule-item { padding: 6px; font-size: 10px; }
    .avatar-xs { width: 20px; height: 20px; font-size: 9px; }
    .modal-container { margin: 1rem; max-height: 80vh; }
}

@media (max-width: 640px) {
    .calendar-cell { min-height: 80px; }
    .schedule-list { max-height: 60px; }
    .date-header { flex-direction: column; align-items: flex-start; gap: 2px; }
    .date-header span:last-child { font-size: 10px; }
}

/* Dark mode adjustments */
[data-theme="dark"] .calendar-cell.today {
    background-color: rgba(var(--warning-rgb), 0.1);
}

[data-theme="dark"] .calendar-cell.other-month {
    background-color: rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .calendar-row.header {
    background-color: rgba(0, 0, 0, 0.2);
}

/* Button sizes (shared with other secu blades) */
.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
}

.btn-modern {
    color: white;
    font-weight: 500;
    border: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>
@endpush