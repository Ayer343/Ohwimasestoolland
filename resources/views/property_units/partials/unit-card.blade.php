{{-- property_units/partials/unit-card.blade.php --}}
@php
    /**
     * Same contract as unit-row.blade.php — see that file's header
     * for the full list of expected variables.
     */
    $isTenant              = $isTenant              ?? false;
    $isAdmin               = $isAdmin               ?? false;
    $isSuperAdmin          = $isSuperAdmin          ?? false;
    $isLandlord            = $isLandlord            ?? false;
    $isOnAdminDashboard    = $isOnAdminDashboard    ?? false;
    $isOnLandlordDashboard = $isOnLandlordDashboard ?? false;
    $isOwner               = $isOwner               ?? ($isLandlord && $unit->property && (int) $unit->property->landlord_id === (int) auth()->id());
    $isLinkedFamilyMember  = $isLinkedFamilyMember  ?? false;
    $hasManageUnitsAccess  = $hasManageUnitsAccess  ?? false;
    $routePrefix           = $routePrefix           ?? 'property-units';

    /* ------------------------------------------------------------
       PERMISSION DERIVATION
       ------------------------------------------------------------ */
    $canManageUnits = $isOwner || ($isLinkedFamilyMember && $hasManageUnitsAccess);
    $canDeleteUnit  = $isOwner;

    /* ------------------------------------------------------------
       UNIT STATE
       ------------------------------------------------------------ */
    $isOccupied = $unit->status === 'occupied'
        || ($unit->tenant_status === 'approved' && $unit->tenant_id);

    $isAvailable = $unit->is_available && $unit->status === 'available';

    $hasTenant = $unit->tenant_status && in_array($unit->tenant_status, [
        \App\Models\PropertyUnit::TENANT_STATUS_APPROVED,
        \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
    ], true);

    /* ------------------------------------------------------------
       ACTION VISIBILITY
       ------------------------------------------------------------ */
    $showEditButton   = $canManageUnits && !$isOnAdminDashboard;
    $showAssignButton = $canManageUnits && !$isOnAdminDashboard && $isAvailable && !$hasTenant;
    $showDeleteButton = $canDeleteUnit  && !$isOnAdminDashboard;
    $showVacateButton = $canManageUnits && !$isOnAdminDashboard && $unit->tenant_status === 'approved';

    /* ------------------------------------------------------------
       BADGE COLORS
       ------------------------------------------------------------ */
    $statusColor = match($unit->status) {
        'available'         => 'success',
        'occupied'          => 'primary',
        'under_maintenance' => 'warning',
        'reserved'          => 'info',
        default             => 'secondary',
    };

    /* ------------------------------------------------------------
       PHOTO — cover photo + count
       ------------------------------------------------------------ */
    $coverPhoto = $unit->relationLoaded('coverPhoto') ? $unit->coverPhoto : null;
    $photoCount = $unit->photo_count;
    $hasPhotos  = $coverPhoto !== null || $photoCount > 0;
@endphp

<div class="card hover:shadow-lg transition-all duration-300 overflow-hidden">
    {{-- ========== PHOTO: cover image (top of card) ========== --}}
    <a href="{{ route('property-units.show', $unit->id) }}"
       class="block relative group"
       title="{{ $hasPhotos ? 'View unit photos' : 'View unit' }}">
        @if($coverPhoto)
            <div class="w-full aspect-video overflow-hidden" style="background-color: var(--bg-secondary);">
                <img src="{{ $coverPhoto->url }}"
                     alt="{{ $unit->unit_number }} cover photo"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                     loading="lazy">
            </div>
        @else
            <div class="w-full aspect-video flex items-center justify-center"
                 style="background-color: var(--bg-secondary);">
                <div class="text-center">
                    <i class="fas fa-image text-2xl mb-1" style="color: var(--text-secondary); opacity: 0.6;"></i>
                    <p class="text-xs" style="color: var(--text-secondary);">No photos yet</p>
                </div>
            </div>
        @endif

        {{-- Status badge overlay --}}
        <span class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs badge-{{ $statusColor }}"
              style="backdrop-filter: blur(4px); background-color: rgba(var(--card-bg), 0.9);">
            {{ ucfirst(str_replace('_', ' ', $unit->status)) }}
        </span>

        {{-- Photo count pill (bottom-left) --}}
        @if($photoCount > 0)
            <span class="absolute bottom-2 left-2 text-xs font-medium px-2 py-1 rounded-full flex items-center"
                  style="background-color: rgba(0, 0, 0, 0.6); color: white; backdrop-filter: blur(4px);">
                <i class="fas fa-images mr-1"></i>
                {{ $photoCount }} {{ Str::plural('photo', $photoCount) }}
            </span>
        @endif
    </a>
    {{-- ========== /PHOTO ========== --}}

    <!-- Card Header -->
    <div class="p-4 border-b" style="border-color: var(--border-color);">
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
                <h4 class="font-bold truncate" style="color: var(--text-primary);">
                    {{ $unit->unit_number }}
                    @if($unit->unit_name)
                        <span class="text-sm font-normal" style="color: var(--text-secondary);">
                            ({{ $unit->unit_name }})
                        </span>
                    @endif
                </h4>
                <p class="text-xs mt-1 truncate" style="color: var(--text-secondary);">
                    <i class="fas fa-building mr-1"></i>
                    {{ $unit->property->property_name ?? 'N/A' }}
                </p>

                @if($isLinkedFamilyMember && !$isOwner)
                    <span class="inline-flex items-center mt-1 text-xs px-2 py-0.5 rounded-full badge-info">
                        <i class="fas fa-user-friends mr-1"></i>
                        {{ $hasManageUnitsAccess ? 'Linked · Manage' : 'Linked · Read-only' }}
                    </span>
                @endif

                @if(($isAdmin || $isSuperAdmin) && $unit->property && $unit->property->landlord)
                    <p class="text-xs mt-1 flex items-center truncate" style="color: var(--text-secondary);">
                        <i class="fas fa-user-tie mr-1"></i>
                        Landlord: {{ $unit->property->landlord->name }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Card Body -->
    <div class="p-4">
        <!-- Unit Details -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="text-center p-2 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                <i class="fas fa-bed text-sm" style="color: var(--text-secondary);"></i>
                <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">
                    {{ $unit->bedrooms ?? '0' }} Bed
                </div>
            </div>
            <div class="text-center p-2 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                <i class="fas fa-bath text-sm" style="color: var(--text-secondary);"></i>
                <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">
                    {{ $unit->bathrooms ?? '0' }} Bath
                </div>
            </div>
        </div>

        <!-- Unit Type -->
        <div class="mb-3">
            <div class="flex items-center">
                <i class="fas fa-home text-xs mr-2" style="color: var(--text-secondary);"></i>
                <span class="text-xs" style="color: var(--text-secondary);">Type:</span>
                <span class="text-xs font-medium ml-1" style="color: var(--text-primary);">
                    {{ ucfirst(str_replace('_', ' ', $unit->unit_type)) }}
                </span>
            </div>
            @if($unit->floor_area)
                <div class="flex items-center mt-1">
                    <i class="fas fa-ruler-combined text-xs mr-2" style="color: var(--text-secondary);"></i>
                    <span class="text-xs" style="color: var(--text-secondary);">Area:</span>
                    <span class="text-xs font-medium ml-1" style="color: var(--text-primary);">
                        {{ $unit->floor_area }} sqft
                    </span>
                </div>
            @endif
            @if($unit->is_furnished)
                <div class="flex items-center mt-1">
                    <i class="fas fa-couch text-xs mr-2" style="color: var(--success);"></i>
                    <span class="text-xs badge-success">Furnished</span>
                </div>
            @endif
        </div>

        <!-- Tenant Info -->
        @if(!$isTenant)
            <div class="mb-4">
                <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Tenant</h5>
                @if($unit->tenant)
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $unit->tenant->name }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $unit->tenant->phone ?? 'No phone' }}
                            </div>
                            @if($unit->tenant_status === 'pending_approval')
                                <div class="text-xs badge-warning inline-flex items-center mt-1">
                                    <i class="fas fa-clock mr-1"></i> Pending Approval
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($unit->tenant_status === 'pending_approval' && $unit->tenant_id)
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-user-clock text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium" style="color: var(--warning);">
                                Pending Tenant
                            </div>
                            <div class="text-xs badge-warning inline-flex items-center mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i> Awaiting Approval
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-2">
                        <span class="text-sm" style="color: var(--text-secondary);">No tenant assigned</span>
                        @if($unit->available_from && $unit->available_from->isFuture())
                            <p class="text-xs mt-1 flex items-center justify-center" style="color: var(--info);">
                                <i class="fas fa-clock mr-1"></i>
                                Available from {{ $unit->available_from->format('M d') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Rent Info -->
        <div class="mb-4">
            <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Rent</h5>
            <div class="text-xl font-bold" style="color: var(--primary);">
                GHS {{ number_format($unit->current_rent_amount ?? $unit->monthly_rent, 2) }}
            </div>
            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                @if($unit->security_deposit)
                    <i class="fas fa-shield-alt mr-1"></i>
                    Deposit: GHS {{ number_format($unit->security_deposit, 2) }}
                @endif
            </div>
        </div>

        <!-- Actions -->
        <div class="flex space-x-2">
            {{-- View — everyone --}}
            <a href="{{ route('property-units.show', $unit->id) }}"
               class="flex-1 p-2 text-center rounded-lg action-btn view"
               data-tooltip="View Details">
                <i class="fas fa-eye"></i>
            </a>

            {{-- Edit — owner or linked-with-manage_units, non-occupied --}}
            @if($showEditButton && !$isOccupied)
                <a href="{{ route($routePrefix . '.edit', $unit->id) }}"
                   class="flex-1 p-2 text-center rounded-lg action-btn edit"
                   data-tooltip="Edit Unit">
                    <i class="fas fa-edit"></i>
                </a>
            @endif

            {{-- Assign — only when available + no tenant --}}
            @if($showAssignButton)
                <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}"
                   class="flex-1 p-2 text-center rounded-lg action-btn assign"
                   data-tooltip="Assign Tenant">
                    <i class="fas fa-user-plus"></i>
                </a>
            @endif

            {{-- Delete — owner only, non-occupied --}}
            @if($showDeleteButton && !$isOccupied)
                <button type="button"
                        onclick="return confirmDelete({{ $unit->id }}, @js($unit->unit_number), false)"
                        class="flex-1 p-2 text-center rounded-lg action-btn delete"
                        data-tooltip="Delete Unit">
                    <i class="fas fa-trash-alt"></i>
                </button>
            @endif

            {{-- Vacate — owner or linked-with-manage_units, approved tenant only --}}
            @if($showVacateButton)
                <button type="button"
                        onclick="showVacateModal(
                            {{ $unit->id }},
                            @js($unit->unit_number),
                            @js($unit->tenant->name ?? 'Tenant'),
                            {{ (float) ($unit->security_deposit ?? 0) }}
                        )"
                        class="flex-1 p-2 text-center rounded-lg action-btn vacate"
                        data-tooltip="Vacate Tenant">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            @endif
        </div>

        {{-- Read-only / admin metadata --}}
        @if(($isAdmin || $isSuperAdmin) && !$canManageUnits)
            <div class="mt-3 pt-3 border-t text-center" style="border-color: var(--border-color);">
                <span class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-eye mr-1"></i> Admin View — Read Only
                </span>
            </div>
        @elseif($isLinkedFamilyMember && !$isOwner && !$hasManageUnitsAccess)
            <div class="mt-3 pt-3 border-t text-center" style="border-color: var(--border-color);">
                <span class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-lock mr-1"></i> Read-only linked access
                </span>
            </div>
        @elseif($isLinkedFamilyMember && !$isOwner && $hasManageUnitsAccess)
            <div class="mt-3 pt-3 border-t text-center" style="border-color: var(--border-color);">
                <span class="text-xs" style="color: var(--info);">
                    <i class="fas fa-user-friends mr-1"></i> Acting as linked family member — actions are logged
                </span>
            </div>
        @endif
    </div>

    <!-- Card Footer -->
    <div class="p-3 border-t text-center" style="border-color: var(--border-color);">
        <span class="text-xs" style="color: var(--text-secondary);">
            <i class="far fa-clock mr-1"></i>
            Created {{ $unit->created_at->diffForHumans() }}
        </span>
    </div>
</div>