@extends('layouts.secu')

@section('title', "Today's Team")

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-day mr-2" style="color: var(--success);"></i>
                        Today's Team
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
                    <i class="fas fa-eye mr-2"></i> My Assignments
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    @if(isset($stats))
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Personnel</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['checked_in'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Checked In</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['pending'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending Check-in</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-hourglass-half" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['absent'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Absent</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-user-slash" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Team by Post -->
    @if(isset($team) && count($team) > 0)
        @foreach($team as $postName => $schedules)
        <div class="card">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                    {{ $postName }}
                    <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-primary">
                        {{ count($schedules) }} personnel
                    </span>
                </h3>
                
                <!-- Post Stats -->
                <div class="flex items-center space-x-4">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        {{ $schedules->where('check_in_status', 'verified')->count() }}
                    </span>
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-hourglass-half mr-1" style="color: var(--warning);"></i>
                        {{ $schedules->where('check_in_status', 'pending')->count() }}
                    </span>
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-user-slash mr-1" style="color: var(--danger);"></i>
                        {{ $schedules->where('status', 'absent')->count() }}
                    </span>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Security Personnel</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Time</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Check-in</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules as $schedule)
                            <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                                style="border-color: var(--border-color); background-color: var(--card-bg);">
                                
                                <td class="py-4 px-6">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                            {{ $schedule->securityUser ? substr($schedule->securityUser->name, 0, 1) : '?' }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->securityUser->name ?? 'Unknown' }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->securityUser->badge_number ?? 'No Badge' }}</div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-4 px-6">
                                    @if($schedule->shift)
                                        <span class="px-2 py-1 text-xs rounded-full badge-info">
                                            {{ $schedule->shift->name }}
                                        </span>
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">No Shift</span>
                                    @endif
                                </td>
                                
                                <td class="py-4 px-6">
                                    @if($schedule->shift)
                                        <div style="color: var(--text-primary);">{{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}</div>
                                        @if($schedule->shift->is_overnight)
                                            <span class="text-xs" style="color: var(--info);">🌙 Overnight</span>
                                        @endif
                                    @else
                                        <span style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                
                                <td class="py-4 px-6">
                                    @if($schedule->checkin_time)
                                        <div style="color: var(--text-primary);">{{ $schedule->checkin_time->format('H:i') }}</div>
                                        @if($schedule->late_minutes > 0)
                                            <div class="text-xs" style="color: var(--warning);">Late {{ $schedule->late_minutes }}m</div>
                                        @endif
                                    @else
                                        <span style="color: var(--text-secondary);">Not checked in</span>
                                    @endif
                                </td>
                                
                                <td class="py-4 px-6">
                                    @php
                                        $statusColors = [
                                            'verified' => 'success',
                                            'pending' => 'warning',
                                            'absent' => 'danger',
                                            'scheduled' => 'info'
                                        ];
                                        $statusColor = $statusColors[$schedule->check_in_status] ?? 'secondary';
                                    @endphp
                                    <span class="px-2 py-1 text-xs rounded-full badge-{{ $statusColor }}">
                                        {{ ucfirst($schedule->check_in_status) }}
                                    </span>
                                </td>
                                
                                <td class="py-4 px-6">
                                    <div class="flex space-x-2">
                                        <button onclick="viewPersonnel({{ $schedule->securityUser->id ?? 0 }})" 
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                title="View Details">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                        
                                        @if(($schedule->check_in_status == 'pending' && !$schedule->checkin_time) || ($schedule->check_in_status == 'scheduled' && !$schedule->checkin_time))
                                            <button onclick="markAbsent({{ $schedule->id }})" 
                                                    class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                                    title="Mark Absent">
                                                <i class="fas fa-user-slash text-sm"></i>
                                            </button>
                                        @endif
                                        
                                        @if($schedule->check_in_status == 'pending' && $schedule->checkin_time)
                                            <button onclick="verifyCheckin({{ $schedule->id }})" 
                                                    class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                    title="Verify Check-in">
                                                <i class="fas fa-check-circle text-sm"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @else
        <div class="card p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-calendar-times text-4xl" style="color: var(--info);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Team Members Today</h3>
            <p class="mb-6" style="color: var(--text-secondary);">There are no security personnel scheduled for today at your assigned posts.</p>
        </div>
    @endif

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('security.supervisor.actions.pending') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-bell text-xl" style="color: var(--warning);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Pending Approvals</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">Review pending requests</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>
        
        @if(isset($postIdsArray) && count($postIdsArray) > 0)
        <a href="{{ route('security.supervisor.posts.schedule', ['postId' => $postIdsArray[0] ?? null]) }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar-alt text-xl" style="color: var(--info);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">View Schedule</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">See full post schedule</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>
        @endif
        
        <a href="{{ route('security.supervisor.assignments.current') }}" 
           class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-user-tie text-xl" style="color: var(--primary);"></i>
                </div>
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">My Assignments</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">View your supervisor posts</p>
                </div>
            </div>
            <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
        </a>
    </div>
</div>

<!-- Absent Modal -->
<div id="absentModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('absentModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                    Mark as Absent
                </h3>
            </div>
            
            <div class="p-6">
                <form id="absentForm" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Reason for Absence</label>
                        <select name="reason" class="w-full rounded-lg px-4 py-2 mb-3" 
                                style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                            <option value="no_show">No Show</option>
                            <option value="sick">Sick Leave</option>
                            <option value="emergency">Emergency</option>
                            <option value="approved">Approved Leave</option>
                            <option value="other">Other</option>
                        </select>
                        
                        <textarea name="notes" rows="3" class="w-full rounded-lg px-4 py-2" 
                                  style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('absentModal')"
                                class="px-4 py-2 rounded-lg text-sm font-medium"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white"
                                style="background-color: var(--warning);">
                            Mark as Absent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentScheduleId = null;

function viewPersonnel(userId) {
    if(userId > 0) {
        window.location.href = `/security/personnel/${userId}`;
    }
}

function markAbsent(scheduleId) {
    currentScheduleId = scheduleId;
    document.getElementById('absentForm').action = `/security/supervisor/schedules/${scheduleId}/mark-absent`;
    document.getElementById('absentModal').classList.remove('hidden');
}

function verifyCheckin(scheduleId) {
    if(confirm('Verify this check-in?')) {
        fetch(`/security/supervisor/schedules/${scheduleId}/verify-checkin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Check-in verified successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to verify check-in');
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
            console.error('Error:', error);
        });
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('absentModal');
    }
}
</script>

<style>
.action-btn {
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}

.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
</style>
@endsection