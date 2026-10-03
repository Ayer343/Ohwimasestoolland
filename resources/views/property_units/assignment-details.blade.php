{{-- resources/views/property_units/assignment-details.blade.php --}}
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
        $pageTitle = 'Review Tenant Assignment - Admin Portal';
    } elseif ($isOnLandlordDashboard) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
        $isAdminView = false;
        $pageTitle = 'Review Tenant Assignment - Landlord Portal';
    } elseif ($isOnDeveloperDashboard) {
        $layout = 'layouts.app';
        $routePrefix = 'developer.property-units';
        $isAdminView = false;
        $pageTitle = 'Review Tenant Assignment - Developer';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
        $isAdminView = false;
        $pageTitle = 'Review Tenant Assignment';
    }
    
    // ============================================================
    // Determine permissions based on ACTIVE DASHBOARD
    // ============================================================
    // Check if user can approve/reject (only admins in admin context)
    $canApproveReject = $isOnAdminDashboard && ($isAdmin || $isSuperAdmin);
    
    // Check if user can view financial information - ONLY landlords (NOT admins)
    $canViewFinancialInfo = $isOnLandlordDashboard;
    
    // Check if user is the owner/landlord of the property
    $isOwner = $isLandlord && isset($unit) && $unit->property->landlord_id === auth()->id();
    
    // ============================================================
    // Calculate internal metrics (hidden from view if not authorized)
    // ============================================================
    $monthlyIncome = 0;
    if ($unit->tenant_type === 'existing' && $unit->tenant) {
        $monthlyIncome = $unit->tenant->monthly_income ?? 0;
    } elseif ($unit->tenant_type === 'new' && $unit->tenant_details) {
        $monthlyIncome = $unit->tenant_details['monthly_income'] ?? 0;
    }
    $proposedRent = $unit->proposed_rent ?? $unit->monthly_rent ?? 1;
    $incomeRentRatio = $monthlyIncome > 0 ? round($monthlyIncome / $proposedRent, 2) : 0;
    
    $rentDifference = 0;
    $rentPercentage = 0;
    if ($unit->proposed_rent && $unit->monthly_rent) {
        $rentDifference = $unit->proposed_rent - $unit->monthly_rent;
        $rentPercentage = $unit->monthly_rent > 0 ? ($rentDifference / $unit->monthly_rent) * 100 : 0;
    }
    
    // Days pending
    $daysPending = null;
    $daysPendingDisplay = 'N/A';
    $daysPendingColor = 'var(--warning)';
    
    if ($unit->tenant_requested_at) {
        $daysPending = now()->diffInDays($unit->tenant_requested_at);
        $daysPendingDisplay = $daysPending . ' days';
        
        if ($daysPending > 30) {
            $daysPendingColor = 'var(--danger)';
        } elseif ($daysPending > 14) {
            $daysPendingColor = 'var(--warning)';
        } else {
            $daysPendingColor = 'var(--success)';
        }
    }
    
    // Get the correct routes based on dashboard context
    if ($isOnAdminDashboard) {
        $approveRoute = route('admin.property-units.approve-tenant', $unit->id);
        $rejectRoute = route('admin.property-units.reject-tenant', $unit->id);
        $bulkApprovalRoute = route('admin.property-units.bulk-approval');
    } elseif ($isOnLandlordDashboard) {
        $approveRoute = route('landlord.property-units.approve-tenant', $unit->id);
        $rejectRoute = route('landlord.property-units.reject-tenant', $unit->id);
        $bulkApprovalRoute = route('landlord.property-units.bulk-approval');
    } else {
        $approveRoute = route('property-units.approve-tenant', $unit->id);
        $rejectRoute = route('property-units.reject-tenant', $unit->id);
        $bulkApprovalRoute = route('property-units.bulk-approval');
    }
    
    // Old form data for error recovery
    $oldData = session()->getOldInput();
    
    // Document preview setup
    $hasDocuments = !empty($unit->tenant_documents) && is_array($unit->tenant_documents);
    $documentTypes = [
        'pdf' => ['application/pdf'],
        'image' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
        'word' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'excel' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'text' => ['text/plain']
    ];
    
    // Determine route for dashboard link
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
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--info) 0%, var(--primary) 100%); color: white; font-weight: 600; border-color: var(--info);">
                        <i class="fas fa-file-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-2" style="color: var(--info);"></i> 
                        Review Tenant Assignment
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>{{ $unit->property->property_name ?? 'N/A' }} - Unit {{ $unit->unit_number ?? 'N/A' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-tie mr-1"></i>
                        <span>{{ $unit->requestedBy->name ?? 'Unknown' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-1"></i>
                        <span>{{ $daysPendingDisplay }}</span>
                        @if($isOnAdminDashboard)
                            <span class="mx-2">•</span>
                            <i class="fas fa-user-shield mr-1"></i>
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                Admin View
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if($canApproveReject)
                <a href="{{ $bulkApprovalRoute }}?property_id={{ $unit->property_id }}&tenant_type={{ $unit->tenant_type }}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-layer-group mr-2"></i> Bulk Approval
                </a>
                @endif
                <a href="{{ route($routePrefix . '.pending-approvals') }}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Pending
                </a>
                @if($dashboardRoute && $dashboardRoute != '#')
                <a href="{{ $dashboardRoute }}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-2"></i> Dashboard
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Display Validation Errors -->
    @if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Validation Errors!</span>
        </div>
        <ul class="mt-2 ml-6 list-disc text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

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

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Application Header -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                {{ $unit->property->property_name ?? 'N/A' }} - Unit {{ $unit->unit_number ?? 'N/A' }}
                                @if($unit->unit_name)
                                    <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                                        ({{ $unit->unit_name }})
                                    </span>
                                @endif
                                @if($isOnAdminDashboard)
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-building mr-1"></i> {{ $unit->property->property_name ?? 'N/A' }}
                                    </span>
                                @endif
                            </h3>
                            <div class="flex flex-wrap items-center gap-3 mt-2">
                                <!-- Tenant Type Badge -->
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-info">
                                    <i class="fas fa-user-tag mr-1"></i>
                                    {{ ucfirst($unit->tenant_type) }} Tenant
                                </span>
                                
                                <!-- Requested By -->
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-user-tie mr-1"></i>
                                    Requested by: {{ $unit->requestedBy->name ?? 'Unknown' }}
                                    @if($isOnAdminDashboard && $unit->requestedBy)
                                        <span class="text-xs" style="color: var(--text-secondary);">(Landlord)</span>
                                    @endif
                                </span>
                                
                                <!-- Date -->
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ $unit->tenant_requested_at ? $unit->tenant_requested_at->format('F j, Y H:i') : 'Date not set' }}
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium mb-2 badge-warning">
                                <i class="fas fa-clock mr-1"></i>
                                Pending Review
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-hashtag mr-1"></i> Application ID: {{ $unit->id }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Bulk Approval Quick Link -->
                    @if($canApproveReject)
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Multiple Applications?</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Approve similar applications in bulk
                                    <a href="{{ $bulkApprovalRoute }}?property_id={{ $unit->property_id }}&tenant_type={{ $unit->tenant_type }}" 
                                       class="ml-1 font-medium" style="color: var(--info);">
                                        Go to Bulk Approval →
                                    </a>
                                </p>
                            </div>
                            @if($isOnAdminDashboard)
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                Admin
                            </span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Tenant Information Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-user-circle mr-2" style="color: var(--info);"></i> 
                            Tenant Information
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-eye mr-1"></i> Admin View
                                </span>
                            @endif
                        </h3>
                        <span class="text-sm px-3 py-1 rounded-full badge-info">
                            <i class="fas fa-user-tag mr-1"></i> {{ ucfirst($unit->tenant_type) }} Tenant
                        </span>
                    </div>
                    
                    @if($unit->tenant_type === 'existing' && $unit->tenant)
                        <!-- Existing Tenant -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div class="space-y-4">
                                <div class="p-4 rounded-lg" 
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                    <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                        Basic Information
                                    </label>
                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Full Name</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant->name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Email</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant->email }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Phone</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant->phone }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">National ID</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant->national_id ?? 'Not provided' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="space-y-4">
                                <div class="p-4 rounded-lg" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                        Employment Information
                                    </label>
                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Employment Status</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">
                                                {{ ucfirst(str_replace('_', ' ', $unit->tenant->employment_status ?? 'Not provided')) }}
                                            </p>
                                        </div>
                                        @if($canViewFinancialInfo && $unit->tenant->monthly_income)
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Monthly Income</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">
                                                GHS {{ number_format($unit->tenant->monthly_income ?? 0, 2) }}
                                            </p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Emergency Contact -->
                        @if($unit->tenant->emergency_contact)
                        <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                            <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                                <i class="fas fa-phone-alt mr-2" style="color: var(--danger);"></i> Emergency Contact
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-3 rounded-lg" 
                                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                    <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Name</p>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->emergency_contact['name'] ?? 'N/A' }}</p>
                                </div>
                                <div class="p-3 rounded-lg" 
                                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                    <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Phone</p>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->emergency_contact['phone'] ?? 'N/A' }}</p>
                                </div>
                                <div class="p-3 rounded-lg" 
                                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                    <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Relationship</p>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->emergency_contact['relationship'] ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        @endif

                    @elseif($unit->tenant_type === 'new' && $unit->tenant_details)
                        <!-- New Tenant -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div class="space-y-4">
                                <div class="p-4 rounded-lg" 
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                    <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                        Basic Information
                                    </label>
                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Full Name</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant_details['name'] ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Email</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant_details['email'] ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Phone</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->tenant_details['phone'] ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Gender</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">{{ ucfirst($unit->tenant_details['gender'] ?? 'N/A') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="space-y-4">
                                <div class="p-4 rounded-lg" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                        Employment Information
                                    </label>
                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Employment Status</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">
                                                {{ ucfirst(str_replace('_', ' ', $unit->tenant_details['employment_status'] ?? 'N/A')) }}
                                            </p>
                                        </div>
                                        @if($canViewFinancialInfo && !empty($unit->tenant_details['monthly_income']))
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">Monthly Income</p>
                                            <p class="font-medium text-base" style="color: var(--text-primary);">
                                                GHS {{ number_format($unit->tenant_details['monthly_income'] ?? 0, 2) }}
                                            </p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="mt-6 pt-6 border-t space-y-6" style="border-color: var(--border-color);">
                            <!-- Emergency Contact -->
                            @if(!empty($unit->tenant_details['emergency_contact']))
                            <div>
                                <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                                    <i class="fas fa-phone-alt mr-2" style="color: var(--danger);"></i> Emergency Contact
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="p-3 rounded-lg" 
                                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Name</p>
                                        <p class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant_details['emergency_contact']['name'] ?? 'N/A' }}</p>
                                    </div>
                                    <div class="p-3 rounded-lg" 
                                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Phone</p>
                                        <p class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant_details['emergency_contact']['phone'] ?? 'N/A' }}</p>
                                    </div>
                                    <div class="p-3 rounded-lg" 
                                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Relationship</p>
                                        <p class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant_details['emergency_contact']['relationship'] ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Background -->
                            @if(!empty($unit->tenant_details['background']))
                            <div>
                                <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> Background Information
                                </h4>
                                <div class="p-4 rounded-lg" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <p class="text-sm" style="color: var(--text-secondary); line-height: 1.6;">{{ $unit->tenant_details['background'] }}</p>
                                </div>
                            </div>
                            @endif

                            <!-- Invitation Channels -->
                            @if(!empty($unit->tenant_details['invitation_channels']))
                            <div>
                                <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                                    <i class="fas fa-bell mr-2" style="color: var(--primary);"></i> Invitation Preferences
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($unit->tenant_details['invitation_channels'] as $channel)
                                        @if($channel === 'email')
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-info">
                                                <i class="fas fa-envelope mr-1"></i> Email
                                            </span>
                                        @elseif($channel === 'sms')
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-success">
                                                <i class="fas fa-sms mr-1"></i> SMS
                                            </span>
                                        @elseif($channel === 'whatsapp')
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" 
                                                  style="background-color: rgba(37, 211, 102, 0.1); color: #25D366; border: 1px solid rgba(37, 211, 102, 0.3);">
                                                <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    @else
                        <!-- No Tenant Info -->
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--warning-rgb), 0.1);">
                                <i class="fas fa-user-slash text-xl" style="color: var(--warning);"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> Tenant information not available
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Unit Details Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-home mr-2" style="color: var(--success);"></i> 
                            Unit Details
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-eye mr-1"></i> Admin View
                                </span>
                            @endif
                        </h3>
                        @if($isOnAdminDashboard && $unit->property->landlord)
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user mr-1"></i> {{ $unit->property->landlord->name }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Unit Information -->
                        <div class="space-y-4">
                            <div class="p-4 rounded-lg" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    <i class="fas fa-building mr-1"></i> Property Details
                                </label>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Property</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->property->property_name ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Landlord</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->property->landlord->name ?? 'N/A' }}</p>
                                        @if($isOnAdminDashboard)
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-1"></i> {{ $unit->property->landlord->email ?? 'N/A' }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 rounded-lg" 
                                 style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    <i class="fas fa-door-closed mr-1"></i> Unit Details
                                </label>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Unit Number</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">{{ $unit->unit_number ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Unit Type</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">{{ ucfirst($unit->unit_type ?? 'N/A') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Size</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">
                                            <i class="fas fa-bed mr-1"></i>{{ $unit->bedrooms ?? 0 }} bed • 
                                            <i class="fas fa-bath mr-1"></i>{{ $unit->bathrooms ?? 0 }} bath
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Furnished</p>
                                        <p class="font-medium text-base" style="color: {{ $unit->is_furnished ? 'var(--success)' : 'var(--text-primary)' }};">
                                            {{ $unit->is_furnished ? 'Yes' : 'No' }}
                                            @if($unit->is_furnished)
                                                <i class="fas fa-check-circle ml-1"></i>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rental Information - Visible ONLY to landlords (NOT admins) -->
                        @if($canViewFinancialInfo)
                        <div class="space-y-4">
                            <div class="p-4 rounded-lg" 
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Rental Information
                                </label>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Base Monthly Rent</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">
                                            GHS {{ number_format($unit->monthly_rent ?? 0, 2) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Proposed Monthly Rent</p>
                                        <p class="font-medium text-base" style="color: var(--primary);">
                                            GHS {{ number_format($unit->proposed_rent ?? $unit->monthly_rent ?? 0, 2) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Security Deposit</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">
                                            GHS {{ number_format($unit->security_deposit ?? 0, 2) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs" style="color: var(--text-secondary);">Proposed Move-in Date</p>
                                        <p class="font-medium text-base" style="color: var(--text-primary);">
                                            {{ $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('F j, Y') : 'Not set' }}
                                            @if($unit->tenant_move_in_date && $unit->tenant_move_in_date->isFuture())
                                                <span class="text-xs ml-2 badge-info">Upcoming</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @elseif($isOnAdminDashboard)
                        <!-- Admin view - show placeholder instead of Rental Information -->
                        <div class="space-y-4">
                            <div class="p-4 rounded-lg" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Rental Information
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-eye mr-1"></i> Admin View
                                    </span>
                                </label>
                                <div class="text-center py-4">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" 
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <i class="fas fa-lock text-xl" style="color: var(--info);"></i>
                                    </div>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        Rental information is only visible to the property owner
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        This is an admin view - financial details are hidden
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Enhanced Documents Section with Preview -->
            @if($hasDocuments)
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-file-alt mr-2" style="color: #9333ea;"></i> 
                            Tenant Documents & Preview
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-eye mr-1"></i> Admin View
                                </span>
                            @endif
                        </h3>
                        <span class="text-sm px-3 py-1 rounded-full" 
                              style="background-color: rgba(147, 51, 234, 0.1); color: #9333ea; border: 1px solid rgba(147, 51, 234, 0.3);">
                            <i class="fas fa-folder-open mr-1"></i> {{ count($unit->tenant_documents) }} document(s)
                        </span>
                    </div>
                    
                    <!-- Document Preview Modal -->
                    <div id="documentPreviewModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
                        <div class="fixed inset-0 bg-black bg-opacity-75" onclick="closeDocumentPreview()"></div>
                        <div class="fixed inset-0 flex items-center justify-center p-4">
                            <div class="theme-modal-compact" style="max-width: 90vw; max-height: 90vh; width: 1200px;">
                                <!-- Modal Header -->
                                <div class="theme-modal-header-compact">
                                    <h3 id="previewDocumentTitle" class="flex items-center truncate" style="color: var(--text-primary);">
                                        <i class="fas fa-file-alt mr-2"></i> 
                                        Document Preview
                                    </h3>
                                    <div class="flex items-center space-x-2">
                                        <button type="button" 
                                                onclick="downloadCurrentDocument()"
                                                class="px-3 py-1 rounded-lg text-sm font-medium" 
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </button>
                                        <button type="button" 
                                                onclick="closeDocumentPreview()" 
                                                style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Modal Body -->
                                <div class="theme-modal-body-compact">
                                    <div id="previewContent" class="flex items-center justify-center min-h-[400px]">
                                        <!-- Content will be loaded here -->
                                        <div class="text-center">
                                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                                 style="background-color: rgba(var(--info-rgb), 0.1);">
                                                <i class="fas fa-spinner fa-spin text-xl" style="color: var(--info);"></i>
                                            </div>
                                            <p class="text-sm" style="color: var(--text-secondary);">
                                                Loading document preview...
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modal Footer -->
                                <div class="theme-modal-footer-compact">
                                    <div class="flex items-center justify-between w-full">
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <span id="previewDocumentType"></span>
                                            • 
                                            <span id="previewDocumentSize"></span>
                                        </div>
                                        <div>
                                            <button type="button" 
                                                    onclick="closeDocumentPreview()" 
                                                    class="px-4 py-2 rounded-lg text-sm font-medium" 
                                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                                Close Preview
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document List -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        @foreach($unit->tenant_documents as $index => $document)
                            @php
                                $fileType = $document['type'] ?? 'application/octet-stream';
                                $fileName = $document['name'] ?? 'Document ' . ($index + 1);
                                $filePath = $document['path'] ?? '';
                                $uploadedAt = isset($document['uploaded_at']) ? \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') : 'Unknown date';
                                
                                // Determine file icon and color
                                $fileIcon = 'fa-file';
                                $fileColor = 'var(--info)';
                                $bgColor = 'rgba(var(--info-rgb), 0.1)';
                                
                                if (str_contains($fileType, 'pdf')) {
                                    $fileIcon = 'fa-file-pdf';
                                    $fileColor = '#ef4444';
                                    $bgColor = 'rgba(239, 68, 68, 0.1)';
                                } elseif (str_contains($fileType, 'image')) {
                                    $fileIcon = 'fa-file-image';
                                    $fileColor = '#10b981';
                                    $bgColor = 'rgba(16, 185, 129, 0.1)';
                                } elseif (str_contains($fileType, 'word') || str_contains($fileType, 'document')) {
                                    $fileIcon = 'fa-file-word';
                                    $fileColor = '#2563eb';
                                    $bgColor = 'rgba(37, 99, 235, 0.1)';
                                } elseif (str_contains($fileType, 'excel') || str_contains($fileType, 'sheet')) {
                                    $fileIcon = 'fa-file-excel';
                                    $fileColor = '#16a34a';
                                    $bgColor = 'rgba(22, 163, 74, 0.1)';
                                } elseif (str_contains($fileType, 'text')) {
                                    $fileIcon = 'fa-file-alt';
                                    $fileColor = '#9333ea';
                                    $bgColor = 'rgba(147, 51, 234, 0.1)';
                                }
                                
                                // Create preview URL
                                $previewUrl = $filePath ? route('admin.preview-document', ['path' => base64_encode($filePath), 'disk' => $document['disk'] ?? 'private']) : '#';
                            @endphp
                            
                            <div class="border rounded-lg p-4 transition-all duration-200 hover:shadow-md cursor-pointer group" 
                                 style="border-color: var(--border-color); background-color: var(--card-bg);"
                                 onclick="previewDocument('{{ $fileName }}', '{{ $fileType }}', '{{ $previewUrl }}', '{{ $uploadedAt }}', {{ $index }})">
                                <div class="flex items-start">
                                    <div class="mr-3 flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                                             style="background-color: {{ $bgColor }};">
                                            <i class="fas {{ $fileIcon }}" style="color: {{ $fileColor }};"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <p class="font-medium text-sm truncate mb-1" style="color: var(--text-primary);">
                                            {{ $fileName }}
                                        </p>
                                        <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-file mr-1"></i>
                                            <span class="truncate">
                                                @switch(true)
                                                    @case(str_contains($fileType, 'pdf'))
                                                        PDF Document
                                                        @break
                                                    @case(str_contains($fileType, 'image'))
                                                        Image File
                                                        @break
                                                    @case(str_contains($fileType, 'word') || str_contains($fileType, 'document'))
                                                        Word Document
                                                        @break
                                                    @case(str_contains($fileType, 'excel') || str_contains($fileType, 'sheet'))
                                                        Excel Spreadsheet
                                                        @break
                                                    @case(str_contains($fileType, 'text'))
                                                        Text File
                                                        @break
                                                    @default
                                                        {{ $fileType }}
                                                @endswitch
                                            </span>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <span>{{ $uploadedAt }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <button type="button" 
                                            onclick="event.stopPropagation(); previewDocument('{{ $fileName }}', '{{ $fileType }}', '{{ $previewUrl }}', '{{ $uploadedAt }}', {{ $index }})"
                                            class="px-2 py-1 text-xs rounded flex items-center"
                                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                        <i class="fas fa-eye mr-1"></i> Preview
                                    </button>
                                    @if($filePath)
                                    <a href="{{ route('admin.download-document', ['path' => base64_encode($filePath), 'disk' => $document['disk'] ?? 'private']) }}" 
                                       class="px-2 py-1 text-xs rounded flex items-center"
                                       onclick="event.stopPropagation();"
                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Document Summary -->
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <h4 class="text-sm font-medium" style="color: var(--text-primary);">Document Review Guidelines</h4>
                        </div>
                        <ul class="text-xs space-y-1" style="color: var(--text-secondary); line-height: 1.5;">
                            <li>• <strong>Click on any document card to preview</strong> - View documents directly in your browser</li>
                            <li>• <strong>Hover over documents</strong> to see preview and download options</li>
                            <li>• <strong>Required documents should include:</strong> National ID/Passport, Proof of Income, Employment Letter</li>
                            <li>• <strong>Verify document authenticity</strong> before approving the application</li>
                            <li>• <strong>Check document dates</strong> to ensure they are current and valid</li>
                            <li>• <strong>Document preview supports:</strong> PDF, Images (JPG, PNG), Word, Excel files</li>
                        </ul>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Approval Actions Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        @if($canApproveReject)
                            <i class="fas fa-clipboard-check mr-2" style="color: var(--success);"></i> 
                            Approval Actions
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-user-shield mr-1"></i> Admin
                                </span>
                            @endif
                        @else
                            <i class="fas fa-eye mr-2" style="color: var(--info);"></i> 
                            View Details
                        @endif
                    </h3>
                    
                    <!-- Quick Stats -->
                    <div class="space-y-4 mb-6">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Tenant Type -->
                            <div class="text-center p-4 rounded-lg" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Tenant Type</p>
                                <p class="text-lg font-bold" style="color: var(--info);">
                                    {{ ucfirst($unit->tenant_type) }}
                                </p>
                            </div>
                            
                            <!-- Days Pending -->
                            <div class="text-center p-4 rounded-lg" 
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Days Pending</p>
                                <p class="text-lg font-bold" style="color: {{ $daysPendingColor }};">
                                    @if($daysPending !== null)
                                        {{ $daysPending }}
                                        <span class="text-xs font-normal ml-1" style="color: var(--text-secondary);">days</span>
                                    @else
                                        N/A
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-3">
                        @if($canApproveReject)
                            <!-- Reject Button -->
                            <button type="button" onclick="openRejectModal()" 
                                    class="w-full px-4 py-3 rounded-lg inline-flex items-center justify-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times mr-2"></i> Reject Application
                            </button>

                            <!-- Approve Button -->
                            <button type="button" onclick="openApproveModal()" 
                                    class="w-full px-4 py-3 rounded-lg inline-flex items-center justify-center text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200"
                                    style="background-color: var(--success); color: white; border: 2px solid var(--success);">
                                <i class="fas fa-check mr-2"></i> Approve Application
                            </button>
                        @else
                            <!-- View Only Message -->
                            <div class="text-center p-4 rounded-lg" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <i class="fas fa-info-circle text-xl mb-2" style="color: var(--info);"></i>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">View Only Mode</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    @if($isOnLandlordDashboard)
                                        Approvals are handled by administrators
                                    @else
                                        You do not have permission to approve or reject applications
                                    @endif
                                </p>
                            </div>
                        @endif

                        <!-- View Unit Button -->
                        <a href="{{ route('property-units.show', $unit->id) }}" 
                           class="w-full px-4 py-3 rounded-lg inline-flex items-center justify-center text-sm font-medium hover:shadow-md transition-all duration-200"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-eye mr-2"></i> View Unit Details
                        </a>

                        <!-- Bulk Approval Link -->
                        @if($canApproveReject)
                        <a href="{{ $bulkApprovalRoute }}?property_id={{ $unit->property_id }}&tenant_type={{ $unit->tenant_type }}" 
                           class="w-full px-4 py-3 rounded-lg inline-flex items-center justify-center text-sm font-medium hover:shadow-md transition-all duration-200"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-layer-group mr-2"></i> Bulk Approve Similar
                        </a>
                        @endif
                    </div>

                    <!-- Additional Actions -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-ellipsis-h mr-2" style="color: var(--text-secondary);"></i>
                            Additional Options
                        </h4>
                        <div class="space-y-2">
                            @if($unit->property->landlord->email ?? false)
                            <a href="mailto:{{ $unit->property->landlord->email }}?subject=Tenant%20Application%20{{ $unit->id }}" 
                               class="flex items-center text-sm px-2 py-1 rounded transition-colors"
                               style="color: var(--info);">
                                <i class="fas fa-envelope mr-2 w-4"></i>
                                Contact Landlord
                            </a>
                            @endif
                            
                            @if($unit->tenant_type === 'new' && !empty($unit->tenant_details['email']))
                            <a href="mailto:{{ $unit->tenant_details['email'] }}?subject=Tenant%20Application%20Inquiry" 
                               class="flex items-center text-sm px-2 py-1 rounded transition-colors"
                               style="color: var(--info);">
                                <i class="fas fa-envelope mr-2 w-4"></i>
                                Contact Applicant
                            </a>
                            @endif
                            
                            @if($unit->tenant_type === 'existing' && $unit->tenant)
                            <a href="mailto:{{ $unit->tenant->email }}?subject=Tenant%20Application%20Inquiry" 
                               class="flex items-center text-sm px-2 py-1 rounded transition-colors"
                               style="color: var(--info);">
                                <i class="fas fa-envelope mr-2 w-4"></i>
                                Contact Existing Tenant
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Landlord Notes Card -->
            @if($unit->tenant_approval_notes)
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <h3 class="text-base font-medium" style="color: var(--text-primary);">Landlord Notes</h3>
                    </div>
                    
                    <div class="p-4 rounded-lg" 
                         style="background-color: rgba(var(--warning-rgb), 0.05); border-left: 3px solid var(--warning);">
                        <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                            {{ $unit->tenant_approval_notes }}
                        </p>
                        <p class="text-xs mt-3 flex items-center" style="color: var(--text-secondary); opacity: 0.7;">
                            <i class="fas fa-clock mr-1"></i>
                            Added by landlord during submission
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Timeline Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="text-base font-medium" style="color: var(--text-primary);">Application Timeline</h3>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Submitted -->
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: var(--success);">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                                <div class="h-full w-0.5 mt-2" style="background-color: rgba(var(--success-rgb), 0.3);"></div>
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Application Submitted</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ $unit->tenant_requested_at ? $unit->tenant_requested_at->format('M d, Y H:i') : 'N/A' }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Review -->
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: var(--warning);">
                                    <i class="fas fa-clock text-white text-xs"></i>
                                </div>
                                <div class="h-full w-0.5 mt-2" style="background-color: rgba(var(--warning-rgb), 0.3);"></div>
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Under Review</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-user-tie mr-1"></i>
                                    Currently being reviewed
                                    @if($isOnAdminDashboard)
                                        <span class="ml-1 text-xs" style="color: var(--info);">(Admin)</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        
                        <!-- Decision -->
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1); border: 2px solid rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-ellipsis-h" style="color: var(--primary); font-size: 10px;"></i>
                                </div>
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Decision</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($canApproveReject)
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        Awaiting your decision
                                    @else
                                        <i class="fas fa-user-tie mr-1"></i>
                                        Waiting for admin review
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
@if($canApproveReject)
<div id="rejectModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> 
                    Reject Tenant Application
                </h3>
                <button type="button" 
                        id="closeRejectModalBtn" 
                        onclick="closeRejectModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="rejectForm" method="POST" action="{{ $rejectRoute }}" class="theme-modal-body-compact">
                @csrf
                
                <!-- Rejection Reason -->
                <div class="mb-6">
                    <label for="rejection_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Rejection Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea name="rejection_reason" 
                              id="rejection_reason" 
                              required 
                              rows="4"
                              minlength="20"
                              maxlength="500"
                              class="form-textarea w-full"
                              placeholder="Please provide a clear reason for rejecting this tenant assignment request..."
                              style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">{{ old('rejection_reason') }}</textarea>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        This reason will be shared with the landlord (minimum 20 characters)
                    </p>
                </div>

                <!-- Allow Resubmission -->
                <div class="mb-6">
                    <div class="flex items-center space-x-3 mb-3">
                        <input type="checkbox" 
                               id="allow_resubmission" 
                               name="allow_resubmission" 
                               value="1" 
                               class="form-checkbox"
                               {{ old('allow_resubmission') ? 'checked' : '' }}>
                        <label for="allow_resubmission" class="text-sm font-medium flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-redo mr-2"></i>
                            Allow Resubmission
                        </label>
                    </div>
                    
                    <!-- Resubmission Notes -->
                    <div id="resubmissionFields" class="space-y-4 pl-7 mt-3 hidden">
                        <div class="mb-4">
                            <label for="resubmission_notes" class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Resubmission Notes
                            </label>
                            <textarea name="resubmission_notes" 
                                      id="resubmission_notes"
                                      rows="3"
                                      class="form-textarea w-full text-sm"
                                      placeholder="Provide guidance on what needs to be improved for resubmission..."
                                      style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">{{ old('resubmission_notes') }}</textarea>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                These notes will help the landlord improve their application
                            </p>
                        </div>
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

<!-- Approve Modal - FIXED: Shows landlord-set values as read-only -->
<div id="approveModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact" style="max-width: 600px;">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> 
                    Approve Tenant Application
                </h3>
                <button type="button" 
                        id="closeApproveModalBtn" 
                        onclick="closeApproveModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="approveForm" method="POST" action="{{ $approveRoute }}" class="theme-modal-body-compact">
                @csrf
                
                <!-- Approval Notes -->
                <div class="mb-6">
                    <label for="approval_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Approval Notes <span class="text-red-500">*</span>
                        <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">(Minimum 20 characters)</span>
                    </label>
                    <textarea name="approval_notes" 
                              id="approval_notes" 
                              required 
                              rows="3"
                              minlength="20"
                              maxlength="500"
                              class="form-textarea w-full"
                              placeholder="Add notes about this approval..."
                              style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">{{ old('approval_notes', 'Tenant application approved by administrator after review.') }}</textarea>
                </div>

                <!-- FIXED: Display landlord-set values as read-only (cannot be modified by admin) -->
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar mr-1"></i> Move-in Date
                                <span class="ml-1 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    Landlord Set
                                </span>
                            </p>
                            <p class="font-medium text-base" style="color: var(--text-primary);">
                                {{ $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('F j, Y') : 'Not set' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-money-bill-wave mr-1"></i> Final Rent
                                <span class="ml-1 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    Landlord Set
                                </span>
                            </p>
                            <p class="font-medium text-base" style="color: var(--primary);">
                                GHS {{ number_format($unit->proposed_rent ?? $unit->monthly_rent ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t" style="border-color: rgba(var(--primary-rgb), 0.2);">
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-shield-alt mr-1"></i> Security Deposit
                                <span class="ml-1 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    Landlord Set
                                </span>
                            </p>
                            <p class="font-medium text-base" style="color: var(--text-primary);">
                                GHS {{ number_format($unit->security_deposit ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                    <p class="text-xs mt-3" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        These values were set by the landlord and cannot be changed during admin approval
                    </p>
                </div>

                <!-- IMPORTANT NOTICE: Lease will be created by landlord -->
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Lease Agreement Information:</p>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary); line-height: 1.5;">
                                <li>• <strong>Lease creation is managed by the landlord</strong></li>
                                <li>• After approval, the landlord will create the lease agreement</li>
                                <li>• The landlord can customize lease terms as needed</li>
                                <li>• Lease will be sent to tenant for signature by the landlord</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Send Invitation for New Tenants -->
                @if($unit->tenant_type === 'new')
                <div class="mb-6">
                    <div class="flex items-center space-x-3 mb-3">
                        <input type="checkbox" 
                               id="send_invitation" 
                               name="send_invitation" 
                               value="1" 
                               class="form-checkbox"
                               {{ old('send_invitation', '1') ? 'checked' : '' }}>
                        <label for="send_invitation" class="text-sm font-medium flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-2"></i>
                            Send Invitation to New Tenant
                        </label>
                    </div>
                    
                    <!-- Invitation Channels -->
                    <div id="invitationFields" class="space-y-4 pl-7 mt-3">
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Invitation Channels <span class="text-red-500">*</span>
                            </label>
                            <div class="flex flex-wrap gap-3">
                                @php
                                    $oldChannels = old('invitation_channels', $unit->tenant_details['invitation_channels'] ?? ['email']);
                                    $channels = ['email', 'sms', 'whatsapp'];
                                @endphp
                                
                                @foreach($channels as $channel)
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" 
                                               name="invitation_channels[]" 
                                               value="{{ $channel }}" 
                                               class="form-checkbox"
                                               {{ in_array($channel, $oldChannels) ? 'checked' : '' }}>
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            @if($channel === 'email')
                                                <i class="fas fa-envelope mr-1"></i> Email
                                            @elseif($channel === 'sms')
                                                <i class="fas fa-sms mr-1"></i> SMS
                                            @elseif($channel === 'whatsapp')
                                                <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Select how to notify the new tenant about their approval
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Important Notice -->
                <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Important:</p>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary); line-height: 1.5;">
                                <li>• Approving this application will assign the tenant to this unit</li>
                                <li>• The unit status will change from "Reserved" to "Occupied"</li>
                                @if($unit->tenant_type === 'new')
                                    <li>• A new user account will be created for the tenant</li>
                                    <li>• Invitations will be sent via selected channels</li>
                                @endif
                                <li>• <strong>Move-in Date, Rent, and Deposit are fixed as set by landlord</strong></li>
                                <li>• <strong>Lease agreement will be created by the landlord separately</strong></li>
                                <li>• You cannot undo this action once completed</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="theme-modal-footer-compact">
                    <button type="button" 
                            onclick="closeApproveModal()" 
                            class="px-4 py-2 rounded-lg text-sm font-medium" 
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                            style="background-color: var(--success); color: white; border: 2px solid var(--success);"
                            onclick="return confirmApproval()">
                        <i class="fas fa-check mr-2"></i> Approve Application
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
// Store current document data
let currentDocument = null;

// Document Preview Functions
function previewDocument(name, type, url, date, index) {
    if (!url || url === '#') {
        alert('Document preview is not available for this file.');
        return;
    }
    
    currentDocument = {
        name: name,
        type: type,
        url: url,
        date: date,
        index: index
    };
    
    // Update modal title
    document.getElementById('previewDocumentTitle').innerHTML = `
        <i class="fas fa-file-alt mr-2"></i> 
        ${name}
    `;
    
    // Update document info
    const typeText = getFileTypeText(type);
    document.getElementById('previewDocumentType').textContent = typeText;
    document.getElementById('previewDocumentSize').textContent = `Uploaded: ${date}`;
    
    // Show loading indicator
    const previewContent = document.getElementById('previewContent');
    previewContent.innerHTML = `
        <div class="text-center">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                 style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-spinner fa-spin text-xl" style="color: var(--info);"></i>
            </div>
            <p class="text-sm" style="color: var(--text-secondary);">
                Loading document preview...
            </p>
        </div>
    `;
    
    // Show modal
    document.getElementById('documentPreviewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load content based on file type
    setTimeout(() => {
        loadDocumentPreview(type, url);
    }, 100);
}

function getFileTypeText(type) {
    if (type.includes('pdf')) return 'PDF Document';
    if (type.includes('image')) return 'Image File';
    if (type.includes('word') || type.includes('document')) return 'Word Document';
    if (type.includes('excel') || type.includes('sheet')) return 'Excel Spreadsheet';
    if (type.includes('text')) return 'Text File';
    return 'Document';
}

function loadDocumentPreview(type, url) {
    const previewContent = document.getElementById('previewContent');
    
    if (type.includes('pdf')) {
        previewContent.innerHTML = `
            <div class="w-full h-full">
                <iframe 
                    src="${url}" 
                    class="w-full h-full min-h-[500px] border rounded-lg"
                    style="border-color: var(--border-color);"
                    frameborder="0">
                </iframe>
                <div class="text-center mt-2">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        PDF preview may require download for full functionality
                    </p>
                </div>
            </div>
        `;
    } else if (type.includes('image')) {
        previewContent.innerHTML = `
            <div class="text-center">
                <img src="${url}" 
                     alt="Document Preview" 
                     class="max-w-full max-h-[70vh] mx-auto rounded-lg shadow-lg"
                     onerror="this.onerror=null; showDownloadOnly()">
                <div class="mt-4">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-expand-arrows-alt mr-1"></i>
                        Click and drag to view full image
                    </p>
                </div>
            </div>
        `;
    } else if (type.includes('word') || type.includes('document')) {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(37, 99, 235, 0.1);">
                    <i class="fas fa-file-word text-3xl" style="color: #2563eb;"></i>
                </div>
                <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Word Document</h4>
                <p class="text-sm mb-4" style="color: var(--text-secondary); max-w-md mx-auto">
                    Word documents cannot be previewed directly in the browser.
                    Please download the file to view its contents.
                </p>
                <a href="${url.replace('/preview/', '/download/')}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <i class="fas fa-download mr-2"></i> Download Word Document
                </a>
            </div>
        `;
    } else if (type.includes('excel') || type.includes('sheet')) {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(22, 163, 74, 0.1);">
                    <i class="fas fa-file-excel text-3xl" style="color: #16a34a;"></i>
                </div>
                <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Excel Spreadsheet</h4>
                <p class="text-sm mb-4" style="color: var(--text-secondary); max-w-md mx-auto">
                    Excel files cannot be previewed directly in the browser.
                    Please download the file to view its contents.
                </p>
                <a href="${url.replace('/preview/', '/download/')}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <i class="fas fa-download mr-2"></i> Download Excel File
                </a>
            </div>
        `;
    } else if (type.includes('text')) {
        fetch(url)
            .then(response => response.text())
            .then(text => {
                previewContent.innerHTML = `
                    <div class="w-full">
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-auto text-sm font-mono max-h-[500px]">${escapeHtml(text.substring(0, 10000))}</pre>
                        <div class="text-center mt-2">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Showing first 10,000 characters
                            </p>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                showDownloadOnly();
            });
    } else {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-file text-3xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Unsupported File Type</h4>
                <p class="text-sm mb-4" style="color: var(--text-secondary); max-w-md mx-auto">
                    This file type cannot be previewed in the browser.
                    Please download the file to view its contents.
                </p>
                <a href="${url.replace('/preview/', '/download/')}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <i class="fas fa-download mr-2"></i> Download File
                </a>
            </div>
        `;
    }
}

function showDownloadOnly() {
    const previewContent = document.getElementById('previewContent');
    previewContent.innerHTML = `
        <div class="text-center py-8">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                 style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-exclamation-triangle text-3xl" style="color: var(--warning);"></i>
            </div>
            <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Preview Unavailable</h4>
            <p class="text-sm mb-4" style="color: var(--text-secondary); max-w-md mx-auto">
                Unable to load document preview. The file may be corrupted or in an unsupported format.
            </p>
            <a href="${currentDocument.url.replace('/preview/', '/download/')}" 
               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <i class="fas fa-download mr-2"></i> Download File
            </a>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function downloadCurrentDocument() {
    if (currentDocument && currentDocument.url) {
        const downloadUrl = currentDocument.url.replace('/preview/', '/download/');
        window.open(downloadUrl, '_blank');
    }
}

function closeDocumentPreview() {
    document.getElementById('documentPreviewModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentDocument = null;
}

document.addEventListener('DOMContentLoaded', function() {
    // Toggle invitation fields
    const invitationCheckbox = document.getElementById('send_invitation');
    const invitationFields = document.getElementById('invitationFields');
    
    if (invitationCheckbox && invitationFields) {
        function toggleInvitationFields() {
            invitationFields.style.display = invitationCheckbox.checked ? 'block' : 'none';
            
            const invitationChannelCheckboxes = invitationFields.querySelectorAll('input[type="checkbox"]');
            if (invitationCheckbox.checked) {
                invitationChannelCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', validateInvitationChannels);
                });
            }
        }
        
        invitationCheckbox.addEventListener('change', toggleInvitationFields);
        toggleInvitationFields(); // Initial state
    }

    // Toggle resubmission fields
    const resubmissionCheckbox = document.getElementById('allow_resubmission');
    const resubmissionFields = document.getElementById('resubmissionFields');
    
    if (resubmissionCheckbox && resubmissionFields) {
        function toggleResubmissionFields() {
            resubmissionFields.style.display = resubmissionCheckbox.checked ? 'block' : 'none';
        }
        
        resubmissionCheckbox.addEventListener('change', toggleResubmissionFields);
        toggleResubmissionFields(); // Initial state
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

    // Validate invitation channels
    function validateInvitationChannels() {
        const channelCheckboxes = document.querySelectorAll('input[name="invitation_channels[]"]');
        let checkedCount = 0;
        
        channelCheckboxes.forEach(checkbox => {
            if (checkbox.checked) checkedCount++;
        });
        
        if (checkedCount === 0) {
            alert('Please select at least one invitation channel');
            return false;
        }
        
        return true;
    }

    // Form validation for approve form
    const approveForm = document.getElementById('approveForm');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            const approvalNotes = document.getElementById('approval_notes');
            if (approvalNotes && approvalNotes.value.length < 20) {
                e.preventDefault();
                alert('Approval notes must be at least 20 characters long');
                approvalNotes.focus();
                return false;
            }

            const sendInvitation = document.getElementById('send_invitation');
            if (sendInvitation && sendInvitation.checked) {
                if (!validateInvitationChannels()) {
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    }
});

// Modal Functions
@if($canApproveReject)
    function openRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            const textarea = document.getElementById('rejection_reason');
            if (textarea) textarea.focus();
        }, 100);
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        
        const textarea = document.getElementById('rejection_reason');
        if (textarea) textarea.value = '';
    }

    function openApproveModal() {
        const modal = document.getElementById('approveModal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeApproveModal() {
        const modal = document.getElementById('approveModal');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function confirmApproval() {
        return confirm('Are you sure you want to approve this tenant application?\n\nThis will:\n• Assign the tenant to the unit\n• Change unit status to "Occupied"\n• Notify the tenant (if applicable)\n• Use landlord-set: Move-in Date, Rent, and Deposit\n• Lease will be created by landlord separately\n\nThis action cannot be undone.');
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRejectModal();
            closeApproveModal();
            closeDocumentPreview();
        }
    });

    // Close modal on outside click
    document.addEventListener('click', function(e) {
        const rejectModal = document.getElementById('rejectModal');
        const approveModal = document.getElementById('approveModal');
        const docPreviewModal = document.getElementById('documentPreviewModal');
        
        if (rejectModal && e.target === rejectModal) {
            closeRejectModal();
        }
        if (approveModal && e.target === approveModal) {
            closeApproveModal();
        }
        if (docPreviewModal && e.target === docPreviewModal) {
            closeDocumentPreview();
        }
    });
@endif
</script>

<style>
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

.form-checkbox:checked::after {
    content: '✓';
    color: white;
    position: absolute;
    font-size: 0.75rem;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

/* Modal Styles */
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
    max-height: 70vh;
    overflow-y: auto;
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

/* Document Preview Styles */
#documentPreviewModal .theme-modal-compact {
    max-width: 90vw;
    max-height: 90vh;
    width: 1200px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .lg\:col-span-2 {
        grid-column: 1;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2,
    .grid.grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .card .p-6 {
        padding: 1rem;
    }

    .flex.flex-col.md\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .theme-modal-compact {
        width: 95vw;
        margin: 0.5rem;
    }
    
    #documentPreviewModal .theme-modal-compact {
        width: 95vw;
        max-width: 95vw;
        margin: 0.5rem;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .space-y-6 {
        gap: 1rem;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .theme-modal-body-compact {
        padding: 1rem;
    }
    
    .theme-modal-header-compact,
    .theme-modal-footer-compact {
        padding: 1rem;
    }
    
    .document-card {
        padding: 0.75rem;
    }
}
</style>
@endsection