{{-- property_units/pending-approvals.blade.php --}}
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
    
    // ============================================================
    // CRITICAL: Determine the active dashboard from session
    // ============================================================
    $activeDashboard = $selectedRole ?? 'default';
    
    // Determine which dashboard the user is currently viewing
    $isOnAdminDashboard = in_array($selectedRole, ['admin', 'super-admin']) || ($isAdmin || $isSuperAdmin && !$selectedRole);
    $isOnLandlordDashboard = $selectedRole === 'landlord' || ($isLandlord && !$selectedRole && !$isOnAdminDashboard);
    $isOnDeveloperDashboard = $selectedRole === 'developer' || ($isDeveloper && !$selectedRole);
    
    // ============================================================
    // Determine layout based on ACTIVE DASHBOARD
    // ============================================================
    if ($isOnAdminDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.property-units';
        $isAdminView = true;
        $pageTitle = 'Pending Tenant Approvals - Admin Portal';
    } elseif ($isOnLandlordDashboard) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
        $isAdminView = false;
        $pageTitle = 'Pending Tenant Approvals - Landlord Portal';
    } elseif ($isOnDeveloperDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'developer.property-units';
        $isAdminView = false;
        $pageTitle = 'Pending Tenant Approvals - Developer';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
        $isAdminView = false;
        $pageTitle = 'Pending Tenant Approvals';
    }
    
    // ============================================================
    // Determine permissions based on ACTIVE DASHBOARD
    // ============================================================
    // Check if user can take action (approve/reject) - ONLY admins in admin context
    $canTakeAction = $isOnAdminDashboard && ($isAdmin || $isSuperAdmin);
    
    // Check if user can view financial information - ONLY landlords (NOT admins)
    $canViewFinancialInfo = $isOnLandlordDashboard;
    
    // Check if user is the owner/landlord of the property
    $isOwner = $isLandlord && isset($unit) && $unit->property->landlord_id === auth()->id();
    
    // ============================================================
    // Determine route for dashboard link
    // ============================================================
    $dashboardRoute = '#';
    if ($isOnAdminDashboard) {
        $dashboardRoute = route('admin.dashboard');
    } elseif ($isOnLandlordDashboard) {
        $dashboardRoute = route('landlord.dashboard');
    } elseif ($isOnDeveloperDashboard) {
        $dashboardRoute = route('developer.dashboard');
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, #ff9f43 100%); color: white; font-weight: 600; border-color: var(--warning);">
                        <i class="fas fa-user-clock text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-clock mr-2" style="color: var(--warning);"></i> 
                        Pending Tenant Approvals
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        @if($isOnAdminDashboard)
                            <span>Manage and review all pending tenant assignment requests</span>
                        @elseif($isOnLandlordDashboard)
                            <span>Review pending tenant requests for your properties</span>
                        @else
                            <span>Review pending tenant requests</span>
                        @endif
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-1"></i>
                        <span>{{ $pendingUnits->total() }} pending request(s)</span>
                        @if($isOnLandlordDashboard)
                            <span class="ml-1">for your properties</span>
                        @elseif($isOnAdminDashboard)
                            <span class="ml-1">across all properties</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                
                @if($dashboardRoute && $dashboardRoute != '#')
                <a href="{{ $dashboardRoute }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> Dashboard
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ session('success') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ session('error') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($pendingUnits->isEmpty())
        <!-- Empty State -->
        <div class="card">
            <div class="text-center py-16">
                <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6" 
                     style="background-color: rgba(var(--success-rgb), 0.1); border: 2px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-check-circle text-4xl" style="color: var(--success);"></i>
                </div>
                <h4 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">All Approvals Processed</h4>
                <p class="mb-8 max-w-md mx-auto text-sm" style="color: var(--text-secondary);">
                    Great news! All tenant assignment requests have been reviewed and processed.
                    There are currently no pending approvals.
                </p>
                <a href="{{ route($routePrefix . '.index') }}" 
                   class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                   style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                    <i class="fas fa-home mr-2"></i> Back to Property Units
                </a>
            </div>
        </div>
    @else
        <!-- Filters Card -->
        <div class="card mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2"></i> Filters & Search
                        @if($isOnAdminDashboard)
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user-shield mr-1"></i> Admin Mode
                            </span>
                        @endif
                    </h3>
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Use filters to narrow down results
                    </div>
                </div>
                
                <form method="GET" action="{{ route('property-units.pending-approvals') }}" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Search Input -->
                        <div>
                            <label for="search" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Search
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       name="search" 
                                       id="search" 
                                       value="{{ request('search') }}" 
                                       class="form-input w-full pl-10 pr-3"
                                       placeholder="Search unit, tenant, property..."
                                       style="padding-left: 2.5rem;">
                            </div>
                        </div>

                        <!-- Tenant Type Filter -->
                        <div>
                            <label for="tenant_type" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Tenant Type
                            </label>
                            <select name="tenant_type" 
                                    id="tenant_type" 
                                    class="form-select w-full">
                                <option value="">All Types</option>
                                <option value="new" {{ request('tenant_type') == 'new' ? 'selected' : '' }}>New Tenants</option>
                                <option value="existing" {{ request('tenant_type') == 'existing' ? 'selected' : '' }}>Existing Tenants</option>
                            </select>
                        </div>

                        <!-- Property Filter -->
                        @if($properties && $properties->count() > 0)
                            <div>
                                <label for="property_id" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Property
                                </label>
                                <select name="property_id" 
                                        id="property_id" 
                                        class="form-select w-full">
                                    <option value="">All Properties</option>
                                    @foreach($properties as $property)
                                        <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                            {{ $property->property_name }}
                                            @if($isOnAdminDashboard)
                                                ({{ $property->landlord->name ?? 'Unknown' }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <!-- Requested By Filter (Admin only) -->
                        @if($isOnAdminDashboard)
                            <div>
                                <label for="requested_by" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Requested By
                                </label>
                                <input type="text" 
                                       name="requested_by" 
                                       id="requested_by" 
                                       value="{{ request('requested_by') }}" 
                                       class="form-input w-full"
                                       placeholder="Requester name...">
                            </div>
                        @endif
                    </div>

                    <!-- Date Range -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="date_from" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                From Date
                            </label>
                            <input type="date" 
                                   name="date_from" 
                                   id="date_from" 
                                   value="{{ request('date_from') }}" 
                                   class="form-input w-full">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                To Date
                            </label>
                            <input type="date" 
                                   name="date_to" 
                                   id="date_to" 
                                   value="{{ request('date_to') }}" 
                                   class="form-input w-full">
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="flex flex-wrap gap-3 mb-4 md:mb-0">
                            <a href="{{ route($routePrefix . '.index') }}" 
                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Units
                            </a>
                            
                            @if($isOnAdminDashboard || $isOnLandlordDashboard)
                                <a href="{{ route('property-units.export', array_merge(request()->all(), ['tenant_status' => 'pending_approval'])) }}" 
                                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-file-export mr-2"></i> Export List
                                </a>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('property-units.pending-approvals') }}" 
                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-redo mr-2"></i> Reset Filters
                            </a>
                            
                            <button type="submit" 
                                    class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                                    style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                                <i class="fas fa-search mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Pending Requests List -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="p-6">
                        <!-- Results Count -->
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Showing <span class="font-bold">{{ $pendingUnits->firstItem() }}</span> to 
                                    <span class="font-bold">{{ $pendingUnits->lastItem() }}</span> of 
                                    <span class="font-bold">{{ $pendingUnits->total() }}</span> pending requests
                                </p>
                            </div>
                            <div class="text-xs px-3 py-1 rounded-full" 
                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-clock mr-1"></i> Awaiting Approval
                            </div>
                        </div>

                        <div class="space-y-6">
                            @foreach($pendingUnits as $unit)
                                @if(is_null($unit))
                                    @continue
                                @endif
                                
                                <div class="border rounded-lg p-5 transition-all duration-200 hover:border-warning hover:shadow-md" 
                                     style="border-color: var(--border-color); background-color: var(--card-bg); border-left: 4px solid var(--warning);">
                                    <!-- Unit Header -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                        <div class="mb-3 md:mb-0">
                                            <div class="flex items-center mb-2">
                                                <h4 class="font-semibold text-lg mr-3" style="color: var(--text-primary);">
                                                    <a href="{{ route('property-units.show', $unit->id) }}" 
                                                       class="hover:underline hover:text-primary">
                                                        {{ $unit->unit_number ?? 'N/A' }}
                                                    </a>
                                                </h4>
                                                @if($unit->unit_name)
                                                    <span class="text-sm px-2 py-1 rounded-full" 
                                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        {{ $unit->unit_name }}
                                                    </span>
                                                @endif
                                                @if($isOnAdminDashboard)
                                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-building mr-1"></i> {{ $unit->property->property_name ?? 'N/A' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-building mr-2"></i>
                                                <span>{{ $unit->property->property_name ?? 'N/A' }}</span>
                                                @if($isOnAdminDashboard && $unit->property->landlord)
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-user mr-1"></i>
                                                    <span>{{ $unit->property->landlord->name }}</span>
                                                @endif
                                                @if($unit->property->propertyType)
                                                    <span class="mx-2">•</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" 
                                                          style="background-color: {{ $unit->property->propertyType->color }}20; color: {{ $unit->property->propertyType->color }};">
                                                        @if($unit->property->propertyType->icon)
                                                            <i class="{{ $unit->property->propertyType->icon }} mr-1"></i>
                                                        @endif
                                                        {{ $unit->property->propertyType->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-warning">
                                                <i class="fas fa-user-clock mr-1"></i>
                                                {{ ucfirst($unit->tenant_type ?? 'unknown') }} Tenant
                                            </span>
                                            {{-- Rent Adjustment badge - only visible to landlords (not admins) --}}
                                            @if($isOnLandlordDashboard && $unit->proposed_rent && $unit->proposed_rent != $unit->monthly_rent)
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-info">
                                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                                    Rent Adjustment
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Details Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <!-- Tenant Information -->
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                                    <i class="fas fa-user mr-1"></i> Tenant Information
                                                </label>
                                                @if($unit->tenant_type === 'existing' && $unit->tenant)
                                                    <div class="flex items-start space-x-3">
                                                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 avatar-sm"
                                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-base" style="color: var(--text-primary);">
                                                                {{ $unit->tenant->name ?? 'N/A' }}
                                                            </div>
                                                            <div class="text-sm space-y-1 mt-2">
                                                                <div class="flex items-center" style="color: var(--text-secondary);">
                                                                    <i class="fas fa-phone mr-2 w-4"></i>
                                                                    {{ $unit->tenant->phone ?? 'No phone' }}
                                                                </div>
                                                                <div class="flex items-center" style="color: var(--text-secondary);">
                                                                    <i class="fas fa-envelope mr-2 w-4"></i>
                                                                    {{ $unit->tenant->email ?? 'No email' }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @elseif($unit->tenant_type === 'new')
                                                    <div class="flex items-start space-x-3">
                                                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 avatar-sm"
                                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                            <i class="fas fa-user-plus"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-base" style="color: var(--text-primary);">
                                                                {{ $unit->tenant_details['name'] ?? 'N/A' }}
                                                            </div>
                                                            <div class="text-sm space-y-1 mt-2">
                                                                <div class="flex items-center" style="color: var(--text-secondary);">
                                                                    <i class="fas fa-phone mr-2 w-4"></i>
                                                                    {{ $unit->tenant_details['phone'] ?? 'No phone' }}
                                                                </div>
                                                                <div class="flex items-center" style="color: var(--text-secondary);">
                                                                    <i class="fas fa-envelope mr-2 w-4"></i>
                                                                    {{ $unit->tenant_details['email'] ?? 'No email' }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="text-center py-4">
                                                        <p class="text-sm italic" style="color: var(--text-secondary);">
                                                            <i class="fas fa-user-slash mr-1"></i>
                                                            Tenant information not available
                                                        </p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Request Details -->
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                                    <i class="fas fa-clipboard-list mr-1"></i> Request Details
                                                </label>
                                                <div class="space-y-3">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                            <i class="fas fa-user-tie"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                                {{ $unit->requestedBy->name ?? 'N/A' }}
                                                                @if($isOnAdminDashboard)
                                                                    <span class="text-xs" style="color: var(--text-secondary);">
                                                                        (Landlord)
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                <i class="fas fa-calendar mr-1"></i>
                                                                {{ $unit->tenant_requested_at ? $unit->tenant_requested_at->format('M d, Y H:i') : 'N/A' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    {{-- Proposed Rent Section - Visible ONLY to landlords (NOT admins) --}}
                                                    @if($isOnLandlordDashboard)
                                                    <div class="pt-3 border-t" style="border-color: rgba(var(--warning-rgb), 0.2);">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <p class="text-sm font-medium" style="color: var(--primary);">
                                                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                                                    Proposed Rent
                                                                </p>
                                                                <p class="text-lg font-bold mt-1" style="color: var(--primary);">
                                                                    GHS {{ number_format($unit->proposed_rent ?? $unit->monthly_rent, 2) }}
                                                                </p>
                                                            </div>
                                                            @if($unit->proposed_rent && $unit->proposed_rent != $unit->monthly_rent)
                                                                <div class="text-right">
                                                                    <p class="text-xs font-medium" style="color: var(--text-secondary);">
                                                                        Base Rent
                                                                    </p>
                                                                    <p class="text-sm line-through" style="color: var(--danger);">
                                                                        GHS {{ number_format($unit->monthly_rent, 2) }}
                                                                    </p>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @elseif($isOnAdminDashboard)
                                                    <!-- Admin view - show placeholder instead of Proposed Rent -->
                                                    <div class="pt-3 border-t" style="border-color: rgba(var(--warning-rgb), 0.2);">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <p class="text-sm font-medium" style="color: var(--text-secondary);">
                                                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                                                    Proposed Rent
                                                                </p>
                                                                <div class="flex items-center mt-1">
                                                                    <i class="fas fa-lock mr-2" style="color: var(--warning);"></i>
                                                                    <p class="text-sm" style="color: var(--text-secondary);">
                                                                        Hidden for admins
                                                                    </p>
                                                                </div>
                                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                    Only visible to landlords
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if($unit->tenant_approval_notes)
                                        <div class="mb-6 p-4 rounded-lg" 
                                             style="background-color: rgba(var(--warning-rgb), 0.05); border-left: 3px solid var(--warning);">
                                            <p class="text-xs font-medium mb-2 flex items-center" style="color: var(--warning);">
                                                <i class="fas fa-sticky-note mr-2"></i> LANDLORD NOTES
                                            </p>
                                            <p class="text-sm" style="color: var(--text-secondary);">
                                                {{ $unit->tenant_approval_notes }}
                                            </p>
                                        </div>
                                    @endif

                                    <!-- Action Buttons -->
                                    <div class="flex flex-wrap justify-end gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                                        @if($isOnAdminDashboard)
                                            <!-- Admin Actions -->
                                            <button onclick="openRejectModal({{ $unit->id }})" 
                                                    class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                <i class="fas fa-times mr-2"></i> Reject
                                            </button>
                                            
                                            <a href="{{ route('admin.property-units.assignment-details', $unit->id) }}" 
                                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                                <i class="fas fa-eye mr-2"></i> Review Details
                                            </a>
                                            
                                            <a href="{{ route('admin.property-units.assignment-details', $unit->id) }}" 
                                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200"
                                               style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                                                <i class="fas fa-clipboard-check mr-2"></i> Approve
                                            </a>
                                        @elseif($isOnLandlordDashboard)
                                            <!-- Landlord View Only Message -->
                                            <div class="flex-1">
                                                <div class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium" 
                                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    <span>View only - Awaiting admin approval</span>
                                                </div>
                                            </div>
                                            <!-- Landlord can only view basic details -->
                                            <a href="{{ route('landlord.property-units.show', $unit->id) }}" 
                                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                                <i class="fas fa-eye mr-2"></i> View Unit
                                            </a>
                                        @else
                                            <!-- Other users (shouldn't see this page) -->
                                            <div class="text-sm text-gray-500 italic">
                                                No actions available for your role
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="flex flex-col md:flex-row items-center justify-between pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                            <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                                <span class="font-medium">Showing</span> 
                                <span class="font-bold">{{ $pendingUnits->firstItem() }}</span> 
                                <span class="font-medium">to</span> 
                                <span class="font-bold">{{ $pendingUnits->lastItem() }}</span> 
                                <span class="font-medium">of</span> 
                                <span class="font-bold">{{ $pendingUnits->total() }}</span> 
                                <span class="font-medium">entries</span>
                            </div>
                            <div class="pagination">
                                {{ $pendingUnits->appends(request()->except('page'))->links('vendor.pagination.tailwind') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Statistics Card -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-chart-pie mr-2"></i> Approval Statistics
                                @if($isOnAdminDashboard)
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        Admin
                                    </span>
                                @endif
                            </h3>
                            <i class="fas fa-info-circle text-sm" style="color: var(--text-secondary);"></i>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Total Pending -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Pending</p>
                                        <p class="text-3xl font-bold" style="color: var(--warning);">{{ $pendingUnits->total() }}</p>
                                    </div>
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                                        <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stats Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- New Tenants -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                                            <i class="fas fa-user-plus" style="color: var(--primary);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">New Tenants</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['new_tenants'] ?? 0 }}</p>
                                </div>
                                
                                <!-- Existing Tenants -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--success-rgb), 0.1);">
                                            <i class="fas fa-users" style="color: var(--success);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Existing Tenants</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--success);">{{ $stats['existing_tenants'] ?? 0 }}</p>
                                </div>
                                
                                <!-- Today's Requests -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                                            <i class="fas fa-calendar-day" style="color: var(--danger);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Today's Requests</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['today_requests'] ?? 0 }}</p>
                                </div>
                                
                                <!-- Total Rent Value - Visible ONLY to landlords (NOT admins) -->
                                @if($isOnLandlordDashboard)
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--info-rgb), 0.1);">
                                            <i class="fas fa-money-bill-wave" style="color: var(--info);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Total Rent</span>
                                    </div>
                                    <p class="text-lg font-bold" style="color: var(--info);">
                                        GHS {{ number_format($pendingUnits->sum('proposed_rent') ?? $pendingUnits->sum('monthly_rent'), 0) }}
                                    </p>
                                </div>
                                @elseif($isOnAdminDashboard)
                                <!-- Admin view - show placeholder instead of Total Rent -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--info-rgb), 0.1);">
                                            <i class="fas fa-money-bill-wave" style="color: var(--info);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Total Rent</span>
                                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            Admin
                                        </span>
                                    </div>
                                    <div class="text-center py-2">
                                        <p class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-lock mr-1"></i>
                                            Hidden for admins
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Only visible to landlords
                                        </p>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Filters Card -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-bolt mr-2"></i> Quick Filters
                            </h3>
                            <button type="button" onclick="resetFilters()" 
                                    class="text-xs px-3 py-1 rounded-lg" 
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-redo mr-1"></i> Reset
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <!-- New Tenants Filter -->
                            <a href="{{ route('property-units.pending-approvals', ['tenant_type' => 'new']) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md {{ request('tenant_type') == 'new' ? 'border-primary bg-primary text-white' : 'border' }}"
                               style="{{ request('tenant_type') == 'new' ? 'border-color: var(--primary);' : 'border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);' }}">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 {{ request('tenant_type') == 'new' ? 'bg-white/20' : '' }}"
                                     style="{{ request('tenant_type') != 'new' ? 'background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);' : 'color: white;' }}">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">New Tenants</span>
                                    <span class="text-xs block mt-1" style="{{ request('tenant_type') == 'new' ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">
                                        {{ $stats['new_tenants'] ?? 0 }} pending requests
                                    </span>
                                </div>
                                @if(request('tenant_type') == 'new')
                                    <i class="fas fa-check text-white"></i>
                                @endif
                            </a>
                            
                            <!-- Existing Tenants Filter -->
                            <a href="{{ route('property-units.pending-approvals', ['tenant_type' => 'existing']) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md {{ request('tenant_type') == 'existing' ? 'border-success bg-success text-white' : 'border' }}"
                               style="{{ request('tenant_type') == 'existing' ? 'border-color: var(--success);' : 'border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);' }}">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 {{ request('tenant_type') == 'existing' ? 'bg-white/20' : '' }}"
                                     style="{{ request('tenant_type') != 'existing' ? 'background-color: rgba(var(--success-rgb), 0.1); color: var(--success);' : 'color: white;' }}">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">Existing Tenants</span>
                                    <span class="text-xs block mt-1" style="{{ request('tenant_type') == 'existing' ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">
                                        {{ $stats['existing_tenants'] ?? 0 }} pending requests
                                    </span>
                                </div>
                                @if(request('tenant_type') == 'existing')
                                    <i class="fas fa-check text-white"></i>
                                @endif
                            </a>
                            
                            <!-- Today's Requests Filter -->
                            <a href="{{ route('property-units.pending-approvals', ['date_from' => today()->format('Y-m-d')]) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md {{ request('date_from') == today()->format('Y-m-d') ? 'border-danger bg-danger text-white' : 'border' }}"
                               style="{{ request('date_from') == today()->format('Y-m-d') ? 'border-color: var(--danger);' : 'border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);' }}">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 {{ request('date_from') == today()->format('Y-m-d') ? 'bg-white/20' : '' }}"
                                     style="{{ request('date_from') != today()->format('Y-m-d') ? 'background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);' : 'color: white;' }}">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">Today's Requests</span>
                                    <span class="text-xs block mt-1" style="{{ request('date_from') == today()->format('Y-m-d') ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">
                                        {{ $stats['today_requests'] ?? 0 }} today
                                    </span>
                                </div>
                                @if(request('date_from') == today()->format('Y-m-d'))
                                    <i class="fas fa-check text-white"></i>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-rocket mr-2"></i> Quick Actions
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    Admin
                                </span>
                            @endif
                        </h3>
                        
                        <div class="space-y-3">
                            @if($isOnAdminDashboard)
                                <!-- Admin Management View -->
                                <a href="{{ route('admin.property-units.pending-approvals') }}" 
                                   class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                                   style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(147, 51, 234, 0.1); color: #9333ea;">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div>
                                        <span class="font-medium block">Admin Management View</span>
                                        <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                            Full administrative control
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                                </a>
                                
                                <!-- Bulk Approval -->
                                <a href="{{ route('admin.property-units.bulk-approval') }}" 
                                   class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                                   style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(249, 115, 22, 0.1); color: #f97316;">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <div>
                                        <span class="font-medium block">Bulk Approval</span>
                                        <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                            Approve multiple requests
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                                </a>
                            @endif
                            
                            @if($isOnLandlordDashboard || $isLandlord)
                                <!-- My Property Units -->
                                <a href="{{ route('landlord.property-units.index', ['tenant_status' => 'pending_approval']) }}" 
                                   class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                                   style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(249, 115, 22, 0.1); color: #f97316;">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div>
                                        <span class="font-medium block">My Property Units</span>
                                        <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                            View all your units
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                                </a>
                            @endif
                            
                            <!-- All Property Units -->
                            <a href="{{ route($routePrefix . '.index') }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                               style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(107, 114, 128, 0.1); color: #6b7280;">
                                    <i class="fas fa-th-list"></i>
                                </div>
                                <div>
                                    <span class="font-medium block">All Property Units</span>
                                    <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                        Browse all property units
                                    </span>
                                </div>
                                <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Reject Modal (Only show for admins) -->
@if($isOnAdminDashboard)
<div id="rejectModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> 
                    Reject Tenant Assignment
                </h3>
                <button type="button" 
                        id="closeRejectModalBtn" 
                        onclick="closeRejectModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="rejectForm" method="POST" action="" class="theme-modal-body-compact">
                @csrf
                @method('POST')
                
                <!-- Rejection Reason -->
                <div class="mb-6">
                    <label for="rejection_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Rejection Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea name="rejection_reason" 
                              id="rejection_reason" 
                              required 
                              rows="4"
                              class="form-textarea w-full"
                              placeholder="Please provide a clear reason for rejecting this tenant assignment request..."
                              style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);"></textarea>
                </div>
                
                <!-- Resubmission Options -->
                <div class="mb-6">
                    <div class="flex items-center space-x-3 mb-3">
                        <input type="checkbox" 
                               id="allow_resubmission" 
                               name="allow_resubmission" 
                               value="1" 
                               class="form-checkbox">
                        <label for="allow_resubmission" class="text-sm font-medium" style="color: var(--text-primary);">
                            Allow landlord to resubmit
                        </label>
                    </div>
                    
                    <div id="resubmissionNotes" class="hidden p-4 rounded-lg" 
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <label for="resubmission_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Resubmission Instructions
                        </label>
                        <textarea name="resubmission_notes" 
                                  id="resubmission_notes" 
                                  rows="3"
                                  class="form-textarea w-full"
                                  placeholder="Provide specific instructions for resubmission..."
                                  style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);"></textarea>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            These instructions will be sent to the landlord
                        </p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="theme-modal-footer-compact">
                    <button type="button" 
                            onclick="closeRejectModal()" 
                            class="px-4 py-2 rounded-lg text-sm font-medium" 
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                            style="background-color: var(--danger); color: white; border: 2px solid var(--danger);">
                        <i class="fas fa-times mr-2"></i> Confirm Rejection
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
document.addEventListener('DOMContentLoaded', function() {
    // Resubmission toggle (only for admin modal)
    const resubmissionCheckbox = document.getElementById('allow_resubmission');
    const resubmissionNotesDiv = document.getElementById('resubmissionNotes');
    
    if (resubmissionCheckbox && resubmissionNotesDiv) {
        resubmissionCheckbox.addEventListener('change', function() {
            if (this.checked) {
                resubmissionNotesDiv.classList.remove('hidden');
                const textarea = resubmissionNotesDiv.querySelector('textarea');
                if (textarea) textarea.focus();
            } else {
                resubmissionNotesDiv.classList.add('hidden');
                const textarea = resubmissionNotesDiv.querySelector('textarea');
                if (textarea) textarea.value = '';
            }
        });
    }
    
    // Auto-hide success and error messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }
    
    // Close button hover effect for reject modal
    const closeButton = document.getElementById('closeRejectModalBtn');
    if (closeButton) {
        closeButton.addEventListener('mouseenter', function() {
            this.style.color = 'var(--text-primary)';
            this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
        });
        closeButton.addEventListener('mouseleave', function() {
            this.style.color = 'var(--text-secondary)';
            this.style.backgroundColor = 'transparent';
        });
    }
});

let currentUnitId = null;

function openRejectModal(unitId) {
    currentUnitId = unitId;
    const form = document.getElementById('rejectForm');
    
    // Set the correct route - only admins can reject
    @if($isOnAdminDashboard)
        form.action = '/admin/property-units/' + unitId + '/reject-tenant';
    @endif
    
    // Reset form
    form.reset();
    const resubmissionNotesDiv = document.getElementById('resubmissionNotes');
    if (resubmissionNotesDiv) {
        resubmissionNotesDiv.classList.add('hidden');
    }
    
    // Show modal
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentUnitId = null;
    }
}

function resetFilters() {
    window.location.href = "{{ route('property-units.pending-approvals') }}";
}

// Close modal with Escape key (only for admin)
@if($isOnAdminDashboard)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});
@endif

// Close modal on outside click (only for admin)
const rejectModal = document.getElementById('rejectModal');
if (rejectModal) {
    rejectModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
}

// Animation for quick filter cards
document.querySelectorAll('.quick-filter-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>

<style>
/* Search input specific styling */
.relative .form-input {
    padding-left: 2.5rem;
    padding-right: 0.75rem;
}

.relative .absolute.inset-y-0.left-0.pl-3 {
    padding-left: 0.75rem;
    z-index: 10;
}

.relative .absolute.inset-y-0.left-0.pl-3 .fas.fa-search {
    font-size: 0.875rem;
    color: #9ca3af;
}

/* Reject Modal Styling */
#rejectModal {
    animation: modalFadeIn 0.3s ease-out;
    z-index: 9999;
}

.theme-modal-compact {
    width: 95vw;
    max-width: 500px;
    margin: 1rem auto;
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.theme-modal-header-compact {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--header-bg);
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.theme-modal-header-compact h3 {
    color: var(--text-primary) !important;
    font-weight: 600;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    margin: 0;
}

.theme-modal-body-compact {
    padding: 1.5rem;
    background-color: var(--card-bg);
}

.theme-modal-footer-compact {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Badge Styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Form Styles */
.form-input, .form-select, .form-textarea {
    background-color: var(--bg-input);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-input);
    cursor: pointer;
    transition: all 0.2s;
}

.form-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Pagination Styling */
.pagination {
    display: flex;
    gap: 0.25rem;
}

.pagination .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-input);
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.pagination .page-link:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

.pagination .page-item.active .page-link {
    background-color: var(--primary);
    border-color: var(--primary);
    color: white;
}

.pagination .page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: var(--bg-secondary);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .lg\:col-span-2 {
        grid-column: 1;
    }
    
    .space-y-6 > * + * {
        margin-top: 1.5rem;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .flex.flex-wrap {
        gap: 0.5rem;
    }
    
    .card .p-6 {
        padding: 1rem;
    }
    
    .text-lg {
        font-size: 1rem;
    }
    
    .text-2xl {
        font-size: 1.5rem;
    }
    
    .text-3xl {
        font-size: 1.75rem;
    }
    
    /* Adjust search input padding on mobile */
    .relative .form-input {
        padding-left: 2.25rem;
    }
    
    .relative .absolute.inset-y-0.left-0.pl-3 {
        padding-left: 0.5rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .space-y-6 {
        gap: 1rem;
    }
    
    .border.p-5 {
        padding: 1rem;
    }
    
    .flex.flex-wrap.justify-end.gap-3 {
        justify-content: stretch;
    }
    
    .flex.flex-wrap.justify-end.gap-3 button,
    .flex.flex-wrap.justify-end.gap-3 a {
        flex: 1;
        justify-content: center;
    }
    
    /* Stack action buttons vertically on very small screens */
    .flex.flex-wrap.gap-3 {
        flex-direction: column;
    }
    
    .flex.flex-wrap.gap-3 > * {
        width: 100%;
    }
}

/* Dark theme adjustments for form elements */
[data-theme="dark"] .form-input,
[data-theme="dark"] .form-select,
[data-theme="dark"] .form-textarea {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

[data-theme="dark"] .form-input:focus,
[data-theme="dark"] .form-select:focus,
[data-theme="dark"] .form-textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
}

/* Dark theme for search icon */
[data-theme="dark"] .relative .absolute.inset-y-0.left-0.pl-3 .fas.fa-search {
    color: #a0a0a0;
}

/* Ensure all text is visible in dark mode */
[data-theme="dark"] .text-sm,
[data-theme="dark"] .text-xs,
[data-theme="dark"] .text-lg,
[data-theme="dark"] .text-xl {
    color: inherit !important;
}

/* Ensure hover effects work in dark mode */
[data-theme="dark"] a:hover,
[data-theme="dark"] button:hover {
    color: inherit !important;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-primary);
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Animation keyframes */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Avatar sizes */
.avatar-sm {
    width: 2.5rem;
    height: 2.5rem;
}

/* Line through styling */
.line-through {
    text-decoration: line-through;
    opacity: 0.7;
}

/* Transition utilities */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.duration-200 {
    transition-duration: 200ms;
}

/* Hover shadow effects */
.hover\:shadow-md:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.hover\:shadow-xl:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

/* Ensure the Create button is visible in dark mode */
[data-theme="dark"] button[type="submit"],
[data-theme="dark"] .btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3) !important;
}

[data-theme="dark"] button[type="submit"]:hover,
[data-theme="dark"] .btn-primary:hover {
    background-color: var(--primary-dark) !important;
    border-color: var(--primary-dark) !important;
    box-shadow: 0 6px 16px rgba(var(--primary-rgb), 0.4) !important;
}

/* View only message styling */
.bg-warning\/10 {
    background-color: rgba(var(--warning-rgb), 0.1);
}

.text-warning {
    color: var(--warning);
}

.border-warning\/20 {
    border-color: rgba(var(--warning-rgb), 0.2);
}

/* Placeholder styling */
.form-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Search input specific styling for dark/light themes */
.relative .form-input {
    transition: all 0.3s ease;
}

.relative .form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Search icon positioning fix */
.relative .absolute.inset-y-0.left-0 {
    pointer-events: none;
}

/* Ensure search icon is visible in both themes */
[data-theme="dark"] .relative .absolute.inset-y-0.left-0.pl-3 .fas.fa-search {
    color: #a0a0a0;
}

[data-theme="light"] .relative .absolute.inset-y-0.left-0.pl-3 .fas.fa-search {
    color: #6b7280;
}
</style>
@endsection