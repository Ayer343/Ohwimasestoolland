{{-- developer/super-admins/create.blade.php --}}
@php
    // Determine layout and route prefix based on user role
    $layout = 'layouts.dev';
    $routePrefix = 'developer.super-admins';
    $pageTitle = 'Create Super Admin - Developer Portal';
    
    $statuses = $statuses ?? [];
    $defaultExpiryDays = $defaultExpiryDays ?? 7;
    $defaultStatus = $defaultStatus ?? 'pending';
    
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
    
    // Get old form data
    $oldData = session('_old_input', []);
    
    // Check if invitation result exists
    $invitationResult = session('invitation_result');
    $invitationRecord = session('invitation_record');
    
    // Determine initial invitation method
    $initialInvitationMethod = old('send_invitation', $developerEmailConfigured ? '1' : '0');
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
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i> 
                        Create New Super Admin
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Create a new Super Admin account with optional invitation</span>
                        @if(isset($stats) && isset($stats['total_super_admins']))
                        <span class="mx-2">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ $stats['total_super_admins'] ?? 0 }} super admins total</span>
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
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        @if($invitationResult && $invitationResult['success'] && isset($invitationResult['invitation_url']))
        <div class="mt-2 ml-6 text-sm">
            <p class="mb-1"><strong>Invitation URL:</strong></p>
            <div class="flex items-center">
                <input type="text" 
                       value="{{ $invitationResult['invitation_url'] }}" 
                       class="flex-1 bg-white border border-gray-300 rounded px-2 py-1 text-xs font-mono"
                       readonly>
                <button type="button" 
                        onclick="copyToClipboard('{{ $invitationResult['invitation_url'] }}')"
                        class="ml-2 px-2 py-1 text-xs font-medium rounded transition-colors btn-primary">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
            </div>
            @if(isset($invitationResult['expires_in_days']))
            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                Expires in {{ $invitationResult['expires_in_days'] }} days
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
        @if($invitationResult && !$invitationResult['success'])
        <div class="mt-2 ml-6 text-sm">
            <p><strong>Invitation Error:</strong> {{ $invitationResult['message'] ?? 'Unknown error' }}</p>
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
                <a href="{{ route('developer.super-admins.index') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Super Admins List
                </a>
                
                <div class="flex items-center space-x-3">
                    <span class="text-xs px-3 py-1 rounded-full" 
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-shield-alt mr-1"></i> Developer Access Required
                    </span>
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
                        You can still create the account with manual password.
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
                            Fill in the required information to create a new Super Admin account
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('developer.super-admins.store') }}" enctype="multipart/form-data" id="createSuperAdminForm">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Information -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <span class="text-red-500">*</span> Full Name
                                </label>
                                <input type="text" 
                                       name="name" 
                                       value="{{ old('name') }}" 
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
                                       value="{{ old('email') }}" 
                                       class="index-custom-input w-full @error('email') border-red-500 @enderror"
                                       placeholder="email@example.com"
                                       required>
                                @error('email')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <span class="text-red-500">*</span> Phone Number
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       value="{{ old('phone') }}" 
                                       class="index-custom-input w-full @error('phone') border-red-500 @enderror"
                                       placeholder="+233XXXXXXXXX"
                                       required>
                                @error('phone')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Username (Optional)
                                </label>
                                <input type="text" 
                                       name="username" 
                                       value="{{ old('username') }}" 
                                       class="index-custom-input w-full @error('username') border-red-500 @enderror"
                                       placeholder="Leave empty to auto-generate">
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
                                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('gender')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <!-- ⚡ UPDATED: Status Field with conditional display -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Initial Status
                                </label>
                                <div id="statusDisplay" class="{{ $initialInvitationMethod == '0' ? 'hidden' : '' }}">
                                    <select name="status" class="index-custom-dropdown w-full @error('status') border-red-500 @enderror">
                                        @foreach($statuses as $key => $label)
                                            <option value="{{ $key }}" {{ old('status', $defaultStatus) == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);" id="statusHelpText">
                                        Status will be "Pending" until invitation is accepted
                                    </p>
                                </div>
                                <div id="statusManualNote" class="{{ $initialInvitationMethod == '1' ? 'hidden' : 'p-3 bg-green-50 border border-green-200 rounded-md' }}">
                                    <p class="text-sm text-green-700">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Manual setup: Account will be created as <strong>Active</strong> with immediate access.
                                    </p>
                                </div>
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
                                            <img id="photoPreview" 
                                                 src="" 
                                                 alt="Photo preview" 
                                                 class="w-full h-full object-cover hidden">
                                            <div class="w-full h-full flex items-center justify-center" id="photoPlaceholder">
                                                <i class="fas fa-user text-xl" style="color: var(--text-secondary);"></i>
                                            </div>
                                        </div>
                                        <input type="file" 
                                               name="photo" 
                                               id="photoInput" 
                                               accept="image/*" 
                                               class="hidden"
                                               onchange="previewPhoto(event)">
                                    </div>
                                    <div>
                                        <label for="photoInput" 
                                               class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium cursor-pointer"
                                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                            <i class="fas fa-upload mr-2"></i> Upload
                                        </label>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
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
                                   value="{{ old('digital_address') }}" 
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
                                   value="{{ old('region') }}" 
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
                                   value="{{ old('location') }}" 
                                   class="index-custom-input w-full @error('location') border-red-500 @enderror"
                                   placeholder="e.g., Accra Central">
                            @error('location')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Account Setup Method -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                            Account Setup Method
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Email Invitation -->
                            <div class="card border-l-4" style="border-left-color: var(--primary);">
                                <div class="p-4">
                                    <div class="flex items-center mb-3">
                                        <input type="radio" 
                                               id="send_invitation_yes" 
                                               name="send_invitation" 
                                               value="1" 
                                               class="index-custom-checkbox"
                                               {{ old('send_invitation', $developerEmailConfigured ? '1' : '0') == '1' ? 'checked' : '' }}
                                               onchange="toggleInvitationFields()">
                                        <label for="send_invitation_yes" class="ml-3 text-sm font-medium" style="color: var(--text-primary);">
                                            Send Email Invitation
                                        </label>
                                    </div>
                                    
                                    <div id="invitationFields" class="{{ old('send_invitation', $developerEmailConfigured ? '1' : '0') == '0' ? 'hidden' : '' }}">
                                        <div class="space-y-4">
                                            @if($developerEmailConfigured)
                                            <div class="flex items-center text-sm" style="color: var(--success);">
                                                <i class="fas fa-check-circle mr-2"></i>
                                                <span>Email system is ready for invitations</span>
                                            </div>
                                            @else
                                            <div class="flex items-center text-sm" style="color: var(--warning);">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                <span>Email configuration required</span>
                                            </div>
                                            @endif
                                            
                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Invitation Expires In (Days)
                                                </label>
                                                <input type="number" 
                                                       name="expires_in_days" 
                                                       value="{{ old('expires_in_days', $defaultExpiryDays) }}" 
                                                       min="1" 
                                                       max="30"
                                                       class="index-custom-input w-full @error('expires_in_days') border-red-500 @enderror">
                                                @error('expires_in_days')
                                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Custom Message (Optional)
                                                </label>
                                                <textarea name="custom_message" 
                                                          rows="2" 
                                                          class="index-custom-textarea"
                                                          placeholder="Add a personal message to the invitation...">{{ old('custom_message') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Manual Password -->
                            <div class="card border-l-4" style="border-left-color: var(--info);">
                                <div class="p-4">
                                    <div class="flex items-center mb-3">
                                        <input type="radio" 
                                               id="send_invitation_no" 
                                               name="send_invitation" 
                                               value="0" 
                                               class="index-custom-checkbox"
                                               {{ old('send_invitation', $developerEmailConfigured ? '1' : '0') == '0' ? 'checked' : '' }}
                                               onchange="toggleInvitationFields()">
                                        <label for="send_invitation_no" class="ml-3 text-sm font-medium" style="color: var(--text-primary);">
                                            Set Password Manually
                                        </label>
                                    </div>
                                    
                                    <div id="passwordFields" class="space-y-4 {{ old('send_invitation', $developerEmailConfigured ? '1' : '0') == '1' ? 'hidden' : '' }}">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                <span class="text-red-500">*</span> Password
                                            </label>
                                            <input type="password" 
                                                   name="password" 
                                                   class="index-custom-input w-full @error('password') border-red-500 @enderror"
                                                   placeholder="Enter password"
                                                   id="passwordInput"
                                                   {{ old('send_invitation', $developerEmailConfigured ? '1' : '0') == '0' ? 'required' : '' }}>
                                            @error('password')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                <span class="text-red-500">*</span> Confirm Password
                                            </label>
                                            <input type="password" 
                                                   name="password_confirmation" 
                                                   class="index-custom-input w-full"
                                                   placeholder="Confirm password"
                                                   {{ old('send_invitation', $developerEmailConfigured ? '1' : '0') == '0' ? 'required' : '' }}>
                                        </div>
                                        
                                        <div>
                                            <button type="button" 
                                                    onclick="generatePassword()"
                                                    class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                                <i class="fas fa-key mr-2"></i> Generate Password
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Auto Verification -->
                    <div class="mt-6">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-bolt mr-1"></i> Quick Options
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="auto_verify_email" 
                                       name="auto_verify_email" 
                                       value="1" 
                                       class="index-custom-checkbox"
                                       {{ old('auto_verify_email') ? 'checked' : '' }}>
                                <label for="auto_verify_email" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Auto-verify email address
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="auto_verify_phone" 
                                       name="auto_verify_phone" 
                                       value="1" 
                                       class="index-custom-checkbox"
                                       {{ old('auto_verify_phone') ? 'checked' : '' }}>
                                <label for="auto_verify_phone" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Auto-verify phone number
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-between items-center mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                        <div>
                            <a href="{{ route('developer.super-admins.index') }}" 
                               class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i> Back to List
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button type="reset" 
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-medium btn-secondary">
                                <i class="fas fa-redo mr-2"></i> Reset Form
                            </button>
                            <button type="submit" 
                                    id="submitButton"
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white btn-primary {{ !$developerEmailConfigured && old('send_invitation', $developerEmailConfigured ? '1' : '0') == '1' ? 'opacity-50 cursor-not-allowed' : '' }}"
                                    {{ !$developerEmailConfigured && old('send_invitation', $developerEmailConfigured ? '1' : '0') == '1' ? 'disabled' : '' }}>
                                <i class="fas fa-user-plus mr-2"></i> Create Super Admin
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Information & Status -->
        <div class="lg:col-span-1">
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
                        
                        <!-- Password Setup -->
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <i class="fas fa-key mr-3 text-green-500"></i>
                                <span style="color: var(--text-primary);">
                                    Manual Password
                                </span>
                            </div>
                            <div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    Available
                                </span>
                            </div>
                        </div>
                        
                        <!-- Developer Access -->
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt mr-3 text-green-500"></i>
                                <span style="color: var(--text-primary);">
                                    Developer Access
                                </span>
                            </div>
                            <div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                                    <i class="fas fa-check mr-1"></i> Granted
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-xs mt-4" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        @if($developerEmailConfigured)
                            Email system is configured and ready for invitations.
                            <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" class="text-primary hover:underline ml-1">
                                Test configuration
                            </a>
                        @else
                            Configure email system to enable invitation sending.
                            <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" class="text-primary hover:underline ml-1">
                                Configure now
                            </a>
                        @endif
                    </p>
                </div>
            </div>
            
            <!-- Help/Instructions Card -->
            <div class="card border-l-4" style="border-left-color: var(--primary);">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-question-circle mr-2" style="color: var(--primary);"></i> 
                        How It Works
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-envelope text-green-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Email Invitation</h4>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    User receives secure invitation link via email to set their own password. Account will be <strong>Pending</strong> until invitation is accepted.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-key text-blue-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Manual Password</h4>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Set password immediately. Account will be created as <strong>Active</strong> with immediate access.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-shield-alt text-purple-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Super Admin Access</h4>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Full system access. Monitor activity and deactivate unused accounts
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <p class="text-xs" style="color: var(--warning);">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Important:</strong> Super Admins have full system access. Only create accounts for trusted individuals.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    initFormValidation();
    
    // Check initial invitation method
    toggleInvitationFields();
    
    // Auto-hide success and error messages
    autoHideMessages();
});

function toggleInvitationFields() {
    const sendInvitationYes = document.getElementById('send_invitation_yes');
    const invitationFields = document.getElementById('invitationFields');
    const passwordFields = document.getElementById('passwordFields');
    const statusDisplay = document.getElementById('statusDisplay');
    const statusManualNote = document.getElementById('statusManualNote');
    const statusHelpText = document.getElementById('statusHelpText');
    const submitButton = document.getElementById('submitButton');
    const developerEmailConfigured = {{ $developerEmailConfigured ? 'true' : 'false' }};
    
    if (sendInvitationYes.checked) {
        // Invitation mode
        invitationFields.classList.remove('hidden');
        passwordFields.classList.add('hidden');
        statusDisplay.classList.remove('hidden');
        statusManualNote.classList.add('hidden');
        
        // Make password not required
        const passwordInput = document.getElementById('passwordInput');
        if (passwordInput) {
            passwordInput.removeAttribute('required');
        }
        
        // Update status help text
        if (statusHelpText) {
            statusHelpText.textContent = 'Status will be "Pending" until invitation is accepted';
        }
        
        // Check email configuration
        if (!developerEmailConfigured) {
            // Disable submit button if email not configured
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
        } else {
            // Enable submit button
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    } else {
        // Manual password mode
        invitationFields.classList.add('hidden');
        passwordFields.classList.remove('hidden');
        statusDisplay.classList.add('hidden');
        statusManualNote.classList.remove('hidden');
        
        // Make password required
        const passwordInput = document.getElementById('passwordInput');
        if (passwordInput) {
            passwordInput.setAttribute('required', 'required');
        }
        
        // Update status help text (not visible but set anyway)
        if (statusHelpText) {
            statusHelpText.textContent = 'Status automatically set to "Active" for manual setup';
        }
        
        // Enable submit button (always available for manual setup)
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
}

function previewPhoto(event) {
    const input = event.target;
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    
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
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

function generatePassword() {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|}{[]:;?><,./-=";
    let password = "";
    
    // Ensure at least one of each required character type
    password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ".charAt(Math.floor(Math.random() * 26));
    password += "abcdefghijklmnopqrstuvwxyz".charAt(Math.floor(Math.random() * 26));
    password += "0123456789".charAt(Math.floor(Math.random() * 10));
    password += "!@#$%^&*()_+~`|}{[]:;?><,./-=".charAt(Math.floor(Math.random() * 32));
    
    // Fill the rest
    for (let i = password.length; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    
    // Shuffle the password
    password = password.split('').sort(() => 0.5 - Math.random()).join('');
    
    // Set the password field
    const passwordInput = document.getElementById('passwordInput');
    if (passwordInput) {
        passwordInput.value = password;
        passwordInput.type = 'text'; // Show the password
        
        // Copy to clipboard
        navigator.clipboard.writeText(password).then(() => {
            alert('Password generated and copied to clipboard!');
        }).catch(err => {
            alert(`Password generated: ${password}\n\nPlease copy it manually.`);
        });
        
        // Change back to password type after 5 seconds
        setTimeout(() => {
            if (passwordInput.value === password) {
                passwordInput.type = 'password';
            }
        }, 5000);
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
    const form = document.getElementById('createSuperAdminForm');
    const developerEmailConfigured = {{ $developerEmailConfigured ? 'true' : 'false' }};
    
    if (!form) return;
    
    form.addEventListener('submit', function(event) {
        const sendInvitationYes = document.getElementById('send_invitation_yes');
        
        // Check if sending invitation and email is not configured
        if (sendInvitationYes && sendInvitationYes.checked && !developerEmailConfigured) {
            event.preventDefault();
            alert('Email configuration is required for invitations. Please setup email or use manual password method.');
            return false;
        }
        
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
        
        // If password is required, validate it
        const passwordInput = document.getElementById('passwordInput');
        if (passwordInput && passwordInput.hasAttribute('required') && passwordInput.value) {
            if (passwordInput.value.length < 8) {
                event.preventDefault();
                alert('Password must be at least 8 characters long.');
                passwordInput.focus();
                return false;
            }
        }
        
        // Show loading state
        const submitButton = document.getElementById('submitButton');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    #photoPreviewContainer {
        width: 60px;
        height: 60px;
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
@endsection