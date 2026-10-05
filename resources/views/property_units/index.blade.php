{{-- property_units/index.blade.php --}}
@php
    // Get the authenticated user
    $user = auth()->user();
    
    // CRITICAL FIX: Get the selected role from session (dashboard switch)
    $selectedRole = session('selected_role');
    
    // Determine if user has landlord role (for feature access)
    $hasLandlordRole = $user->isLandlord();
    
    // Determine if user is a landlord by LEGACY type (backward compatibility)
    $isLandlordByLegacy = $user->type === \App\Models\User::TYPE_LANDLORD;
    
    // Determine if user is a landlord by ROLE (spatie/permission)
    $isLandlordByRole = $user->hasRole('landlord');
    
    // TRUE LANDLORD: User has landlord role in any form (legacy or role-based)
    $isLandlord = $hasLandlordRole || $isLandlordByLegacy || $isLandlordByRole;
    
    // TRUE ADMIN: User has admin role in any form
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isDeveloper = $user->isDeveloper();
    $isFieldAgent = $user->isFieldAgent();
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
    $isOnFieldAgentDashboard = $selectedRole === 'field-agent' || ($isFieldAgent && !$selectedRole);
    
    // ============================================================
    // CRITICAL: Determine layout based on ACTIVE DASHBOARD, not just role
    // ============================================================
    if ($isOnAdminDashboard) {
        // Admin/Super Admin viewing from admin dashboard
        $layout = 'layouts.app';
        $routePrefix = 'admin.property-units';
        $isAdminView = true;
        $pageTitle = 'Property Units - Admin Portal';
    } elseif ($isOnLandlordDashboard) {
        // Landlord viewing from landlord dashboard
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
        $isAdminView = false;
        $pageTitle = 'My Property Units';
    } elseif ($isOnTenantDashboard) {
        $layout = 'layouts.tenant';
        $routePrefix = 'tenant.property-units';
        $isAdminView = false;
        $pageTitle = 'My Unit';
    } elseif ($isOnDeveloperDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'developer.property-units';
        $isAdminView = false;
        $pageTitle = 'Property Units - Developer';
    } elseif ($isOnFieldAgentDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'field-agent.property-units';
        $isAdminView = false;
        $pageTitle = 'Property Units';
    } else {
        // Default fallback
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
        $isAdminView = false;
        $pageTitle = 'Property Units';
    }
    
    // ============================================================
    // Determine permissions based on ACTIVE DASHBOARD
    // ============================================================
    // Only allow creation if viewing as landlord OR if user has landlord role AND is on admin dashboard
    $canCreateUnits = false;
    
    if ($isOnLandlordDashboard && $isLandlord) {
        // Landlord viewing their own dashboard - CAN create
        $canCreateUnits = true;
    } elseif ($isOnAdminDashboard && $isLandlord && !$isAdminView) {
        // Admin/Super Admin who ALSO has landlord role - viewing from admin dashboard
        // Allow creation but with admin context
        $canCreateUnits = true;
    } elseif ($isOnAdminDashboard && ($isAdmin || $isSuperAdmin) && !$isLandlord) {
        // Pure admin without landlord role - CANNOT create
        $canCreateUnits = false;
    } else {
        $canCreateUnits = false;
    }
    
    // Check if user can view trash (only landlords in landlord context)
    $canViewTrash = $isOnLandlordDashboard && $isLandlord;
    
    // Get trashed units count (only for landlords in landlord context)
    $trashedCount = 0;
    if ($canViewTrash) {
        $trashedCount = \App\Models\PropertyUnit::onlyTrashed()
            ->whereHas('property', function ($q) {
                $q->where('landlord_id', auth()->id());
            })
            ->count();
    }
    
    // ============================================================
    // Get status options from model
    // ============================================================
    $statusOptions = \App\Models\PropertyUnit::getStatusOptions();
    $tenantStatusOptions = \App\Models\PropertyUnit::getTenantStatusOptions();
    $typeOptions = \App\Models\PropertyUnit::getTypeOptions();
    
    // ============================================================
    // Get properties with pending tenants (for alert)
    // ============================================================
    // FIX: Filter out tenants who have been vacated or are already assigned to units
    $assignedTenantIds = \App\Models\PropertyUnit::whereNotNull('tenant_id')
        ->whereIn('tenant_status', [
            \App\Models\PropertyUnit::TENANT_STATUS_APPROVED, 
            \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
        ])
        ->pluck('tenant_id')
        ->toArray();
    
    $vacatedTenantIds = \App\Models\PropertyUnit::where('tenant_status', \App\Models\PropertyUnit::TENANT_STATUS_VACATED)
        ->whereNotNull('tenant_id')
        ->pluck('tenant_id')
        ->toArray();
    
    $excludedTenantIds = array_unique(array_merge($assignedTenantIds, $vacatedTenantIds));
    
    // Show pending tenants alert if:
    // 1. On landlord dashboard (landlord sees their own)
    // 2. On admin dashboard (admin sees all)
    $showPendingTenantsAlert = ($isOnLandlordDashboard && $isLandlord) || ($isOnAdminDashboard && ($isAdmin || $isSuperAdmin));
    
    $propertiesWithPendingTenants = collect();
    if ($showPendingTenantsAlert) {
        $propertiesWithPendingTenants = \App\Models\Property::whereHas('tenants', function ($q) use ($excludedTenantIds) {
                $q->where('users.type', \App\Models\User::TYPE_TENANT)
                  ->whereNotIn('users.id', $excludedTenantIds);
            })
            ->when($isOnLandlordDashboard && $isLandlord, function ($q) {
                $q->where('landlord_id', auth()->id());
            })
            ->with(['tenants' => function ($q) use ($excludedTenantIds) {
                $q->where('users.type', \App\Models\User::TYPE_TENANT)
                  ->whereNotIn('users.id', $excludedTenantIds);
            }])
            ->get()
            ->filter(function ($property) {
                return $property->tenants->isNotEmpty();
            })
            ->values();
    }
    
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
    } elseif ($isOnFieldAgentDashboard) {
        $dashboardRoute = route('field-agent.dashboard');
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div id="property-units-page" class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-building text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2" style="color: var(--primary);"></i> 
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        @if($isOnAdminDashboard)
                            <span>Viewing all property units across the system</span>
                        @elseif($isOnLandlordDashboard)
                            <span>Manage all your property units in one place</span>
                        @elseif($isOnTenantDashboard)
                            <span>View your assigned unit details</span>
                        @else
                            <span>Property units overview</span>
                        @endif
                        
                        @if(isset($stats) && isset($stats['total_units']))
                        <span class="mx-2">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ $stats['total_units'] }} units total</span>
                        @endif
                        
                        @if($canViewTrash && $trashedCount > 0)
                        <span class="mx-2">•</span>
                        <i class="fas fa-trash mr-1"></i>
                        <span>{{ $trashedCount }} units in trash</span>
                        @endif
                        
                        @if($isOnAdminDashboard)
                            <span class="mx-2">•</span>
                            <i class="fas fa-user-shield mr-1"></i>
                            <span>Admin Mode</span>
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
                
                {{-- NEW UNIT BUTTON - Only show for landlords (not admins) --}}
                @if($canCreateUnits && !$isOnAdminDashboard)
                <a href="{{ route('property-units.create') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-plus-circle mr-1"></i> New Unit
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Success Messages -->
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

    @if(session('bulk_success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Bulk Action Success!</span>
            <span class="ml-2">{{ session('bulk_success') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

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

    @if(session('bulk_error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Bulk Action Error!</span>
            <span class="ml-2">{{ session('bulk_error') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Navigation Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    @if($isOnTenantDashboard)
                        <a href="{{ route('tenant.dashboard') }}" 
                           class="inline-flex items-center text-sm font-medium" 
                           style="color: var(--primary);">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    @elseif($isOnLandlordDashboard)
                        {{-- Landlord Dashboard: Show create and trash links --}}
                        @if($canCreateUnits)
                        <a href="{{ route('property-units.create') }}" 
                           class="inline-flex items-center text-sm font-medium" 
                           style="color: var(--primary);">
                            <i class="fas fa-plus-circle mr-2"></i> Add New Unit
                        </a>
                        @endif
                        
                        @if($canViewTrash && $trashedCount > 0)
                        <a href="{{ route('property-units.trash') }}" 
                           class="inline-flex items-center text-sm font-medium px-3 py-1 rounded-lg"
                           style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash mr-2"></i> 
                            Trash ({{ $trashedCount }})
                        </a>
                        @endif
                    @elseif($isOnAdminDashboard)
                        {{-- Admin Dashboard: Show system info, NOT create link --}}
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>Property units are managed by landlords</span>
                        </div>
                    @else
                        {{-- Default for other roles --}}
                        @if($canCreateUnits)
                        <a href="{{ route('property-units.create') }}" 
                           class="inline-flex items-center text-sm font-medium" 
                           style="color: var(--primary);">
                            <i class="fas fa-plus-circle mr-2"></i> Add New Unit
                        </a>
                        @endif
                    @endif
                </div>
                
                <div class="flex items-center space-x-3">
                    @if(!$isTenant && ($isAdmin || $isSuperAdmin || $isLandlord))
                    <a href="{{ route($routePrefix . '.pending-approvals') }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-clock mr-1"></i>
                        @if(isset($stats) && isset($stats['pending_approval']))
                            Pending Approvals ({{ $stats['pending_approval'] }})
                        @else
                            Pending Approvals
                        @endif
                    </a>
                    @endif
                    
                    @if(!$isTenant)
                    <span class="text-xs px-3 py-1 rounded-full" 
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-filter mr-1"></i> Filters Applied: {{ request()->hasAny(['search', 'property_id', 'status', 'tenant_status']) ? 'Yes' : 'No' }}
                    </span>
                    @endif
                    
                    {{-- Dashboard context indicator --}}
                    @if($isOnAdminDashboard)
                    <span class="text-xs px-2 py-1 rounded-full" style="background: rgba(var(--info-rgb), 0.15); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <i class="fas fa-eye mr-1"></i> Admin View
                    </span>
                    @elseif($isOnLandlordDashboard)
                    <span class="text-xs px-2 py-1 rounded-full" style="background: rgba(var(--success-rgb), 0.15); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <i class="fas fa-eye mr-1"></i> Landlord View
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    @if(!$isTenant && isset($stats))
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Total Units Card -->
        <div class="card stat-card users-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-building text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Units</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['total_units']) }}</p>
                </div>
                <div class="text-right">
                    @if($stats['total_units'] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                        <i class="fas fa-chart-line mr-1"></i>
                        {{ round(($stats['occupied_units'] / $stats['total_units']) * 100, 1) }}% occupied
                    </span>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Available Units Card -->
        <div class="card stat-card revenue-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-home text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Available Units</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['available_units']) }}</p>
                </div>
                <div class="text-right">
                    @if($stats['available_units'] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ round(($stats['available_units'] / $stats['total_units']) * 100, 1) }}% available
                    </span>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Occupied Units Card -->
        <div class="card stat-card conversion-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Occupied Units</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['occupied_units']) }}</p>
                </div>
                <div class="text-right">
                    @if($stats['occupied_units'] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                        <i class="fas fa-user-check mr-1"></i>
                        {{ round(($stats['occupied_units'] / $stats['total_units']) * 100, 1) }}% occupied
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Trash Bin Notification (only in landlord context) -->
    @if($canViewTrash && $trashedCount > 0)
    <div class="card border-l-4 mb-6" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <i class="fas fa-trash text-lg" style="color: var(--danger);"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-1 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Trash Bin Notification
                        </h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            You have {{ $trashedCount }} deleted unit{{ $trashedCount > 1 ? 's' : '' }} in the trash bin.
                            Items in trash will be automatically deleted after 30 days.
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('property-units.trash') }}" 
                       class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white btn-danger">
                        <i class="fas fa-trash mr-2"></i> View Trash Bin
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Pending Tenant Registration Alert -->
    @if($showPendingTenantsAlert && $propertiesWithPendingTenants->count() > 0)
    <div class="card border-l-4 mb-6" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0 mr-4 mt-1">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-user-clock text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2"></i> Pending Tenant Assignments
                    </h4>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        @if($isOnLandlordDashboard)
                            You have tenants registered to your properties. Create units to assign them:
                        @elseif($isOnAdminDashboard)
                            There are tenants registered to properties awaiting unit assignment:
                        @endif
                    </p>
                    
                    <div class="space-y-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar" id="pendingTenantsList">
                        @foreach($propertiesWithPendingTenants as $property)
                            @foreach($property->tenants as $tenant)
                                <div class="bg-card rounded-lg p-4 border" style="border-color: var(--border-color);" id="tenant-card-{{ $tenant->id }}">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="flex items-start space-x-3">
                                            <!-- Tenant Avatar -->
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                                     style="background-color: rgba(var(--warning-rgb), 0.1); border: 2px solid rgba(var(--warning-rgb), 0.3);">
                                                    <i class="fas fa-user text-lg" style="color: var(--warning);"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Tenant Details -->
                                            <div class="flex-grow">
                                                <div class="flex items-center flex-wrap gap-2 mb-1">
                                                    <span class="font-semibold" style="color: var(--text-primary);">
                                                        {{ $tenant->name }}
                                                    </span>
                                                    <span class="text-xs px-2 py-0.5 rounded-full badge-warning">
                                                        <i class="fas fa-clock mr-1"></i> Pending
                                                    </span>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1 text-sm mb-2">
                                                    <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-envelope mr-2"></i>
                                                        {{ $tenant->email }}
                                                    </div>
                                                    @if($tenant->phone)
                                                    <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-phone mr-2"></i>
                                                        {{ $tenant->phone }}
                                                    </div>
                                                    @endif
                                                    <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-building mr-2"></i>
                                                        Property: <span class="font-medium ml-1">{{ $property->property_name }}</span>
                                                    </div>
                                                    <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-calendar-alt mr-2"></i>
                                                        Registered: {{ $tenant->created_at->format('M d, Y') }}
                                                    </div>
                                                </div>
                                                
                                                @if($tenant->preferred_move_in_date)
                                                <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                                    <i class="fas fa-calendar-check mr-2" style="color: var(--success);"></i>
                                                    Preferred move-in: {{ \Carbon\Carbon::parse($tenant->preferred_move_in_date)->format('M d, Y') }}
                                                </div>
                                                @endif
                                                
                                                @if($tenant->preferred_rent_range)
                                                <div class="flex items-center text-xs mt-1" style="color: var(--text-secondary);">
                                                    <i class="fas fa-money-bill-wave mr-2" style="color: var(--success);"></i>
                                                    Budget: {{ $tenant->preferred_rent_range }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons - Only show for landlords -->
                                        @if($isOnLandlordDashboard)
                                        <div class="flex flex-col sm:flex-row gap-2 md:ml-4">
                                            <a href="{{ route('property-units.create', [
                                                'property_id' => $property->id,
                                                'tenant_id' => $tenant->id,
                                                'tenant_name' => $tenant->name,
                                                'tenant_email' => $tenant->email,
                                                'tenant_phone' => $tenant->phone,
                                                'preferred_move_in' => $tenant->preferred_move_in_date,
                                                'preferred_rent' => $tenant->preferred_rent_range
                                            ]) }}" 
                                               class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium btn-success text-white">
                                                <i class="fas fa-plus-circle mr-2"></i> Create Unit & Assign
                                            </a>
                                        </div>
                                        @elseif($isOnAdminDashboard)
                                        <div class="flex flex-col sm:flex-row gap-2 md:ml-4">
                                            <a href="{{ route('properties.show', $property->id) }}" 
                                               class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium btn-info text-white">
                                                <i class="fas fa-eye mr-2"></i> View Property
                                            </a>
                                            <button type="button" 
                                                    onclick="notifyLandlord({{ $property->landlord_id }}, {{ $tenant->id }})"
                                                    class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                                                <i class="fas fa-bell mr-2"></i> Notify Landlord
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                    
                                    @if($tenant->notes)
                                    <div class="mt-3 pt-3 border-t text-sm" style="border-color: rgba(var(--warning-rgb), 0.2);">
                                        <p class="text-xs italic" style="color: var(--text-secondary);">
                                            <i class="fas fa-quote-left mr-1"></i>
                                            {{ $tenant->notes }}
                                        </p>
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters and Units Table Container -->
    @if(!$isTenant)
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route($routePrefix . '.index') }}" class="space-y-4">
                        <!-- Search -->
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

                        <!-- Property Filter -->
                        @if(isset($properties) && $properties->count() > 0)
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property
                            </label>
                            <select name="property_id" class="index-custom-dropdown w-full">
                                <option value="">All Properties</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }}
                                        @if($isOnAdminDashboard && $property->landlord_id)
                                            ({{ $property->landlord->name ?? 'Unknown' }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Unit Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Status</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Tenant Status Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-user-tag mr-1"></i> Tenant Status
                            </label>
                            <select name="tenant_status" class="index-custom-dropdown w-full">
                                <option value="">All Tenant Status</option>
                                @foreach($tenantStatusOptions as $key => $label)
                                    <option value="{{ $key }}" {{ request('tenant_status') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Unit Type Filter -->
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

                        <!-- Quick Actions -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" 
                                        class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route($routePrefix . '.index') }}" 
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                @if($canViewTrash && $trashedCount > 0)
                                <a href="{{ route('property-units.trash') }}" 
                                   class="block w-full text-center btn-danger px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-trash mr-2"></i> View Trash ({{ $trashedCount }})
                                </a>
                                @endif
                                
                                @if($isOnAdminDashboard)
                                <a href="{{ route($routePrefix . '.export', request()->all()) }}" 
                                   class="block w-full text-center btn-modern bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-file-export mr-2"></i> Export Data
                                </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Units Table -->
        <div class="lg:col-span-3">
    @else
        <!-- For Tenants (full width) -->
        <div class="lg:col-span-4">
    @endif
            <div class="card p-6">
                <!-- Table Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            @if($isTenant)
                                My Unit Details
                            @elseif($isOnLandlordDashboard)
                                My Units ({{ $units->total() }})
                            @elseif($isOnAdminDashboard)
                                All Units ({{ $units->total() }})
                            @else
                                Property Units ({{ $units->total() }})
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if(!$isTenant)
                            Showing {{ $units->firstItem() }} to {{ $units->lastItem() }} of {{ $units->total() }} entries
                            @else
                            Your assigned unit information
                            @endif
                        </p>
                    </div>
                    
                    @if(!$isTenant)
                    <!-- View Toggle and Actions -->
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        <!-- View Toggle -->
                        <div class="flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); border-radius: 0.375rem; padding: 0.25rem;">
                            <button id="tableViewBtn" class="px-3 py-1 rounded-md" style="background-color: var(--card-bg);">
                                <i class="fas fa-table" style="color: var(--primary);"></i>
                            </button>
                            <button id="cardViewBtn" class="px-3 py-1 rounded-md">
                                <i class="fas fa-th-large" style="color: var(--text-secondary);"></i>
                            </button>
                        </div>
                        
                        <!-- Sort Dropdown -->
                        <div class="relative">
                            <select onchange="updateSort(this.value)" 
                                    class="index-custom-dropdown text-sm pl-3 pr-8 py-1 rounded-lg appearance-none">
                                <option value="created_at_desc" {{ request('sort') == 'created_at' && request('direction') == 'desc' ? 'selected' : '' }}>
                                    Sort: Newest First
                                </option>
                                <option value="created_at_asc" {{ request('sort') == 'created_at' && request('direction') == 'asc' ? 'selected' : '' }}>
                                    Sort: Oldest First
                                </option>
                                <option value="unit_number_asc" {{ request('sort') == 'unit_number' && request('direction') == 'asc' ? 'selected' : '' }}>
                                    Sort: Unit Number (A-Z)
                                </option>
                                <option value="monthly_rent_desc" {{ request('sort') == 'monthly_rent' && request('direction') == 'desc' ? 'selected' : '' }}>
                                    Sort: Rent (High to Low)
                                </option>
                            </select>
                        </div>
                    </div>
                    @endif
                </div>

                @if($units->isEmpty() && !$isTenant)
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-home text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            @if($isOnLandlordDashboard)
                                No property units found
                            @elseif($isOnAdminDashboard)
                                No units available in the system
                            @else
                                No units available
                            @endif
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if($isOnLandlordDashboard)
                                Start by creating your first property unit.
                            @elseif($isOnAdminDashboard)
                                No property units match your search criteria.
                            @else
                                No property units match your search criteria.
                            @endif
                        </p>
                        @if($canCreateUnits && !$isOnAdminDashboard)
                        <div class="space-x-3">
                            <a href="{{ route('property-units.create') }}" 
                               class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-plus-circle mr-2"></i> Create First Unit
                            </a>
                        </div>
                        @endif
                    </div>
                @else
                    <!-- Table View -->
                    <div id="tableView" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Unit Details</th>
                                    @if(!$isTenant)
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property</th>
                                    @endif
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                    @if(!$isTenant)
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Tenant</th>
                                    @endif
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Rent</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($isTenant && isset($tenantUnit))
                                    <!-- Single unit for tenant -->
                                    @include('property_units.partials.unit-row', [
                                        'unit' => $tenantUnit, 
                                        'isTenant' => true,
                                        'isAdmin' => $isAdmin,
                                        'isSuperAdmin' => $isSuperAdmin,
                                        'isLandlord' => $isLandlord,
                                        'isOnAdminDashboard' => $isOnAdminDashboard,
                                        'isOnLandlordDashboard' => $isOnLandlordDashboard
                                    ])
                                @else
                                    <!-- Multiple units for non-tenants -->
                                    @foreach($units as $unit)
                                        @include('property_units.partials.unit-row', [
                                            'unit' => $unit, 
                                            'isTenant' => false,
                                            'isAdmin' => $isAdmin,
                                            'isSuperAdmin' => $isSuperAdmin,
                                            'isLandlord' => $isLandlord,
                                            'isOnAdminDashboard' => $isOnAdminDashboard,
                                            'isOnLandlordDashboard' => $isOnLandlordDashboard
                                        ])
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <!-- Card View (Hidden by default) -->
                    <div id="cardView" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @if($isTenant && isset($tenantUnit))
                            <!-- Single card for tenant -->
                            @include('property_units.partials.unit-card', [
                                'unit' => $tenantUnit, 
                                'isTenant' => true,
                                'isAdmin' => $isAdmin,
                                'isSuperAdmin' => $isSuperAdmin,
                                'isLandlord' => $isLandlord,
                                'isOnAdminDashboard' => $isOnAdminDashboard,
                                'isOnLandlordDashboard' => $isOnLandlordDashboard
                            ])
                        @else
                            <!-- Multiple cards for non-tenants -->
                            @foreach($units as $unit)
                                @include('property_units.partials.unit-card', [
                                    'unit' => $unit, 
                                    'isTenant' => false,
                                    'isAdmin' => $isAdmin,
                                    'isSuperAdmin' => $isSuperAdmin,
                                    'isLandlord' => $isLandlord,
                                    'isOnAdminDashboard' => $isOnAdminDashboard,
                                    'isOnLandlordDashboard' => $isOnLandlordDashboard
                                ])
                            @endforeach
                        @endif
                    </div>

                    @if(!$isTenant)
                    <!-- Pagination -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $units->firstItem() }} to {{ $units->lastItem() }} of {{ $units->total() }} entries
                        </div>
                        <div class="pagination">
                            {{ $units->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                    @endif
                @endif
            </div>
        </div>
    @if(!$isTenant)
    </div>
    @endif

    <!-- Pending Approvals Alert -->
    @if(($isOnAdminDashboard || $isOnLandlordDashboard) && isset($stats['pending_approval']) && $stats['pending_approval'] > 0)
        <div class="card border-l-4 mt-6" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold mb-1" style="color: var(--text-primary);">
                            Pending Tenant Approvals
                        </h4>
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                            There are {{ $stats['pending_approval'] }} unit(s) waiting for tenant assignment approval.
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(Admin View)</span>
                            @endif
                        </p>
                        <a href="{{ route($routePrefix . '.pending-approvals') }}" 
                           class="p-2 rounded-lg inline-flex items-center" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-clipboard-check mr-2"></i> Review Pending Approvals
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Confirm Deletion
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Are you sure you want to delete this property unit? This action cannot be undone.
                        </p>
                        <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <p class="font-medium" style="color: var(--danger);">Warning:</p>
                            <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>All unit data will be permanently deleted</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>This action cannot be reversed</span>
                                </li>
                                @if($isOnLandlordDashboard || $isLandlord)
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Occupied units cannot be deleted</span>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="delete_confirmation" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Type "DELETE" to confirm:
                        </label>
                        <input type="text" 
                               id="delete_confirmation" 
                               name="delete_confirmation" 
                               class="index-custom-input w-full"
                               placeholder="Type DELETE here"
                               oninput="checkDeleteConfirmation(this)">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);" id="confirmationMessage">
                            Type exactly "DELETE" (without quotes) to enable the delete button
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideDeleteModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="confirmDeleteBtn"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white opacity-50 cursor-not-allowed"
                            disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Delete Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vacate Modal -->
<div id="vacateModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideVacateModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-sign-out-alt mr-2" style="color: var(--warning);"></i> Mark Tenant as Vacated
                </h3>
                <button type="button" onclick="hideVacateModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            
            <form id="vacateForm" method="POST" action="">
                @csrf
                @method('PUT')
                
                <div class="modal-body space-y-6">
                    <!-- Unit Info Preview -->
                    <div class="p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="font-semibold mb-2 text-sm" style="color: var(--text-primary);">Unit Information</h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Unit:</span>
                                <span class="ml-2 font-medium" style="color: var(--text-primary);" id="modalUnitIdentifier"></span>
                            </div>
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Tenant:</span>
                                <span class="ml-2 font-medium" style="color: var(--text-primary);" id="modalTenantName"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Move-out Date -->
                    <div>
                        <label for="modal_move_out_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Move-out Date <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar" style="color: var(--text-secondary);"></i>
                            </div>
                            <input type="date" 
                                   id="modal_move_out_date" 
                                   name="move_out_date" 
                                   value="{{ date('Y-m-d') }}"
                                   class="index-custom-input pl-10 w-full"
                                   required
                                   max="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <!-- Reason for Vacating -->
                    <div>
                        <label for="modal_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Vacating <span class="text-danger ml-1">*</span>
                        </label>
                        <select id="modal_reason" name="reason" class="index-custom-dropdown w-full" required>
                            <option value="">Select reason...</option>
                            <option value="lease_ended">Lease Ended</option>
                            <option value="tenant_requested">Tenant Requested Early Move-out</option>
                            <option value="mutual_agreement">Mutual Agreement</option>
                            <option value="eviction">Eviction</option>
                            <option value="transfer">Company Transfer</option>
                            <option value="personal_reasons">Personal Reasons</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Additional Notes -->
                    <div>
                        <label for="modal_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Additional Notes
                        </label>
                        <textarea id="modal_notes" name="notes" rows="2" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Any additional information..."></textarea>
                    </div>

                    <!-- Property Condition -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Property Condition <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center p-2 rounded-lg" style="background: rgba(var(--success-rgb), 0.05);">
                                <input type="radio" id="modal_condition_good" name="property_condition" value="good" class="index-custom-radio" checked>
                                <label for="modal_condition_good" class="ml-3 text-sm cursor-pointer flex-1" style="color: var(--text-primary);">
                                    <span class="font-medium">Good</span>
                                    <span class="block text-xs" style="color: var(--text-secondary);">No damage or minor wear and tear</span>
                                </label>
                            </div>
                            <div class="flex items-center p-2 rounded-lg" style="background: rgba(var(--warning-rgb), 0.05);">
                                <input type="radio" id="modal_condition_fair" name="property_condition" value="fair" class="index-custom-radio">
                                <label for="modal_condition_fair" class="ml-3 text-sm cursor-pointer flex-1" style="color: var(--text-primary);">
                                    <span class="font-medium">Fair</span>
                                    <span class="block text-xs" style="color: var(--text-secondary);">Minor damage requiring repair</span>
                                </label>
                            </div>
                            <div class="flex items-center p-2 rounded-lg" style="background: rgba(var(--danger-rgb), 0.05);">
                                <input type="radio" id="modal_condition_poor" name="property_condition" value="poor" class="index-custom-radio">
                                <label for="modal_condition_poor" class="ml-3 text-sm cursor-pointer flex-1" style="color: var(--text-primary);">
                                    <span class="font-medium">Poor</span>
                                    <span class="block text-xs" style="color: var(--text-secondary);">Significant damage requiring major repair</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Unit Status After Vacating -->
                    <div class="p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-flag mr-2 text-primary"></i> Unit Status After Vacating <span class="text-danger ml-1">*</span>
                        </label>
                        
                        <div class="space-y-3">
                            <div class="flex items-center p-3 rounded-lg transition-colors cursor-pointer" 
                                 style="background: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);"
                                 onclick="document.getElementById('modal_status_maintenance').click();">
                                <input type="radio" 
                                       id="modal_status_maintenance" 
                                       name="unit_status_after_vacate" 
                                       value="maintenance"
                                       class="h-4 w-4 focus:ring-2 focus:ring-primary cursor-pointer"
                                       style="color: var(--warning);"
                                       checked>
                                <label for="modal_status_maintenance" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" style="color: var(--warning);">Under Maintenance</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--warning-rgb), 0.2); color: var(--warning);">Recommended</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Unit needs repairs, cleaning, or inspections before being re-listed.
                                    </p>
                                </label>
                            </div>
                            
                            <div class="flex items-center p-3 rounded-lg transition-colors cursor-pointer" 
                                 style="background: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);"
                                 onclick="document.getElementById('modal_status_available').click();">
                                <input type="radio" 
                                       id="modal_status_available" 
                                       name="unit_status_after_vacate" 
                                       value="available"
                                       class="h-4 w-4 focus:ring-2 focus:ring-primary cursor-pointer"
                                       style="color: var(--success);">
                                <label for="modal_status_available" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" style="color: var(--success);">Available Immediately</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--success-rgb), 0.2); color: var(--success);">Ready for Rent</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Unit is in good condition and ready for new tenants immediately.
                                    </p>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Cleaning Required Checkbox -->
                    <div class="flex items-center p-3 rounded-lg border" 
                         style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <input type="checkbox" 
                               id="modal_cleaning_required" 
                               name="cleaning_required" 
                               value="1"
                               class="h-4 w-4 rounded focus:ring-2 focus:ring-primary"
                               style="color: var(--primary);">
                        <label for="modal_cleaning_required" class="ml-2 text-sm flex items-center cursor-pointer" style="color: var(--text-primary);">
                            <i class="fas fa-broom mr-2 text-primary"></i> 
                            <span>Cleaning Required</span>
                        </label>
                    </div>

                    <!-- Damages Noted -->
                    <div>
                        <label for="modal_damages_noted" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Damages Noted
                        </label>
                        <textarea id="modal_damages_noted" name="damages_noted" rows="2" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Describe any damages to the property..."></textarea>
                    </div>

                    <!-- Security Deposit Refund -->
                    <div class="p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="flex items-center mb-3">
                            <input type="checkbox" 
                                   id="modal_refund_deposit" 
                                   name="refund_deposit" 
                                   value="1"
                                   class="h-4 w-4 rounded focus:ring-2 focus:ring-primary"
                                   style="color: var(--primary);">
                            <label for="modal_refund_deposit" class="ml-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Process Security Deposit Refund
                            </label>
                        </div>
                        
                        <div id="modalRefundContainer" class="space-y-3 hidden">
                            <div>
                                <label for="modal_deposit_refund_amount" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Refund Amount <span class="text-danger ml-1">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span style="color: var(--text-secondary);">₵</span>
                                    </div>
                                    <input type="number" 
                                           id="modal_deposit_refund_amount" 
                                           name="deposit_refund_amount" 
                                           min="0" 
                                           step="0.01"
                                           class="index-custom-input pl-10 w-full"
                                           placeholder="0.00">
                                </div>
                            </div>
                            
                            <div>
                                <label for="modal_refund_notes" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Refund Notes
                                </label>
                                <textarea id="modal_refund_notes" name="refund_notes" rows="2"
                                          class="index-custom-textarea w-full"
                                          placeholder="Notes about deductions or refund details..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Final Keys Returned -->
                    <div class="flex items-center p-3 rounded-lg border" 
                         style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <input type="checkbox" 
                               id="modal_keys_returned" 
                               name="keys_returned" 
                               value="1"
                               class="h-4 w-4 rounded focus:ring-2 focus:ring-primary"
                               style="color: var(--primary);">
                        <label for="modal_keys_returned" class="ml-2 text-sm flex items-center cursor-pointer" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-2 text-primary"></i> All keys have been returned by tenant
                        </label>
                    </div>

                    <!-- Final Inspection Completed -->
                    <div class="flex items-center p-3 rounded-lg border" 
                         style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <input type="checkbox" 
                               id="modal_inspection_completed" 
                               name="inspection_completed" 
                               value="1"
                               class="h-4 w-4 rounded focus:ring-2 focus:ring-primary"
                               style="color: var(--primary);">
                        <label for="modal_inspection_completed" class="ml-2 text-sm flex items-center cursor-pointer" style="color: var(--text-primary);">
                            <i class="fas fa-clipboard-check mr-2 text-primary"></i> Final inspection has been completed
                        </label>
                    </div>

                    <!-- Confirmation Checkbox -->
                    <div class="p-4 rounded-lg border" 
                         style="background: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <input type="checkbox" 
                                   id="modal_confirm_action" 
                                   name="confirm_action" 
                                   value="1"
                                   class="h-4 w-4 mt-1 rounded focus:ring-2 focus:ring-primary"
                                   style="color: var(--danger);"
                                   required>
                            <label for="modal_confirm_action" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-exclamation-triangle mr-1 text-danger"></i>
                                <span class="font-medium">I confirm that the tenant has vacated the unit and all information provided is accurate.</span>
                                <span class="text-danger ml-1 text-xs block mt-1">* Required</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="hideVacateModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white"
                            style="background: linear-gradient(to right, var(--warning), #ff9f43);">
                        <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize table/card view toggle
    initViewToggle();
    
    // Initialize modals
    initModals();
    
    // Initialize tooltips
    initTooltips();
    
    // Auto-hide success and error messages
    autoHideMessages();
    
    // Check if tenant data was passed and show notification
    checkForTenantData();
    
    // Initialize vacate modal dynamic behavior
    initVacateModalBehavior();
    
    // Check if we need to refresh after vacate operation
    checkForVacateRefresh();
});

function initViewToggle() {
    const tableViewBtn = document.getElementById('tableViewBtn');
    const cardViewBtn = document.getElementById('cardViewBtn');
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    
    if (tableViewBtn && cardViewBtn) {
        // Check localStorage for saved view preference
        const savedView = localStorage.getItem('propertyUnitsView') || 'table';
        
        if (savedView === 'card') {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
            tableViewBtn.style.backgroundColor = 'transparent';
            tableViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            cardViewBtn.style.backgroundColor = 'var(--card-bg)';
            cardViewBtn.querySelector('i').style.color = 'var(--primary)';
        } else {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
            tableViewBtn.style.backgroundColor = 'var(--card-bg)';
            tableViewBtn.querySelector('i').style.color = 'var(--primary)';
            cardViewBtn.style.backgroundColor = 'transparent';
            cardViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
        }
        
        tableViewBtn.addEventListener('click', function() {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
            tableViewBtn.style.backgroundColor = 'var(--card-bg)';
            tableViewBtn.querySelector('i').style.color = 'var(--primary)';
            cardViewBtn.style.backgroundColor = 'transparent';
            cardViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            localStorage.setItem('propertyUnitsView', 'table');
        });
        
        cardViewBtn.addEventListener('click', function() {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
            tableViewBtn.style.backgroundColor = 'transparent';
            tableViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            cardViewBtn.style.backgroundColor = 'var(--card-bg)';
            cardViewBtn.querySelector('i').style.color = 'var(--primary)';
            localStorage.setItem('propertyUnitsView', 'card');
        });
    }
}

function initModals() {
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideDeleteModal();
            hideVacateModal();
        }
    });
}

function initVacateModalBehavior() {
    // Toggle refund amount field
    const refundCheckbox = document.getElementById('modal_refund_deposit');
    const refundContainer = document.getElementById('modalRefundContainer');
    const refundAmountInput = document.getElementById('modal_deposit_refund_amount');
    
    if (refundCheckbox && refundContainer) {
        refundCheckbox.addEventListener('change', function() {
            if (this.checked) {
                refundContainer.classList.remove('hidden');
                if (refundAmountInput) {
                    refundAmountInput.required = true;
                    refundAmountInput.focus();
                }
            } else {
                refundContainer.classList.add('hidden');
                if (refundAmountInput) {
                    refundAmountInput.required = false;
                    refundAmountInput.value = '';
                }
            }
        });
    }
    
    // Auto-populate damages based on property condition
    const conditionRadios = document.querySelectorAll('input[name="property_condition"]');
    const damagesField = document.getElementById('modal_damages_noted');
    const cleaningCheckbox = document.getElementById('modal_cleaning_required');
    
    conditionRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'poor') {
                if (damagesField && !damagesField.value) {
                    damagesField.value = 'Significant damage requiring major repair.';
                }
                if (cleaningCheckbox) {
                    cleaningCheckbox.checked = true;
                }
            } else if (this.value === 'fair') {
                if (damagesField && !damagesField.value) {
                    damagesField.value = 'Minor damage requiring some repair.';
                }
                if (cleaningCheckbox) {
                    cleaningCheckbox.checked = true;
                }
            } else if (this.value === 'good') {
                if (cleaningCheckbox) {
                    cleaningCheckbox.checked = false;
                }
            }
        });
    });
}

function initTooltips() {
    // Simple tooltip implementation
    const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
    elementsWithTooltip.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'fixed z-50 px-2 py-1 text-xs rounded-lg';
            tooltip.style.backgroundColor = 'var(--card-bg)';
            tooltip.style.color = 'var(--text-primary)';
            tooltip.style.border = '1px solid var(--border-color)';
            tooltip.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.id = 'tooltip-' + Date.now();
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
            tooltip.style.top = (rect.bottom + 5) + 'px';
            
            this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
}

function autoHideMessages() {
    // Auto-hide success and error messages after 5 seconds
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.bg-green-100');
        successMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
        
        const errorMessages = document.querySelectorAll('.bg-red-100');
        errorMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
    }, 5000);
}

function checkForTenantData() {
    // Check if tenant data was passed and show notification
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tenant_id')) {
        // Show a notification that tenant data was received
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="font-bold">Tenant data loaded!</span>
                <span class="ml-2">The tenant information has been pre-filled in the form.</span>
            </div>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

function checkForVacateRefresh() {
    // Check if we just completed a vacate operation
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('vacated') && urlParams.get('vacated') === '1') {
        // Remove the parameter from URL without refreshing
        urlParams.delete('vacated');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', newUrl);
        
        // Show success message specifically for the alert disappearing
        const pendingAlert = document.querySelector('.border-l-4.mb-6[style*="border-left-color: var(--warning)"]');
        if (pendingAlert) {
            // The alert will be gone on next page load anyway
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg';
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span class="font-bold">Tenant vacated successfully!</span>
                    <span class="ml-2">The tenant has been removed from pending assignments.</span>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    }
}

function updateSort(value) {
    const [sort, direction] = value.split('_');
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    url.searchParams.set('direction', direction);
    window.location.href = url.toString();
}

// Delete Modal Functions
function showDeleteModal(unitId, unitNumber) {
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const confirmationInput = document.getElementById('delete_confirmation');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    // Set the correct route based on user role and dashboard context
    @if($isOnAdminDashboard)
        form.action = '/admin/property-units/' + unitId;
    @elseif($isOnLandlordDashboard)
        form.action = '/landlord/property-units/' + unitId;
    @else
        form.action = '/property-units/' + unitId;
    @endif
    
    // Reset form
    if (form) form.reset();
    if (confirmationInput) confirmationInput.value = '';
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    
    // Update confirmation message
    const messageElement = document.getElementById('confirmationMessage');
    if (messageElement) {
        messageElement.innerHTML = `Type exactly "DELETE" to delete unit ${unitNumber}`;
    }
    
    // Show modal
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    // Focus on confirmation input
    setTimeout(() => {
        if (confirmationInput) confirmationInput.focus();
    }, 100);
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function checkDeleteConfirmation(input) {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const messageElement = document.getElementById('confirmationMessage');
    
    if (confirmBtn && messageElement) {
        if (input.value === 'DELETE') {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--success)';
            messageElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmation matches. You can now delete.';
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--danger)';
            messageElement.innerHTML = `Type exactly "DELETE" (without quotes) to enable the delete button. You typed: "${input.value}"`;
        }
    }
}

// Vacate Modal Functions
function showVacateModal(unitId, unitNumber, tenantName, securityDeposit) {
    const modal = document.getElementById('vacateModal');
    const form = document.getElementById('vacateForm');
    
    // Set the correct route based on user role and dashboard context
    @if($isOnAdminDashboard)
        form.action = '/admin/property-units/' + unitId + '/mark-vacated';
    @elseif($isOnLandlordDashboard)
        form.action = '/landlord/property-units/' + unitId + '/mark-vacated';
    @else
        form.action = '/property-units/' + unitId + '/mark-vacated';
    @endif
    
    // Set unit info
    document.getElementById('modalUnitIdentifier').textContent = unitNumber;
    document.getElementById('modalTenantName').textContent = tenantName;
    
    // Set max deposit amount
    const refundAmountInput = document.getElementById('modal_deposit_refund_amount');
    if (refundAmountInput) {
        refundAmountInput.max = securityDeposit;
    }
    
    // Reset form
    if (form) form.reset();
    
    // Reset conditional fields
    const refundContainer = document.getElementById('modalRefundContainer');
    if (refundContainer) {
        refundContainer.classList.add('hidden');
    }
    
    // Set today's date as default for move-out
    const moveOutInput = document.getElementById('modal_move_out_date');
    if (moveOutInput) {
        const today = new Date().toISOString().split('T')[0];
        moveOutInput.value = today;
    }
    
    // Set default property condition to good
    const goodCondition = document.getElementById('modal_condition_good');
    if (goodCondition) goodCondition.checked = true;
    
    // Set default unit status to maintenance
    const maintenanceStatus = document.getElementById('modal_status_maintenance');
    if (maintenanceStatus) maintenanceStatus.checked = true;
    
    // Uncheck confirmation checkbox
    const confirmCheckbox = document.getElementById('modal_confirm_action');
    if (confirmCheckbox) confirmCheckbox.checked = false;
    
    // Show modal
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideVacateModal() {
    const modal = document.getElementById('vacateModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function confirmDelete(unitId, unitNumber, isOccupied) {
    if (isOccupied) {
        alert('Cannot delete an occupied unit. Please vacate the tenant first.');
        return false;
    }
    
    showDeleteModal(unitId, unitNumber);
    return false;
}

function notifyLandlord(landlordId, tenantId) {
    fetch('/api/notify-landlord', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            landlord_id: landlordId,
            tenant_id: tenantId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Landlord notified successfully!');
        } else {
            alert('Failed to notify landlord. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function archiveUnit(unitId, unitNumber) {
    if (confirm(`Are you sure you want to archive unit ${unitNumber}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/property-units/${unitId}/archive`;
        form.style.display = 'none';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function restoreUnit(unitId, unitNumber) {
    if (confirm(`Are you sure you want to restore unit ${unitNumber}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/property-units/${unitId}/restore`;
        form.style.display = 'none';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function exportUnits(format = 'csv') {
    const url = new URL(window.location.href);
    url.searchParams.set('export', format);
    window.location.href = url.toString();
}
</script>
@endsection

{{-- ============================================================ --}}
{{-- STYLES — moved OUT of @section('scripts') into @push('styles') --}}
{{-- so they render in <head> via the layout's @stack('styles'),    --}}
{{-- and don't leak into the header/sidebar.                        --}}
{{-- The global `.hidden` rule has been scoped to only the elements --}}
{{-- this page controls, and focus/transition rules are scoped to   --}}
{{-- #property-units-page so they don't affect the header's search. --}}
{{-- ============================================================ --}}
@push('styles')
<style>
/* Index-specific form control styles with dark mode support */
.index-custom-dropdown {
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

.index-custom-radio {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    accent-color: var(--primary);
}

[data-theme="dark"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

[data-theme="light"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.index-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Table styles scoped to this page to avoid affecting header tables (if any) */
#property-units-page table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

#property-units-page table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}

#property-units-page table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}

#property-units-page table tr:last-child td {
    border-bottom: none;
}

#property-units-page table tr {
    background-color: var(--card-bg) !important;
}

#property-units-page table tr:hover td,
#property-units-page table tr:hover {
    background-color: var(--card-bg) !important;
}

#cardView .card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    background-color: var(--card-bg);
}

#cardView .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

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

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

/* Scoped to this page so we don't override the header's buttons */
#property-units-page .btn-primary,
#vacateModal .btn-primary,
#deleteModal .btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

#property-units-page .btn-primary:hover,
#vacateModal .btn-primary:hover,
#deleteModal .btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

#property-units-page .btn-secondary,
#vacateModal .btn-secondary,
#deleteModal .btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

#property-units-page .btn-secondary:hover,
#vacateModal .btn-secondary:hover,
#deleteModal .btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

#property-units-page .btn-danger,
#vacateModal .btn-danger,
#deleteModal .btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

#property-units-page .btn-danger:hover,
#vacateModal .btn-danger:hover,
#deleteModal .btn-danger:hover {
    background-color: #dc3545 !important;
    transform: translateY(-1px);
}

#property-units-page .btn-success,
#vacateModal .btn-success {
    background: linear-gradient(to right, #10b981, #059669) !important;
    color: white !important;
    border: none !important;
    transition: all 0.2s ease;
}

#property-units-page .btn-success:hover,
#vacateModal .btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

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

.modal-body {
    padding: 1.5rem;
}

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

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.action-btn {
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

.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}

.action-btn.view:hover {
    background-color: rgba(var(--info-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.edit {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.3);
}

.action-btn.edit:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.assign {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.assign:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.delete:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.vacate {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.3);
}

.action-btn.vacate:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

.pagination .pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 0.25rem;
}

.pagination .pagination li a,
.pagination .pagination li span {
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

.pagination .pagination li a:hover:not(.disabled) {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

.pagination .pagination li.active span {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

@media (max-width: 768px) {
    #property-units-page .grid.grid-cols-1.lg\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    #property-units-page .lg\:col-span-1 {
        margin-bottom: 1.5rem;
    }
    
    #property-units-page table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    #cardView {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: flex-start;
    }
    
    .modal-container {
        width: 95%;
        margin: 0.5rem;
    }
    
    .fixed.bottom-4.right-4.z-40 {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        max-width: none !important;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#cardView .card {
    animation: fadeIn 0.3s ease-out;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}

/* ============================================================ */
/* SCOPED .hidden OVERRIDE                                      */
/* The previous GLOBAL `.hidden { display: none !important; }`  */
/* was leaking into the header (and Alpine's x-show, etc.).     */
/* Now scoped to the specific IDs this page controls.           */
/* ============================================================ */

#property-units-page #deleteModal.hidden,
#property-units-page #vacateModal.hidden,
#property-units-page #tableView.hidden,
#property-units-page #cardView.hidden,
#property-units-page #modalRefundContainer.hidden,
#deleteModal.hidden,
#vacateModal.hidden,
#tableView.hidden,
#cardView.hidden,
#modalRefundContainer.hidden {
    display: none !important;
}
</style>
@endpush