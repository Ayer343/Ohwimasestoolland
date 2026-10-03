{{-- property_units/show.blade.php --}}
@php
    // Get the authenticated user
    $user = auth()->user();
    
    // Get the selected role from session (dashboard switch)
    $selectedRole = session('selected_role');
    
    // Determine if user has landlord role (for feature access)
    $hasLandlordRole = $user->isLandlord();
    
    // Determine if user is a landlord by LEGACY type (backward compatibility)
    $isLandlordByLegacy = $user->type === \App\Models\User::TYPE_LANDLORD;
    
    // Determine if user is a landlord by ROLE (spatie/permission)
    $isLandlordByRole = $user->hasRole('landlord');
    
    // TRUE LANDLORD: User has landlord role in any form
    $isLandlord = $hasLandlordRole || $isLandlordByLegacy || $isLandlordByRole;
    
    // TRUE ADMIN: User has admin role in any form
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isDeveloper = $user->isDeveloper();
    $isTenant = $user->isTenant();
    
    // ============================================================
    // CRITICAL: Determine the active dashboard from session
    // ============================================================
    $activeDashboard = $selectedRole ?? 'default';
    
    // Determine which dashboard the user is currently viewing
    $isOnAdminDashboard = in_array($selectedRole, ['admin', 'super-admin']) || ($isAdmin || $isSuperAdmin && !$selectedRole);
    $isOnLandlordDashboard = $selectedRole === 'landlord' || ($isLandlord && !$selectedRole && !$isOnAdminDashboard);
    $isOnTenantDashboard = $selectedRole === 'tenant' || ($isTenant && !$selectedRole);
    $isOnDeveloperDashboard = $selectedRole === 'developer' || ($isDeveloper && !$selectedRole);
    
    // ============================================================
    // Determine layout based on ACTIVE DASHBOARD
    // ============================================================
    if ($isOnAdminDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.property-units';
        $isAdminView = true;
        $pageTitle = 'Property Unit - Admin View';
    } elseif ($isOnLandlordDashboard) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
        $isAdminView = false;
        $pageTitle = 'Property Unit Details';
    } elseif ($isOnTenantDashboard) {
        $layout = 'layouts.tenant';
        $routePrefix = 'tenant.property-units';
        $isAdminView = false;
        $pageTitle = 'My Unit Details';
    } elseif ($isOnDeveloperDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'developer.property-units';
        $isAdminView = false;
        $pageTitle = 'Property Unit - Developer';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
        $isAdminView = false;
        $pageTitle = 'Property Unit';
    }
    
    // ============================================================
    // Determine permissions based on ACTIVE DASHBOARD
    // ============================================================
    // Check if user is the owner/landlord of the property
    $isOwner = $isLandlord && $unit->property->landlord_id === auth()->id();
    
    // Check if current user is the assigned tenant
    $isAssignedTenant = $isTenant && $unit->tenant_id === auth()->id();
    
    // Can edit: Only landlords who own the unit and it's not occupied
    // IMPORTANT: Admins should NOT be able to edit units from the admin view
    $canEdit = $isOwner && $unit->status !== \App\Models\PropertyUnit::STATUS_OCCUPIED;
    
    // Show financial and tenant info: Only to the owner in landlord context
    // HIDDEN from admin viewing - admins should not see financial information
    $showFinancialAndTenantInfo = $isOwner && !$isOnAdminDashboard;
    
    // Determine if user should see management actions
    $showManagementActions = $isOwner || $isOnAdminDashboard || $isOnDeveloperDashboard;
    
    // Can delete: Only the owner can delete their own units (not admins)
    $canDelete = $isOwner && $displayStatus !== \App\Models\PropertyUnit::STATUS_OCCUPIED;
    
    // ============================================================
    // Determine correct unit status display
    // ============================================================
    $isTenantApproved = $unit->tenant_status === \App\Models\PropertyUnit::TENANT_STATUS_APPROVED;
    $displayStatus = ($isTenantApproved && $unit->status === \App\Models\PropertyUnit::STATUS_OCCUPIED) 
        ? \App\Models\PropertyUnit::STATUS_OCCUPIED 
        : $unit->status;
    
    $statusBadge = $unit->getStatusBadgeAttribute($displayStatus);
    
    // ============================================================
    // Inline amenity options array
    // ============================================================
    $amenityOptions = [
        'air_conditioning' => 'Air Conditioning',
        'balcony' => 'Balcony',
        'parking' => 'Parking',
        'swimming_pool' => 'Swimming Pool',
        'gym' => 'Gym',
        'laundry' => 'Laundry',
        'security' => 'Security',
        'elevator' => 'Elevator',
        'wifi' => 'WiFi',
        'furnished' => 'Furnished',
        'garden' => 'Garden',
        'playground' => 'Playground',
        'pet_friendly' => 'Pet Friendly',
        'dishwasher' => 'Dishwasher',
        'microwave' => 'Microwave',
        'oven' => 'Oven',
        'refrigerator' => 'Refrigerator',
        'washer' => 'Washer',
        'dryer' => 'Dryer',
        'heating' => 'Heating',
        'fireplace' => 'Fireplace',
        'cable_tv' => 'Cable TV',
        'satellite_tv' => 'Satellite TV',
    ];
    
    // ============================================================
    // Determine route for dashboard link
    // ============================================================
    $dashboardRoute = '#';
    if ($isOnAdminDashboard) {
        $dashboardRoute = route('admin.dashboard');
    } elseif ($isOnLandlordDashboard) {
        $dashboardRoute = route('landlord.dashboard');
    } elseif ($isOnTenantDashboard) {
        $dashboardRoute = route('tenant.dashboard');
    } elseif ($isOnDeveloperDashboard) {
        $dashboardRoute = route('developer.dashboard');
    }
@endphp

@extends($layout)

@section('title', $unit->full_unit_identifier . ' - ' . $pageTitle)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-fadeInUp">
    <!-- Main Content (3 columns) -->
    <div class="lg:col-span-3 space-y-6">
        <!-- Unit Header Card -->
        <div class="card">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
                <div>
                    <div class="flex items-center space-x-3 mb-2">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center gradient-bg">
                            <i class="fas fa-building text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold" style="color: var(--text-primary);">
                                {{ $unit->full_unit_identifier }}
                            </h2>
                            <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                <i class="far fa-clock mr-1"></i>
                                Created {{ $unit->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                    {{-- Dashboard context indicator --}}
                    @if($isOnAdminDashboard)
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                          style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <i class="fas fa-eye mr-1"></i> Admin View
                    </span>
                    @elseif($isOnLandlordDashboard)
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                          style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <i class="fas fa-eye mr-1"></i> Landlord View
                    </span>
                    @endif
                    
                    @if($isOnAdminDashboard && $isLandlord)
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                          style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-exchange-alt mr-1"></i> Multi-Role
                    </span>
                    @endif
                    
                    <a href="{{ route($routePrefix . '.index') }}" 
                       class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    
                    {{-- Show Edit button ONLY for landlords who own this unit, NOT for admins --}}
                    @if($canEdit && !$isOnAdminDashboard)
                    <a href="{{ route('property-units.edit', $unit->id) }}" 
                       class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200" 
                       style="background-color: var(--primary); color: white;">
                        <i class="fas fa-edit mr-2"></i> Edit Unit
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        <!-- Unit Information Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Basic Information Card (Visible to all) -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2"></i> Basic Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property
                            </label>
                            <a href="{{ route('properties.show', $unit->property_id) }}" 
                               class="inline-flex items-center font-medium hover:underline" 
                               style="color: var(--primary);">
                                <i class="fas fa-building mr-2"></i>
                                {{ $unit->property->property_name ?? 'N/A' }}
                            </a>
                            @if($isOnAdminDashboard)
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user mr-1"></i> {{ $unit->property->landlord->name ?? 'Unknown' }}
                            </span>
                            @endif
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Unit Type
                                </label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-tag mr-1"></i>
                                    {{ \App\Models\PropertyUnit::getTypeOptions()[$unit->unit_type] ?? $unit->unit_type }}
                                </span>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Status
                                </label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                                      style="background-color: {{ $statusBadge['color'] ?? 'rgba(var(--secondary-rgb), 0.1)' }}; color: white; border: 1px solid rgba(255,255,255,0.2);">
                                    <i class="{{ $statusBadge['icon'] ?? 'fas fa-question' }} mr-1"></i>
                                    {{ $statusBadge['text'] ?? ucfirst($displayStatus) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Available
                                </label>
                                @if($unit->is_available && $displayStatus !== \App\Models\PropertyUnit::STATUS_OCCUPIED)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                                    <i class="fas fa-check-circle mr-1"></i> Yes
                                </span>
                                @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-danger">
                                    <i class="fas fa-times-circle mr-1"></i> No
                                </span>
                                @endif
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Furnished
                                </label>
                                @if($unit->is_furnished)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-info">
                                    <i class="fas fa-couch mr-1"></i> Yes
                                </span>
                                @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-secondary">
                                    <i class="fas fa-couch mr-1"></i> No
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Information Card - Visible ONLY to owner (landlord), NOT to admins -->
            @if($showFinancialAndTenantInfo)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-money-bill-wave mr-2"></i> Financial Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Monthly Rent
                            </label>
                            <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                {{ $unit->rent_formatted ?? '₵' . number_format($unit->monthly_rent, 2) }}
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Security Deposit
                            </label>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                {{ $unit->security_deposit_formatted ?? ($unit->security_deposit ? '₵' . number_format($unit->security_deposit, 2) : 'Not set') }}
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Floor Area
                            </label>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                {{ $unit->floor_area_formatted ?? ($unit->floor_area ? number_format($unit->floor_area) . ' sq ft' : 'Not specified') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @else
            <!-- Show a placeholder or alternative content for non-owners and admins -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2"></i> Unit Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Floor Area
                            </label>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                {{ $unit->floor_area_formatted ?? ($unit->floor_area ? number_format($unit->floor_area) . ' sq ft' : 'Not specified') }}
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 rounded-lg text-center" 
                                 style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <p class="text-xl font-bold mb-1" style="color: var(--primary);">
                                    {{ $unit->bedrooms }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-bed mr-1"></i> Bedrooms
                                </p>
                            </div>
                            
                            <div class="p-3 rounded-lg text-center" 
                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                <p class="text-xl font-bold mb-1" style="color: var(--success);">
                                    {{ $unit->bathrooms }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-bath mr-1"></i> Bathrooms
                                </p>
                            </div>
                        </div>
                        
                        @if($isOnAdminDashboard)
                        <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Financial details are only visible to the property owner
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Unit Description Card (Visible to all) -->
        @if($unit->description)
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-align-left mr-2"></i> Description
                </h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ $unit->description }}</p>
            </div>
        </div>
        @endif

        <!-- Tenant Information Card - Visible ONLY to owner (landlord), NOT to admins -->
        @if($showFinancialAndTenantInfo)
        <div class="card">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-2"></i> Tenant Information
                    </h3>
                    
                    @if($unit->tenant_status)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                          style="background-color: {{ $unit->tenant_status_badge['color'] ?? 'rgba(var(--secondary-rgb), 0.1)' }}; color: white; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="{{ $unit->tenant_status_badge['icon'] ?? 'fas fa-question' }} mr-1"></i>
                        {{ $unit->tenant_status_badge['text'] ?? ucfirst(str_replace('_', ' ', $unit->tenant_status)) }}
                    </span>
                    @endif
                </div>
                
                @if($unit->tenant)
                <div class="flex flex-col md:flex-row gap-6">
                    <!-- Tenant Profile -->
                    <div class="flex-1">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                @if($unit->tenant->profile_photo)
                                <div class="w-16 h-16 rounded-full overflow-hidden border-2" style="border-color: var(--primary);">
                                    <img src="{{ Storage::url($unit->tenant->profile_photo) }}" 
                                         alt="{{ $unit->tenant->name }}" 
                                         class="w-full h-full object-cover">
                                </div>
                                @else
                                <div class="w-16 h-16 rounded-full flex items-center justify-center avatar" 
                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                    <span class="text-white text-lg font-bold">
                                        {{ substr($unit->tenant->name, 0, 1) }}
                                    </span>
                                </div>
                                @endif
                            </div>
                            
                            <div class="flex-grow">
                                <h4 class="font-bold text-lg mb-1" style="color: var(--text-primary);">
                                    {{ $unit->tenant->name }}
                                </h4>
                                
                                <div class="space-y-1">
                                    <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-envelope mr-2 flex-shrink-0"></i>
                                        <span class="truncate">{{ $unit->tenant->email }}</span>
                                    </p>
                                    
                                    @if($unit->tenant->phone)
                                    <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-phone mr-2 flex-shrink-0"></i>
                                        {{ $unit->tenant->phone }}
                                    </p>
                                    @endif
                                    
                                    @if($unit->tenant_move_in_date)
                                    <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar-alt mr-2 flex-shrink-0"></i>
                                        Moved in: {{ $unit->tenant_move_in_date->format('M d, Y') }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approval Information -->
                        @if($unit->tenant_approved_at)
                        <div class="mt-4 p-4 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-user-check text-lg mt-1" style="color: var(--info);"></i>
                                <div>
                                    <h6 class="font-semibold mb-1" style="color: var(--text-primary);">
                                        Tenant Approval Information
                                    </h6>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        Approved by: <strong>{{ $unit->approvedBy->name ?? 'Admin' }}</strong><br>
                                        Approved on: {{ $unit->tenant_approved_at->format('F d, Y \a\t h:i A') }}
                                        @if($unit->tenant_approval_notes)
                                        <br>Notes: {{ $unit->tenant_approval_notes }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    
                    <!-- Tenant Actions -->
                    <div class="flex flex-col gap-2 min-w-max">
                        @if($unit->tenant_status === \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
                        {{-- Show "Mark as Vacated" button only for owners --}}
                        @if($isOwner)
                        <a href="{{ route('property-units.mark-vacated-form', $unit->id) }}" 
                           class="px-4 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200" 
                           style="background-color: var(--warning); color: white;">
                            <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                        </a>
                        @endif
                        @endif
                    </div>
                </div>
                @else
                <!-- No Tenant -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center" 
                         style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-user-slash text-2xl"></i>
                    </div>
                    <h4 class="font-semibold mb-2" style="color: var(--text-primary);">No Tenant Assigned</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        This unit currently has no tenant assigned.
                    </p>
                    
                    @if($unit->is_available && $displayStatus !== \App\Models\PropertyUnit::STATUS_OCCUPIED)
                    @if($isOwner)
                    <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}" 
                       class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200" 
                       style="background-color: var(--primary); color: white;">
                        <i class="fas fa-user-plus mr-2"></i> Assign Tenant
                    </a>
                    @endif
                    @endif
                </div>
                @endif
            </div>
        </div>
        @else
        <!-- Show a placeholder when tenant info is hidden -->
        @if($isOnAdminDashboard && !$isOwner)
        <div class="card">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-2"></i> Tenant Information
                    </h3>
                    <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-eye mr-1"></i> Admin View
                    </span>
                </div>
                <div class="text-center py-6">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center" 
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-user-shield text-2xl" style="color: var(--info);"></i>
                    </div>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Tenant information is only visible to the property owner
                    </p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        This is an admin view - you cannot see tenant details
                    </p>
                </div>
            </div>
        </div>
        @endif
        @endif

        <!-- Unit Details & Amenities Card (Visible to all) -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Unit Details & Amenities
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Specifications -->
                    <div>
                        <h4 class="font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-ruler-combined mr-2"></i> Specifications
                        </h4>
                        
                        <div class="space-y-3">
                            @if($unit->floor_area)
                            <div class="flex items-center justify-between p-3 rounded-lg" 
                                 style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                                <span class="text-sm font-medium" style="color: var(--text-secondary);">
                                    <i class="fas fa-ruler-combined mr-2"></i> Floor Area
                                </span>
                                <span class="font-semibold" style="color: var(--text-primary);">
                                    {{ $unit->floor_area }} sq ft
                                </span>
                            </div>
                            @endif
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 rounded-lg text-center" 
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--primary);">
                                        {{ $unit->bedrooms }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-bed mr-1"></i> Bedrooms
                                    </p>
                                </div>
                                
                                <div class="p-3 rounded-lg text-center" 
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--success);">
                                        {{ $unit->bathrooms }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-bath mr-1"></i> Bathrooms
                                    </p>
                                </div>
                            </div>
                            
                            @if($unit->living_rooms > 0 || $unit->kitchens > 0)
                            <div class="grid grid-cols-2 gap-3 mt-3">
                                @if($unit->living_rooms > 0)
                                <div class="p-3 rounded-lg text-center" 
                                     style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--warning);">
                                        {{ $unit->living_rooms }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-couch mr-1"></i> Living Rooms
                                    </p>
                                </div>
                                @endif
                                
                                @if($unit->kitchens > 0)
                                <div class="p-3 rounded-lg text-center" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--info);">
                                        {{ $unit->kitchens }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-utensils mr-1"></i> Kitchens
                                    </p>
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Amenities -->
                    <div>
                        <h4 class="font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-concierge-bell mr-2"></i> Amenities
                        </h4>
                        
                        @if(!empty($unit->amenities))
                        <div class="grid grid-cols-2 gap-3">
                            @foreach($unit->amenities as $amenity)
                            @if(isset($amenityOptions[$amenity]))
                            <div class="p-3 rounded-lg flex items-center" 
                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                <i class="fas fa-check text-sm mr-2" style="color: var(--success);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    {{ $amenityOptions[$amenity] }}
                                </span>
                            </div>
                            @else
                            <div class="p-3 rounded-lg flex items-center" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-check text-sm mr-2" style="color: var(--secondary);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    {{ ucfirst(str_replace('_', ' ', $amenity)) }}
                                </span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                <i class="fas fa-slash text-xl"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">No amenities specified</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar (1 column) -->
    <div class="space-y-6">
        {{-- Quick Statistics Card - Show ONLY for landlords (NOT for admins) --}}
        @if($isLandlord && !$isOnAdminDashboard)
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2"></i> Quick Statistics
                </h3>
                
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" 
                         style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                            Rental Agreements
                        </p>
                        <p class="text-xl font-bold" style="color: var(--primary);">
                            {{ $stats['total_rental_agreements'] ?? 0 }}
                        </p>
                    </div>
                    
                    <div class="p-3 rounded-lg" 
                         style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                            Active Maintenance
                        </p>
                        <p class="text-xl font-bold" style="color: var(--warning);">
                            {{ $stats['active_maintenance_requests'] ?? 0 }}
                        </p>
                    </div>
                    
                    <div class="p-3 rounded-lg" 
                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                            Outstanding Balance
                        </p>
                        <p class="text-xl font-bold" style="color: var(--danger);">
                            ₵{{ number_format($stats['outstanding_invoices'] ?? 0, 2) }}
                        </p>
                    </div>
                    
                    <div class="p-3 rounded-lg" 
                         style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                        <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                            Total Rent Collected
                        </p>
                        <p class="text-xl font-bold" style="color: var(--success);">
                            ₵{{ number_format($stats['total_rent_collected'] ?? 0, 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @elseif($isOnAdminDashboard)
        {{-- Admin view - show a placeholder explaining stats are hidden --}}
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2"></i> Quick Statistics
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-eye mr-1"></i> Admin View
                    </span>
                </h3>
                <div class="text-center py-6">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center" 
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-user-shield text-2xl" style="color: var(--info);"></i>
                    </div>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Statistics are only visible to the property owner
                    </p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        This is an admin view - financial stats are hidden
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Quick Actions Card - Show for landlords ONLY (not for admins) --}}
        @if($isLandlord && !$isOnAdminDashboard)
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i> Quick Actions
                </h3>
                
                <div class="space-y-2">
                    @if($unit->currentLease)
                    <a href="{{ route('property-units.lease-management', $unit->id) }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-file-contract mr-2"></i> View Lease Agreement
                    </a>
                    @elseif($unit->tenant && $isOwner)
                    <a href="{{ route($routePrefix . '.create-lease.form', $unit->id) }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200" 
                       style="background-color: var(--success); color: white;">
                        <i class="fas fa-file-signature mr-2"></i> Create Lease Agreement
                    </a>
                    @endif
                    
                    @if($isOwner)
                    <a href="{{ route('property-units.maintenance-requests', $unit->id) }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-tools mr-2"></i> Maintenance Requests
                    </a>
                    
                    <a href="{{ route('property-units.financials', $unit->id) }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-chart-line mr-2"></i> Financial Information
                    </a>
                    @endif
                    
                    @if($isAssignedTenant)
                    <a href="{{ route('tenant.property-units.my-unit') }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200" 
                       style="background-color: var(--primary); color: white;">
                        <i class="fas fa-home mr-2"></i> My Unit Dashboard
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Unit Management Card -->
        @if($showManagementActions)
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2"></i> Unit Management
                    @if($isOnAdminDashboard)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-user-shield mr-1"></i> Admin
                    </span>
                    @endif
                </h3>
                
                <div class="space-y-2">
                    @if($unit->tenant_status === \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                    @if($isAdmin || $isSuperAdmin || $isOwner)
                    <a href="{{ route('property-units.assignment-details', $unit->id) }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-clipboard-check mr-2"></i> Review Assignment
                    </a>
                    @endif
                    @endif
                    
                    {{-- Assign New Tenant button - Only for owners (NOT for admins) --}}
                    @if($unit->is_available && $displayStatus !== \App\Models\PropertyUnit::STATUS_OCCUPIED)
                    @if($isOwner)
                    <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200" 
                       style="background-color: var(--primary); color: white;">
                        <i class="fas fa-user-plus mr-2"></i> Assign New Tenant
                    </a>
                    @endif
                    @endif
                    
                    @if($isAdmin || $isSuperAdmin)
                    <a href="{{ route('admin.property-units.pending-approvals') }}" 
                       class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock mr-2"></i> Pending Approvals
                    </a>
                    @endif
                    
                    {{-- Delete Unit - Only for landlord owners (NOT for admins) --}}
                    @if($canDelete && !$isOnAdminDashboard)
                    <button type="button" 
                            onclick="confirmDeleteUnit()"
                            class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium mt-2" 
                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Unit
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Recent Activity Card (Visible to all) -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2"></i> Recent Activity
                </h3>
                
                <div class="activity-timeline">
                    @forelse($activities ?? [] as $activity)
                    <div class="activity-item mb-4 last:mb-0">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                 style="background-color: rgba(var(--{{ $activity['color'] ?? 'secondary' }}-rgb), 0.1); color: var(--{{ $activity['color'] ?? 'secondary' }});">
                                <i class="{{ $activity['icon'] ?? 'fas fa-info' }} text-sm"></i>
                            </div>
                            
                            <div class="flex-grow">
                                <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    {{ $activity['description'] ?? 'Activity' }}
                                </p>
                                <div class="flex justify-between items-center">
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        By {{ $activity['user'] ?? 'System' }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ isset($activity['date']) ? $activity['date']->diffForHumans() : 'Just now' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4">
                        <div class="w-12 h-12 rounded-full mx-auto mb-3 flex items-center justify-center" 
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-history"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">No recent activity</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success/error messages
    setTimeout(() => {
        const successMsg = document.querySelector('.bg-green-100');
        const errorMsg = document.querySelector('.bg-red-100');
        
        if (successMsg) successMsg.style.display = 'none';
        if (errorMsg) errorMsg.style.display = 'none';
    }, 5000);
});

// Confirm delete unit
function confirmDeleteUnit() {
    const unitId = {{ $unit->id }};
    const unitName = '{{ $unit->full_unit_identifier }}';
    
    if (confirm(`Are you sure you want to delete unit "${unitName}"? This action cannot be undone.`)) {
        // Determine the correct route based on dashboard context
        @if($isOnAdminDashboard)
            const deleteUrl = `/admin/property-units/${unitId}`;
        @elseif($isOnLandlordDashboard)
            const deleteUrl = `/landlord/property-units/${unitId}`;
        @else
            const deleteUrl = `/property-units/${unitId}`;
        @endif
        
        fetch(deleteUrl, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.ok) {
                return response.json().then(data => {
                    if (data.success) {
                        showToast('success', data.message || 'Unit deleted successfully');
                        setTimeout(() => {
                            window.location.href = '{{ route($routePrefix . '.index') }}';
                        }, 1500);
                    } else {
                        showToast('error', data.message || 'Failed to delete unit');
                    }
                });
            } else {
                return response.json().then(data => {
                    showToast('error', data.message || 'Failed to delete unit');
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while deleting the unit');
        });
    }
}

function showToast(type, message) {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        `;
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.cssText = `
        padding: 12px 16px;
        margin-bottom: 10px;
        border-radius: 8px;
        color: white;
        font-size: 14px;
        animation: slideInRight 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
    `;
    
    // Set background color based on type
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    
    toast.style.backgroundColor = colors[type] || colors.info;
    
    // Add content
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
        <button type="button" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add close button functionality
    const closeBtn = toast.querySelector('button');
    closeBtn.addEventListener('click', () => {
        toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    });
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
    
    // Add to container
    toastContainer.appendChild(toast);
}
</script>

<style>
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(100%);
        opacity: 1;
    }
    to {
        transform: translateX(0);
        opacity: 0;
    }
}
</style>
@endsection