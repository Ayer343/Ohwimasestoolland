{{-- resources/views/landlord/waste/show.blade.php --}}

@extends('layouts.landlord')

@section('title', 'Request Details')

@section('content')
@php
    // -----------------------------------------------------------------
    // Theme detection — server-side mirror of the layout's client theme
    // -----------------------------------------------------------------
    $userAuth = auth()->user();
    $currentTheme = null;

    if ($userAuth && method_exists($userAuth, 'getThemePreference')) {
        $currentTheme = $userAuth->getThemePreference();
    }

    if (!$currentTheme) {
        $currentTheme = request()->cookie('theme')
            ?? session('theme')
            ?? 'light';
    }

    $isDark = $currentTheme === 'dark';

    // -----------------------------------------------------------------
    // Inline badge style builders — literal values, no CSS variables.
    // These cannot be overridden by any stylesheet or theme switcher.
    // -----------------------------------------------------------------
    $baseBadgeStyle = 'display:inline-flex!important;'
        . 'align-items:center;'
        . 'justify-content:center;'
        . 'padding:2px 8px;'
        . 'border-radius:6px;'
        . 'font-size:11px;'
        . 'font-weight:600;'
        . 'line-height:1.4;'
        . 'white-space:nowrap;'
        . 'visibility:visible!important;'
        . 'opacity:1!important;'
        . 'border:1px solid transparent;';

    // ---------- Request status badges ----------
    $statusStyles = [
        'pending' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'assigned' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.35)') . '!important;',

        'en_route' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(99,102,241,0.22)' : 'rgba(99,102,241,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#a5b4fc' : '#4f46e5') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(99,102,241,0.45)' : 'rgba(99,102,241,0.35)') . '!important;',

        'arrived' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(168,85,247,0.22)' : 'rgba(168,85,247,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#c084fc' : '#7e22ce') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(168,85,247,0.45)' : 'rgba(168,85,247,0.35)') . '!important;',

        'in_progress' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(6,182,212,0.22)' : 'rgba(6,182,212,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#22d3ee' : '#0891b2') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(6,182,212,0.45)' : 'rgba(6,182,212,0.35)') . '!important;',

        'completed' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'cancelled' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'missed' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Approval status badges ----------
    $approvalStyles = [
        'pending' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'approved' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'rejected' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'expired' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',

        'auto_approved' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.35)') . '!important;',

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Timeline event icon badges ----------
    // Each is a circular badge with an icon inside, colored by status.
    $baseTimelineStyle = 'display:inline-flex!important;'
        . 'align-items:center;'
        . 'justify-content:center;'
        . 'width:32px;'
        . 'height:32px;'
        . 'border-radius:50%;'
        . 'font-size:12px;'
        . 'line-height:1;'
        . 'flex-shrink:0;'
        . 'visibility:visible!important;'
        . 'opacity:1!important;'
        . 'border:1px solid transparent;';

    $timelineStyles = [
        'pending' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.25)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.5)' : 'rgba(245,158,11,0.35)') . '!important;',

        'assigned' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.25)' : 'rgba(59,130,246,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.5)' : 'rgba(59,130,246,0.35)') . '!important;',

        'en_route' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(99,102,241,0.25)' : 'rgba(99,102,241,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#a5b4fc' : '#4f46e5') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(99,102,241,0.5)' : 'rgba(99,102,241,0.35)') . '!important;',

        'arrived' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(168,85,247,0.25)' : 'rgba(168,85,247,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#c084fc' : '#7e22ce') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(168,85,247,0.5)' : 'rgba(168,85,247,0.35)') . '!important;',

        'in_progress' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(6,182,212,0.25)' : 'rgba(6,182,212,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#22d3ee' : '#0891b2') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(6,182,212,0.5)' : 'rgba(6,182,212,0.35)') . '!important;',

        'completed' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.25)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.5)' : 'rgba(34,197,94,0.35)') . '!important;',

        'cancelled' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.25)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.5)' : 'rgba(239,68,68,0.35)') . '!important;',

        'missed' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.25)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.5)' : 'rgba(239,68,68,0.35)') . '!important;',

        'unknown' => $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.22)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.4)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Priority text colors ----------
    $priorityTextStyles = [
        'emergency' => $isDark ? 'color:#f87171!important;font-weight:600;' : 'color:#dc2626!important;font-weight:600;',
        'high'      => $isDark ? 'color:#fb923c!important;font-weight:600;' : 'color:#ea580c!important;font-weight:600;',
        'medium'    => $isDark ? 'color:#fbbf24!important;font-weight:600;' : 'color:#b45309!important;font-weight:600;',
        'low'       => $isDark ? 'color:#cbd5e1!important;' : 'color:#6b7280!important;',
    ];

    // Shared resolver — normalize key and fallback to 'unknown'
    $resolveBadgeStyle = function (?string $key, array $map) use ($baseBadgeStyle, $isDark) {
        $normalized = is_string($key) ? strtolower(trim($key)) : '';
        $normalized = str_replace('-', '_', $normalized);

        if (isset($map[$normalized])) return $map[$normalized];
        if (isset($map['unknown']))    return $map['unknown'];

        return $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';
    };

    $resolveTimelineStyle = function (?string $key, array $map) use ($baseTimelineStyle, $isDark) {
        $normalized = is_string($key) ? strtolower(trim($key)) : '';
        $normalized = str_replace('-', '_', $normalized);

        if (isset($map[$normalized])) return $map[$normalized];
        if (isset($map['unknown']))    return $map['unknown'];

        return $baseTimelineStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.22)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.4)' : 'rgba(107,114,128,0.25)') . '!important;';
    };

    // -----------------------------------------------------------------
    // Resolve the current request's status + approval presentation
    // -----------------------------------------------------------------
    $statusKey     = $wasteCollectionRequest->status ?? '';
    $statusStyle   = $resolveBadgeStyle($statusKey, $statusStyles);
    $statusLabel   = match ($statusKey) {
        'pending'     => 'Pending',
        'assigned'    => 'Assigned',
        'en_route'    => 'En Route',
        'arrived'     => 'Arrived',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        'missed'      => 'Missed',
        ''            => 'Unknown',
        default       => ucfirst(str_replace('_', ' ', $statusKey)),
    };

    $approvalKey   = $wasteCollectionRequest->approval_status ?? '';
    $approvalStyle = $resolveBadgeStyle($approvalKey, $approvalStyles);
    $approvalLabel = match ($approvalKey) {
        'pending'       => 'Pending Approval',
        'approved'      => 'Approved',
        'rejected'      => 'Rejected',
        'expired'       => 'Expired',
        'auto_approved' => 'Auto-Approved',
        ''              => 'N/A',
        default         => ucfirst(str_replace('_', ' ', $approvalKey)),
    };

    $priorityKey   = $wasteCollectionRequest->priority ?? 'medium';
    $priorityStyle = $priorityTextStyles[$priorityKey] ?? $priorityTextStyles['low'];
    $priorityLabel = ucfirst($priorityKey ?: 'Medium');
@endphp

<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">

        <!-- Flash messages -->
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg" style="background-color: rgba(34,197,94,0.1); color: #16a34a; border: 1px solid #16a34a;">
                <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg" style="background-color: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid #dc2626;">
                <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Back Button -->
        <div class="mb-6">
            <a href="{{ url()->previous() }}" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <!-- Request Details -->
        <div class="card p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                        Collection Request #{{ $wasteCollectionRequest->id }}
                    </h1>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ $wasteCollectionRequest->property->property_name ?? 'Unknown Property' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    {{-- ✅ Status badge — inline style, theme-aware, cannot be hidden --}}
                    <span style="{{ $statusStyle }}" data-status="{{ $statusKey }}">
                        <i class="fas fa-circle mr-1" style="font-size:8px;"></i>
                        {{ $statusLabel }}
                    </span>

                    {{-- ✅ Approval badge — same guarantee --}}
                    <span style="{{ $approvalStyle }}" data-approval-status="{{ $approvalKey }}">
                        Approval: {{ $approvalLabel }}
                    </span>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h4 class="text-xs font-medium uppercase tracking-wider mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i> Property Details
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Property Name</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $wasteCollectionRequest->property->property_name ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Digital Address</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $wasteCollectionRequest->digital_address ?? $wasteCollectionRequest->property->digital_address ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Zone</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $wasteCollectionRequest->property->zone ?? 'Unassigned' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Landlord</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $wasteCollectionRequest->property->landlord->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-medium uppercase tracking-wider mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-clipboard-list mr-2"></i> Collection Details
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Waste Type</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ ucfirst($wasteCollectionRequest->waste_type ?? 'General') }}
                            </span>
                        </div>

                        {{-- ✅ Priority — inline text color, theme-aware --}}
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Priority</span>
                            <span class="text-sm" style="{{ $priorityStyle }}" data-priority="{{ $priorityKey }}">
                                {{ $priorityLabel }}
                            </span>
                        </div>

                        <div class="flex justify-between py-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Duration</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $wasteCollectionRequest->duration_formatted ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Request Status Timeline -->
            <div class="mb-6">
                <h4 class="text-xs font-medium uppercase tracking-wider mb-3" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-2"></i> Timeline
                </h4>
                <div class="relative">
                    @forelse($wasteCollectionRequest->getStatusHistory() ?? [] as $event)
                        @php
                            $eventStatus = $event['status'] ?? '';
                            $eventStyle  = $resolveTimelineStyle($eventStatus, $timelineStyles);

                            $eventIcon = match ($eventStatus) {
                                'assigned'    => 'fa-user-plus',
                                'en_route'    => 'fa-truck',
                                'arrived'     => 'fa-flag-checkered',
                                'in_progress' => 'fa-play',
                                'completed'   => 'fa-check',
                                'cancelled'   => 'fa-times',
                                'missed'      => 'fa-exclamation',
                                'pending'     => 'fa-hourglass-half',
                                default       => 'fa-circle',
                            };

                            $eventLabel = $event['label']
                                ?? ($eventStatus
                                    ? ucfirst(str_replace('_', ' ', $eventStatus))
                                    : 'Update');
                        @endphp
                        <div class="flex items-start mb-4 last:mb-0">
                            {{-- ✅ Timeline icon — inline style, theme-aware, cannot be hidden --}}
                            <div style="{{ $eventStyle }}" data-event-status="{{ $eventStatus }}">
                                <i class="fas {{ $eventIcon }}"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $eventLabel }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if(!empty($event['timestamp']))
                                        {{ \Carbon\Carbon::parse($event['timestamp'])->format('M d, Y g:i A') }}
                                        ({{ \Carbon\Carbon::parse($event['timestamp'])->diffForHumans() }})
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-secondary);">No timeline events yet.</p>
                    @endforelse
                </div>
            </div>

            <!-- Notes -->
            @if($wasteCollectionRequest->completion_notes || $wasteCollectionRequest->collection_notes)
                <div class="mb-6">
                    <h4 class="text-xs font-medium uppercase tracking-wider mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-sticky-note mr-2"></i> Notes
                    </h4>
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ $wasteCollectionRequest->completion_notes ?? $wasteCollectionRequest->collection_notes }}
                        </p>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            @if($wasteCollectionRequest->approval_status === 'pending')
                <div class="border-t pt-6" style="border-color: var(--border-color);">
                    <h4 class="text-sm font-medium mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-gavel mr-2"></i> Review Request
                    </h4>
                    <div class="flex flex-wrap gap-3">
                        <button type="button"
                                onclick="approveRequest('{{ $wasteCollectionRequest->id }}')"
                                class="btn-success">
                            <i class="fas fa-check mr-2"></i> Approve
                        </button>
                        <button type="button"
                                onclick="showRejectModal('{{ $wasteCollectionRequest->id }}')"
                                class="btn-danger">
                            <i class="fas fa-times mr-2"></i> Reject
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-w-lg"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-times-circle mr-2" style="color: #ef4444;"></i>
                Reject Request
            </h3>
            <button type="button" onclick="closeRejectModal()" class="p-1 rounded-full transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form id="rejectForm" method="POST">
            @csrf
            <div class="p-6">
                <p class="text-sm mb-4" style="color: var(--text-primary);">
                    Please provide a reason for rejecting this request:
                </p>
                <textarea name="rejection_reason" id="rejectionReason" rows="4" required
                          class="w-full px-4 py-3 rounded-lg focus:ring-2 transition-colors duration-200"
                          style="background-color: var(--bg-secondary);
                                 color: var(--text-primary);
                                 border: 1px solid var(--border-color);
                                 outline: none;"
                          placeholder="Enter rejection reason..."></textarea>
                <input type="hidden" name="request_id" id="rejectRequestId" value="{{ $wasteCollectionRequest->id }}">
            </div>
            <div class="px-6 py-4 border-t flex justify-end space-x-3"
                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 text-sm font-medium transition-colors duration-200 rounded-lg"
                        style="color: var(--text-secondary);">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: #ef4444; color: white;">
                    <i class="fas fa-times mr-2"></i> Reject
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>
    .btn-primary, .btn-secondary, .btn-info, .btn-warning, .btn-success, .btn-danger {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.5rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-secondary:hover {
        background-color: var(--bg-secondary);
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }
    .btn-success {
        background-color: #22c55e;
        color: white;
    }
    .btn-success:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-danger {
        background-color: #ef4444;
        color: white;
    }
    .btn-danger:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-warning {
        background-color: #f59e0b;
        color: white;
    }
    .btn-warning:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-info {
        background-color: var(--info);
        color: white;
    }
    .btn-info:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
</style>
@endpush

@push('scripts')
<script>
    // ✅ Route templates — placeholder replaced at runtime
    const approveRouteBase = "{{ route('landlord.waste.request.approve', ['wasteCollectionRequest' => '__ID__']) }}";
    const rejectRouteBase  = "{{ route('landlord.waste.request.reject',  ['wasteCollectionRequest' => '__ID__']) }}";

    function approveRequest(id) {
        if (!confirm('Are you sure you want to approve this request?')) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = approveRouteBase.replace('__ID__', id);
        form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
        document.body.appendChild(form);
        form.submit();
    }

    function showRejectModal(id) {
        const form = document.getElementById('rejectForm');
        form.action = rejectRouteBase.replace('__ID__', id);
        document.getElementById('rejectRequestId').value = id;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        const textarea = document.getElementById('rejectionReason');
        if (modal) modal.classList.add('hidden');
        if (textarea) textarea.value = '';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeRejectModal();
    });

    document.getElementById('rejectModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeRejectModal();
    });
</script>
@endpush