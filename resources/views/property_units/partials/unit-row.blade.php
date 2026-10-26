{{-- property_units/partials/unit-row.blade.php --}}
@php
    /**
     * Variables expected from the parent view:
     *   $unit                    PropertyUnit (with property, tenant, creator, coverPhoto loaded)
     *   $isTenant                bool
     *   $isAdmin                 bool
     *   $isSuperAdmin            bool
     *   $isLandlord              bool
     *   $isOnAdminDashboard      bool
     *   $isOnLandlordDashboard   bool
     *   $isOwner                 bool  — user owns the property this unit belongs to
     *   $isLinkedFamilyMember    bool  — user is linked (approved) but does NOT own
     *   $hasManageUnitsAccess    bool  — link grants `manage_units` for this property
     *   $routePrefix             string — e.g. 'landlord.property-units'
     *
     * Safe defaults so this partial can still render if included from
     * a context that predates the family-link flags (avoids 500s).
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
       PERMISSION DERIVATION (single source of truth)
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

    $tenantStatusColor = $unit->tenant_status ? match($unit->tenant_status) {
        'pending_approval' => 'warning',
        'approved'         => 'success',
        'rejected'         => 'danger',
        'vacated'          => 'secondary',
        'terminated'       => 'danger',
        default            => 'info',
    } : null;

    /* ------------------------------------------------------------
       PHOTO — cover photo state
       ------------------------------------------------------------ */
    $coverPhoto      = $unit->relationLoaded('coverPhoto') ? $unit->coverPhoto : null;
    $photoCount      = $unit->photo_count;   // accessor; uses loaded `photos` relation if present
    $hasPhotos       = $coverPhoto !== null || $photoCount > 0;
@endphp

<tr class="border-b transition-colors hover:bg-gray-50 dark:hover:bg-gray-700"
    style="border-color: var(--border-color);">

    <!-- Unit Details -->
    <td class="p-3">
        <div class="flex items-start space-x-3">
            {{-- ========== PHOTO: cover thumbnail (falls back to unit-type icon) ========== --}}
            <div class="flex-shrink-0 relative">
                @if($coverPhoto)
                    <a href="{{ route('property-units.show', $unit->id) }}"
                       class="block w-12 h-12 rounded-lg overflow-hidden border group"
                       style="border-color: var(--border-color);"
                       title="View unit photos">
                        <img src="{{ $coverPhoto->thumb_url }}"
                             alt="{{ $unit->unit_number }} cover photo"
                             class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                             loading="lazy">
                    </a>

                    {{-- Photo count pill (only when there's more than one) --}}
                    @if($photoCount > 1)
                        <span class="absolute -bottom-1 -right-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                              style="background-color: var(--card-bg); color: var(--text-secondary); border: 1px solid var(--border-color);"
                              title="{{ $photoCount }} photos">
                            <i class="fas fa-images mr-0.5"></i>{{ $photoCount }}
                        </span>
                    @endif
                @else
                    @php
                        $unitIcon = match($unit->unit_type) {
                            'apartment'  => 'fa-building',
                            'house'      => 'fa-home',
                            'studio'     => 'fa-cube',
                            'commercial' => 'fa-store',
                            'office'     => 'fa-briefcase',
                            default      => 'fa-home',
                        };
                    @endphp
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                         style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                        <i class="fas {{ $unitIcon }}"></i>
                    </div>
                @endif
            </div>
            {{-- ========== /PHOTO ========== --}}

            <div class="min-w-0">
                <a href="{{ route('property-units.show', $unit->id) }}"
                   class="font-medium hover:underline" style="color: var(--text-primary);">
                    {{ $unit->unit_number }}
                </a>
                @if($unit->unit_name)
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ $unit->unit_name }}</p>
                @endif
                <div class="flex items-center space-x-3 mt-1">
                    @if($unit->bedrooms)
                        <span class="text-xs flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-bed mr-1"></i> {{ $unit->bedrooms }}
                        </span>
                    @endif
                    @if($unit->bathrooms)
                        <span class="text-xs flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-bath mr-1"></i> {{ $unit->bathrooms }}
                        </span>
                    @endif
                    @if($unit->floor_area)
                        <span class="text-xs flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-ruler-combined mr-1"></i>
                            {{ $unit->floor_area }} sqft
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </td>

    <!-- Property (hidden for tenants) -->
    @if(!$isTenant)
        <td class="p-3">
            @if($unit->property)
                <div>
                    <a href="{{ route('properties.show', $unit->property_id) }}"
                       class="font-medium hover:underline" style="color: var(--text-primary);">
                        {{ $unit->property->property_name }}
                    </a>

                    @if($isLinkedFamilyMember && !$isOwner)
                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full badge-info"
                              data-tooltip="{{ $hasManageUnitsAccess ? 'You can manage units here' : 'Read-only access' }}">
                            <i class="fas fa-user-friends mr-1"></i>
                            {{ $hasManageUnitsAccess ? 'Linked · Manage' : 'Linked · Read-only' }}
                        </span>
                    @endif

                    @if(($isAdmin || $isSuperAdmin) && $unit->property->landlord)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-user-tie mr-1"></i>
                            {{ $unit->property->landlord->name ?? 'N/A' }}
                        </p>
                    @endif
                </div>
                <p class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    {{ $unit->property->digital_address ?? ($unit->property->zone ?? 'N/A') }}
                </p>
            @else
                <span class="text-sm" style="color: var(--danger);">Property not found</span>
            @endif
        </td>
    @endif

    <!-- Status -->
    <td class="p-3">
        <div class="space-y-1">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-{{ $statusColor }}">
                <i class="fas fa-circle mr-1 text-xs"></i>
                {{ ucfirst(str_replace('_', ' ', $unit->status)) }}
            </span>

            @if($unit->tenant_status)
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-{{ $tenantStatusColor }}">
                    <i class="fas fa-user mr-1"></i>
                    {{ ucfirst(str_replace('_', ' ', $unit->tenant_status)) }}
                </span>
            @endif

            @if($unit->is_furnished)
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-success">
                    <i class="fas fa-couch mr-1"></i> Furnished
                </span>
            @endif
        </div>
    </td>

    <!-- Tenant (hidden for tenants) -->
    @if(!$isTenant)
        <td class="p-3">
            @if($unit->tenant)
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center avatar-sm"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->name }}</div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $unit->tenant->phone ?? 'No phone' }}</p>
                        @if($unit->tenant_move_in_date)
                            <p class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                {{ $unit->tenant_move_in_date->format('M d, Y') }}
                            </p>
                        @endif
                    </div>
                </div>
            @elseif($unit->tenant_status === 'pending_approval' && $unit->tenant_id)
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center avatar-sm"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--warning);">
                            Pending Tenant
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Awaiting Admin Approval</p>
                    </div>
                </div>
            @else
                <div class="text-center py-2">
                    <span class="text-sm" style="color: var(--text-secondary);">No tenant</span>
                    @if($unit->available_from && $unit->available_from->isFuture())
                        <p class="text-xs mt-1 flex items-center" style="color: var(--info);">
                            <i class="fas fa-clock mr-1"></i>
                            Available from {{ $unit->available_from->format('M d, Y') }}
                        </p>
                    @endif
                </div>
            @endif
        </td>
    @endif

    <!-- Rent -->
    <td class="p-3">
        <div class="font-bold" style="color: var(--primary);">
            GHS {{ number_format($unit->current_rent_amount ?? $unit->monthly_rent, 2) }}
            <span class="text-xs font-normal block mt-1" style="color: var(--text-secondary);">per month</span>
        </div>
        @if($unit->security_deposit)
            <div class="mt-2">
                <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Deposit: GHS {{ number_format($unit->security_deposit, 2) }}
                </p>
            </div>
        @endif
    </td>

    <!-- Actions -->
    <td class="p-3">
        <div class="action-buttons">
            {{-- View — everyone --}}
            <a href="{{ route('property-units.show', $unit->id) }}"
               class="action-btn view"
               data-tooltip="View Details">
                <i class="fas fa-eye"></i>
                <span>View</span>
            </a>

            {{-- Edit — owner or linked-with-manage_units. Only when not occupied. --}}
            @if($showEditButton)
                @if($isOccupied)
                    <button type="button"
                            class="action-btn edit opacity-50 cursor-not-allowed"
                            data-tooltip="Cannot edit an occupied unit"
                            disabled>
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </button>
                @else
                    <a href="{{ route($routePrefix . '.edit', $unit->id) }}"
                       class="action-btn edit"
                       data-tooltip="Edit Unit">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </a>
                @endif

                {{-- Assign — only when available and no tenant --}}
                @if($showAssignButton)
                    <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}"
                       class="action-btn assign"
                       data-tooltip="Assign Tenant">
                        <i class="fas fa-user-plus"></i>
                        <span>Assign</span>
                    </a>
                @endif
            @endif

            {{-- Delete — owner only, and only when not occupied --}}
            @if($showDeleteButton)
                @if($isOccupied)
                    <button type="button"
                            class="action-btn delete opacity-50 cursor-not-allowed"
                            data-tooltip="Cannot delete an occupied unit"
                            disabled>
                        <i class="fas fa-trash-alt"></i>
                        <span>Delete</span>
                    </button>
                @else
                    <button type="button"
                            onclick="return confirmDelete({{ $unit->id }}, @js($unit->unit_number), false)"
                            class="action-btn delete"
                            data-tooltip="Delete Unit">
                        <i class="fas fa-trash-alt"></i>
                        <span>Delete</span>
                    </button>
                @endif
            @endif
        </div>

        {{-- Vacate — owner or linked-with-manage_units, only when an approved tenant exists --}}
        @if($showVacateButton)
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                <button type="button"
                        class="action-btn vacate w-full"
                        data-tooltip="Mark Tenant as Vacated"
                        onclick="showVacateModal(
                            {{ $unit->id }},
                            @js($unit->unit_number),
                            @js($unit->tenant->name ?? 'Tenant'),
                            {{ (float) ($unit->security_deposit ?? 0) }}
                        )">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Vacate Tenant</span>
                </button>
            </div>
        @endif

        {{-- Admin/Developer metadata — who manages this unit --}}
        @if(($isAdmin || $isSuperAdmin) && $unit->property && $unit->property->landlord)
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                <p class="text-xs text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Managed by: {{ $unit->property->landlord->name }}
                </p>
                @if($isOnAdminDashboard)
                    <p class="text-xs text-center mt-1" style="color: var(--info);">
                        <i class="fas fa-eye mr-1"></i>
                        Admin View — Read Only
                    </p>
                @endif
            </div>
        @endif

        {{-- Family-member context — reassure that actions are audited --}}
        @if($isLinkedFamilyMember && !$isOwner && $hasManageUnitsAccess)
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                <p class="text-xs text-center" style="color: var(--info);">
                    <i class="fas fa-user-friends mr-1"></i>
                    Acting as linked family member — actions are logged
                </p>
            </div>
        @elseif($isLinkedFamilyMember && !$isOwner && !$hasManageUnitsAccess)
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                <p class="text-xs text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-lock mr-1"></i>
                    Read-only linked access
                </p>
            </div>
        @endif
    </td>
</tr>