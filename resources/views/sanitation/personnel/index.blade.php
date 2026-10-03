{{-- resources/views/sanitation/personnel/index.blade.php --}}

@php
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // Current user's own personnel record (if they are sanitation personnel)
    $currentPersonnel   = $user->sanitationPersonnel;
    $currentPersonnelId = $currentPersonnel?->id;

    // Trash count for the header badge
    $trashedPersonnelCount = \App\Models\SanitationPersonnel::onlyTrashed()->count();

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

    // ---------- Personnel status ----------
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

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Role badges ----------
    $roleSupervisorStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(168,85,247,0.22)' : 'rgba(168,85,247,0.15)') . '!important;'
        . 'color:' . ($isDark ? '#c084fc' : '#7e22ce') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(168,85,247,0.45)' : 'rgba(168,85,247,0.35)') . '!important;';

    $roleDriverStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(249,115,22,0.22)' : 'rgba(249,115,22,0.15)') . '!important;'
        . 'color:' . ($isDark ? '#fb923c' : '#ea580c') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(249,115,22,0.45)' : 'rgba(249,115,22,0.35)') . '!important;';

    $roleWorkerStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.15)') . '!important;'
        . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.35)') . '!important;';

    // ---------- Small hierarchy chips (You, Root, Level, Team size) ----------
    $chipBaseStyle = 'display:inline-flex!important;'
        . 'align-items:center;'
        . 'justify-content:center;'
        . 'padding:1px 6px;'
        . 'border-radius:4px;'
        . 'font-size:10px;'
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

    // ---------- "Top of tree" badge ----------
    $topOfTreeStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.2)' : 'rgba(34,197,94,0.1)') . '!important;'
        . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.4)' : 'rgba(34,197,94,0.3)') . '!important;';

    // ---------- "Invitation pending" chip ----------
    $chipInviteStyle = $chipBaseStyle
        . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.28)' : 'rgba(245,158,11,0.18)') . '!important;'
        . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.5)' : 'rgba(245,158,11,0.35)') . '!important;';

    // Shared helper — resolve status style with fallback
    $resolveStatusStyle = function (?string $status, array $map) use ($baseBadgeStyle, $isDark) {
        $key = is_string($status) ? strtolower(trim($status)) : '';
        $key = str_replace('-', '_', $key);

        if (isset($map[$key])) {
            return $map[$key];
        }

        return $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';
    };
@endphp

@extends($layout)

@section('title', 'Sanitation Personnel')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        {{-- ============================================================ --}}
        {{-- ✅ FLASH MESSAGES — mirrors admin create/sanitation create --}}
        {{-- ============================================================ --}}
        @if(session('success'))
            <div class="card p-0 overflow-hidden border-l-4 mb-6" style="border-left-color: var(--success);">
                <div class="p-4" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3" style="color: var(--success);"></i>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--success);">Success</h3>
                                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>

                                @if(session('invitation_result') && (session('invitation_result')['success'] ?? false))
                                    <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <div class="flex items-start">
                                            <i class="fas fa-paper-plane mt-0.5 mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <p class="text-sm font-medium" style="color: var(--text-primary);">Invitation Status:</p>
                                                <p class="text-sm" style="color: var(--text-secondary);">
                                                    Invitation sent successfully via
                                                    {{ implode(', ', session('invitation_result')['channels_successful'] ?? []) }}
                                                    @if(!empty(session('invitation_result')['failed_channels']))
                                                        <br><span class="text-yellow-600">
                                                            ⚠️ Failed channels:
                                                            {{ implode(', ', session('invitation_result')['failed_channels']) }}
                                                        </span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <button type="button"
                                onclick="this.closest('.card').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="card p-0 overflow-hidden border-l-4 mb-6" style="border-left-color: var(--danger);">
                <div class="p-4" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-2xl mr-3" style="color: var(--danger);"></i>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--danger);">Error</h3>
                                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
                            </div>
                        </div>
                        <button type="button"
                                onclick="this.closest('.card').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                    Sanitation Personnel
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage all sanitation personnel and their assignments
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.personnel.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Add Personnel
                </a>

                <a href="{{ route('sanitation.workers.index') }}" class="btn-info">
                    <i class="fas fa-user-hard-hat mr-2"></i> Workers
                </a>

                {{-- 🗑️ Trash button with live count badge --}}
                <a href="{{ route('sanitation.personnel.trash') }}"
                   class="btn-secondary flex items-center relative"
                   title="View deleted personnel">
                    <i class="fas fa-trash-alt mr-2"></i> Trash
                    @if($trashedPersonnelCount > 0)
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-500 text-white font-semibold">
                            {{ $trashedPersonnelCount }}
                        </span>
                    @endif
                </a>

                @if($isAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                @else
                    <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                @endif
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $stats['total'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['active'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['on_leave'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">On Leave</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $stats['supervisors'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Supervisors</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-purple-500">{{ $stats['workers'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Workers</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-orange-500">{{ $stats['drivers'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Drivers</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}"
                           autocomplete="off"
                           class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Name, phone, employee ID...">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Role</label>
                    <select name="role" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>
                                {{ ucfirst($role) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.personnel.index') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Personnel Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Reports To</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Stats</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personnel as $person)
                            @php
                                // -------- Deletion rules (aligned with PersonnelController@destroy) --------
                                $isSelf              = $currentPersonnelId && $person->id === $currentPersonnelId;
                                $subordinateCount    = $person->subordinates()->count();
                                $activeRequestsCount = $person->activeRequests()->count();
                                $hasLegacyWorkers    = $person->isSupervisor() && $person->workers()->count() > 0;

                                $canDelete = !$isSelf
                                    && $subordinateCount === 0
                                    && $activeRequestsCount === 0
                                    && !$hasLegacyWorkers;

                                $deleteReason = null;
                                if ($isSelf) {
                                    $deleteReason = 'You cannot delete your own account.';
                                } elseif ($subordinateCount > 0) {
                                    $deleteReason = "Cannot delete personnel with {$subordinateCount} subordinate(s). Reassign them first.";
                                } elseif ($activeRequestsCount > 0) {
                                    $deleteReason = "Cannot delete personnel with {$activeRequestsCount} active request(s).";
                                } elseif ($hasLegacyWorkers) {
                                    $deleteReason = 'Cannot delete supervisor with assigned workers.';
                                }

                                // -------- Hierarchy info --------
                                $depth    = $person->chain_depth;
                                $isRoot   = $person->isRootSupervisor();
                                $teamSize = $person->isSupervisor() ? $subordinateCount : 0;

                                // -------- Invitation pending indicator --------
                                $linkedUser         = $person->user;
                                $invitePending      = $linkedUser
                                    && !empty($linkedUser->invitation_sent_at)
                                    && empty($linkedUser->email_verified_at)
                                    && ($linkedUser->status ?? null) === \App\Models\User::STATUS_PENDING;

                                // -------- Badge styles --------
                                $isSupervisorRole = in_array($person->role, \App\Models\SanitationPersonnel::SUPERVISOR_ROLES);
                                if ($isSupervisorRole) {
                                    $roleBadgeStyle = $roleSupervisorStyle;
                                } elseif ($person->role === 'driver') {
                                    $roleBadgeStyle = $roleDriverStyle;
                                } else {
                                    $roleBadgeStyle = $roleWorkerStyle;
                                }

                                $statusBadgeStyle = $resolveStatusStyle($person->status, $statusStyles);
                            @endphp
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-3">
                                        @if($person->profile_photo)
                                            <img src="{{ Storage::url($person->profile_photo) }}"
                                                 alt="{{ $person->full_name }}"
                                                 class="w-10 h-10 rounded-full object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                                 style="background: linear-gradient(135deg, var(--primary), var(--info));">
                                                {{ strtoupper(substr($person->first_name, 0, 1) . substr($person->last_name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium flex items-center gap-2 flex-wrap" style="color: var(--text-primary);">
                                                {{ $person->full_name }}

                                                @if($isSelf)
                                                    <span style="{{ $chipYouStyle }}" data-chip="you">
                                                        You
                                                    </span>
                                                @endif

                                                @if($isRoot)
                                                    <span style="{{ $chipRootStyle }}" data-chip="root">
                                                        <i class="fas fa-crown mr-1"></i>Root
                                                    </span>
                                                @elseif($depth > 0)
                                                    <span style="{{ $chipLevelStyle }}" data-chip="level">
                                                        Level {{ $depth }}
                                                    </span>
                                                @endif

                                                @if($teamSize > 0)
                                                    <span style="{{ $chipTeamStyle }}"
                                                          data-chip="team"
                                                          title="{{ $teamSize }} direct report(s)">
                                                        <i class="fas fa-users mr-1"></i>{{ $teamSize }}
                                                    </span>
                                                @endif

                                                {{-- ✅ Invitation pending chip --}}
                                                @if($invitePending)
                                                    <span style="{{ $chipInviteStyle }}"
                                                          data-chip="invite-pending"
                                                          title="Invitation sent {{ $linkedUser->invitation_sent_at->diffForHumans() }} — awaiting acceptance">
                                                        <i class="fas fa-hourglass-half mr-1"></i>Invite pending
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                ID: {{ $person->employee_id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $person->phone }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $person->email }}</div>
                                </td>

                                {{-- Role badge --}}
                                <td class="px-4 py-3">
                                    <span style="{{ $roleBadgeStyle }}" data-role="{{ $person->role }}">
                                        {{ ucfirst($person->role) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    @if($person->supervisor)
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            <i class="fas fa-user-tie mr-1" style="color: var(--primary);"></i>
                                            {{ $person->supervisor->full_name }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ ucfirst($person->supervisor->role) }}
                                        </div>
                                    @else
                                        <span style="{{ $topOfTreeStyle }}" data-hierarchy="root">
                                            <i class="fas fa-crown mr-1"></i>Top of tree
                                        </span>
                                    @endif
                                </td>

                                {{-- Status badge --}}
                                <td class="px-4 py-3">
                                    <span style="{{ $statusBadgeStyle }}" data-status="{{ $person->status }}">
                                        {{ ucfirst($person->status) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $person->active_jobs_count }} active
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $person->completed_jobs_today }} today
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2 items-center">
                                        {{-- View --}}
                                        <a href="{{ route('sanitation.personnel.show', $person) }}"
                                           class="text-sm hover:underline" style="color: var(--primary);"
                                           title="View details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        {{-- Edit (self-edit blocked) --}}
                                        @if($isSelf)
                                            <button type="button"
                                                    disabled
                                                    class="text-sm cursor-not-allowed"
                                                    style="color: var(--text-secondary); opacity: 0.4;"
                                                    title="You cannot edit your own account here. Use your profile settings instead.">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        @else
                                            <a href="{{ route('sanitation.personnel.edit', $person) }}"
                                               class="text-sm hover:underline" style="color: var(--info);"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif

                                        {{-- Delete (already gated on $canDelete, which includes !$isSelf) --}}
                                        @if($canDelete)
                                            <button type="button"
                                                    onclick="deletePersonnel({{ $person->id }}, {{ json_encode($person->full_name) }})"
                                                    class="text-sm hover:underline"
                                                    style="color: var(--danger);"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @else
                                            <button type="button"
                                                    disabled
                                                    class="text-sm cursor-not-allowed"
                                                    style="color: var(--text-secondary); opacity: 0.4;"
                                                    title="{{ $deleteReason }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-users text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No personnel found</p>
                                    <a href="{{ route('sanitation.personnel.create') }}" class="btn-primary mt-3 inline-block">
                                        <i class="fas fa-plus mr-2"></i> Add First Personnel
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($personnel->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $personnel->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
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
                    <strong id="deletePersonnelName" style="color: var(--text-primary);"></strong>.
                    This action cannot be undone.
                </p>
            </div>
        </div>

        <form id="deleteForm" method="POST">
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
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-info, .btn-danger {
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
    .btn-info    { background-color: var(--info);    color: white; }
    .btn-danger  { background-color: #ef4444;        color: white; }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-info:hover, .btn-danger:hover {
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
    /* Note: Status badges use inline styles — no class rules here.      */
    /* ----------------------------------------------------------------- */
</style>
@endpush

@push('scripts')
<script>
    // Route template with placeholder — populated at runtime
    const deleteRouteTemplate = "{{ route('sanitation.personnel.destroy', ['personnel' => '__ID__']) }}";

    function deletePersonnel(id, name) {
        const modal   = document.getElementById('deleteModal');
        const form    = document.getElementById('deleteForm');
        const nameEl  = document.getElementById('deletePersonnelName');

        form.action = deleteRouteTemplate.replace('__ID__', id);
        if (nameEl) nameEl.textContent = name || 'this personnel';

        modal.classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modal on Escape
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeDeleteModal();
    });

    // Close modal when clicking on the backdrop
    document.getElementById('deleteModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeDeleteModal();
    });
</script>
@endpush