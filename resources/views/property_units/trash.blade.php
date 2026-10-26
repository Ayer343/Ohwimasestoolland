{{-- property_units/trash.blade.php --}}
@php
    use App\Models\PropertyUnit;

    $user = auth()->user();

    /* ============================================================
       ROLE / DASHBOARD RESOLUTION
       ============================================================ */
    $isLandlord   = $user->isLandlord();
    $isAdmin      = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();

    if ($isLandlord) {
        $layout      = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout      = 'layouts.app';
        $routePrefix = 'admin.property-units';
    } else {
        $layout      = 'layouts.app';
        $routePrefix = 'property-units';
    }

    /* ============================================================
       FAMILY-LINK FLAGS (safe fallbacks)
       ============================================================ */
    $isOwner              = $isOwner              ?? ($isLandlord && $unit->property ?? false ? false : false);
    $isLinkedFamilyMember = $isLinkedFamilyMember ?? false;
    $hasManageUnitsAccess = $hasManageUnitsAccess ?? false;

    /* ============================================================
       TRASH PERMISSIONS
       ------------------------------------------------------------
       Trash is a destructive surface. Restore and permanent-delete
       are OWNER-ONLY — even a linked family member with
       `manage_units` cannot resurrect or destroy units from here.
       They can, however, view the trash for properties they manage.
       ============================================================ */
    $canRestoreUnits      = $isOwner;
    $canForceDeleteUnits  = $isOwner;
    $canEmptyTrash        = $isOwner && $trashedUnits->total() > 0;
    $canBulkRestore       = $isOwner && $trashedUnits->total() > 0;

    /* ============================================================
       STATS / ROUTES
       ============================================================ */
    $totalTrashed  = $trashedUnits->total();
    $emptyTrashUrl = route($routePrefix . '.empty-trash');

    /* ============================================================
       FLASH
       ============================================================ */
    $successMessage = session('success');
    $errorMessage   = session('error');
    $warningMessage = session('warning');
@endphp

@extends($layout)

@section('title', 'Trash / Recycle Bin')

@section('content')
<div id="property-units-trash-page" class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, #ff6b6b 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash mr-2" style="color: var(--danger);"></i>
                        Trash / Recycle Bin
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and manage deleted property units. Items are automatically deleted after 30 days.</span>
                        @if($totalTrashed > 0)
                            <span class="mx-2">•</span>
                            <i class="fas fa-box mr-1"></i>
                            <span>{{ $totalTrashed }} deleted unit{{ $totalTrashed > 1 ? 's' : '' }}</span>
                        @endif

                        @if($isLinkedFamilyMember && !$isOwner)
                            <span class="mx-2">•</span>
                            <i class="fas fa-user-friends mr-1"></i>
                            <span>Linked properties</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route($routePrefix . '.index') }}"
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-building mr-1"></i> Back to Units
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FLASH
         ============================================================ --}}
    @if($successMessage)
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="font-bold">Success!</span>
                <span class="ml-2">{{ $successMessage }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span class="font-bold">Error!</span>
                <span class="ml-2">{{ $errorMessage }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if($warningMessage)
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="font-bold">Warning!</span>
                <span class="ml-2">{{ $warningMessage }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- ============================================================
         FAMILY-MEMBER BANNER
         ============================================================ --}}
    @if($isLinkedFamilyMember && !$isOwner)
        <div class="card border-l-4"
             style="border-left-color: var(--info); background-color: rgba(var(--info-rgb), 0.05);">
            <div class="p-4 flex items-start">
                <i class="fas fa-user-friends text-xl mr-3 mt-0.5"
                   style="color: var(--info);"></i>
                <div class="flex-1">
                    <p class="font-medium" style="color: var(--text-primary);">
                        You're viewing the trash for properties you're linked to.
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Restoring units and permanently deleting them is reserved for the property owner.
                        Contact the owner if a unit needs to be restored.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         INFO ALERT
         ============================================================ --}}
    <div class="card border-l-4 mb-6" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2"></i> Important Information
                    </h4>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        Items in the trash bin are temporarily stored and will be automatically
                        <strong>permanently deleted after 30 days</strong>.
                        @if($canRestoreUnits)
                            You can restore items or permanently delete them immediately.
                        @else
                            Only the property owner can restore or permanently delete items.
                        @endif
                    </p>
                    <div class="flex items-center space-x-4 text-sm">
                        <span class="flex items-center">
                            <i class="fas fa-undo-alt mr-2" style="color: var(--success);"></i>
                            <span style="color: var(--text-secondary);">Restore: Bring back to active units</span>
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                            <span style="color: var(--text-secondary);">Permanent Delete: Cannot be undone</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         BULK ACTIONS — owner only
         ============================================================ --}}
    @if($totalTrashed > 0 && $isOwner)
        <div class="card mb-6">
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-semibold mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-tasks mr-2"></i> Bulk Actions
                        </h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Perform actions on all deleted units at once.
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="showEmptyTrashConfirmation()"
                                class="px-4 py-2 rounded-lg font-medium text-white btn-danger">
                            <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                        </button>
                        <button onclick="restoreAll()"
                                class="px-4 py-2 rounded-lg font-medium btn-success">
                            <i class="fas fa-undo-alt mr-2"></i> Restore All
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         FILTERS + TABLE
         ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Filters Sidebar --}}
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>

                    <form method="GET" action="{{ route($routePrefix . '.trash') }}" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text"
                                       name="search"
                                       value="{{ request('search') }}"
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Unit number, name, tenant..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        @if($properties && $properties->count() > 0)
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-building mr-1"></i> Property
                                </label>
                                <select name="property_id" class="index-custom-dropdown w-full">
                                    <option value="">All Properties</option>
                                    @foreach($properties as $property)
                                        @php
                                            $pOwned  = (int) $property->landlord_id === (int) $user->id;
                                            $pLinked = $isLinkedFamilyMember && !$pOwned;
                                        @endphp
                                        <option value="{{ $property->id }}"
                                            {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                            {{ $property->property_name }}
                                            @if($pLinked)
                                                — family linked
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-home mr-1"></i> Unit Type
                            </label>
                            <select name="unit_type" class="index-custom-dropdown w-full">
                                <option value="">All Types</option>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" {{ request('unit_type') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort mr-1"></i> Sort By
                            </label>
                            <select name="sort" class="index-custom-dropdown w-full">
                                <option value="deleted_at" {{ request('sort', 'deleted_at') == 'deleted_at' ? 'selected' : '' }}>Deletion Date</option>
                                <option value="unit_number" {{ request('sort') == 'unit_number' ? 'selected' : '' }}>Unit Number</option>
                                <option value="monthly_rent" {{ request('sort') == 'monthly_rent' ? 'selected' : '' }}>Monthly Rent</option>
                                <option value="created_at" {{ request('sort') == 'created_at' ? 'selected' : '' }}>Creation Date</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort-amount-down mr-1"></i> Sort Direction
                            </label>
                            <select name="direction" class="index-custom-dropdown w-full">
                                <option value="desc" {{ request('direction', 'desc') == 'desc' ? 'selected' : '' }}>Newest First</option>
                                <option value="asc" {{ request('direction') == 'asc' ? 'selected' : '' }}>Oldest First</option>
                            </select>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit"
                                        class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>

                                <a href="{{ route($routePrefix . '.trash') }}"
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>

                                <div class="flex items-center justify-center pt-2"
                                     style="background-color: rgba(var(--secondary-rgb), 0.1); border-radius: 0.375rem; padding: 0.25rem;">
                                    <button id="tableViewBtn" type="button" class="px-3 py-1 rounded-md" style="background-color: var(--card-bg);">
                                        <i class="fas fa-table" style="color: var(--primary);"></i>
                                    </button>
                                    <button id="cardViewBtn" type="button" class="px-3 py-1 rounded-md">
                                        <i class="fas fa-th-large" style="color: var(--text-secondary);"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Table / Cards --}}
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Deleted Units ({{ $totalTrashed }})
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($totalTrashed > 0)
                                Showing {{ $trashedUnits->firstItem() }} to {{ $trashedUnits->lastItem() }} of {{ $totalTrashed }} deleted units
                            @else
                                No deleted units found
                            @endif
                        </p>
                    </div>

                    @if($totalTrashed > 0)
                        <div class="flex items-center space-x-3 mt-4 md:mt-0">
                            <div class="flex items-center">
                                <span class="text-sm mr-2" style="color: var(--text-secondary);">Show:</span>
                                <select onchange="updatePerPage(this.value)"
                                        class="index-custom-dropdown text-sm pl-3 pr-8 py-1 rounded-lg appearance-none">
                                    <option value="10" {{ request('per_page', '20') == '10' ? 'selected' : '' }}>10</option>
                                    <option value="20" {{ request('per_page', '20') == '20' ? 'selected' : '' }}>20</option>
                                    <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                        </div>
                    @endif
                </div>

                @if($totalTrashed == 0)
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <i class="fas fa-trash text-2xl" style="color: var(--danger);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            Trash Bin is Empty
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            No deleted property units found. Items you delete will appear here before being permanently removed.
                        </p>
                        <div class="space-x-3">
                            <a href="{{ route($routePrefix . '.index') }}"
                               class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-building mr-2"></i> View Active Units
                            </a>
                        </div>
                    </div>
                @else
                    {{-- TABLE VIEW --}}
                    <div id="tableView" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Unit Details</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted By</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted At</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trashedUnits as $unit)
                                    @php
                                        $unitPropertyOwned = $unit->property
                                            && (int) $unit->property->landlord_id === (int) $user->id;
                                        $unitPropertyLinked = $isLinkedFamilyMember && !$unitPropertyOwned;
                                    @endphp
                                    <tr>
                                        <td class="p-3">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                                    <i class="fas fa-home" style="color: var(--danger);"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">
                                                        {{ $unit->unit_number }}
                                                        @if($unit->unit_name)
                                                            - {{ $unit->unit_name }}
                                                        @endif
                                                    </div>
                                                    <div class="text-xs flex items-center mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-home mr-1"></i>
                                                        <span class="mr-2">{{ $unit->unit_type_label ?? $unit->unit_type }}</span>
                                                        <i class="fas fa-money-bill-wave mr-1 ml-2"></i>
                                                        <span>GHS {{ number_format($unit->monthly_rent, 2) }}</span>
                                                    </div>
                                                    @if($unit->tenant)
                                                        <div class="text-xs flex items-center mt-1" style="color: var(--text-secondary);">
                                                            <i class="fas fa-user mr-1"></i>
                                                            <span>Tenant: {{ $unit->tenant->name }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <td class="p-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $unit->property->property_name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $unit->property->address ?? '' }}
                                            </div>
                                            @if($unitPropertyLinked)
                                                <span class="inline-flex items-center mt-1 text-xs px-2 py-0.5 rounded-full badge-info">
                                                    <i class="fas fa-user-friends mr-1"></i> Linked Property
                                                </span>
                                            @endif
                                        </td>

                                        <td class="p-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $unit->creator->name ?? 'System' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                @if($unit->creator)
                                                    {{ $unit->creator->email }}
                                                @endif
                                            </div>
                                        </td>

                                        <td class="p-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $unit->deleted_at->format('M d, Y') }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $unit->deleted_at->format('h:i A') }}
                                                @php $daysDeleted = $unit->deleted_at->diffInDays(now()); @endphp
                                                <div class="mt-1">
                                                    @if($daysDeleted < 7)
                                                        <span class="px-2 py-1 rounded-full text-xs badge-success">
                                                            Deleted {{ $daysDeleted }} day{{ $daysDeleted != 1 ? 's' : '' }} ago
                                                        </span>
                                                    @elseif($daysDeleted < 30)
                                                        <span class="px-2 py-1 rounded-full text-xs badge-warning">
                                                            Deleted {{ $daysDeleted }} days ago
                                                        </span>
                                                    @else
                                                        <span class="px-2 py-1 rounded-full text-xs badge-danger">
                                                            Deleted {{ $daysDeleted }} days ago
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <td class="p-3">
                                            <div class="action-buttons">
                                                @if($canRestoreUnits && $unitPropertyOwned)
                                                    <button onclick="showRestoreConfirmation({{ $unit->id }}, @js($unit->unit_number))"
                                                            class="action-btn restore"
                                                            data-tooltip="Restore this unit">
                                                        <i class="fas fa-undo-alt"></i>
                                                        <span class="hidden md:inline">Restore</span>
                                                    </button>
                                                @endif

                                                @if($canForceDeleteUnits && $unitPropertyOwned)
                                                    <button onclick="showForceDeleteConfirmation({{ $unit->id }}, @js($unit->unit_number))"
                                                            class="action-btn delete"
                                                            data-tooltip="Permanently delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                        <span class="hidden md:inline">Delete</span>
                                                    </button>
                                                @endif

                                                <a href="{{ route($routePrefix . '.show', $unit->id) }}"
                                                   class="action-btn view"
                                                   target="_blank"
                                                   data-tooltip="View details">
                                                    <i class="fas fa-eye"></i>
                                                    <span class="hidden md:inline">View</span>
                                                </a>

                                                @if($isLinkedFamilyMember && !$unitPropertyOwned && !$canRestoreUnits)
                                                    <span class="text-xs self-center px-2 py-0.5 rounded-full"
                                                          style="background: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                          data-tooltip="Owner must restore or delete">
                                                        <i class="fas fa-lock mr-1"></i> Owner only
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- CARD VIEW --}}
                    <div id="cardView" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($trashedUnits as $unit)
                            @php
                                $unitPropertyOwned = $unit->property
                                    && (int) $unit->property->landlord_id === (int) $user->id;
                                $unitPropertyLinked = $isLinkedFamilyMember && !$unitPropertyOwned;
                                $daysDeleted = $unit->deleted_at->diffInDays(now());
                            @endphp
                            <div class="card border" style="border-color: var(--border-color);">
                                <div class="p-4">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                                 style="background-color: rgba(var(--danger-rgb), 0.1);">
                                                <i class="fas fa-home" style="color: var(--danger);"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $unit->unit_number }}
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    {{ $unit->property->property_name ?? 'N/A' }}
                                                </div>
                                                @if($unitPropertyLinked)
                                                    <span class="inline-flex items-center mt-1 text-xs px-2 py-0.5 rounded-full badge-info">
                                                        <i class="fas fa-user-friends mr-1"></i> Linked
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($daysDeleted < 7)
                                            <span class="px-2 py-1 rounded-full text-xs badge-success">{{ $daysDeleted }}d</span>
                                        @elseif($daysDeleted < 30)
                                            <span class="px-2 py-1 rounded-full text-xs badge-warning">{{ $daysDeleted }}d</span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-xs badge-danger">{{ $daysDeleted }}d</span>
                                        @endif
                                    </div>

                                    <div class="space-y-2 mb-4">
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-home mr-2"></i>
                                            {{ $unit->unit_type_label ?? $unit->unit_type }}
                                        </div>
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-money-bill-wave mr-2"></i>
                                            GHS {{ number_format($unit->monthly_rent, 2) }}
                                        </div>
                                        @if($unit->tenant)
                                            <div class="text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-user mr-2"></i>
                                                {{ $unit->tenant->name }}
                                            </div>
                                        @endif
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-trash mr-2"></i>
                                            {{ $unit->deleted_at->format('M d, Y') }}
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        @if($canRestoreUnits && $unitPropertyOwned)
                                            <button onclick="showRestoreConfirmation({{ $unit->id }}, @js($unit->unit_number))"
                                                    class="action-btn restore">
                                                <i class="fas fa-undo-alt"></i> Restore
                                            </button>
                                        @endif
                                        @if($canForceDeleteUnits && $unitPropertyOwned)
                                            <button onclick="showForceDeleteConfirmation({{ $unit->id }}, @js($unit->unit_number))"
                                                    class="action-btn delete">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        @endif
                                        @if($isLinkedFamilyMember && !$unitPropertyOwned)
                                            <span class="text-xs self-center px-2 py-0.5 rounded-full"
                                                  style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-lock mr-1"></i> Owner only
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- PAGINATION --}}
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $trashedUnits->firstItem() }} to {{ $trashedUnits->lastItem() }} of {{ $totalTrashed }} entries
                        </div>
                        <div class="pagination">
                            {{ $trashedUnits->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     EMPTY TRASH MODAL — owner only
     ============================================================ --}}
@if($canEmptyTrash)
    <div id="emptyTrashModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideEmptyTrashModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
                <div class="modal-header">
                    <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Empty Trash Bin
                    </h3>
                    <button type="button" onclick="hideEmptyTrashModal()" class="modal-close-btn">
                        <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
                <form method="POST" action="{{ $emptyTrashUrl }}" id="emptyTrashForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-4">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                    <i class="fas fa-exclamation-triangle text-lg" style="color: var(--danger);"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold" style="color: var(--danger);">Warning!</h4>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        You are about to permanently delete ALL items in the trash bin.
                                    </p>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <p class="font-medium text-center mb-2" style="color: var(--danger);">
                                    This action will affect {{ $totalTrashed }} unit{{ $totalTrashed > 1 ? 's' : '' }}
                                </p>
                                <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>All deleted units will be permanently removed</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>This action cannot be undone</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>Associated data (invoices, maintenance requests) will also be deleted</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mb-4">
                                <label for="empty_confirmation" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Type "EMPTY TRASH" to confirm:
                                </label>
                                <input type="text"
                                       id="empty_confirmation"
                                       name="confirm_empty_trash"
                                       class="index-custom-input w-full"
                                       placeholder="Type EMPTY TRASH here"
                                       oninput="checkEmptyConfirmation(this)">
                                <p class="text-xs mt-1" style="color: var(--text-secondary);" id="emptyConfirmationMessage">
                                    Type exactly "EMPTY TRASH" (without quotes) to enable the button
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" onclick="hideEmptyTrashModal()"
                                class="btn-secondary px-4 py-2 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                id="confirmEmptyBtn"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white opacity-50 cursor-not-allowed"
                                disabled>
                            <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

{{-- ============================================================
     RESTORE MODAL — owner only
     ============================================================ --}}
@if($canRestoreUnits)
    <div id="restoreModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRestoreModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
                <div class="modal-header">
                    <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-undo-alt mr-2" style="color: var(--success);"></i> Restore Unit
                    </h3>
                    <button type="button" onclick="hideRestoreModal()" class="modal-close-btn">
                        <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
                <form method="POST" action="" id="restoreForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-4">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1);">
                                    <i class="fas fa-undo-alt text-lg" style="color: var(--success);"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold" style="color: var(--success);">Restore Unit</h4>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        You are about to restore a deleted unit.
                                    </p>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <p class="font-medium text-center mb-2" id="restoreUnitNumber" style="color: var(--info);"></p>
                                <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                    <li class="flex items-start">
                                        <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                        <span>Unit will be restored to active status</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                        <span>Any existing tenant assignments will be preserved</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                        <span>Unit will appear in the main units list</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="mb-2">Are you sure you want to restore this unit?</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" onclick="hideRestoreModal()"
                                class="btn-secondary px-4 py-2 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                            <i class="fas fa-undo-alt mr-2"></i> Restore Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

{{-- ============================================================
     FORCE DELETE MODAL — owner only
     ============================================================ --}}
@if($canForceDeleteUnits)
    <div id="forceDeleteModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideForceDeleteModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
                <div class="modal-header">
                    <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Permanent Deletion
                    </h3>
                    <button type="button" onclick="hideForceDeleteModal()" class="modal-close-btn">
                        <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
                <form method="POST" action="" id="forceDeleteForm">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body">
                        <div class="mb-4">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                    <i class="fas fa-exclamation-triangle text-lg" style="color: var(--danger);"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold" style="color: var(--danger);">Permanent Deletion</h4>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        You are about to permanently delete a unit.
                                    </p>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <p class="font-medium text-center mb-2" id="deleteUnitNumber" style="color: var(--danger);"></p>
                                <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>This action cannot be undone</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>All unit data will be permanently deleted</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>Associated invoices, maintenance requests, and documents will be deleted</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                        <span>Tenant data associated with this unit will be preserved</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mb-4">
                                <label for="force_delete_confirmation" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Type "DELETE PERMANENTLY" to confirm:
                                </label>
                                <input type="text"
                                       id="force_delete_confirmation"
                                       name="confirm_force_delete"
                                       class="index-custom-input w-full"
                                       placeholder="Type DELETE PERMANENTLY here"
                                       oninput="checkForceDeleteConfirmation(this)">
                                <p class="text-xs mt-1" style="color: var(--text-secondary);" id="forceDeleteConfirmationMessage">
                                    Type exactly "DELETE PERMANENTLY" (without quotes) to enable the button
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" onclick="hideForceDeleteModal()"
                                class="btn-secondary px-4 py-2 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                id="confirmForceDeleteBtn"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white opacity-50 cursor-not-allowed"
                                disabled>
                            <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
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
    // Server facts to JS once.
    window.__PU = {
        isOnAdminDashboard: @json($isAdmin || $isSuperAdmin),
        isOnLandlordDashboard: @json($isLandlord),
        isOwner: @json($isOwner),
        canRestoreUnits: @json($canRestoreUnits),
        canForceDeleteUnits: @json($canForceDeleteUnits),
        indexUrl: @json(route($routePrefix . '.index')),
    };

    document.addEventListener('DOMContentLoaded', function () {
        initViewToggle();
        initTooltips();
        autoHideMessages();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideEmptyTrashModal();
                hideRestoreModal();
                hideForceDeleteModal();
            }
        });
    });

    /* ------------------------------------------------------------
       VIEW TOGGLE
       ------------------------------------------------------------ */
    function initViewToggle() {
        const tableViewBtn = document.getElementById('tableViewBtn');
        const cardViewBtn  = document.getElementById('cardViewBtn');
        const tableView    = document.getElementById('tableView');
        const cardView     = document.getElementById('cardView');

        if (!tableViewBtn || !cardViewBtn || !tableView || !cardView) return;

        const setTableActive = () => {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
            tableViewBtn.style.backgroundColor = 'var(--card-bg)';
            tableViewBtn.querySelector('i').style.color = 'var(--primary)';
            cardViewBtn.style.backgroundColor = 'transparent';
            cardViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
        };

        const setCardActive = () => {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
            tableViewBtn.style.backgroundColor = 'transparent';
            tableViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            cardViewBtn.style.backgroundColor = 'var(--card-bg)';
            cardViewBtn.querySelector('i').style.color = 'var(--primary)';
        };

        const savedView = localStorage.getItem('propertyUnitsTrashView') || 'table';
        savedView === 'card' ? setCardActive() : setTableActive();

        tableViewBtn.addEventListener('click', () => {
            setTableActive();
            localStorage.setItem('propertyUnitsTrashView', 'table');
        });
        cardViewBtn.addEventListener('click', () => {
            setCardActive();
            localStorage.setItem('propertyUnitsTrashView', 'card');
        });
    }

    /* ------------------------------------------------------------
       TOOLTIPS
       ------------------------------------------------------------ */
    function initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', function () {
                const tooltip = document.createElement('div');
                tooltip.className = 'fixed z-50 px-2 py-1 text-xs rounded-lg';
                Object.assign(tooltip.style, {
                    backgroundColor: 'var(--card-bg)',
                    color: 'var(--text-primary)',
                    border: '1px solid var(--border-color)',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
                });
                tooltip.textContent = this.getAttribute('data-tooltip');
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
                tooltip.style.top = (rect.bottom + 5) + 'px';

                this._tooltip = tooltip;
            });
            el.addEventListener('mouseleave', function () {
                if (this._tooltip) {
                    this._tooltip.remove();
                    delete this._tooltip;
                }
            });
        });
    }

    /* ------------------------------------------------------------
       FLASH AUTO-HIDE
       ------------------------------------------------------------ */
    function autoHideMessages() {
        setTimeout(() => {
            document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100')
                .forEach(msg => {
                    if (msg.style.display !== 'none') msg.style.display = 'none';
                });
        }, 5000);
    }

    /* ------------------------------------------------------------
       PER-PAGE
       ------------------------------------------------------------ */
    function updatePerPage(value) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', value);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    /* ------------------------------------------------------------
       EMPTY TRASH
       ------------------------------------------------------------ */
    function showEmptyTrashConfirmation() {
        const modal = document.getElementById('emptyTrashModal');
        const confirmationInput = document.getElementById('empty_confirmation');
        const confirmBtn = document.getElementById('confirmEmptyBtn');
        if (!modal || !confirmationInput || !confirmBtn) return;

        confirmationInput.value = '';
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => confirmationInput.focus(), 100);
    }

    function hideEmptyTrashModal() {
        const modal = document.getElementById('emptyTrashModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    function checkEmptyConfirmation(input) {
        const confirmBtn = document.getElementById('confirmEmptyBtn');
        const messageElement = document.getElementById('emptyConfirmationMessage');
        if (!confirmBtn || !messageElement) return;

        if (input.value === 'EMPTY TRASH') {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--success)';
            messageElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmation matches. You can now empty trash.';
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--danger)';
            messageElement.innerHTML = `Type exactly "EMPTY TRASH" (without quotes) to enable the button. You typed: "${input.value}"`;
        }
    }

    /* ------------------------------------------------------------
       RESTORE
       ------------------------------------------------------------ */
    function showRestoreConfirmation(unitId, unitNumber) {
        const modal = document.getElementById('restoreModal');
        const form = document.getElementById('restoreForm');
        const unitNumberElement = document.getElementById('restoreUnitNumber');
        if (!modal || !form) return;

        form.action = window.__PU.isOnLandlordDashboard
            ? '/landlord/property-units/' + unitId + '/restore-from-trash'
            : window.__PU.isOnAdminDashboard
                ? '/admin/property-units/' + unitId + '/restore-from-trash'
                : '/property-units/' + unitId + '/restore-from-trash';

        if (unitNumberElement) {
            unitNumberElement.textContent = 'Unit: ' + unitNumber;
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function hideRestoreModal() {
        const modal = document.getElementById('restoreModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    /* ------------------------------------------------------------
       FORCE DELETE
       ------------------------------------------------------------ */
    function showForceDeleteConfirmation(unitId, unitNumber) {
        const modal = document.getElementById('forceDeleteModal');
        const form = document.getElementById('forceDeleteForm');
        const confirmationInput = document.getElementById('force_delete_confirmation');
        const confirmBtn = document.getElementById('confirmForceDeleteBtn');
        const unitNumberElement = document.getElementById('deleteUnitNumber');
        if (!modal || !form || !confirmationInput || !confirmBtn) return;

        form.action = window.__PU.isOnLandlordDashboard
            ? '/landlord/property-units/' + unitId + '/force-delete'
            : window.__PU.isOnAdminDashboard
                ? '/admin/property-units/' + unitId + '/force-delete'
                : '/property-units/' + unitId + '/force-delete';

        if (unitNumberElement) {
            unitNumberElement.textContent = 'Unit: ' + unitNumber;
        }

        confirmationInput.value = '';
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => confirmationInput.focus(), 100);
    }

    function hideForceDeleteModal() {
        const modal = document.getElementById('forceDeleteModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    function checkForceDeleteConfirmation(input) {
        const confirmBtn = document.getElementById('confirmForceDeleteBtn');
        const messageElement = document.getElementById('forceDeleteConfirmationMessage');
        if (!confirmBtn || !messageElement) return;

        if (input.value === 'DELETE PERMANENTLY') {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--success)';
            messageElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmation matches. You can now delete permanently.';
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--danger)';
            messageElement.innerHTML = `Type exactly "DELETE PERMANENTLY" (without quotes) to enable the button. You typed: "${input.value}"`;
        }
    }

    /* ------------------------------------------------------------
       BULK RESTORE (stub — kept for compatibility)
       ------------------------------------------------------------ */
    function restoreAll() {
        if (!window.__PU.canRestoreUnits) {
            alert('Only the property owner can restore units.');
            return;
        }
        if (confirm('Are you sure you want to restore ALL deleted units?')) {
            alert('Bulk restore feature is not yet implemented. Please restore units individually.');
        }
    }
</script>
@endsection

@push('styles')
<style>
    /* ------------------------------------------------------------
       Form controls — scoped to this page
       ------------------------------------------------------------ */
    #property-units-trash-page .index-custom-dropdown {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        width: 100%;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }

    [data-theme="dark"] #property-units-trash-page .index-custom-dropdown {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    }

    [data-theme="light"] #property-units-trash-page .index-custom-dropdown {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    }

    #property-units-trash-page .index-custom-dropdown:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    #property-units-trash-page .index-custom-dropdown option {
        background-color: var(--card-bg);
        color: var(--text-primary);
    }

    [data-theme="dark"] #property-units-trash-page .index-custom-dropdown option {
        background-color: #2a2a3c;
        color: #e4e4e4;
    }

    [data-theme="light"] #property-units-trash-page .index-custom-dropdown option {
        background-color: #ffffff;
        color: #4b4b4b;
    }

    #property-units-trash-page .index-custom-input,
    #property-units-trash-page .index-custom-textarea {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        width: 100%;
        transition: all 0.3s ease;
    }

    #property-units-trash-page .index-custom-textarea { resize: vertical; }

    #property-units-trash-page .index-custom-input:focus,
    #property-units-trash-page .index-custom-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    #property-units-trash-page .index-custom-input:disabled {
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
        cursor: not-allowed;
    }

    #property-units-trash-page .index-custom-checkbox {
        width: 1rem;
        height: 1rem;
        border-radius: 0.25rem;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        cursor: pointer;
        transition: all 0.2s;
    }

    #property-units-trash-page .index-custom-checkbox:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    #property-units-trash-page .index-custom-checkbox:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    #property-units-trash-page .index-custom-input::placeholder,
    #property-units-trash-page .index-custom-textarea::placeholder {
        color: var(--text-secondary);
        opacity: 0.7;
    }

    #property-units-trash-page .index-custom-dropdown:focus-visible,
    #property-units-trash-page .index-custom-input:focus-visible,
    #property-units-trash-page .index-custom-textarea:focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }

    /* ------------------------------------------------------------
       Table — scoped so it doesn't leak into the layout
       ------------------------------------------------------------ */
    #property-units-trash-page table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    #property-units-trash-page table th {
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.75rem;
        padding: 0.75rem;
        border-bottom: 2px solid var(--border-color);
        background-color: var(--bg-secondary) !important;
    }

    #property-units-trash-page table td {
        padding: 0.75rem;
        border-bottom: 1px solid var(--border-color);
        vertical-align: top;
        background-color: var(--card-bg) !important;
    }

    #property-units-trash-page table tr:last-child td { border-bottom: none; }

    #property-units-trash-page table tr,
    #property-units-trash-page table tr:hover,
    #property-units-trash-page table tr:hover td {
        background-color: var(--card-bg) !important;
    }

    /* ------------------------------------------------------------
       Card view
       ------------------------------------------------------------ */
    #property-units-trash-page #cardView .card {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        background-color: var(--card-bg);
        animation: fadeIn 0.3s ease-out;
    }

    #property-units-trash-page #cardView .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }

    /* ------------------------------------------------------------
       Badges
       ------------------------------------------------------------ */
    #property-units-trash-page .badge-success,
    #property-units-trash-page .badge-warning,
    #property-units-trash-page .badge-danger,
    #property-units-trash-page .badge-info,
    #property-units-trash-page .badge-primary,
    #property-units-trash-page .badge-secondary {
        border-width: 1px !important;
        border-style: solid !important;
    }

    #property-units-trash-page .badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border-color: rgba(var(--success-rgb), 0.3) !important; }
    #property-units-trash-page .badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border-color: rgba(var(--warning-rgb), 0.3) !important; }
    #property-units-trash-page .badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border-color: rgba(var(--danger-rgb), 0.3) !important; }
    #property-units-trash-page .badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border-color: rgba(var(--info-rgb), 0.3) !important; }
    #property-units-trash-page .badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border-color: rgba(var(--primary-rgb), 0.3) !important; }
    #property-units-trash-page .badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border-color: rgba(var(--secondary-rgb), 0.3) !important; }

    /* ------------------------------------------------------------
       Buttons — scoped to page + modals
       ------------------------------------------------------------ */
    #property-units-trash-page .btn-primary,
    #emptyTrashModal .btn-primary,
    #restoreModal .btn-primary,
    #forceDeleteModal .btn-primary {
        background-color: var(--primary) !important;
        color: white !important;
        border: 1px solid var(--primary) !important;
        transition: all 0.2s ease;
    }

    #property-units-trash-page .btn-primary:hover,
    #emptyTrashModal .btn-primary:hover,
    #restoreModal .btn-primary:hover,
    #forceDeleteModal .btn-primary:hover {
        background-color: var(--secondary) !important;
        border-color: var(--secondary) !important;
        transform: translateY(-1px);
    }

    #property-units-trash-page .btn-secondary,
    #emptyTrashModal .btn-secondary,
    #restoreModal .btn-secondary,
    #forceDeleteModal .btn-secondary {
        background-color: rgba(var(--secondary-rgb), 0.1) !important;
        color: var(--secondary) !important;
        border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
        transition: all 0.2s ease;
    }

    #property-units-trash-page .btn-secondary:hover,
    #emptyTrashModal .btn-secondary:hover,
    #restoreModal .btn-secondary:hover,
    #forceDeleteModal .btn-secondary:hover {
        background-color: rgba(var(--secondary-rgb), 0.2) !important;
        transform: translateY(-1px);
    }

    #property-units-trash-page .btn-danger,
    #emptyTrashModal .btn-danger,
    #forceDeleteModal .btn-danger {
        background-color: var(--danger) !important;
        color: white !important;
        border: 1px solid var(--danger) !important;
        transition: all 0.2s ease;
    }

    #property-units-trash-page .btn-danger:hover,
    #emptyTrashModal .btn-danger:hover,
    #forceDeleteModal .btn-danger:hover {
        background-color: #dc3545 !important;
        transform: translateY(-1px);
    }

    #property-units-trash-page .btn-success,
    #restoreModal .btn-success {
        background-color: var(--success) !important;
        color: white !important;
        border: 1px solid var(--success) !important;
        transition: all 0.2s ease;
    }

    #property-units-trash-page .btn-success:hover,
    #restoreModal .btn-success:hover {
        background-color: #28a745 !important;
        transform: translateY(-1px);
    }

    /* ------------------------------------------------------------
       Modal containers
       ------------------------------------------------------------ */
    .modal-container {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 16px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-body { padding: 1.5rem; }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .modal-close-btn {
        padding: 0.5rem;
        border-radius: 0.375rem;
        transition: background-color 0.2s;
        cursor: pointer;
        background: none;
        border: none;
    }

    .modal-close-btn:hover { background-color: rgba(0, 0, 0, 0.05); }

    /* ------------------------------------------------------------
       Action buttons
       ------------------------------------------------------------ */
    #property-units-trash-page .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    #property-units-trash-page .action-btn {
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
    }

    #property-units-trash-page .action-btn.view     { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border-color: rgba(var(--info-rgb), 0.3); }
    #property-units-trash-page .action-btn.edit     { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3); }
    #property-units-trash-page .action-btn.assign   { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3); }
    #property-units-trash-page .action-btn.delete   { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger);  border-color: rgba(var(--danger-rgb), 0.3); }
    #property-units-trash-page .action-btn.restore  { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3); }
    #property-units-trash-page .action-btn.vacate   { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3); }

    #property-units-trash-page .action-btn.view:hover,
    #property-units-trash-page .action-btn.edit:hover,
    #property-units-trash-page .action-btn.assign:hover,
    #property-units-trash-page .action-btn.delete:hover,
    #property-units-trash-page .action-btn.restore:hover,
    #property-units-trash-page .action-btn.vacate:hover {
        transform: translateY(-1px);
    }

    #property-units-trash-page .action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }

    /* ------------------------------------------------------------
       Scrollbar
       ------------------------------------------------------------ */
    #property-units-trash-page .custom-scrollbar::-webkit-scrollbar       { width: 6px; }
    #property-units-trash-page .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.05); border-radius: 3px; }
    #property-units-trash-page .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(var(--primary-rgb), 0.3); border-radius: 3px; }
    #property-units-trash-page .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(var(--primary-rgb), 0.5); }

    /* ------------------------------------------------------------
       Pagination — scoped so it doesn't fight other pages
       ------------------------------------------------------------ */
    #property-units-trash-page .pagination .pagination {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 0.25rem;
    }

    #property-units-trash-page .pagination .pagination li a,
    #property-units-trash-page .pagination .pagination li span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.5rem;
        height: 2.5rem;
        padding: 0 0.75rem;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    #property-units-trash-page .pagination .pagination li a:hover:not(.disabled) {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        border-color: var(--primary);
    }

    #property-units-trash-page .pagination .pagination li.active span {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    #property-units-trash-page .pagination .pagination li.disabled span {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ------------------------------------------------------------
       Responsive
       ------------------------------------------------------------ */
    @media (max-width: 768px) {
        #property-units-trash-page .grid.grid-cols-1.lg\:grid-cols-4 {
            grid-template-columns: 1fr;
        }

        #property-units-trash-page .lg\:col-span-1 { margin-bottom: 1.5rem; }

        #property-units-trash-page table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }

        #property-units-trash-page #cardView { grid-template-columns: 1fr; }

        #property-units-trash-page .action-buttons { flex-direction: column; }

        #property-units-trash-page .action-btn {
            width: 100%;
            justify-content: flex-start;
        }

        #property-units-trash-page .action-btn span { display: inline !important; }

        .modal-container {
            width: 95%;
            max-height: 80vh;
            margin: 0.5rem;
        }
    }

    /* ------------------------------------------------------------
       Animations
       ------------------------------------------------------------ */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    .fa-spinner.fa-spin { animation: spin 1s linear infinite; }

    /* ------------------------------------------------------------
       Scoped .hidden — so the modals don't leak into the layout
       ------------------------------------------------------------ */
    #property-units-trash-page #tableView.hidden,
    #property-units-trash-page #cardView.hidden,
    #emptyTrashModal.hidden,
    #restoreModal.hidden,
    #forceDeleteModal.hidden {
        display: none !important;
    }

    /* ------------------------------------------------------------
       Flash background overrides
       ------------------------------------------------------------ */
    #property-units-trash-page .bg-green-100  { background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3); }
    #property-units-trash-page .bg-red-100    { background-color: rgba(var(--danger-rgb), 0.1);  border-color: rgba(var(--danger-rgb), 0.3); }
    #property-units-trash-page .bg-yellow-100 { background-color: rgba(var(--warning-rgb), 0.1); border-color: rgba(var(--warning-rgb), 0.3); }
</style>
@endpush