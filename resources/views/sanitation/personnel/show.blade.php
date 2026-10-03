{{-- resources/views/sanitation/personnel/show.blade.php --}}

@php
    use Illuminate\Support\Str;

    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // Current user's personnel record (if any)
    $currentPersonnel = $user->sanitationPersonnel;
    $isSelf           = $currentPersonnel && $currentPersonnel->id === $personnel->id;

    // Hierarchy flags
    $isRoot       = $personnel->isRootSupervisor();
    $depth        = $personnel->chain_depth;
    $ancestors    = $personnel->ancestors()->reverse(); // top → nearest
    $subordinates = $personnel->subordinates()->with(['user'])->orderBy('first_name')->get();
    $teamSize     = $subordinates->count();

    // Delete permission
    $hasActiveRequests = $personnel->activeRequests()->count() > 0;
    $hasSubordinates   = $teamSize > 0;
    $hasLegacyWorkers  = $personnel->isSupervisor() && $personnel->workers()->count() > 0;
    $canDelete         = !$isSelf && !$hasActiveRequests && !$hasSubordinates && !$hasLegacyWorkers;

    // -----------------------------------------------------------------
    // Detect theme server-side so inline styles match on first paint.
    // -----------------------------------------------------------------
    $currentTheme = null;

    if ($user && method_exists($user, 'getThemePreference')) {
        $currentTheme = $user->getThemePreference();
    }

    if (!$currentTheme) {
        $currentTheme = request()->cookie('theme')
            ?? session('theme')
            ?? 'light';
    }

    $isDark = $currentTheme === 'dark';

    // -----------------------------------------------------------------
    // Inline style builders — literal values, no CSS variables.
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

    // ---------- Status badges ----------
    $statusStyles = [
        'active' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'on_leave' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'suspended' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'inactive' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(107,114,128,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',

        'completed' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'pending' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'cancelled' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

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

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Priority badges ----------
    $priorityStyles = [
        'emergency' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.25)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fca5a5' : '#b91c1c') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.5)' : 'rgba(239,68,68,0.35)') . '!important;',

        'high' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(249,115,22,0.22)' : 'rgba(249,115,22,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fb923c' : '#c2410c') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(249,115,22,0.45)' : 'rgba(249,115,22,0.35)') . '!important;',

        'medium' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'low' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.35)') . '!important;',
    ];

    // ---------- Small chips in header (You / Root / Level / Team size) ----------
    $chipBaseStyle = 'display:inline-flex!important;'
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

    $chipYouStyle = $chipBaseStyle
        . 'background-color:' . ($isDark ? 'rgba(99,102,241,0.25)' : 'rgba(99,102,241,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#a5b4fc' : '#4f46e5') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(99,102,241,0.45)' : 'rgba(99,102,241,0.3)') . '!important;';

    $chipRootStyle = $chipBaseStyle
        . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.28)' : 'rgba(245,158,11,0.18)') . '!important;'
        . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.5)' : 'rgba(245,158,11,0.35)') . '!important;';

    $chipLevelStyle = $chipBaseStyle
        . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';

    $chipTeamStyle = $chipBaseStyle
        . 'background-color:' . ($isDark ? 'rgba(6,182,212,0.22)' : 'rgba(6,182,212,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#22d3ee' : '#0891b2') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(6,182,212,0.45)' : 'rgba(6,182,212,0.3)') . '!important;';

    $chipSupervisorStyle = $chipBaseStyle
        . 'padding:1px 6px;'
        . 'font-size:10px;'
        . 'background-color:' . ($isDark ? 'rgba(6,182,212,0.22)' : 'rgba(6,182,212,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#22d3ee' : '#0891b2') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(6,182,212,0.45)' : 'rgba(6,182,212,0.3)') . '!important;';

    // ---------- Root badge in Reporting Chain card ----------
    $rootBadgeStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.2)' : 'rgba(245,158,11,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.3)') . '!important;';

    // Shared helper — resolve a status/priority style with fallback
    $resolveBadgeStyle = function (?string $key, array $map) use ($baseBadgeStyle, $isDark) {
        $normalized = is_string($key) ? strtolower(trim($key)) : '';
        $normalized = str_replace('-', '_', $normalized);

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';
    };

    // Resolve once for the header employee-status badge
    $personnelStatusStyle = $resolveBadgeStyle($personnel->status, $statusStyles);
@endphp

@extends($layout)

@section('title', $personnel->full_name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex items-center space-x-4">
                @if($personnel->profile_photo)
                    <img src="{{ Storage::url($personnel->profile_photo) }}"
                         alt="{{ $personnel->full_name }}"
                         class="w-16 h-16 rounded-full object-cover">
                @else
                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-semibold"
                         style="background: linear-gradient(135deg, var(--primary), var(--info));">
                        {{ strtoupper(substr($personnel->first_name, 0, 1) . substr($personnel->last_name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-3xl font-bold flex items-center gap-3 flex-wrap" style="color: var(--text-primary);">
                        {{ $personnel->full_name }}

                        @if($isSelf)
                            <span style="{{ $chipYouStyle }}" data-chip="you">You</span>
                        @endif

                        @if($isRoot)
                            <span style="{{ $chipRootStyle }}" data-chip="root">
                                <i class="fas fa-crown mr-1"></i>Root Supervisor
                            </span>
                        @elseif($depth > 0)
                            <span style="{{ $chipLevelStyle }}" data-chip="level">
                                Level {{ $depth }}
                            </span>
                        @endif

                        @if($teamSize > 0)
                            <span style="{{ $chipTeamStyle }}" data-chip="team">
                                <i class="fas fa-users mr-1"></i>{{ $teamSize }} report{{ $teamSize === 1 ? '' : 's' }}
                            </span>
                        @endif
                    </h1>

                    <div class="text-sm mt-1 flex items-center gap-2 flex-wrap" style="color: var(--text-secondary);">
                        <span>{{ $personnel->employee_id }} • {{ ucfirst($personnel->role) }}</span>
                        <span style="{{ $personnelStatusStyle }}" data-status="{{ $personnel->status }}">
                            {{ ucfirst($personnel->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
    {{-- Edit (self-edit blocked for non-admins) --}}
    @if($isSelf && !$isAdmin)
        <button type="button"
                class="btn-primary"
                disabled
                style="opacity: 0.5; cursor: not-allowed;"
                title="You cannot edit your own personnel record here. Please use your profile settings.">
            <i class="fas fa-edit mr-2"></i> Edit
        </button>
    @else
        <a href="{{ route('sanitation.personnel.edit', ['personnel' => $personnel->id]) }}" class="btn-primary">
            <i class="fas fa-edit mr-2"></i> Edit
        </a>
    @endif

   {{-- Delete (already gated on $canDelete, which includes !$isSelf) --}}
@if($canDelete)
    <button type="button"
            onclick="openDeleteModal()"
            class="btn-danger">
        <i class="fas fa-trash mr-2"></i> Delete
    </button>
@else
    <button type="button"
            class="btn-danger"
            disabled
            style="opacity: 0.5; cursor: not-allowed;"
            title="@if($isSelf) You cannot delete your own personnel record.
                   @elseif($hasSubordinates) Reassign {{ $teamSize }} subordinate(s) first.
                   @elseif($hasActiveRequests) Complete active requests first.
                   @elseif($hasLegacyWorkers) Reassign workers first.
                   @else Cannot delete this personnel record right now.
                   @endif">
        <i class="fas fa-trash mr-2"></i> Delete
    </button>
@endif

<a href="{{ route('sanitation.personnel.index') }}" class="btn-secondary">
    <i class="fas fa-arrow-left mr-2"></i> Back
</a>
</div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $stats['total_requests'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['completed'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completed</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['active'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $stats['completion_rate'] }}%</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completion Rate</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-purple-500">{{ number_format($stats['total_weight'], 2) }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Waste (kg)</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-indigo-500">{{ $teamSize }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Team Size</div>
            </div>
        </div>

        <!-- Personnel Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                    Personal Details
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Name</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $personnel->full_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Phone</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $personnel->phone ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Email</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $personnel->email ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Address</span>
                        <span class="text-sm font-medium text-right" style="color: var(--text-primary);">{{ $personnel->address ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Hire Date</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ optional($personnel->hire_date)->format('M d, Y') ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Shift</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $personnel->shift_preference ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-truck mr-2" style="color: var(--info);"></i>
                    Vehicle Information
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Vehicle Number</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $personnel->vehicle_number ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Vehicle Type</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $personnel->vehicle_type ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Emergency Contact</span>
                        <span class="text-sm font-medium text-right" style="color: var(--text-primary);">{{ $personnel->emergency_contact ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--warning);"></i>
                    Performance
                </h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-secondary);">Completion Rate</span>
                            <span style="color: var(--text-primary);">{{ $stats['completion_rate'] }}%</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 style="width: {{ $stats['completion_rate'] }}%; background: linear-gradient(to right, var(--primary), var(--success));"></div>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Avg Completion Time</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $stats['avg_completion_time'] ? number_format($stats['avg_completion_time'], 0) . ' min' : 'N/A' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Total Waste Collected</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ number_format($stats['total_weight'], 2) }} kg
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reporting Structure -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Supervisor Chain (Ancestors) -->
            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-sitemap mr-2" style="color: var(--primary);"></i>
                    Reporting Chain
                </h3>

                @if($ancestors->isEmpty() && !$personnel->supervisor)
                    <div class="p-4 rounded-lg text-center"
                         style="background-color: rgba(245,158,11,0.08); border-left: 3px solid #f59e0b;">
                        <i class="fas fa-crown text-2xl mb-2" style="color: #f59e0b;"></i>
                        <p class="font-medium" style="color: var(--text-primary);">Root Supervisor</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            This personnel is at the top of the hierarchy — no one reports above them.
                        </p>
                    </div>
                @else
                    <div class="space-y-3">
                        {{-- Breadcrumb walk from root to this person --}}
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            @foreach($ancestors as $ancestor)
                                <a href="{{ route('sanitation.personnel.show', ['personnel' => $ancestor->id]) }}"
                                   class="px-3 py-1.5 rounded-lg transition-colors hover:opacity-80"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    @if($ancestor->isRootSupervisor())
                                        <i class="fas fa-crown mr-1" style="color: #f59e0b;"></i>
                                    @else
                                        <i class="fas fa-user-tie mr-1" style="color: var(--primary);"></i>
                                    @endif
                                    {{ $ancestor->full_name }}
                                </a>
                                <i class="fas fa-chevron-right text-xs" style="color: var(--text-secondary);"></i>
                            @endforeach

                            {{-- Current person (highlighted) --}}
                            <span class="px-3 py-1.5 rounded-lg font-medium"
                                  style="background-color: {{ $isDark ? 'rgba(99,102,241,0.25)' : 'rgba(99,102,241,0.12)' }} !important;
                                         color: {{ $isDark ? '#a5b4fc' : '#4f46e5' }} !important;
                                         border: 1px solid {{ $isDark ? 'rgba(99,102,241,0.45)' : 'rgba(99,102,241,0.35)' }} !important;">
                                <i class="fas fa-user mr-1"></i>{{ $personnel->full_name }}
                            </span>
                        </div>

                        {{-- Immediate supervisor detail --}}
                        @if($personnel->supervisor)
                            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                                <p class="text-xs mb-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Directly reports to
                                </p>
                                <a href="{{ route('sanitation.personnel.show', ['personnel' => $personnel->supervisor->id]) }}"
                                   class="flex items-center gap-3 p-3 rounded-lg transition-colors hover:opacity-80"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                         style="background: linear-gradient(135deg, var(--primary), var(--info));">
                                        {{ strtoupper(substr($personnel->supervisor->first_name, 0, 1) . substr($personnel->supervisor->last_name, 0, 1)) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $personnel->supervisor->full_name }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ ucfirst($personnel->supervisor->role) }} • {{ $personnel->supervisor->employee_id }}
                                        </div>
                                    </div>
                                    <i class="fas fa-arrow-right text-xs" style="color: var(--text-secondary);"></i>
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Direct Subordinates (Team) -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--info);"></i>
                        Direct Reports
                    </h3>
                    @if($teamSize > 0)
                        <span style="{{ $chipTeamStyle }}" data-chip="team-count">
                            {{ $teamSize }}
                        </span>
                    @endif
                </div>

                @if($subordinates->isEmpty())
                    <div class="text-center py-6" style="color: var(--text-secondary);">
                        <i class="fas fa-user-slash text-3xl mb-3 block" style="opacity: 0.3;"></i>
                        <p class="text-sm">No direct reports</p>
                        @if($personnel->isSupervisor())
                            <a href="{{ route('sanitation.personnel.create') }}"
                               class="btn-primary mt-3 inline-flex items-center text-xs">
                                <i class="fas fa-plus mr-1"></i> Add Personnel
                            </a>
                        @endif
                    </div>
                @else
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach($subordinates as $subordinate)
                            @php
                                $subStatusStyle = $resolveBadgeStyle($subordinate->status, $statusStyles);
                            @endphp
                            <a href="{{ route('sanitation.personnel.show', ['personnel' => $subordinate->id]) }}"
                               class="flex items-center gap-3 p-3 rounded-lg transition-colors hover:opacity-80"
                               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                @if($subordinate->profile_photo)
                                    <img src="{{ Storage::url($subordinate->profile_photo) }}"
                                         alt="{{ $subordinate->full_name }}"
                                         class="w-9 h-9 rounded-full object-cover">
                                @else
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                                         style="background: linear-gradient(135deg, var(--primary), var(--info));">
                                        {{ strtoupper(substr($subordinate->first_name, 0, 1) . substr($subordinate->last_name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-sm truncate" style="color: var(--text-primary);">
                                        {{ $subordinate->full_name }}
                                    </div>
                                    <div class="text-xs flex items-center gap-2 flex-wrap" style="color: var(--text-secondary);">
                                        <span>{{ ucfirst($subordinate->role) }}</span>
                                        @if($subordinate->isSupervisor())
                                            <span style="{{ $chipSupervisorStyle }}" data-chip="supervisor">
                                                Supervisor
                                            </span>
                                        @endif
                                        <span>•</span>
                                        <span>{{ $subordinate->active_jobs_count }} active</span>
                                    </div>
                                </div>
                                <span style="{{ $subStatusStyle }}" data-status="{{ $subordinate->status }}">
                                    {{ ucfirst($subordinate->status) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                    Recent Requests
                </h3>
                <span class="text-sm" style="color: var(--text-secondary);">
                    Total: {{ $stats['total_requests'] }}
                </span>
            </div>

            @if($requests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead style="background-color: var(--bg-secondary);">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Property</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Priority</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $request)
                                @php
                                    $reqStatusStyle   = $resolveBadgeStyle($request->status, $statusStyles);
                                    $reqPriorityStyle = $resolveBadgeStyle($request->priority, $priorityStyles);
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm" style="color: var(--text-primary);">
                                        {{ $request->property->property_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span style="{{ $reqStatusStyle }}" data-status="{{ $request->status }}">
                                            {{ ucfirst($request->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span style="{{ $reqPriorityStyle }}" data-priority="{{ $request->priority }}">
                                            {{ ucfirst($request->priority) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm" style="color: var(--text-secondary);">
                                        {{ $request->created_at->format('M d, Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($requests->hasPages())
                    <div class="mt-4">
                        {{ $requests->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-8" style="color: var(--text-secondary);">
                    <i class="fas fa-inbox text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                    <p>No requests assigned</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal (rendered only when allowed) -->
@if($canDelete)
<div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeDeleteModal()"></div>
    <div class="relative rounded-xl shadow-2xl max-w-md w-full mx-4 p-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background-color: rgba(239, 68, 68, 0.15);">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    Delete Personnel?
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    You are about to delete
                    <strong style="color: var(--text-primary);">{{ $personnel->full_name }}</strong>.
                    This action cannot be undone.
                </p>
            </div>
        </div>

        <form action="{{ route('sanitation.personnel.destroy', ['personnel' => $personnel->id]) }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex justify-end space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="closeDeleteModal()" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-danger">
                    <i class="fas fa-trash mr-2"></i>Delete
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-danger {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-danger  { background-color: #ef4444;        color: white; }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-danger:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary:hover {
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }

    /* ----------------------------------------------------------------- */
    /* Cards                                                             */
    /* ----------------------------------------------------------------- */
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    /* ----------------------------------------------------------------- */
    /* Note: Status/priority badges and chips use inline styles.         */
    /* ----------------------------------------------------------------- */
</style>
@endpush

@push('scripts')
<script>
    function openDeleteModal() {
        const modal = document.getElementById('deleteModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        if (modal) modal.classList.add('hidden');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeDeleteModal();
    });

    document.getElementById('deleteModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeDeleteModal();
    });
</script>
@endpush