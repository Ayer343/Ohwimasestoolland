{{-- super-admin/super-admins/show.blade.php --}}
@php
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $isOwnProfile = $isSuperAdmin && $user->id === auth()->id();
    $isViewingOwnProfile = $isOwnProfile;
    
    $pageTitle = $isOwnProfile ? 'My Profile - Super Admin' : 'Super Admin Details - View Only';
@endphp

@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Profile Photo or Initials -->
                <div class="mr-4">
                    @if($user->has_photo && $user->photo)
                    <div class="w-16 h-16 rounded-full overflow-hidden border-2" style="border-color: var(--primary);">
                        <img src="{{ Storage::url('users/photos/' . $user->photo) }}" 
                             alt="{{ $user->name }}" 
                             class="w-full h-full object-cover">
                    </div>
                    @else
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <span class="text-xl">{{ $user->initials }}</span>
                    </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i> 
                        {{ $user->name }}
                        <span class="ml-2 status-indicator status-{{ $user->status }}">
                            {{ $user->display_status['label'] ?? $user->status }}
                        </span>
                        @if($isOwnProfile)
                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            <i class="fas fa-user mr-1"></i> You
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Super Admin account</span>
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
                        @if(!$isOwnProfile)
                        <span class="mx-2">•</span>
                        <i class="fas fa-eye mr-1 text-blue-500"></i>
                        <span class="text-blue-600 font-medium">Read-Only View</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm text-right" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="mt-2">
                    <a href="{{ route('super-admin.super-admins.index') }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
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
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center space-x-3">
                    <a href="{{ route('super-admin.dashboard') }}" 
                       class="inline-flex items-center text-sm font-medium" 
                       style="color: var(--primary);">
                        <i class="fas fa-dashboard mr-2"></i> Dashboard
                    </a>
                    <span class="text-xs px-3 py-1 rounded-full" 
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-shield-alt mr-1"></i> Super Admin Portal
                    </span>
                </div>
                
                @if($isOwnProfile)
                <div class="flex items-center space-x-3">
                    <a href="{{ route('super-admin.super-admins.index') }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-secondary">
                        <i class="fas fa-users mr-2"></i> All Super Admins
                    </a>
                    <a href="{{ route('super-admin.super-admins.activities', $user->id) }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-info">
                        <i class="fas fa-history mr-2"></i> My Activities
                    </a>
                    <a href="{{ route('super-admin.super-admins.invitation-history', $user->id) }}" 
                       class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-secondary">
                        <i class="fas fa-paper-plane mr-2"></i> Invitation History
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Account Status Card -->
        <div class="card p-4">
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
        <div class="card p-4">
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
        <div class="card p-4">
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
        
        <!-- Role Count Card -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-tags text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Roles</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ ($hasLandlordRole ? 1 : 0) + ($hasAdminRole ? 1 : 0) + 1 }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                        <i class="fas fa-shield-alt mr-1"></i> +Super Admin
                    </span>
                </div>
            </div>
        </div>
    </div>

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
                            <div class="flex items-center flex-wrap">
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
                            <div class="flex items-center flex-wrap">
                                <p class="text-sm" style="color: var(--text-primary);">{{ $user->local_phone ?? $user->phone ?? 'Not set' }}</p>
                                @if($user->phone_verified_at)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                @elseif($user->phone)
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
                                {{ $user->gender ? ucfirst($user->gender) : 'Not specified' }}
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
            @if(!empty($recentActivities) && count($recentActivities) > 0)
            <div class="card p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> 
                        Recent Activities
                    </h3>
                    @if($isOwnProfile)
                    <a href="{{ route('super-admin.super-admins.activities', $user->id) }}" 
                       class="text-sm font-medium" 
                       style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                    @endif
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
        
        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Current Roles Card (View Only - No Role Management) -->
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
                        @if($propertyCount > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs" style="background-color: var(--success); color: white;">
                            {{ $propertyCount }}
                        </span>
                        @endif
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
                
                <!-- Info note about role management location -->
                @if($isOwnProfile)
                <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                    <p class="text-xs flex items-center gap-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle" style="color: var(--info);"></i>
                        <span>To manage your additional roles (Landlord, Admin), please visit the 
                        <a href="{{ route('super-admin.super-admins.index') }}" class="font-medium hover:underline" style="color: var(--primary);">
                            Super Administrators List
                        </a> page and click the "Roles" button next to your name.
                        </span>
                    </p>
                </div>
                @endif
                
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

            <!-- Account Info Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> 
                    Account Info
                </h3>
                
                <div class="space-y-4">
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
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                            Account Age
                        </label>
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ $user->age }}
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
                
                <div class="mt-4 pt-3 border-t text-center" style="border-color: var(--border-color);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        Last sent: {{ $invitationStats['last_sent'] ?? 'Never' }}
                    </p>
                </div>
                
                @if($isOwnProfile)
                <div class="mt-4">
                    <a href="{{ route('super-admin.super-admins.invitation-history', $user->id) }}" 
                       class="block text-center w-full px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                        <i class="fas fa-history mr-2"></i> View Full Invitation History
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
/* Status indicator styles */
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
    
    .flex-wrap {
        justify-content: center;
    }
    
    .flex-wrap > * {
        margin-bottom: 0.5rem;
    }
}
</style>
@endsection