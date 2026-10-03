{{-- property_units/partials/unit-row.blade.php --}}
@php
    // Determine user role and permissions
    $user = auth()->user();
    $isLandlord = $user->isLandlord();
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isDeveloper = $user->isDeveloper();
    
    // Get the selected role from session (dashboard switch)
    $selectedRole = session('selected_role');
    
    // Determine if user is viewing from admin dashboard
    $isOnAdminDashboard = in_array($selectedRole, ['admin', 'super-admin']) || ($isAdmin || $isSuperAdmin && !$selectedRole);
    $isOnLandlordDashboard = $selectedRole === 'landlord' || ($isLandlord && !$selectedRole && !$isOnAdminDashboard);
    
    // IMPORTANT: Admins should NOT edit or delete units - ONLY landlords can
    // Check if user can edit this unit - Landlords can edit their own units only AND only if NOT in admin view
    $canEdit = !$isOnAdminDashboard && $isLandlord && $unit->property && $unit->property->landlord_id == $user->id;
    
    // Check if user can delete this unit - Landlords can delete their own units only AND only if NOT in admin view
    $canDelete = !$isOnAdminDashboard && $isLandlord && $unit->property && $unit->property->landlord_id == $user->id;
    
    // Check if unit is occupied
    $isOccupied = $unit->status === 'occupied' || 
                  ($unit->tenant_status === 'approved' && $unit->tenant_id);
    
    // Check if unit is available for tenant assignment
    $isAvailable = $unit->is_available && $unit->status === 'available';
    $hasTenant = $unit->tenant_status && in_array($unit->tenant_status, [
        \App\Models\PropertyUnit::TENANT_STATUS_APPROVED,
        \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
    ]);
    
    // Only landlords can assign tenants, not admins
    $showAssignButton = $canEdit && $isAvailable && !$hasTenant;
    
    // Only landlords can vacate tenants, not admins
    $canVacate = $canEdit && $unit->tenant_status === 'approved';
    
    // Status colors
    $statusColor = match($unit->status) {
        'available' => 'success',
        'occupied' => 'primary',
        'under_maintenance' => 'warning',
        'reserved' => 'info',
        default => 'secondary'
    };
    
    // Tenant status colors
    $tenantStatusColor = $unit->tenant_status ? match($unit->tenant_status) {
        'pending_approval' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'vacated' => 'secondary',
        'terminated' => 'danger',
        default => 'info'
    } : null;
@endphp

<tr class="border-b transition-colors hover:bg-gray-50 dark:hover:bg-gray-700" 
    style="border-color: var(--border-color);">
    <!-- Unit Details -->
    <td class="p-3">
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
                @php
                    $unitIcon = match($unit->unit_type) {
                        'apartment' => 'fa-building',
                        'house' => 'fa-home',
                        'studio' => 'fa-cube',
                        'commercial' => 'fa-store',
                        'office' => 'fa-briefcase',
                        default => 'fa-home'
                    };
                @endphp
                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                     style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                    <i class="fas {{ $unitIcon }}"></i>
                </div>
            </div>
            <div>
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
                @if(($isAdmin || $isSuperAdmin || $isDeveloper) && $unit->property->landlord)
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
                    @php
                        $pendingTenant = \App\Models\User::find($unit->tenant_id);
                    @endphp
                    <div class="font-medium" style="color: var(--warning);">
                        {{ $pendingTenant->name ?? 'Pending Tenant' }}
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
            <!-- View Button - Always visible for all roles -->
            <a href="{{ route('property-units.show', $unit->id) }}" 
               class="action-btn view" 
               data-tooltip="View Details">
                <i class="fas fa-eye"></i>
                <span>View</span>
            </a>
            
            <!-- Edit Button - ONLY for landlords (NOT admins) -->
            @if($canEdit && !$isOnAdminDashboard)
                <a href="{{ route('property-units.edit', $unit->id) }}" 
                   class="action-btn edit {{ $isOccupied && $isLandlord ? 'opacity-50 cursor-not-allowed' : '' }}"
                   data-tooltip="{{ $isOccupied && $isLandlord ? 'Cannot edit occupied unit' : 'Edit Unit' }}"
                   @if($isOccupied && $isLandlord) disabled @endif>
                    <i class="fas fa-edit"></i>
                    <span>Edit</span>
                </a>
                
                <!-- Assign Tenant Button - Only for landlords when unit is available -->
                @if($showAssignButton && !$isOnAdminDashboard)
                    <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}" 
                       class="action-btn assign"
                       data-tooltip="Assign Tenant">
                        <i class="fas fa-user-plus"></i>
                        <span>Assign</span>
                    </a>
                @endif
            @endif
            
            <!-- Delete Button - ONLY for landlords (NOT admins), and not for occupied units -->
            @if($canDelete && !$isOccupied && !$isOnAdminDashboard)
                <button type="button" 
                        onclick="return confirmDelete({{ $unit->id }}, '{{ $unit->unit_number }}', {{ $isOccupied ? 'true' : 'false' }})"
                        class="action-btn delete"
                        data-tooltip="Delete Unit">
                    <i class="fas fa-trash-alt"></i>
                    <span>Delete</span>
                </button>
            @elseif($canDelete && $isOccupied && !$isOnAdminDashboard)
                <button type="button" 
                        class="action-btn delete opacity-50 cursor-not-allowed"
                        data-tooltip="Cannot delete occupied unit"
                        disabled>
                    <i class="fas fa-trash-alt"></i>
                    <span>Delete</span>
                </button>
            @endif
        </div>
        
        <!-- Quick Actions - Vacate Tenant (Only for landlords) -->
        @if($canVacate && !$isOnAdminDashboard)
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                <button type="button" 
                        class="action-btn vacate w-full"
                        data-tooltip="Mark Tenant as Vacated"
                        onclick="showVacateModal({{ $unit->id }})">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Vacate Tenant</span>
                </button>
            </div>
        @endif
        
        <!-- Admin/SuperAdmin Additional Info - Shows who manages the unit -->
        @if(($isAdmin || $isSuperAdmin) && $unit->property && $unit->property->landlord)
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                <p class="text-xs text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Managed by: {{ $unit->property->landlord->name }}
                </p>
                @if($isOnAdminDashboard)
                    <p class="text-xs text-center mt-1" style="color: var(--info);">
                        <i class="fas fa-eye mr-1"></i>
                        Admin View - Read Only
                    </p>
                @endif
            </div>
        @endif
    </td>
</tr>