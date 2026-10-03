@extends('layouts.secu')

@section('title', 'Schedule Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header with Back Button -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <a href="{{ route('security.schedules.index') }}" 
                   class="mr-4 w-10 h-10 rounded-lg flex items-center justify-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        Schedule Details
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span>{{ $schedule->assignment_date->format('l, F j, Y') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <button onclick="addToCalendar({{ $schedule->id }})"
                        class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-plus mr-2"></i> Add to Calendar
                </button>
            </div>
        </div>
    </div>

    <!-- Main Schedule Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Post & Shift Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Post Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                    Post Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Post Name</div>
                            <div class="text-lg font-semibold" style="color: var(--text-primary);">{{ $schedule->post->name }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Post Code</div>
                            <div class="font-mono" style="color: var(--text-primary);">{{ $schedule->post->code }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Post Type</div>
                            <span class="px-3 py-1 text-xs rounded-full badge-info">{{ ucfirst($schedule->post->type) }}</span>
                        </div>
                    </div>
                    
                    <div>
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Location</div>
                            <div class="flex items-start">
                                <i class="fas fa-map-marker-alt mt-1 mr-2" style="color: var(--primary);"></i>
                                <span style="color: var(--text-primary);">{{ $schedule->post->location }}</span>
                            </div>
                        </div>
                        
                        @if($schedule->post->contact_number)
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Contact Number</div>
                            <div class="flex items-center">
                                <i class="fas fa-phone-alt mr-2" style="color: var(--success);"></i>
                                <span style="color: var(--text-primary);">{{ $schedule->post->contact_number }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                @if($schedule->post->description)
                <div class="mt-4 p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="text-sm mb-2" style="color: var(--text-secondary);">Description</div>
                    <p style="color: var(--text-primary);">{{ $schedule->post->description }}</p>
                </div>
                @endif
            </div>

            <!-- Shift Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                    Shift Details
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Shift Name</div>
                            <div class="text-lg font-semibold" style="color: var(--text-primary);">{{ $schedule->shift->name }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Time</div>
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center">
                                    <i class="fas fa-sun mr-2" style="color: var(--warning);"></i>
                                    <span style="color: var(--text-primary);">{{ substr($schedule->shift->start_time, 0, 5) }}</span>
                                </div>
                                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
                                <div class="flex items-center">
                                    <i class="fas fa-moon mr-2" style="color: var(--info);"></i>
                                    <span style="color: var(--text-primary);">{{ substr($schedule->shift->end_time, 0, 5) }}</span>
                                </div>
                            </div>
                        </div>
                        
                        @if($schedule->shift->is_overnight)
                        <div class="mb-4">
                            <span class="px-3 py-1 text-xs rounded-full badge-warning">
                                <i class="fas fa-moon mr-1"></i> Overnight Shift
                            </span>
                        </div>
                        @endif
                    </div>
                    
                    <div>
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Duration</div>
                            <div class="flex items-center">
                                <i class="fas fa-hourglass-half mr-2" style="color: var(--primary);"></i>
                                <span style="color: var(--text-primary);">{{ $schedule->shift->duration_hours }} hours</span>
                            </div>
                        </div>
                        
                        @if($schedule->shift->break_schedule && $schedule->shift->break_schedule['has_break'])
                        <div class="mb-4">
                            <div class="text-sm mb-1" style="color: var(--text-secondary);">Break Schedule</div>
                            @foreach($schedule->shift->break_schedule['breaks'] as $break)
                            <div class="flex items-center text-sm mt-1">
                                <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                                <span style="color: var(--text-primary);">{{ $break['name'] }} ({{ $break['duration'] }} min)</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                
                @if($schedule->special_instructions)
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid var(--border-color);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                        <div>
                            <div class="text-sm font-medium mb-1" style="color: var(--warning);">Special Instructions</div>
                            <p style="color: var(--text-primary);">{{ $schedule->special_instructions }}</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Rotation Information (if applicable) -->
            @if($schedule->rotationGroup)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--secondary);"></i>
                    Rotation Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="text-sm" style="color: var(--text-secondary);">Rotation Group</div>
                                <div class="font-semibold" style="color: var(--text-primary);">{{ $schedule->rotationGroup->name }}</div>
                            </div>
                        </div>
                        
                        <a href="{{ route('security.rotation-group', $schedule->rotationGroup->id) }}" 
                           class="inline-flex items-center text-sm mt-2" style="color: var(--primary);">
                            View Group Details <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    
                    @if($schedule->rotatedFromUser)
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div>
                                <div class="text-sm" style="color: var(--text-secondary);">Rotated From</div>
                                <div class="font-semibold" style="color: var(--text-primary);">{{ $schedule->rotatedFromUser->name }}</div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column - Status & Timeline -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Status Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-thermometer-half mr-2" style="color: var(--primary);"></i>
                    Status
                </h3>
                
                <div class="text-center mb-6">
                    @php
                        $statusColors = [
                            'scheduled' => 'info',
                            'active' => 'success',
                            'completed' => 'secondary',
                            'absent' => 'danger',
                            'cancelled' => 'danger'
                        ];
                        $statusColor = $statusColors[$schedule->status] ?? 'secondary';
                        $statusIcons = [
                            'scheduled' => 'fa-calendar',
                            'active' => 'fa-play-circle',
                            'completed' => 'fa-check-circle',
                            'absent' => 'fa-times-circle',
                            'cancelled' => 'fa-ban'
                        ];
                    @endphp
                    
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-3"
                         style="background-color: rgba(var({{ $statusColor }}-rgb), 0.1);">
                        <i class="fas {{ $statusIcons[$schedule->status] ?? 'fa-question' }} text-3xl" style="color: var(--{{ $statusColor }});"></i>
                    </div>
                    
                    <h4 class="text-xl font-semibold mb-1" style="color: var(--text-primary);">{{ ucfirst($schedule->status) }}</h4>
                    
                    @if($schedule->status === 'scheduled' && $schedule->assignment_date->isToday())
                        <span class="text-sm" style="color: var(--warning);">Ready for check-in</span>
                    @elseif($schedule->status === 'active')
                        <span class="text-sm" style="color: var(--success);">Shift in progress</span>
                    @endif
                </div>
                
                @if($schedule->late_minutes > 0)
                <div class="p-3 rounded-lg mb-3" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--text-primary);">Late by {{ $schedule->late_minutes }} minutes</span>
                    </div>
                </div>
                @endif
                
                @if($schedule->overtime_minutes > 0)
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2" style="color: var(--success);"></i>
                        <span style="color: var(--text-primary);">Overtime: {{ $schedule->overtime_minutes }} minutes</span>
                    </div>
                </div>
                @endif
            </div>

            <!-- Timeline Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--info);"></i>
                    Timeline
                </h3>
                
                <div class="space-y-4">
                    <!-- Scheduled Time -->
                    <div class="flex items-start">
                        <div class="relative">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            @if($schedule->checkin_time || $schedule->checkout_time)
                            <div class="absolute top-8 left-4 w-0.5 h-12" style="background-color: var(--border-color);"></div>
                            @endif
                        </div>
                        <div class="ml-3 flex-1">
                            <div class="flex justify-between">
                                <span class="font-medium" style="color: var(--text-primary);">Scheduled</span>
                                <span style="color: var(--text-secondary);">{{ $schedule->shift->start_time }} - {{ $schedule->shift->end_time }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Check-in Time -->
                    @if($schedule->checkin_time)
                    <div class="flex items-start">
                        <div class="relative">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            @if($schedule->checkout_time)
                            <div class="absolute top-8 left-4 w-0.5 h-12" style="background-color: var(--border-color);"></div>
                            @endif
                        </div>
                        <div class="ml-3 flex-1">
                            <div class="flex justify-between">
                                <span class="font-medium" style="color: var(--text-primary);">Checked In</span>
                                <span style="color: var(--text-secondary);">{{ $schedule->checkin_time->format('H:i') }}</span>
                            </div>
                            @if($schedule->checkin_notes)
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">{{ $schedule->checkin_notes }}</div>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    <!-- Check-out Time -->
                    @if($schedule->checkout_time)
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <div class="flex justify-between">
                                <span class="font-medium" style="color: var(--text-primary);">Checked Out</span>
                                <span style="color: var(--text-secondary);">{{ $schedule->checkout_time->format('H:i') }}</span>
                            </div>
                            @if($schedule->checkout_notes)
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">{{ $schedule->checkout_notes }}</div>
                            @endif
                            @if($schedule->total_minutes)
                            <div class="text-sm mt-2 font-medium" style="color: var(--success);">
                                Total Duration: {{ round($schedule->total_minutes / 60, 1) }} hours
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Handover Information (if applicable) -->
            @if($schedule->handover_info)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exchange-alt mr-2" style="color: var(--warning);"></i>
                    Handover Information
                </h3>
                
                @if($schedule->handover_completed)
                <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                        <span style="color: var(--text-primary);">Handover completed</span>
                    </div>
                    <div class="text-sm mt-2" style="color: var(--text-secondary);">
                        Completed by: {{ $schedule->handover_completed_by_name ?? 'Unknown' }}<br>
                        at: {{ $schedule->handover_completed_at ? \Carbon\Carbon::parse($schedule->handover_completed_at)->format('H:i') : 'N/A' }}
                    </div>
                </div>
                @else
                <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--text-primary);">Handover pending</span>
                    </div>
                </div>
                @endif
                
                @if($schedule->previousSchedule)
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="text-sm mb-2" style="color: var(--text-secondary);">Previous Shift</div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->previousSchedule->securityUser->name ?? 'Unknown' }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->previousSchedule->shift->getTimeRange() ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <!-- Approval Information -->
            @if($schedule->approvedBy)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                    Approval Information
                </h3>
                
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->approvedBy->name }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Approved at: {{ $schedule->approved_at ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<style>
/* Reuse styles from my-schedules */
.action-btn {
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
</style>

<script>
function addToCalendar(scheduleId) {
    window.location.href = `/security/schedules/${scheduleId}/calendar`;
}
</script>
@endsection