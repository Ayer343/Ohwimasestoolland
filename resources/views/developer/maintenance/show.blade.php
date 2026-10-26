@extends('layouts.dev')

@section('title', 'Maintenance Details - ' . $maintenance->reference_id)

@section('content')
@php
    use App\Models\Maintenance;
    use App\Models\User;

    $statusStyle = match ($maintenance->status) {
        Maintenance::STATUS_DRAFT       => ['info',    'var(--info)',    'Draft'],
        Maintenance::STATUS_SCHEDULED   => ['primary', 'var(--primary)', 'Scheduled'],
        Maintenance::STATUS_IN_PROGRESS => ['warning', 'var(--warning)', 'In Progress'],
        Maintenance::STATUS_COMPLETED   => ['success', 'var(--success)', 'Completed'],
        Maintenance::STATUS_CANCELLED   => ['danger',  'var(--danger)',  'Cancelled'],
        default                         => ['info',    'var(--info)',    ucfirst($maintenance->status)],
    };

    $impactStyle = match ($maintenance->impact_level) {
        'critical' => ['danger',   'var(--danger)'],
        'high'     => ['warning',  'var(--warning)'],
        'medium'   => ['warning',  'var(--warning)'],
        default    => ['success',  'var(--success)'],
    };

    $userTypeLabels = User::getUserTypes();
    $editable = in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED], true);
    $canCancel = in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED], true);
@endphp

<div class="max-w-7xl mx-auto">

    {{-- ============================================================
         | HEADER
         * ============================================================ --}}
    <div class="mb-8">
        <div class="flex justify-between items-start mb-4 flex-wrap gap-3">
            <div>
                <div class="flex items-center mb-2 flex-wrap gap-2">
                    <h1 class="text-2xl font-bold mr-3" style="color: var(--text-primary);">
                        {{ $maintenance->title }}
                    </h1>
                    <span class="badge"
                          style="background-color: rgba(var(--{{ $statusStyle[0] }}-rgb), 0.15); color: {{ $statusStyle[1] }}; border: 1px solid rgba(var(--{{ $statusStyle[0] }}-rgb), 0.3);">
                        {{ $statusStyle[2] }}
                    </span>
                    @if($maintenance->is_emergency)
                        <span class="badge" style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-bolt mr-1"></i> Emergency
                        </span>
                    @endif
                </div>
                <div class="flex items-center flex-wrap gap-4">
                    <div class="flex items-center">
                        <i class="fas fa-hashtag text-sm mr-2" style="color: var(--text-secondary);"></i>
                        <span class="text-sm font-mono" style="color: var(--text-secondary);">{{ $maintenance->reference_id }}</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-user-circle text-sm mr-2" style="color: var(--text-secondary);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Created by {{ $maintenance->creator->name ?? 'Unknown' }}
                        </span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar text-sm mr-2" style="color: var(--text-secondary);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            {{ $maintenance->created_at->format('M d, Y H:i') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if($maintenance->status === Maintenance::STATUS_DRAFT)
                    <a href="{{ route('developer.maintenance.edit', $maintenance->id) }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>
                    <form action="{{ route('developer.maintenance.approve', $maintenance->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" onclick="return confirm('Approve this maintenance schedule?')"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-check mr-2"></i> Approve
                        </button>
                    </form>
                @elseif($maintenance->status === Maintenance::STATUS_SCHEDULED)
                    <form action="{{ route('developer.maintenance.start', $maintenance->id) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="confirm_pre_checks" value="1">
                        <input type="hidden" name="start_notes" value="Started from detail page">
                        <button type="submit" onclick="return confirm('Start this maintenance now? This will notify users.')"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-play mr-2"></i> Start
                        </button>
                    </form>
                    <a href="{{ route('developer.maintenance.edit', $maintenance->id) }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-pencil-alt mr-2"></i> Update
                    </a>
                    <button type="button" onclick="openCancelModal()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                @elseif($maintenance->status === Maintenance::STATUS_IN_PROGRESS)
                    <button type="button" onclick="openCompleteModal()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--success); color: white;">
                        <i class="fas fa-flag-checkered mr-2"></i> Complete
                    </button>
                @elseif($maintenance->status === Maintenance::STATUS_COMPLETED)
                    <a href="{{ route('developer.maintenance.history', $maintenance->id) }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i> History
                    </a>
                @endif

                <a href="{{ route('developer.maintenance.index') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-list mr-2"></i> Back
                </a>
            </div>
        </div>

        {{-- ============================================================
             | TIMELINE
             * ============================================================ --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-4">
                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <h3 class="font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-stream mr-2" style="color: var(--primary);"></i> Maintenance Timeline
                    </h3>
                    @if($maintenance->status === Maintenance::STATUS_IN_PROGRESS)
                        <div class="flex items-center">
                            <div class="h-2 w-2 rounded-full animate-pulse bg-green-500 mr-2"></div>
                            <span class="text-sm" style="color: var(--success);">
                                In progress for {{ $maintenance->current_duration }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="relative">
                    <div class="absolute left-0 top-1/2 h-0.5 w-full -translate-y-1/2"
                         style="background-color: var(--border-color);"></div>

                    <div class="relative flex justify-between">
                        {{-- Draft --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if($maintenance->status !== Maintenance::STATUS_DRAFT) bg-green-500 text-white
                                @else bg-blue-500 text-white @endif">
                                <i class="fas fa-pencil-alt text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Draft</span>
                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $maintenance->created_at->format('M d') }}
                            </span>
                        </div>

                        {{-- Approved --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if(in_array($maintenance->status, [Maintenance::STATUS_SCHEDULED, Maintenance::STATUS_IN_PROGRESS, Maintenance::STATUS_COMPLETED], true)) bg-green-500 text-white
                                @elseif($maintenance->status === Maintenance::STATUS_DRAFT) bg-gray-300 text-gray-500
                                @else bg-blue-500 text-white @endif">
                                <i class="fas fa-check text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Approved</span>
                            @if($maintenance->approved_at)
                                <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $maintenance->approved_at->format('M d') }}
                                </span>
                            @endif
                        </div>

                        {{-- Scheduled --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if(in_array($maintenance->status, [Maintenance::STATUS_IN_PROGRESS, Maintenance::STATUS_COMPLETED], true)) bg-green-500 text-white
                                @elseif(in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_CANCELLED], true)) bg-gray-300 text-gray-500
                                @else bg-blue-500 text-white @endif">
                                <i class="fas fa-clock text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Scheduled</span>
                            @if($maintenance->scheduled_start)
                                <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $maintenance->scheduled_start->format('M d') }}
                                </span>
                            @endif
                        </div>

                        {{-- In Progress --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if($maintenance->status === Maintenance::STATUS_COMPLETED) bg-green-500 text-white
                                @elseif($maintenance->status === Maintenance::STATUS_IN_PROGRESS) bg-blue-500 text-white
                                @else bg-gray-300 text-gray-500 @endif">
                                <i class="fas fa-play text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">In Progress</span>
                            @if($maintenance->actual_start)
                                <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $maintenance->actual_start->format('M d') }}
                                </span>
                            @endif
                        </div>

                        {{-- Completed --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if($maintenance->status === Maintenance::STATUS_COMPLETED) bg-green-500 text-white
                                @else bg-gray-300 text-gray-500 @endif">
                                <i class="fas fa-flag-checkered text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Completed</span>
                            @if($maintenance->completed_at)
                                <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $maintenance->completed_at->format('M d') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ============================================================
             | LEFT COLUMN
             * ============================================================ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Details Card --}}
            <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Maintenance Details
                    </h3>
                </div>

                <div class="p-6 space-y-6">
                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Description</h4>
                        <p class="text-sm" style="color: var(--text-primary);">{{ $maintenance->description }}</p>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">User Impact Description</h4>
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <p class="text-sm" style="color: var(--text-primary);">{{ $maintenance->user_impact_description }}</p>
                        </div>
                    </div>

                    @if($maintenance->technical_details)
                        <div>
                            <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Technical Details</h4>
                            <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                                <p class="text-sm whitespace-pre-wrap">{{ $maintenance->technical_details }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-tag mr-2"></i> Classification
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Type:</span>
                                    <span class="font-medium capitalize" style="color: var(--text-primary);">
                                        {{ str_replace('_', ' ', $maintenance->maintenance_type) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Impact Level:</span>
                                    <span class="badge" style="background-color: rgba(var(--{{ $impactStyle[0] }}-rgb), 0.15); color: {{ $impactStyle[1] }}; border: 1px solid rgba(var(--{{ $impactStyle[0] }}-rgb), 0.3);">
                                        {{ ucfirst($maintenance->impact_level) }}
                                    </span>
                                </div>
                                @if($maintenance->is_emergency)
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Emergency:</span>
                                        <span class="badge" style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger);">Yes</span>
                                    </div>
                                @endif
                                @if($maintenance->emergencyMode)
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Linked Emergency:</span>
                                        <a href="{{ route('developer.emergency.show', $maintenance->emergencyMode->id) }}"
                                           class="hover:underline" style="color: var(--primary);">
                                            {{ $maintenance->emergencyMode->reference_id }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-user-cog mr-2"></i> Responsibility
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Created By:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $maintenance->creator->name ?? 'Unknown' }}</span>
                                </div>
                                @if($maintenance->approver)
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Approved By:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $maintenance->approver->name }}</span>
                                    </div>
                                @endif
                                @if($maintenance->completer)
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Completed By:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $maintenance->completer->name }}</span>
                                    </div>
                                @endif
                                @if($maintenance->cancelled_by)
                                    <div class="flex justify-between">
                                        <span style="color: var(--text-secondary);">Cancelled By:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">
                                            {{ User::find($maintenance->cancelled_by)?->name ?? 'Unknown' }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Schedule & Timing --}}
            <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Schedule &amp; Timing
                    </h3>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Scheduled --}}
                        <div>
                            <h4 class="text-sm font-medium mb-4" style="color: var(--text-secondary);">Scheduled Timeline</h4>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm" style="color: var(--text-secondary);">Start Time</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ optional($maintenance->scheduled_start)->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full" style="width: 100%; background-color: var(--primary);"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm" style="color: var(--text-secondary);">Estimated End</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ optional($maintenance->scheduled_end)->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full"
                                             style="width: {{ min(100, ($maintenance->estimated_duration_minutes / 1440) * 100) }}%; background-color: var(--success);"></div>
                                    </div>
                                </div>

                                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                    <div class="flex items-center">
                                        <i class="fas fa-hourglass-half mr-2" style="color: var(--info);"></i>
                                        <span class="text-sm" style="color: var(--text-secondary);">
                                            Estimated Duration:
                                            <span class="font-medium" style="color: var(--text-primary);">
                                                {{ $maintenance->estimated_duration_minutes }} minutes
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actual --}}
                        <div>
                            <h4 class="text-sm font-medium mb-4" style="color: var(--text-secondary);">
                                Actual Timeline
                                @if($maintenance->status === Maintenance::STATUS_IN_PROGRESS)
                                    <span class="ml-2 badge" style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">Live</span>
                                @endif
                            </h4>

                            @if($maintenance->actual_start || $maintenance->actual_end)
                                <div class="space-y-4">
                                    @if($maintenance->actual_start)
                                        <div>
                                            <div class="flex justify-between mb-1">
                                                <span class="text-sm" style="color: var(--text-secondary);">Actual Start</span>
                                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                                    {{ $maintenance->actual_start->format('M d, Y H:i') }}
                                                </span>
                                            </div>
                                            <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                                <div class="h-full rounded-full" style="width: 100%; background-color: var(--primary);"></div>
                                            </div>
                                        </div>
                                    @endif

                                    @if($maintenance->actual_end)
                                        <div>
                                            <div class="flex justify-between mb-1">
                                                <span class="text-sm" style="color: var(--text-secondary);">Actual End</span>
                                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                                    {{ $maintenance->actual_end->format('M d, Y H:i') }}
                                                </span>
                                            </div>
                                            <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                                <div class="h-full rounded-full" style="width: 100%; background-color: var(--success);"></div>
                                            </div>
                                        </div>

                                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <span class="text-xs block" style="color: var(--text-secondary);">Actual Duration</span>
                                                    <span class="font-medium" style="color: var(--text-primary);">
                                                        {{ $maintenance->actual_duration_minutes ?? 'N/A' }} min
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-xs block" style="color: var(--text-secondary);">Downtime</span>
                                                    <span class="font-medium" style="color: var(--text-primary);">
                                                        {{ $maintenance->downtime_minutes ?? 0 }} min
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center justify-center h-full p-8">
                                    <div class="text-center">
                                        <i class="fas fa-clock text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-sm" style="color: var(--text-secondary);">
                                            Actual timeline will appear here<br>once maintenance starts
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($maintenance->status === Maintenance::STATUS_SCHEDULED)
                        <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div>
                                    <h4 class="font-medium mb-1" style="color: var(--text-primary);">
                                        <i class="fas fa-calendar-alt mr-2"></i> Maintenance starts in
                                    </h4>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        {{ optional($maintenance->scheduled_start)->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Impact Assessment --}}
            <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i> Impact Assessment
                        </h3>
                        @if($maintenance->notify_users && $maintenance->notification_sent_at)
                            <span class="badge" style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                <i class="fas fa-bell mr-1"></i> Notified {{ $maintenance->notification_sent_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Affected Modules --}}
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Affected Modules</h4>
                        <div class="flex flex-wrap gap-2">
                            @forelse($maintenance->affected_modules_list ?? [] as $module)
                                <span class="badge" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-cube mr-1"></i> {{ $module }}
                                </span>
                            @empty
                                <span class="text-sm" style="color: var(--text-secondary);">No modules specified</span>
                            @endforelse
                        </div>
                    </div>

                    {{-- Affected User Types --}}
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Affected User Types
                            <span class="ml-2 text-xs font-normal">({{ $affectedUsersCount }} users affected)</span>
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($maintenance->affected_user_types ?? [] as $type)
                                <div class="flex items-center p-2 rounded-lg border" style="border-color: var(--border-color);">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        @switch((int) $type)
                                            @case(User::TYPE_SUPER_ADMIN) <i class="fas fa-crown"></i> @break
                                            @case(User::TYPE_ADMIN) <i class="fas fa-user-tie"></i> @break
                                            @case(User::TYPE_LANDLORD) <i class="fas fa-building"></i> @break
                                            @case(User::TYPE_TENANT) <i class="fas fa-home"></i> @break
                                            @case(User::TYPE_FIELD_AGENT) <i class="fas fa-user-check"></i> @break
                                            @case(User::TYPE_DEVELOPER) <i class="fas fa-code"></i> @break
                                            @case(User::TYPE_SECURITY_PERSONNEL) <i class="fas fa-shield-alt"></i> @break
                                            @default <i class="fas fa-user"></i>
                                        @endswitch
                                    </div>
                                    <span class="text-sm" style="color: var(--text-primary);">
                                        {{ $userTypeLabels[(int) $type] ?? 'Type ' . $type }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Allowed Operations --}}
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Allowed Operations During Maintenance</h4>
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <div class="flex flex-wrap gap-2">
                                @forelse($maintenance->allowed_operations ?? [] as $operation)
                                    <span class="badge" style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i> {{ str_replace('_', ' ', ucfirst($operation)) }}
                                    </span>
                                @empty
                                    <span class="text-sm" style="color: var(--text-secondary);">None specified</span>
                                @endforelse
                            </div>
                            <p class="text-xs mt-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> These operations will remain available during the maintenance window.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             | RIGHT COLUMN
             * ============================================================ --}}
        <div class="space-y-6">

            {{-- Statistics --}}
            <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Statistics
                    </h3>
                </div>

                <div class="p-6 space-y-4">
                    @if(!empty($impactMetrics))
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Impact Metrics</h4>
                            <div class="space-y-3">
                                @php
                                    $accuracy = $impactMetrics['user_impact']['accuracy_percentage'] ?? 0;
                                    $accuracyColor = $accuracy >= 90 ? 'success' : ($accuracy >= 70 ? 'warning' : 'danger');
                                @endphp
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs" style="color: var(--text-secondary);">Estimated vs Actual Users</span>
                                        <span class="text-xs font-medium" style="color: var(--{{ $accuracyColor }});">
                                            {{ round($accuracy, 1) }}%
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full"
                                             style="width: {{ min(100, $accuracy) }}%; background-color: var(--{{ $accuracyColor }});"></div>
                                    </div>
                                    <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                                        <span>Est: {{ number_format($impactMetrics['user_impact']['estimated_users'] ?? 0) }}</span>
                                        <span>Act: {{ number_format($impactMetrics['user_impact']['actual_users'] ?? 0) }}</span>
                                    </div>
                                </div>

                                @if($maintenance->status === Maintenance::STATUS_COMPLETED && !empty($impactMetrics['duration_metrics']))
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs" style="color: var(--text-secondary);">Duration Accuracy</span>
                                            <span class="text-xs font-medium"
                                                  style="color: {{ $impactMetrics['duration_metrics']['within_estimate'] ? 'var(--success)' : 'var(--warning)' }};">
                                                {{ $impactMetrics['duration_metrics']['accuracy_percentage'] }}%
                                            </span>
                                        </div>
                                        <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                            <div class="h-full rounded-full"
                                                 style="width: {{ min(100, $impactMetrics['duration_metrics']['accuracy_percentage']) }}%;
                                                        background-color: {{ $impactMetrics['duration_metrics']['within_estimate'] ? 'var(--success)' : 'var(--warning)' }};"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div class="text-center p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <div class="text-2xl font-bold mb-1" style="color: var(--text-primary);">{{ $affectedUsersCount }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Affected Users</div>
                        </div>

                        <div class="text-center p-3 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <div class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                                {{ count($maintenance->affected_modules ?? []) }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">Modules</div>
                        </div>
                    </div>

                    @if(!empty($impactMetrics['financial_impact']))
                        <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: rgba(var(--warning-rgb), 0.05);">
                            <h4 class="text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-chart-line mr-2"></i> Estimated Financial Impact
                            </h4>
                            <div class="text-lg font-bold mb-1" style="color: var(--warning);">
                                ${{ number_format($impactMetrics['financial_impact']['estimated_cost'], 2) }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                Based on {{ number_format($impactMetrics['financial_impact']['affected_users']) }} users
                                and {{ $impactMetrics['financial_impact']['downtime_hours'] }} hours downtime
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Related Maintenances --}}
            @if(($relatedMaintenances ?? collect())->count() > 0)
                <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-link mr-2" style="color: var(--primary);"></i> Overlapping Maintenance
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        @foreach($relatedMaintenances as $related)
                            @php
                                $relatedStatusStyle = match ($related->status) {
                                    Maintenance::STATUS_DRAFT       => ['info',    'var(--info)',    'Draft'],
                                    Maintenance::STATUS_SCHEDULED   => ['primary', 'var(--primary)', 'Scheduled'],
                                    Maintenance::STATUS_IN_PROGRESS => ['warning', 'var(--warning)', 'In Progress'],
                                    Maintenance::STATUS_COMPLETED   => ['success', 'var(--success)', 'Completed'],
                                    Maintenance::STATUS_CANCELLED   => ['danger',  'var(--danger)',  'Cancelled'],
                                    default                         => ['info',    'var(--info)',    ucfirst($related->status)],
                                };
                            @endphp
                            <a href="{{ route('developer.maintenance.show', $related->id) }}"
                               class="block p-3 rounded-lg border transition-all hover:opacity-80"
                               style="border-color: var(--border-color);">
                                <div class="flex justify-between items-start mb-2 gap-2">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ Str::limit($related->title, 40) }}
                                    </span>
                                    <span class="badge"
                                          style="background-color: rgba(var(--{{ $relatedStatusStyle[0] }}-rgb), 0.15); color: {{ $relatedStatusStyle[1] }};">
                                        {{ $relatedStatusStyle[2] }}
                                    </span>
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <div class="flex items-center mb-1">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ optional($related->scheduled_start)->format('M d, H:i') }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recent Activity --}}
            <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Recent Activity
                        </h3>
                        <a href="{{ route('developer.maintenance.history', $maintenance->id) }}"
                           class="text-sm hover:underline" style="color: var(--primary);">
                            View All
                        </a>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    @forelse($recentLogs as $log)
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 mt-1 flex-shrink-0"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                @switch($log->action)
                                    @case('created')     <i class="fas fa-plus"></i> @break
                                    @case('approved')    <i class="fas fa-check"></i> @break
                                    @case('started')     <i class="fas fa-play"></i> @break
                                    @case('completed')   <i class="fas fa-flag-checkered"></i> @break
                                    @case('cancelled')   <i class="fas fa-times"></i> @break
                                    @case('updated')     <i class="fas fa-edit"></i> @break
                                    @case('notified')    <i class="fas fa-bell"></i> @break
                                    @default             <i class="fas fa-info-circle"></i>
                                @endswitch
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between flex-wrap gap-1">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        {{ $log->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $log->details }}</p>
                                @if($log->performed_by)
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i>
                                        {{ User::find($log->performed_by)?->name ?? 'System' }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="fas fa-history text-2xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">No activity recorded yet</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Quick Actions
                    </h3>
                </div>

                <div class="p-6 space-y-2">
                    @if($editable)
                        <a href="{{ route('developer.maintenance.edit', $maintenance->id) }}"
                           class="block w-full px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 text-left"
                           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-edit mr-2"></i> Edit Schedule
                        </a>
                    @endif

                    @if($canCancel)
                        <button type="button" onclick="openCancelModal()"
                                class="w-full px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 text-left"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-times mr-2"></i> Cancel Maintenance
                        </button>
                    @endif

                    <a href="{{ route('developer.maintenance.history', $maintenance->id) }}"
                       class="block w-full px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 text-left"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i> View Full History
                    </a>

                    <a href="{{ route('developer.health.index') }}"
                       class="block w-full px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 text-left"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-heartbeat mr-2"></i> System Health
                    </a>

                    <a href="{{ route('developer.maintenance.statistics') }}"
                       class="block w-full px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 text-left"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-chart-pie mr-2"></i> View Statistics
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     | CANCEL MODAL
     * ============================================================ --}}
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Maintenance
                </h3>
                <button type="button" onclick="closeModal('cancelModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This action cannot be undone. Users will be notified if this was scheduled.
            </p>

            <form action="{{ route('developer.maintenance.cancel', $maintenance->id) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="cancellation_reason" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Cancellation Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3" maxlength="500"
                              class="w-full p-3 border rounded-lg"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Why are you cancelling this maintenance?"
                              required minlength="10"></textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('cancelModal')"
                            class="px-4 py-2 rounded-lg text-sm"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Keep Schedule
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-times mr-2"></i> Cancel Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     | COMPLETE MODAL
     * ============================================================ --}}
<div id="completeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-lg max-h-[90vh] overflow-y-auto"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-flag-checkered mr-2" style="color: var(--success);"></i> Complete Maintenance
                </h3>
                <button type="button" onclick="closeModal('completeModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="{{ route('developer.maintenance.complete', $maintenance->id) }}" method="POST">
                @csrf
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Completion Notes <span class="text-red-500">*</span>
                        </label>
                        <textarea name="completion_notes" rows="3" maxlength="1000"
                                  class="w-full p-3 border rounded-lg"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe what was done during maintenance..."
                                  required minlength="10"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                Actual Duration (min) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="actual_duration_minutes"
                                   value="{{ $maintenance->actual_start ? max(1, $maintenance->actual_start->diffInMinutes(now())) : $maintenance->estimated_duration_minutes }}"
                                   min="1" max="1440" required
                                   class="w-full p-2 border rounded-lg"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                Affected Users <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="actual_affected_users"
                                   value="{{ $maintenance->estimated_affected_users }}"
                                   min="0" required
                                   class="w-full p-2 border rounded-lg"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Downtime Minutes (if any)</label>
                        <input type="number" name="downtime_minutes" min="0"
                               class="w-full p-2 border rounded-lg"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Estimated vs Actual <span class="text-red-500">*</span>
                        </label>
                        <select name="estimated_vs_actual" required
                                class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="within_estimate">Within Estimate</option>
                            <option value="under_estimate">Under Estimate</option>
                            <option value="over_estimate">Over Estimate</option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="post_checks_passed" name="post_checks_passed" value="1" required
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="post_checks_passed" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Confirm post-maintenance checks have passed <span class="text-red-500">*</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('completeModal')"
                            class="px-4 py-2 rounded-lg text-sm"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm flex items-center"
                            style="background-color: var(--success); color: white;">
                        <i class="fas fa-flag-checkered mr-2"></i> Complete Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .card {
        transition: box-shadow 0.2s ease;
    }
    .card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

    @media (max-width: 768px) {
        .card { border-radius: 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---------- Modal helpers ---------- */
    window.openCancelModal = function () {
        document.getElementById('cancelModal').classList.remove('hidden');
    };

    window.openCompleteModal = function () {
        document.getElementById('completeModal').classList.remove('hidden');
    };

    window.closeModal = function (id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    };

    /* ---------- Backdrop click closes ---------- */
    document.querySelectorAll('.fixed.inset-0').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });

    /* ---------- Escape closes ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal('cancelModal');
            closeModal('completeModal');
        }
    });

    /* ---------- Optional silent refresh while in progress ---------- */
    @if($maintenance->status === \App\Models\Maintenance::STATUS_IN_PROGRESS)
        setInterval(function () {
            const anyModalOpen = document.querySelector('#cancelModal:not(.hidden), #completeModal:not(.hidden)');
            if (!anyModalOpen) {
                window.location.reload();
            }
        }, 90000);
    @endif
})();
</script>
@endpush