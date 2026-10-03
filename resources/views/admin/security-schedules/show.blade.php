@extends('layouts.app')

@section('title', 'Security Schedule Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> 
                        Schedule Details #{{ $schedule->id }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-2"></i>
                        <span>View and manage security schedule information</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <!-- Status Badge -->
                @php
                    $statusColors = [
                        'scheduled' => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)', 'icon' => 'fa-clock'],
                        'active' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-play-circle'],
                        'completed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-check-circle'],
                        'absent' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'icon' => 'fa-user-slash'],
                        'cancelled' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-ban'],
                    ];
                    $statusInfo = $statusColors[$schedule->status] ?? $statusColors['scheduled'];
                @endphp
                <span class="px-4 py-2 rounded-full text-sm font-medium inline-flex items-center"
                      style="background-color: {{ $statusInfo['bg'] }}; color: {{ $statusInfo['text'] }};">
                    <i class="fas {{ $statusInfo['icon'] }} mr-2"></i>
                    {{ ucfirst($schedule->status) }}
                </span>
                
                <a href="{{ route('admin.security-schedules.edit', $schedule->id) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Main Dashboard
                </a>
                
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-map-marker-alt mr-1"></i> Security Posts
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> All Schedules
                </a>
                
                <a href="{{ route('admin.users.index') }}?type=6" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-users mr-1"></i> Personnel
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> Created: {{ $schedule->created_at->format('M j, Y H:i') }}
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Security Post -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Security Post</div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">{{ $schedule->post->name ?? 'N/A' }}</div>
                    @if($schedule->post->code ?? false)
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Code: {{ $schedule->post->code }}</div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Shift -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Shift</div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">{{ $schedule->shift->name ?? 'N/A' }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $schedule->shift ? substr($schedule->shift->start_time, 0, 5) . ' - ' . substr($schedule->shift->end_time, 0, 5) : 'N/A' }}
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Assignment Date -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Assignment Date</div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $schedule->assignment_date->format('l') }}</div>
                </div>
            </div>
        </div>
        
        <!-- Personnel -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Personnel</div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">{{ $schedule->securityUser->name ?? 'N/A' }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $schedule->securityUser->phone ?? 'No phone' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Main Details (2/3 width) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Assignment Details Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            Assignment Details
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            ID: #{{ $schedule->id }}
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Post Information -->
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i>
                                Security Post Details
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Name</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->name ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Code</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->code ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Type</span>
                                    <span class="px-2 py-1 rounded-full text-xs"
                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        {{ ucfirst(str_replace('_', ' ', $schedule->post->type ?? 'standard')) }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Location</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->post->location ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span style="color: var(--text-secondary);">Status</span>
                                    <span class="px-2 py-1 rounded-full text-xs"
                                          style="background-color: {{ $schedule->post->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; 
                                                 color: {{ $schedule->post->is_active ? 'var(--success)' : 'var(--warning)' }};">
                                        {{ $schedule->post->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shift Information -->
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-clock mr-1" style="color: var(--success);"></i>
                                Shift Details
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Name</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->shift->name ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Time</span>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->shift ? substr($schedule->shift->start_time, 0, 5) . ' - ' . substr($schedule->shift->end_time, 0, 5) : 'N/A' }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Duration</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $schedule->shift->duration_hours ?? 0 }} hours</span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Category</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ ucfirst($schedule->shift->category ?? 'standard') }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span style="color: var(--text-secondary);">Overnight</span>
                                    <span class="px-2 py-1 rounded-full text-xs"
                                          style="background-color: {{ ($schedule->shift->is_overnight ?? false) ? 'rgba(var(--warning-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)' }}; 
                                                 color: {{ ($schedule->shift->is_overnight ?? false) ? 'var(--warning)' : 'var(--success)' }};">
                                        {{ ($schedule->shift->is_overnight ?? false) ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shift Timeline -->
                    @if($schedule->shift)
                    @php
                        $startTime = $schedule->shift->start_time ?? '00:00';
                        $endTime = $schedule->shift->end_time ?? '00:00';
                        $checkinTime = $schedule->checkin_time ? \Carbon\Carbon::parse($schedule->checkin_time)->format('H:i') : null;
                        $checkoutTime = $schedule->checkout_time ? \Carbon\Carbon::parse($schedule->checkout_time)->format('H:i') : null;
                        
                        // Calculate progress percentage
                        $progress = 0;
                        if ($schedule->status === 'active' && $checkinTime) {
                            $now = \Carbon\Carbon::now();
                            $shiftStart = \Carbon\Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $startTime);
                            $shiftEnd = \Carbon\Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $endTime);
                            
                            if ($shiftEnd <= $shiftStart) {
                                $shiftEnd->addDay();
                            }
                            
                            if ($now->between($shiftStart, $shiftEnd)) {
                                $totalMinutes = $shiftStart->diffInMinutes($shiftEnd);
                                $elapsedMinutes = $shiftStart->diffInMinutes($now);
                                $progress = ($elapsedMinutes / $totalMinutes) * 100;
                            } elseif ($now->greaterThan($shiftEnd)) {
                                $progress = 100;
                            }
                        } elseif ($schedule->status === 'completed') {
                            $progress = 100;
                        }
                    @endphp
                    
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-medium mb-4" style="color: var(--text-primary);">
                            <i class="fas fa-chart-line mr-1" style="color: var(--info);"></i>
                            Shift Timeline
                        </h4>
                        
                        <div class="relative pt-6 pb-2">
                            <div class="flex items-center justify-between text-xs mb-2 px-1">
                                <span style="color: var(--text-secondary);">{{ substr($startTime, 0, 5) }}</span>
                                @if($checkinTime)
                                <span class="px-2 py-1 rounded-full text-xs" 
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Check-in: {{ $checkinTime }}
                                </span>
                                @endif
                                @if($checkoutTime)
                                <span class="px-2 py-1 rounded-full text-xs" 
                                      style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                    <i class="fas fa-sign-out-alt mr-1"></i> Check-out: {{ $checkoutTime }}
                                </span>
                                @endif
                                <span style="color: var(--text-secondary);">{{ substr($endTime, 0, 5) }}</span>
                            </div>
                            <div class="w-full h-3 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <div class="h-3 rounded-full transition-all duration-500" 
                                     style="width: {{ $progress }}%; 
                                            background: linear-gradient(90deg, var(--success) 0%, var(--info) 100%);">
                                </div>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-xxs" style="color: var(--text-secondary);">Shift Start</span>
                                @if($schedule->handover_info)
                                <span class="text-xxs" style="color: var(--primary);">
                                    <i class="fas fa-handshake mr-1"></i>Handover
                                </span>
                                @endif
                                <span class="text-xxs" style="color: var(--text-secondary);">Shift End</span>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Personnel Details Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--warning);"></i>
                        Security Personnel
                    </h3>
                </div>
                <div class="p-6">
                    @if($schedule->securityUser)
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Avatar -->
                        <div class="flex-shrink-0">
                            <div class="w-24 h-24 rounded-full flex items-center justify-center"
                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);">
                                <span class="text-3xl font-bold text-white">
                                    {{ strtoupper(substr($schedule->securityUser->name, 0, 1)) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-grow">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm" style="color: var(--text-secondary);">Full Name</div>
                                    <div class="font-medium mt-1" style="color: var(--text-primary);">{{ $schedule->securityUser->name }}</div>
                                </div>
                                <div>
                                    <div class="text-sm" style="color: var(--text-secondary);">Phone Number</div>
                                    <div class="font-medium mt-1" style="color: var(--text-primary);">
                                        <a href="tel:{{ $schedule->securityUser->phone }}" class="hover:underline">
                                            {{ $schedule->securityUser->phone }}
                                        </a>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm" style="color: var(--text-secondary);">Email</div>
                                    <div class="font-medium mt-1" style="color: var(--text-primary);">
                                        <a href="mailto:{{ $schedule->securityUser->email }}" class="hover:underline">
                                            {{ $schedule->securityUser->email ?? 'N/A' }}
                                        </a>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm" style="color: var(--text-secondary);">Badge Number</div>
                                    <div class="font-medium mt-1" style="color: var(--text-primary);">
                                        {{ $schedule->securityUser->badge_number ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>

                            @if($schedule->checkin_time || $schedule->checkout_time || $schedule->late_minutes > 0 || $schedule->overtime_minutes > 0)
                            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    @if($schedule->checkin_time)
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Check-in Time</div>
                                        <div class="text-sm font-medium mt-1" style="color: var(--success);">
                                            <i class="fas fa-sign-in-alt mr-1"></i>
                                            {{ \Carbon\Carbon::parse($schedule->checkin_time)->format('H:i') }}
                                        </div>
                                        <div class="text-xxs mt-1" style="color: var(--text-secondary);">
                                            {{ \Carbon\Carbon::parse($schedule->checkin_time)->format('M d, Y') }}
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if($schedule->checkout_time)
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Check-out Time</div>
                                        <div class="text-sm font-medium mt-1" style="color: var(--danger);">
                                            <i class="fas fa-sign-out-alt mr-1"></i>
                                            {{ \Carbon\Carbon::parse($schedule->checkout_time)->format('H:i') }}
                                        </div>
                                        <div class="text-xxs mt-1" style="color: var(--text-secondary);">
                                            {{ \Carbon\Carbon::parse($schedule->checkout_time)->format('M d, Y') }}
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if($schedule->late_minutes > 0)
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Late By</div>
                                        <div class="text-sm font-medium mt-1" style="color: var(--warning);">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ $schedule->late_minutes }} minutes
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if($schedule->overtime_minutes > 0)
                                    <div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Overtime</div>
                                        <div class="text-sm font-medium mt-1" style="color: var(--info);">
                                            <i class="fas fa-hourglass-end mr-1"></i>
                                            {{ $schedule->overtime_minutes }} minutes
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                        </div>
                        <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Personnel Assigned</p>
                        <p style="color: var(--text-secondary);">This schedule has no security personnel assigned.</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Handover Information Card -->
            @if($schedule->handover_info)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-handshake mr-2" style="color: var(--primary);"></i>
                        Handover Information
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Handover Start</div>
                            <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                                {{ $schedule->handover_info['handover_start'] ?? '--:--' }}
                            </div>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Handover End</div>
                            <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                                {{ $schedule->handover_info['handover_end'] ?? '--:--' }}
                            </div>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Duration</div>
                            <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                                {{ $schedule->handover_info['handover_duration'] ?? 30 }} min
                            </div>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-xs" style="color: var(--text-secondary);">Status</div>
                            <div class="text-lg font-semibold mt-1">
                                <span class="px-2 py-1 rounded-full text-xs"
                                      style="background-color: {{ ($schedule->handover_info['completed'] ?? false) ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }};
                                             color: {{ ($schedule->handover_info['completed'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                                    {{ ($schedule->handover_info['completed'] ?? false) ? 'Completed' : 'Pending' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    @if(!empty($schedule->handover_info['notes']))
                    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="text-xs" style="color: var(--text-secondary);">Handover Notes</div>
                        <div class="text-sm mt-2" style="color: var(--text-primary);">
                            {{ $schedule->handover_info['notes'] }}
                        </div>
                        @if(!empty($schedule->handover_info['notes_submitted_at']))
                        <div class="text-xxs mt-3" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i>
                            Submitted by {{ $schedule->handover_info['notes_submitted_by'] ?? 'Unknown' }} 
                            at {{ \Carbon\Carbon::parse($schedule->handover_info['notes_submitted_at'])->format('M d, Y H:i') }}
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Break History Card -->
            @if($schedule->include_breaks && $schedule->break_history)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-coffee mr-2" style="color: var(--warning);"></i>
                            Break History
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            {{ count($schedule->break_history) }} Breaks
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($schedule->break_history as $index => $break)
                        <div class="flex items-center justify-between p-3 rounded-lg"
                             style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                                    <i class="fas fa-coffee" style="color: var(--warning);"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        Break #{{ $index + 1 }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ \Carbon\Carbon::parse($break['start_time'])->format('H:i') }} - 
                                        {{ \Carbon\Carbon::parse($break['end_time'])->format('H:i') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $break['duration'] }} min
                                </span>
                                @if(!empty($break['notes']))
                                <div class="text-xxs" style="color: var(--text-secondary);">{{ $break['notes'] }}</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column - Additional Info (1/3 width) -->
        <div class="space-y-6">
            <!-- Emergency Contact Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-phone-alt mr-2" style="color: var(--danger);"></i>
                        Emergency Contact
                    </h3>
                </div>
                <div class="p-6">
                    @if($schedule->emergency_contact)
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                        <div class="text-sm" style="color: var(--text-secondary);">Contact Information</div>
                        <div class="text-lg font-semibold mt-2" style="color: var(--text-primary);">
                            {{ $schedule->emergency_contact }}
                        </div>
                        <div class="text-xs mt-3" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            For emergencies during this shift
                        </div>
                    </div>
                    @else
                    <div class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-phone-alt" style="color: var(--text-secondary);"></i>
                        </div>
                        <p style="color: var(--text-secondary);">No emergency contact provided</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Special Instructions Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        Special Instructions
                    </h3>
                </div>
                <div class="p-6">
                    @if($schedule->special_instructions)
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <p class="text-sm whitespace-pre-wrap" style="color: var(--text-primary);">
                            {{ $schedule->special_instructions }}
                        </p>
                    </div>
                    @else
                    <div class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        </div>
                        <p style="color: var(--text-secondary);">No special instructions</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Notes Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-2" style="color: var(--info);"></i>
                        Additional Notes
                    </h3>
                </div>
                <div class="p-6">
                    @if($schedule->notes)
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ $schedule->notes }}
                        </p>
                        <div class="text-xs mt-3" style="color: var(--text-secondary);">
                            <i class="fas fa-user mr-1"></i>
                            Internal note - not visible to personnel
                        </div>
                    </div>
                    @else
                    <div class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-sticky-note" style="color: var(--text-secondary);"></i>
                        </div>
                        <p style="color: var(--text-secondary);">No additional notes</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Rotation Information Card -->
            @if($schedule->rotation_data)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i>
                        Rotation Information
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Sequence Type</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ ucfirst(str_replace('_', ' ', $schedule->rotation_data['sequence_type'] ?? 'Standard')) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Current Position</span>
                            <span class="px-2 py-1 rounded-full text-xs"
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                {{ ucfirst($schedule->rotation_data['current_position'] ?? 'N/A') }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Next Position</span>
                            <span class="px-2 py-1 rounded-full text-xs"
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                {{ ucfirst($schedule->rotation_data['next_position'] ?? 'N/A') }}
                            </span>
                        </div>
                        @if($schedule->rotation_data['next_rotation_date'] ?? false)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Next Rotation</span>
                            <span style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($schedule->rotation_data['next_rotation_date'])->format('M d, Y') }}
                            </span>
                        </div>
                        @endif
                        <div class="flex justify-between py-2">
                            <span style="color: var(--text-secondary);">Rotation Days</span>
                            <span style="color: var(--text-primary);">
                                {{ $schedule->rotation_data['rotation_days'] ?? 7 }} days
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Metadata Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--secondary);"></i>
                        Metadata
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Created At</span>
                            <span style="color: var(--text-primary);">{{ $schedule->created_at->format('M d, Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Created By</span>
                            <span style="color: var(--text-primary);">{{ $schedule->assignedBy->name ?? 'System' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">Last Updated</span>
                            <span style="color: var(--text-primary);">{{ $schedule->updated_at->format('M d, Y H:i') }}</span>
                        </div>
                        @if($schedule->status === 'completed' && $schedule->checkout_time)
                        <div class="flex justify-between py-2">
                            <span style="color: var(--text-secondary);">Completed At</span>
                            <span style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($schedule->checkout_time)->format('M d, Y H:i') }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Buttons Card -->
            <div class="card">
                <div class="p-6">
                    <div class="space-y-3">
                        <a href="{{ route('admin.security-schedules.edit', $schedule->id) }}" 
                           class="btn-primary w-full flex items-center justify-center py-3">
                            <i class="fas fa-edit mr-2"></i> Edit Schedule
                        </a>
                        
                        @if($schedule->status === 'scheduled')
                        <form action="{{ route('admin.security-schedules.update-status', $schedule->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="mark_absent">
                            <button type="submit" 
                                    class="w-full px-4 py-3 rounded-lg font-medium inline-flex items-center justify-center"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                                    onclick="return confirm('Mark this security personnel as absent?')">
                                <i class="fas fa-user-slash mr-2"></i> Mark as Absent
                            </button>
                        </form>
                        @endif
                        
                        @if($schedule->status !== 'cancelled' && $schedule->status !== 'completed')
                        <button onclick="showDeleteModal({{ $schedule->id }})"
                                class="w-full px-4 py-3 rounded-lg font-medium inline-flex items-center justify-center"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash-alt mr-2"></i> Delete Schedule
                        </button>
                        @endif
                        
                        <a href="{{ route('admin.security-schedules.index') }}" 
                           class="w-full px-4 py-3 rounded-lg font-medium inline-flex items-center justify-center"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Schedule</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Are you sure?</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This will move the schedule to trash. You can restore it from there if needed.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('deleteModal')">
                Cancel
            </button>
            <form id="deleteForm" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); border: none;">
                    <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// Show delete modal
function showDeleteModal(scheduleId) {
    const deleteForm = document.getElementById('deleteForm');
    deleteForm.action = `{{ url('admin/security-schedules') }}/${scheduleId}`;
    openModal('deleteModal');
}

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Toast notification
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    toast.innerHTML = `
        <span class="text-sm font-medium flex-1">${message}</span>
        <button class="ml-4 transition-colors duration-200" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Handle form submissions with AJAX (optional)
document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        
        try {
            const response = await fetch(this.action, {
                method: this.method,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: new FormData(this)
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1500);
                } else {
                    setTimeout(() => window.location.reload(), 1500);
                }
            } else {
                showToast(data.message || 'An error occurred', 'error');
            }
        } catch (error) {
            showToast('Network error occurred', 'error');
            console.error('Error:', error);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});
</script>

<style>
/* Text extra small utility */
.text-xxs {
    font-size: 0.65rem;
    line-height: 1rem;
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Status badge animations */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

[style*="status: 'active'"] .fa-play-circle {
    animation: pulse 2s infinite;
}

/* Progress bar animation */
#shift-progress {
    transition: width 0.5s ease-in-out;
}

/* Modal styles */
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
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem;
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
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
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
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Hidden class */
.hidden {
    display: none !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
    }
    
    .w-24.h-24 {
        margin-bottom: 1rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
}

/* Dark mode adjustments */
[data-theme="dark"] .card {
    background-color: var(--card-bg);
    border-color: var(--border-color);
}

[data-theme="dark"] .modal-container {
    background-color: #1f2937;
    border-color: #374151;
}

[data-theme="dark"] .modal-footer {
    background-color: rgba(55, 65, 81, 0.5);
}
</style>
@endsection