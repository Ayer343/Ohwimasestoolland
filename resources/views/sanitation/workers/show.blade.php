{{-- resources/views/sanitation/workers/show.blade.php --}}

@php
    // -----------------------------------------------------------------
    // Layout detection
    // -----------------------------------------------------------------
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // Theme detection — server-side mirror of the layout's client theme
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
    // Inline badge style builders — literal values, no CSS variables.
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

    // ---------- Worker status ----------
    $statusStyles = [
        'active' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'inactive' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',

        'on_leave' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'suspended' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Request status badges ----------
    $requestStatusStyles = [
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

    // ---------- Skill chips ----------
    $skillChipStyle = $baseBadgeStyle
        . 'padding:3px 10px;'
        . 'font-size:11px;'
        . 'border-radius:999px;'
        . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.3)') . '!important;';

    // ---------- Certification chips ----------
    $certChipStyle = $baseBadgeStyle
        . 'padding:3px 10px;'
        . 'font-size:11px;'
        . 'border-radius:999px;'
        . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
        . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;';

    // -----------------------------------------------------------------
    // Shared resolver — normalize key and fall back to 'unknown'
    // -----------------------------------------------------------------
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

    // Resolve once for the header worker-status badge
    $workerStatusKey   = $worker->status ?: 'unknown';
    $workerStatusStyle = $resolveBadgeStyle($workerStatusKey, $statusStyles);
    $workerStatusLabel = ucfirst(str_replace('_', ' ', $workerStatusKey));

    $workerSkills         = is_array($worker->skills) ? $worker->skills : [];
    $workerCertifications = is_array($worker->certifications) ? $worker->certifications : [];
@endphp

@extends($layout)

@section('title', $worker->full_name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex items-center space-x-4">
                @if($worker->profile_photo)
                    <img src="{{ Storage::url($worker->profile_photo) }}"
                         alt="{{ $worker->full_name }}"
                         class="w-16 h-16 rounded-full object-cover">
                @else
                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-semibold"
                         style="background: linear-gradient(135deg, var(--info), var(--primary));">
                        {{ strtoupper(substr($worker->first_name, 0, 1) . substr($worker->last_name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                        {{ $worker->full_name }}
                    </h1>
                    <div class="text-sm flex items-center gap-2 flex-wrap" style="color: var(--text-secondary);">
                        <span>Worker</span>

                        {{-- ✅ Status badge — inline style, theme-aware, cannot be hidden --}}
                        <span style="{{ $workerStatusStyle }}" data-worker-status="{{ $workerStatusKey }}">
                            {{ $workerStatusLabel }}
                        </span>

                        @if($worker->supervisor)
                            <span style="color: var(--text-secondary);">
                                • Supervisor: {{ $worker->supervisor->full_name }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('sanitation.workers.edit', $worker) }}" class="btn-primary">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
                <a href="{{ route('sanitation.workers.index') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Worker Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                    Personal Details
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Name</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $worker->full_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Phone</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $worker->phone }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Email</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $worker->email }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Address</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $worker->address }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Hire Date</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $worker->hire_date?->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Emergency Contact</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $worker->emergency_contact }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--info);"></i>
                    Supervisor
                </h3>
                @if($worker->supervisor)
                    <div class="flex items-center space-x-3 mb-4">
                        @if($worker->supervisor->profile_photo)
                            <img src="{{ Storage::url($worker->supervisor->profile_photo) }}"
                                 alt="{{ $worker->supervisor->full_name }}"
                                 class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                 style="background: linear-gradient(135deg, var(--primary), var(--info));">
                                {{ strtoupper(substr($worker->supervisor->first_name, 0, 1) . substr($worker->supervisor->last_name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ $worker->supervisor->full_name }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ ucfirst($worker->supervisor->role) }} • {{ $worker->supervisor->employee_id }}
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('sanitation.personnel.show', $worker->supervisor) }}"
                       class="btn-outline btn-sm w-full text-center">
                        <i class="fas fa-external-link-alt mr-2"></i> View Supervisor
                    </a>
                @else
                    <div class="text-center py-4" style="color: var(--text-secondary);">
                        <i class="fas fa-user-slash text-3xl mb-2 block"></i>
                        <p>No supervisor assigned</p>
                    </div>
                @endif
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-tools mr-2" style="color: var(--warning);"></i>
                    Skills &amp; Certifications
                </h3>
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Skills</h4>
                        @if(count($workerSkills) > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach($workerSkills as $skill)
                                    <span style="{{ $skillChipStyle }}" data-skill="{{ $skill }}">
                                        {{ $skill }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm" style="color: var(--text-secondary);">No skills listed</p>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Certifications</h4>
                        @if(count($workerCertifications) > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach($workerCertifications as $cert)
                                    <span style="{{ $certChipStyle }}" data-certification="{{ $cert }}">
                                        {{ $cert }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm" style="color: var(--text-secondary);">No certifications listed</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Requests -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                    Assigned Requests
                </h3>
                <span class="text-sm" style="color: var(--text-secondary);">
                    Recent {{ $assignedRequests->count() }} requests
                </span>
            </div>

            @if($assignedRequests->count() > 0)
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
                            @foreach($assignedRequests as $request)
                                @php
                                    $reqStatusKey   = $request->status ?: 'unknown';
                                    $reqStatusStyle = $resolveBadgeStyle($reqStatusKey, $requestStatusStyles);
                                    $reqStatusLabel = ucfirst(str_replace('_', ' ', $reqStatusKey));

                                    $reqPriorityKey   = $request->priority ?: 'low';
                                    $reqPriorityStyle = $resolveBadgeStyle($reqPriorityKey, $priorityStyles);
                                    $reqPriorityLabel = ucfirst($reqPriorityKey);
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm" style="color: var(--text-primary);">
                                        {{ $request->property->property_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span style="{{ $reqStatusStyle }}" data-request-status="{{ $reqStatusKey }}">
                                            {{ $reqStatusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span style="{{ $reqPriorityStyle }}" data-request-priority="{{ $reqPriorityKey }}">
                                            {{ $reqPriorityLabel }}
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
            @else
                <div class="text-center py-8" style="color: var(--text-secondary);">
                    <i class="fas fa-inbox text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                    <p>No requests assigned to this worker</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-outline {
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
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-outline {
        background-color: transparent;
        color: var(--primary);
        border: 1px solid var(--primary);
    }
    .btn-primary:hover {
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
    .btn-outline:hover {
        background-color: var(--primary);
        color: white;
        text-decoration: none;
    }
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
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
    /* Note: Status, priority, skill, and certification badges use       */
    /*       inline styles — no class rules here.                        */
    /* ----------------------------------------------------------------- */
</style>
@endpush