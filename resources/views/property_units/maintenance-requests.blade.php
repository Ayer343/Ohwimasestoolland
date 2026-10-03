{{-- resources/views/property_units/maintenance-requests.blade.php --}}
@php
    use App\Models\MaintenanceRequest;

    // ========== AUTH & ROLE ==========
    $user = auth()->user();
    $isLandlord   = $user->isLandlord()   || $user->hasRole('landlord');
    $isAdmin      = $user->isAdmin()      || $user->hasRole('admin');
    $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('super-admin');
    $isTenant     = $user->isTenant()     || $user->hasRole('tenant');

    // ========== LAYOUT & ROUTE PREFIX ==========
    if ($isLandlord) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin';
    } elseif ($isTenant) {
        $layout = 'layouts.tenant';
        $routePrefix = 'tenant';
    } else {
        $layout = 'layouts.app';
        $routePrefix = '';
    }

    // ========== CURRENCY & LAW ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');

    // ========== ROUTE DISCOVERY ==========
    $findRoute = function (array $candidates) {
        foreach ($candidates as $name) {
            if ($name && Route::has($name)) return $name;
        }
        return null;
    };

    $showUnitRoute = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.show" : null,
        'property-units.show',
    ]));

    $createRoute = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.create-maintenance-request" : null,
        'property-units.create-maintenance-request',
    ]));

    $showRequestRoute = function ($id) use ($routePrefix, $findRoute) {
        $name = $findRoute(array_filter([
            $routePrefix ? "{$routePrefix}.property-units.show-maintenance-request" : null,
            'property-units.show-maintenance-request',
        ]));
        return $name ? route($name, $id) : null;
    };

    $cancelRoute = function ($id) use ($routePrefix, $findRoute) {
        $name = $findRoute(array_filter([
            $routePrefix ? "{$routePrefix}.property-units.cancel-maintenance-request" : null,
            'property-units.cancel-maintenance-request',
        ]));
        return $name ? route($name, $id) : null;
    };

    // ========== VIEW DATA ==========
    $allRequests = $requests
        ?? $maintenanceRequests
        ?? $unit->maintenanceRequests
        ?? collect();

    // ========== STATS ==========
    $totalRequests   = $allRequests->count();
    $pendingRequests = $allRequests->where('status', 'pending')->count();
    $inProgress      = $allRequests->where('status', 'in_progress')->count();
    $completedCount  = $allRequests->where('status', 'completed')->count();
    $cancelledCount  = $allRequests->where('status', 'cancelled')->count();
    $urgentCount     = $allRequests->where('priority', 'urgent')->count();

    // ========== STATUS / PRIORITY META ==========
    $statusMeta = [
        'pending'     => ['badge-warning',  'clock',                'Pending'],
        'in_progress' => ['badge-info',     'spinner',              'In Progress'],
        'completed'   => ['badge-success',  'check-circle',         'Completed'],
        'cancelled'   => ['badge-secondary','ban',                  'Cancelled'],
    ];

    $priorityMeta = [
        'low'    => ['badge-secondary', 'arrow-down',            'Low'],
        'medium' => ['badge-info',      'minus',                 'Medium'],
        'high'   => ['badge-warning',   'arrow-up',              'High'],
        'urgent' => ['badge-danger',    'exclamation-triangle',  'Urgent'],
    ];

    $categoryMeta = [
        'plumbing'    => ['fa-faucet',       'Plumbing'],
        'electrical'  => ['fa-bolt',         'Electrical'],
        'appliance'   => ['fa-blender',      'Appliance'],
        'structural'  => ['fa-building',     'Structural'],
        'cleaning'    => ['fa-broom',        'Cleaning'],
        'other'       => ['fa-tools',        'Other'],
    ];

    // ========== PAGE TITLE ==========
    $pageTitle = 'Maintenance Requests - ' . $unit->unit_number;

    // ========== SESSION MESSAGES ==========
    $successMessage   = session('success');
    $errorMessage     = session('error');
    $validationErrors = session('errors');
@endphp

@extends($layout)

@section('title', $pageTitle)

{{-- Prevent duplicate navigation header (matches create.blade.php) --}}
@section('page-header')
@stop

@section('content')
<div class="ownership-transfer-container" style="padding-left: 0; margin-left: 0;">
    <div class="grid grid-cols-1 gap-6 mb-6">

        {{-- ============================================================
             HEADER CARD
             ============================================================ --}}
        <div class="card">
            <div class="flex justify-between items-center p-6 flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="mr-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            <i class="fas fa-tools text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                            <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>
                            Maintenance Requests
                            @if($urgentCount > 0)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-exclamation-triangle mr-1"></i> {{ $urgentCount }} Urgent
                            </span>
                            @endif
                        </h2>
                        <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>{{ $unit->property->property_name }}</span>
                            <span class="mx-1">•</span>
                            <i class="fas fa-door-closed mr-1"></i>
                            <span>Unit {{ $unit->unit_number }}</span>
                            <span class="mx-1">•</span>
                            <i class="fas fa-list mr-1"></i>
                            <span>{{ $totalRequests }} total request(s)</span>
                        </div>
                    </div>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                    <div class="flex flex-wrap gap-3 mt-2">
                        @if($showUnitRoute)
                        <a href="{{ route($showUnitRoute, $unit->id) }}"
                           class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Unit
                        </a>
                        @endif
                        @if($createRoute)
                        <button type="button"
                                onclick="openCreateModal()"
                                class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> New Request
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             SESSION MESSAGES
             ============================================================ --}}
        @foreach(['success' => ['bg-green-100','border-green-400','text-green-700','check-circle','Success!'],
                  'error'   => ['bg-red-100','border-red-400','text-red-700','exclamation-circle','Error!'],
                  'info'    => ['bg-blue-100','border-blue-400','text-blue-700','info-circle','Info!']] as $key => $cfg)
            @if(session($key))
            <div class="{{ $cfg[0] }} border {{ $cfg[1] }} {{ $cfg[2] }} px-4 py-3 rounded relative" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-{{ $cfg[3] }} mr-2"></i>
                    <span class="font-bold">{{ $cfg[4] }}</span>
                    <span class="ml-2">{{ session($key) }}</span>
                </div>
                <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            @endif
        @endforeach

        @if($validationErrors)
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="font-bold">Please fix the following errors:</span>
            </div>
            <ul class="mt-2 ml-6 list-disc">
                @foreach ($validationErrors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                    onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        {{-- ============================================================
             STATS STRIP
             ============================================================ --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="card">
                <div class="flex items-center p-4">
                    <div class="mr-3">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-list text-lg"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Total</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $totalRequests }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center p-4">
                    <div class="mr-3">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-clock text-lg"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Pending</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $pendingRequests }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center p-4">
                    <div class="mr-3">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-spinner text-lg"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">In Progress</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $inProgress }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center p-4">
                    <div class="mr-3">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-check-circle text-lg"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Completed</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $completedCount }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center p-4">
                    <div class="mr-3">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            <i class="fas fa-exclamation-triangle text-lg"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Urgent</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $urgentCount }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             FILTERS CARD
             ============================================================ --}}
        <div class="card">
            <div class="p-6">
                <div class="flex items-center gap-2 mb-4">
                    <i class="fas fa-filter" style="color: var(--primary);"></i>
                    <h3 class="font-semibold" style="color: var(--text-primary);">Filters</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                        </div>
                        <input type="text"
                               id="searchInput"
                               placeholder="Search requests..."
                               class="w-full p-2 border rounded pl-10 pr-3"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               onkeyup="filterRequests()">
                    </div>

                    <div>
                        <select id="statusFilter" onchange="filterRequests()"
                                class="w-full p-2 border rounded"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <select id="priorityFilter" onchange="filterRequests()"
                                class="w-full p-2 border rounded"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Priorities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <select id="categoryFilter" onchange="filterRequests()"
                                class="flex-1 p-2 border rounded"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Categories</option>
                            @foreach($categoryMeta as $key => $meta)
                                <option value="{{ $key }}">{{ $meta[1] }}</option>
                            @endforeach
                        </select>
                        <button type="button" onclick="clearFilters()"
                                class="index-custom-btn btn-secondary px-3 py-2 rounded-lg font-medium inline-flex items-center"
                                title="Clear filters">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             REQUESTS LIST CARD
             ============================================================ --}}
        <div class="card">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        Requests
                    </h3>
                    <span class="text-xs px-3 py-1 rounded-full"
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-list mr-1"></i>
                        <span id="visibleCount">{{ $totalRequests }}</span> of {{ $totalRequests }}
                    </span>
                </div>

                @if($allRequests->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-tools text-3xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No maintenance requests yet
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if($isTenant)
                                Report an issue with your unit and the landlord will be notified.
                            @else
                                This unit has no maintenance requests on record.
                            @endif
                        </p>
                        @if($createRoute)
                        <button type="button" onclick="openCreateModal()"
                                class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Submit First Request
                        </button>
                        @endif
                    </div>
                @else
                    <div class="space-y-3" id="requestsList">
                        @foreach($allRequests as $req)
                            @php
                                [$statusClass, $statusIcon, $statusLabel] = $statusMeta[$req->status]
                                    ?? ['badge-secondary', 'circle', ucfirst($req->status)];
                                [$priorityClass, $priorityIcon, $priorityLabel] = $priorityMeta[$req->priority ?? 'medium']
                                    ?? ['badge-secondary', 'circle', ucfirst($req->priority ?? 'medium')];
                                [$catIcon, $catLabel] = $categoryMeta[$req->category ?? 'other']
                                    ?? ['fa-tools', ucfirst(str_replace('_', ' ', $req->category ?? 'other'))];
                            @endphp

                            <div class="request-row p-4 rounded-xl transition-all"
                                 data-status="{{ $req->status }}"
                                 data-priority="{{ $req->priority ?? 'medium' }}"
                                 data-category="{{ $req->category ?? 'other' }}"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">

                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex items-start gap-3 flex-1 min-w-0">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas {{ $catIcon }} text-lg"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                <h4 class="font-semibold truncate" style="color: var(--text-primary);">
                                                    {{ $req->title }}
                                                </h4>
                                                <span class="px-2 py-0.5 text-xs rounded-full {{ $statusClass }}">
                                                    <i class="fas fa-{{ $statusIcon }} mr-1"></i> {{ $statusLabel }}
                                                </span>
                                                <span class="px-2 py-0.5 text-xs rounded-full {{ $priorityClass }}">
                                                    <i class="fas fa-{{ $priorityIcon }} mr-1"></i> {{ $priorityLabel }}
                                                </span>
                                            </div>

                                            <p class="text-sm mb-2 line-clamp-2" style="color: var(--text-secondary);">
                                                {{ Str::limit($req->description, 200) }}
                                            </p>

                                            <div class="flex flex-wrap items-center gap-4 text-xs"
                                                 style="color: var(--text-secondary);">
                                                @if($req->reference_id)
                                                    <span>
                                                        <i class="fas fa-hashtag mr-1"></i>{{ $req->reference_id }}
                                                    </span>
                                                @endif
                                                <span>
                                                    <i class="fas fa-tag mr-1"></i>{{ $catLabel }}
                                                </span>
                                                <span>
                                                    <i class="fas fa-calendar mr-1"></i>
                                                    {{ optional($req->created_at)->format('M j, Y') ?? '—' }}
                                                </span>
                                                @if($req->updated_at && $req->updated_at != $req->created_at)
                                                    <span>
                                                        <i class="fas fa-sync mr-1"></i>
                                                        Updated {{ $req->updated_at->diffForHumans() }}
                                                    </span>
                                                @endif
                                                @if(!empty($req->images) && is_array($req->images))
                                                    <span>
                                                        <i class="fas fa-image mr-1"></i>
                                                        {{ count($req->images) }} photo(s)
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @php $showUrl = $showRequestRoute($req->id); @endphp
                                        @if($showUrl)
                                        <a href="{{ $showUrl }}"
                                           class="inline-flex items-center justify-center w-9 h-9 rounded-lg"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @endif

                                        @php $cancelUrl = $cancelRoute($req->id); @endphp
                                        @if($cancelUrl && $req->status === 'pending')
                                        <form method="POST" action="{{ $cancelUrl }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                                                    onclick="return confirm('Cancel this maintenance request?');"
                                                    title="Cancel">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p id="noResultsMessage" class="text-center py-8 hidden" style="color: var(--text-secondary);">
                        <i class="fas fa-search mr-1"></i> No requests match your filters.
                    </p>
                @endif
            </div>
        </div>

        {{-- ============================================================
             GOVERNING LAW FOOTER
             ============================================================ --}}
        <div class="card">
            <div class="p-4">
                <p class="text-xs text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-balance-scale mr-1"></i>
                    Maintenance obligations governed by the <strong>{{ $governingLaw }}</strong> and lease terms.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     CREATE REQUEST MODAL (styled to match create.blade.php)
     ============================================================ --}}
@if($createRoute)
<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeCreateModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto"
             style="background: var(--card-bg); border: 1px solid var(--border-color);">

            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>
                    New Maintenance Request
                </h3>
                <button type="button" onclick="closeCreateModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>

            <form method="POST" action="{{ route($createRoute, $unit->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body space-y-4">

                    {{-- Unit info banner --}}
                    <div class="p-4 rounded-lg flex items-center gap-3"
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <i class="fas fa-building mr-2" style="color: var(--info);"></i>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <strong style="color: var(--text-primary);">{{ $unit->property->property_name }}</strong>
                            <span class="mx-1">•</span>
                            Unit {{ $unit->unit_number }}
                        </div>
                    </div>

                    {{-- Title --}}
                    <div>
                        <label for="mr_title" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Title <span style="color: var(--danger);">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                <i class="fas fa-heading" style="color: var(--text-secondary);"></i>
                            </div>
                            <input type="text" name="title" id="mr_title" required maxlength="255"
                                   class="w-full p-2 border rounded pl-10 pr-3"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., Leaking kitchen tap"
                                   value="{{ old('title') }}">
                        </div>
                    </div>

                    {{-- Priority + Category --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="mr_priority" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Priority <span style="color: var(--danger);">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                    <i class="fas fa-flag" style="color: var(--text-secondary);"></i>
                                </div>
                                <select name="priority" id="mr_priority" required
                                        class="w-full p-2 border rounded pl-10 pr-3"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="">Select Priority</option>
                                    <option value="low"     {{ old('priority') === 'low'     ? 'selected' : '' }}>Low</option>
                                    <option value="medium"  {{ old('priority') === 'medium'  ? 'selected' : '' }}>Medium</option>
                                    <option value="high"    {{ old('priority') === 'high'    ? 'selected' : '' }}>High</option>
                                    <option value="urgent"  {{ old('priority') === 'urgent'  ? 'selected' : '' }}>Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="mr_category" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Category <span style="color: var(--danger);">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                    <i class="fas fa-tag" style="color: var(--text-secondary);"></i>
                                </div>
                                <select name="category" id="mr_category" required
                                        class="w-full p-2 border rounded pl-10 pr-3"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="">Select Category</option>
                                    @foreach($categoryMeta as $key => $meta)
                                        <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>
                                            {{ $meta[1] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="mr_description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Description <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="description" id="mr_description" rows="4" required
                                  minlength="20" maxlength="1000"
                                  class="w-full p-2 border rounded"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe the issue in detail...">{{ old('description') }}</textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Minimum 20 characters.
                        </p>
                    </div>

                    {{-- Photos --}}
                    <div>
                        <label for="mr_photos" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Photos <span class="text-xs" style="color: var(--text-secondary);">(Optional, max 5)</span>
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 rounded-xl transition-colors"
                             id="mrDropzone"
                             style="border: 2px dashed var(--border-color);">
                            <div class="space-y-2 text-center">
                                <div class="mx-auto h-12 w-12">
                                    <i class="fas fa-cloud-upload-alt text-3xl" style="color: var(--text-secondary);"></i>
                                </div>
                                <div class="flex text-sm justify-center" style="color: var(--text-secondary);">
                                    <label for="mr_photos" class="relative cursor-pointer rounded-md font-medium hover:text-primary" style="color: var(--primary);">
                                        <span>Upload photos</span>
                                        <input id="mr_photos" name="photos[]" type="file" multiple accept="image/*" class="sr-only">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    JPEG, PNG, WEBP. Max 5 MB each.
                                </p>
                                <div class="mt-4" id="mrFilePreview" style="display: none;">
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span id="mrFileCount">0</span> file(s) selected
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Info notice --}}
                    <div class="p-3 rounded-lg"
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                            The landlord will be notified of this request. You will receive a confirmation once submitted.
                        </p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="closeCreateModal()"
                            class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" id="createSubmitBtn"
                            class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
function openCreateModal() {
    const m = document.getElementById('createModal');
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeCreateModal() {
    const m = document.getElementById('createModal');
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
}

// Live client-side filtering
function filterRequests() {
    const q         = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const status    = document.getElementById('statusFilter')?.value || '';
    const priority  = document.getElementById('priorityFilter')?.value || '';
    const category  = document.getElementById('categoryFilter')?.value || '';

    let visible = 0;
    document.querySelectorAll('.request-row').forEach(row => {
        const textMatch = !q || row.textContent.toLowerCase().includes(q);
        const statusMatch   = !status   || row.dataset.status   === status;
        const priorityMatch = !priority || row.dataset.priority === priority;
        const categoryMatch = !category || row.dataset.category === category;

        if (textMatch && statusMatch && priorityMatch && categoryMatch) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    const noResults = document.getElementById('noResultsMessage');
    if (noResults) {
        noResults.classList.toggle('hidden', visible > 0);
    }

    const visibleCountEl = document.getElementById('visibleCount');
    if (visibleCountEl) {
        visibleCountEl.textContent = visible;
    }
}

function clearFilters() {
    ['searchInput', 'statusFilter', 'priorityFilter', 'categoryFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    filterRequests();
}

// File preview for the create modal
document.getElementById('mr_photos')?.addEventListener('change', function () {
    const preview = document.getElementById('mrFilePreview');
    const count = document.getElementById('mrFileCount');
    if (!preview || !count) return;

    const n = this.files ? this.files.length : 0;
    if (n > 0) {
        count.textContent = n;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

// ESC closes the modal
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCreateModal();
});

// Prevent double-submit
document.querySelector('#createModal form')?.addEventListener('submit', function (e) {
    const btn = document.getElementById('createSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
    }
});

// Auto-hide session messages
setTimeout(() => {
    document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100')
        .forEach(msg => msg.style.display = 'none');
}, 5000);
</script>
@endsection

@section('styles')
<style>
/* ============================================================
   Card + Button + Badge + Modal styles — mirrors ownership-transfers/create
   ============================================================ */

.card {
    border-radius: 16px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

/* Badges */
.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

/* Buttons */
.index-custom-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 500;
}

.index-custom-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
}
.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}
.btn-secondary:hover:not(:disabled) {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

/* Modal */
.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--card-bg);
    z-index: 10;
    border-radius: 16px 16px 0 0;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
    z-index: 10;
    border-radius: 0 0 16px 16px;
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
}
.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Line clamp for description preview */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Request row hover */
.request-row:hover {
    border-color: var(--primary) !important;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.08);
    transform: translateY(-1px);
}

/* Dropzone hover */
#mrDropzone:hover {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}

/* Focus states */
input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .grid.grid-cols-2.md\:grid-cols-3.lg\:grid-cols-5 { grid-template-columns: repeat(2, 1fr); }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 { grid-template-columns: 1fr; }
    .modal-container { width: 95%; max-height: calc(100vh - 2rem); }
}
</style>
@endsection