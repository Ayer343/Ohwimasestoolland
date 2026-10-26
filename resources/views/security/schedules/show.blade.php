@extends('layouts.secu')

@section('title', $securityPost->name ?? 'Security Schedule Details')

@php
    // ============================================================
    // MODE RESOLUTION
    // - Supervisors (Head of Security, admins): admin-parity view
    // - Ordinary security personnel: the original guard-facing view
    // ============================================================
    $isSupervisorMode = isset($canManage, $isSupervisor) && $canManage && $isSupervisor;

    $statusColors = [
        'scheduled' => ['bg' => 'rgba(var(--info-rgb), 0.1)',    'text' => 'var(--info)',    'icon' => 'fa-clock'],
        'active'    => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-play-circle'],
        'completed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-check-circle'],
        'absent'    => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)',  'icon' => 'fa-user-slash'],
        'cancelled' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-ban'],
    ];
    $statusInfo = $statusColors[$schedule->status] ?? $statusColors['scheduled'];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================ --}}
    {{-- HEADER (shared, action buttons conditional)                  --}}
    {{-- ============================================================ --}}
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-center">
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
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        <span>{{ $schedule->assignment_date->format('l, F j, Y') }}</span>
                        <span>•</span>
                        <i class="fas fa-user-shield mr-1"></i>
                        <span>{{ $isSupervisorMode ? 'Supervisor' : 'Personnel' }}: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="px-4 py-2 rounded-full text-sm font-medium inline-flex items-center"
                      style="background-color: {{ $statusInfo['bg'] }}; color: {{ $statusInfo['text'] }};">
                    <i class="fas {{ $statusInfo['icon'] }} mr-2"></i>
                    {{ ucfirst($schedule->status) }}
                </span>

                @if($isSupervisorMode)
                    <a href="{{ route('security.schedules.edit', $schedule->id) }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>
                    <a href="{{ route('security.schedules.index') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                @else
                    <button type="button" onclick="addToCalendar({{ $schedule->id }})"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-calendar-plus mr-2"></i> Add to Calendar
                    </button>
                    <a href="{{ route('security.schedules.my') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-2"></i> My Schedules
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($isSupervisorMode)
        {{-- ======================================================== --}}
        {{-- SUPERVISOR MODE — full admin-parity dashboard             --}}
        {{-- ======================================================== --}}

        <!-- Navigation Card -->
        <div class="card p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center flex-wrap gap-2">
                    <a href="{{ route('security.schedules.index') }}"
                       class="inline-flex items-center text-sm font-medium"
                       style="color: var(--primary);">
                        <i class="fas fa-arrow-left mr-2"></i> All Schedules
                    </a>
                    <a href="{{ route('security.posts.index') }}"
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-map-marker-alt mr-1"></i> Security Posts
                    </a>
                    @if($schedule->post)
                        <a href="{{ route('security.posts.show', $schedule->post->id) }}"
                           class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-building mr-1"></i> This Post
                        </a>
                    @endif
                    <a href="{{ route('security.schedules.calendar') }}"
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-calendar-alt mr-1"></i> Calendar
                    </a>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> Created: {{ $schedule->created_at->format('M j, Y H:i') }}
                </div>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

        <!-- Main grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Assignment Details -->
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
                            <div>
                                <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                                    <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i> Security Post Details
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
                            <div>
                                <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                                    <i class="fas fa-clock mr-1" style="color: var(--success);"></i> Shift Details
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

                        @if($schedule->shift)
                            @php
                                $startTime = $schedule->shift->start_time ?? '00:00';
                                $endTime = $schedule->shift->end_time ?? '00:00';
                                $checkinTime = $schedule->checkin_time ? \Carbon\Carbon::parse($schedule->checkin_time)->format('H:i') : null;
                                $checkoutTime = $schedule->checkout_time ? \Carbon\Carbon::parse($schedule->checkout_time)->format('H:i') : null;
                                $progress = 0;
                                if ($schedule->status === 'active' && $checkinTime) {
                                    $now = \Carbon\Carbon::now();
                                    $shiftStart = \Carbon\Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $startTime);
                                    $shiftEnd = \Carbon\Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $endTime);
                                    if ($shiftEnd <= $shiftStart) $shiftEnd->addDay();
                                    if ($now->between($shiftStart, $shiftEnd)) {
                                        $totalMinutes = $shiftStart->diffInMinutes($shiftEnd);
                                        $elapsedMinutes = $shiftStart->diffInMinutes($now);
                                        $progress = $totalMinutes > 0 ? ($elapsedMinutes / $totalMinutes) * 100 : 0;
                                    } elseif ($now->greaterThan($shiftEnd)) {
                                        $progress = 100;
                                    }
                                } elseif ($schedule->status === 'completed') {
                                    $progress = 100;
                                }
                            @endphp
                            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="text-sm font-medium mb-4" style="color: var(--text-primary);">
                                    <i class="fas fa-chart-line mr-1" style="color: var(--info);"></i> Shift Timeline
                                </h4>
                                <div class="relative pt-6 pb-2">
                                    <div class="flex items-center justify-between text-xs mb-2 px-1 flex-wrap gap-2">
                                        <span style="color: var(--text-secondary);">{{ substr($startTime, 0, 5) }}</span>
                                        @if($checkinTime)
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-sign-in-alt mr-1"></i> Check-in: {{ $checkinTime }}
                                            </span>
                                        @endif
                                        @if($checkoutTime)
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-sign-out-alt mr-1"></i> Check-out: {{ $checkoutTime }}
                                            </span>
                                        @endif
                                        <span style="color: var(--text-secondary);">{{ substr($endTime, 0, 5) }}</span>
                                    </div>
                                    <div class="w-full h-3 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                        <div class="h-3 rounded-full transition-all duration-500 progress-bar"
                                             style="width: {{ $progress }}%;
                                                    background: linear-gradient(90deg, var(--success) 0%, var(--info) 100%);"></div>
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

                <!-- Personnel card -->
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
                                <div class="flex-shrink-0">
                                    <div class="w-24 h-24 rounded-full flex items-center justify-center"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);">
                                        <span class="text-3xl font-bold text-white">
                                            {{ strtoupper(substr($schedule->securityUser->name, 0, 1)) }}
                                        </span>
                                    </div>
                                </div>
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

                <!-- Handover -->
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
                                    <div class="text-sm mt-2" style="color: var(--text-primary);">{{ $schedule->handover_info['notes'] }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Break History -->
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
                                                <div class="text-sm font-medium" style="color: var(--text-primary);">Break #{{ $index + 1 }}</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    {{ \Carbon\Carbon::parse($break['start_time'])->format('H:i') }} -
                                                    {{ \Carbon\Carbon::parse($break['end_time'])->format('H:i') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $break['duration'] }} min</span>
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

            <!-- Right column -->
            <div class="space-y-6">
                <!-- Emergency -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-phone-alt mr-2" style="color: var(--danger);"></i> Emergency Contact
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($schedule->emergency_contact)
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                                <div class="text-sm" style="color: var(--text-secondary);">Contact Information</div>
                                <div class="text-lg font-semibold mt-2" style="color: var(--text-primary);">{{ $schedule->emergency_contact }}</div>
                                <div class="text-xs mt-3" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i> For emergencies during this shift
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

                <!-- Instructions -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i> Special Instructions
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($schedule->special_instructions)
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                                <p class="text-sm whitespace-pre-wrap" style="color: var(--text-primary);">{{ $schedule->special_instructions }}</p>
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

                <!-- Notes -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-sticky-note mr-2" style="color: var(--info);"></i> Additional Notes
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($schedule->notes)
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                <p class="text-sm" style="color: var(--text-primary);">{{ $schedule->notes }}</p>
                                <div class="text-xs mt-3" style="color: var(--text-secondary);">
                                    <i class="fas fa-user mr-1"></i> Internal note - not visible to personnel
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

                <!-- Rotation -->
                @if($schedule->rotation_data)
                    <div class="card">
                        <div class="p-6 border-b" style="border-color: var(--border-color);">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i> Rotation Information
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
                                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        {{ ucfirst($schedule->rotation_data['current_position'] ?? 'N/A') }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Next Position</span>
                                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        {{ ucfirst($schedule->rotation_data['next_position'] ?? 'N/A') }}
                                    </span>
                                </div>
                                @if($schedule->rotation_data['next_rotation_date'] ?? false)
                                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                        <span style="color: var(--text-secondary);">Next Rotation</span>
                                        <span style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($schedule->rotation_data['next_rotation_date'])->format('M d, Y') }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between py-2">
                                    <span style="color: var(--text-secondary);">Rotation Days</span>
                                    <span style="color: var(--text-primary);">{{ $schedule->rotation_data['rotation_days'] ?? 7 }} days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Metadata -->
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--secondary);"></i> Metadata
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

                <!-- Actions -->
                <div class="card">
                    <div class="p-6">
                        <div class="space-y-3">
                            <a href="{{ route('security.schedules.edit', $schedule->id) }}"
                               class="btn-primary w-full flex items-center justify-center py-3">
                                <i class="fas fa-edit mr-2"></i> Edit Schedule
                            </a>

                            @if($schedule->status === 'scheduled')
                                <form action="{{ route('security.schedules.update-status', $schedule->id) }}" method="POST">
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
                                <button type="button" onclick="showDeleteModal({{ $schedule->id }})"
                                        class="w-full px-4 py-3 rounded-lg font-medium inline-flex items-center justify-center"
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                    <i class="fas fa-trash-alt mr-2"></i> Delete Schedule
                                </button>
                            @endif

                            <a href="{{ route('security.schedules.index') }}"
                               class="w-full px-4 py-3 rounded-lg font-medium inline-flex items-center justify-center"
                               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Supervisor-only Delete Modal --}}
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
                    <button type="button" onclick="closeModal('deleteModal')"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
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
    @else
        {{-- ======================================================== --}}
        {{-- GUARD MODE — original read-only guard view               --}}
        {{-- ======================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Post Info -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2" style="color: var(--primary);"></i> Post Information
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

                <!-- Shift Info -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--info);"></i> Shift Details
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

                <!-- Rotation Info -->
                @if($schedule->rotationGroup)
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-sync-alt mr-2" style="color: var(--secondary);"></i> Rotation Information
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

            <div class="lg:col-span-1 space-y-6">
                <!-- Status -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-thermometer-half mr-2" style="color: var(--primary);"></i> Status
                    </h3>
                    <div class="text-center mb-6">
                        @php
                            $statusColorsG = [
                                'scheduled' => 'info',
                                'active' => 'success',
                                'completed' => 'secondary',
                                'absent' => 'danger',
                                'cancelled' => 'danger',
                            ];
                            $statusColorG = $statusColorsG[$schedule->status] ?? 'secondary';
                            $statusIconsG = [
                                'scheduled' => 'fa-calendar',
                                'active' => 'fa-play-circle',
                                'completed' => 'fa-check-circle',
                                'absent' => 'fa-times-circle',
                                'cancelled' => 'fa-ban',
                            ];
                        @endphp
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-3"
                             style="background-color: rgba(var({{ $statusColorG }}-rgb), 0.1);">
                            <i class="fas {{ $statusIconsG[$schedule->status] ?? 'fa-question' }} text-3xl" style="color: var(--{{ $statusColorG }});"></i>
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

                <!-- Timeline -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--info);"></i> Timeline
                    </h3>
                    <div class="space-y-4">
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

                <!-- Handover -->
                @if($schedule->handover_info)
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-exchange-alt mr-2" style="color: var(--warning);"></i> Handover Information
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

                <!-- Approval -->
                @if($schedule->approvedBy)
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approval Information
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
    @endif
</div>

<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
    // Blade-injected values (safe against apostrophes via @json)
    const SCHEDULE_ID = {{ $schedule->id }};
    const DELETE_URL  = "{{ url('security/schedules') }}/" + SCHEDULE_ID;

    // ===========================
    // Delete modal (supervisor only)
    // ===========================
    function showDeleteModal(scheduleId) {
        const form = document.getElementById('deleteForm');
        if (form) form.action = "{{ url('security/schedules') }}/" + scheduleId;
        openModal('deleteModal');
    }
    function openModal(id) {
        const m = document.getElementById(id);
        if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
    }

    // ===========================
    // Add-to-calendar (guard path)
    // ===========================
    function addToCalendar(scheduleId) {
        window.location.href = "{{ url('security/schedules') }}/" + scheduleId + "/calendar";
    }

    // ===========================
    // Toast (used by both)
    // ===========================
    function showToast(message, type = 'info') {
        let c = document.getElementById('toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'toast-container';
            c.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(c);
        }
        const t = document.createElement('div');
        t.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            type === 'error'   ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                                 'bg-blue-100 text-blue-800 border border-blue-200'
        }`;
        t.innerHTML = `<span class="text-sm font-medium flex-1">${message}</span><button class="ml-4" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
        c.appendChild(t);
        setTimeout(() => { t.classList.remove('translate-x-full'); t.classList.add('translate-x-0'); }, 10);
        setTimeout(() => { if (t.parentNode === c) { t.classList.add('translate-x-full'); setTimeout(() => t.remove(), 300); } }, 5000);
    }

    // ===========================
    // Global dismissers
    // ===========================
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            const m = e.target.closest('.modal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
            document.body.style.overflow = 'auto';
        }
    });

    // ===========================
    // Optional AJAX form handlers (data-ajax="true")
    // ===========================
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            try {
                const res = await fetch(this.action, {
                    method: this.method,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: new FormData(this),
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Success', 'success');
                    if (data.redirect) setTimeout(() => window.location.href = data.redirect, 1200);
                    else setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(data.message || 'An error occurred', 'error');
                }
            } catch (err) {
                showToast('Network error occurred', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    });
</script>

<style>
    .text-xxs { font-size: 0.65rem; line-height: 1rem; }
    .card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }
    .progress-bar { transition: width 0.5s ease-in-out; }
    .hidden { display: none !important; }

    /* Badge helpers used by guard branch */
    .badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
    .badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
    .badge-danger  { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger);  border: 1px solid rgba(var(--danger-rgb), 0.3); }
    .badge-info    { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border: 1px solid rgba(var(--info-rgb), 0.3); }
    .badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }

    /* Modal system (supervisor-only) */
    .modal { position: fixed; inset: 0; z-index: 9999; }
    .modal-overlay { position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); }
    .modal-container { position: relative; background-color: var(--card-bg); border-radius: 16px; margin: 2rem auto; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color); animation: modalFadeIn 0.3s ease-out; }
    .modal-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
    .modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 6px; transition: all 0.2s ease; }
    .modal-close:hover { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); }
    .modal-body { padding: 1.5rem; }
    .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background-color: var(--bg-secondary); border-radius: 0 0 16px 16px; }
    @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

    .btn-primary { background-color: var(--primary, #3b82f6); color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-primary:hover { background-color: var(--primary-dark, #2563eb); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .btn-secondary { background-color: var(--bg-tertiary, #f3f4f6); color: var(--text-primary, #1f2937); padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; border: 1px solid var(--border-color, #d1d5db); transition: all 0.2s ease; }
    .btn-secondary:hover { background-color: var(--bg-secondary, #e5e7eb); text-decoration: none; }

    @media (max-width: 768px) {
        .grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
        .flex.flex-col.md\:flex-row { flex-direction: column; }
        .modal-container { margin: 1rem; }
    }
    [data-theme="dark"] .card { background-color: var(--card-bg); border-color: var(--border-color); }
    [data-theme="dark"] .modal-container { background-color: #1f2937; border-color: #374151; }
    [data-theme="dark"] .modal-footer { background-color: rgba(55, 65, 81, 0.5); }
</style>
@endsection