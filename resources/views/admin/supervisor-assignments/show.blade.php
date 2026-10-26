@extends('layouts.app')

@section('title', 'Head of Security Appointment')

@section('content')
@php
    $user         = $assignment->user;
    $userName     = optional($user)->name ?? 'Deleted User';
    $userEmail    = optional($user)->email ?? 'No email';
    $userPhone    = optional($user)->phone ?? 'Not provided';
    $assignedBy   = optional($assignment->assignedBy)->name ?? 'System';
    $startDate    = $assignment->start_date;
    $endDate      = $assignment->end_date;

    $isExpired  = $endDate && $endDate->isPast();
    $isActive   = (bool) $assignment->is_active && !$isExpired;
    $daysActive = $startDate && $startDate->isPast()
        ? (int) $startDate->diffInDays(now())
        : 0;
    $daysLeft = $endDate && !$isExpired
        ? (int) now()->diffInDays($endDate, false)
        : null;
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

                        @if($assignment->trashed())
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger);">
                                <i class="fas fa-trash-alt mr-1"></i> Deleted
                            </span>
                        @elseif($isActive)
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

                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs"
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-user-shield mr-1"></i> Admin
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-4 sm:mt-0">
                @if(!$assignment->trashed())
                    <a href="{{ route('admin.supervisor-assignments.edit', $assignment) }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>

                    <form action="{{ route('admin.supervisor-assignments.update', $assignment) }}"
                          method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="user_id"    value="{{ $assignment->user_id }}">
                        <input type="hidden" name="title"      value="{{ $assignment->title }}">
                        <input type="hidden" name="start_date" value="{{ $startDate?->format('Y-m-d') }}">
                        <input type="hidden" name="end_date"   value="{{ $endDate?->format('Y-m-d') }}">
                        <input type="hidden" name="notes"      value="{{ $assignment->notes }}">
                        <input type="hidden" name="is_active"  value="{{ $assignment->is_active ? '0' : '1' }}">
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                style="background-color: rgba(var(--{{ $assignment->is_active ? 'warning' : 'success' }}-rgb), 0.15); color: var(--{{ $assignment->is_active ? 'warning' : 'success' }});">
                            <i class="fas fa-{{ $assignment->is_active ? 'pause' : 'play' }} mr-2"></i>
                            {{ $assignment->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>

                    @if($isActive && $endDate)
                        <button type="button" onclick="showExtendModal()"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info);">
                            <i class="fas fa-calendar-plus mr-2"></i> Extend
                        </button>
                    @endif

                    @if($isActive)
                        <button type="button" onclick="showTerminateModal()"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger);">
                            <i class="fas fa-ban mr-2"></i> Terminate
                        </button>
                    @endif

                    <form action="{{ route('admin.supervisor-assignments.destroy', $assignment) }}"
                          method="POST" class="inline"
                          onsubmit="return confirm('Move this appointment to trash?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                style="background-color: var(--danger); color: white;">
                            <i class="fas fa-trash mr-2"></i> Delete
                        </button>
                    </form>
                @else
                    <form action="{{ route('admin.supervisor-assignments.trash.restore', $assignment->id) }}"
                          method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                style="background-color: var(--success); color: white;">
                            <i class="fas fa-undo-alt mr-2"></i> Restore
                        </button>
                    </form>
                    <form action="{{ route('admin.supervisor-assignments.trash.force-delete', $assignment->id) }}"
                          method="POST" class="inline"
                          onsubmit="return confirm('Permanently delete this appointment? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                style="background-color: var(--danger); color: white;">
                            <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                        </button>
                    </form>
                @endif

                <a href="{{ route('admin.supervisor-assignments.index') }}"
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
            <div class="flex items-start">
                <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--success);">Success!</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-4"
             style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--danger);">Error!</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ===================== LEFT COLUMN ===================== -->
        <div class="lg:col-span-1 space-y-6">

            <!-- Officer Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Officer
                    </h3>
                </div>
                <div class="p-6">
                    @if($user)
                        <div class="flex items-center mb-4">
                            @if($user->has_photo)
                                <img src="{{ $user->photo_url }}" alt="{{ $userName }}"
                                     class="w-16 h-16 rounded-full object-cover">
                            @else
                                <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl"
                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                    {{ strtoupper(substr($userName, 0, 1)) }}
                                </div>
                            @endif
                            <div class="ml-4">
                                <h4 class="text-xl font-semibold" style="color: var(--text-primary);">
                                    {{ $userName }}
                                </h4>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $userEmail }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Eligible:</span>
                                <span class="text-sm font-medium">
                                    @if($user->can_be_supervisor)
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
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $userPhone }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Member since:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $user->created_at?->format('M Y') ?? 'N/A' }}
                                </span>
                            </div>

                            @if(!empty($user->security_supervisor_certifications))
                                <div class="pt-2">
                                    <p class="text-xs font-semibold uppercase" style="color: var(--text-secondary);">
                                        Certifications
                                    </p>
                                    <p class="text-sm mt-1 whitespace-pre-line" style="color: var(--text-primary);">
                                        {{ $user->security_supervisor_certifications }}
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
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                        Appointment Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
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
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                        Timeline
                    </h3>
                </div>
                <div class="p-6">
                    <div class="relative">
                        <div class="absolute left-3 top-0 bottom-0 w-0.5"
                             style="background-color: var(--border-color);"></div>

                        <!-- Start -->
                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: var(--success); color: white;">
                                <i class="fas fa-play text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Start Date</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    {{ $startDate?->format('l, F j, Y') ?? 'N/A' }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $startDate?->diffForHumans() ?? 'N/A' }}
                                </p>
                            </div>
                        </div>

                        <!-- Current -->
                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: {{ $isActive ? 'var(--info)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $isActive ? 'hourglass-half' : 'hourglass-end' }} text-xs"></i>
                            </div>
                            <div>
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
                        </div>

                        <!-- End -->
                        <div class="relative pl-10">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: {{ $endDate ? 'var(--warning)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $endDate ? 'stop' : 'infinity' }} text-xs"></i>
                            </div>
                            <div>
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
                    </div>

                    <!-- Duration stats -->
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

        <!-- ===================== RIGHT COLUMN ===================== -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--primary);">{{ $team->count() }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">
                        Team
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Members</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--success);">{{ $daysActive }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">
                        Days
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">In role</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--warning);">
                        {{ count($auditTrail) }}
                    </div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">
                        Changes
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Recorded</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--info);">
                        {{ $pendingApprovals['swap_requests'] + $pendingApprovals['overtime_requests'] + $pendingApprovals['pending_verifications'] }}
                    </div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">
                        Pending
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Approvals</div>
                </div>
            </div>

            <!-- Notes -->
            @if($assignment->notes)
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
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
                                No changes recorded yet. Updates will appear here.
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

            <!-- Metadata debug (collapsible) -->
            @if(!empty($assignment->metadata) && count((array) $assignment->metadata) > 0)
                <div class="card">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-database mr-2" style="color: var(--primary);"></i>
                            Metadata
                        </h3>
                    </div>
                    <div class="p-6">
                        <details>
                            <summary class="cursor-pointer text-sm font-medium" style="color: var(--text-secondary);">
                                View raw metadata ({{ count((array) $assignment->metadata) }} key(s))
                            </summary>
                            <pre class="mt-3 p-3 rounded-lg text-xs overflow-x-auto"
                                 style="background-color: var(--bg-secondary); color: var(--text-secondary);">{{ json_encode($assignment->metadata, JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Extend Modal -->
<div id="extendModal" class="fixed inset-0 z-50 hidden"
     style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Extend Appointment</h3>
            </div>
            <form action="{{ route('admin.supervisor-assignments.extend', $assignment) }}" method="POST">
                @csrf
                <div class="p-6">
                    <div class="mb-4">
                        <label for="new_end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            New End Date
                        </label>
                        <input type="date" name="new_end_date" id="new_end_date"
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="{{ now()->format('Y-m-d') }}"
                               value="{{ $endDate?->format('Y-m-d') }}" required>
                    </div>
                    <div class="mb-4">
                        <label for="extend_reason" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Reason
                        </label>
                        <textarea name="reason" id="extend_reason" rows="3"
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Optional reason for extension..."></textarea>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeModal('extendModal')"
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-secondary">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-primary text-white">
                        Extend
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terminate Modal -->
<div id="terminateModal" class="fixed inset-0 z-50 hidden"
     style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Terminate Appointment</h3>
            </div>
            <form action="{{ route('admin.supervisor-assignments.terminate', $assignment) }}" method="POST">
                @csrf
                <div class="p-6">
                    <div class="mb-4">
                        <label for="terminate_reason" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Reason for termination <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="reason" id="terminate_reason" rows="3"
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Explain why this appointment is being terminated..."
                                  required></textarea>
                    </div>
                    <div class="p-3 rounded-lg"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                            Terminating ends the appointment immediately. The record is preserved in the audit trail.
                        </p>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeModal('terminateModal')"
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-secondary">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium"
                            style="background-color: var(--danger); color: white;">
                        Terminate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function showExtendModal() {
    const modal = document.getElementById('extendModal');
    modal.classList.remove('hidden');
}

function showTerminateModal() {
    const modal = document.getElementById('terminateModal');
    modal.classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Close on outside click
window.addEventListener('click', function (event) {
    ['extendModal', 'terminateModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
});
</script>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none; transition: all 0.2s ease;
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.btn-secondary {
    background-color: var(--bg-secondary); border: 1px solid var(--border-color);
    color: var(--text-primary); transition: all 0.2s ease;
}
.btn-secondary:hover { background-color: var(--border-color); }

.index-custom-input { transition: all 0.2s ease; }
.index-custom-input:focus {
    border-color: var(--primary) !important; outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-family: 'Courier New', monospace;
}
</style>
@endsection