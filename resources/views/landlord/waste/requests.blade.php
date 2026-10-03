{{-- resources/views/landlord/waste/requests.blade.php --}}

@extends('layouts.landlord')

@section('title', 'My Collection Requests')

@section('content')
@php
    // -----------------------------------------------------------------
    // Existing view-model prep
    // -----------------------------------------------------------------
    $linkedProperties    = $myProperties ?? collect();
    $hasLinkedProperties = $linkedProperties->isNotEmpty();

    $unlinkedProperties    = $unlinkedProperties ?? collect();
    $hasUnlinkedProperties = $unlinkedProperties->isNotEmpty();

    $hasSupervisors = (bool) ($hasSanitationSupervisors ?? false);

    $pendingServiceRequests = $pendingServiceRequests ?? collect();
    $pendingServiceCount    = $pendingServiceRequests->count();

    // -----------------------------------------------------------------
    // Sanitation settings
    // -----------------------------------------------------------------
    $settings = $sanitationSettings ?? null;

    $availableFrequencies = $settings?->getEffectiveCollectionFrequencies()
        ?? ['daily', 'weekly', 'biweekly', 'monthly'];

    $frequencyPricing = $settings?->getResolvedFrequencyPricing()
        ?? \App\Models\SanitationSetting::FALLBACK_FREQUENCY_PRICING;

    $defaultCollectionFee   = $settings->default_collection_fee   ?? 50.00;
    $lateFeePercentage      = $settings->late_fee_percentage      ?? 5.00;

    $companyName = $settings->company_name ?? 'Sanitation Team';

    // Format helper
    $ghs = fn ($amount) => 'GH₵ ' . number_format((float) $amount, 2);

    // Emergency pickup — single source of truth
    $emergencyEnabled       = $settings?->hasEmergencyConfigured() ?? false;
    $emergencyCollectionFee = $settings?->getRawEmergencyFee() ?? 0.0;

    // Collection schedule
    $collectionDays = $settings?->default_collection_days ?? [];
    if (!is_array($collectionDays)) {
        $collectionDays = [];
    }

    $weekdayOrder = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday',
    ];

    $weekdayShort = [
        'Monday'    => 'Mon',
        'Tuesday'   => 'Tue',
        'Wednesday' => 'Wed',
        'Thursday'  => 'Thu',
        'Friday'    => 'Fri',
        'Saturday'  => 'Sat',
        'Sunday'    => 'Sun',
    ];

    $collectionDays = collect($collectionDays)
        ->filter(fn ($d) => is_string($d) && in_array($d, $weekdayOrder, true))
        ->unique()
        ->sortBy(fn ($d) => array_search($d, $weekdayOrder, true))
        ->values()
        ->all();

    $collectionDayCount = count($collectionDays);

    $collectionDaysLabel = collect($collectionDays)
        ->map(fn ($d) => $weekdayShort[$d] ?? $d)
        ->join(', ');

    $visitsPerMonthFor = function (string $freq) use ($collectionDayCount) {
        if ($collectionDayCount > 0) return $collectionDayCount;
        return match ($freq) {
            'daily'    => 30,
            'weekly'   => 4,
            'biweekly' => 2,
            'monthly'  => 1,
            default    => 0,
        };
    };

    $visitsSuffixFor = function (string $freq) use ($collectionDaysLabel) {
        return match ($freq) {
            'daily'    => $collectionDaysLabel ? "on {$collectionDaysLabel}" : 'every day',
            'weekly'   => $collectionDaysLabel ? "on {$collectionDaysLabel}" : 'once a week',
            'biweekly' => $collectionDaysLabel ? "every 2 weeks on {$collectionDaysLabel}" : 'every 2 weeks',
            'monthly'  => $collectionDaysLabel ? "on {$collectionDaysLabel}" : 'once a month',
            default    => '',
        };
    };

    $pricingJson = collect($frequencyPricing)->mapWithKeys(function ($row, $freq) {
        return [$freq => [
            'per_month' => (float) ($row['per_month'] ?? 0),
            'per_visit' => (float) ($row['per_visit'] ?? 0),
            'emergency' => (float) ($row['emergency'] ?? 0),
        ]];
    })->toJson();

    $visitsJson = collect($availableFrequencies)->mapWithKeys(function ($freq) use ($visitsPerMonthFor) {
        return [$freq => $visitsPerMonthFor($freq)];
    })->toJson();

    $suffixJson = collect($availableFrequencies)->mapWithKeys(function ($freq) use ($visitsSuffixFor) {
        return [$freq => $visitsSuffixFor($freq)];
    })->toJson();

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

    // ---------- Waste type badges ----------
    $wasteTypeStyles = [
        'general' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',

        'recyclable' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'organic' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'hazardous' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'bulk' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(168,85,247,0.22)' : 'rgba(168,85,247,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#c084fc' : '#7e22ce') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(168,85,247,0.45)' : 'rgba(168,85,247,0.35)') . '!important;',
    ];

    // Shared resolver — normalizes key, falls back to 'unknown'
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
@endphp

<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        <!-- Flash messages -->
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg"
                 style="background-color: rgba(34,197,94,0.1); color: #16a34a; border: 1px solid #16a34a;">
                <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg"
                 style="background-color: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid #dc2626;">
                <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Pending service requests banner -->
        @if($pendingServiceCount > 0)
            <div class="mb-4 px-4 py-3 rounded-lg"
                 style="background-color: rgba(245,158,11,0.1); color: #b45309; border: 1px solid #f59e0b;">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start">
                        <i class="fas fa-hourglass-half mr-3 mt-1"></i>
                        <div>
                            <strong>Sanitation service requested</strong>
                            <p class="text-sm mt-1">
                                You have {{ $pendingServiceCount }}
                                propert{{ $pendingServiceCount === 1 ? 'y' : 'ies' }}
                                awaiting a response from the sanitation team:
                                <span class="font-medium">
                                    {{ $pendingServiceRequests->pluck('property_name')->join(', ') }}
                                </span>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--primary);"></i>
                    My Collection Requests
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    View all waste collection requests for your properties
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">

                @if($hasUnlinkedProperties && $hasSupervisors)
                    <button type="button" onclick="showRequestServiceModal()" class="btn-primary">
                        <i class="fas fa-hands-helping mr-2"></i> Request Sanitation Service
                    </button>
                @else
                    <button type="button"
                            onclick="showRequestServiceUnavailable()"
                            class="btn-primary"
                            style="opacity: 0.5; cursor: not-allowed;"
                            title="{{ !$hasUnlinkedProperties ? 'All your properties are already linked' : 'No sanitation supervisors available' }}">
                        <i class="fas fa-hands-helping mr-2"></i> Request Sanitation Service
                    </button>
                @endif

                @if($hasLinkedProperties)
                    <button type="button" onclick="showBinFullModal()" class="btn-warning">
                        <i class="fas fa-trash-alt mr-2"></i> Report Bin Full
                    </button>
                @else
                    <button type="button"
                            onclick="showNoLinkedPropertiesAlert()"
                            class="btn-warning"
                            style="opacity: 0.5; cursor: not-allowed;"
                            title="No properties linked to waste collection yet">
                        <i class="fas fa-trash-alt mr-2"></i> Report Bin Full
                    </button>
                @endif

                <a href="{{ route('landlord.waste.approvals') }}" class="btn-warning">
                    <i class="fas fa-check-double mr-2"></i> Pending Approvals
                </a>
                <a href="{{ route('landlord.waste.history') }}" class="btn-secondary">
                    <i class="fas fa-history mr-2"></i> History
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}" autocomplete="off"
                           class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Property name...">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status"
                            class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Approval Status</label>
                    <select name="approval_status"
                            class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        @foreach($approvalStatuses as $key => $label)
                            <option value="{{ $key }}" {{ request('approval_status') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="{{ route('landlord.waste.requests') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Waste Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Approval</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Requested</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $request)
                            @php
                                // ---------- Status ----------
                                $statusKey    = $request->status ?? '';
                                $statusStyle  = $resolveBadgeStyle($statusKey, $statusStyles);
                                $statusLabel  = ucfirst(str_replace('_', ' ', $statusKey ?: 'Unknown'));

                                // ---------- Approval (the column you flagged) ----------
                                $approvalKey    = $request->approval_status ?? '';
                                $approvalStyle  = $resolveBadgeStyle($approvalKey, $approvalStyles);
                                $approvalLabel  = match ($approvalKey) {
                                    'pending'       => 'Pending Approval',
                                    'approved'      => 'Approved',
                                    'rejected'      => 'Rejected',
                                    'expired'       => 'Expired',
                                    'auto_approved' => 'Auto-Approved',
                                    ''              => 'N/A',
                                    default         => ucfirst(str_replace('_', ' ', $approvalKey)),
                                };

                                // ---------- Waste type ----------
                                $wasteType     = $request->waste_type ?? 'general';
                                $wasteStyle    = $resolveBadgeStyle($wasteType, $wasteTypeStyles);
                                $wasteLabel    = ucfirst($wasteType ?: 'General');

                                $canReportBinFull = in_array($request->approval_status, ['approved', 'auto_approved'], true)
                                    && $request->property_id;
                            @endphp
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $request->property->property_name ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $request->property->digital_address ?? 'No address' }}
                                        </div>
                                    </div>
                                </td>

                                {{-- Waste Type --}}
                                <td class="px-4 py-3">
                                    <span style="{{ $wasteStyle }}" data-waste-type="{{ $wasteType }}">
                                        {{ $wasteLabel }}
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3">
                                    <span style="{{ $statusStyle }}" data-status="{{ $statusKey }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                {{-- ✅ Approval — the fixed column --}}
                                <td class="px-4 py-3">
                                    <span style="{{ $approvalStyle }}" data-approval-status="{{ $approvalKey }}">
                                        {{ $approvalLabel }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ optional($request->created_at)->format('M d, Y') ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ optional($request->created_at)->format('g:i A') ?? '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <a href="{{ route('landlord.waste.request.show', ['wasteCollectionRequest' => $request->id]) }}"
                                           class="text-sm hover:underline px-2 py-1 rounded"
                                           style="color: var(--primary); background-color: rgba(59,130,246,0.1);"
                                           title="View details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if($request->approval_status === 'pending')
                                            <button type="button"
                                                    onclick="approveRequest('{{ $request->id }}')"
                                                    class="text-sm hover:underline px-2 py-1 rounded"
                                                    style="color: #22c55e; background-color: rgba(34, 197, 94, 0.1);"
                                                    title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button"
                                                    onclick="showRejectModal('{{ $request->id }}')"
                                                    class="text-sm hover:underline px-2 py-1 rounded"
                                                    style="color: #ef4444; background-color: rgba(239, 68, 68, 0.1);"
                                                    title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif

                                        @if($canReportBinFull)
                                            <button type="button"
                                                    onclick="reportBinFull({{ $request->property_id }}, {{ json_encode($request->property->property_name ?? 'Property') }})"
                                                    class="text-sm hover:underline px-2 py-1 rounded"
                                                    style="color: #f59e0b; background-color: rgba(245, 158, 11, 0.1);"
                                                    title="Report bin full">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-inbox text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No collection requests found</p>
                                    <p class="text-xs mt-1">Try adjusting your filters</p>

                                    @if($hasUnlinkedProperties && $hasSupervisors)
                                        <button type="button"
                                                onclick="showRequestServiceModal()"
                                                class="btn-primary mt-4 inline-flex">
                                            <i class="fas fa-hands-helping mr-2"></i>
                                            Request Sanitation Service
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $requests->appends(request()->query())->links() }}
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
            <button type="button" onclick="closeRejectModal()"
                    class="p-1 rounded-full transition-colors duration-200"
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
                <input type="hidden" name="request_id" id="rejectRequestId">
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

<!-- Bin Full Modal -->
<div id="binFullModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-w-lg"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-trash-alt mr-2" style="color: #f59e0b;"></i>
                Report Bin Full
            </h3>
            <button type="button" onclick="closeBinFullModal()" class="p-1 rounded-full"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        @if($hasLinkedProperties)
            <form id="binFullForm" method="POST" action="{{ route('landlord.waste.bin.full') }}">
                @csrf
                <div class="p-6 space-y-4">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Notify the sanitation team that a bin at one of your linked properties is full.
                    </p>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Property
                        </label>
                        <select name="property_id" id="binFullPropertyId" required
                                class="w-full px-4 py-3 rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary);
                                       border: 1px solid var(--border-color); outline: none;">
                            <option value="">Select a property...</option>
                            @foreach($linkedProperties as $prop)
                                <option value="{{ $prop->id }}">
                                    {{ $prop->property_name }}@if($prop->digital_address) — {{ $prop->digital_address }}@endif
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Only properties linked to waste collection are shown.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Priority
                        </label>
                        <select name="priority"
                                class="w-full px-4 py-3 rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary);
                                       border: 1px solid var(--border-color); outline: none;">
                            <option value="medium">Medium</option>
                            <option value="high" selected>High</option>
                            @if($emergencyEnabled)
                                <option value="emergency">Emergency</option>
                            @endif
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Notes (optional)
                        </label>
                        <textarea name="notes" rows="3" maxlength="500"
                                  class="w-full px-4 py-3 rounded-lg"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary);
                                         border: 1px solid var(--border-color); outline: none;"
                                  placeholder="e.g. Overflowing, smells bad, needs urgent pickup"></textarea>
                    </div>

                    @if($emergencyEnabled && $emergencyCollectionFee > 0)
                        <div class="p-3 rounded-lg"
                             style="background-color: rgba(245,158,11,0.08);
                                    border-left: 4px solid #f59e0b;">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Selecting <strong>Emergency</strong> priority incurs an
                                additional <strong>{{ $ghs($emergencyCollectionFee) }}</strong>
                                surcharge on your next invoice.
                            </p>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 border-t flex justify-end space-x-3"
                     style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                    <button type="button" onclick="closeBinFullModal()"
                            class="px-4 py-2 text-sm font-medium rounded-lg"
                            style="color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg"
                            style="background-color: #f59e0b; color: white;">
                        <i class="fas fa-paper-plane mr-2"></i> Notify Sanitation
                    </button>
                </div>
            </form>
        @else
            <div class="p-6 text-center">
                <i class="fas fa-info-circle text-4xl mb-3" style="color: var(--info); opacity: 0.6;"></i>
                <p class="text-sm font-medium" style="color: var(--text-primary);">
                    No properties linked to waste collection
                </p>
                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                    Only properties with an active waste collection request can report a full bin.
                    Contact the sanitation team to link a property first.
                </p>
            </div>
            <div class="px-6 py-4 border-t flex justify-end"
                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                <button type="button" onclick="closeBinFullModal()"
                        class="px-4 py-2 text-sm font-medium rounded-lg"
                        style="color: var(--text-secondary);">
                    Close
                </button>
            </div>
        @endif
    </div>
</div>

<!-- Request Sanitation Service Modal -->
<div id="requestServiceModal"
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">

    <div class="rounded-lg shadow-xl w-full max-w-3xl flex flex-col overflow-hidden modal-shell"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                max-height: 90vh;">

        <div class="px-6 py-4 border-b flex justify-between items-center flex-shrink-0"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-hands-helping mr-2" style="color: var(--primary);"></i>
                Request Sanitation Service
            </h3>
            <button type="button" onclick="closeRequestServiceModal()"
                    class="p-1 rounded-full"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        @if($hasUnlinkedProperties && $hasSupervisors)
            <form id="requestServiceForm" method="POST"
                  action="{{ route('landlord.waste.request.service') }}"
                  class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf

                <div class="request-service-modal-body p-6 space-y-6 flex-1 min-h-0 overflow-y-auto">

                    {{-- STEP 1 --}}
                    <section>
                        <div class="flex items-center mb-3">
                            <span class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-bold mr-3"
                                  style="background-color: var(--primary); color: white;">1</span>
                            <h4 class="font-semibold" style="color: var(--text-primary);">
                                Choose Property
                            </h4>
                        </div>

                        <div class="p-3 rounded-lg mb-3"
                             style="background-color: rgba(59,130,246,0.1);
                                    border-left: 4px solid var(--info);">
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                Only properties not yet linked to waste collection are shown.
                            </p>
                        </div>

                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Property <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="property_id" id="requestServicePropertyId" required
                                class="w-full px-4 py-3 rounded-lg"
                                style="background-color: var(--bg-secondary);
                                       color: var(--text-primary);
                                       border: 1px solid var(--border-color);
                                       outline: none;">
                            <option value="">Select a property...</option>
                            @foreach($unlinkedProperties as $prop)
                                <option value="{{ $prop->id }}" data-zone="{{ $prop->zone }}">
                                    {{ $prop->property_name }}@if($prop->digital_address) — {{ $prop->digital_address }}@endif
                                </option>
                            @endforeach
                        </select>
                    </section>

                    {{-- STEP 2 --}}
                    <section>
                        <div class="flex items-center mb-3">
                            <span class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-bold mr-3"
                                  style="background-color: var(--primary); color: white;">2</span>
                            <h4 class="font-semibold" style="color: var(--text-primary);">
                                Collection Frequency
                            </h4>
                        </div>

                        <p class="text-xs mb-3" style="color: var(--text-secondary);">
                            Choose how often you'd like the truck to visit. Your monthly rate is
                            shown next to each option.
                            @if($collectionDayCount > 0)
                                <br>
                                <span class="inline-flex items-center mt-1 font-medium"
                                      style="color: var(--primary);">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    Collection days scheduled by the sanitation team:
                                    <span class="ml-1">{{ $collectionDaysLabel }}</span>
                                </span>
                            @endif
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="frequencyOptions">
                            @foreach($availableFrequencies as $freq)
                                @php
                                    $row         = $frequencyPricing[$freq] ?? [];
                                    $perMonth    = $row['per_month'] ?? $defaultCollectionFee;
                                    $visitCount  = $visitsPerMonthFor($freq);
                                    $visitsLabel = $visitCount > 0
                                        ? $visitCount . ' visit' . ($visitCount === 1 ? '' : 's')
                                        : '';
                                    $daySuffix   = $visitsSuffixFor($freq);
                                    $dayChips    = $collectionDays;
                                @endphp
                                <label class="freq-option cursor-pointer flex items-start p-4 rounded-lg border-2 transition-all"
                                       data-frequency="{{ $freq }}"
                                       data-per-month="{{ $perMonth }}"
                                       data-visits="{{ $visitCount }}"
                                       style="background-color: var(--bg-secondary);
                                              border-color: var(--border-color);">
                                    <input type="radio"
                                           name="collection_frequency"
                                           value="{{ $freq }}"
                                           class="freq-radio mt-1 mr-3"
                                           {{ old('collection_frequency') === $freq ? 'checked' : '' }}>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1 pr-2">
                                                <div class="font-semibold" style="color: var(--text-primary);">
                                                    {{ ucfirst($freq) }}
                                                </div>

                                                <div class="text-xs mt-0.5 freq-cadence"
                                                     style="color: var(--text-secondary);">
                                                    @if($visitsLabel)
                                                        <span class="freq-visits">
                                                            {{ $visitsLabel }} / week
                                                        </span>
                                                        @if($daySuffix)
                                                            <span class="freq-days"> ({{ $daySuffix }})</span>
                                                        @endif
                                                    @else
                                                        <span class="freq-visits">—</span>
                                                    @endif
                                                </div>

                                                @if($collectionDayCount > 0)
                                                    <div class="flex flex-wrap gap-1 mt-2">
                                                        @foreach($dayChips as $day)
                                                            <span class="freq-day-chip px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                                  style="background-color: rgba(59,130,246,0.12);
                                                                         color: var(--primary);">
                                                                {{ $weekdayShort[$day] ?? $day }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-right ml-3 flex-shrink-0">
                                                <div class="font-bold" style="color: var(--primary);">
                                                    {{ $ghs($perMonth) }}
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    per month
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        @error('collection_frequency')
                            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </section>

                    {{-- STEP 3 --}}
                    <section>
                        <div class="flex items-center mb-3">
                            <span class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-bold mr-3"
                                  style="background-color: var(--primary); color: white;">3</span>
                            <h4 class="font-semibold" style="color: var(--text-primary);">
                                Pricing & Agreement
                            </h4>
                        </div>

                        <div class="rounded-lg overflow-hidden mb-4"
                             style="background-color: var(--bg-secondary);
                                    border: 1px solid var(--border-color);">
                            <div class="px-4 py-3 border-b" style="border-color: var(--border-color);">
                                <div class="text-xs font-medium uppercase tracking-wider"
                                     style="color: var(--text-secondary);">
                                    Your Monthly Estimate
                                </div>
                            </div>

                            <dl class="divide-y" style="border-color: var(--border-color);">
                                <div class="flex justify-between px-4 py-3">
                                    <dt style="color: var(--text-secondary);">
                                        Base subscription
                                        <span class="text-xs" id="pricingFrequencyLabel">—</span>
                                    </dt>
                                    <dd class="font-semibold" style="color: var(--text-primary);"
                                        id="pricingBaseAmount">
                                        {{ $ghs($defaultCollectionFee) }}
                                    </dd>
                                </div>

                                <div class="flex justify-between px-4 py-3">
                                    <dt style="color: var(--text-secondary);">
                                        Collection days
                                    </dt>
                                    <dd class="font-medium text-right" style="color: var(--text-primary);"
                                        id="pricingCadence">
                                        @if($collectionDayCount > 0)
                                            {{ $collectionDaysLabel }}
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>

                                <div class="flex justify-between px-4 py-3">
                                    <dt style="color: var(--text-secondary);">
                                        Visits per week
                                    </dt>
                                    <dd class="font-medium" style="color: var(--text-primary);"
                                        id="pricingVisits">
                                        @if($collectionDayCount > 0)
                                            {{ $collectionDayCount }} visit{{ $collectionDayCount === 1 ? '' : 's' }} / week
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>

                                @if($emergencyEnabled)
                                    <div class="flex justify-between px-4 py-3">
                                        <dt style="color: var(--text-secondary);">
                                            Emergency pickup (optional)
                                            <span class="text-xs" style="color: var(--text-secondary);">
                                                — charged only when you report a bin full
                                            </span>
                                        </dt>
                                        <dd class="font-medium" style="color: var(--text-primary);"
                                            id="pricingEmergencyAmount"
                                            data-emergency-enabled="1">
                                            @if($emergencyCollectionFee > 0)
                                                {{ $ghs($emergencyCollectionFee) }}
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </div>
                                @endif

                                <div class="flex justify-between px-4 py-3">
                                    <dt style="color: var(--text-secondary);">
                                        Late payment fee
                                    </dt>
                                    <dd class="font-medium" style="color: var(--text-primary);">
                                        {{ number_format((float) $lateFeePercentage, 2) }}% / month on overdue
                                    </dd>
                                </div>
                                <div class="flex justify-between px-4 py-3"
                                     style="background-color: rgba(59,130,246,0.08);">
                                    <dt class="font-semibold" style="color: var(--text-primary);">
                                        Estimated monthly total
                                    </dt>
                                    <dd class="font-bold" style="color: var(--primary);"
                                        id="pricingMonthlyTotal">
                                        {{ $ghs($defaultCollectionFee) }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="px-4 py-2 text-xs border-t"
                                 style="border-color: var(--border-color);
                                        color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                @if($emergencyEnabled)
                                    Emergency and bulk pickups are billed separately. This estimate
                                    covers your scheduled collections only.
                                @else
                                    Bulk pickups are billed separately. This estimate
                                    covers your scheduled collections only.
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg p-4 mb-4"
                             style="background-color: rgba(245,158,11,0.08);
                                    border-left: 4px solid #f59e0b;">
                            <h5 class="font-semibold text-sm mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-file-contract mr-2"></i>
                                Terms of Service
                            </h5>
                            <ul class="text-xs space-y-1.5 list-disc list-inside"
                                style="color: var(--text-secondary);">
                                <li>The subscription fee is billed <strong>monthly in advance</strong> by {{ $companyName }}.</li>
                                <li>
                                    Scheduled pickups run at the frequency you selected
                                    @if($collectionDayCount > 0)
                                        ({{ $collectionDaysLabel }})
                                    @endif
                                    ; missed visits are caught up on the next available slot.
                                </li>
                                @if($emergencyEnabled)
                                    <li>Any bin-full report or emergency pickup is surcharged per the rate above.</li>
                                @endif
                                <li>Bulk waste (furniture, construction debris, etc.) is billed separately at GH₵ 150 per event.</li>
                                <li>Overdue invoices accrue a {{ number_format((float) $lateFeePercentage, 2) }}% late fee per month.</li>
                                <li>You may cancel the subscription at any time; billing stops at the end of the current cycle.</li>
                                <li>Service is subject to the team's availability and operational hours.</li>
                            </ul>
                        </div>

                        <label class="flex items-start p-4 rounded-lg cursor-pointer"
                               style="background-color: rgba(34,197,94,0.08);
                                      border: 2px solid var(--border-color);">
                            <input type="checkbox"
                                   name="agreement_accepted"
                                   id="agreementAccepted"
                                   value="1"
                                   required
                                   class="mt-1 mr-3 w-5 h-5 cursor-pointer"
                                   {{ old('agreement_accepted') ? 'checked' : '' }}>
                            <span class="text-sm" style="color: var(--text-primary);">
                                I have read and agree to the <strong>Terms of Service</strong>
                                and the <strong>pricing</strong> above. I understand that the
                                subscription is billed monthly and that extras are surcharged
                                as described.
                            </span>
                        </label>
                        @error('agreement_accepted')
                            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </section>

                    <section>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Message (optional)
                        </label>
                        <textarea name="message" id="requestServiceMessage"
                                  rows="3" maxlength="500"
                                  class="w-full px-4 py-3 rounded-lg"
                                  style="background-color: var(--bg-secondary);
                                         color: var(--text-primary);
                                         border: 1px solid var(--border-color);
                                         outline: none;"
                                  placeholder="e.g. I would like scheduled pickup on Monday mornings."></textarea>
                        <div class="text-xs mt-1 text-right" style="color: var(--text-secondary);">
                            <span id="requestServiceCharCount">0</span>/500
                        </div>
                    </section>

                    <div class="p-3 rounded-lg"
                         style="background-color: rgba(59,130,246,0.08);
                                border-left: 4px solid var(--info);">
                        <div class="flex items-start">
                            <i class="fas fa-user-shield mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <p class="text-xs font-medium" style="color: var(--text-primary);">
                                    How routing works
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Your request is delivered to the sanitation supervisor(s)
                                    assigned by the administrator. You'll be notified once
                                    someone accepts it.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t flex justify-end space-x-3 flex-shrink-0"
                     style="border-color: var(--border-color);
                            background-color: var(--bg-secondary);">
                    <button type="button" onclick="closeRequestServiceModal()"
                            class="px-4 py-2 text-sm font-medium rounded-lg"
                            style="color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-paper-plane mr-2"></i> Send Request
                    </button>
                </div>
            </form>
        @else
            <div class="p-6 text-center">
                <i class="fas fa-info-circle text-4xl mb-3"
                   style="color: var(--info); opacity: 0.6;"></i>
                <p class="text-sm font-medium" style="color: var(--text-primary);">
                    @if(!$hasUnlinkedProperties)
                        All your properties are already linked to waste collection.
                    @else
                        No sanitation supervisors are currently available.
                    @endif
                </p>
                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                    @if(!$hasUnlinkedProperties)
                        Use <strong>Report Bin Full</strong> if you need a pickup on a linked property.
                    @else
                        Please try again later or contact the administrator.
                    @endif
                </p>
            </div>
            <div class="px-6 py-4 border-t flex justify-end flex-shrink-0"
                 style="border-color: var(--border-color);
                        background-color: var(--bg-secondary);">
                <button type="button" onclick="closeRequestServiceModal()"
                        class="px-4 py-2 text-sm font-medium rounded-lg"
                        style="color: var(--text-secondary);">
                    Close
                </button>
            </div>
        @endif
    </div>
</div>

@endsection

@push('styles')
<style>
    /* Buttons */
    .btn-primary, .btn-secondary, .btn-info, .btn-warning {
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
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white; text-decoration: none;
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
    .btn-warning { background-color: #f59e0b; color: white; }
    .btn-warning:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white; text-decoration: none;
    }
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    /* Frequency option hover + selected state */
    .freq-option:hover {
        border-color: var(--primary);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .freq-option.freq-option--selected {
        border-color: var(--primary) !important;
        background-color: rgba(var(--primary-rgb, 59,130,246), 0.08) !important;
        box-shadow: 0 4px 12px rgba(var(--primary-rgb, 59,130,246), 0.15);
    }

    /* Frequency day chips */
    .freq-day-chip {
        line-height: 1.4;
        letter-spacing: 0.02em;
    }
    .freq-cadence {
        line-height: 1.4;
    }
    .freq-option--selected .freq-day-chip {
        background-color: rgba(var(--primary-rgb, 59,130,246), 0.22) !important;
    }

    /* Modal shell */
    .modal-shell {
        overflow: hidden;
    }

    .request-service-modal-body {
        overflow-y: auto;
        min-height: 0;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;

        background-image:
            linear-gradient(var(--card-bg) 30%, rgba(0, 0, 0, 0)),
            linear-gradient(rgba(0, 0, 0, 0), var(--card-bg) 70%);
        background-position: 0 0, 0 100%;
        background-repeat: no-repeat;
        background-size: 100% 24px, 100% 24px;
        background-attachment: local, local;
    }

    .request-service-modal-body {
        scrollbar-width: thin;
        scrollbar-color: rgba(var(--primary-rgb, 59,130,246), 0.5) transparent;
    }

    .request-service-modal-body::-webkit-scrollbar { width: 10px; }
    .request-service-modal-body::-webkit-scrollbar-track {
        background: transparent;
        margin: 4px 0;
    }
    .request-service-modal-body::-webkit-scrollbar-thumb {
        background-color: rgba(var(--primary-rgb, 59,130,246), 0.35);
        border-radius: 8px;
        border: 2px solid transparent;
        background-clip: padding-box;
    }
    .request-service-modal-body::-webkit-scrollbar-thumb:hover {
        background-color: rgba(var(--primary-rgb, 59,130,246), 0.6);
    }

    #frequencyOptions::-webkit-scrollbar { width: 6px; }
    #frequencyOptions::-webkit-scrollbar-thumb {
        background: rgba(var(--primary-rgb, 59,130,246), 0.3);
        border-radius: 6px;
    }

    @media (max-width: 640px) {
        .modal-shell { max-height: 95vh !important; }
        .request-service-modal-body { padding: 1rem !important; }
    }

    [data-theme="dark"] .request-service-modal-body {
        scrollbar-color: rgba(var(--primary-rgb, 59,130,246), 0.6) transparent;
    }
</style>
@endpush

@push('scripts')
<script>
    // Route templates
    const approveRouteBase    = "{{ route('landlord.waste.request.approve', ['wasteCollectionRequest' => '__ID__']) }}";
    const rejectRouteBase     = "{{ route('landlord.waste.request.reject',  ['wasteCollectionRequest' => '__ID__']) }}";
    const binFullRoute        = "{{ route('landlord.waste.bin.full') }}";
    const requestServiceRoute = "{{ route('landlord.waste.request.service') }}";

    const FREQUENCY_PRICING = {!! $pricingJson !!};
    const CURRENCY_LABEL    = 'GH₵ ';

    const FREQUENCY_VISITS  = {!! $visitsJson !!};
    const FREQUENCY_SUFFIX  = {!! $suffixJson !!};

    const EMERGENCY_ENABLED = {{ $emergencyEnabled ? 'true' : 'false' }};

    // Approve / Reject
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
        document.getElementById('rejectRequestId').value = id;
        document.getElementById('rejectForm').action = rejectRouteBase.replace('__ID__', id);
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        const modal    = document.getElementById('rejectModal');
        const textarea = document.getElementById('rejectionReason');
        if (modal) modal.classList.add('hidden');
        if (textarea) textarea.value = '';
    }

    // Bin Full
    function showBinFullModal() {
        const modal = document.getElementById('binFullModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeBinFullModal() {
        const modal = document.getElementById('binFullModal');
        if (modal) modal.classList.add('hidden');
    }

    function showNoLinkedPropertiesAlert() {
        alert(
            'You have no properties linked to waste collection yet.\n\n' +
            'Please contact the sanitation team to link a property before reporting a full bin.'
        );
    }

    function reportBinFull(propertyId, propertyName) {
        if (!confirm(`Report that the bin at "${propertyName}" is full and needs collection?`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = binFullRoute;
        form.innerHTML = `
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="property_id" value="${propertyId}">
            <input type="hidden" name="priority" value="high">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    // Request Sanitation Service
    function showRequestServiceModal() {
        const modal = document.getElementById('requestServiceModal');
        if (modal) modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        const body = modal?.querySelector('.request-service-modal-body');
        if (body) body.scrollTop = 0;
    }

    function closeRequestServiceModal() {
        const modal = document.getElementById('requestServiceModal');
        const form  = document.getElementById('requestServiceForm');
        if (modal) modal.classList.add('hidden');
        if (form)  form.reset();
        const counter = document.getElementById('requestServiceCharCount');
        if (counter) counter.textContent = '0';
        clearFrequencySelection();
        document.body.style.overflow = '';
    }

    function showRequestServiceUnavailable() {
        const hasUnlinked   = {{ $hasUnlinkedProperties ? 'true' : 'false' }};
        const hasSupervisor = {{ $hasSupervisors ? 'true' : 'false' }};

        if (!hasUnlinked && !hasSupervisor) {
            alert('All your properties are already linked, and no sanitation supervisors are available.');
        } else if (!hasUnlinked) {
            alert('All your properties are already linked to waste collection.');
        } else {
            alert('No sanitation supervisors are currently available. Please try again later.');
        }
    }

    // Frequency selection + live pricing
    function formatCurrency(amount) {
        return CURRENCY_LABEL + Number(amount || 0).toLocaleString('en-GH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function clearFrequencySelection() {
        document.querySelectorAll('.freq-option').forEach(opt => {
            opt.classList.remove('freq-option--selected');
        });
    }

    function applyFrequencyPricing(freq) {
        const row = FREQUENCY_PRICING[freq] || null;

        const baseEl    = document.getElementById('pricingBaseAmount');
        const emergEl   = document.getElementById('pricingEmergencyAmount');
        const totalEl   = document.getElementById('pricingMonthlyTotal');
        const labelEl   = document.getElementById('pricingFrequencyLabel');
        const visitsEl  = document.getElementById('pricingVisits');

        if (!row) {
            if (baseEl)   baseEl.textContent   = '—';
            if (emergEl)  emergEl.textContent  = '—';
            if (totalEl)  totalEl.textContent  = '—';
            if (labelEl)  labelEl.textContent  = '';
            if (visitsEl) visitsEl.textContent = '—';
            return;
        }

        if (baseEl)  baseEl.textContent  = formatCurrency(row.per_month);
        if (totalEl) totalEl.textContent = formatCurrency(row.per_month);
        if (labelEl) labelEl.textContent = '(' + freq + ')';

        if (emergEl && EMERGENCY_ENABLED) {
            const emergVal = Number(row.emergency || 0);
            emergEl.textContent = emergVal > 0 ? formatCurrency(emergVal) : '—';
        }

        if (visitsEl) {
            const visits = FREQUENCY_VISITS[freq] ?? 0;
            visitsEl.textContent = visits > 0
                ? visits + ' visit' + (visits === 1 ? '' : 's') + ' / week'
                : '—';
        }
    }

    function wireFrequencyOptions() {
        const options = document.querySelectorAll('.freq-option');
        const radios  = document.querySelectorAll('.freq-radio');

        options.forEach(opt => {
            opt.addEventListener('click', () => {
                const radio = opt.querySelector('.freq-radio');
                if (!radio) return;
                radio.checked = true;
                clearFrequencySelection();
                opt.classList.add('freq-option--selected');
                applyFrequencyPricing(radio.value);
            });
        });

        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (!radio.checked) return;
                clearFrequencySelection();
                const parent = radio.closest('.freq-option');
                if (parent) parent.classList.add('freq-option--selected');
                applyFrequencyPricing(radio.value);
            });
        });

        const preselected = document.querySelector('.freq-radio:checked');
        if (preselected) {
            const parent = preselected.closest('.freq-option');
            if (parent) parent.classList.add('freq-option--selected');
            applyFrequencyPricing(preselected.value);
        }
    }

    // Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('requestServiceForm');
        if (form) {
            form.addEventListener('input', function (e) {
                if (e.target.name === 'message') {
                    const counter = document.getElementById('requestServiceCharCount');
                    if (counter) counter.textContent = e.target.value.length;
                }
            });

            form.addEventListener('submit', function (e) {
                const freq   = document.querySelector('.freq-radio:checked');
                const agreed = document.getElementById('agreementAccepted');

                if (!freq) {
                    e.preventDefault();
                    alert('Please choose a collection frequency.');
                    return;
                }
                if (!agreed || !agreed.checked) {
                    e.preventDefault();
                    alert('Please read and accept the Terms of Service and pricing before submitting.');
                    return;
                }
            });
        }

        wireFrequencyOptions();
    });

    // Global modal close handlers
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeRejectModal();
            closeBinFullModal();
            closeRequestServiceModal();
        }
    });

    document.getElementById('rejectModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeRejectModal();
    });

    document.getElementById('binFullModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeBinFullModal();
    });

    document.getElementById('requestServiceModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeRequestServiceModal();
    });
</script>
@endpush