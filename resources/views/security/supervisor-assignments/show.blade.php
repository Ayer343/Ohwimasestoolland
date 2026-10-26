@extends('layouts.secu')

@section('title', 'Head of Security Appointment')

@section('content')
@php
    $currentUser = auth()->user();
    $officer     = $assignment->user;
    $officerName = optional($officer)->name ?? 'Deleted User';
    $officerEmail = optional($officer)->email ?? 'No email';
    $officerPhone = optional($officer)->phone ?? 'Not provided';
    $assignedBy  = optional($assignment->assignedBy)->name ?? 'System';

    $startDate = $assignment->start_date;
    $endDate   = $assignment->end_date;

    $isExpired  = $endDate && $endDate->isPast();
    $isActive   = (bool) $assignment->is_active && ! $isExpired;
    $isSoon     = $isActive && $endDate && $endDate->diffInDays(now()) <= 30;
    $daysActive = $startDate && $startDate->isPast()
        ? (int) $startDate->diffInDays(now())
        : 0;
    $daysLeft = $endDate && ! $isExpired
        ? (int) now()->diffInDays($endDate, false)
        : null;

    $isOwnAppointment = $officer && $officer->id === $currentUser->id;
    $canResign        = $isOwnAppointment && $isActive;
@endphp

<div class="grid grid-cols-1 gap-6 mb-6">

    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        Head of Security — Appointment #{{ $assignment->id }}
                    </h2>
                    <div class="text-sm flex items-center flex-wrap mt-1" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>
                        <span>Created {{ $assignment->created_at?->format('M j, Y') ?? 'N/A' }}</span>
                        <span class="mx-2">•</span>

                        @if($isActive)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                <i class="fas fa-crown mr-1"></i> Active Head
                            </span>
                        @elseif($isExpired)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--secondary-rgb), 0.15); color: var(--text-secondary);">
                                <i class="fas fa-hourglass-end mr-1"></i> Expired
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                <i class="fas fa-pause-circle mr-1"></i> Inactive
                            </span>
                        @endif

                        @if($isOwnAppointment)
                            <span class="mx-2">•</span>
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-user-check mr-1"></i> Your appointment
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-4 sm:mt-0">
                @if($canResign)
                    <button type="button" onclick="showResignModal()"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-danger">
                        <i class="fas fa-sign-out-alt mr-2"></i> Resign from post
                    </button>
                @endif

                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- Flash -->
    @if(session('success'))
        <div class="card p-4"
             style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-lg mr-3" style="color: var(--success);"></i>
                <p class="text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="card p-4"
             style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-lg mr-3" style="color: var(--danger);"></i>
                <p class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ==================== LEFT COLUMN ==================== -->
        <div class="lg:col-span-1 space-y-6">

            <!-- Officer Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Officer
                    </h3>
                </div>
                <div class="p-6">
                    @if($officer)
                        <div class="flex items-center mb-4">
                            @if($officer->has_photo)
                                <img src="{{ $officer->photo_url }}" alt="{{ $officerName }}"
                                     class="w-16 h-16 rounded-full object-cover">
                            @else
                                <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl"
                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                    {{ strtoupper(substr($officerName, 0, 1)) }}
                                </div>
                            @endif
                            <div class="ml-4">
                                <h4 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                                    {{ $officerName }}
                                    @if($officer->can_be_supervisor)
                                        <span class="ml-2 px-1.5 py-0.5 text-xs rounded"
                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                              title="Eligible for Head of Security">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    @endif
                                </h4>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $officerEmail }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Eligible:</span>
                                <span class="text-sm font-medium">
                                    @if($officer->can_be_supervisor)
                                        <span class="px-2 py-1 rounded-full text-xs"
                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-check-circle mr-1"></i> Yes
                                        </span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> No
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Phone:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $officerPhone }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-sm" style="color: var(--text-secondary);">Member since:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $officer->created_at?->format('M Y') ?? 'N/A' }}
                                </span>
                            </div>

                            @if(!empty($officer->security_supervisor_certifications))
                                <div class="pt-2">
                                    <p class="text-xs font-semibold uppercase" style="color: var(--text-secondary);">
                                        Certifications
                                    </p>
                                    <p class="text-sm mt-1 whitespace-pre-line" style="color: var(--text-primary);">
                                        {{ $officer->security_supervisor_certifications }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-3xl mb-2" style="color: var(--warning);"></i>
                            <p style="color: var(--text-secondary);">Officer record not available</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">The user may have been deleted.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Appointment Details -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                        Appointment Details
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Title:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $assignment->title_label }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Status:</span>
                        <span class="text-sm font-medium">
                            @if($isActive)
                                <span class="px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-crown mr-1"></i> Active head
                                </span>
                            @elseif($isExpired)
                                <span class="px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                    <i class="fas fa-hourglass-end mr-1"></i> Expired
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-pause-circle mr-1"></i> Inactive
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Appointed by:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $assignedBy }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Created:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $assignment->created_at?->format('M j, Y g:i A') ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Last updated:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $assignment->updated_at?->diffForHumans() ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                        Timeline
                    </h3>
                </div>
                <div class="p-6">
                    <div class="relative">
                        <div class="absolute left-3 top-0 bottom-0 w-0.5"
                             style="background-color: var(--border-color);"></div>

                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: var(--success); color: white;">
                                <i class="fas fa-play text-xs"></i>
                            </div>
                            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Start Date</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                {{ $startDate?->format('l, F j, Y') ?? 'N/A' }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $startDate?->diffForHumans() ?? 'N/A' }}
                            </p>
                        </div>

                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: {{ $isActive ? 'var(--info)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $isActive ? 'hourglass-half' : 'hourglass-end' }} text-xs"></i>
                            </div>
                            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Current</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                @if($isActive)
                                    Currently active — {{ $daysActive }} day(s) served
                                @elseif($isExpired)
                                    Ended
                                @else
                                    Inactive
                                @endif
                            </p>
                        </div>

                        <div class="relative pl-10">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: {{ $endDate ? 'var(--warning)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $endDate ? 'stop' : 'infinity' }} text-xs"></i>
                            </div>
                            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">End Date</h4>
                            @if($endDate)
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    {{ $endDate->format('l, F j, Y') }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($isActive && $daysLeft !== null && $daysLeft >= 0)
                                        {{ $daysLeft }} day(s) remaining
                                    @else
                                        Ended {{ $endDate->diffForHumans() }}
                                    @endif
                                </p>
                            @else
                                <p class="text-sm italic" style="color: var(--text-secondary);">
                                    No end date (indefinite)
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-lg text-center" style="background-color: var(--bg-secondary);">
                            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $daysActive }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Days served</div>
                        </div>
                        <div class="p-3 rounded-lg text-center" style="background-color: var(--bg-secondary);">
                            <div class="text-2xl font-bold" style="color: var(--success);">
                                @if($isActive && $daysLeft === null)
                                    ∞
                                @elseif($daysLeft !== null)
                                    {{ max(0, $daysLeft) }}
                                @else
                                    0
                                @endif
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">Days remaining</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT COLUMN ==================== -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Quick stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--primary);">{{ $team->count() }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Team</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Members</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--success);">{{ $daysActive }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Days</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">In role</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--warning);">{{ count($auditTrail) }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Changes</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Recorded</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--info);">
                        {{ ($pendingApprovals['swap_requests'] ?? 0)
                         + ($pendingApprovals['overtime_requests'] ?? 0)
                         + ($pendingApprovals['pending_verifications'] ?? 0) }}
                    </div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Pending</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Approvals</div>
                </div>
            </div>

            <!-- Notes -->
            @if($assignment->notes)
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i>
                            Notes
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                            <p class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $assignment->notes }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Team oversight -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Team Overseen
                    </h3>
                    <span class="px-3 py-1 rounded-full text-xs"
                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        {{ $team->count() }} member(s)
                    </span>
                </div>
                <div class="p-6">
                    @if($team->isEmpty())
                        <div class="text-center py-6">
                            <i class="fas fa-user-friends text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                No other active security personnel at the moment.
                            </p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($team as $member)
                                <div class="flex items-center p-3 rounded-lg"
                                     style="background-color: var(--bg-secondary);">
                                    @if($member->has_photo)
                                        <img src="{{ $member->photo_url }}" alt="{{ $member->name }}"
                                             class="w-10 h-10 rounded-full object-cover mr-3">
                                    @else
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-xs font-semibold"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                            {{ strtoupper(substr($member->name ?? '?', 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                                {{ $member->name }}
                                            </span>
                                            @if($member->can_be_supervisor)
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded"
                                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                      title="Also eligible for head">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs truncate" style="color: var(--text-secondary);">
                                            {{ $member->email ?? 'No email' }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Audit trail -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
                        Audit Trail
                    </h3>
                    <span class="text-xs" style="color: var(--text-secondary);">
                        {{ count($auditTrail) }} change(s)
                    </span>
                </div>
                <div class="p-6">
                    @if(empty($auditTrail))
                        <div class="text-center py-6">
                            <i class="fas fa-clipboard text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                No changes recorded yet.
                            </p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach(array_reverse($auditTrail) as $entry)
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-sm" style="color: var(--text-primary);">
                                            {{ $entry['updated_by_name'] ?? 'Unknown' }}
                                        </span>
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            {{ \Carbon\Carbon::parse($entry['updated_at'])->diffForHumans() }}
                                        </span>
                                    </div>
                                    @if(!empty($entry['changes']))
                                        <div class="mt-2 space-y-1">
                                            @foreach($entry['changes'] as $field => $change)
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    <strong style="color: var(--text-primary);">{{ $field }}:</strong>
                                                    <span style="text-decoration: line-through; opacity: 0.6;">
                                                        {{ is_scalar($change['old'] ?? '') ? ($change['old'] ?? '—') : json_encode($change['old'] ?? '') }}
                                                    </span>
                                                    <i class="fas fa-arrow-right mx-1" style="font-size: 8px;"></i>
                                                    <span>{{ is_scalar($change['new'] ?? '') ? ($change['new'] ?? '—') : json_encode($change['new'] ?? '') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Admin notes (shown only to admins) -->
            @if(($currentUser->isAdmin() || $currentUser->isSuperAdmin()) && !empty($assignment->metadata['admin_notes']))
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--primary);">
                            <i class="fas fa-user-shield mr-2"></i> Admin Notes
                            <span class="text-xs ml-2" style="color: var(--text-secondary);">(Internal)</span>
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="p-4 rounded-lg"
                             style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 3px solid var(--primary);">
                            <p class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $assignment->metadata['admin_notes'] }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Resign modal -->
<div id="resignModal" class="fixed inset-0 z-50 hidden overflow-y-auto"
     style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-sign-out-alt mr-2" style="color: var(--danger);"></i>
                    Resign from Head of Security
                </h3>
            </div>
            <form id="resignForm"
                  action="{{ route('security.supervisor-assignments.resign', $assignment) }}"
                  method="POST">
                @csrf
                <div class="p-6 space-y-4">
                    <div class="p-3 rounded-lg"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <p class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-1" style="color: var(--warning);"></i>
                            The community will have <strong>no active Head of Security</strong>
                            until an administrator appoints a successor.
                        </p>
                    </div>

                    <div>
                        <label for="resign_reason" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Reason for resignation <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="reason" id="resign_reason" rows="3"
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why are you stepping down?"
                                  required></textarea>
                    </div>

                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" id="resign_confirm" required
                               class="mt-1 w-4 h-4 rounded"
                               style="accent-color: var(--primary);">
                        <span class="text-sm" style="color: var(--text-secondary);">
                            I understand that my appointment will end immediately and administrators will be notified.
                        </span>
                    </label>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeModal('resignModal')"
                            class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg"
                            style="background-color: var(--danger); color: white;">
                        Confirm resignation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.btn-secondary {
    background-color: var(--bg-secondary); border: 1px solid var(--border-color);
    color: var(--text-primary); transition: all 0.2s;
}
.btn-secondary:hover { background-color: var(--border-color); }

.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    border: none; transition: all 0.2s; color: white;
}
.btn-danger:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--danger-rgb), 0.3); }

.index-custom-input { transition: all 0.2s ease; }
.index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}
</style>

<script>
function showResignModal() {
    document.getElementById('resign_reason').value = '';
    document.getElementById('resign_confirm').checked = false;
    document.getElementById('resignModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

window.addEventListener('click', function (event) {
    const modal = document.getElementById('resignModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});
</script>
@endsection