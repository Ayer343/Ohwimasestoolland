@extends('layouts.secu')

@section('title', 'Head of Security Dashboard')

@section('content')
@php
    $me  = auth()->user();
    $app = $activeAssignment;
    $isSoon = $app->end_date
        && ! $app->end_date->isPast()
        && $app->end_date->diffInDays(now()) <= 30;
    $isExpired = $app->end_date && $app->end_date->isPast();
@endphp

<div class="grid grid-cols-1 gap-6 mb-6">

    <!-- Header -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Head of Security Dashboard
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Welcome back, <strong style="color: var(--text-primary);">{{ $me->name }}</strong>.
                        You are currently Head of Security.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-4 sm:mt-0">
                <a href="{{ route('security.supervisor-assignments.show', $app) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary">
                    <i class="fas fa-eye mr-2"></i> View Appointment
                </a>
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-clipboard-list mr-2"></i> All Appointments
                </a>
            </div>
        </div>
    </div>

    <!-- Info banner -->
    @if($isSoon || $isExpired)
        <div class="card p-4"
             style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle mt-0.5 mr-3" style="color: var(--warning);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        @if($isExpired)
                            Your appointment has expired.
                        @else
                            Your appointment is expiring soon ({{ $app->end_date->format('M j, Y') }}).
                        @endif
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Contact an administrator to extend or replace the appointment.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Stat cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $counters['team_size'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Team Members</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $counters['days_active'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Days Served</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-hourglass-half" style="color: var(--success);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">
                        @if($counters['days_left'] === null)
                            ∞
                        @else
                            {{ $counters['days_left'] }}
                        @endif
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Days Remaining</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $counters['total_appointments'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Appointments Held</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                    style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clipboard-list" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Two-column body -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left column: appointment summary -->
        <div class="lg:col-span-1 space-y-6">
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        Current Appointment
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Title:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $app->title_label }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Appointed by:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ optional($app->assignedBy)->name ?? 'System' }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Start:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $app->start_date?->format('M j, Y') ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-sm" style="color: var(--text-secondary);">End:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $app->end_date?->format('M j, Y') ?? 'Indefinite' }}
                        </span>
                    </div>

                    @if($app->notes)
                        <div class="pt-2">
                            <p class="text-xs font-semibold uppercase" style="color: var(--text-secondary);">
                                Notes
                            </p>
                            <p class="text-sm mt-1 whitespace-pre-line" style="color: var(--text-primary);">
                                {{ $app->notes }}
                            </p>
                        </div>
                    @endif

                    <div class="pt-3">
                        <a href="{{ route('security.supervisor-assignments.show', $app) }}"
                           class="block text-center px-4 py-2 rounded-lg text-sm font-medium btn-secondary">
                            <i class="fas fa-arrow-right mr-2"></i> Full Appointment Details
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right column: team + history -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Team -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Your Team
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
                                                      title="Also eligible for Head of Security">
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

            <!-- History -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
                        Recent Appointments
                    </h3>
                    <a href="{{ route('security.supervisor-assignments.index') }}"
                       class="text-sm hover:underline" style="color: var(--primary);">
                        View all →
                    </a>
                </div>
                <div class="p-6">
                    @if($history->isEmpty())
                        <div class="text-center py-6">
                            <i class="fas fa-clipboard text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                No appointment history yet.
                            </p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($history as $entry)
                                @php
                                    $isCurrent = $entry->id === $app->id;
                                    $isPast    = $entry->end_date && $entry->end_date->isPast();
                                @endphp
                                <div class="flex items-center justify-between p-3 rounded-lg"
                                     style="background-color: var(--bg-secondary);">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                                {{ $entry->title_label }}
                                            </span>
                                            @if($isCurrent)
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded"
                                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                    Current
                                                </span>
                                            @elseif($isPast)
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded"
                                                      style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                                    Ended
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            {{ $entry->start_date?->format('M j, Y') ?? '—' }}
                                            →
                                            {{ $entry->end_date?->format('M j, Y') ?? 'indefinite' }}
                                            @if($entry->assignedBy)
                                                · by {{ $entry->assignedBy->name }}
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('security.supervisor-assignments.show', $entry) }}"
                                       class="ml-3 px-3 py-1.5 rounded-lg text-xs btn-secondary">
                                        View
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none; transition: all 0.2s; color: white;
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.btn-secondary {
    background-color: var(--bg-secondary); border: 1px solid var(--border-color);
    color: var(--text-primary); transition: all 0.2s;
}
.btn-secondary:hover { background-color: var(--border-color); }
</style>
@endsection