{{-- resources/views/sanitation/properties/available.blade.php --}}

@php
    use Illuminate\Support\Str;

    // Detect which layout to use based on user role or route
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // ✅ FIXED: Determine whether the CURRENT user may LINK properties.
    //
    // Rule (mirrors SanitationController::canCurrentUserLinkProperties):
    //   - Admin / Super Admin → allowed
    //   - Sanitation personnel → must be a SUPERVISOR (root OR sub)
    //     whose creator is either an admin OR another supervisor.
    //   - Workers / drivers / others → denied.
    //
    // We compute this inline as a fallback so the Blade renders correctly
    // even if the controller forgets to pass $canLinkProperties.
    // -----------------------------------------------------------------
    if (!isset($canLinkProperties)) {
        $personnel = $user->sanitationPersonnel;

        $canLinkProperties = false;

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $canLinkProperties = true;
        } elseif ($personnel && method_exists($personnel, 'isSupervisor') && $personnel->isSupervisor()) {

            // Read metadata safely (array-cast OR raw JSON string).
            $meta = $personnel->metadata ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }

            $createdById = is_array($meta) ? ($meta['created_by'] ?? null) : null;

            if ($createdById) {
                $creator = \App\Models\User::find($createdById);

                if ($creator) {
                    // Case 1: created by an admin.
                    if ($creator->isAdmin() || $creator->isSuperAdmin()) {
                        $canLinkProperties = true;
                    }
                    // Case 2: created by another sanitation supervisor.
                    elseif ($creator->sanitationPersonnel
                        && method_exists($creator->sanitationPersonnel, 'isSupervisor')
                        && $creator->sanitationPersonnel->isSupervisor()) {
                        $canLinkProperties = true;
                    }
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // Landlord-submitted service requests for the properties on this page
    // -----------------------------------------------------------------
    $propertyIds = $properties->pluck('id')->all();

    $landlordRequests = \App\Models\SanitationServiceRequest::query()
        ->whereIn('property_id', $propertyIds)
        ->pending()
        ->with(['property:id,property_name,digital_address', 'landlord:id,name,phone,email'])
        ->get()
        ->keyBy('property_id');

    // Aggregate count for the stats card
    $landlordRequestCount = \App\Models\SanitationServiceRequest::query()->pending()->count();

    // -----------------------------------------------------------------
    // Normalize $debugInfo so the stat card grid never renders undefined keys.
    // -----------------------------------------------------------------
    $debugInfo = array_merge(
        [
            'total_properties'            => 0,
            'active_properties'           => 0,
            'properties_with_requests'    => 0,
            'properties_without_requests' => 0,
            'properties_pending_approval' => 0,
        ],
        $debugInfo ?? []
    );

    // -----------------------------------------------------------------
    // "Showing Active" reflects the whole filtered set, not just this page.
    // -----------------------------------------------------------------
    $showingActiveCount = $debugInfo['active_properties'] ?? 0;

    // -----------------------------------------------------------------
    // Property status → presentation map.
    // -----------------------------------------------------------------
    $statusPresentation = [
        'active'             => ['label' => 'Active',             'class' => 'status-active'],
        'inactive'           => ['label' => 'Inactive',           'class' => 'status-inactive'],
        'pending'            => ['label' => 'Pending',            'class' => 'status-pending'],
        'under_maintenance'  => ['label' => 'Under Maintenance',  'class' => 'status-maintenance'],
        'vacant'             => ['label' => 'Vacant',             'class' => 'status-vacant'],
        'under_construction' => ['label' => 'Under Construction', 'class' => 'status-construction'],
        'archived'           => ['label' => 'Archived',           'class' => 'status-archived'],
    ];
@endphp

@extends($layout)

@section('title', 'Available Properties')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                    Available Properties
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ $canLinkProperties
                        ? 'Link properties to waste collection services'
                        : 'View-only mode — only supervisors created by an administrator or another supervisor can link properties' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.properties.linked') }}" class="btn-info">
                    <i class="fas fa-link mr-2"></i> Linked Properties
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

        {{-- ✅ View-only banner for users without linking rights --}}
        @unless($canLinkProperties)
            <div class="card p-3 mb-4" style="background-color: rgba(59, 130, 246, 0.08); border-color: #3b82f6;">
                <div class="flex items-start gap-2" style="color: #2563eb;">
                    <i class="fas fa-eye mt-0.5"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            View-only mode
                        </p>
                        <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                            Only sanitation supervisors created by an administrator or another supervisor can link properties.
                            Contact your administrator if you need linking rights.
                        </p>
                    </div>
                </div>
            </div>
        @endunless

        <!-- Flash messages -->
        @if(session('success'))
            <div class="card p-3 mb-4" style="background-color: rgba(34, 197, 94, 0.1); border-color: #22c55e;">
                <div class="flex items-center gap-2" style="color: #16a34a;">
                    <i class="fas fa-check-circle"></i>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="card p-3 mb-4" style="background-color: rgba(239, 68, 68, 0.1); border-color: #ef4444;">
                <div class="flex items-center gap-2" style="color: #dc2626;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Debug Info (visible to admins only) -->
        @if($isAdmin)
            <div class="card p-4 mb-6" style="background-color: #fef3c7; border-color: #f59e0b;">
                <div class="flex items-start gap-3">
                    <i class="fas fa-bug text-yellow-600 text-xl mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-sm" style="color: var(--text-primary);">Debug Information</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-2 text-xs">
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Total Properties:</span>
                                <span class="font-bold" style="color: var(--text-primary);">{{ $debugInfo['total_properties'] }}</span>
                            </div>
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Active Properties:</span>
                                <span class="font-bold text-green-600">{{ $debugInfo['active_properties'] }}</span>
                            </div>
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">With Requests:</span>
                                <span class="font-bold text-blue-600">{{ $debugInfo['properties_with_requests'] }}</span>
                            </div>
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Without Requests:</span>
                                <span class="font-bold text-orange-600">{{ $debugInfo['properties_without_requests'] }}</span>
                            </div>
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            Linking rights for current user:
                            <strong style="color: {{ $canLinkProperties ? '#16a34a' : '#dc2626' }};">
                                {{ $canLinkProperties ? 'GRANTED (admin, or supervisor created by admin/supervisor)' : 'DENIED' }}
                            </strong>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $properties->total() }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Displaying</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $debugInfo['active_properties'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active Properties</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $debugInfo['properties_with_requests'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Already Linked</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-orange-500">{{ $debugInfo['properties_without_requests'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Available to Link</div>
            </div>
            <div class="card p-3 text-center" style="{{ $landlordRequestCount > 0 ? 'border-color: #f59e0b; background-color: rgba(245, 158, 11, 0.06);' : '' }}">
                <div class="text-xl font-bold" style="color: #f59e0b;">{{ $landlordRequestCount }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Landlord Requested</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">
                    {{ $showingActiveCount }}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Showing Active</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        <i class="fas fa-search mr-1" style="font-size: 10px;"></i>
                        Search Property or Landlord
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2 pointer-events-none"
                              style="color: var(--text-secondary); opacity: 0.6;">
                            <i class="fas fa-search text-xs"></i>
                        </span>
                        <input type="search"
                               name="search"
                               id="searchInput"
                               value="{{ request('search') }}"
                               autocomplete="off"
                               class="w-full pl-7 pr-2 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Property name, address, landlord name, phone…">
                    </div>
                    <p class="text-[10px] mt-1 leading-tight" style="color: var(--text-secondary); opacity: 0.75;">
                        Searches: property name, digital address, street name,
                        landlord name, landlord phone.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="under_maintenance" {{ request('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                        <option value="vacant" {{ request('status') == 'vacant' ? 'selected' : '' }}>Vacant</option>
                        <option value="under_construction" {{ request('status') == 'under_construction' ? 'selected' : '' }}>Under Construction</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Zone</label>
                    <select name="zone" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Zones</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone }}" {{ request('zone') == $zone ? 'selected' : '' }}>
                                {{ $zone }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Collection Status</label>
                    <select name="collection_status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        <option value="linked" {{ request('collection_status') == 'linked' ? 'selected' : '' }}>Linked</option>
                        <option value="available" {{ request('collection_status') == 'available' ? 'selected' : '' }}>Available</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.properties.available') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>

            @if(request('search'))
                <div class="mt-3 flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle"></i>
                    <span>
                        Filtering by:
                        <strong style="color: var(--text-primary);">"{{ request('search') }}"</strong>
                        <span class="opacity-75">(matches property or landlord)</span>
                    </span>
                    <a href="{{ route('sanitation.properties.available', request()->except('search')) }}"
                       class="ml-2 hover:underline" style="color: var(--primary);">
                        <i class="fas fa-times-circle"></i> Clear search
                    </a>
                </div>
            @endif
        </div>

        {{-- ✅ Bulk Actions Bar only renders when the user can link --}}
        @if($canLinkProperties)
            <div id="bulkActionsBar"
                 class="card p-3 mb-4 hidden"
                 style="background-color: rgba(59, 130, 246, 0.08); border-color: #3b82f6;">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-square" style="color: #3b82f6;"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            <span id="bulkSelectedCount">0</span> selected
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="bulkLinkBtn" class="btn-primary text-sm">
                            <i class="fas fa-link mr-1"></i> Link Selected
                        </button>
                        <button type="button" id="bulkApproveBtn" class="btn-warning text-sm">
                            <i class="fas fa-check mr-1"></i> Approve &amp; Link
                        </button>
                        <button type="button" id="bulkClearBtn" class="btn-secondary text-sm">
                            <i class="fas fa-times mr-1"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Properties Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            {{-- Tick column header only when linking is allowed --}}
                            @if($canLinkProperties)
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary); width: 40px;">
                                    <input type="checkbox" id="selectAllCheckbox"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           title="Select all linkable properties">
                                </th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Landlord</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Zone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Collection Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($properties as $property)
                            @php
                                $rawCount   = $property->waste_collection_requests_count ?? 0;
                                $hasRequest = (int) $rawCount > 0;

                                $landlordRequest     = $landlordRequests->get($property->id);
                                $isLandlordRequested = (bool) $landlordRequest;
                                $canBeSelected       = !$hasRequest;

                                $rawStatus = $property->status;
                                if ($rawStatus === null || $rawStatus === '' || $rawStatus === 'unknown') {
                                    $rawStatus = 'unknown';
                                }

                                $statusInfo  = $statusPresentation[$rawStatus] ?? [
                                    'label' => ucfirst(str_replace('_', ' ', $rawStatus)),
                                    'class' => 'status-unknown',
                                ];
                                $statusClass = $statusInfo['class'];
                                $statusLabel = $statusInfo['label'];

                                // Blank colspan when tick column is hidden
                                $emptyColspan = $canLinkProperties ? 8 : 7;
                            @endphp
                            <tr class="property-row {{ $isLandlordRequested ? 'landlord-requested-row' : '' }}"
                                style="background-color: var(--bg-secondary);"
                                data-property-id="{{ $property->id }}"
                                data-landlord-requested="{{ $isLandlordRequested ? '1' : '0' }}"
                                data-has-request="{{ $hasRequest ? '1' : '0' }}"
                                data-status="{{ $rawStatus }}">

                                {{-- Tick column — only when linking is allowed --}}
                                @if($canLinkProperties)
                                    <td class="px-3 py-3">
                                        @if($canBeSelected)
                                            <input type="checkbox"
                                                   class="property-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                   value="{{ $property->id }}"
                                                   data-property-id="{{ $property->id }}"
                                                   data-landlord-requested="{{ $isLandlordRequested ? '1' : '0' }}">
                                        @else
                                            <i class="fas fa-lock text-xs" style="color: var(--text-secondary); opacity: 0.5;" title="Already linked"></i>
                                        @endif
                                    </td>
                                @endif

                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-2">
                                        @if($isLandlordRequested)
                                            <span class="landlord-requested-pin" title="Landlord requested this link">
                                                <i class="fas fa-bolt"></i>
                                            </span>
                                        @endif
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $property->property_name }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $property->propertyType->name ?? 'N/A' }}
                                            </div>
                                            @if($isLandlordRequested)
                                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                                    <span class="badge-landlord-requested">
                                                        <i class="fas fa-user-check"></i>
                                                        Landlord Requested
                                                    </span>
                                                    @if($landlordRequest->collection_frequency)
                                                        <span class="badge-chip">
                                                            {{ ucfirst($landlordRequest->collection_frequency) }}
                                                        </span>
                                                    @endif
                                                    @if($landlordRequest->quoted_monthly_fee)
                                                        <span class="badge-chip">
                                                            GH₵ {{ number_format((float) $landlordRequest->quoted_monthly_fee, 2) }}/mo
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $property->street_name ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $property->digital_address ?? 'No address' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $property->landlord->name ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $property->landlord->phone ?? '' }}</div>
                                    @if($isLandlordRequested && $landlordRequest->landlord_name)
                                        <div class="text-xs mt-1" style="color: #f59e0b;">
                                            <i class="fas fa-paper-plane mr-1"></i>
                                            Submitted by {{ $landlordRequest->landlord_name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="pill-neutral">
                                        {{ $property->zone ?? 'Unassigned' }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="status-badge {{ $statusClass }}" data-status="{{ $rawStatus }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    @if($hasRequest)
                                        <span class="status-badge status-linked" data-collection-status="linked">
                                            <i class="fas fa-link mr-1"></i> Linked
                                        </span>
                                    @elseif($isLandlordRequested)
                                        <span class="status-badge status-awaiting" data-collection-status="awaiting">
                                            <i class="fas fa-hourglass-half mr-1"></i> Awaiting Link
                                        </span>
                                    @else
                                        <span class="status-badge status-available" data-collection-status="available">
                                            <i class="fas fa-unlink mr-1"></i> Available
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if($hasRequest)
                                            {{-- View is always allowed --}}
                                            <a href="{{ route('sanitation.properties.show', $property) }}"
                                               class="text-sm hover:underline" style="color: var(--primary);">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        @elseif($canLinkProperties)
                                            {{-- Link actions gated by permission --}}
                                            <a href="{{ route('sanitation.properties.link', $property) }}"
                                               class="text-sm hover:underline" style="color: var(--primary);">
                                                <i class="fas fa-link"></i> Link
                                            </a>
                                            @if($isLandlordRequested && $landlordRequest)
                                                <form method="POST"
                                                      action="{{ route('sanitation.properties.link', $property) }}"
                                                      class="inline">
                                                    @csrf
                                                    <input type="hidden" name="collection_frequency" value="{{ $landlordRequest->collection_frequency }}">
                                                    <input type="hidden" name="declaration" value="1">
                                                    <button type="submit"
                                                            class="text-sm hover:underline"
                                                            style="color: #f59e0b; background: none; border: none; cursor: pointer; padding: 0;">
                                                        <i class="fas fa-bolt"></i> Quick Link
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            {{-- View-only users see a disabled hint --}}
                                            <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">
                                                <i class="fas fa-lock mr-1"></i> Not available
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canLinkProperties ? 8 : 7 }}" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-building text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No properties found</p>
                                    <p class="text-xs mt-1">
                                        @if(request('search'))
                                            Nothing matches "<strong>{{ request('search') }}</strong>".
                                            Try a different property name, address, or landlord.
                                        @else
                                            Try adjusting your filters
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($properties->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $properties->appends(request()->query())->links() }}
                </div>
            @endif
        </div>

        <!-- Quick Action Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            {{-- Link card adapts to permission --}}
            <div class="card p-4 text-center hover:shadow-lg transition-shadow">
                <i class="fas fa-plus-circle text-3xl mb-2" style="color: var(--primary);"></i>
                <h4 class="font-semibold" style="color: var(--text-primary);">Link New Property</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    {{ $canLinkProperties
                        ? 'Connect a property to waste collection'
                        : 'Only supervisors created by admin or another supervisor can link' }}
                </p>
                @if($canLinkProperties)
                    <a href="{{ route('sanitation.properties.available') }}" class="btn-primary text-sm mt-2 inline-block">
                        <i class="fas fa-plus mr-1"></i> Link Property
                    </a>
                @else
                    <span class="btn-secondary text-sm mt-2 inline-block" style="cursor: not-allowed; opacity: 0.65;">
                        <i class="fas fa-lock mr-1"></i> Not Available
                    </span>
                @endif
            </div>
            <div class="card p-4 text-center hover:shadow-lg transition-shadow">
                <i class="fas fa-list text-3xl mb-2" style="color: var(--info);"></i>
                <h4 class="font-semibold" style="color: var(--text-primary);">View Linked</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">See all properties in collection system</p>
                <a href="{{ route('sanitation.properties.linked') }}" class="btn-info text-sm mt-2 inline-block">
                    <i class="fas fa-arrow-right mr-1"></i> View Linked
                </a>
            </div>
            <div class="card p-4 text-center hover:shadow-lg transition-shadow">
                <i class="fas fa-chart-bar text-3xl mb-2" style="color: var(--success);"></i>
                <h4 class="font-semibold" style="color: var(--text-primary);">Collection Stats</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">View overall collection statistics</p>
                <a href="{{ route('sanitation.statistics') }}" class="btn-success text-sm mt-2 inline-block">
                    <i class="fas fa-chart mr-1"></i> View Stats
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-info, .btn-success, .btn-warning, .btn-danger {
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
    .btn-primary   { background-color: var(--primary);   color: white; }
    .btn-info      { background-color: var(--info);      color: white; }
    .btn-success   { background-color: var(--success);   color: white; }
    .btn-danger    { background-color: var(--danger);    color: white; }
    .btn-warning   { background-color: #f59e0b;          color: white; }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-info:hover, .btn-success:hover,
    .btn-warning:hover, .btn-danger:hover {
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
    .hover\:shadow-lg:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .transition-shadow { transition: box-shadow 0.2s; }

    /* ----------------------------------------------------------------- */
    /* Property row highlight (landlord-requested)                       */
    /* ----------------------------------------------------------------- */
    .property-row.landlord-requested-row {
        border-left: 3px solid #f59e0b;
        background-color: rgba(245, 158, 11, 0.05) !important;
    }
    .property-row.landlord-requested-row:hover {
        background-color: rgba(245, 158, 11, 0.1) !important;
    }
    [data-theme="dark"] .property-row.landlord-requested-row {
        background-color: rgba(245, 158, 11, 0.08) !important;
    }
    [data-theme="dark"] .property-row.landlord-requested-row:hover {
        background-color: rgba(245, 158, 11, 0.15) !important;
    }

    .landlord-requested-pin {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: #f59e0b;
        color: white;
        font-size: 10px;
        flex-shrink: 0;
        margin-top: 2px;
        animation: landlordPinPulse 2s infinite;
    }
    @keyframes landlordPinPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
        50%      { box-shadow: 0 0 0 5px rgba(245, 158, 11, 0); }
    }

    .badge-landlord-requested {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        font-size: 10px;
        font-weight: 600;
        border-radius: 999px;
        background-color: #f59e0b;
        color: white;
        letter-spacing: 0.2px;
    }
    .badge-chip {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 4px;
        background-color: rgba(245, 158, 11, 0.15);
        color: #b45309;
        font-weight: 500;
    }
    [data-theme="dark"] .badge-chip {
        background-color: rgba(245, 158, 11, 0.25);
        color: #fbbf24;
    }

    /* ----------------------------------------------------------------- */
    /* STATUS BADGES                                                     */
    /* ----------------------------------------------------------------- */
    .status-badge,
    .pill-neutral {
        display: inline-flex !important;
        align-items: center;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
        visibility: visible !important;
        opacity: 1 !important;
        border: 1px solid transparent;
    }

    .pill-neutral,
    .status-unknown {
        background-color: rgba(107, 114, 128, 0.15);
        color: var(--text-secondary);
        border-color: rgba(107, 114, 128, 0.25);
    }
    [data-theme="dark"] .pill-neutral,
    [data-theme="dark"] .status-unknown {
        background-color: rgba(148, 163, 184, 0.18);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    .status-active {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }
    [data-theme="dark"] .status-active {
        background-color: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border-color: rgba(34, 197, 94, 0.4);
    }

    .status-inactive {
        background-color: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="dark"] .status-inactive {
        background-color: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    .status-pending {
        background-color: rgba(245, 158, 11, 0.15);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.3);
    }
    [data-theme="dark"] .status-pending {
        background-color: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.4);
    }

    .status-maintenance {
        background-color: rgba(168, 85, 247, 0.15);
        color: #7e22ce;
        border-color: rgba(168, 85, 247, 0.3);
    }
    [data-theme="dark"] .status-maintenance {
        background-color: rgba(168, 85, 247, 0.2);
        color: #c084fc;
        border-color: rgba(168, 85, 247, 0.4);
    }

    .status-vacant {
        background-color: rgba(59, 130, 246, 0.15);
        color: #2563eb;
        border-color: rgba(59, 130, 246, 0.3);
    }
    [data-theme="dark"] .status-vacant {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, 0.4);
    }

    .status-construction {
        background-color: rgba(249, 115, 22, 0.15);
        color: #ea580c;
        border-color: rgba(249, 115, 22, 0.3);
    }
    [data-theme="dark"] .status-construction {
        background-color: rgba(249, 115, 22, 0.2);
        color: #fb923c;
        border-color: rgba(249, 115, 22, 0.4);
    }

    .status-archived {
        background-color: rgba(71, 85, 105, 0.15);
        color: #475569;
        border-color: rgba(71, 85, 105, 0.3);
    }
    [data-theme="dark"] .status-archived {
        background-color: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    .status-linked {
        background-color: rgba(59, 130, 246, 0.15);
        color: #2563eb;
        border-color: rgba(59, 130, 246, 0.3);
    }
    [data-theme="dark"] .status-linked {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, 0.4);
    }

    .status-awaiting {
        background-color: rgba(245, 158, 11, 0.18);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.35);
    }
    [data-theme="dark"] .status-awaiting {
        background-color: rgba(245, 158, 11, 0.25);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.45);
    }

    .status-available {
        background-color: rgba(249, 115, 22, 0.15);
        color: #ea580c;
        border-color: rgba(249, 115, 22, 0.3);
    }
    [data-theme="dark"] .status-available {
        background-color: rgba(249, 115, 22, 0.2);
        color: #fb923c;
        border-color: rgba(249, 115, 22, 0.4);
    }

    /* ----------------------------------------------------------------- */
    /* Checkboxes                                                        */
    /* ----------------------------------------------------------------- */
    .property-checkbox,
    #selectAllCheckbox {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    /* Search input */
    input[type="search"]::-webkit-search-cancel-button {
        cursor: pointer;
        opacity: 0.6;
    }
    input[type="search"]::-webkit-search-cancel-button:hover {
        opacity: 1;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    // ✅ If linking is not permitted, the bulk bar + checkboxes
    //    were not rendered. Skip all JS wiring entirely.
    const canLink = {{ $canLinkProperties ? 'true' : 'false' }};
    if (!canLink) {
        return;
    }

    const bulkBar     = document.getElementById('bulkActionsBar');
    const bulkCountEl = document.getElementById('bulkSelectedCount');
    const selectAll   = document.getElementById('selectAllCheckbox');
    const linkBtn     = document.getElementById('bulkLinkBtn');
    const approveBtn  = document.getElementById('bulkApproveBtn');
    const clearBtn    = document.getElementById('bulkClearBtn');
    const searchInput = document.getElementById('searchInput');

    const checkboxes = () => Array.from(document.querySelectorAll('.property-checkbox'));

    function selectedIds() {
        return checkboxes().filter(cb => cb.checked).map(cb => cb.value);
    }

    function selectedIdsLandlordRequested() {
        return checkboxes()
            .filter(cb => cb.checked && cb.dataset.landlordRequested === '1')
            .map(cb => cb.value);
    }

    function refreshBulkBar() {
        const ids = selectedIds();
        if (bulkBar) bulkBar.classList.toggle('hidden', ids.length === 0);
        if (bulkCountEl) bulkCountEl.textContent = ids.length;
        if (selectAll) {
            const all = checkboxes();
            const allChecked = all.length > 0 && all.every(cb => cb.checked);
            selectAll.checked = allChecked;
            selectAll.indeterminate = !allChecked && ids.length > 0;
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('property-checkbox')) {
            refreshBulkBar();
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const target = this.checked;
            checkboxes().forEach(cb => { cb.checked = target; });
            refreshBulkBar();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            checkboxes().forEach(cb => { cb.checked = false; });
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            refreshBulkBar();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.form && this.form.submit();
            }
        });
    }

    function submitIds(url, ids, extra = {}) {
        if (!ids.length) {
            alert('Please select at least one property.');
            return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
        form.appendChild(csrf);

        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'property_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        Object.entries(extra).forEach(([k, v]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    if (linkBtn) {
        linkBtn.addEventListener('click', function () {
            submitIds("{{ route('sanitation.properties.bulk-link') }}", selectedIds());
        });
    }

    if (approveBtn) {
        approveBtn.addEventListener('click', function () {
            const ids = selectedIdsLandlordRequested();
            if (!ids.length) {
                alert('Please select at least one landlord-requested property.');
                return;
            }
            submitIds("{{ route('sanitation.properties.bulk-approve') }}", ids);
        });
    }

    refreshBulkBar();
})();
</script>
@endpush