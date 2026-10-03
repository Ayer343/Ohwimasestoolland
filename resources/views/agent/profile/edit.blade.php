@extends('layouts.field')

@section('title', 'Edit Profile - Field Agent')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header with Stats -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">Field Agent Profile</h1>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Manage your field agent account and assignment information</p>
                </div>
                
                <!-- Profile Completion & Status -->
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
                    
                    <a href="{{ route('field-agent.dashboard') }}" class="btn-secondary flex items-center">
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
            <!-- Left Column - Profile & Stats -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Photo Card -->
                <div class="card p-6">
                    <div class="text-center">
                        <!-- Profile Photo with Lazy Loading -->
                        <div class="relative inline-block mb-4">
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
                                     style="background: linear-gradient(135deg, var(--primary), var(--success));"
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

                        <!-- Photo Actions -->
                        <div class="space-y-2">
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

                <!-- Agent Performance Card -->
                <div class="card p-6">
                    <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Agent Performance</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-2xl font-bold" style="color: var(--text-primary);">
                                    {{ $user->assignedPlans()->count() }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Total Assignments</div>
                            </div>
                            <i class="fas fa-tasks text-2xl" style="color: var(--primary);"></i>
                        </div>
                        
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-xl font-bold text-green-500">
                                    {{ $user->properties()->count() }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Properties Registered</div>
                            </div>
                            <i class="fas fa-home text-xl text-green-500"></i>
                        </div>
                        
                        <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-xl font-bold text-blue-500">
                                    {{ $activeAssignments ?? 0 }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Active Plans</div>
                            </div>
                            <i class="fas fa-chart-line text-xl text-blue-500"></i>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-xl font-bold text-purple-500">
                                    {{ $completionRate ?? '0' }}%
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Completion Rate</div>
                            </div>
                            <i class="fas fa-percent text-xl text-purple-500"></i>
                        </div>
                    </div>
                    
                    @if(Route::has('agent.assignments'))
                        <a href="{{ route('agent.assignments') }}" class="mt-4 btn-outline btn-sm w-full text-center">
                            <i class="fas fa-external-link-alt mr-2"></i>View Assignments
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
                                <div class="flex space-x-2">
                                    @if($user->phone)
                                        <button onclick="sendPhoneVerification()" 
                                                class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600 hover:bg-yellow-200">
                                            <i class="fas fa-sms mr-1"></i>Send Code
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-500">Add phone first</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- ID Verification -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-id-card" style="color: var(--text-secondary);"></i>
                                <span class="text-sm" style="color: var(--text-secondary);">ID Verification</span>
                            </div>
                            @if($user->metadata['id_verified'] ?? false)
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-check mr-1"></i>Verified
                                </span>
                            @else
                                <button onclick="uploadIdDocument()" 
                                        class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600 hover:bg-yellow-200">
                                    <i class="fas fa-upload mr-1"></i>Upload
                                </button>
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
                    
                    <!-- Quick Actions -->
                    <div class="space-y-2 mt-4">
                        <button onclick="getProfileStats()" 
                               class="btn-outline btn-sm w-full text-center flex items-center justify-center">
                            <i class="fas fa-chart-bar mr-2"></i>View Statistics
                        </button>
                        <a href="#" 
                           class="btn-outline btn-sm w-full text-center flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i>Download Data
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column - Forms -->
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
                        <button type="button" data-tab="agent" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-id-badge mr-2"></i>Agent Details
                        </button>
                        <button type="button" data-tab="notifications" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-bell mr-2"></i>Notifications
                        </button>
                        <button type="button" data-tab="security" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-shield-alt mr-2"></i>Security
                        </button>
                        <button type="button" data-tab="password" 
                                class="tab-button py-2 px-4 font-medium text-sm border-b-2 transition-colors whitespace-nowrap">
                            <i class="fas fa-key mr-2"></i>Password
                        </button>
                    </nav>
                </div>

                <!-- Tab Content Container -->
                <div id="tabContent">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane active" id="personalPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Personal Information</h3>
                            
                            <form action="{{ route('agent.profile.personal.update') }}" method="POST" id="personalInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Full Name *
                                                <span class="text-xs text-gray-500">(As on official ID)</span>
                                            </label>
                                            <input type="text" name="name" value="{{ old('name', $user->name) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required>
                                            @error('name')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Username *
                                                <span class="text-xs text-gray-500">(For login and reports)</span>
                                            </label>
                                            <input type="text" name="username" 
                                                   value="{{ old('username', $user->username) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Used for login and appearing in reports
                                            </div>
                                            @error('username')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>

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

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Date of Birth</label>
                                            <input type="date" name="dob" 
                                                   value="{{ old('dob', $user->dob ? $user->dob->format('Y-m-d') : '') }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   max="{{ date('Y-m-d') }}">
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Required for age verification
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                National ID Number
                                                <span class="text-xs text-gray-500">(Optional)</span>
                                            </label>
                                            <input type="text" name="national_id" 
                                                   value="{{ old('national_id', $user->metadata['national_id'] ?? '') }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="GHA-XXXXXXXX-X">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Emergency Contact
                                                <span class="text-xs text-gray-500">(Optional)</span>
                                            </label>
                                            <input type="text" name="emergency_contact" 
                                                   value="{{ old('emergency_contact', $user->metadata['emergency_contact'] ?? '') }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="Name and phone number">
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('contact')" class="btn-outline">
                                        Next: Contact Info
                                    </button>
                                    <button type="submit" class="btn-primary" id="personalSubmitBtn">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contact Information Tab -->
                    <div class="tab-pane hidden" id="contactPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Contact Information</h3>
                            
                            <form action="{{ route('agent.profile.contact.update') }}" method="POST" id="contactInfoForm">
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
                                            For important notifications and password recovery
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
                                                
                                                <div id="verificationStatus" class="mt-2 hidden"></div>
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Used for SMS notifications and field communications
                                        </div>
                                        @error('phone')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Location Fields -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Region
                                            </label>
                                            <input type="text" name="region" 
                                                   value="{{ old('region', $user->region) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="Your primary region">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Digital Address
                                                <span class="text-xs text-gray-500">(Ghana GPS)</span>
                                            </label>
                                            <input type="text" name="digital_address" 
                                                   value="{{ old('digital_address', $user->digital_address) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="e.g., GA-123-4567">
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Full Address</label>
                                            <input type="text" name="location" 
                                                   value="{{ old('location', $user->location) }}" 
                                                   class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="Street, City, Landmark">
                                        </div>
                                    </div>

                                    <!-- Alternative Contact -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Alternative Phone
                                            <span class="text-xs text-gray-500">(Optional)</span>
                                        </label>
                                        <input type="text" name="alt_phone" 
                                               value="{{ old('alt_phone', $user->metadata['alt_phone'] ?? '') }}" 
                                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               placeholder="Alternative contact number">
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('personal')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" onclick="showTab('agent')" class="btn-outline">
                                            Next: Agent Details
                                        </button>
                                        <button type="submit" class="btn-primary" id="contactSubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Agent Details Tab -->
                    <div class="tab-pane hidden" id="agentPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Agent Details</h3>
                            
                            <form action="{{ route('agent.profile.agent.update') }}" method="POST" id="agentInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Agent Information -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Agent ID
                                                <span class="text-xs text-gray-500">(Auto-generated)</span>
                                            </label>
                                            <input type="text" value="{{ $user->agent_id ?? 'AG-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) }}" 
                                                   class="w-full p-3 border rounded-lg bg-gray-100"
                                                   style="color: var(--text-secondary); border-color: var(--border-color);"
                                                   disabled readonly>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Date of Joining
                                            </label>
                                            <input type="text" value="{{ $user->created_at->format('M d, Y') }}" 
                                                   class="w-full p-3 border rounded-lg bg-gray-100"
                                                   style="color: var(--text-secondary); border-color: var(--border-color);"
                                                   disabled readonly>
                                        </div>
                                    </div>

                                    <!-- Specializations -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Specializations
                                            <span class="text-xs text-gray-500">(Select all that apply)</span>
                                        </label>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            @php
                                                $specializations = ['residential', 'commercial', 'industrial', 'land', 'apartments', 'villas', 'townhouses', 'others'];
                                                $currentSpecializations = json_decode($user->metadata['specializations'] ?? '[]', true) ?? [];
                                            @endphp
                                            @foreach($specializations as $spec)
                                                <label class="flex items-center space-x-2 cursor-pointer">
                                                    <input type="checkbox" name="specializations[]" value="{{ $spec }}"
                                                           {{ in_array($spec, $currentSpecializations) ? 'checked' : '' }}
                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="text-sm capitalize" style="color: var(--text-secondary);">{{ $spec }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Work Preferences -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Work Preferences
                                        </label>
                                        <div class="space-y-3">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="checkbox" name="preferences[]" value="remote_areas"
                                                       {{ ($user->metadata['preferences']['remote_areas'] ?? false) ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm" style="color: var(--text-secondary);">Willing to work in remote areas</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="checkbox" name="preferences[]" value="weekend_work"
                                                       {{ ($user->metadata['preferences']['weekend_work'] ?? false) ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm" style="color: var(--text-secondary);">Available for weekend work</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="checkbox" name="preferences[]" value="evening_work"
                                                       {{ ($user->metadata['preferences']['evening_work'] ?? false) ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm" style="color: var(--text-secondary);">Available for evening work</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Equipment Information -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Equipment Available
                                            <span class="text-xs text-gray-500">(What equipment do you have?)</span>
                                        </label>
                                        <textarea name="equipment" rows="3"
                                                  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                  placeholder="e.g., Measuring tape, GPS device, Camera, Tablet...">{{ old('equipment', $user->metadata['equipment'] ?? '') }}</textarea>
                                    </div>

                                    <!-- Additional Notes -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Additional Notes
                                            <span class="text-xs text-gray-500">(Any additional information)</span>
                                        </label>
                                        <textarea name="agent_notes" rows="3"
                                                  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                  placeholder="Any special skills, certifications, or preferences...">{{ old('agent_notes', $user->metadata['agent_notes'] ?? '') }}</textarea>
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('contact')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" onclick="showTab('notifications')" class="btn-outline">
                                            Next: Notifications
                                        </button>
                                        <button type="submit" class="btn-primary" id="agentSubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Notifications Settings Tab -->
                    <div class="tab-pane hidden" id="notificationsPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Notification Settings</h3>
                            
                            <form action="{{ route('agent.profile.notifications.update') }}" method="POST" id="notificationsFormElement">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Field Notifications -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Field Notifications</h4>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">New Assignments</div>
                                                    <div class="toggle-description">Notify when you receive new field assignments</div>
                                                </div>
                                                <label class="toggle-switch success">
                                                    <input type="checkbox" name="new_assignment_notifications" value="1" 
                                                           {{ ($user->metadata['new_assignment_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Assignment Updates</div>
                                                    <div class="toggle-description">Changes to existing assignments</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="assignment_update_notifications" value="1" 
                                                           {{ ($user->metadata['assignment_update_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Deadline Reminders</div>
                                                    <div class="toggle-description">Reminders for upcoming assignment deadlines</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="deadline_notifications" value="1" 
                                                           {{ ($user->metadata['deadline_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Communication Preferences -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Communication</h4>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Team Messages</div>
                                                    <div class="toggle-description">Receive messages from team members</div>
                                                </div>
                                                <label class="toggle-switch success">
                                                    <input type="checkbox" name="team_message_notifications" value="1" 
                                                           {{ ($user->metadata['team_message_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">System Announcements</div>
                                                    <div class="toggle-description">Important platform announcements</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="announcement_notifications" value="1" 
                                                           {{ ($user->metadata['announcement_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Training Updates</div>
                                                    <div class="toggle-description">New training materials and updates</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="training_notifications" value="1" 
                                                           {{ ($user->metadata['training_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Performance & Feedback -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Performance & Feedback</h4>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Performance Reviews</div>
                                                    <div class="toggle-description">Notify about completed performance reviews</div>
                                                </div>
                                                <label class="toggle-switch success">
                                                    <input type="checkbox" name="performance_review_notifications" value="1" 
                                                           {{ ($user->metadata['performance_review_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Feedback Requests</div>
                                                    <div class="toggle-description">Requests for feedback on assignments</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="feedback_notifications" value="1" 
                                                           {{ ($user->metadata['feedback_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Recognition Notifications</div>
                                                    <div class="toggle-description">Milestones and achievements</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="recognition_notifications" value="1" 
                                                           {{ ($user->metadata['recognition_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notification Channels -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Notification Channels</h4>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Email Notifications</div>
                                                    <div class="toggle-description">Receive notifications via email</div>
                                                </div>
                                                <label class="toggle-switch success">
                                                    <input type="checkbox" name="email_notifications" value="1" 
                                                           {{ ($user->metadata['email_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">SMS Notifications</div>
                                                    <div class="toggle-description">Receive notifications via SMS (important only)</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="sms_notifications" value="1" 
                                                           {{ ($user->metadata['sms_notifications'] ?? false) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">In-App Notifications</div>
                                                    <div class="toggle-description">Show notifications within the app</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="in_app_notifications" value="1" 
                                                           {{ ($user->metadata['in_app_notifications'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('agent')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" onclick="showTab('security')" class="btn-outline">
                                            Next: Security
                                        </button>
                                        <button type="submit" class="btn-primary" id="notificationsSubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Notification Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-pane hidden" id="securityPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Security & Privacy</h3>
                            
                            <form action="{{ route('agent.profile.security.update') }}" method="POST" id="securityInfoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="space-y-6">
                                    <!-- Two-Factor Authentication -->
                                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                        <div class="flex justify-between items-center mb-3">
                                            <div>
                                                <h4 class="font-medium" style="color: var(--text-primary);">Two-Factor Authentication</h4>
                                                <p class="text-xs" style="color: var(--text-secondary);">Add an extra layer of security to your account</p>
                                            </div>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="two_factor_enabled" value="1"
                                                       {{ ($user->metadata['two_factor_enabled'] ?? false) ? 'checked' : '' }}>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Security Alerts -->
                                    <div class="p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                        <div class="flex justify-between items-center mb-3">
                                            <div>
                                                <h4 class="font-medium" style="color: var(--text-primary);">Login Alerts</h4>
                                                <p class="text-xs" style="color: var(--text-secondary);">Get notified of new logins to your account</p>
                                            </div>
                                            <label class="toggle-switch info">
                                                <input type="checkbox" name="login_alerts" value="1"
                                                       {{ ($user->metadata['login_alerts'] ?? true) ? 'checked' : '' }}>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Additional Security Notifications -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Security Notifications</h4>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Suspicious Activity</div>
                                                    <div class="toggle-description">Alert for suspicious login attempts</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="suspicious_activity_alerts" value="1"
                                                           {{ ($user->metadata['suspicious_activity_alerts'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Password Changes</div>
                                                    <div class="toggle-description">Notify when password is changed</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="password_change_alerts" value="1"
                                                           {{ ($user->metadata['password_change_alerts'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">New Device Logins</div>
                                                    <div class="toggle-description">Alert for logins from new devices</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="new_device_alerts" value="1"
                                                           {{ ($user->metadata['new_device_alerts'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Active Sessions -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Active Sessions</h4>
                                        <div class="space-y-3">
                                            @foreach($activeSessions ?? [] as $session)
                                            <div class="flex justify-between items-center p-3 border rounded-lg"
                                                 style="border-color: var(--border-color);">
                                                <div>
                                                    <div class="flex items-center space-x-2">
                                                        <i class="fas {{ $session['current'] ? 'fa-desktop text-green-500' : 'fa-mobile-alt text-blue-500' }}"></i>
                                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                                            {{ $session['device'] }}
                                                            @if($session['current'])
                                                            <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                                                Current
                                                            </span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        {{ $session['location'] }} • {{ $session['last_active'] }}
                                                    </div>
                                                </div>
                                                @if(!$session['current'])
                                                <button type="button" onclick="revokeSession('{{ $session['id'] }}')" 
                                                        class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </button>
                                                @endif
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Data Privacy -->
                                    <div>
                                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Data Privacy</h4>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Share Anonymous Usage Data</div>
                                                    <div class="toggle-description">Help improve the platform by sharing anonymous statistics</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="data_sharing" value="1"
                                                           {{ ($user->metadata['data_sharing'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Marketing Emails</div>
                                                    <div class="toggle-description">Receive updates about features, tips, and promotions</div>
                                                </div>
                                                <label class="toggle-switch info">
                                                    <input type="checkbox" name="marketing_emails" value="1"
                                                           {{ ($user->metadata['marketing_emails'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="toggle-item">
                                                <div class="toggle-content">
                                                    <div class="toggle-title">Location Tracking</div>
                                                    <div class="toggle-description">Allow location tracking during active assignments</div>
                                                </div>
                                                <label class="toggle-switch warning">
                                                    <input type="checkbox" name="location_tracking" value="1"
                                                           {{ ($user->metadata['location_tracking'] ?? true) ? 'checked' : '' }}>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showTab('notifications')" class="btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <div class="space-x-3">
                                        <button type="button" onclick="showTab('password')" class="btn-outline">
                                            Next: Password
                                        </button>
                                        <button type="submit" class="btn-primary" id="securitySubmitBtn">
                                            <i class="fas fa-save mr-2"></i>Save Security Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Tab -->
                    <div class="tab-pane hidden" id="passwordPane">
                        <div class="card p-6 mb-6">
                            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Change Password</h3>
                            
                            <!-- Password Strength Requirements -->
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
                            
                            <form action="{{ route('agent.profile.password.update') }}" method="POST" id="passwordFormElement">
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

                                    <div class="flex justify-between space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                                        <button type="button" onclick="showTab('security')" class="btn-secondary">
                                            <i class="fas fa-arrow-left mr-2"></i>Back
                                        </button>
                                        <button type="submit" class="btn-primary" id="passwordSubmitBtn">
                                            <i class="fas fa-key mr-2"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ID Upload Modal -->
<div id="idUploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6" style="color: var(--text-primary);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Upload ID Document</h3>
            <button onclick="closeIdUploadModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <p class="mb-4 text-sm" style="color: var(--text-secondary);">
            Upload a clear photo of your government-issued ID for verification.
        </p>
        
        <form id="idUploadForm" enctype="multipart/form-data" class="space-y-4">
            @csrf
            
            <div>
                <label class="block text-sm font-medium mb-2">ID Type</label>
                <select name="id_type" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">Select ID Type</option>
                    <option value="ghana_card">Ghana Card</option>
                    <option value="passport">Passport</option>
                    <option value="driver_license">Driver's License</option>
                    <option value="voter_id">Voter's ID</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-2">ID Number</label>
                <input type="text" name="id_number" 
                       class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Enter ID number as shown on document">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-2">Upload Document</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-500 transition-colors cursor-pointer"
                     onclick="document.getElementById('idDocumentInput').click()"
                     style="background-color: var(--bg-secondary);">
                    <input type="file" name="id_document" id="idDocumentInput" accept="image/*,.pdf" class="hidden">
                    <i class="fas fa-cloud-upload-alt text-4xl mb-4" style="color: var(--text-secondary);"></i>
                    <p class="text-sm mb-2" style="color: var(--text-primary);">Click to upload ID document</p>
                    <p class="text-xs" style="color: var(--text-secondary);">JPG, PNG, or PDF • Max 5MB</p>
                </div>
                <div id="idDocumentPreview" class="mt-4 hidden">
                    <p class="text-sm font-medium mb-2">Selected file:</p>
                    <div class="flex items-center justify-between p-3 border rounded-lg"
                         style="border-color: var(--border-color);">
                        <span id="idFileName" class="text-sm truncate"></span>
                        <button type="button" onclick="clearIdDocument()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="flex justify-end space-x-3 mt-6">
            <button onclick="closeIdUploadModal()" class="btn-secondary">Cancel</button>
            <button onclick="submitIdDocument()" class="btn-primary">Upload & Submit</button>
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

document.addEventListener('DOMContentLoaded', function() {
    console.log('Field Agent Profile page loaded');
    
    // Initialize tab system
    initTabs();
    
    // Set up photo upload
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                uploadPhoto(this.files[0]);
            }
        });
    }
    
    // Set up form submissions
    setupForms();
    
    // Start timer if verification section exists and phone is unverified
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
    const forms = ['personalInfoForm', 'contactInfoForm', 'agentInfoForm', 'notificationsFormElement', 'securityInfoForm', 'passwordFormElement'];
    
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
        body: new FormData(form),
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
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
            // Update form fields with new user data if provided
            if (data.user) {
                Object.keys(data.user).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'password') {
                        input.value = data.user[key];
                    }
                });
            }
        } else {
            showToast('error', data.message || 'Failed to save changes.');
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    const input = form.querySelector(`[name="${field}"]`);
                    if (input) {
                        input.classList.add('border-red-500');
                    }
                });
            }
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
    
    fetch('{{ route("agent.profile.photo.update") }}', {
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
    
    fetch('{{ route("agent.profile.photo.remove") }}', {
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
    
    fetch('{{ route("agent.profile.phone.verify.send") }}', {
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
    
    fetch('{{ route("agent.profile.phone.verify") }}', {
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
    
    fetch('{{ route("agent.profile.email.verify.send") }}', {
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

// ID Document Upload
function uploadIdDocument() {
    const modal = document.getElementById('idUploadModal');
    if (modal) modal.classList.remove('hidden');
}

function closeIdUploadModal() {
    const modal = document.getElementById('idUploadModal');
    if (modal) modal.classList.add('hidden');
}

document.getElementById('idDocumentInput')?.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const fileName = this.files[0].name;
        document.getElementById('idFileName').textContent = fileName;
        document.getElementById('idDocumentPreview').classList.remove('hidden');
    }
});

function clearIdDocument() {
    document.getElementById('idDocumentInput').value = '';
    document.getElementById('idDocumentPreview').classList.add('hidden');
}

function submitIdDocument() {
    const formData = new FormData(document.getElementById('idUploadForm'));
    
    if (!formData.get('id_type')) {
        showToast('error', 'Please select ID type.');
        return;
    }
    
    if (!formData.get('id_number')) {
        showToast('error', 'Please enter ID number.');
        return;
    }
    
    if (!formData.get('id_document')) {
        showToast('error', 'Please select a document to upload.');
        return;
    }
    
    fetch('{{ route("agent.profile.id.upload") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'ID document uploaded successfully! Verification pending.');
            closeIdUploadModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to upload ID document.');
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

// Session Management
function revokeSession(sessionId) {
    if (confirm('Are you sure you want to revoke this session?')) {
        fetch('{{ route("agent.profile.session.revoke") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ session_id: sessionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Session revoked successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message || 'Failed to revoke session.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred. Please try again.');
        });
    }
}

// Statistics Function
function getProfileStats() {
    showToast('info', 'Loading statistics...');
    
    fetch('{{ route("agent.profile.stats") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Profile statistics loaded (check console)');
                console.log('Profile Stats:', data.stats);
            } else {
                showToast('error', 'Failed to load statistics.');
            }
        })
        .catch(error => console.error('Error:', error));
}

// Toast Notification System
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

// Initialize toggle switches
document.querySelectorAll('.toggle-switch input').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const toggleSwitch = this.closest('.toggle-switch');
        if (this.checked) {
            toggleSwitch.classList.add('active');
        } else {
            toggleSwitch.classList.remove('active');
        }
    });
    
    // Set initial state
    if (toggle.checked) {
        toggle.closest('.toggle-switch')?.classList.add('active');
    }
});
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
    background: linear-gradient(to right, var(--primary), var(--success));
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

/* Toggle Switch Styles */
.toggle-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid;
    border-radius: 0.5rem;
    border-color: var(--border-color);
    background-color: var(--bg-secondary);
}

.toggle-content {
    flex: 1;
}

.toggle-title {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.toggle-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
    margin-left: 1rem;
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
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #10b981;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.toggle-switch.success input:checked + .toggle-slider {
    background-color: #10b981;
}

.toggle-switch.info input:checked + .toggle-slider {
    background-color: #3b82f6;
}

.toggle-switch.warning input:checked + .toggle-slider {
    background-color: #f59e0b;
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

/* Responsive */
@media (max-width: 768px) {
    .tab-button {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    
    .card {
        padding: 1rem !important;
    }
    
    .toggle-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .toggle-switch {
        align-self: flex-end;
    }
}

/* Error styling */
.border-red-500 {
    border-color: #ef4444 !important;
}
</style>
@endpush