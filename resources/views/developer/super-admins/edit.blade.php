{{-- developer/super-admins/edit.blade.php --}}
@php
    // Determine layout and route prefix based on user role
    $layout = 'layouts.dev';
    $routePrefix = 'developer.super-admins';
    $pageTitle = 'Edit Super Admin - Developer Portal';
    
    $user = $user ?? null;
    $statuses = $statuses ?? [];
    $genders = $genders ?? [];
    
    // Define constants
    $STATUS_ACTIVE = $userConstants['STATUS_ACTIVE'] ?? 'active';
    $STATUS_PENDING = $userConstants['STATUS_PENDING'] ?? 'pending';
    $STATUS_SUSPENDED = $userConstants['STATUS_SUSPENDED'] ?? 'suspended';
    $STATUS_INACTIVE = $userConstants['STATUS_INACTIVE'] ?? 'inactive';
    $TYPE_SUPER_ADMIN = $userConstants['TYPE_SUPER_ADMIN'] ?? 1;
    
    // Get email configuration status
    $emailConfigStatus = $emailConfigStatus ?? [
        'connection' => false,
        'authentication' => false,
        'can_send' => false,
        'message' => 'Email configuration status unknown',
        'last_test' => null,
        'config_source' => 'service'
    ];
    
    // Check if developer email is configured
    $developerEmailConfigured = $emailConfigStatus['can_send'] ?? false;
    
    // Check for any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Check for any validation errors
    $validationErrors = session('errors');
    
    if (!$user) {
        abort(404, 'Super Admin not found');
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
                    @if($user->photo)
                        <img src="{{ Storage::url($user->photo) }}" 
                             alt="{{ $user->name }}" 
                             class="w-16 h-16 rounded-full border-2 object-cover"
                             style="border-color: var(--border-color);">
                    @else
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            <span class="text-xl">{{ $user->initials ?? substr($user->name, 0, 2) }}</span>
                        </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-edit mr-2" style="color: var(--primary);"></i> 
                        Edit Super Admin: {{ $user->name }}
                        <span class="ml-3 px-2 py-1 text-xs font-medium rounded-full status-indicator status-{{ $user->status }}">
                            {{ $user->display_status['label'] ?? ucfirst($user->status) }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Update Super Admin account details and settings</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-tie mr-1"></i>
                        <span>Created by: {{ $user->creator_name ?? 'System' }}</span>
                        @if($user->created_at)
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>Joined {{ $user->created_at->format('M j, Y') }}</span>
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
                <a href="{{ route('developer.super-admins.index') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Success Messages -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        @if(session('invitation_result') && session('invitation_result')['success'] && isset(session('invitation_result')['invitation_url']))
        <div class="mt-2 ml-6 text-sm">
            <p class="mb-1"><strong>Invitation URL:</strong></p>
            <div class="flex items-center">
                <input type="text" 
                       value="{{ session('invitation_result')['invitation_url'] }}" 
                       class="flex-1 bg-white border border-gray-300 rounded px-2 py-1 text-xs font-mono"
                       readonly>
                <button type="button" 
                        onclick="copyToClipboard('{{ session('invitation_result')['invitation_url'] }}')"
                        class="ml-2 px-2 py-1 text-xs font-medium rounded transition-colors btn-primary">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
            </div>
            @if(isset(session('invitation_result')['expires_in_days']))
            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                Expires in {{ session('invitation_result')['expires_in_days'] }} days
            </p>
            @endif
        </div>
        @endif
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Messages -->
    @if($errorMessage)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
        </div>
        @if(session('invitation_result') && !session('invitation_result')['success'])
        <div class="mt-2 ml-6 text-sm">
            <p><strong>Invitation Error:</strong> {{ session('invitation_result')['message'] ?? 'Unknown error' }}</p>
        </div>
        @endif
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Navigation Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('developer.super-admins.show', $user->id) }}" 
                       class="inline-flex items-center text-sm font-medium" 
                       style="color: var(--primary);">
                        <i class="fas fa-eye mr-2"></i> View Details
                    </a>
                    <a href="{{ route('developer.super-admins.change-password', $user->id) }}" 
                       class="inline-flex items-center text-sm font-medium" 
                       style="color: var(--warning);">
                        <i class="fas fa-key mr-2"></i> Change Password
                    </a>
                </div>
                
                <div class="flex items-center space-x-3">
                    <span class="text-xs px-3 py-1 rounded-full" 
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-shield-alt mr-1"></i> Developer Access Required
                    </span>
                    @if($user->status === $STATUS_ACTIVE)
                        <span class="text-xs px-3 py-1 rounded-full badge-success">
                            <i class="fas fa-circle mr-1"></i> Active
                        </span>
                    @elseif($user->status === $STATUS_SUSPENDED)
                        <span class="text-xs px-3 py-1 rounded-full badge-danger">
                            <i class="fas fa-ban mr-1"></i> Suspended
                        </span>
                    @elseif($user->status === $STATUS_INACTIVE)
                        <span class="text-xs px-3 py-1 rounded-full badge-secondary">
                            <i class="fas fa-power-off mr-1"></i> Inactive
                        </span>
                    @else
                        <span class="text-xs px-3 py-1 rounded-full badge-warning">
                            <i class="fas fa-clock mr-1"></i> Pending
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Email Configuration Status Card -->
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
                        You can still update the account without email functionality.
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

    <!-- Main Form Container -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Form Fields -->
        <div class="lg:col-span-2">
            <div class="card p-6">
                <!-- Form Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Super Admin Details
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Update the Super Admin account information and settings
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('developer.super-admins.update', $user->id) }}" enctype="multipart/form-data" id="editSuperAdminForm">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Information -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <span class="text-red-500">*</span> Full Name
                                </label>
                                <input type="text" 
                                       name="name" 
                                       value="{{ old('name', $user->name) }}" 
                                       class="index-custom-input w-full @error('name') border-red-500 @enderror"
                                       placeholder="Enter full name"
                                       required>
                                @error('name')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <span class="text-red-500">*</span> Email Address
                                </label>
                                <input type="email" 
                                       name="email" 
                                       value="{{ old('email', $user->email) }}" 
                                       class="index-custom-input w-full @error('email') border-red-500 @enderror"
                                       placeholder="email@example.com"
                                       required>
                                @error('email')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                                <div class="flex items-center mt-2">
                                    @if($user->email_verified_at)
                                    <span class="text-xs px-2 py-1 rounded-full badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                    @else
                                    <span class="text-xs px-2 py-1 rounded-full badge-danger">
                                        <i class="fas fa-times-circle mr-1"></i> Not Verified
                                    </span>
                                    @endif
                                    <div class="flex items-center ml-4 space-x-2">
                                        <input type="checkbox" 
                                               id="auto_verify_email" 
                                               name="auto_verify_email" 
                                               value="1" 
                                               class="index-custom-checkbox"
                                               {{ old('auto_verify_email') ? 'checked' : '' }}>
                                        <label for="auto_verify_email" class="text-xs" style="color: var(--text-primary);">
                                            Force verify
                                        </label>
                                        
                                        @if($user->email_verified_at)
                                        <input type="checkbox" 
                                               id="remove_email_verification" 
                                               name="remove_email_verification" 
                                               value="1" 
                                               class="index-custom-checkbox">
                                        <label for="remove_email_verification" class="text-xs" style="color: var(--text-primary);">
                                            Remove verification
                                        </label>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <span class="text-red-500">*</span> Phone Number
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       value="{{ old('phone', $user->phone) }}" 
                                       class="index-custom-input w-full @error('phone') border-red-500 @enderror"
                                       placeholder="+233XXXXXXXXX"
                                       required>
                                @error('phone')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                                <div class="flex items-center mt-2">
                                    @if($user->phone_verified_at)
                                    <span class="text-xs px-2 py-1 rounded-full badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                    @else
                                    <span class="text-xs px-2 py-1 rounded-full badge-danger">
                                        <i class="fas fa-times-circle mr-1"></i> Not Verified
                                    </span>
                                    @endif
                                    <div class="flex items-center ml-4 space-x-2">
                                        <input type="checkbox" 
                                               id="auto_verify_phone" 
                                               name="auto_verify_phone" 
                                               value="1" 
                                               class="index-custom-checkbox"
                                               {{ old('auto_verify_phone') ? 'checked' : '' }}>
                                        <label for="auto_verify_phone" class="text-xs" style="color: var(--text-primary);">
                                            Force verify
                                        </label>
                                        
                                        @if($user->phone_verified_at)
                                        <input type="checkbox" 
                                               id="remove_phone_verification" 
                                               name="remove_phone_verification" 
                                               value="1" 
                                               class="index-custom-checkbox">
                                        <label for="remove_phone_verification" class="text-xs" style="color: var(--text-primary);">
                                            Remove verification
                                        </label>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Username
                                </label>
                                <input type="text" 
                                       name="username" 
                                       value="{{ old('username', $user->username) }}" 
                                       class="index-custom-input w-full @error('username') border-red-500 @enderror"
                                       placeholder="Leave empty to keep current">
                                @error('username')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Account Settings -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Gender
                                </label>
                                <select name="gender" class="index-custom-dropdown w-full @error('gender') border-red-500 @enderror">
                                    <option value="">Select Gender</option>
                                    @foreach($genders as $key => $label)
                                        <option value="{{ $key }}" {{ old('gender', $user->gender) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('gender')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <span class="text-red-500">*</span> Status
                                </label>
                                <select name="status" class="index-custom-dropdown w-full @error('status') border-red-500 @enderror" required>
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" {{ old('status', $user->status) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <!-- Profile Photo -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Profile Photo
                                </label>
                                <div class="flex items-center space-x-4">
                                    <div class="relative">
                                        <div class="w-20 h-20 rounded-full overflow-hidden border-2" 
                                             style="border-color: var(--border-color); background-color: var(--bg-secondary);"
                                             id="photoPreviewContainer">
                                            @if($user->photo)
                                                <img id="photoPreview" 
                                                     src="{{ Storage::url($user->photo) }}" 
                                                     alt="Photo preview" 
                                                     class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center" id="photoPlaceholder">
                                                    <i class="fas fa-user text-xl" style="color: var(--text-secondary);"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <input type="file" 
                                               name="photo" 
                                               id="photoInput" 
                                               accept="image/*" 
                                               class="hidden"
                                               onchange="previewPhoto(event)">
                                    </div>
                                    <div class="space-y-2">
                                        <label for="photoInput" 
                                               class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium cursor-pointer"
                                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                            <i class="fas fa-upload mr-2"></i> Upload New
                                        </label>
                                        @if($user->photo)
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   id="remove_photo" 
                                                   name="remove_photo" 
                                                   value="1" 
                                                   class="index-custom-checkbox">
                                            <label for="remove_photo" class="ml-2 text-xs" style="color: var(--text-primary);">
                                                Remove current photo
                                            </label>
                                        </div>
                                        @endif
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            JPG, PNG, GIF, WEBP up to 5MB
                                        </p>
                                        @error('photo')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Digital Address
                            </label>
                            <input type="text" 
                                   name="digital_address" 
                                   value="{{ old('digital_address', $user->digital_address) }}" 
                                   class="index-custom-input w-full @error('digital_address') border-red-500 @enderror"
                                   placeholder="e.g., GT-123-456">
                            @error('digital_address')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Region
                            </label>
                            <input type="text" 
                                   name="region" 
                                   value="{{ old('region', $user->region) }}" 
                                   class="index-custom-input w-full @error('region') border-red-500 @enderror"
                                   placeholder="e.g., Greater Accra">
                            @error('region')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Location
                            </label>
                            <input type="text" 
                                   name="location" 
                                   value="{{ old('location', $user->location) }}" 
                                   class="index-custom-input w-full @error('location') border-red-500 @enderror"
                                   placeholder="e.g., Accra Central">
                            @error('location')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Quick Actions Section -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                            Quick Actions
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Send Invitation -->
                            <button type="button" 
                                    onclick="sendInvitation()"
                                    class="flex items-center justify-center p-4 rounded-lg text-sm font-medium btn-success {{ $developerEmailConfigured ? '' : 'opacity-50 cursor-not-allowed' }}"
                                    {{ !$developerEmailConfigured ? 'disabled' : '' }}>
                                <i class="fas fa-paper-plane mr-3"></i>
                                <div class="text-left">
                                    <div class="font-semibold">Send Invitation</div>
                                    <div class="text-xs opacity-75">Send email invitation link</div>
                                </div>
                            </button>
                            
                            <!-- Change Password -->
                            <a href="{{ route('developer.super-admins.change-password', $user->id) }}"
                               class="flex items-center justify-center p-4 rounded-lg text-sm font-medium btn-warning">
                                <i class="fas fa-key mr-3"></i>
                                <div class="text-left">
                                    <div class="font-semibold">Change Password</div>
                                    <div class="text-xs opacity-75">Reset or change password</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Account Information Display -->
                    <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                        <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-2"></i> Account Information
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Created</div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Last Active</div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $user->last_active ?? 'Never' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Email Verified</div>
                                <div class="text-sm font-medium {{ $user->email_verified_at ? 'text-green-500' : 'text-red-500' }}">
                                    {{ $user->email_verified_at ? 'Yes' : 'No' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Phone Verified</div>
                                <div class="text-sm font-medium {{ $user->phone_verified_at ? 'text-green-500' : 'text-red-500' }}">
                                    {{ $user->phone_verified_at ? 'Yes' : 'No' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-between items-center mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                        <div>
                            <a href="{{ route('developer.super-admins.show', $user->id) }}" 
                               class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Details
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button type="reset" 
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-medium btn-secondary">
                                <i class="fas fa-redo mr-2"></i> Reset Changes
                            </button>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white btn-primary">
                                <i class="fas fa-save mr-2"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Information & Status -->
        <div class="lg:col-span-1">
            <!-- Account Management Card -->
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i> 
                        Account Management
                    </h3>
                    
                    <div class="space-y-3">
                        @if($user->status !== $STATUS_ACTIVE)
                        <form method="POST" action="{{ route('developer.super-admins.activate', $user->id) }}" 
                              onsubmit="return confirm('Are you sure you want to activate this Super Admin?')">
                            @csrf
                            <button type="submit" 
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium btn-success">
                                <i class="fas fa-toggle-on mr-2"></i> Activate Account
                            </button>
                        </form>
                        @endif
                        
                        @if($user->status !== $STATUS_SUSPENDED)
                        <form method="POST" action="{{ route('developer.super-admins.suspend', $user->id) }}" 
                              onsubmit="return confirm('Are you sure you want to suspend this Super Admin?')">
                            @csrf
                            <button type="submit" 
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium btn-warning">
                                <i class="fas fa-ban mr-2"></i> Suspend Account
                            </button>
                        </form>
                        @endif
                        
                        @if($user->status !== $STATUS_INACTIVE)
                        <form method="POST" action="{{ route('developer.super-admins.deactivate', $user->id) }}" 
                              onsubmit="return confirm('Are you sure you want to deactivate this Super Admin?')">
                            @csrf
                            <button type="submit" 
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                                <i class="fas fa-toggle-off mr-2"></i> Deactivate Account
                            </button>
                        </form>
                        @endif
                        
                        <!-- Force Verify Email -->
                        @if(!$user->email_verified_at)
                        <form method="POST" action="{{ route('developer.super-admins.force-verify-email', $user->id) }}" 
                              onsubmit="return confirm('Are you sure you want to force verify email?')">
                            @csrf
                            <button type="submit" 
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-envelope-check mr-2"></i> Verify Email
                            </button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('developer.super-admins.remove-email-verification', $user->id) }}" 
                              onsubmit="return confirm('Are you sure you want to remove email verification?')">
                            @csrf
                            <button type="submit" 
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-envelope-times mr-2"></i> Unverify Email
                            </button>
                        </form>
                        @endif
                    </div>
                    
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <p class="text-xs" style="color: var(--warning);">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Note:</strong> Account management actions are logged and cannot be undone.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Service Status Card -->
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-broadcast-tower mr-2" style="color: var(--info);"></i> 
                        Service Status
                    </h3>
                    
                    <div class="space-y-3">
                        <!-- Email Status -->
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                @if($developerEmailConfigured)
                                    <i class="fas fa-check-circle mr-3 text-green-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Email System
                                    </span>
                                @else
                                    <i class="fas fa-exclamation-circle mr-3 text-yellow-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Email System
                                    </span>
                                @endif
                            </div>
                            <div>
                                @if($developerEmailConfigured)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    <i class="fas fa-check mr-1"></i> Active
                                </span>
                                @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                                    <i class="fas fa-exclamation mr-1"></i> Setup Required
                                </span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Invitation Status -->
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                @if($developerEmailConfigured)
                                    <i class="fas fa-paper-plane mr-3 text-green-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Email Invitations
                                    </span>
                                @else
                                    <i class="fas fa-paper-plane mr-3 text-yellow-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Email Invitations
                                    </span>
                                @endif
                            </div>
                            <div>
                                @if($developerEmailConfigured)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    Available
                                </span>
                                @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                                    Unavailable
                                </span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Account Status -->
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                @if($user->status === $STATUS_ACTIVE)
                                    <i class="fas fa-check-circle mr-3 text-green-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Account Status
                                    </span>
                                @elseif($user->status === $STATUS_SUSPENDED)
                                    <i class="fas fa-ban mr-3 text-red-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Account Status
                                    </span>
                                @else
                                    <i class="fas fa-clock mr-3 text-yellow-500"></i>
                                    <span style="color: var(--text-primary);">
                                        Account Status
                                    </span>
                                @endif
                            </div>
                            <div>
                                @if($user->status === $STATUS_ACTIVE)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    Active
                                </span>
                                @elseif($user->status === $STATUS_SUSPENDED)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-danger">
                                    Suspended
                                </span>
                                @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                                    {{ ucfirst($user->status) }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-xs mt-4" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        @if($developerEmailConfigured)
                            Email system is configured and ready for invitations.
                        @else
                            Configure email system to enable invitation sending.
                        @endif
                    </p>
                </div>
            </div>
            
            <!-- Help/Instructions Card -->
            <div class="card border-l-4" style="border-left-color: var(--primary);">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-question-circle mr-2" style="color: var(--primary);"></i> 
                        Editing Notes
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-save text-blue-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Save Changes</h4>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Click "Save Changes" to update all modified fields
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-envelope text-green-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Email Invitation</h4>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Send new invitation if user hasn't activated account
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-undo-alt text-gray-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Reset Changes</h4>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Use "Reset Changes" to revert to original values
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <p class="text-xs" style="color: var(--warning);">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Important:</strong> Email changes may require re-verification. Update carefully.
                        </p>
                    </div>
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
            <form id="invitationForm" method="POST" action="{{ route('developer.super-admins.send-invitation', $user->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i>
                            <span style="color: var(--text-primary);">To: {{ $user->name }} ({{ $user->email }})</span>
                        </div>
                    </div>
                    
                    @if(!$developerEmailConfigured)
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-500 mt-1 mr-2"></i>
                            <div>
                                <p class="text-sm text-red-700">
                                    Email configuration is not set up. Please configure email settings first.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-calendar mr-1"></i> Expires In (Days)
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
                            <i class="fas fa-comment-alt mr-1"></i> Custom Message (Optional)
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    initFormValidation();
    
    // Auto-hide success and error messages
    autoHideMessages();
});

function sendInvitation() {
    const developerEmailConfigured = {{ $developerEmailConfigured ? 'true' : 'false' }};
    
    if (!developerEmailConfigured) {
        alert('Email configuration is not set up. Please configure email settings first.');
        return;
    }
    
    const modal = document.getElementById('invitationModal');
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

function previewPhoto(event) {
    const input = event.target;
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    const container = document.getElementById('photoPreviewContainer');
    
    if (input.files && input.files[0]) {
        // Validate file size (5MB max)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (input.files[0].size > maxSize) {
            alert('File size exceeds 5MB limit. Please choose a smaller file.');
            input.value = '';
            return;
        }
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(input.files[0].type)) {
            alert('Invalid file type. Please upload JPG, PNG, GIF, or WEBP image.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Create preview image if it doesn't exist
            if (!preview) {
                const img = document.createElement('img');
                img.id = 'photoPreview';
                img.className = 'w-full h-full object-cover';
                img.alt = 'Photo preview';
                container.prepend(img);
                if (placeholder) placeholder.classList.add('hidden');
            } else {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('URL copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard');
    });
}

function initFormValidation() {
    const form = document.getElementById('editSuperAdminForm');
    
    if (!form) return;
    
    form.addEventListener('submit', function(event) {
        // Validate phone format
        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput && phoneInput.value) {
            // Basic phone validation - adjust regex as needed
            const phoneRegex = /^\+?[\d\s\-\(\)]{10,20}$/;
            if (!phoneRegex.test(phoneInput.value.replace(/\s/g, ''))) {
                event.preventDefault();
                alert('Please enter a valid phone number (10-20 digits, may include +, spaces, dashes, parentheses).');
                phoneInput.focus();
                return false;
            }
        }
        
        // Validate email format
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput && emailInput.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value)) {
                event.preventDefault();
                alert('Please enter a valid email address.');
                emailInput.focus();
                return false;
            }
        }
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
        }
        
        return true;
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
</script>

<style>
/* Edit page specific styles */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
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

/* Status indicator styles */
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

/* Additional button styles */
.btn-success {
    background-color: var(--success) !important;
    color: white !important;
    border: 1px solid var(--success) !important;
    transition: all 0.2s ease;
}

.btn-success:hover:not(:disabled) {
    background-color: #28a745 !important;
    transform: translateY(-1px);
}

/* Index-specific form control styles (copied from create.blade.php) */
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

/* Custom input styles */
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

/* Custom textarea styles */
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

/* Custom checkbox styles */
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

/* Radio button custom styling */
input[type="radio"] {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
    appearance: none;
    position: relative;
}

input[type="radio"]:checked {
    border-color: var(--primary);
    background-color: var(--primary);
}

input[type="radio"]:checked::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background-color: white;
}

/* Error message styling */
.text-red-500 {
    color: var(--danger) !important;
}

.border-red-500 {
    border-color: var(--danger) !important;
}

/* Card styles */
.card.border-l-4 {
    border-left-width: 4px !important;
}

/* Loading animation */
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    #photoPreviewContainer {
        width: 60px;
        height: 60px;
    }
    
    .modal-container {
        width: 95%;
        max-height: 80vh;
        margin: 0.5rem;
    }
}

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.3);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.5);
}
</style>