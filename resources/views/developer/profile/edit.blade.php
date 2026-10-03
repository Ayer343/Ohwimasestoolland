@extends('layouts.dev')

@section('title', 'Edit Profile - Developer')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">Developer Profile</h1>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Manage your developer account and system access</p>
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
                    
                    <a href="{{ route('dashboard') }}" class="btn-secondary flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Dashboard
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
                        <div class="relative inline-block mb-4" id="photoContainer">
                            @php
                                $user = Auth::user();
                                $photoUrl = $user->photo_url ?? null;
                                $initials = $user->getInitials();
                                $hasPhoto = !empty($user->photo) && $photoUrl;
                            @endphp
                            
                            @if($hasPhoto)
                                <div class="relative">
                                    <img src="{{ $photoUrl }}" 
                                         alt="{{ $user->name }}"
                                         class="w-32 h-32 rounded-full object-cover border-4 mx-auto shadow-lg lazy"
                                         style="border-color: var(--success);"
                                         id="profileImage"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTI4IiBoZWlnaHQ9IjEyOCIgdmlld0JveD0iMCAwIDEyOCAxMjgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iNjQiIGN5PSI2NCIgcj0iNjQiIGZpbGw9IiMxMGI5ODEiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjQwIiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPnt7IGluaXRpYWxzIH19PC90ZXh0Pjwvc3ZnPg=='">
                                </div>
                            @else
                                <div class="w-32 h-32 rounded-full flex items-center justify-center font-semibold text-white text-3xl mx-auto shadow-lg"
                                     style="background-color: var(--success);"
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
                                {{ $user->type_name ?? 'Developer' }}
                            </span>
                        </div>

                        <!-- Photo Actions -->
                        <div class="space-y-2 mb-4" id="photoActions">
                            <form id="updatePhotoForm" enctype="multipart/form-data" class="hidden">
                                @csrf
                                <input type="file" name="photo" id="photoInput" accept="image/*" class="hidden">
                            </form>
                            
                            <button onclick="document.getElementById('photoInput').click()" 
                                    class="btn-success btn-sm w-full" id="uploadBtn">
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
                                <div class="text-sm" style="color: var(--text-secondary);">Last Login</div>
                                <div class="font-semibold" style="color: var(--text-primary);">
                                    {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                </div>
                            </div>
                            <i class="fas fa-sign-in-alt text-xl" style="color: var(--info);"></i>
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
                    
                    <!-- Developer Tools Link -->
                    <a href="{{ route('developer.tools') }}" 
                       class="mt-4 btn-outline btn-sm w-full text-center">
                        <i class="fas fa-tools mr-2"></i>Developer Tools
                    </a>
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
                        <button type="button" data-tab="developer" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-code mr-2"></i>Developer
                        </button>
                    </nav>
                </div>

                <!-- Tab Content Container -->
                <div id="tabContent">
                    <!-- Personal Information Form -->
                    <div class="tab-pane active" id="personalPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Personal Information</h3>
                            
                            <form action="{{ route('developer.profile.personal.update') }}" method="POST" id="personalInfoForm">
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
                        
                                        <!-- Region -->
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
                                    </div>
                                </div>
                        
                                <!-- Form Actions -->
                                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('contact')" class="btn-secondary">
                                        Next: Contact Info
                                    </button>
                                    <button type="submit" class="btn-success" id="personalSubmitBtn">
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
                            
                            <form action="{{ route('developer.profile.contact.update') }}" method="POST" id="contactInfoForm">
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
                                            <input type="email" name="email" id="emailAddress"
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
                                        <button type="submit" class="btn-success" id="contactSubmitBtn">
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
                            
                            <form action="{{ route('developer.profile.password.update') }}" method="POST" id="passwordFormElement">
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
                                            <button type="button" onclick="showTab('developer')" class="btn-outline">
                                                Next: Developer
                                            </button>
                                            <button type="submit" class="btn-success" id="passwordSubmitBtn">
                                                <i class="fas fa-key mr-2"></i>Change Password
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Developer Settings Form -->
                    <div class="tab-pane hidden" id="developerPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Developer Settings</h3>
                            
                            <form action="{{ route('developer.profile.developer.update') }}" method="POST" id="developerFormElement">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- API Access -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">API Access</h4>
                                        <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                            <div class="flex items-center justify-between mb-3">
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">API Key Status</div>
                                                    <div class="text-sm" style="color: var(--text-secondary);">Manage your API access credentials</div>
                                                </div>
                                                @if($user->metadata['api_key'] ?? false)
                                                    <span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                                        <i class="fas fa-check mr-1"></i>Active
                                                    </span>
                                                @else
                                                    <span class="px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Inactive
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                        Generate API Key
                                                    </label>
                                                    <button type="button" onclick="generateApiKey()" class="btn-outline w-full">
                                                        <i class="fas fa-key mr-2"></i>Generate New Key
                                                    </button>
                                                </div>
                                                
                                                <div>
                                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                        API Access Level
                                                    </label>
                                                    <select name="api_access_level" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                        <option value="read" {{ ($user->metadata['api_access_level'] ?? 'read') == 'read' ? 'selected' : '' }}>Read Only</option>
                                                        <option value="write" {{ ($user->metadata['api_access_level'] ?? '') == 'write' ? 'selected' : '' }}>Read & Write</option>
                                                        <option value="admin" {{ ($user->metadata['api_access_level'] ?? '') == 'admin' ? 'selected' : '' }}>Admin Access</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- API Key Display -->
                                            @if($user->metadata['api_key'] ?? false)
                                                <div class="mt-4 p-3 rounded border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: var(--border-color);">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-sm font-medium" style="color: var(--text-primary);">Current API Key</span>
                                                        <button type="button" onclick="toggleApiKeyVisibility()" class="text-xs" style="color: var(--primary);">
                                                            <i class="fas fa-eye mr-1"></i> Show
                                                        </button>
                                                    </div>
                                                    <div class="font-mono text-sm bg-gray-800 text-gray-300 p-2 rounded overflow-x-auto hidden" id="apiKeyDisplay">
                                                        {{ $user->metadata['api_key'] }}
                                                    </div>
                                                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Keep this key secure. Do not share it publicly.
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Development Preferences -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Development Preferences</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Default Environment
                                                </label>
                                                <select name="default_environment" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                    <option value="local" {{ ($user->metadata['default_environment'] ?? 'local') == 'local' ? 'selected' : '' }}>Local</option>
                                                    <option value="staging" {{ ($user->metadata['default_environment'] ?? '') == 'staging' ? 'selected' : '' }}>Staging</option>
                                                    <option value="production" {{ ($user->metadata['default_environment'] ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                                                </select>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Debug Mode
                                                </label>
                                                <div class="toggle-container">
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" name="debug_mode" value="1" 
                                                               {{ ($user->metadata['debug_mode'] ?? false) ? 'checked' : '' }}>
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                </div>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    Enable for detailed error logging and debugging
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notification Preferences -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Developer Notifications</h4>
                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">System Alerts</div>
                                                    <div class="text-xs" style="color: var(--text-secondary);">Receive system error alerts</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="system_alerts" value="1" 
                                                           {{ ($user->metadata['system_alerts'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">API Changes</div>
                                                    <div class="text-xs" style="color: var(--text-secondary);">Notify about API changes</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="api_change_notifications" value="1" 
                                                           {{ ($user->metadata['api_change_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">Security Alerts</div>
                                                    <div class="text-xs" style="color: var(--text-secondary);">Security-related notifications</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="security_alerts" value="1" 
                                                           {{ ($user->metadata['security_alerts'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('password')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <button type="submit" class="btn-success" id="developerSubmitBtn">
                                        <i class="fas fa-code mr-2"></i>Save Developer Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Information Card -->
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Account Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <table class="w-full">
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="py-2 font-medium" style="color: var(--text-primary);">User Type:</td>
                                    <td class="py-2">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            {{ $user->type_name ?? 'Developer' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="py-2 font-medium" style="color: var(--text-primary);">Account Status:</td>
                                    <td class="py-2">
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
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="py-2 font-medium" style="color: var(--text-primary);">Registration Date:</td>
                                    <td class="py-2" style="color: var(--text-secondary);">{{ $user->created_at->format('M j, Y') }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div>
                            <table class="w-full">
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="py-2 font-medium" style="color: var(--text-primary);">Last Login:</td>
                                    <td class="py-2" style="color: var(--text-secondary);">
                                        {{ $user->last_login_at ? $user->last_login_at->format('M j, Y g:i A') : 'Never' }}
                                    </td>
                                </tr>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="py-2 font-medium" style="color: var(--text-primary);">Last Activity:</td>
                                    <td class="py-2" style="color: var(--text-secondary);">
                                        {{ $user->last_activity_at ? $user->last_activity_at->format('M j, Y g:i A') : 'Never' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-2 font-medium" style="color: var(--text-primary);">Account Age:</td>
                                    <td class="py-2" style="color: var(--text-secondary);">
                                        {{ $profileStats['account_age_days'] ?? 0 }} days
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Developer Actions -->
                    <div class="flex space-x-3 mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <button onclick="getProfileStats()" class="btn-outline flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>View Stats
                        </button>
                        <a href="{{ route('developer.profile.download-data') }}" class="btn-outline flex items-center">
                            <i class="fas fa-download mr-2"></i>Download Data
                        </a>
                        <a href="{{ route('developer.tools') }}" class="btn-primary flex items-center ml-auto">
                            <i class="fas fa-tools mr-2"></i>Developer Tools
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
<script>
// Timer variables
let countdownInterval = null;
let timerSeconds = 600; // 10 minutes = 600 seconds
let emailResendTimeout = null;

// Tab Management
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
    // Hide all tab panes
    const tabPanes = document.querySelectorAll('.tab-pane');
    tabPanes.forEach(pane => {
        pane.classList.remove('active');
        pane.classList.add('hidden');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('#tabNav .tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active-tab');
        button.style.borderColor = 'transparent';
        button.style.color = 'var(--text-secondary)';
    });
    
    // Show selected tab pane
    const selectedPane = document.getElementById(tabName + 'Pane');
    if (selectedPane) {
        selectedPane.classList.remove('hidden');
        selectedPane.classList.add('active');
    }
    
    // Highlight selected tab button
    const selectedButton = document.querySelector(`#tabNav .tab-button[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active-tab');
        selectedButton.style.borderColor = 'var(--primary)';
        selectedButton.style.color = 'var(--primary)';
    }
}

// Photo Management
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                uploadPhoto(this.files[0]);
            }
        });
    }
    
    // Start timer if verification section exists and phone is unverified
    @if(!$user->phone_verified_at && $user->phone)
    if (localStorage.getItem('developer_verification_sent')) {
        const sentAt = parseInt(localStorage.getItem('developer_verification_sent'));
        const elapsed = Math.floor((Date.now() - sentAt) / 1000);
        const remaining = Math.max(0, timerSeconds - elapsed);
        if (remaining > 0) {
            startTimer(remaining);
        }
    }
    @endif
    
    // Check for email verification status from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('email_verified') === '1') {
        showToast('success', 'Email verified successfully!');
        // Remove the parameter from URL
        window.history.replaceState({}, document.title, window.location.pathname);
        // Reload to update verification status
        setTimeout(() => window.location.reload(), 2000);
    }
    
    // Initialize email verification button state
    initEmailVerificationButton();
});

function uploadPhoto(file) {
    const uploadBtn = document.getElementById('uploadBtn');
    const originalText = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
    
    const uploadProgress = document.getElementById('uploadProgress');
    if (uploadProgress) uploadProgress.classList.remove('hidden');
    
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('_token', '{{ csrf_token() }}');
    
    fetch('{{ route("developer.profile.photo.update") }}', {
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
    
    fetch('{{ route("developer.profile.photo.remove") }}', {
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
    
    // Validate phone number format
    if (!phoneNumber.match(/^\+?[0-9]{10,15}$/)) {
        showToast('error', 'Please enter a valid phone number with country code (e.g., +233XXXXXXXXX)');
        return;
    }
    
    showToast('info', 'Sending verification code...');
    
    // Disable send button
    const sendBtn = document.querySelector('#contactPane .bg-yellow-500');
    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending...';
    }
    
    fetch('{{ route("developer.profile.phone.verify.send") }}', {
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
            localStorage.setItem('developer_verification_sent', Date.now().toString());
            startTimer(timerSeconds);
            
            // Focus on verification code input
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
    })
    .finally(() => {
        if (sendBtn) {
            setTimeout(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-sms mr-1"></i>Send Code';
            }, 30000);
        }
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
    
    // Disable verify button
    const verifyBtn = document.querySelector('#contactPane .bg-green-500');
    if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Verifying...';
    }
    
    fetch('{{ route("developer.profile.phone.verify") }}', {
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
            
            // Clear timer
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            
            // Update verification section
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
            
            // Update verification status in sidebar
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
    })
    .finally(() => {
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Verify';
        }
    });
}

function resendVerificationCode() {
    const resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        resendBtn.disabled = true;
        resendBtn.style.opacity = '0.5';
    }
    
    sendPhoneVerification();
    
    // Re-enable after 30 seconds
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

// Email Verification - UPDATED with proper implementation
function initEmailVerificationButton() {
    // Check if email verification was recently sent
    const lastEmailSent = localStorage.getItem('email_verification_sent');
    if (lastEmailSent) {
        const elapsed = Math.floor((Date.now() - parseInt(lastEmailSent)) / 1000);
        const remaining = Math.max(0, 60 - elapsed); // 60 seconds cooldown
        if (remaining > 0) {
            const verifyBtn = document.querySelector('#contactPane .bg-yellow-500, #contactPane .px-4.bg-yellow-500');
            if (verifyBtn && verifyBtn.innerHTML.includes('Verify')) {
                verifyBtn.disabled = true;
                verifyBtn.innerHTML = `<i class="fas fa-clock mr-1"></i>Wait ${remaining}s`;
                
                const timer = setInterval(() => {
                    const newRemaining = Math.max(0, 60 - Math.floor((Date.now() - parseInt(lastEmailSent)) / 1000));
                    if (newRemaining <= 0) {
                        clearInterval(timer);
                        verifyBtn.disabled = false;
                        verifyBtn.innerHTML = '<i class="fas fa-envelope mr-1"></i>Verify';
                    } else {
                        verifyBtn.innerHTML = `<i class="fas fa-clock mr-1"></i>Wait ${newRemaining}s`;
                    }
                }, 1000);
            }
        }
    }
}

function sendEmailVerification() {
    const emailInput = document.getElementById('emailAddress');
    const email = emailInput?.value;
    
    if (!email) {
        showToast('error', 'Please enter your email address first.');
        emailInput?.focus();
        return;
    }
    
    // Validate email format
    if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        showToast('error', 'Please enter a valid email address.');
        emailInput?.focus();
        return;
    }
    
    showToast('info', 'Sending verification email...');
    
    // Disable the verify button to prevent multiple requests
    const verifyBtn = document.querySelector('#contactPane .bg-yellow-500, #contactPane .px-4.bg-yellow-500');
    const originalText = verifyBtn?.innerHTML;
    
    if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending...';
    }
    
    fetch('{{ route("developer.profile.email.verify.send") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ email: email })
    })
    .then(async response => {
        let data;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Invalid response from server');
        }
        
        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            showToast('success', 'Verification email sent! Please check your inbox and spam folder.');
            
            // Store in localStorage when verification was sent (for cooldown)
            localStorage.setItem('email_verification_sent', Date.now().toString());
            
            // Show additional info about checking spam
            setTimeout(() => {
                showToast('info', '💡 Tip: Check your spam/junk folder if you don\'t see the email in your inbox.');
            }, 3000);
            
            // Start cooldown timer for resend
            let remaining = 60;
            const cooldownTimer = setInterval(() => {
                remaining--;
                if (verifyBtn && remaining > 0) {
                    verifyBtn.innerHTML = `<i class="fas fa-clock mr-1"></i>Wait ${remaining}s`;
                }
                if (remaining <= 0) {
                    clearInterval(cooldownTimer);
                    if (verifyBtn) {
                        verifyBtn.disabled = false;
                        verifyBtn.innerHTML = '<i class="fas fa-envelope mr-1"></i>Verify';
                    }
                }
            }, 1000);
        } else {
            showToast('error', data.message || 'Failed to send verification email. Please try again.');
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = originalText || '<i class="fas fa-envelope mr-1"></i>Verify';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', error.message || 'An error occurred. Please try again.');
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = originalText || '<i class="fas fa-envelope mr-1"></i>Verify';
        }
    });
}

// Password Functions
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const button = input.parentElement?.querySelector('button');
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    if (button) {
        if (type === 'text') {
            button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        } else {
            button.innerHTML = '<i class="fas fa-eye"></i> Show';
        }
    }
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
        /[a-z]/.test(password) && /[A-Z]/.test(password),
        /[0-9]/.test(password),
        /[@$!%*?&]/.test(password)
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

// Developer Functions
function generateApiKey() {
    if (!confirm('⚠️ Warning: Generating a new API key will invalidate the old one. Any applications using the old key will stop working. Continue?')) return;
    
    showToast('info', 'Generating new API key...');
    
    fetch('{{ route("developer.profile.api.generate") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'New API key generated successfully!');
            if (data.api_key) {
                const apiKeyDisplay = document.getElementById('apiKeyDisplay');
                if (apiKeyDisplay) {
                    apiKeyDisplay.textContent = data.api_key;
                    apiKeyDisplay.classList.remove('hidden');
                }
                // Copy to clipboard option
                if (confirm('Would you like to copy the new API key to clipboard?')) {
                    navigator.clipboard.writeText(data.api_key).then(() => {
                        showToast('success', 'API key copied to clipboard!');
                    });
                }
            }
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to generate API key.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while generating API key.');
    });
}

function toggleApiKeyVisibility() {
    const apiKeyDisplay = document.getElementById('apiKeyDisplay');
    const button = event.target.closest('button');
    
    if (apiKeyDisplay.classList.contains('hidden')) {
        apiKeyDisplay.classList.remove('hidden');
        if (button) button.innerHTML = '<i class="fas fa-eye-slash mr-1"></i> Hide';
    } else {
        apiKeyDisplay.classList.add('hidden');
        if (button) button.innerHTML = '<i class="fas fa-eye mr-1"></i> Show';
    }
}

function getProfileStats() {
    showToast('info', 'Loading statistics...');
    
    fetch('{{ route("developer.profile.stats") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Profile statistics loaded successfully!');
                console.log('Profile Stats:', data.stats);
                // Display stats in console for debugging
                console.table(data.stats);
            } else {
                showToast('error', data.message || 'Failed to load statistics.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while loading statistics.');
        });
}

// Form Submission Handling
function setupForms() {
    const forms = ['personalInfoForm', 'contactInfoForm', 'passwordFormElement', 'developerFormElement'];
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = this.querySelector('button[type="submit"]');
                if (!submitBtn) return;
                
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                
                fetch(this.action, {
                    method: this.method,
                    body: new FormData(this),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(async response => {
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        throw new Error('Invalid server response');
                    }
                    
                    if (!response.ok) {
                        throw new Error(data.message || `HTTP ${response.status}`);
                    }
                    return data;
                })
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
                    showToast('error', error.message || 'An error occurred while saving.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }
    });
}

// Utility Functions
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    // Limit to 5 toasts at a time
    while (container.children.length >= 5) {
        container.removeChild(container.firstChild);
    }
    
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-yellow-500'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    const bgColor = colors[type] || 'bg-gray-500';
    const icon = icons[type] || 'fa-bell';
    
    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300 mb-2`;
    toast.style.minWidth = '300px';
    toast.style.maxWidth = '400px';
    toast.innerHTML = `
        <i class="fas ${icon} mr-3 text-lg"></i>
        <span class="flex-1">${escapeHtml(message)}</span>
        <button onclick="this.parentElement.remove()" class="ml-3 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateProfileCompletion(percentage) {
    const completionElements = document.querySelectorAll('[data-profile-completion]');
    completionElements.forEach(el => {
        el.textContent = `${percentage}%`;
        // Add animation effect
        el.style.transform = 'scale(1.1)';
        setTimeout(() => {
            el.style.transform = 'scale(1)';
        }, 200);
    });
    
    // Update any progress bars
    const progressBar = document.querySelector('.profile-progress-bar');
    if (progressBar) {
        progressBar.style.width = `${percentage}%`;
        progressBar.setAttribute('aria-valuenow', percentage);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setupForms();
    
    // Initialize toggle switches state
    document.querySelectorAll('.toggle-switch input').forEach(toggle => {
        if (toggle.checked) {
            toggle.closest('.toggle-switch')?.classList.add('active');
        }
        
        // Add change event for better UX
        toggle.addEventListener('change', function() {
            const toggleSwitch = this.closest('.toggle-switch');
            if (this.checked) {
                toggleSwitch?.classList.add('active');
            } else {
                toggleSwitch?.classList.remove('active');
            }
        });
    });
    
    // Add password confirmation validation
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    if (newPassword && confirmPassword) {
        function validatePasswordMatch() {
            if (confirmPassword.value.length > 0) {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                    confirmPassword.style.borderColor = '#ef4444';
                } else {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.style.borderColor = 'var(--border-color)';
                }
            }
        }
        
        newPassword.addEventListener('change', validatePasswordMatch);
        confirmPassword.addEventListener('keyup', validatePasswordMatch);
    }
    
    // Add phone number formatting
    const phoneInput = document.getElementById('phoneNumber');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = this.value.replace(/[^0-9+]/g, '');
            if (value.length > 0 && !value.startsWith('+')) {
                if (value.startsWith('0')) {
                    value = '+233' + value.substring(1);
                } else if (value.length === 9) {
                    value = '+233' + value;
                }
            }
            this.value = value;
        });
    }
    
    // Log successful page load
    console.log('Developer profile page loaded successfully', {
        user_id: {{ $user->id }},
        email_verified: {{ $user->email_verified_at ? 'true' : 'false' }},
        phone_verified: {{ $user->phone_verified_at ? 'true' : 'false' }}
    });
});

// Handle before unload to prevent data loss
let formModified = false;
document.querySelectorAll('form input, form select, form textarea').forEach(field => {
    field.addEventListener('change', () => {
        formModified = true;
    });
});

window.addEventListener('beforeunload', (e) => {
    if (formModified) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Function to reset form modified flag after successful save
function resetFormModified() {
    formModified = false;
}
</script>

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

/* Toast animations */
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

.toast {
    animation: slideInRight 0.3s ease-out;
    min-width: 300px;
}

/* Toggle Switch Styles */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: var(--success, #10b981);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(26px);
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

/* Spinner animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Button styles */
.btn-primary, .btn-secondary, .btn-danger, .btn-outline, .btn-success {
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

.btn-success {
    background-color: var(--success, #10b981);
    color: white;
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border-color: var(--border-color);
}

.btn-outline {
    background-color: transparent;
    color: var(--primary);
    border-color: var(--primary);
}

.btn-danger {
    background-color: var(--danger, #ef4444);
    color: white;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
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
</style>
@endpush