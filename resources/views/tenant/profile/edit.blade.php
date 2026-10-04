@extends('layouts.tenant')

@php
    $user = Auth::user();
    
    // ==================== DASHBOARD AWARE LOGIC ====================
    $sourceDashboard = session('last_dashboard', 'tenant');
    $returnRoute = null;
    $returnLabel = 'Dashboard';
    $returnIcon = 'tachometer-alt';
    
    $roleBasedRoles = $user->roles ?? collect();
    $legacyType = $user->type;
    $legacyTypeRoleMap = [
        0 => 'super-admin',
        1 => 'admin',
        2 => 'landlord',
        3 => 'tenant',
        4 => 'field-agent',
        5 => 'developer',
        6 => 'security-personnel',
    ];
    $legacyRoleSlug = $legacyTypeRoleMap[$legacyType] ?? null;
    
    $allRoleSlugs = $roleBasedRoles->pluck('slug')->toArray();
    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $allRoleSlugs)) {
        $allRoleSlugs[] = $legacyRoleSlug;
    }
    $hasMultipleRoles = count($allRoleSlugs) > 1;
    
    $currentRole = session('selected_role', $legacyRoleSlug ?? 'tenant');
    
    $roleIcons = [
        'super-admin' => 'crown',
        'admin' => 'shield-alt',
        'landlord' => 'home',
        'tenant' => 'user',
        'field-agent' => 'clipboard-list',
        'security-personnel' => 'shield-alt',
        'developer' => 'code',
    ];
    
    $roleDescriptions = [
        'super-admin' => 'Full system control',
        'admin' => 'System management',
        'landlord' => 'Property portfolio management',
        'tenant' => 'Rental management',
        'field-agent' => 'Property registration',
        'security-personnel' => 'Security operations',
        'developer' => 'System development',
    ];
    
    $switcherRoles = [];
    $addedSlugs = [];
    
    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $addedSlugs)) {
        $switcherRoles[] = (object)[
            'slug' => $legacyRoleSlug,
            'display_name' => ucfirst(str_replace('-', ' ', $legacyRoleSlug)),
            'source' => 'legacy',
            'description' => $roleDescriptions[$legacyRoleSlug] ?? 'Dashboard access'
        ];
        $addedSlugs[] = $legacyRoleSlug;
    }
    
    foreach ($roleBasedRoles as $role) {
        if (!in_array($role->slug, $addedSlugs)) {
            $switcherRoles[] = (object)[
                'slug' => $role->slug,
                'display_name' => $role->display_name ?? ucfirst(str_replace('-', ' ', $role->slug)),
                'source' => 'role',
                'description' => $roleDescriptions[$role->slug] ?? 'Dashboard access'
            ];
            $addedSlugs[] = $role->slug;
        }
    }
    
    usort($switcherRoles, function($a, $b) {
        $order = ['super-admin' => 0, 'admin' => 1, 'landlord' => 2, 'tenant' => 3];
        $orderA = $order[$a->slug] ?? 99;
        $orderB = $order[$b->slug] ?? 99;
        if ($orderA === $orderB) return strcmp($a->slug, $b->slug);
        return $orderA - $orderB;
    });
    
    switch($currentRole) {
        case 'super-admin':
            $returnRoute = route('super-admin.dashboard');
            $returnLabel = 'Super Admin Dashboard';
            $returnIcon = 'crown';
            break;
        case 'admin':
            $returnRoute = route('admin.dashboard');
            $returnLabel = 'Admin Dashboard';
            $returnIcon = 'shield-alt';
            break;
        case 'landlord':
            $returnRoute = route('landlord.dashboard');
            $returnLabel = 'Landlord Dashboard';
            $returnIcon = 'home';
            break;
        case 'tenant':
            $returnRoute = route('tenant.dashboard');
            $returnLabel = 'Tenant Dashboard';
            $returnIcon = 'user';
            break;
        case 'field-agent':
            $returnRoute = route('field-agent.dashboard');
            $returnLabel = 'Field Agent Dashboard';
            $returnIcon = 'clipboard-list';
            break;
        case 'security-personnel':
            $returnRoute = route('security.dashboard');
            $returnLabel = 'Security Dashboard';
            $returnIcon = 'shield-alt';
            break;
        case 'developer':
            $returnRoute = route('developer.dashboard');
            $returnLabel = 'Developer Dashboard';
            $returnIcon = 'code';
            break;
        default:
            $returnRoute = route('tenant.dashboard');
            $returnLabel = 'Tenant Dashboard';
            $returnIcon = 'user';
    }
    
    $titlePrefix = ucfirst(str_replace('-', ' ', $currentRole));
    
    // Rental stats
    use App\Models\Rental;
    $activeRentals = $user->rentals()
        ->where('status', Rental::STATUS_ACTIVE)
        ->count();
    
    $pastRentals = $user->rentals()
        ->whereIn('status', [Rental::STATUS_COMPLETED, Rental::STATUS_CANCELLED])
        ->count();
    
    $totalRent = $user->rentals()
        ->where('status', Rental::STATUS_ACTIVE)
        ->sum('monthly_rent');
    
    $nextPayment = null;
    $activeRental = $user->rentals()
        ->where('status', Rental::STATUS_ACTIVE)
        ->first();
    
    if ($activeRental) {
        $nextPayment = now()->addMonth()->startOfMonth();
    }
@endphp

@section('title', "Edit Profile - {$titlePrefix}")

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header with Stats -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">{{ $titlePrefix }} Profile</h1>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Manage your account and rental information</p>
                </div>
                
                <div class="flex items-center space-x-4 flex-wrap gap-3">
                    <!-- ✅ THEME: Profile completion percentage -->
                    <div class="text-center">
                        <div class="text-2xl font-bold" style="color: var(--warning);" data-profile-completion>{{ $profileCompletion ?? 0 }}%</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Profile Complete</div>
                    </div>
                    
                    @if($hasMultipleRoles)
                    <div class="dashboard-switcher relative">
                        <button id="profileSwitcherBtn" 
                                class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all text-sm"
                                style="background: linear-gradient(135deg, var(--warning) 0%, var(--orange) 100%); color: white;">
                            <i class="fas fa-{{ $roleIcons[$currentRole] ?? 'user' }} mr-1"></i>
                            <span>{{ ucfirst(str_replace('-', ' ', $currentRole)) }}</span>
                            <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </button>
                        
                        <div id="profileSwitcherMenu" 
                             class="absolute right-0 mt-2 w-72 rounded-xl shadow-lg overflow-hidden z-50 hidden"
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="py-2">
                                <div class="px-4 py-2 border-b" style="border-color: var(--border-color);">
                                    <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">
                                        <i class="fas fa-layer-group mr-1"></i> Switch Dashboard
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.7;">
                                        You have {{ count($switcherRoles) }} dashboard{{ count($switcherRoles) > 1 ? 's' : '' }} available
                                    </p>
                                </div>
                                
                                @foreach($switcherRoles as $role)
                                    @php
                                        $isActive = ($currentRole === $role->slug);
                                        $icon = $roleIcons[$role->slug] ?? 'user';
                                    @endphp
                                    <button type="button"
                                            class="dashboard-switch-option w-full text-left flex items-center gap-3 px-4 py-3 transition-all {{ $isActive ? 'active-option' : '' }}"
                                            data-role="{{ $role->slug }}"
                                            data-current-role="{{ $currentRole }}"
                                            style="display: flex; color: var(--text-primary); {{ $isActive ? 'background-color: rgba(var(--warning-rgb), 0.1); border-left: 3px solid var(--warning);' : '' }}">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center" 
                                             style="background: {{ $isActive ? 'linear-gradient(135deg, var(--warning) 0%, var(--orange) 100%)' : 'rgba(var(--warning-rgb), 0.1)' }}">
                                            <i class="fas fa-{{ $icon }} text-sm" style="color: {{ $isActive ? 'white' : 'var(--warning)' }}"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">{{ ucfirst(str_replace('-', ' ', $role->slug)) }}</p>
                                            <p class="text-xs" style="color: var(--text-secondary);">
                                                @if(($role->source ?? '') === 'legacy')
                                                    <span class="inline-flex items-center gap-1">
                                                        <i class="fas fa-database text-xs"></i> Legacy Type • {{ $role->description }}
                                                    </span>
                                                @else
                                                    {{ $role->description }}
                                                @endif
                                            </p>
                                        </div>
                                        @if($isActive)
                                            <i class="fas fa-check-circle mr-2" style="color: var(--success); font-size: 0.875rem;"></i>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <a href="{{ $returnRoute }}" 
                       class="btn-secondary flex items-center" id="returnDashboardBtn">
                        <i class="fas fa-{{ $returnIcon }} mr-2"></i> {{ $returnLabel }}
                    </a>
                </div>
            </div>
            
            <!-- Progress Breakdown -->
            <div class="card p-4 mb-6">
                <h3 class="font-semibold mb-3" style="color: var(--text-primary);">Complete Your Profile</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    @if(isset($profileStats['completion_details']))
                        @foreach($profileStats['completion_details'] as $field => $detail)
                            @if($field !== 'total_weight' && $field !== 'completed_weight')
                                <div class="flex items-center space-x-2" title="{{ $detail['completed'] ? 'Completed' : 'Incomplete' }}">
                                    <!-- ✅ THEME: Completed = success var, Incomplete = muted -->
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs"
                                         style="background-color: {{ $detail['completed'] ? 'rgba(var(--success-rgb), 0.15)' : 'rgba(var(--text-secondary-rgb, 150 150 150), 0.1)' }};
                                                color: {{ $detail['completed'] ? 'var(--success)' : 'var(--text-secondary)' }};">
                                        <i class="fas {{ $detail['completed'] ? 'fa-check' : 'fa-times' }}"></i>
                                    </div>
                                    <span class="text-sm truncate" style="color: var(--text-secondary);">
                                        {{ $detail['label'] ?? ucfirst(str_replace('_', ' ', $field)) }}
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    @else
                        <div class="col-span-2 md:col-span-5 text-center py-4" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-2"></i>Profile completion details not available
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i>
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left Column - Profile & Stats -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Photo Card -->
                <div class="card p-6">
                    <div class="text-center">
                        <div class="relative inline-block mb-4">
                            @php
                                $photoUrl = $user->photo_url ?? null;
                                $initials = $user->getInitials();
                                $hasPhoto = !empty($user->photo) && $photoUrl;
                            @endphp
                            
                            @if($hasPhoto)
                                <div class="relative">
                                    <img src="{{ $photoUrl }}" 
                                         alt="{{ $user->name }}"
                                         class="w-32 h-32 rounded-full object-cover border-4 mx-auto shadow-lg lazy"
                                         style="border-color: var(--warning);"
                                         id="profileImage"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='{{ asset("images/default-avatar.png") }}'">
                                </div>
                            @else
                                <div class="w-32 h-32 rounded-full flex items-center justify-center font-semibold text-white text-3xl mx-auto shadow-lg"
                                     style="background: linear-gradient(135deg, var(--warning), var(--orange));"
                                     id="avatarPlaceholder">
                                    {{ $initials }}
                                </div>
                            @endif
                            
                            <div id="uploadProgress" class="hidden mt-2">
                                <div class="flex items-center justify-center space-x-2">
                                    <!-- ✅ THEME: Spinner uses warning color -->
                                    <div class="w-8 h-8 border-2 border-t-transparent rounded-full animate-spin" style="border-color: var(--warning); border-top-color: transparent;"></div>
                                    <span class="text-sm" style="color: var(--text-secondary);">Uploading...</span>
                                </div>
                            </div>
                        </div>

                        <h3 class="font-semibold mb-1" style="color: var(--text-primary);">{{ $user->name ?? 'No Name' }}</h3>
                        <div class="mb-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium mb-2" 
                                  style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.1), rgba(var(--orange-rgb), 0.1)); color: var(--warning);">
                                {{ $titlePrefix }}
                            </span>
                            @if($hasMultipleRoles)
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium ml-1" 
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-tags mr-1"></i> Multi-Role
                            </span>
                            @endif
                        </div>

                        <div class="space-y-2">
                            <form id="updatePhotoForm" enctype="multipart/form-data" class="hidden">
                                @csrf
                                <input type="file" name="photo" id="photoInput" accept="image/*" class="hidden">
                            </form>
                            
                            <button onclick="document.getElementById('photoInput').click()" 
                                    class="btn-warning btn-sm w-full" id="uploadBtn">
                                <i class="fas fa-camera mr-2"></i>
                                <span>{{ $hasPhoto ? 'Change Photo' : 'Upload Photo' }}</span>
                            </button>
                            
                            @if($hasPhoto)
                            <button onclick="removeProfilePhoto()" 
                                    class="btn-danger btn-sm w-full" id="removeBtn">
                                <i class="fas fa-trash mr-2"></i>Remove Photo
                            </button>
                            @endif
                        </div>
                    </div>
                    
                    <div class="mt-4 text-xs" style="color: var(--text-secondary);">
                        <p class="flex items-center mb-1">
                            <i class="fas fa-info-circle mr-2" style="color: var(--warning);"></i>
                            Max size: 5MB
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            Formats: JPEG, PNG, GIF, WebP
                        </p>
                    </div>
                </div>

                <!-- Role Information Card -->
                @if($hasMultipleRoles)
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-layer-group mr-2"></i>Your Roles
                    </h3>
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($switcherRoles as $role)
                            <!-- ✅ THEME: Role badges use theme variables -->
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--warning-rgb), 0.1);
                                         color: var(--warning);
                                         border: 1px solid rgba(var(--warning-rgb), 0.25);">
                                <i class="fas fa-{{ $roleIcons[$role->slug] ?? 'user' }} mr-1"></i>
                                {{ ucfirst(str_replace('-', ' ', $role->slug)) }}
                                @if($role->slug === $currentRole)
                                    <span class="ml-1 text-xs opacity-70">(current)</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                        Use the dashboard switcher above to access different dashboards.
                    </p>
                </div>
                @endif

                <!-- Rental Overview Card -->
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Rental Overview</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-2xl font-bold" style="color: var(--text-primary);">
                                    {{ $activeRentals }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Active Rentals</div>
                            </div>
                            <i class="fas fa-home text-2xl" style="color: var(--warning);"></i>
                        </div>
                        
                        <!-- ✅ THEME: Past rentals uses success var -->
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-xl font-bold" style="color: var(--success);">
                                    {{ $pastRentals }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Past Rentals</div>
                            </div>
                            <i class="fas fa-history text-xl" style="color: var(--success);"></i>
                        </div>
                        
                        <!-- ✅ THEME: Monthly rent uses info var -->
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-xl font-bold" style="color: var(--info);">
                                    GHS {{ number_format($totalRent, 2) }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Monthly Rent</div>
                            </div>
                            <i class="fas fa-money-bill-wave text-xl" style="color: var(--info);"></i>
                        </div>
                        
                        @if($nextPayment)
                        <!-- ✅ THEME: Next payment uses primary var -->
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-xl font-bold" style="color: var(--primary);">
                                    {{ $nextPayment->format('M d') }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Next Payment Due</div>
                            </div>
                            <i class="fas fa-calendar-alt text-xl" style="color: var(--primary);"></i>
                        </div>
                        @endif
                    </div>
                    
                    @if(Route::has('tenant.rentals.index'))
                        <a href="{{ route('tenant.rentals.index') }}" 
                           class="mt-4 btn-outline btn-sm w-full text-center">
                            <i class="fas fa-external-link-alt mr-2"></i>Manage Rentals
                        </a>
                    @endif
                </div>

                <!-- Verification Status Card -->
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Verification Status</h3>
                    <div class="space-y-3">
                        <!-- Email Verification -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-envelope" style="color: var(--text-secondary);"></i>
                                <span class="text-sm" style="color: var(--text-secondary);">Email</span>
                            </div>
                            @if($user->email_verified_at)
                                <!-- ✅ THEME: Verified pill -->
                                <span class="px-2 py-1 text-xs rounded-full"
                                      style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                    <i class="fas fa-check mr-1"></i>Verified
                                </span>
                            @else
                                <!-- ✅ THEME: Pending pill -->
                                <button onclick="sendEmailVerification()" 
                                        class="px-2 py-1 text-xs rounded-full transition"
                                        style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                    <i class="fas fa-envelope mr-1"></i>Verify
                                </button>
                            @endif
                        </div>
                        
                        <!-- Phone Verification -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
                                <span class="text-sm" style="color: var(--text-secondary);">Phone</span>
                            </div>
                            @if($user->phone_verified_at)
                                <span class="px-2 py-1 text-xs rounded-full"
                                      style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                    <i class="fas fa-check mr-1"></i>Verified
                                </span>
                            @else
                                <div class="flex space-x-2">
                                    @if($user->phone)
                                        <button onclick="sendPhoneVerification()" 
                                                class="px-2 py-1 text-xs rounded-full transition"
                                                style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                            <i class="fas fa-sms mr-1"></i>Send Code
                                        </button>
                                        <button onclick="showTab('contact'); showVerifyModal();" 
                                                class="px-2 py-1 text-xs rounded-full transition"
                                                style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info);">
                                            <i class="fas fa-key mr-1"></i>Verify
                                        </button>
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">Add phone first</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Tenant ID -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-id-card" style="color: var(--text-secondary);"></i>
                                <span class="text-sm" style="color: var(--text-secondary);">Tenant ID</span>
                            </div>
                            @if($user->metadata['tenant_id_verified'] ?? false)
                                <span class="px-2 py-1 text-xs rounded-full"
                                      style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                    <i class="fas fa-check mr-1"></i>Verified
                                </span>
                            @else
                                <span class="text-xs font-mono" style="color: var(--text-secondary);">
                                    TN-{{ str_pad($user->id, 6, '0', STR_PAD_LEFT) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Account Stats Card -->
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Account Stats</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-sm" style="color: var(--text-secondary);">Member Since</div>
                                <div class="font-semibold" style="color: var(--text-primary);">
                                    {{ $user->created_at->format('M Y') }}
                                </div>
                            </div>
                            <i class="fas fa-calendar text-xl" style="color: var(--warning);"></i>
                        </div>
                        
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-sm" style="color: var(--text-secondary);">Current Role</div>
                                <div class="font-semibold" style="color: var(--text-primary);">
                                    {{ $titlePrefix }}
                                </div>
                            </div>
                            <i class="fas fa-{{ $roleIcons[$currentRole] ?? 'user' }} text-xl" style="color: var(--info);"></i>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-sm" style="color: var(--text-secondary);">Account Age</div>
                                <div class="font-semibold" style="color: var(--text-primary);">
                                    {{ $profileStats['account_age_days'] ?? 0 }} days
                                </div>
                            </div>
                            <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Quick Actions</h3>
                    <div class="space-y-2">
                        @if(Route::has('tenant.payments.index'))
                        <a href="{{ route('tenant.payments.index') }}" 
                           class="flex items-center justify-between p-3 rounded-lg transition quick-action-item"
                           style="color: var(--text-secondary);">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-credit-card" style="color: var(--success);"></i>
                                <span>Make Payment</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                        @endif

                        @if(Route::has('tenant.maintenance.create'))
                        <a href="{{ route('tenant.maintenance.create') }}" 
                           class="flex items-center justify-between p-3 rounded-lg transition quick-action-item"
                           style="color: var(--text-secondary);">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-tools" style="color: var(--info);"></i>
                                <span>Request Maintenance</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                        @endif

                        @if(Route::has('tenant.documents.index'))
                        <a href="{{ route('tenant.documents.index') }}" 
                           class="flex items-center justify-between p-3 rounded-lg transition quick-action-item"
                           style="color: var(--text-secondary);">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-file-contract" style="color: var(--primary);"></i>
                                <span>View Documents</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                        @endif

                        @if(Route::has('tenant.profile.data.download'))
                        <button onclick="downloadTenantData()" 
                                class="flex items-center justify-between w-full p-3 rounded-lg transition text-left quick-action-item"
                                style="color: var(--text-secondary);">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-download" style="color: var(--warning);"></i>
                                <span>Download My Data</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs"></i>
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column - Forms -->
            <div class="lg:col-span-3">
                <!-- Tabs Navigation -->
                <div class="mb-6 border-b" style="border-color: var(--border-color);">
                    <nav class="flex space-x-4 overflow-x-auto" id="tabNav">
                        <button type="button" data-tab="personal" class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap active-tab">
                            <i class="fas fa-user mr-2"></i>Personal Info
                        </button>
                        <button type="button" data-tab="contact" class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-address-book mr-2"></i>Contact Info
                        </button>
                        <button type="button" data-tab="tenant" class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-home mr-2"></i>Tenant Details
                        </button>
                        <button type="button" data-tab="emergency" class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-phone-alt mr-2"></i>Emergency Contacts
                        </button>
                        <button type="button" data-tab="password" class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-key mr-2"></i>Password
                        </button>
                    </nav>
                </div>

                <!-- Tab Content Wrapper -->
                <div id="tabContent">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane active" id="personalPane" data-tab-pane="personal">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Personal Information</h3>
                            
                            <form action="{{ route('tenant.profile.personal.update') }}" method="POST" id="personalInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Full Name *
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(As on official ID)</span>
                                            </label>
                                            <input type="text" name="name" value="{{ old('name', $user->name) }}" 
                                                   class="form-input"
                                                   required>
                                            @error('name')
                                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Date of Birth
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Required for age verification)</span>
                                            </label>
                                            <input type="date" name="dob" 
                                                   value="{{ old('dob', $user->dob ? $user->dob->format('Y-m-d') : '') }}" 
                                                   class="form-input"
                                                   max="{{ date('Y-m-d') }}">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Gender</label>
                                            <select name="gender" class="form-input">
                                                <option value="">Select Gender</option>
                                                <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                                <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                                <option value="other" {{ old('gender', $user->gender) == 'other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                National ID Number
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Optional)</span>
                                            </label>
                                            <input type="text" name="national_id" 
                                                   value="{{ old('national_id', $user->metadata['national_id'] ?? '') }}" 
                                                   class="form-input"
                                                   placeholder="GHA-XXXXXXXX-X">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Passport Number
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Optional, for foreign nationals)</span>
                                            </label>
                                            <input type="text" name="passport_number" 
                                                   value="{{ old('passport_number', $user->metadata['passport_number'] ?? '') }}" 
                                                   class="form-input"
                                                   placeholder="Passport number">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Marital Status
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Optional)</span>
                                            </label>
                                            <select name="marital_status" class="form-input">
                                                <option value="">Select Status</option>
                                                <option value="single" {{ old('marital_status', $user->metadata['marital_status'] ?? '') == 'single' ? 'selected' : '' }}>Single</option>
                                                <option value="married" {{ old('marital_status', $user->metadata['marital_status'] ?? '') == 'married' ? 'selected' : '' }}>Married</option>
                                                <option value="divorced" {{ old('marital_status', $user->metadata['marital_status'] ?? '') == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                                <option value="widowed" {{ old('marital_status', $user->metadata['marital_status'] ?? '') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" 
                                            onclick="event.preventDefault(); event.stopPropagation(); showTab('contact');" 
                                            class="btn-outline">
                                        Next: Contact Info <i class="fas fa-arrow-right ml-1"></i>
                                    </button>
                                    <button type="submit" class="btn-warning" id="personalSubmitBtn">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contact Information Tab -->
                    <div class="tab-pane hidden" id="contactPane" data-tab-pane="contact">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Contact Information</h3>
                            
                            <form action="{{ route('tenant.profile.contact.update') }}" method="POST" id="contactInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Email -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Email Address *
                                            @if($user->email_verified_at)
                                                <span class="ml-2 px-2 py-1 text-xs rounded-full"
                                                      style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                                    <i class="fas fa-check mr-1"></i>Verified
                                                </span>
                                            @endif
                                        </label>
                                        <div class="flex space-x-2">
                                            <input type="email" name="email" 
                                                   value="{{ old('email', $user->email) }}" 
                                                   class="form-input flex-1"
                                                   required>
                                            @if(!$user->email_verified_at)
                                                <button type="button" onclick="sendEmailVerification()" 
                                                        class="btn-warning">
                                                    Verify
                                                </button>
                                            @endif
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            For rental notifications and communications
                                        </div>
                                        @error('email')
                                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Phone Number *
                                            @if($user->phone_verified_at)
                                                <span class="ml-2 px-2 py-1 text-xs rounded-full"
                                                      style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                                    <i class="fas fa-check mr-1"></i>Verified
                                                </span>
                                            @endif
                                        </label>
                                        <div class="space-y-3">
                                            <div class="flex space-x-2">
                                                <input type="text" name="phone" id="phoneNumber"
                                                       value="{{ old('phone', $user->phone) }}" 
                                                       class="form-input flex-1"
                                                       required>
                                                @if(!$user->phone_verified_at && $user->phone)
                                                    <button type="button" onclick="sendPhoneVerification()" 
                                                            class="btn-warning">
                                                        Send Code
                                                    </button>
                                                @endif
                                            </div>
                                            
                                            <!-- Inline Verification Section -->
                                            <div id="verificationCodeSection" class="hidden space-y-2">
                                                <div class="flex space-x-2">
                                                    <input type="text" id="verificationCode" 
                                                           placeholder="Enter 6-digit code"
                                                           class="form-input flex-1 text-center text-lg font-mono"
                                                           maxlength="6">
                                                    <button type="button" onclick="verifyPhone()" 
                                                            class="btn-success">
                                                        <i class="fas fa-check-circle mr-1"></i>Verify
                                                    </button>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <button type="button" onclick="resendVerificationCode()" 
                                                            class="text-xs hover:underline" style="color: var(--warning);">
                                                        <i class="fas fa-redo mr-1"></i>Resend Code
                                                    </button>
                                                    <div class="text-xs" style="color: var(--text-secondary);" id="timerDisplay">
                                                        Code expires in <span id="countdownTimer">10:00</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            For SMS alerts and emergency contacts
                                        </div>
                                        @error('phone')
                                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Location Info -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Region
                                            </label>
                                            <input type="text" name="region" 
                                                   value="{{ old('region', $user->region) }}" 
                                                   class="form-input"
                                                   placeholder="Your current region">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Digital Address
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Ghana GPS)</span>
                                            </label>
                                            <input type="text" name="digital_address" 
                                                   value="{{ old('digital_address', $user->digital_address) }}" 
                                                   class="form-input"
                                                   placeholder="e.g., GA-123-4567">
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Current Address</label>
                                            <input type="text" name="location" 
                                                   value="{{ old('location', $user->location) }}" 
                                                   class="form-input"
                                                   placeholder="Current residential address">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Alternative Phone
                                            <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Optional)</span>
                                        </label>
                                        <input type="text" name="alt_phone" 
                                               value="{{ old('alt_phone', $user->metadata['alt_phone'] ?? '') }}" 
                                               class="form-input"
                                               placeholder="Alternative contact number">
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" 
                                            onclick="event.preventDefault(); event.stopPropagation(); showTab('personal');" 
                                            class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" 
                                                onclick="event.preventDefault(); event.stopPropagation(); showTab('tenant');" 
                                                class="btn-outline">
                                            Next: Tenant Details <i class="fas fa-arrow-right ml-1"></i>
                                        </button>
                                        <button type="submit" class="btn-warning" id="contactSubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tenant Details Tab -->
                    <div class="tab-pane hidden" id="tenantPane" data-tab-pane="tenant">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Tenant Details</h3>
                            
                            <form action="{{ route('tenant.profile.tenant.update') }}" method="POST" id="tenantInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Tenant Information -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Tenant ID
                                                <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Auto-generated)</span>
                                            </label>
                                            <!-- ✅ THEME: Read-only input -->
                                            <input type="text" value="TN-{{ str_pad($user->id, 6, '0', STR_PAD_LEFT) }}" 
                                                   class="form-input"
                                                   style="background-color: var(--bg-tertiary); color: var(--text-secondary); cursor: not-allowed;"
                                                   disabled readonly>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Date Registered
                                            </label>
                                            <input type="text" value="{{ $user->created_at->format('M d, Y') }}" 
                                                   class="form-input"
                                                   style="background-color: var(--bg-tertiary); color: var(--text-secondary); cursor: not-allowed;"
                                                   disabled readonly>
                                        </div>
                                    </div>

                                    <!-- Occupation Information -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Occupation Details
                                        </label>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Current Occupation</label>
                                                <input type="text" name="occupation" 
                                                       value="{{ old('occupation', $user->metadata['occupation'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="e.g., Software Developer, Teacher, Business Owner">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Employer/Company</label>
                                                <input type="text" name="employer" 
                                                       value="{{ old('employer', $user->metadata['employer'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Name of employer or company">
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Work Address</label>
                                                <input type="text" name="work_address" 
                                                       value="{{ old('work_address', $user->metadata['work_address'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Your work address (optional)">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Rental Preferences -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Rental Preferences
                                            <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(For future rental searches)</span>
                                        </label>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="flex items-center space-x-2 cursor-pointer">
                                                    <input type="checkbox" name="preferences[]" value="pets_allowed"
                                                           {{ ($user->metadata['preferences']['pets_allowed'] ?? false) ? 'checked' : '' }}
                                                           class="form-checkbox">
                                                    <span class="text-sm" style="color: var(--text-secondary);">Interested in pet-friendly properties</span>
                                                </label>
                                            </div>
                                            <div>
                                                <label class="flex items-center space-x-2 cursor-pointer">
                                                    <input type="checkbox" name="preferences[]" value="parking_available"
                                                           {{ ($user->metadata['preferences']['parking_available'] ?? false) ? 'checked' : '' }}
                                                           class="form-checkbox">
                                                    <span class="text-sm" style="color: var(--text-secondary);">Need parking space</span>
                                                </label>
                                            </div>
                                            <div>
                                                <label class="flex items-center space-x-2 cursor-pointer">
                                                    <input type="checkbox" name="preferences[]" value="furnished"
                                                           {{ ($user->metadata['preferences']['furnished'] ?? false) ? 'checked' : '' }}
                                                           class="form-checkbox">
                                                    <span class="text-sm" style="color: var(--text-secondary);">Prefer furnished apartments</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tenant Notes -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Additional Information
                                            <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Any special requirements or notes)</span>
                                        </label>
                                        <textarea name="tenant_notes" rows="3"
                                                  class="form-input"
                                                  placeholder="Any special requirements, medical conditions, or other information...">{{ old('tenant_notes', $user->metadata['tenant_notes'] ?? '') }}</textarea>
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" 
                                            onclick="event.preventDefault(); event.stopPropagation(); showTab('contact');" 
                                            class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" 
                                                onclick="event.preventDefault(); event.stopPropagation(); showTab('emergency');" 
                                                class="btn-outline">
                                            Next: Emergency Contacts <i class="fas fa-arrow-right ml-1"></i>
                                        </button>
                                        <button type="submit" class="btn-warning" id="tenantSubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Emergency Contacts Tab -->
                    <div class="tab-pane hidden" id="emergencyPane" data-tab-pane="emergency">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Emergency Contacts</h3>
                            
                            <form action="{{ route('tenant.profile.emergency.update') }}" method="POST" id="emergencyInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Primary Emergency Contact -->
                                    <div class="p-4 rounded-lg border" 
                                         style="border-color: var(--border-color); background-color: var(--bg-tertiary);">
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                                            <i class="fas fa-user-shield mr-2" style="color: var(--warning);"></i>
                                            Primary Emergency Contact
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Full Name *</label>
                                                <input type="text" name="emergency_contact_name" 
                                                       value="{{ old('emergency_contact_name', $user->metadata['emergency_contact_name'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Full name of contact person"
                                                       required>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Phone Number *</label>
                                                <input type="text" name="emergency_contact_phone" 
                                                       value="{{ old('emergency_contact_phone', $user->metadata['emergency_contact_phone'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Phone number"
                                                       required>
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Relationship *</label>
                                                <select name="emergency_contact_relationship" class="form-input" required>
                                                    <option value="">Select Relationship</option>
                                                    <option value="spouse" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'spouse' ? 'selected' : '' }}>Spouse</option>
                                                    <option value="parent" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'parent' ? 'selected' : '' }}>Parent</option>
                                                    <option value="sibling" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'sibling' ? 'selected' : '' }}>Sibling</option>
                                                    <option value="child" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'child' ? 'selected' : '' }}>Child</option>
                                                    <option value="friend" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'friend' ? 'selected' : '' }}>Friend</option>
                                                    <option value="colleague" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'colleague' ? 'selected' : '' }}>Colleague</option>
                                                    <option value="other" {{ old('emergency_contact_relationship', $user->metadata['emergency_contact_relationship'] ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Address</label>
                                                <input type="text" name="emergency_contact_address" 
                                                       value="{{ old('emergency_contact_address', $user->metadata['emergency_contact_address'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Contact person's address (optional)">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Secondary Emergency Contact -->
                                    <div class="p-4 rounded-lg border" 
                                         style="border-color: var(--border-color); background-color: var(--bg-tertiary);">
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                                            <i class="fas fa-user-friends mr-2" style="color: var(--info);"></i>
                                            Secondary Emergency Contact (Optional)
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Full Name</label>
                                                <input type="text" name="secondary_emergency_name" 
                                                       value="{{ old('secondary_emergency_name', $user->metadata['secondary_emergency_name'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Full name">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Phone Number</label>
                                                <input type="text" name="secondary_emergency_phone" 
                                                       value="{{ old('secondary_emergency_phone', $user->metadata['secondary_emergency_phone'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Phone number">
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Relationship</label>
                                                <select name="secondary_emergency_relationship" class="form-input">
                                                    <option value="">Select Relationship</option>
                                                    <option value="spouse" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'spouse' ? 'selected' : '' }}>Spouse</option>
                                                    <option value="parent" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'parent' ? 'selected' : '' }}>Parent</option>
                                                    <option value="sibling" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'sibling' ? 'selected' : '' }}>Sibling</option>
                                                    <option value="child" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'child' ? 'selected' : '' }}>Child</option>
                                                    <option value="friend" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'friend' ? 'selected' : '' }}>Friend</option>
                                                    <option value="colleague" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'colleague' ? 'selected' : '' }}>Colleague</option>
                                                    <option value="other" {{ old('secondary_emergency_relationship', $user->metadata['secondary_emergency_relationship'] ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Medical Information -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                                            <i class="fas fa-notes-medical mr-2" style="color: var(--danger);"></i>
                                            Medical Information (Optional)
                                        </h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Medical Conditions
                                                    <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Any conditions we should be aware of)</span>
                                                </label>
                                                <textarea name="medical_conditions" rows="2"
                                                          class="form-input"
                                                          placeholder="List any medical conditions, allergies, or special requirements">{{ old('medical_conditions', $user->metadata['medical_conditions'] ?? '') }}</textarea>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Insurance Information
                                                    <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">(Health insurance details)</span>
                                                </label>
                                                <input type="text" name="insurance_info" 
                                                       value="{{ old('insurance_info', $user->metadata['insurance_info'] ?? '') }}" 
                                                       class="form-input"
                                                       placeholder="Insurance provider and policy number (optional)">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" 
                                            onclick="event.preventDefault(); event.stopPropagation(); showTab('tenant');" 
                                            class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" 
                                                onclick="event.preventDefault(); event.stopPropagation(); showTab('password');" 
                                                class="btn-outline">
                                            Next: Security <i class="fas fa-arrow-right ml-1"></i>
                                        </button>
                                        <button type="submit" class="btn-warning" id="emergencySubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Contacts
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Tab -->
                    <div class="tab-pane hidden" id="passwordPane" data-tab-pane="password">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Change Password</h3>
                            
                            <div class="mb-6 p-4 rounded-lg" style="background-color: var(--bg-tertiary); border: 1px solid var(--border-color);">
                                <h4 class="font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-shield-alt mr-2" style="color: var(--warning);"></i>
                                    Password Requirements
                                </h4>
                                <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2" style="color: var(--success);"></i>
                                        At least 8 characters
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2" style="color: var(--success);"></i>
                                        Uppercase and lowercase letters
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2" style="color: var(--success);"></i>
                                        At least one number
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2" style="color: var(--success);"></i>
                                        At least one special character
                                    </li>
                                </ul>
                            </div>
                            
                            <form action="{{ route('tenant.profile.password.update') }}" method="POST" id="passwordFormElement">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Current Password *
                                            <button type="button" onclick="togglePassword('currentPassword')" 
                                                    class="ml-2 text-xs" style="color: var(--warning);">
                                                <i class="fas fa-eye"></i> Show
                                            </button>
                                        </label>
                                        <input type="password" name="current_password" 
                                               class="form-input"
                                               required id="currentPassword">
                                        @error('current_password')
                                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            New Password *
                                            <button type="button" onclick="togglePassword('newPassword')" 
                                                    class="ml-2 text-xs" style="color: var(--warning);">
                                                <i class="fas fa-eye"></i> Show
                                            </button>
                                        </label>
                                        <input type="password" name="password" 
                                               class="form-input"
                                               required id="newPassword"
                                               onkeyup="checkPasswordStrength(this.value)">
                                        <div class="mt-2 hidden" id="passwordStrength">
                                            <div class="flex items-center space-x-2">
                                                <div class="flex-1 h-2 rounded-full overflow-hidden" style="background-color: var(--bg-tertiary);">
                                                    <div class="h-full rounded-full" id="strengthBar"></div>
                                                </div>
                                                <span class="text-xs font-medium" id="strengthText"></span>
                                            </div>
                                        </div>
                                        @error('password')
                                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Confirm New Password *
                                            <button type="button" onclick="togglePassword('confirmPassword')" 
                                                    class="ml-2 text-xs" style="color: var(--warning);">
                                                <i class="fas fa-eye"></i> Show
                                            </button>
                                        </label>
                                        <input type="password" name="password_confirmation" 
                                               class="form-input"
                                               required id="confirmPassword">
                                    </div>

                                    <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                        <button type="button" 
                                                onclick="event.preventDefault(); event.stopPropagation(); showTab('emergency');" 
                                                class="btn-secondary">
                                            <i class="fas fa-arrow-left mr-2"></i>Back
                                        </button>
                                        <button type="submit" class="btn-warning" id="passwordSubmitBtn">
                                            <i class="fas fa-key mr-2"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div><!-- /#tabContent -->
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div id="verificationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 p-4" style="display: none;">
    <div class="rounded-lg shadow-xl max-w-md w-full p-6 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"
         style="background-color: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-shield-alt mr-2" style="color: var(--warning);"></i>
                Verify Phone Number
            </h3>
            <button onclick="closeVerifyModal()" 
                    class="hover:opacity-70 transition" 
                    style="color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <p class="mb-4 text-sm" style="color: var(--text-secondary);">
            Enter the 6-digit verification code sent to your phone.
        </p>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Verification Code</label>
                <input type="text" id="modalVerificationCode" 
                       class="form-input text-center text-lg font-mono"
                       placeholder="123456" maxlength="6">
            </div>
            
            <div class="flex justify-between items-center">
                <button onclick="resendVerificationCode()" class="text-sm hover:underline" style="color: var(--warning);">
                    <i class="fas fa-redo mr-1"></i>Resend Code
                </button>
                <div class="text-xs" style="color: var(--text-secondary);" id="countdown">
                    Resend available in <span id="timer">60</span>s
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 mt-6">
            <button onclick="closeVerifyModal()" class="btn-secondary">Cancel</button>
            <button onclick="submitVerificationCode()" class="btn-warning">Verify</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('styles')
<style>
/* ============================================================
   ✅ THEME-CONSISTENT FORM CONTROLS
   ============================================================ */
.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    outline: none;
    font-family: inherit;
}

.form-input:focus {
    border-color: var(--warning);
    box-shadow: 0 0 0 3px rgba(var(--warning-rgb), 0.15);
}

.form-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.6;
}

.form-input:disabled,
.form-input[readonly] {
    background-color: var(--bg-tertiary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.form-input.error {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.15);
}

/* Checkbox theming */
.form-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    cursor: pointer;
    accent-color: var(--warning);
    transition: all 0.2s ease;
}

.form-checkbox:checked {
    background-color: var(--warning);
    border-color: var(--warning);
}

.form-checkbox:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--warning-rgb), 0.2);
}

/* ============================================================
   TAB STYLES
   ============================================================ */
.tab-button {
    border-bottom: 2px solid transparent;
    color: var(--text-secondary);
    transition: all 0.3s ease;
    cursor: pointer;
    background: transparent;
}

.tab-button:hover {
    color: var(--warning);
}

.tab-button.active-tab {
    border-bottom-color: var(--warning) !important;
    color: var(--warning) !important;
}

/* Tab Content */
.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.hidden {
    display: none;
}

/* ============================================================
   DASHBOARD SWITCHER
   ============================================================ */
.dashboard-switcher {
    position: relative;
}

#profileSwitcherMenu {
    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
    transform-origin: top right;
    opacity: 0;
    transform: translateY(-10px);
    visibility: hidden;
}

#profileSwitcherMenu.show {
    opacity: 1;
    transform: translateY(0);
    visibility: visible;
    display: block !important;
}

.dashboard-switch-option {
    transition: all 0.2s ease;
    cursor: pointer;
}

.dashboard-switch-option:hover {
    background-color: rgba(var(--warning-rgb), 0.08);
    padding-left: 1rem;
}

.active-option {
    background-color: rgba(var(--warning-rgb), 0.1);
    border-left: 3px solid var(--warning);
}

/* ============================================================
   BUTTONS
   ============================================================ */
.btn-primary, .btn-secondary, .btn-danger, .btn-outline,
.btn-warning, .btn-success {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s;
    border: 1px solid transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: inherit;
}

.btn-warning {
    background: linear-gradient(to right, var(--warning), var(--orange));
    color: white;
}

.btn-warning:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success);
    color: white;
}

.btn-success:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border-color: var(--border-color);
}

.btn-secondary:hover {
    background-color: var(--bg-tertiary);
}

.btn-outline {
    background-color: transparent;
    color: var(--warning);
    border-color: var(--warning);
}

.btn-outline:hover {
    background-color: rgba(var(--warning-rgb), 0.1);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
}

.btn-danger:hover {
    opacity: 0.9;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* ============================================================
   QUICK ACTION ROWS
   ============================================================ */
.quick-action-item:hover {
    background-color: rgba(var(--warning-rgb), 0.06);
}

/* ============================================================
   VERIFICATION INPUT
   ============================================================ */
#verificationCode, #modalVerificationCode {
    font-family: 'Courier New', monospace;
    letter-spacing: 2px;
    text-align: center;
}

#verificationCode:focus, #modalVerificationCode:focus {
    border-color: var(--warning);
    box-shadow: 0 0 0 3px rgba(var(--warning-rgb), 0.15);
}

/* ============================================================
   TOAST
   ============================================================ */
.toast {
    animation: slideInRight 0.3s ease-out;
}

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

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 768px) {
    .tab-button {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    
    .card {
        padding: 1rem !important;
    }
}

/* Password strength meter */
#passwordStrength {
    transition: all 0.3s ease;
}

#strengthBar {
    transition: width 0.3s ease, background-color 0.3s ease;
}
</style>
@endpush

@push('scripts')
<script>
// ==================== DASHBOARD SWITCHER FUNCTIONALITY ====================
document.addEventListener('DOMContentLoaded', function() {
    const switcherBtn = document.getElementById('profileSwitcherBtn');
    const switcherMenu = document.getElementById('profileSwitcherMenu');
    const currentRole = '{{ $currentRole }}';
    
    if (switcherBtn && switcherMenu) {
        switcherBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            switcherMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!switcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                switcherMenu.classList.remove('show');
            }
        });
        
        const switchOptions = document.querySelectorAll('#profileSwitcherMenu .dashboard-switch-option');
        switchOptions.forEach(option => {
            option.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const roleSlug = this.dataset.role;
                
                if (roleSlug === currentRole) {
                    showToast('info', `Already on ${roleSlug.replace('-', ' ')} dashboard`);
                    switcherMenu.classList.remove('show');
                    return;
                }
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    showToast('error', 'Security error. Please refresh the page.');
                    return;
                }
                
                const originalHTML = switcherBtn.innerHTML;
                switcherBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
                switcherBtn.disabled = true;
                switcherBtn.style.opacity = '0.7';
                switcherMenu.classList.remove('show');
                
                try {
                    const response = await fetch('/dashboard/switch', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ role: roleSlug })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.redirect_url) {
                        showToast('success', data.message || `Switching to ${roleSlug.replace('-', ' ')} dashboard...`);
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 300);
                    } else {
                        throw new Error(data.message || 'Failed to switch dashboard');
                    }
                } catch (error) {
                    console.error('Switch error:', error);
                    showToast('error', error.message);
                    switcherBtn.innerHTML = originalHTML;
                    switcherBtn.disabled = false;
                    switcherBtn.style.opacity = '1';
                }
            });
        });
    }
});

let countdownInterval = null;
let timerSeconds = 600;

// ==================== TAB FUNCTIONS ====================
const TAB_NAMES = ['personal', 'contact', 'tenant', 'emergency', 'password'];

function initTabs() {
    const tabButtons = document.querySelectorAll('#tabNav .tab-button');

    tabButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const tabName = this.getAttribute('data-tab');
            if (tabName) showTab(tabName);
        });
    });

    const hash = window.location.hash.replace('#', '');
    if (hash && TAB_NAMES.includes(hash)) {
        showTab(hash, false);
    }

    window.addEventListener('hashchange', function () {
        const h = window.location.hash.replace('#', '');
        if (h && TAB_NAMES.includes(h)) {
            showTab(h, false);
        }
    });
}

function showTab(tabName, updateHash = true) {
    if (!TAB_NAMES.includes(tabName)) {
        console.warn('[Profile] Unknown tab:', tabName);
        return;
    }

    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
        pane.classList.add('hidden');
        pane.style.display = 'none';
    });

    document.querySelectorAll('#tabNav .tab-button').forEach(button => {
        button.classList.remove('active-tab');
        button.style.borderBottomColor = 'transparent';
        button.style.color = 'var(--text-secondary)';
    });

    const selectedPane = document.getElementById(tabName + 'Pane');
    if (selectedPane) {
        selectedPane.classList.remove('hidden');
        selectedPane.classList.add('active');
        selectedPane.style.display = 'block';
    }

    const selectedButton = document.querySelector(`#tabNav .tab-button[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active-tab');
        selectedButton.style.borderBottomColor = 'var(--warning)';
        selectedButton.style.color = 'var(--warning)';
    }

    if (updateHash) {
        history.replaceState(null, '', '#' + tabName);
    }

    const tabNav = document.getElementById('tabNav');
    if (tabNav) {
        tabNav.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// ==================== FORM SUBMISSION ====================
function setupForms() {
    const forms = ['personalInfoForm', 'contactInfoForm', 'tenantInfoForm', 'emergencyInfoForm', 'passwordFormElement'];
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this);
            });
        }
    });
}

function submitForm(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return;
    
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    fetch(form.action, {
        method: form.method,
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Changes saved successfully!');
            if (data.profile_completion) updateProfileCompletion(data.profile_completion);
            if (data.user) {
                Object.keys(data.user).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'password') input.value = data.user[key];
                });
            }
            if (data.reload) setTimeout(() => location.reload(), 2000);
        } else {
            showToast('error', data.message || 'Failed to save changes.');
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    const input = form.querySelector(`[name="${field}"]`);
                    if (input) input.classList.add('error');
                });
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// ==================== PHOTO MANAGEMENT ====================
function uploadPhoto(file) {
    const uploadBtn = document.getElementById('uploadBtn');
    if (!uploadBtn) return;
    
    const originalText = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
    
    const uploadProgress = document.getElementById('uploadProgress');
    if (uploadProgress) uploadProgress.classList.remove('hidden');
    
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('_token', '{{ csrf_token() }}');
    
    fetch('{{ route("tenant.profile.photo.update") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Profile photo updated successfully!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('error', data.message || 'Failed to update photo');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while uploading the photo.');
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
        if (uploadProgress) uploadProgress.classList.add('hidden');
    });
}

function removeProfilePhoto() {
    if (!confirm('Are you sure you want to remove your profile photo?')) return;
    
    const removeBtn = document.getElementById('removeBtn');
    if (!removeBtn) return;
    
    const originalText = removeBtn.innerHTML;
    removeBtn.disabled = true;
    removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Removing...';
    
    fetch('{{ route("tenant.profile.photo.remove") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Profile photo removed successfully!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('error', data.message || 'Failed to remove photo');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while removing the photo.');
    })
    .finally(() => {
        removeBtn.disabled = false;
        removeBtn.innerHTML = originalText;
    });
}

// ==================== PHONE VERIFICATION ====================
function sendPhoneVerification() {
    const phoneNumber = document.getElementById('phoneNumber')?.value;
    
    if (!phoneNumber) {
        showToast('error', 'Please enter your phone number first.');
        document.getElementById('phoneNumber')?.focus();
        return;
    }
    
    if (!phoneNumber.match(/^\+?[0-9]{10,15}$/)) {
        showToast('error', 'Please enter a valid phone number with country code (e.g., +233XXXXXXXXX)');
        return;
    }
    
    showToast('info', 'Sending verification code...');
    
    fetch('{{ route("tenant.profile.phone.verify.send") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Verification code sent to your phone!');
            localStorage.setItem('verificationSentAt', Date.now().toString());
            startTimer(timerSeconds);
            
            const verificationSection = document.getElementById('verificationCodeSection');
            if (verificationSection) verificationSection.classList.remove('hidden');
            
            const codeInput = document.getElementById('verificationCode');
            if (codeInput) codeInput.focus();
        } else {
            showToast('error', data.message || 'Failed to send verification code.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    });
}

function verifyPhone() {
    const codeInput = document.getElementById('verificationCode');
    const verificationCode = codeInput?.value;
    
    if (!verificationCode || verificationCode.length !== 6) {
        showToast('error', 'Please enter the 6-digit verification code.');
        codeInput?.focus();
        return;
    }
    
    if (!/^\d{6}$/.test(verificationCode)) {
        showToast('error', 'Please enter a valid 6-digit numeric code.');
        codeInput?.focus();
        return;
    }
    
    showToast('info', 'Verifying code...');
    
    fetch('{{ route("tenant.profile.phone.verify") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ verification_code: verificationCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Phone number verified successfully!');
            
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            
            const verificationSection = document.getElementById('verificationCodeSection');
            if (verificationSection) {
                verificationSection.innerHTML = `
                    <div class="rounded-lg p-3" 
                         style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            <span class="text-sm" style="color: var(--success);">Phone number verified successfully!</span>
                        </div>
                    </div>
                `;
            }
            
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showToast('error', data.message || 'Invalid verification code. Please try again.');
            if (codeInput) {
                codeInput.value = '';
                codeInput.focus();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while verifying. Please try again.');
    });
}

function resendVerificationCode() {
    const resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        resendBtn.disabled = true;
        resendBtn.style.opacity = '0.5';
    }
    
    sendPhoneVerification();
    
    setTimeout(() => {
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.style.opacity = '1';
        }
    }, 30000);
}

function startTimer(seconds) {
    const timerDisplay = document.getElementById('countdownTimer');
    if (!timerDisplay) return;
    
    if (countdownInterval) clearInterval(countdownInterval);
    
    let remaining = seconds;
    
    function updateTimerDisplay() {
        const minutes = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        
        if (remaining <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            timerDisplay.textContent = 'Expired';
        }
    }
    
    updateTimerDisplay();
    countdownInterval = setInterval(() => {
        remaining--;
        updateTimerDisplay();
    }, 1000);
}

// ==================== EMAIL VERIFICATION ====================
function sendEmailVerification() {
    showToast('info', 'Sending verification email...');
    
    fetch('{{ route("tenant.profile.email.verify.send") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Verification email sent! Please check your inbox.');
        } else {
            showToast('error', data.message || 'Failed to send verification email.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    });
}

// ==================== PASSWORD ====================
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
}

function checkPasswordStrength(password) {
    const strengthDiv = document.getElementById('passwordStrength');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    if (!strengthDiv || !strengthBar || !strengthText) return;
    
    if (password.length === 0) {
        strengthDiv.classList.add('hidden');
        return;
    }
    
    strengthDiv.classList.remove('hidden');
    
    let strength = 0;
    const criteria = [
        password.length >= 8,
        password.length >= 12,
        password.match(/[a-z]/),
        password.match(/[A-Z]/),
        password.match(/[0-9]/),
        password.match(/[@$!%*?&]/)
    ];
    
    strength = criteria.filter(Boolean).length;
    
    let color, width, text;
    if (strength <= 2) {
        color = 'var(--danger)';
        width = '33%';
        text = 'Weak';
    } else if (strength <= 4) {
        color = 'var(--warning)';
        width = '66%';
        text = 'Medium';
    } else {
        color = 'var(--success)';
        width = '100%';
        text = 'Strong';
    }
    
    strengthBar.style.width = width;
    strengthBar.style.backgroundColor = color;
    strengthText.textContent = text;
    strengthText.style.color = color;
}

// ==================== DOWNLOAD DATA ====================
function downloadTenantData() {
    showToast('info', 'Preparing your data for download...');
    
    fetch('{{ route("tenant.profile.data.download") }}', {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
    .then(response => {
        if (response.ok) return response.blob();
        throw new Error('Failed to download data');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `tenant_data_{{ $user->id }}_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showToast('success', 'Your data has been downloaded!');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Failed to download data. Please try again.');
    });
}

// ==================== MODAL ====================
function showVerifyModal() {
    const modal = document.getElementById('verificationModal');
    if (modal) modal.style.display = 'block';
}

function closeVerifyModal() {
    const modal = document.getElementById('verificationModal');
    if (modal) modal.style.display = 'none';
}

function submitVerificationCode() {
    const modalCodeInput = document.getElementById('modalVerificationCode');
    const code = modalCodeInput ? modalCodeInput.value : '';
    
    if (!code || code.length !== 6) {
        showToast('error', 'Please enter a valid 6-digit code.');
        return;
    }
    
    const codeField = document.getElementById('verificationCode');
    if (codeField) codeField.value = code;
    
    verifyPhone();
    closeVerifyModal();
}

// ==================== TOAST ====================
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        info: 'var(--info)'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    toast.style.backgroundColor = colors[type] || 'var(--text-secondary)';
    toast.style.color = 'white';
    toast.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300 mb-2';
    toast.innerHTML = `<i class="fas ${icons[type] || 'fa-bell'} mr-2"></i><span>${message}</span>`;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function updateProfileCompletion(percentage) {
    document.querySelectorAll('[data-profile-completion]').forEach(el => {
        el.textContent = `${percentage}%`;
    });
}

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Tenant Profile page loaded - Theme consistent');
    
    initTabs();
    setupForms();
    
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                uploadPhoto(this.files[0]);
            }
        });
    }
    
    @if(!$user->phone_verified_at && $user->phone)
    if (localStorage.getItem('verificationSentAt')) {
        const sentAt = parseInt(localStorage.getItem('verificationSentAt'));
        const elapsed = Math.floor((Date.now() - sentAt) / 1000);
        const remaining = Math.max(0, timerSeconds - elapsed);
        if (remaining > 0) {
            startTimer(remaining);
            const verificationSection = document.getElementById('verificationCodeSection');
            if (verificationSection) verificationSection.classList.remove('hidden');
        }
    }
    @endif
});
</script>
@endpush