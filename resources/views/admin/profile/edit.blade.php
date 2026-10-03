@extends('layouts.app')

@php
    $user = Auth::user();
    
    // ==================== DASHBOARD AWARE LOGIC ====================
    // Detect which dashboard the user came from based on session or referrer
    $sourceDashboard = session('last_dashboard', 'admin');
    $returnRoute = null;
    $returnLabel = 'Dashboard';
    $returnIcon = 'tachometer-alt';
    
    // Check if user has multiple roles via the dual-mode system
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
    
    // Combine roles for multi-role detection
    $allRoleSlugs = $roleBasedRoles->pluck('slug')->toArray();
    if ($legacyRoleSlug && !in_array($legacyRoleSlug, $allRoleSlugs)) {
        $allRoleSlugs[] = $legacyRoleSlug;
    }
    $hasMultipleRoles = count($allRoleSlugs) > 1;
    
    // Get current role from session (set by dashboard switcher)
    $currentRole = session('selected_role', $legacyRoleSlug ?? 'admin');
    
    // Determine return route based on current role and source
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
            $returnRoute = route('admin.dashboard');
            $returnLabel = 'Dashboard';
            $returnIcon = 'tachometer-alt';
    }
    
    // Also check referrer for additional context
    $referrer = url()->previous();
    if (str_contains($referrer, 'super-admin')) {
        $sourceDashboard = 'super-admin';
    } elseif (str_contains($referrer, 'landlord')) {
        $sourceDashboard = 'landlord';
    } elseif (str_contains($referrer, 'tenant')) {
        $sourceDashboard = 'tenant';
    } elseif (str_contains($referrer, 'field-agent')) {
        $sourceDashboard = 'field-agent';
    } elseif (str_contains($referrer, 'security')) {
        $sourceDashboard = 'security';
    } elseif (str_contains($referrer, 'developer')) {
        $sourceDashboard = 'developer';
    }
    
    // Title prefix based on current role
    $titlePrefix = ucfirst(str_replace('-', ' ', $currentRole));
@endphp

@section('title', "Edit Profile - {$titlePrefix}")

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">{{ $titlePrefix }} Profile</h1>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Manage your account and settings</p>
                </div>
                
                <!-- Profile Completion & SMS Status -->
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold" style="color: var(--success);" data-profile-completion>{{ $profileCompletion ?? 0 }}%</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Profile Complete</div>
                    </div>
                    
                    @if($smsStatus['system_ready'] ?? false)
                    <div class="flex items-center px-4 py-2 rounded-full text-sm font-medium" 
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-comment-alt mr-2"></i>
                        SMS: Ready
                    </div>
                    @endif
                    
                    <!-- Dashboard Switcher (if user has multiple roles) -->
                    @if($hasMultipleRoles)
                    <div class="dashboard-switcher relative">
                        <button id="profileSwitcherBtn" 
                                class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all text-sm"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                            <i class="fas fa-exchange-alt mr-1"></i>
                            <span>Switch Dashboard</span>
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
                                        You have {{ count($allRoleSlugs) }} dashboard{{ count($allRoleSlugs) > 1 ? 's' : '' }} available
                                    </p>
                                </div>
                                
                                @php
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
                                        'landlord' => 'Property portfolio',
                                        'tenant' => 'Rental management',
                                        'field-agent' => 'Property registration',
                                        'security-personnel' => 'Security operations',
                                        'developer' => 'System development',
                                    ];
                                    
                                    // Build combined roles list for switcher
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
                                @endphp
                                
                                @foreach($switcherRoles as $role)
                                    @php
                                        $isActive = ($currentRole === $role->slug);
                                        $icon = $roleIcons[$role->slug] ?? 'user';
                                    @endphp
                                    <button type="button"
                                            class="dashboard-switch-option w-full text-left flex items-center gap-3 px-4 py-3 transition-all hover:bg-opacity-10 {{ $isActive ? 'active-option' : '' }}"
                                            data-role="{{ $role->slug }}"
                                            data-current-role="{{ $currentRole }}"
                                            style="display: flex; color: var(--text-primary); {{ $isActive ? 'background-color: rgba(var(--primary-rgb), 0.1); border-left: 3px solid var(--primary);' : '' }}">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center" 
                                             style="background: {{ $isActive ? 'linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%)' : 'rgba(var(--primary-rgb), 0.1)' }}">
                                            <i class="fas fa-{{ $icon }} text-sm" style="color: {{ $isActive ? 'white' : 'var(--primary)' }}"></i>
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
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs
                                        {{ $detail['completed'] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }}">
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
            <!-- Left Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Overview Card -->
                <div class="card p-6">
                    <div class="text-center">
                        <!-- Profile Photo with Lazy Loading -->
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
                                         style="border-color: var(--primary);"
                                         id="profileImage"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTI4IiBoZWlnaHQ9IjEyOCIgdmlld0JveD0iMCAwIDEyOCAxMjgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iNjQiIGN5PSI2NCIgcj0iNjQiIGZpbGw9IiMxMGI5ODEiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjQwIiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPnt7IGluaXRpYWxzIH19PC90ZXh0Pjwvc3ZnPg=='">
                                </div>
                            @else
                                <div class="w-32 h-32 rounded-full flex items-center justify-center font-semibold text-white text-3xl mx-auto shadow-lg"
                                     style="background-color: var(--primary);"
                                     id="avatarPlaceholder">
                                    {{ $initials }}
                                </div>
                            @endif
                            
                            <!-- Photo Upload Progress -->
                            <div id="uploadProgress" class="hidden mt-2">
                                <div class="flex items-center justify-center space-x-2">
                                    <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                                    <span class="text-sm" style="color: var(--text-secondary);">Uploading...</span>
                                </div>
                            </div>
                        </div>

                        <!-- User Info -->
                        <h3 class="font-semibold mb-1" style="color: var(--text-primary);">{{ $user->name ?? 'No Name' }}</h3>
                        <div class="mb-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium mb-2" 
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                {{ $titlePrefix }}
                            </span>
                            @if($hasMultipleRoles)
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium ml-1" 
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-tags mr-1"></i> Multi-Role
                            </span>
                            @endif
                        </div>

                        <!-- Photo Actions -->
                        <div class="space-y-2 mb-4">
                            <form id="updatePhotoForm" enctype="multipart/form-data" class="hidden">
                                @csrf
                                <input type="file" name="photo" id="photoInput" accept="image/*" class="hidden">
                            </form>
                            
                            <button onclick="document.getElementById('photoInput').click()" 
                                    class="btn-primary btn-sm w-full" id="uploadBtn">
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
                    
                    <!-- Photo Tips -->
                    <div class="mt-4 text-xs" style="color: var(--text-secondary);">
                        <p class="flex items-center mb-1">
                            <i class="fas fa-info-circle mr-2"></i>
                            Max size: 5MB
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            Formats: JPEG, PNG, GIF, WebP
                        </p>
                    </div>
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
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-check mr-1"></i>Verified
                                </span>
                            @else
                                <button onclick="sendEmailVerification()" 
                                        class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600 hover:bg-yellow-200">
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
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-check mr-1"></i>Verified
                                </span>
                            @else
                                <div class="flex space-x-1">
                                    @if($user->phone)
                                        <button onclick="sendPhoneVerification()" 
                                                class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600 hover:bg-yellow-200">
                                            <i class="fas fa-sms mr-1"></i>Send
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-500">Add phone first</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Card -->
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
                            <i class="fas fa-calendar text-xl" style="color: var(--primary);"></i>
                        </div>
                        
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-sm" style="color: var(--text-secondary);">Current Role</div>
                                <div class="font-semibold" style="color: var(--text-primary);">
                                    {{ $titlePrefix }}
                                </div>
                            </div>
                            <i class="fas fa-{{ $returnIcon }} text-xl" style="color: var(--info);"></i>
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
                    
                    <!-- Admin Actions Link -->
                    <div class="space-y-2 mt-4">
                        <button onclick="getProfileStats()" 
                               class="btn-outline btn-sm w-full text-center flex items-center justify-center">
                            <i class="fas fa-chart-bar mr-2"></i>View Statistics
                        </button>
                        <a href="{{ route('admin.profile.download-data') }}" 
                           class="btn-outline btn-sm w-full text-center flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i>Download Data
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <!-- Tabs Navigation -->
                <div class="mb-6 border-b" style="border-color: var(--border-color);">
                    <nav class="flex space-x-4 overflow-x-auto" id="tabNav">
                        <button type="button" data-tab="personal" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap active-tab">
                            <i class="fas fa-user mr-2"></i>Personal Info
                        </button>
                        <button type="button" data-tab="contact" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-address-book mr-2"></i>Contact Info
                        </button>
                        <button type="button" data-tab="password" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-key mr-2"></i>Security
                        </button>
                        @if($user->isAdmin() || $user->isSuperAdmin() || $user->hasRole('landlord') || $user->hasRole('field-agent') || $user->hasRole('security-personnel'))
                        <button type="button" data-tab="account" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-cog mr-2"></i>Account
                        </button>
                        @endif
                    </nav>
                </div>

                <!-- Tab Content Container -->
                <div id="tabContent">
                    <!-- Personal Information Form -->
                    <div class="tab-pane active" id="personalPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Personal Information</h3>
                            
                            <form action="{{ route('admin.profile.personal.update') }}" method="POST" id="personalInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Basic Info -->
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Full Name *
                                                <span class="text-xs text-gray-500">(Publicly visible)</span>
                                            </label>
                                            <input type="text" name="name" value="{{ old('name', $user->name) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required>
                                            @error('name')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                        
                                        <!-- Username -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Username
                                                <span class="text-xs text-gray-500">(Optional)</span>
                                            </label>
                                            <input type="text" name="username" 
                                                   value="{{ old('username', $user->username) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="Choose a username">
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Used for login and profile URL
                                            </div>
                                            @error('username')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                        
                                        <!-- Gender -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Gender</label>
                                            <select name="gender" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="">Select Gender</option>
                                                <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                                <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                                <option value="other" {{ old('gender', $user->gender) == 'other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </div>
                                    </div>
                        
                                    <!-- Additional Info -->
                                    <div class="space-y-4">
                                        <!-- Date of Birth -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Date of Birth</label>
                                            <input type="date" name="dob" 
                                                   value="{{ old('dob', $user->dob ? $user->dob->format('Y-m-d') : '') }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   max="{{ date('Y-m-d') }}">
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                We use this to wish you happy birthday!
                                            </div>
                                        </div>
                        
                                        <!-- Region - Text Input -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Region
                                                <span class="text-xs text-gray-500">(e.g., Greater Accra, Ashanti, Central)</span>
                                            </label>
                                            <input type="text" name="region" 
                                                   value="{{ old('region', $user->region) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="Enter your region">
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Your primary operating region
                                            </div>
                                            @error('region')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                        
                                        <!-- Digital Address -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Digital Address
                                                <span class="text-xs text-gray-500">(Optional)</span>
                                            </label>
                                            <input type="text" name="digital_address" 
                                                   value="{{ old('digital_address', $user->digital_address) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="e.g., GA-123-4567">
                                        </div>
                                    </div>
                        
                                    <!-- Location -->
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Location</label>
                                        <input type="text" name="location" 
                                               value="{{ old('location', $user->location) }}" 
                                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               placeholder="Enter your full address">
                                        @error('location')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                        
                                <!-- Form Actions -->
                                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('contact')" class="btn-secondary">
                                        Next: Contact Info
                                    </button>
                                    <button type="submit" class="btn-primary" id="personalSubmitBtn">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contact Information Form -->
                    <div class="tab-pane hidden" id="contactPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Contact Information</h3>
                            
                            <form action="{{ route('admin.profile.contact.update') }}" method="POST" id="contactInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Email -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Email Address *
                                            @if($user->email_verified_at)
                                                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                                    <i class="fas fa-check mr-1"></i>Verified
                                                </span>
                                            @endif
                                        </label>
                                        <div class="flex space-x-2">
                                            <input type="email" name="email" 
                                                   value="{{ old('email', $user->email) }}" 
                                                   class="flex-1 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required>
                                            @if(!$user->email_verified_at)
                                                <button type="button" onclick="sendEmailVerification()" 
                                                        class="px-4 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                                                    Verify
                                                </button>
                                            @endif
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            We'll send important notifications to this address
                                        </div>
                                        @error('email')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Phone with Verification Section -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Phone Number *
                                            @if($user->phone_verified_at)
                                                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                                    <i class="fas fa-check mr-1"></i>Verified
                                                </span>
                                            @endif
                                        </label>
                                        
                                        <div class="space-y-3">
                                            <!-- Phone Number Input -->
                                            <div class="flex space-x-2">
                                                <input type="text" name="phone" id="phoneNumber"
                                                       value="{{ old('phone', $user->phone) }}" 
                                                       class="flex-1 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="+233XXXXXXXXX"
                                                       required>
                                                @if(!$user->phone_verified_at && $user->phone)
                                                    <button type="button" onclick="sendPhoneVerification()" 
                                                            class="px-4 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                                                        Send Code
                                                    </button>
                                                @endif
                                            </div>
                                            
                                            <!-- Verification Section - ONLY SHOWN WHEN UNVERIFIED -->
                                            @if(!$user->phone_verified_at && $user->phone)
                                            <div id="verificationSection" class="border-t pt-3 mt-2" style="border-color: var(--border-color);">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-shield-alt mr-2" style="color: var(--warning);"></i>
                                                    <span class="text-sm font-medium" style="color: var(--text-primary);">Phone Verification Required</span>
                                                </div>
                                                
                                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                                                    <p class="text-xs text-yellow-700">
                                                        <i class="fas fa-info-circle mr-1"></i>
                                                        A verification code will be sent to this number. Please enter it below to verify ownership.
                                                    </p>
                                                </div>
                                                
                                                <!-- Verification Code Input -->
                                                <div class="flex space-x-2">
                                                    <input type="text" id="verificationCode" 
                                                           placeholder="Enter 6-digit verification code"
                                                           class="flex-1 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center text-lg font-mono"
                                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                           maxlength="6"
                                                           pattern="[0-9]{6}">
                                                    <button type="button" onclick="verifyPhone()" 
                                                            class="px-6 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                                                        <i class="fas fa-check-circle mr-1"></i>Verify
                                                    </button>
                                                </div>
                                                
                                                <!-- Resend & Timer -->
                                                <div class="flex justify-between items-center mt-3">
                                                    <button type="button" onclick="resendVerificationCode()" 
                                                            class="text-sm hover:underline flex items-center" 
                                                            style="color: var(--primary);"
                                                            id="resendBtn">
                                                        <i class="fas fa-redo-alt mr-1"></i>
                                                        Resend Code
                                                    </button>
                                                    <div class="text-xs" style="color: var(--text-secondary);" id="timerDisplay">
                                                        Code expires in <span id="countdownTimer">10:00</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Verification Status Messages -->
                                                <div id="verificationStatus" class="mt-2 hidden"></div>
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Used for SMS notifications and password recovery
                                        </div>
                                        @error('phone')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('personal')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" onclick="showTab('password')" class="btn-outline">
                                            Next: Security
                                        </button>
                                        <button type="submit" class="btn-primary" id="contactSubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Change Form -->
                    <div class="tab-pane hidden" id="passwordPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Security Settings</h3>
                            
                            <!-- Password Strength Meter -->
                            <div class="mb-6 p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                                <h4 class="font-medium mb-2" style="color: var(--text-primary);">Password Requirements</h4>
                                <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        At least 8 characters
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        Uppercase and lowercase letters
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        At least one number
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        At least one special character
                                    </li>
                                </ul>
                            </div>
                            
                            <form action="{{ route('admin.profile.password.update') }}" method="POST" id="passwordFormElement">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-4">
                                    <!-- Current Password -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Current Password *
                                            <button type="button" onclick="togglePassword('currentPassword')" 
                                                    class="ml-2 text-xs" style="color: var(--primary);">
                                                <i class="fas fa-eye"></i> Show
                                            </button>
                                        </label>
                                        <div class="relative">
                                            <input type="password" name="current_password" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition pr-10"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required id="currentPassword">
                                        </div>
                                        @error('current_password')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- New Password -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            New Password *
                                            <button type="button" onclick="togglePassword('newPassword')" 
                                                    class="ml-2 text-xs" style="color: var(--primary);">
                                                <i class="fas fa-eye"></i> Show
                                            </button>
                                        </label>
                                        <div class="relative">
                                            <input type="password" name="password" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition pr-10"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required id="newPassword"
                                                   onkeyup="checkPasswordStrength(this.value)">
                                            <!-- Password Strength Meter -->
                                            <div class="mt-2 hidden" id="passwordStrength">
                                                <div class="flex items-center space-x-2">
                                                    <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                        <div class="h-full rounded-full" id="strengthBar"></div>
                                                    </div>
                                                    <span class="text-xs font-medium" id="strengthText"></span>
                                                </div>
                                            </div>
                                        </div>
                                        @error('password')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Confirm Password -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Confirm New Password *
                                            <button type="button" onclick="togglePassword('confirmPassword')" 
                                                    class="ml-2 text-xs" style="color: var(--primary);">
                                                <i class="fas fa-eye"></i> Show
                                            </button>
                                        </label>
                                        <input type="password" name="password_confirmation" 
                                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               required id="confirmPassword">
                                    </div>

                                    <!-- Form Actions -->
                                    <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                        <button type="button" onclick="showTab('contact')" class="btn-secondary">
                                            <i class="fas fa-arrow-left mr-2"></i>Back
                                        </button>
                                        <div class="space-x-3">
                                            @if($user->isAdmin() || $user->isSuperAdmin() || $user->hasRole('landlord') || $user->hasRole('field-agent') || $user->hasRole('security-personnel'))
                                            <button type="button" onclick="showTab('account')" class="btn-outline">
                                                Next: Account
                                            </button>
                                            @endif
                                            <button type="submit" class="btn-primary" id="passwordSubmitBtn">
                                                <i class="fas fa-key mr-2"></i>Change Password
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Account Settings Form (For all role types) -->
                    @if($user->isAdmin() || $user->isSuperAdmin() || $user->hasRole('landlord') || $user->hasRole('field-agent') || $user->hasRole('security-personnel'))
                    <div class="tab-pane hidden" id="accountPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Account Settings</h3>
                            
                            <div class="space-y-6">
                                <!-- Account Information -->
                                <div>
                                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">Account Details</h4>
                                    <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">User Type</label>
                                                <div class="flex items-center flex-wrap gap-2">
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        {{ $titlePrefix }}
                                                    </span>
                                                    @if($user->isSuperAdmin())
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-crown mr-1"></i>Super Admin
                                                    </span>
                                                    @endif
                                                    @if($user->hasRole('landlord'))
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-home mr-1"></i>Landlord
                                                    </span>
                                                    @endif
                                                    @if($user->hasRole('field-agent'))
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-user-check mr-1"></i>Field Agent
                                                    </span>
                                                    @endif
                                                    @if($user->hasRole('security-personnel'))
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                                          style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                        <i class="fas fa-shield-alt mr-1"></i>Security
                                                    </span>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Account Status</label>
                                                <div>
                                                    @php
                                                        $statusColors = [
                                                            'active' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)'],
                                                            'pending' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)'],
                                                            'suspended' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)'],
                                                            'inactive' => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)'],
                                                        ];
                                                        $status = $user->status ?? 'active';
                                                        $colors = $statusColors[$status] ?? $statusColors['active'];
                                                    @endphp
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                                          style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
                                                        <i class="fas fa-circle text-xs mr-1"></i>{{ ucfirst($status) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Account Stats -->
                                <div>
                                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">Account Activity</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div class="text-center p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                                            <div class="text-xl font-bold mb-1" style="color: var(--text-primary);">
                                                {{ $profileStats['account_age_days'] ?? 0 }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Days Active</div>
                                        </div>
                                        
                                        <div class="text-center p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                                            <div class="text-xl font-bold mb-1" style="color: var(--text-primary);">
                                                {{ $user->last_login_at ? 'Recent' : 'Never' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Last Login</div>
                                        </div>
                                        
                                        <div class="text-center p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                                            <div class="text-xl font-bold mb-1" style="color: var(--text-primary);">
                                                {{ $user->created_at->format('M Y') }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Joined</div>
                                        </div>
                                        
                                        <div class="text-center p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                                            <div class="text-xl font-bold mb-1" style="color: var(--text-primary);">
                                                {{ $profileCompletion ?? 0 }}%
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Profile</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Role Information (for multi-role users) -->
                                @if($hasMultipleRoles)
                                <div>
                                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">Your Roles</h4>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($switcherRoles as $role)
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium
                                                @if($role->slug === 'landlord') bg-green-100 text-green-800
                                                @elseif($role->slug === 'admin') bg-blue-100 text-blue-800
                                                @elseif($role->slug === 'super-admin') bg-purple-100 text-purple-800
                                                @elseif($role->slug === 'field-agent') bg-cyan-100 text-cyan-800
                                                @elseif($role->slug === 'security-personnel') bg-orange-100 text-orange-800
                                                @elseif($role->slug === 'tenant') bg-yellow-100 text-yellow-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                <i class="fas fa-{{ $roleIcons[$role->slug] ?? 'user' }} mr-1"></i>
                                                {{ ucfirst(str_replace('-', ' ', $role->slug)) }}
                                                @if($role->slug === $currentRole)
                                                    <span class="ml-1 text-xs opacity-70">(current)</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Use the dashboard switcher above to access different dashboards.
                                    </p>
                                </div>
                                @endif

                                <!-- Quick Actions -->
                                <div class="flex space-x-3 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('password')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <a href="{{ $returnRoute }}" 
                                       class="btn-primary ml-auto">
                                        <i class="fas fa-{{ $returnIcon }} mr-2"></i>Go to {{ $returnLabel }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('styles')
<style>
/* Tab Styles */
.tab-button {
    border-bottom: 2px solid transparent;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.tab-button:hover {
    color: var(--primary);
}

.tab-button.active-tab {
    border-bottom-color: var(--primary) !important;
    color: var(--primary) !important;
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

/* Hidden utility */
.hidden {
    display: none !important;
}

/* Button styles */
.btn-primary, .btn-secondary, .btn-danger, .btn-outline {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s;
    border: 1px solid transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(to right, var(--primary), var(--secondary));
    color: white;
}

.btn-primary:hover {
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
    color: var(--primary);
    border-color: var(--primary);
}

.btn-outline:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
}

.btn-danger:hover {
    background-color: #dc2626;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Dashboard Switcher Styles */
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
    background-color: rgba(var(--primary-rgb), 0.1);
    padding-left: 1rem;
}

.active-option {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-left: 3px solid var(--primary);
}

/* Toast animation */
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

/* Spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .tab-button {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    
    .card {
        padding: 1rem !important;
    }
}

/* Phone verification input styling */
#verificationCode {
    font-family: 'Courier New', monospace;
    letter-spacing: 2px;
    text-align: center;
}

#verificationCode:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}
</style>
@endpush

@push('scripts')
<script>
// ==================== DASHBOARD SWITCHER FUNCTIONALITY ====================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard switcher for profile page
    const switcherBtn = document.getElementById('profileSwitcherBtn');
    const switcherMenu = document.getElementById('profileSwitcherMenu');
    const currentRole = '{{ $currentRole }}';
    
    if (switcherBtn && switcherMenu) {
        // Toggle dropdown
        switcherBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            switcherMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!switcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
                switcherMenu.classList.remove('show');
            }
        });
        
        // Handle dashboard switching
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
                
                // Show loading state
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

// Timer variables
let countdownInterval = null;
let timerSeconds = 600; // 10 minutes = 600 seconds

// Tab Functions
function initTabs() {
    const tabButtons = document.querySelectorAll('#tabNav .tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            showTab(tabName);
        });
    });
}

function showTab(tabName) {
    const tabPanes = document.querySelectorAll('.tab-pane');
    tabPanes.forEach(pane => {
        pane.classList.remove('active');
        pane.classList.add('hidden');
    });
    
    const tabButtons = document.querySelectorAll('#tabNav .tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active-tab');
        button.style.borderColor = 'transparent';
        button.style.color = 'var(--text-secondary)';
    });
    
    const selectedPane = document.getElementById(tabName + 'Pane');
    if (selectedPane) {
        selectedPane.classList.remove('hidden');
        selectedPane.classList.add('active');
    }
    
    const selectedButton = document.querySelector(`#tabNav .tab-button[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active-tab');
        selectedButton.style.borderColor = 'var(--primary)';
        selectedButton.style.color = 'var(--primary)';
    }
    
    window.location.hash = tabName;
}

// Form Submission Handling
function setupForms() {
    const forms = ['personalInfoForm', 'contactInfoForm', 'passwordFormElement'];
    
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    fetch(form.action, {
        method: form.method,
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Changes saved successfully!');
            if (data.profile_completion) {
                updateProfileCompletion(data.profile_completion);
            }
            if (data.reload) {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            showToast('error', data.message || 'Failed to save changes.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while saving.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Photo Upload Functions
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
    
    fetch('{{ route("admin.profile.photo.update") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Profile photo updated successfully!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('error', data.message || 'Failed to upload photo');
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
    
    fetch('{{ route("admin.profile.photo.remove") }}', {
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

// Phone Verification Functions
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
    
    fetch('{{ route("admin.profile.send-verification") }}', {
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
            
            const codeInput = document.getElementById('verificationCode');
            if (codeInput) {
                codeInput.focus();
            }
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
    
    fetch('{{ route("admin.profile.verify-phone") }}', {
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
            
            const verificationSection = document.getElementById('verificationSection');
            if (verificationSection) {
                verificationSection.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span class="text-sm text-green-700">Phone number verified successfully!</span>
                        </div>
                    </div>
                `;
            }
            
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showToast('error', data.message || 'Invalid verification code. Please try again.');
            codeInput.value = '';
            codeInput.focus();
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
    
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    
    let remaining = seconds;
    
    function updateTimerDisplay() {
        const minutes = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        
        if (remaining <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            timerDisplay.textContent = 'Expired';
            
            const verificationStatus = document.getElementById('verificationStatus');
            if (verificationStatus) {
                verificationStatus.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-2 mt-2">
                        <p class="text-xs text-red-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Code expired. Please request a new code.
                        </p>
                    </div>
                `;
                verificationStatus.classList.remove('hidden');
            }
        }
    }
    
    updateTimerDisplay();
    countdownInterval = setInterval(() => {
        remaining--;
        updateTimerDisplay();
    }, 1000);
}

// Email Verification
function sendEmailVerification() {
    showToast('info', 'Sending verification email...');
    
    fetch('{{ route("admin.profile.send-email-verification") }}', {
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

// Password Functions
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
        password.match(/[a-z]/) && password.match(/[A-Z]/),
        password.match(/[0-9]/),
        password.match(/[@$!%*?&]/)
    ];
    
    strength = criteria.filter(Boolean).length;
    
    const strengths = {
        1: { text: 'Weak', color: '#ef4444', width: '25%' },
        2: { text: 'Fair', color: '#f59e0b', width: '50%' },
        3: { text: 'Good', color: '#3b82f6', width: '75%' },
        4: { text: 'Strong', color: '#10b981', width: '100%' }
    };
    
    const result = strengths[strength] || strengths[1];
    strengthText.textContent = result.text;
    strengthText.style.color = result.color;
    strengthBar.style.width = result.width;
    strengthBar.style.backgroundColor = result.color;
}

// Utility Functions
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    toast.className = `${colors[type] || 'bg-gray-500'} text-white px-4 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300`;
    toast.innerHTML = `
        <i class="fas ${icons[type] || 'fa-bell'} mr-2"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function updateProfileCompletion(percentage) {
    const completionElements = document.querySelectorAll('[data-profile-completion]');
    completionElements.forEach(el => {
        el.textContent = `${percentage}%`;
    });
}

function getProfileStats() {
    showToast('info', 'Loading statistics...');
    
    fetch('{{ route("admin.profile.stats") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Profile statistics loaded (check console)');
                console.log('Profile Stats:', data.stats);
            }
        })
        .catch(error => console.error('Error:', error));
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('Profile edit page loaded - Dashboard aware');
    
    initTabs();
    
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                uploadPhoto(this.files[0]);
            }
        });
    }
    
    setupForms();
    
    @if(!$user->phone_verified_at && $user->phone)
    if (localStorage.getItem('verificationSentAt')) {
        const sentAt = parseInt(localStorage.getItem('verificationSentAt'));
        const elapsed = Math.floor((Date.now() - sentAt) / 1000);
        const remaining = Math.max(0, timerSeconds - elapsed);
        if (remaining > 0) {
            startTimer(remaining);
        }
    }
    @endif
});
</script>
@endpush