{{-- developer/super-admins/show.blade.php --}}
@php
    $routePrefix = 'developer.super-admins';
    $pageTitle = 'Super Admin Details - Developer Portal';
    
    $user = $user ?? null;
    $userData = $userData ?? $user;
    $emailConfigStatus = $emailConfigStatus ?? [];
    $invitationStats = $invitationStats ?? [];
    $recentActivities = $recentActivities ?? [];
    
    // Check if developer email is configured
    $developerEmailConfigured = $emailConfigStatus['can_send'] ?? false;
    
    // Role management variables (passed from controller)
    $hasLandlordRole = $hasLandlordRole ?? ($user ? $user->hasRole('landlord') : false);
    $hasAdminRole = $hasAdminRole ?? ($user ? $user->hasRole('admin') : false);
    $propertyCount = $propertyCount ?? ($user ? $user->properties()->count() : 0);
    $isPropertyOwner = $isPropertyOwner ?? ($propertyCount > 0);
    $availableRoles = $availableRoles ?? collect();
    
    if (!$user) {
        abort(404, 'Super Admin not found');
    }
@endphp

@extends('layouts.dev')

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
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i> 
                        {{ $user->name }}
                        <span class="ml-2 status-indicator status-{{ $user->status }}">
                            {{ $user->display_status['label'] ?? $user->status }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Super Admin account details and management</span>
                        @if($user->email_verified_at)
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1 text-green-500"></i>
                        <span class="text-green-600 font-medium">Email Verified</span>
                        @endif
                        @if($user->phone_verified_at)
                        <span class="mx-2">•</span>
                        <i class="fas fa-phone mr-1 text-green-500"></i>
                        <span class="text-green-600 font-medium">Phone Verified</span>
                        @endif
                        @if($developerEmailConfigured)
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1 text-green-500"></i>
                        <span class="text-green-600 font-medium">Email Ready</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route('developer.dashboard') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> Dashboard
                </a>
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

    <!-- Navigation Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <a href="{{ route('developer.super-admins.index') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Super Admins List
                </a>
                
                <div class="flex items-center space-x-3">
                    <a href="{{ route('developer.super-admins.edit', $user->id) }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-edit mr-2"></i> Edit Super Admin
                    </a>
                    
                    <span class="text-xs px-3 py-1 rounded-full" 
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-shield-alt mr-1"></i> Developer Access
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Configuration Alert -->
    @if(!$developerEmailConfigured)
    <div class="card border-l-4 mb-6" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold mb-1 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2"></i> Email Configuration Required
                    </h4>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        Email invitations cannot be sent until email configuration is completed.
                    </p>
                    <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" 
                       class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white btn-warning">
                        <i class="fas fa-cog mr-2"></i> Setup Email Now
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Account Status Card -->
        <div class="card stat-card users-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-user-check text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Account Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ ucfirst($user->status) }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium status-indicator status-{{ $user->status }}">
                        {{ $user->display_status['label'] ?? $user->status }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Email Verification Card -->
        <div class="card stat-card revenue-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-envelope text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Email Verification</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $user->email_verified_at ? 'Verified' : 'Pending' }}
                    </p>
                </div>
                <div class="text-right">
                    @if($user->email_verified_at)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                        <i class="fas fa-check-circle mr-1"></i> Verified
                    </span>
                    @else
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                        <i class="fas fa-clock mr-1"></i> Pending
                    </span>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Phone Verification Card -->
        <div class="card stat-card conversion-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-phone text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Phone Verification</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $user->phone_verified_at ? 'Verified' : 'Pending' }}
                    </p>
                </div>
                <div class="text-right">
                    @if($user->phone_verified_at)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                        <i class="fas fa-check-circle mr-1"></i> Verified
                    </span>
                    @else
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                        <i class="fas fa-clock mr-1"></i> Pending
                    </span>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Invitations Card -->
        <div class="card stat-card users-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-paper-plane text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Invitations</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $invitationStats['total'] ?? 0 }}
                    </p>
                </div>
                <div class="text-right">
                    @if(($invitationStats['total'] ?? 0) > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                        <i class="fas fa-history mr-1"></i>
                        {{ $invitationStats['accepted'] ?? 0 }} accepted
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="card border-l-4" style="border-left-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.05);">
        <div class="p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Change Password -->
                    <a href="{{ route('developer.super-admins.change-password', $user->id) }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-warning">
                        <i class="fas fa-key mr-2"></i> Change Password
                    </a>
                    
                    <!-- Send Invitation -->
                    @if($user->canReceiveInvitation())
                    <button onclick="sendInvitation({{ $user->id }})"
                            class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-primary {{ $developerEmailConfigured ? '' : 'opacity-50 cursor-not-allowed' }}"
                            {{ $developerEmailConfigured ? '' : 'disabled' }}>
                        <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                    </button>
                    @endif
                    
                    <!-- Status Actions -->
                    @if($user->status === \App\Models\User::STATUS_ACTIVE)
                    <form method="POST" action="{{ route('developer.super-admins.suspend', $user->id) }}" class="inline" 
                          onsubmit="return confirm('Are you sure you want to suspend this Super Admin?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-danger">
                            <i class="fas fa-ban mr-2"></i> Suspend
                        </button>
                    </form>
                    @elseif($user->status === \App\Models\User::STATUS_SUSPENDED)
                    <form method="POST" action="{{ route('developer.super-admins.activate', $user->id) }}" class="inline"
                          onsubmit="return confirm('Are you sure you want to activate this Super Admin?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-success">
                            <i class="fas fa-check-circle mr-2"></i> Activate
                        </button>
                    </form>
                    @elseif($user->status === \App\Models\User::STATUS_INACTIVE || $user->status === \App\Models\User::STATUS_PENDING)
                    <form method="POST" action="{{ route('developer.super-admins.activate', $user->id) }}" class="inline"
                          onsubmit="return confirm('Are you sure you want to activate this Super Admin?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-success">
                            <i class="fas fa-toggle-on mr-2"></i> Activate
                        </button>
                    </form>
                    @endif
                    
                    <!-- Verification Actions -->
                    @if(!$user->phone_verified_at)
                    <form method="POST" action="{{ route('developer.super-admins.force-verify-phone', $user->id) }}" class="inline"
                          onsubmit="return confirm('Are you sure you want to force verify phone number?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-info">
                            <i class="fas fa-phone-volume mr-2"></i> Verify Phone
                        </button>
                    </form>
                    @endif
                    
                    @if(!$user->email_verified_at)
                    <form method="POST" action="{{ route('developer.super-admins.force-verify-email', $user->id) }}" class="inline"
                          onsubmit="return confirm('Are you sure you want to force verify email?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-info">
                            <i class="fas fa-envelope-open mr-2"></i> Verify Email
                        </button>
                    </form>
                    @endif
                </div>
                
                <div class="flex items-center space-x-3">
                    <!-- Delete Button -->
                    <button onclick="checkRelations({{ $user->id }})"
                            class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-danger">
                        <i class="fas fa-trash-alt mr-2"></i> Delete
                    </button>
                    
                    <!-- More Actions -->
                    <div class="relative">
                        <button onclick="toggleMoreActions()"
                                class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-secondary">
                            <i class="fas fa-ellipsis-v mr-2"></i> More
                        </button>
                        <div id="moreActionsMenu" class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg z-10 hidden"
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="py-1">
                                <a href="{{ route('developer.super-admins.activities', $user->id) }}" 
                                   class="block px-4 py-2 text-sm hover:bg-gray-100"
                                   style="color: var(--text-primary);">
                                    <i class="fas fa-history mr-2"></i> View Activities
                                </a>
                                <a href="{{ route('developer.super-admins.invitation-history', $user->id) }}" 
                                   class="block px-4 py-2 text-sm hover:bg-gray-100"
                                   style="color: var(--text-primary);">
                                    <i class="fas fa-envelope mr-2"></i> Invitation History
                                </a>
                                <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                                @if($user->phone_verified_at)
                                <form method="POST" action="{{ route('developer.super-admins.remove-phone-verification', $user->id) }}" 
                                      class="inline" onsubmit="return confirm('Remove phone verification?')">
                                    @csrf
                                    <button type="submit"
                                            class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100"
                                            style="color: var(--text-primary);">
                                        <i class="fas fa-phone-slash mr-2"></i> Remove Phone Verification
                                    </button>
                                </form>
                                @endif
                                @if($user->email_verified_at)
                                <form method="POST" action="{{ route('developer.super-admins.remove-email-verification', $user->id) }}" 
                                      class="inline" onsubmit="return confirm('Remove email verification?')">
                                    @csrf
                                    <button type="submit"
                                            class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100"
                                            style="color: var(--text-primary);">
                                        <i class="fas fa-envelope mr-2"></i> Remove Email Verification
                                    </button>
                                </form>
                                @endif
                                @if($user->status !== \App\Models\User::STATUS_INACTIVE)
                                <form method="POST" action="{{ route('developer.super-admins.deactivate', $user->id) }}" 
                                      class="inline" onsubmit="return confirm('Deactivate this Super Admin?')">
                                    @csrf
                                    <button type="submit"
                                            class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100"
                                            style="color: var(--text-primary);">
                                        <i class="fas fa-toggle-off mr-2"></i> Deactivate
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== ROLE MANAGEMENT SECTION (FOR SUPER ADMIN VIEWING OWN ACCOUNT) ==================== -->
    @if(auth()->user()->isSuperAdmin() && $user->id === auth()->id())
    <div class="card border-l-4" style="border-left-color: var(--success);">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-tag mr-2" style="color: var(--success);"></i> 
                Role Management
            </h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                As a Super Admin, you can grant yourself additional roles to access other parts of the system.
                <br>Current roles: <strong>{{ $user->roles->pluck('name')->implode(', ') ?: 'Super Admin (default)' }}</strong>
            </p>
            
            <form id="roleManagementForm" method="POST" action="{{ route('super-admin.update-own-roles', $user->id) }}">
                @csrf
                @method('PUT')
                
                <div class="space-y-3">
                    <!-- Landlord Role -->
                    <div class="flex items-center justify-between p-4 rounded-lg" 
                         style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div>
                            <div class="flex items-center">
                                <i class="fas fa-home mr-3 text-lg" style="color: var(--success);"></i>
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">Landlord Role</span>
                                    @if($hasLandlordRole)
                                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Currently Assigned
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <p class="text-xs mt-1 ml-7" style="color: var(--text-secondary);">
                                Allows you to own and manage properties
                            </p>
                            @if($propertyCount > 0)
                                <p class="text-xs mt-1 ml-7" style="color: var(--info);">
                                    <i class="fas fa-info-circle mr-1"></i> You currently own {{ $propertyCount }} property(s)
                                </p>
                            @endif
                        </div>
                        <div>
                            @if($hasLandlordRole)
                                <button type="button" 
                                        onclick="removeOwnRole('landlord')"
                                        class="px-3 py-1.5 rounded text-sm font-medium text-red-600 hover:bg-red-50 transition"
                                        {{ $propertyCount > 0 ? 'disabled' : '' }}
                                        style="{{ $propertyCount > 0 ? 'opacity:50; cursor:not-allowed;' : '' }}">
                                    <i class="fas fa-trash mr-1"></i> Remove Role
                                </button>
                            @else
                                <button type="button" 
                                        onclick="addOwnRole('landlord')"
                                        class="px-3 py-1.5 rounded text-sm font-medium text-green-600 hover:bg-green-50 transition">
                                    <i class="fas fa-plus mr-1"></i> Add Landlord Role
                                </button>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Admin Role (Optional - if you want Super Admin to also have admin role) -->
                    <div class="flex items-center justify-between p-4 rounded-lg" 
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div>
                            <div class="flex items-center">
                                <i class="fas fa-user-shield mr-3 text-lg" style="color: var(--info);"></i>
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">Admin Role</span>
                                    @if($hasAdminRole)
                                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                                            <i class="fas fa-check-circle mr-1"></i> Currently Assigned
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <p class="text-xs mt-1 ml-7" style="color: var(--text-secondary);">
                                Grants access to admin panel and user management
                            </p>
                        </div>
                        <div>
                            @if($hasAdminRole)
                                <button type="button" 
                                        onclick="removeOwnRole('admin')"
                                        class="px-3 py-1.5 rounded text-sm font-medium text-red-600 hover:bg-red-50 transition">
                                    <i class="fas fa-trash mr-1"></i> Remove Role
                                </button>
                            @else
                                <button type="button" 
                                        onclick="addOwnRole('admin')"
                                        class="px-3 py-1.5 rounded text-sm font-medium text-green-600 hover:bg-green-50 transition">
                                    <i class="fas fa-plus mr-1"></i> Add Admin Role
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="role_action" id="roleAction" value="">
                <input type="hidden" name="role_slug" id="roleSlug" value="">
            </form>
            
            @if($propertyCount > 0 && $hasLandlordRole)
            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <p class="text-sm" style="color: var(--warning);">
                    <i class="fas fa-info-circle mr-2"></i>
                    You own {{ $propertyCount }} property(s). The Landlord role cannot be removed while you own properties.
                    To remove this role, please transfer your properties to another owner first.
                </p>
            </div>
            @endif
            
            @if(!$hasLandlordRole && $propertyCount > 0)
            <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                <p class="text-sm" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    You own {{ $propertyCount }} property(s) but do not have the Landlord role. 
                    Please add the Landlord role above to manage your properties properly.
                </p>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Main Content Area -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: User Details -->
        <div class="lg:col-span-2">
            <!-- Personal Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-id-card mr-2" style="color: var(--primary);"></i> 
                    Personal Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user mr-1"></i> Full Name
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">{{ $user->name }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-envelope mr-1"></i> Email Address
                            </label>
                            <div class="flex items-center">
                                <p class="text-sm" style="color: var(--text-primary);">{{ $user->email }}</p>
                                @if($user->email_verified_at)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                @else
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-warning">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-phone mr-1"></i> Phone Number
                            </label>
                            <div class="flex items-center">
                                <p class="text-sm" style="color: var(--text-primary);">{{ $user->local_phone ?? $user->phone }}</p>
                                @if($user->phone_verified_at)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                @else
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-warning">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user-tag mr-1"></i> Username
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">
                                {{ $user->username ?? 'Not set' }}
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-venus-mars mr-1"></i> Gender
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">
                                {{ ucfirst($user->gender) ?? 'Not specified' }}
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-alt mr-1"></i> Account Created
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">
                                {{ $user->created_at->format('F j, Y \a\t g:i A') }}
                                <span class="text-xs" style="color: var(--text-secondary);">({{ $user->age }})</span>
                            </p>
                        </div>
                    </div>
                </div>
                
                @if($user->digital_address || $user->region || $user->location)
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="text-md font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marker-alt mr-2" style="color: var(--info);"></i> 
                        Location Information
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if($user->digital_address)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                Digital Address
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">{{ $user->digital_address }}</p>
                        </div>
                        @endif
                        
                        @if($user->region)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                Region
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">{{ $user->region }}</p>
                        </div>
                        @endif
                        
                        @if($user->location)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                Location
                            </label>
                            <p class="text-sm" style="color: var(--text-primary);">{{ $user->location }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Recent Activities -->
            @if(!empty($recentActivities))
            <div class="card p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> 
                        Recent Activities
                    </h3>
                    <a href="{{ route('developer.super-admins.activities', $user->id) }}" 
                       class="text-sm font-medium" 
                       style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="space-y-3">
                    @foreach($recentActivities as $activity)
                    <div class="flex items-start p-3 rounded-lg" 
                         style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                        <div class="flex-shrink-0 mr-3 mt-1">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-history text-sm" style="color: var(--primary);"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm" style="color: var(--text-primary);">{{ $activity['description'] }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $activity['time_ago'] }} • {{ $activity['created_at'] }}
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        
        <!-- Right Column: Account Information -->
        <div class="space-y-6">
            <!-- Current Roles Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-tags mr-2" style="color: var(--primary);"></i> 
                    Current Roles
                </h3>
                
                <div class="flex flex-wrap gap-2">
                    <!-- Super Admin role (always present) -->
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium"
                          style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <i class="fas fa-crown mr-2"></i> Super Admin
                    </span>
                    
                    <!-- Landlord role if assigned -->
                    @if($hasLandlordRole)
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium"
                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-home mr-2"></i> Landlord
                    </span>
                    @endif
                    
                    <!-- Admin role if assigned -->
                    @if($hasAdminRole)
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium"
                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-user-shield mr-2"></i> Admin
                    </span>
                    @endif
                    
                    @if(!$hasLandlordRole && !$hasAdminRole)
                    <p class="text-sm" style="color: var(--text-secondary);">No additional roles assigned</p>
                    @endif
                </div>
                
                @if($propertyCount > 0)
                <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                    <div class="flex items-center">
                        <i class="fas fa-building mr-2 text-purple-500"></i>
                        <span class="text-sm" style="color: var(--text-primary);">Properties owned: </span>
                        <span class="ml-2 font-bold" style="color: var(--text-primary);">{{ $propertyCount }}</span>
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Account Status Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> 
                    Account Status
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                            Status
                        </label>
                        <div class="flex items-center">
                            <span class="status-indicator status-{{ $user->status }}">
                                {{ $user->display_status['label'] ?? $user->status }}
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                            Last Login
                        </label>
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ $user->last_active ?? 'Never logged in' }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                            Created By
                        </label>
                        <p class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-user-tie mr-1"></i> {{ $user->creator_name }}
                        </p>
                    </div>
                    
                    @if($user->last_invitation_sent_at)
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                            Last Invitation Sent
                        </label>
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ \Carbon\Carbon::parse($user->last_invitation_sent_at)->format('F j, Y \a\t g:i A') }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Invitation Statistics Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--primary);"></i> 
                    Invitation Statistics
                </h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 rounded-lg" 
                         style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $invitationStats['total'] ?? 0 }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Total Sent</p>
                    </div>
                    
                    <div class="text-center p-3 rounded-lg" 
                         style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $invitationStats['accepted'] ?? 0 }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Accepted</p>
                    </div>
                    
                    <div class="text-center p-3 rounded-lg" 
                         style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $invitationStats['expired'] ?? 0 }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Expired</p>
                    </div>
                    
                    <div class="text-center p-3 rounded-lg" 
                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $invitationStats['failed'] ?? 0 }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Failed</p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="{{ route('developer.super-admins.invitation-history', $user->id) }}" 
                       class="block text-center w-full px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                        <i class="fas fa-history mr-2"></i> View Invitation History
                    </a>
                </div>
            </div>
            
            <!-- Email Service Status Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-2" style="color: var(--info);"></i> 
                    Email Service Status
                </h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-lg" 
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            @if($developerEmailConfigured)
                                <i class="fas fa-check-circle mr-3 text-green-500"></i>
                                <span style="color: var(--text-primary);">
                                    Email Invitations
                                </span>
                            @else
                                <i class="fas fa-times-circle mr-3 text-red-500"></i>
                                <span style="color: var(--text-primary);">
                                    Email Invitations
                                </span>
                            @endif
                        </div>
                        <div>
                            @if($developerEmailConfigured)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                <i class="fas fa-check mr-1"></i> Available
                            </span>
                            @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-danger">
                                <i class="fas fa-times mr-1"></i> Unavailable
                            </span>
                            @endif
                        </div>
                    </div>
                    
                    @if(!$developerEmailConfigured)
                    <div class="mt-4">
                        <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" 
                           class="block text-center w-full px-3 py-2 rounded-lg text-sm font-medium btn-warning">
                            <i class="fas fa-cog mr-2"></i> Setup Email System
                        </a>
                    </div>
                    @endif
                    
                    @if($user->canReceiveInvitation() && $developerEmailConfigured)
                    <div class="mt-4">
                        <button onclick="sendInvitation({{ $user->id }})"
                                class="block text-center w-full px-3 py-2 rounded-lg text-sm font-medium text-white btn-primary">
                            <i class="fas fa-paper-plane mr-2"></i> Send Invitation Now
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invitation Modal -->
<div id="invitationModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideInvitationModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--primary);"></i> Send Email Invitation
                </h3>
                <button type="button" onclick="hideInvitationModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="invitationForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i>
                            <span style="color: var(--text-primary);">To: {{ $user->name }} ({{ $user->email }})</span>
                        </div>
                    </div>
                    
                    @if(!$developerEmailConfigured)
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div>
                                <p class="text-sm" style="color: var(--danger);">
                                    Email configuration is not set up. Please configure email settings first.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Expires In (Days)
                        </label>
                        <input type="number" 
                               name="expires_in_days" 
                               value="7" 
                               min="1" 
                               max="30"
                               class="index-custom-input w-full">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Custom Message (Optional)
                        </label>
                        <textarea name="custom_message" 
                                  rows="3" 
                                  class="index-custom-textarea"
                                  placeholder="Add a personal message to the invitation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideInvitationModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white {{ !$developerEmailConfigured ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ !$developerEmailConfigured ? 'disabled' : '' }}>
                        <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
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
                            Are you sure you want to delete Super Admin: <strong>{{ $user->name }}</strong>? This action cannot be undone.
                        </p>
                        <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <p class="font-medium" style="color: var(--danger);">Warning:</p>
                            <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>All Super Admin data will be permanently deleted</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>This action cannot be reversed</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>If they created other users, deletion will be blocked</span>
                                </li>
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
                        <i class="fas fa-trash-alt mr-2"></i> Delete Super Admin
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
    // Auto-hide success and error messages
    autoHideMessages();
});

function toggleMoreActions() {
    const menu = document.getElementById('moreActionsMenu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// Close more actions menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('moreActionsMenu');
    const button = document.querySelector('[onclick="toggleMoreActions()"]');
    
    if (menu && !menu.contains(event.target) && button && !button.contains(event.target)) {
        menu.classList.add('hidden');
    }
});

// ==================== ROLE MANAGEMENT FUNCTIONS ====================

function addOwnRole(roleSlug) {
    let confirmMessage = '';
    let actionText = '';
    
    if (roleSlug === 'landlord') {
        confirmMessage = 'Add Landlord role to your account?\n\nThis will allow you to own and manage properties.';
        actionText = 'Adding Landlord role';
    } else if (roleSlug === 'admin') {
        confirmMessage = 'Add Admin role to your account?\n\nThis will grant you admin panel access.';
        actionText = 'Adding Admin role';
    }
    
    if (confirm(confirmMessage)) {
        const form = document.getElementById('roleManagementForm');
        const roleAction = document.getElementById('roleAction');
        const roleSlugInput = document.getElementById('roleSlug');
        
        roleAction.value = 'add';
        roleSlugInput.value = roleSlug;
        
        // Show loading state on the button
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
        btn.disabled = true;
        
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                _method: 'PUT',
                role_action: 'add',
                role_slug: roleSlug
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || `${roleSlug.charAt(0).toUpperCase() + roleSlug.slice(1)} role added successfully!`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to add role', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}

function removeOwnRole(roleSlug) {
    let confirmMessage = '';
    
    if (roleSlug === 'landlord') {
        const propertyCount = {{ $propertyCount }};
        if (propertyCount > 0) {
            showNotification(`Cannot remove Landlord role. You own ${propertyCount} property(s). Transfer ownership first.`, 'error');
            return;
        }
        confirmMessage = 'Remove Landlord role from your account?\n\nYou will no longer be able to manage properties as a landlord.';
    } else if (roleSlug === 'admin') {
        confirmMessage = 'Remove Admin role from your account?\n\nYou will lose admin panel access.';
    }
    
    if (confirm(confirmMessage)) {
        const form = document.getElementById('roleManagementForm');
        const roleAction = document.getElementById('roleAction');
        const roleSlugInput = document.getElementById('roleSlug');
        
        roleAction.value = 'remove';
        roleSlugInput.value = roleSlug;
        
        // Show loading state on the button
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
        btn.disabled = true;
        
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                _method: 'PUT',
                role_action: 'remove',
                role_slug: roleSlug
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || `${roleSlug.charAt(0).toUpperCase() + roleSlug.slice(1)} role removed successfully!`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to remove role', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}

function sendInvitation(userId) {
    const developerEmailConfigured = {{ $developerEmailConfigured ? 'true' : 'false' }};
    
    if (!developerEmailConfigured) {
        alert('Email configuration is not set up. Please configure email settings first.');
        return;
    }
    
    const modal = document.getElementById('invitationModal');
    const form = document.getElementById('invitationForm');
    
    form.action = `/developer/super-admins/${userId}/send-invitation`;
    form.reset();
    
    // Reset fields
    document.querySelector('#invitationForm input[name="expires_in_days"]').value = 7;
    document.querySelector('#invitationForm textarea[name="custom_message"]').value = '';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideInvitationModal() {
    const modal = document.getElementById('invitationModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function checkRelations(userId) {
    fetch(`/developer/super-admins/${userId}/check-relations`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.has_critical_relations) {
                    const relations = data.critical_relations.join(', ');
                    alert(`Cannot delete this Super Admin. Critical relations found: ${relations}`);
                } else {
                    showDeleteModal(userId, '{{ addslashes($user->name) }}');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to check relations.');
        });
}

function showDeleteModal(userId, userName) {
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const confirmationInput = document.getElementById('delete_confirmation');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    form.action = `/developer/super-admins/${userId}`;
    form.reset();
    confirmationInput.value = '';
    confirmBtn.disabled = true;
    confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    
    const messageElement = document.getElementById('confirmationMessage');
    if (messageElement) {
        messageElement.innerHTML = `Type exactly "DELETE" to delete Super Admin: ${userName}`;
        messageElement.style.color = 'var(--text-secondary)';
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        confirmationInput.focus();
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

function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white`;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2 text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 4 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideInvitationModal();
        hideDeleteModal();
    }
});
</script>

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

/* Update SVG icon color for dark/light mode */
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

.index-custom-dropdown option {
    background-color: var(--card-bg);
    color: var(--text-primary);
}

/* Dark mode dropdown options */
[data-theme="dark"] .index-custom-dropdown option {
    background-color: #2a2a3c;
    color: #e4e4e4;
}

/* Light mode dropdown options */
[data-theme="light"] .index-custom-dropdown option {
    background-color: #ffffff;
    color: #4b4b4b;
}

/* Custom input styles for index page */
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

.index-custom-input:disabled {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

/* Custom textarea styles for index page */
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

/* Custom checkbox styles for index page */
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

.index-custom-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Placeholder color for inputs */
.index-custom-input::placeholder,
.index-custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Focus states for better accessibility */
.index-custom-dropdown:focus-visible,
.index-custom-input:focus-visible,
.index-custom-textarea:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Badge styles */
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

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #dc3545 !important;
    transform: translateY(-1px);
}

.btn-warning {
    background-color: var(--warning) !important;
    color: white !important;
    border: 1px solid var(--warning) !important;
    transition: all 0.2s ease;
}

.btn-warning:hover {
    background-color: #e0a800 !important;
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success) !important;
    color: white !important;
    border: 1px solid var(--success) !important;
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #28a745 !important;
    transform: translateY(-1px);
}

.btn-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-info:hover {
    background-color: rgba(var(--info-rgb), 0.2) !important;
    transform: translateY(-1px);
}

/* Status indicator */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-indicator::before {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    display: inline-block;
}

.status-active {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-active::before {
    background-color: var(--success);
}

.status-pending {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-pending::before {
    background-color: var(--warning);
}

.status-suspended {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-suspended::before {
    background-color: var(--danger);
}

.status-inactive {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.status-inactive::before {
    background-color: var(--secondary);
}

/* Modal styles */
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

/* More actions dropdown */
#moreActionsMenu {
    min-width: 180px;
}

#moreActionsMenu a,
#moreActionsMenu button {
    width: 100%;
    text-align: left;
    transition: background-color 0.2s;
}

#moreActionsMenu a:hover,
#moreActionsMenu button:hover {
    background-color: rgba(var(--secondary-rgb), 0.1);
}

/* Notification animation */
.custom-notification {
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid.grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        width: 95%;
        max-height: 80vh;
        margin: 0.5rem;
    }
    
    .flex-wrap {
        justify-content: center;
    }
    
    .flex-wrap > * {
        margin-bottom: 0.5rem;
    }
}

/* Loading animation */
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endsection