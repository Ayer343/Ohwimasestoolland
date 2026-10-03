{{-- property_units/partials/unit-card.blade.php --}}
@php
    // Determine user role and permissions (same as in unit-row)
    $user = auth()->user();
    $isLandlord = $user->isLandlord();
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    
    // Updated: Admin and SuperAdmin should NOT edit or delete units
    // Check if user can edit this unit - Landlords can edit their own units only
    $canEdit = $isLandlord && $unit->property && $unit->property->landlord_id == $user->id;
    
    // Check if user can delete this unit - Landlords can delete their own units only
    $canDelete = $isLandlord && $unit->property && $unit->property->landlord_id == $user->id;
    
    // Check if unit is occupied
    $isOccupied = $unit->status === 'occupied' || 
                  ($unit->tenant_status === 'approved' && $unit->tenant_id);
    
    // Check if unit is available for tenant assignment
    $isAvailable = $unit->is_available && $unit->status === 'available';
    $hasTenant = $unit->tenant_status && in_array($unit->tenant_status, [
        \App\Models\PropertyUnit::TENANT_STATUS_APPROVED,
        \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
    ]);
    
    // Updated: Only landlords can assign tenants, not admins
    $showAssignButton = $canEdit && $isAvailable && !$hasTenant;
    
    // Updated: Only landlords can vacate tenants, not admins
    $canVacate = $canEdit && $unit->tenant_status === 'approved';
    
    // Status colors
    $statusColor = match($unit->status) {
        'available' => 'success',
        'occupied' => 'primary',
        'under_maintenance' => 'warning',
        default => 'secondary'
    };
@endphp

<div class="card hover:shadow-lg transition-all duration-300">
    <!-- Card Header -->
    <div class="p-4 border-b" style="border-color: var(--border-color);">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="font-bold" style="color: var(--text-primary);">
                    {{ $unit->unit_number }}
                    @if($unit->unit_name)
                        <span class="text-sm font-normal" style="color: var(--text-secondary);">({{ $unit->unit_name }})</span>
                    @endif
                </h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-building mr-1"></i>
                    {{ $unit->property->property_name ?? 'N/A' }}
                </p>
                @if(($isAdmin || $isSuperAdmin) && $unit->property && $unit->property->landlord)
                    <p class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-user-tie mr-1"></i>
                        Landlord: {{ $unit->property->landlord->name }}
                    </p>
                @endif
            </div>
            <span class="px-2 py-1 rounded-full text-xs badge-{{ $statusColor }}">
                {{ ucfirst($unit->status) }}
            </span>
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
                @php
                    $pendingTenant = \App\Models\User::find($unit->tenant_id);
                @endphp
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-user-clock text-sm"></i>
                    </div>
                    <div>
                        <div class="text-sm font-medium" style="color: var(--warning);">
                            {{ $pendingTenant->name ?? 'Pending Tenant' }}
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
            <!-- View Button - Always visible for all roles -->
            <a href="{{ route('property-units.show', $unit->id) }}" 
               class="flex-1 p-2 text-center rounded-lg action-btn view" 
               data-tooltip="View Details">
                <i class="fas fa-eye"></i>
            </a>
            
            <!-- Edit Button - Only for landlords of their own units -->
            @if($canEdit)
                @if($unit->status !== 'occupied')
                <!-- Edit Button -->
                <a href="{{ route('property-units.edit', $unit->id) }}" 
                   class="flex-1 p-2 text-center rounded-lg action-btn edit" 
                   data-tooltip="Edit Unit">
                    <i class="fas fa-edit"></i>
                </a>
                @endif
                
                <!-- Assign Tenant Button - Only for landlords when unit is available -->
                @if($showAssignButton)
                <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}" 
                   class="flex-1 p-2 text-center rounded-lg action-btn assign" 
                   data-tooltip="Assign Tenant">
                    <i class="fas fa-user-plus"></i>
                </a>
                @endif
                
                <!-- Delete Button - Only for landlords, not for occupied units -->
                @if($canDelete && !$isOccupied)
                <button type="button" 
                        onclick="return confirmDelete({{ $unit->id }}, '{{ $unit->unit_number }}', {{ $isOccupied ? 'true' : 'false' }})"
                        class="flex-1 p-2 text-center rounded-lg action-btn delete"
                        data-tooltip="Delete Unit">
                    <i class="fas fa-trash-alt"></i>
                </button>
                @endif
                
                <!-- Vacate Tenant Button - Only for landlords with approved tenants -->
                @if($canVacate)
                <button type="button" 
                        onclick="showVacateModal({{ $unit->id }})"
                        class="flex-1 p-2 text-center rounded-lg action-btn vacate"
                        data-tooltip="Vacate Tenant">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
                @endif
            @endif
            
            <!-- Admin Info Placeholder (if no edit actions available for admin) -->
            @if(($isAdmin || $isSuperAdmin) && !$canEdit)
                <div class="flex-1 p-2 text-center rounded-lg" 
                     style="background-color: rgba(var(--secondary-rgb), 0.05);"
                     data-tooltip="Admin View Only">
                    <i class="fas fa-eye" style="color: var(--text-secondary);"></i>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Card Footer -->
    <div class="p-3 border-t text-center" style="border-color: var(--border-color);">
        <span class="text-xs" style="color: var(--text-secondary);">
            <i class="far fa-clock mr-1"></i>
            Created {{ $unit->created_at->diffForHumans() }}
        </span>
    </div>
</div>