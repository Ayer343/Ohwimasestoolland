@extends('layouts.secu')

@section('title', 'My Schedules')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-check mr-2" style="color: var(--primary);"></i>
                        My Schedules
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - {{ auth()->user()->badge_number ?? 'No Badge' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>{{ $totalSchedules }} total shifts</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.preferences') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-sliders-h mr-2"></i> Preferences
                </a>
                <a href="{{ route('security.availability') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-clock mr-2"></i> Set Availability
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalSchedules }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Shifts</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-calendar" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $completedCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Completed</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $upcomingCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Upcoming</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $rotationCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Rotations</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-sync-alt" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--secondary);">{{ count($rotationGroups) }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">My Groups</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--secondary);"></i>
                </div>
            </div>
        </div>

        <!-- Attendance Rate Card -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">
                        @php
                            $total = $totalSchedules ?? 0;
                            $attendanceRate = $total > 0 ? round(($completedCount / $total) * 100, 1) : 100;
                        @endphp
                        {{ $attendanceRate }}%
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Attendance Rate</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-chart-line" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Schedule with CHECK-IN Button -->
    @if($todaySchedule)
    <div class="card {{ $todaySchedule->checkin_time ? '' : 'ring-2 ring-green-500 ring-opacity-50' }}">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-sun mr-2" style="color: var(--primary);"></i>
                Today's Schedule
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-success">
                    {{ now()->format('l, F j, Y') }}
                </span>
            </h3>
            
            <div class="flex items-center space-x-2">
                @if(!$todaySchedule->checkin_time)
                    <!-- BIG GREEN CHECK-IN BUTTON -->
                    <a href="{{ route('security.schedule.checkin', $todaySchedule->id) }}" 
                       class="px-6 py-3 rounded-lg text-base font-bold text-white inline-flex items-center shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105"
                       style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fas fa-fingerprint mr-3 text-xl"></i>
                        CHECK IN NOW
                        <i class="fas fa-arrow-right ml-3 text-xl"></i>
                    </a>
                @else
                    <span class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center" 
                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle mr-2"></i> Checked in at {{ $todaySchedule->checkin_time->format('H:i') }}
                    </span>
                @endif
                
                <a href="{{ route('security.schedule.show', $todaySchedule->id) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-eye mr-2"></i> Details
                </a>
            </div>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Post Details -->
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center mb-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-2"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-building" style="color: var(--primary);"></i>
                        </div>
                        <span class="font-medium" style="color: var(--text-primary);">Post Information</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Post:</span>
                            <span style="color: var(--text-primary);">{{ $todaySchedule->post->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Location:</span>
                            <span style="color: var(--text-primary);">{{ $todaySchedule->post->location }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Type:</span>
                            <span class="px-2 py-0.5 text-xs rounded-full badge-info">{{ $todaySchedule->post->type }}</span>
                        </div>
                        @if($todaySchedule->post->latitude && $todaySchedule->post->longitude)
                        <div class="flex justify-between text-xs">
                            <span style="color: var(--text-secondary);">Coordinates:</span>
                            <span style="color: var(--text-primary);">{{ substr($todaySchedule->post->latitude, 0, 8) }}°, {{ substr($todaySchedule->post->longitude, 0, 8) }}°</span>
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Shift Time -->
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center mb-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-2"
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-clock" style="color: var(--info);"></i>
                        </div>
                        <span class="font-medium" style="color: var(--text-primary);">Shift Timing</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Shift:</span>
                            <span style="color: var(--text-primary);">{{ $todaySchedule->shift->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Start:</span>
                            <span style="color: var(--text-primary); font-weight: 600;">{{ substr($todaySchedule->shift->start_time, 0, 5) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">End:</span>
                            <span style="color: var(--text-primary);">{{ substr($todaySchedule->shift->end_time, 0, 5) }}</span>
                        </div>
                        
                        <!-- FIXED: Countdown timer for upcoming shift with proper date parsing -->
                        @if(!$todaySchedule->checkin_time)
                            @php
                                try {
                                    $shiftStartTime = $todaySchedule->shift->start_time;
                                    
                                    // Check if start_time contains a full date (contains hyphens)
                                    if (strpos($shiftStartTime, '-') !== false) {
                                        // It's a full datetime, parse it directly
                                        $shiftStart = \Carbon\Carbon::parse($shiftStartTime);
                                    } else {
                                        // It's just a time, combine with assignment date
                                        $shiftStart = \Carbon\Carbon::parse(
                                            $todaySchedule->assignment_date->format('Y-m-d') . ' ' . $shiftStartTime
                                        );
                                    }
                                    
                                    $now = \Carbon\Carbon::now();
                                    $minutesUntil = $now->diffInMinutes($shiftStart, false);
                                } catch (\Exception $e) {
                                    // Fallback if parsing fails
                                    $minutesUntil = null;
                                }
                            @endphp
                            
                            @if(isset($minutesUntil) && $minutesUntil > 0 && $minutesUntil <= 60)
                                <div class="mt-2 p-2 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                    <span class="text-sm font-medium" style="color: var(--warning);">
                                        <i class="fas fa-hourglass-half mr-1"></i> Shift starts in {{ $minutesUntil }} minutes
                                    </span>
                                </div>
                            @elseif(isset($minutesUntil) && $minutesUntil <= 0 && $minutesUntil > -60)
                                <div class="mt-2 p-2 rounded-lg text-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                                    <span class="text-sm font-medium" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> {{ abs($minutesUntil) }} minutes late
                                    </span>
                                </div>
                            @endif
                        @endif
                        
                        @if($todaySchedule->shift->is_overnight)
                            <div class="mt-2 text-xs" style="color: var(--warning);">
                                <i class="fas fa-moon mr-1"></i> Overnight Shift
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Status & Check-in Info -->
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center mb-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-2"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-clipboard-check" style="color: var(--success);"></i>
                        </div>
                        <span class="font-medium" style="color: var(--text-primary);">Status</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Status:</span>
                            @php
                                $statusColors = [
                                    'scheduled' => 'info',
                                    'active' => 'success',
                                    'completed' => 'secondary',
                                    'absent' => 'danger'
                                ];
                                $statusColor = $statusColors[$todaySchedule->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $statusColor }}">
                                {{ ucfirst($todaySchedule->status) }}
                            </span>
                        </div>
                        
                        @if($todaySchedule->checkin_time)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Check-in:</span>
                            <span style="color: var(--text-primary); font-weight: 600;">{{ $todaySchedule->checkin_time->format('H:i') }}</span>
                        </div>
                        
                        @if($todaySchedule->checkin_verification)
                        <div class="flex justify-between text-xs">
                            <span style="color: var(--text-secondary);">Verified via:</span>
                            <span style="color: var(--text-primary);">{{ ucfirst($todaySchedule->checkin_verification['method'] ?? 'GPS') }}</span>
                        </div>
                        @endif
                        @endif
                        
                        @if($todaySchedule->checkout_time)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Check-out:</span>
                            <span style="color: var(--text-primary);">{{ $todaySchedule->checkout_time->format('H:i') }}</span>
                        </div>
                        @endif
                        
                        @if($todaySchedule->late_minutes > 0)
                        <div class="mt-2 text-xs" style="color: var(--warning);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Late by {{ $todaySchedule->late_minutes }} minutes
                        </div>
                        @endif
                        
                        @if($todaySchedule->overtime_minutes > 0)
                        <div class="mt-2 text-xs" style="color: var(--success);">
                            <i class="fas fa-clock mr-1"></i> Overtime: {{ $todaySchedule->overtime_minutes }} minutes
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Check-in instructions if not checked in -->
            @if(!$todaySchedule->checkin_time)
            <div class="mt-4 p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%); border: 1px solid rgba(16, 185, 129, 0.3);">
                <div class="flex items-center">
                    <div class="mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(16, 185, 129, 0.2);">
                            <i class="fas fa-info-circle text-2xl" style="color: #10b981;"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold mb-1" style="color: #10b981;">Ready to check in?</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Click the <strong>CHECK IN NOW</strong> button above. You'll need to:
                        </p>
                        <ul class="text-sm mt-2 space-y-1" style="color: var(--text-secondary);">
                            <li><i class="fas fa-check-circle mr-2 text-xs" style="color: #10b981;"></i>Allow GPS access for location verification</li>
                            @if($todaySchedule->post->checkin_method === 'qr' || !$todaySchedule->post->checkin_method)
                            <li><i class="fas fa-check-circle mr-2 text-xs" style="color: #10b981;"></i>Scan the QR code at the post (if available)</li>
                            @endif
                            <li><i class="fas fa-check-circle mr-2 text-xs" style="color: #10b981;"></i>Take a selfie for face verification (optional)</li>
                        </ul>
                    </div>
                    <div class="ml-4">
                        <a href="{{ route('security.schedule.checkin', $todaySchedule->id) }}" 
                           class="px-6 py-3 rounded-lg text-base font-bold text-white inline-flex items-center"
                           style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="fas fa-arrow-right mr-2"></i> Go
                        </a>
                    </div>
                </div>
            </div>
            @endif
            
            @if($todaySchedule->special_instructions)
            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                <span style="color: var(--text-primary);">{{ $todaySchedule->special_instructions }}</span>
            </div>
            @endif
            
            @if($todaySchedule->handover_info && !$todaySchedule->handover_completed)
            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid var(--border-color);">
                <div class="flex items-center justify-between">
                    <div>
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--text-primary);">Handover required for this shift</span>
                    </div>
                    <a href="{{ route('security.schedule.show', $todaySchedule->id) }}" 
                       class="px-3 py-1 rounded text-sm" style="background-color: var(--warning); color: white;">
                        Complete Handover
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
    @else
    <!-- No schedule today -->
    <div class="card">
        <div class="p-6 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-calendar-day text-3xl" style="color: var(--info);"></i>
            </div>
            <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Schedule Today</h3>
            <p class="text-sm" style="color: var(--text-secondary);">You don't have any shifts scheduled for today.</p>
            <a href="{{ route('security.availability') }}" 
               class="mt-4 px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="background-color: var(--primary); color: white;">
                <i class="fas fa-clock mr-2"></i> Set Your Availability
            </a>
        </div>
    </div>
    @endif

    <!-- Upcoming Schedules -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-calendar-week mr-2" style="color: var(--primary);"></i>
                Upcoming Schedules
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $upcomingSchedules->total() }} upcoming
                </span>
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Time</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Group</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($upcomingSchedules as $schedule)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->assignment_date->format('l') }}</div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->name }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->post->code }}</div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-primary">
                                    {{ $schedule->shift->name }}
                                </span>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}</div>
                                @if($schedule->shift->is_overnight)
                                    <span class="text-xs" style="color: var(--info);">🌙 Overnight</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($schedule->rotationGroup)
                                    <a href="{{ route('security.rotation-group', $schedule->rotationGroup->id) }}" 
                                       class="text-sm hover:underline" style="color: var(--primary);">
                                        {{ $schedule->rotationGroup->name }}
                                    </a>
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @php
                                    $statusColors = [
                                        'scheduled' => 'info',
                                        'confirmed' => 'success',
                                        'pending' => 'warning'
                                    ];
                                    $statusColor = $statusColors[$schedule->status] ?? 'secondary';
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full badge-{{ $statusColor }}">
                                    {{ ucfirst($schedule->status) }}
                                </span>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <a href="{{ route('security.schedule.show', $schedule->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    
                                    <button onclick="addToCalendar({{ $schedule->id }})"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                            title="Add to Calendar">
                                        <i class="fas fa-calendar-plus text-sm"></i>
                                    </button>
                                    
                                    @if($schedule->assignment_date->diffInDays(now()) > 2)
                                    <button onclick="requestSwap({{ $schedule->id }})"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                            title="Request Swap">
                                        <i class="fas fa-exchange-alt text-sm"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center" style="color: var(--text-secondary);">
                                No upcoming schedules found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(method_exists($upcomingSchedules, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $upcomingSchedules->appends(['history_page' => request('history_page')])->links() }}
            </div>
        @endif
    </div>

    <!-- Schedule History -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
                Schedule History
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-secondary">
                    {{ $historySchedules->total() }} past shifts
                </span>
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Check-in</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Check-out</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Duration</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Late/OT</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historySchedules as $schedule)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $schedule->post->name }}</div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-primary">
                                    {{ $schedule->shift->name }}
                                </span>
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($schedule->checkin_time)
                                    <div style="color: var(--text-primary);">{{ $schedule->checkin_time->format('H:i') }}</div>
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($schedule->checkout_time)
                                    <div style="color: var(--text-primary);">{{ $schedule->checkout_time->format('H:i') }}</div>
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($schedule->total_minutes)
                                    {{ round($schedule->total_minutes / 60, 1) }} hrs
                                @else
                                    {{ $schedule->shift->duration_hours }} hrs
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($schedule->late_minutes > 0)
                                    <span class="text-xs" style="color: var(--warning);">
                                        <i class="fas fa-clock mr-1"></i> Late {{ $schedule->late_minutes }}m
                                    </span>
                                @endif
                                @if($schedule->overtime_minutes > 0)
                                    <span class="text-xs" style="color: var(--success);">
                                        <i class="fas fa-clock mr-1"></i> OT {{ $schedule->overtime_minutes }}m
                                    </span>
                                @endif
                                @if($schedule->late_minutes == 0 && $schedule->overtime_minutes == 0)
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <a href="{{ route('security.schedule.show', $schedule->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    
                                    @if($schedule->handover_info && !$schedule->handover_completed)
                                    <a href="{{ route('security.schedule.show', $schedule->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                       title="Complete Handover">
                                        <i class="fas fa-exchange-alt text-sm"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center" style="color: var(--text-secondary);">
                                No schedule history found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(method_exists($historySchedules, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $historySchedules->appends(['upcoming_page' => request('upcoming_page')])->links() }}
            </div>
        @endif
    </div>

    <!-- Performance Summary Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Punctuality Stats -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-stopwatch mr-2" style="color: var(--info);"></i>
                Punctuality Overview
            </h3>
            
            @php
                $totalCompleted = $completedCount ?? 0;
                $lateShifts = $historySchedules->where('late_minutes', '>', 0)->count();
                $onTimeShifts = $totalCompleted - $lateShifts;
                $onTimePercentage = $totalCompleted > 0 ? round(($onTimeShifts / $totalCompleted) * 100, 1) : 100;
                $avgLateMinutes = $lateShifts > 0 ? round($historySchedules->where('late_minutes', '>', 0)->avg('late_minutes'), 1) : 0;
            @endphp
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $onTimePercentage }}%</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">On-Time Rate</div>
                </div>
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $avgLateMinutes }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Avg Late (min)</div>
                </div>
            </div>
            
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">On-time check-ins:</span>
                    <span style="color: var(--text-primary);">{{ $onTimeShifts }} shifts</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">Late check-ins:</span>
                    <span style="color: var(--text-primary);">{{ $lateShifts }} shifts</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">Total late minutes:</span>
                    <span style="color: var(--text-primary);">{{ $historySchedules->sum('late_minutes') }} min</span>
                </div>
            </div>
        </div>
        
        <!-- Overtime Stats -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--success);"></i>
                Overtime Overview
            </h3>
            
            @php
                $overtimeShifts = $historySchedules->where('overtime_minutes', '>', 0)->count();
                $totalOvertimeMinutes = $historySchedules->sum('overtime_minutes');
                $avgOvertime = $overtimeShifts > 0 ? round($totalOvertimeMinutes / $overtimeShifts, 1) : 0;
                $overtimeHours = round($totalOvertimeMinutes / 60, 1);
            @endphp
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $overtimeShifts }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Shifts with OT</div>
                </div>
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $overtimeHours }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Total OT Hours</div>
                </div>
            </div>
            
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">Shifts with overtime:</span>
                    <span style="color: var(--text-primary);">{{ $overtimeShifts }} shifts</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">Average OT per shift:</span>
                    <span style="color: var(--text-primary);">{{ $avgOvertime }} min</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">Total overtime:</span>
                    <span style="color: var(--text-primary);">{{ $overtimeHours }} hours</span>
                </div>
            </div>
        </div>
    </div>

    <!-- My Rotation Groups -->
    @if(count($rotationGroups) > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-users-cog mr-2" style="color: var(--primary);"></i>
                My Rotation Groups
            </h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-6">
            @foreach($rotationGroups as $group)
            <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                            <i class="fas fa-rotate"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">{{ $group->name }}</h4>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ $group->post->name }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Shift:</span>
                        <span style="color: var(--text-primary);">{{ $group->shift->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Type:</span>
                        <span class="px-2 py-0.5 text-xs rounded-full badge-info">{{ ucfirst($group->group_type) }}</span>
                    </div>
                    @if($group->rotation_config['next_rotation_date'] ?? false)
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Next Rotation:</span>
                        <span style="color: var(--primary);">{{ \Carbon\Carbon::parse($group->rotation_config['next_rotation_date'])->format('M j') }}</span>
                    </div>
                    @endif
                </div>
                
                <div class="mt-4">
                    <a href="{{ route('security.rotation-group', $group->id) }}" 
                       class="w-full px-3 py-2 rounded-lg text-sm text-center block"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        View Group Details
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<!-- Break Modal -->
<div id="breakModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('breakModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-coffee mr-2" style="color: var(--info);"></i>
                    Break Management
                </h3>
            </div>
            
            <div class="p-6" id="breakContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
    </div>
</div>

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

/* Badge styles */
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }

/* Modal animations */
.fixed {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Pulse animation for check-in button */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.ring-2 {
    animation: pulse 2s infinite;
}
</style>

<script>
let currentScheduleId = null;

function showBreakModal(scheduleId) {
    currentScheduleId = scheduleId;
    const modal = document.getElementById('breakModal');
    const content = document.getElementById('breakContent');
    
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i></div>';
    modal.classList.remove('hidden');
    
    fetch(`/security/schedules/${scheduleId}/breaks`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayBreakOptions(data);
            } else {
                content.innerHTML = '<div class="text-center py-4" style="color: var(--text-secondary);">No breaks available</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="text-center py-4" style="color: var(--danger);">Failed to load breaks</div>';
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function displayBreakOptions(data) {
    const content = document.getElementById('breakContent');
    let html = '<div class="space-y-3">';
    
    data.breaks.forEach((breakItem, index) => {
        html += `
            <div class="p-3 rounded-lg" style="border: 1px solid var(--border-color);">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">${breakItem.name}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Scheduled: ${breakItem.start_time} - ${breakItem.end_time}</div>
                    </div>
                    <button onclick="takeBreak(${index})"
                            class="px-3 py-1 rounded text-sm" style="background-color: var(--primary); color: white;">
                        Take Break
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
}

function takeBreak(breakId) {
    fetch(`/security/schedules/${currentScheduleId}/start-break`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ break_id: breakId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Break started');
            closeModal('breakModal');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to start break');
        }
    });
}

function addToCalendar(scheduleId) {
    window.location.href = `/security/schedules/${scheduleId}/calendar`;
}

function requestSwap(scheduleId) {
    const reason = prompt('Please provide a reason for requesting a shift swap:');
    if (!reason) return;
    
    fetch(`/security/schedules/${scheduleId}/swap`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Swap request submitted successfully');
        } else {
            alert(data.message || 'Failed to submit swap request');
        }
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('breakModal');
    }
}
</script>
@endsection