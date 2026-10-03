{{-- resources/views/sanitation/zones/show.blade.php --}}

@php
    use Illuminate\Support\Str;

    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // Detect current theme server-side so inline styles match.
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
    // These cannot be overridden by any stylesheet, theme switcher,
    // Tailwind Preflight, or parent class.
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

    // -----------------------------------------------------------------
    // Zone status badges (active / inactive)
    // -----------------------------------------------------------------
    $zoneActiveStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
        . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;';

    $zoneInactiveStyle = $baseBadgeStyle
        . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
        . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;';

    // -----------------------------------------------------------------
    // Property status badges
    // -----------------------------------------------------------------
    $propertyStatusStyles = [
        'active' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'inactive' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'pending' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'under_maintenance' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(168,85,247,0.22)' : 'rgba(168,85,247,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#c084fc' : '#7e22ce') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(168,85,247,0.45)' : 'rgba(168,85,247,0.35)') . '!important;',

        'vacant' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.35)') . '!important;',

        'under_construction' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(249,115,22,0.22)' : 'rgba(249,115,22,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fb923c' : '#ea580c') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(249,115,22,0.45)' : 'rgba(249,115,22,0.35)') . '!important;',

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // -----------------------------------------------------------------
    // Request status badges
    // -----------------------------------------------------------------
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
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(120,113,108,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#d6d3d1' : '#57534e') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(168,162,158,0.35)' : 'rgba(120,113,108,0.25)') . '!important;',
    ];

    // -----------------------------------------------------------------
    // Shared helper — resolve a style string for any status, with fallback.
    // -----------------------------------------------------------------
    $resolveStatusStyle = function (?string $status, array $map, ?string $fallbackKey = null) use ($baseBadgeStyle, $isDark) {
        $key = is_string($status) ? strtolower(trim($status)) : '';
        $key = str_replace('-', '_', $key);

        if (isset($map[$key])) {
            return $map[$key];
        }

        if ($fallbackKey !== null && isset($map[$fallbackKey])) {
            return $map[$fallbackKey];
        }

        // Final fallback — neutral grey
        return $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';
    };

    // Resolve the zone's own status style once
    $zoneBadgeStyle = $zone->is_active ? $zoneActiveStyle : $zoneInactiveStyle;
@endphp

@extends($layout)

@section('title', 'Zone Details: ' . $zone->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                    {{ $zone->name }}
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    @if($zone->code)
                        Code: <span class="font-medium" style="color: var(--text-primary);">{{ $zone->code }}</span> •
                    @endif
                    {{ $zone->description ?? 'No description' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.zones.edit', $zone) }}" class="btn-info">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
                <a href="{{ route('sanitation.zones.index') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Zone Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $stats['total_properties'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Properties</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['active_properties'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active Properties</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $stats['total_requests'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['pending_requests'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Pending Requests</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['completed_requests'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completed Requests</div>
            </div>
        </div>

        <!-- Zone Details -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Left Column -->
            <div class="card p-4">
                <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2"></i> Zone Information
                </h3>
                <div class="space-y-2">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Name</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $zone->name }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Code</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $zone->code ?? 'N/A' }}</span>
                    </div>

                    {{-- ✅ Zone status badge — inline style, cannot disappear --}}
                    <div class="flex justify-between py-2 border-b items-center" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Status</span>
                        <span style="{{ $zoneBadgeStyle }}" data-zone-status="{{ $zone->is_active ? 'active' : 'inactive' }}">
                            {{ $zone->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Assigned Personnel</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $zone->assignedPersonnel?->full_name ?? 'Unassigned' }}
                        </span>
                    </div>
                    @if($zone->region)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Region</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $zone->region }}</span>
                        </div>
                    @endif
                    @if($zone->district)
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">District</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $zone->district }}</span>
                        </div>
                    @endif
                    @if($zone->priority)
                        <div class="flex justify-between py-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Priority</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $zone->priority }}</span>
                        </div>
                    @endif
                </div>

                <!-- Assign Personnel Form -->
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>
                        Assign Personnel
                    </h4>
                    <form action="{{ route('sanitation.zones.assign-personnel', $zone) }}" method="POST" class="flex gap-2">
                        @csrf
                        <select name="personnel_id" class="flex-1 p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">Select Personnel</option>
                            @foreach($personnel ?? [] as $person)
                                <option value="{{ $person->id }}" {{ $zone->assigned_personnel_id == $person->id ? 'selected' : '' }}>
                                    {{ $person->full_name }} ({{ $person->role }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                        </button>
                    </form>
                    @if($zone->assigned_personnel_id)
                        <form action="{{ route('sanitation.zones.unassign-personnel', $zone) }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="text-sm text-red-500 hover:text-red-700 transition-colors duration-200">
                                <i class="fas fa-user-times mr-1"></i> Unassign Personnel
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Right Columns -->
            <div class="md:col-span-2">
                <!-- Properties in Zone -->
                <div class="card p-4 mb-4">
                    <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i> Properties in Zone ({{ $properties->total() }})
                    </h3>
                    @if($properties->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead style="background-color: var(--bg-secondary);">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Name</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Address</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($properties as $property)
                                        @php
                                            $propStatusRaw = $property->status;
                                            if ($propStatusRaw === null || $propStatusRaw === '' || $propStatusRaw === 'unknown') {
                                                $propStatusRaw = 'unknown';
                                            }
                                            $propStatusKey   = str_replace('-', '_', strtolower($propStatusRaw));
                                            $propStatusLabel = ucfirst(str_replace('_', ' ', $propStatusRaw));
                                            $propBadgeStyle  = $resolveStatusStyle($propStatusKey, $propertyStatusStyles, 'unknown');
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2" style="color: var(--text-primary);">{{ $property->property_name }}</td>
                                            <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $property->digital_address ?? 'N/A' }}</td>
                                            <td class="px-3 py-2">
                                                <span style="{{ $propBadgeStyle }}" data-property-status="{{ $propStatusRaw }}">
                                                    {{ $propStatusLabel }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($properties->hasPages())
                            <div class="mt-3">
                                {{ $properties->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <p class="text-sm" style="color: var(--text-secondary);">No properties in this zone.</p>
                    @endif
                </div>

                <!-- Waste Collection Requests -->
                <div class="card p-4">
                    <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-trash-alt mr-2"></i> Waste Collection Requests ({{ $requests->total() }})
                    </h3>
                    @if($requests->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead style="background-color: var(--bg-secondary);">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $request)
                                        @php
                                            $reqStatusRaw = $request->status;
                                            if ($reqStatusRaw === null || $reqStatusRaw === '') {
                                                $reqStatusRaw = 'unknown';
                                            }
                                            $reqStatusKey   = str_replace('-', '_', strtolower($reqStatusRaw));
                                            $reqStatusLabel = $request->status_label
                                                ?? ucfirst(str_replace('_', ' ', $reqStatusRaw));
                                            $reqBadgeStyle  = $resolveStatusStyle($reqStatusKey, $requestStatusStyles);
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2" style="color: var(--text-primary);">#{{ $request->id }}</td>
                                            <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $request->property->property_name ?? 'N/A' }}</td>
                                            <td class="px-3 py-2">
                                                <span style="{{ $reqBadgeStyle }}" data-request-status="{{ $reqStatusRaw }}">
                                                    {{ $reqStatusLabel }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-xs" style="color: var(--text-secondary);">{{ $request->created_at->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($requests->hasPages())
                            <div class="mt-3">
                                {{ $requests->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <p class="text-sm" style="color: var(--text-secondary);">No waste collection requests in this zone.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Notes -->
        @if($zone->notes)
            <div class="card p-4">
                <h3 class="font-semibold text-sm mb-2" style="color: var(--text-secondary);">
                    <i class="fas fa-sticky-note mr-2"></i> Notes
                </h3>
                <p class="text-sm" style="color: var(--text-primary);">{{ $zone->notes }}</p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-info {
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
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-info:hover {
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