@extends('layouts.app')

@section('title', 'Edit ' . $user->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center space-x-4">
                <!-- Profile Photo or Initials -->
                <div class="relative">
                    @if($user->has_photo)
                        <img src="{{ $user->photo_url }}" alt="{{ $user->name }}" 
                             class="w-12 h-12 rounded-full object-cover border-2" 
                             style="border-color: {{ $user->isFieldAgent() ? 'var(--info)' : ($user->isLandlord() ? 'var(--success)' : ($user->isTenant() ? 'var(--warning)' : 'var(--primary)')) }};">
                    @else
                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-semibold text-white text-sm"
                             style="background-color: {{ $user->isFieldAgent() ? 'var(--info)' : ($user->isLandlord() ? 'var(--success)' : ($user->isTenant() ? 'var(--warning)' : 'var(--primary)')) }};">
                            {{ $user->getInitials() }}
                        </div>
                    @endif
                    @if($user->has_photo)
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Edit {{ $user->name }}</h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ $user->type_name }} • {{ $user->status_with_color['label'] ?? ucfirst($user->status) }}
                        @if($user->is_phone_verified) • Phone Verified @endif
                        @if($user->has_photo) • Has Photo @endif
                        @if($user->is_security_supervisor) 
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                <i class="fas fa-user-tie mr-1"></i> Security Supervisor (Level {{ $user->security_supervisor_level ?? $user->supervisor_level ?? 0 }})
                            </span>
                        @endif
                        @if($user->is_sanitation_supervisor)
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                <i class="fas fa-trash-alt mr-1"></i> Sanitation Supervisor
                            </span>
                        @endif
                        @if($user->sanitationPersonnel)
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                <i class="fas fa-user mr-1"></i> {{ ucfirst($user->sanitationPersonnel->role ?? 'Worker') }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if($smsStatus['system_ready'] ?? false)
                <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-comment-alt mr-1"></i>
                    SMS Ready
                </div>
                @endif
                <a href="{{ route('admin.users.show', $user->id) }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Profile
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.users.update', $user->id) }}" method="POST" id="user-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-6">
            @if($errors->any())
                <div class="card p-6">
                    <div class="alert alert-danger">
                        <h4 class="font-bold mb-2">Validation Errors:</h4>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- Profile Photo Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Profile Photo</h3>
                    <i class="fas fa-camera text-2xl opacity-70" style="color: var(--info);"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Current Photo & Preview -->
                    <div class="md:col-span-1 flex flex-col items-center">
                        <div class="relative mb-4">
                            <div id="photo-preview" class="w-32 h-32 rounded-full border-4 overflow-hidden" 
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                @if($user->has_photo)
                                    <img id="photo-preview-image" src="{{ $user->photo_url }}" alt="Current photo" 
                                         class="w-full h-full object-cover">
                                @else
                                    <div id="photo-placeholder" class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-user text-4xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <img id="photo-preview-image" src="" alt="Photo preview" 
                                         class="w-full h-full object-cover hidden">
                                @endif
                            </div>
                            <div id="photo-loading" class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full hidden">
                                <i class="fas fa-spinner fa-spin text-white"></i>
                            </div>
                            @if($user->has_photo)
                                <div class="absolute -bottom-2 -right-2 w-6 h-6 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                            @endif
                        </div>
                        <div class="text-center">
                            <span id="photo-filename" class="text-sm" style="color: var(--text-secondary);">
                                @if($user->has_photo)
                                    Current photo
                                @else
                                    No photo uploaded
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Photo Upload Controls -->
                    <div class="md:col-span-2 space-y-4">
                        <div>
                            <label for="photo" class="block mb-2 font-medium" style="color: var(--text-primary);">Update Photo</label>
                            <div class="flex items-center space-x-4">
                                <label for="photo" class="btn-secondary cursor-pointer flex items-center">
                                    <i class="fas fa-upload mr-2"></i> Choose New Photo
                                    <input type="file" id="photo" name="photo" class="hidden" 
                                           accept="image/jpeg,image/png,image/jpg,image/gif">
                                </label>
                                @if($user->has_photo)
                                    <button type="button" id="remove-photo" class="btn-danger flex items-center">
                                        <i class="fas fa-trash mr-2"></i> Remove Photo
                                    </button>
                                @else
                                    <button type="button" id="remove-photo" class="btn-danger flex items-center hidden">
                                        <i class="fas fa-trash mr-2"></i> Remove
                                    </button>
                                @endif
                            </div>
                            <div class="text-sm mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Supported formats: JPEG, PNG, GIF. Max size: 5MB. Recommended: 300x300px square image.
                            </div>
                            @error('photo')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Remove Photo Option -->
                        @if($user->has_photo)
                        <div>
                            <label class="flex items-start space-x-3">
                                <input type="checkbox" id="remove_photo_checkbox" name="remove_photo" value="1" 
                                       class="mt-1 w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">Remove Current Photo</span>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Delete the current profile photo. User will see initials instead.
                                    </p>
                                </div>
                            </label>
                        </div>
                        @endif

                        <!-- Photo Requirements -->
                        <div class="p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                            <h4 class="font-semibold mb-2 text-sm" style="color: var(--text-primary);">Photo Guidelines:</h4>
                            <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-center">
                                    <i class="fas fa-check-circle mr-2 text-xs" style="color: var(--success);"></i>
                                    Clear, recent photo facing forward
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check-circle mr-2 text-xs" style="color: var(--success);"></i>
                                    Well-lit with neutral background
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check-circle mr-2 text-xs" style="color: var(--success);"></i>
                                    No filters or heavy editing
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check-circle mr-2 text-xs" style="color: var(--success);"></i>
                                    Professional appearance recommended
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Type & Status Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">User Type & Status</h3>
                    <i class="fas fa-user-cog text-2xl opacity-70" style="color: var(--primary);"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- User Type -->
                    <div>
                        <label for="type" class="block mb-2 font-medium" style="color: var(--text-primary);">User Type *</label>
                        <select class="w-full p-2 border rounded @error('type') border-red-500 @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                id="type" name="type" required>
                            @foreach($userTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('type', $user->attributes['type'] ?? $user->type) == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">Status *</label>
                        <select class="w-full p-2 border rounded @error('status') border-red-500 @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                id="status" name="status" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $user->status) == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Personal Information Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Personal Information</h3>
                    <i class="fas fa-id-card text-2xl opacity-70" style="color: var(--info);"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block mb-2 font-medium" style="color: var(--text-primary);">Full Name *</label>
                        <input type="text" class="w-full p-2 border rounded @error('name') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="name" name="name" value="{{ old('name', $user->name) }}" required 
                               placeholder="Enter full name">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block mb-2 font-medium" style="color: var(--text-primary);">Email Address *</label>
                        <input type="email" class="w-full p-2 border rounded @error('email') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="email" name="email" value="{{ old('email', $user->email) }}" required 
                               placeholder="Enter email address">
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block mb-2 font-medium" style="color: var(--text-primary);">Phone Number *</label>
                        <div class="relative">
                            <input type="tel" class="w-full p-2 border rounded @error('phone') border-red-500 @enderror" 
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                   id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required 
                                   placeholder="+233595652410">
                            <div id="phone-validation" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                <i class="fas fa-check" id="phone-valid-icon" style="color: var(--success);"></i>
                                <i class="fas fa-times" id="phone-invalid-icon" style="color: var(--danger);"></i>
                            </div>
                        </div>
                        <div class="text-sm mt-1 flex items-center justify-between">
                            <span style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Format: +[country code][number]
                            </span>
                            @if($user->is_phone_verified)
                                <span class="text-green-600 flex items-center">
                                    <i class="fas fa-check-circle mr-1"></i> Verified
                                </span>
                            @elseif($user->phone)
                                <span class="text-yellow-600 flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Not Verified
                                </span>
                            @endif
                        </div>
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Username -->
                    <div>
                        <label for="username" class="block mb-2 font-medium" style="color: var(--text-primary);">Username</label>
                        <input type="text" class="w-full p-2 border rounded @error('username') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="username" name="username" value="{{ old('username', $user->username) }}" 
                               placeholder="Optional username">
                        @error('username')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- ========== 🔐 SECURITY PERSONNEL SUPERVISOR SECTION ========== -->
            <!-- ============================================================ -->
            <div class="card p-6" id="security-supervisor-section" style="{{ $user->type != App\Models\User::TYPE_SECURITY_PERSONNEL ? 'display: none;' : '' }}">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold mr-3" style="color: var(--text-primary);">
                            <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                            Security Supervisor Settings
                        </h3>
                        <button type="button" id="security-supervisor-toggle-btn" 
                                class="relative inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-300 border-2 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style="background-color: {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'var(--success)' : 'var(--bg-secondary)' }};
                                       border-color: {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'var(--success)' : 'var(--border-color)' }};
                                       color: {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'white' : 'var(--text-secondary)' }};
                                       box-shadow: {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? '0 2px 8px rgba(var(--success-rgb), 0.3)' : 'none' }};">
                            <i class="fas {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-2 text-lg"></i>
                            <span class="toggle-text">{{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'Supervisor Enabled' : 'Can be Supervisor' }}</span>
                            <span class="ml-2 w-2 h-2 rounded-full {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'bg-white' : 'bg-gray-400' }} toggle-indicator"></span>
                        </button>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" 
                          style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        Security Personnel Only
                    </span>
                </div>
                
                <input type="checkbox" name="security_can_be_supervisor" id="security_can_be_supervisor" value="1" 
                       class="hidden" {{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? 'checked' : '' }}>

                <div id="security-supervisor-settings-content" class="{{ old('security_can_be_supervisor', $user->security_can_be_supervisor ?? false) ? '' : 'opacity-50 pointer-events-none' }} transition-all duration-300">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Configure Security Supervisor Capabilities</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    These settings determine if this security personnel can be assigned as a supervisor.
                                    Users must have "Can be Supervisor" checked to appear in supervisor assignment dropdowns.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Supervisor Level -->
                        <div>
                            <label for="security_supervisor_level" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-level-up-alt mr-1" style="color: var(--primary);"></i>
                                Security Supervisor Level
                            </label>
                            <select name="security_supervisor_level" id="security_supervisor_level" 
                                    class="w-full p-2 border rounded @error('security_supervisor_level') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="0" {{ old('security_supervisor_level', $user->security_supervisor_level ?? 0) == 0 ? 'selected' : '' }}>Not a Supervisor</option>
                                <option value="1" {{ old('security_supervisor_level', $user->security_supervisor_level ?? 0) == 1 ? 'selected' : '' }}>Team Lead (Level 1)</option>
                                <option value="2" {{ old('security_supervisor_level', $user->security_supervisor_level ?? 0) == 2 ? 'selected' : '' }}>Section Lead (Level 2)</option>
                                <option value="3" {{ old('security_supervisor_level', $user->security_supervisor_level ?? 0) == 3 ? 'selected' : '' }}>Post Commander (Level 3)</option>
                            </select>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Higher level grants more supervisory authority
                            </div>
                            @error('security_supervisor_level')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Supervisor Score -->
                        <div>
                            <label for="security_supervisor_score" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-star mr-1" style="color: var(--warning);"></i>
                                Security Supervisor Score (0-100)
                            </label>
                            <div class="relative">
                                <input type="number" name="security_supervisor_score" id="security_supervisor_score" 
                                       value="{{ old('security_supervisor_score', $user->security_supervisor_score ?? 0) }}" min="0" max="100" step="1"
                                       class="w-full p-2 border rounded @error('security_supervisor_score') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-sm" style="color: var(--text-secondary);">/100</span>
                                </div>
                            </div>
                            <div class="flex justify-between mt-1">
                                <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                    <div class="bg-yellow-400 h-1.5 rounded-full" id="security-score-preview" style="width: {{ old('security_supervisor_score', $user->security_supervisor_score ?? 0) }}%;"></div>
                                </div>
                            </div>
                            @error('security_supervisor_score')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Supervisor Certifications (JSON) -->
                        <div class="col-span-2">
                            <label for="security_supervisor_certifications" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-certificate mr-1" style="color: var(--primary);"></i>
                                Security Certifications (JSON Array)
                            </label>
                            <textarea name="security_supervisor_certifications" id="security_supervisor_certifications" rows="3"
                                      class="w-full p-2 border rounded font-mono text-xs @error('security_supervisor_certifications') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder='["first_aid", "fire_safety", "crowd_control", "emergency_response"]'>{{ 
                                old('security_supervisor_certifications', 
                                    is_array($user->security_supervisor_certifications ?? null) ? json_encode($user->security_supervisor_certifications, JSON_PRETTY_PRINT) : ($user->security_supervisor_certifications ?? '')
                                ) 
                            }}</textarea>
                            <div class="flex justify-between items-center mt-1">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Enter certifications as a JSON array. Example: ["first_aid", "fire_safety"]
                                </div>
                                <button type="button" id="security-validate-json" class="text-xs px-2 py-1 rounded border" 
                                        style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fas fa-check-circle mr-1"></i> Validate JSON
                                </button>
                            </div>
                            <div id="security-json-validation-result" class="text-xs mt-1 hidden"></div>
                            @error('security_supervisor_certifications')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Security Supervisor Level Description -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                            <div class="flex items-center mb-2">
                                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center text-xs font-bold mr-2">1</span>
                                <span class="font-medium" style="color: var(--text-primary);">Team Lead</span>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">Supervises a small security team, manages daily check-ins, approves shift swaps</p>
                        </div>
                        <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                            <div class="flex items-center mb-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-800 flex items-center justify-center text-xs font-bold mr-2">2</span>
                                <span class="font-medium" style="color: var(--text-primary);">Section Lead</span>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">Manages multiple security teams, handles overtime approvals, reviews incidents</p>
                        </div>
                        <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                            <div class="flex items-center mb-2">
                                <span class="w-6 h-6 rounded-full bg-purple-100 text-purple-800 flex items-center justify-center text-xs font-bold mr-2">3</span>
                                <span class="font-medium" style="color: var(--text-primary);">Post Commander</span>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">Full authority over entire security post, can edit schedules, manage all permissions</p>
                        </div>
                    </div>

                    <!-- Security Supervisor History (if any) -->
                    @if(!empty($user->metadata['security_supervisor_promotion_history']))
                    <div class="mt-6 p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                        <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-history mr-2" style="color: var(--info);"></i>
                            Security Supervisor Promotion History
                        </h4>
                        <div class="space-y-2 text-sm">
                            @foreach(array_reverse($user->metadata['security_supervisor_promotion_history']) as $history)
                                <div class="flex items-start space-x-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                                    <i class="fas fa-arrow-up text-green-500 mt-1"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">
                                            Level {{ $history['old_level'] ?? 0 }} → Level {{ $history['new_level'] }}
                                        </span>
                                        <span class="text-xs block" style="color: var(--text-secondary);">
                                            {{ \Carbon\Carbon::parse($history['promoted_at'])->format('M j, Y g:i A') }}
                                            @if(isset($history['promoted_by_name']))
                                                by {{ $history['promoted_by_name'] }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- ================================================================ -->
            <!-- ========== 🧹 SANITATION PERSONNEL SECTION ========== -->
            <!-- ================================================================ -->
            <div class="card p-6" id="sanitation-supervisor-section" style="{{ $user->type != App\Models\User::TYPE_SANITATION_PERSONNEL ? 'display: none;' : '' }}">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold mr-3" style="color: var(--text-primary);">
                            <i class="fas fa-trash-alt mr-2" style="color: var(--primary);"></i>
                            Sanitation Personnel Settings
                        </h3>
                        <button type="button" id="sanitation-supervisor-toggle-btn" 
                                class="relative inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-300 border-2 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style="background-color: {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'var(--success)' : 'var(--bg-secondary)' }};
                                       border-color: {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'var(--success)' : 'var(--border-color)' }};
                                       color: {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'white' : 'var(--text-secondary)' }};
                                       box-shadow: {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? '0 2px 8px rgba(var(--success-rgb), 0.3)' : 'none' }};">
                            <i class="fas {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-2 text-lg"></i>
                            <span class="toggle-text">{{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'Enabled' : 'Disabled' }}</span>
                            <span class="ml-2 w-2 h-2 rounded-full {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'bg-white' : 'bg-gray-400' }} toggle-indicator"></span>
                        </button>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" 
                          style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        Sanitation Personnel Only
                    </span>
                </div>
                
                <input type="checkbox" name="sanitation_can_be_supervisor" id="sanitation_can_be_supervisor" value="1" 
                       class="hidden" {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'checked' : '' }}>

                <div id="sanitation-supervisor-settings-content" class="{{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? '' : 'opacity-50 pointer-events-none' }} transition-all duration-300">
                    <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-4 mb-6 rounded-r-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-green-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm text-green-700 dark:text-green-300 font-medium">Configure Sanitation Personnel Settings</p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                    Configure the sanitation personnel role and status for this user.
                                    The role determines what features and permissions the user has.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Eligibility toggle and explanation -->
                    <div class="flex items-center justify-between p-4 rounded-lg mb-4" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable Sanitation Personnel</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary); max-width: 600px;">
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                When enabled, this user will have access to sanitation features based on their role.
                                <br>
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                Supervisors can manage workers and collection requests.
                            </p>
                        </div>
                        <div class="toggle-modern flex-shrink-0 ml-4">
                            <input type="checkbox" name="sanitation_can_be_supervisor_toggle" id="sanitation_can_be_supervisor_toggle" value="1" 
                                   {{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? 'checked' : '' }} class="sr-only">
                            <label for="sanitation_can_be_supervisor_toggle" class="toggle-slider"></label>
                        </div>
                    </div>

                    <!-- Sanitation Personnel Role Selection -->
                    <div id="sanitation-supervisor-role-section" class="{{ old('sanitation_can_be_supervisor', $user->sanitation_can_be_supervisor ?? $user->sanitationPersonnel?->is_active ?? false) ? '' : 'hidden' }} transition-all duration-300">
                        <!-- Personnel Role -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sanitation_personnel_role" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-user-tie mr-1" style="color: var(--primary);"></i>
                                    Personnel Role *
                                </label>
                                <select name="sanitation_personnel_role" id="sanitation_personnel_role" 
                                        class="w-full p-2 border rounded @error('sanitation_personnel_role') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="supervisor" {{ old('sanitation_personnel_role', $user->sanitationPersonnel?->role ?? 'supervisor') == 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                                    <option value="worker" {{ old('sanitation_personnel_role', $user->sanitationPersonnel?->role ?? '') == 'worker' ? 'selected' : '' }}>Worker</option>
                                    <option value="driver" {{ old('sanitation_personnel_role', $user->sanitationPersonnel?->role ?? '') == 'driver' ? 'selected' : '' }}>Driver</option>
                                </select>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Select the personnel role for this user
                                </div>
                                @error('sanitation_personnel_role')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sanitation_personnel_status" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-circle mr-1" style="color: var(--info);"></i>
                                    Personnel Status *
                                </label>
                                <select name="sanitation_personnel_status" id="sanitation_personnel_status" 
                                        class="w-full p-2 border rounded @error('sanitation_personnel_status') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="active" {{ old('sanitation_personnel_status', $user->sanitationPersonnel?->status ?? 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('sanitation_personnel_status', $user->sanitationPersonnel?->status ?? '') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="on_leave" {{ old('sanitation_personnel_status', $user->sanitationPersonnel?->status ?? '') == 'on_leave' ? 'selected' : '' }}>On Leave</option>
                                    <option value="suspended" {{ old('sanitation_personnel_status', $user->sanitationPersonnel?->status ?? '') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                </select>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Personnel status determines availability
                                </div>
                                @error('sanitation_personnel_status')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- ✅ NEW: Reports To (Supervisor) -->
                        <div class="mt-4">
                            <label for="sanitation_supervisor_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-1" style="color: var(--primary);"></i>
                                Reports To (Supervisor)
                            </label>
                            <select class="w-full p-2 border rounded @error('sanitation_supervisor_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    id="sanitation_supervisor_id" name="sanitation_supervisor_id">
                                <option value="">— No supervisor —</option>
                                @foreach(($sanitationSupervisors ?? []) as $supervisorId => $supervisorLabel)
                                    <option value="{{ $supervisorId }}" {{ old('sanitation_supervisor_id', $currentSupervisorId ?? null) == $supervisorId ? 'selected' : '' }}>
                                        {{ $supervisorLabel }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sanitation_supervisor_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Optional. Only personnel with a supervisor role appear here. The selected supervisor will be notified about this person's collection requests.
                            </div>
                        </div>

                        <!-- Additional Personnel Fields -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sanitation_vehicle_number" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-truck mr-1" style="color: var(--primary);"></i>
                                    Vehicle Number
                                </label>
                                <input type="text" name="sanitation_vehicle_number" id="sanitation_vehicle_number" 
                                       value="{{ old('sanitation_vehicle_number', $user->sanitationPersonnel?->vehicle_number ?? '') }}"
                                       class="w-full p-2 border rounded @error('sanitation_vehicle_number') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., GS-1234-23">
                                @error('sanitation_vehicle_number')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Required for drivers
                                </div>
                            </div>

                            <div>
                                <label for="sanitation_vehicle_type" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-car mr-1" style="color: var(--primary);"></i>
                                    Vehicle Type
                                </label>
                                <select name="sanitation_vehicle_type" id="sanitation_vehicle_type" 
                                        class="w-full p-2 border rounded @error('sanitation_vehicle_type') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="">Select Vehicle Type</option>
                                    <option value="truck" {{ old('sanitation_vehicle_type', $user->sanitationPersonnel?->vehicle_type ?? '') == 'truck' ? 'selected' : '' }}>Truck</option>
                                    <option value="van" {{ old('sanitation_vehicle_type', $user->sanitationPersonnel?->vehicle_type ?? '') == 'van' ? 'selected' : '' }}>Van</option>
                                    <option value="pickup" {{ old('sanitation_vehicle_type', $user->sanitationPersonnel?->vehicle_type ?? '') == 'pickup' ? 'selected' : '' }}>Pickup</option>
                                    <option value="compactor" {{ old('sanitation_vehicle_type', $user->sanitationPersonnel?->vehicle_type ?? '') == 'compactor' ? 'selected' : '' }}>Compactor</option>
                                </select>
                                @error('sanitation_vehicle_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Required for drivers
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="sanitation_emergency_contact" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-phone-alt mr-1" style="color: var(--danger);"></i>
                                Emergency Contact
                            </label>
                            <input type="text" name="sanitation_emergency_contact" id="sanitation_emergency_contact" 
                                   value="{{ old('sanitation_emergency_contact', $user->sanitationPersonnel?->emergency_contact ?? '') }}"
                                   class="w-full p-2 border rounded @error('sanitation_emergency_contact') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Name: +233XXXXXXXXX">
                            @error('sanitation_emergency_contact')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Format: Name: Phone Number (e.g., John Doe: +233241234567)
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="sanitation_hire_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-1" style="color: var(--info);"></i>
                                Hire Date
                            </label>
                            <input type="date" name="sanitation_hire_date" id="sanitation_hire_date" 
                                   value="{{ old('sanitation_hire_date', $user->sanitationPersonnel?->hire_date ? \Carbon\Carbon::parse($user->sanitationPersonnel->hire_date)->format('Y-m-d') : '') }}"
                                   class="w-full p-2 border rounded @error('sanitation_hire_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @error('sanitation_hire_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Defaults to today if not specified
                            </div>
                        </div>

                        <!-- Sanitation Role Descriptions -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">Supervisor</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">Manages workers and collection requests. Full access to personnel management.</p>
                            </div>
                            <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">Worker</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">Performs waste collection tasks. Can update request statuses.</p>
                            </div>
                            <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-truck mr-2" style="color: var(--primary);"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">Driver</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">Operates collection vehicles. Can update location and request statuses.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Info: What happens next -->
                    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">What's next?</p>
                                <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                    <li>• User will have access to sanitation features based on their role</li>
                                    <li>• Supervisors can manage workers and collection requests</li>
                                    <li>• Workers and drivers can perform collection tasks</li>
                                    <li>• Status determines availability for assignments</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Display current sanitation personnel info if exists -->
                    @if($user->sanitationPersonnel)
                    <div class="mt-4 p-4 rounded-lg border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
                        <h4 class="font-medium mb-2 flex items-center text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--success);"></i>
                            Current Sanitation Personnel Info
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs" style="color: var(--text-secondary);">
                            <div><strong>Employee ID:</strong> {{ $user->sanitationPersonnel->employee_id ?? 'N/A' }}</div>
                            <div><strong>Role:</strong> {{ ucfirst($user->sanitationPersonnel->role ?? 'N/A') }}</div>
                            <div><strong>Status:</strong> {{ ucfirst($user->sanitationPersonnel->status ?? 'N/A') }}</div>
                            @if($user->sanitationPersonnel->supervisor)
                            <div><strong>Supervisor:</strong> {{ $user->sanitationPersonnel->supervisor->full_name ?? 'N/A' }}</div>
                            @endif
                            @if($user->sanitationPersonnel->vehicle_number)
                            <div><strong>Vehicle:</strong> {{ $user->sanitationPersonnel->vehicle_number }}</div>
                            @endif
                            @if($user->sanitationPersonnel->hire_date)
                            <div><strong>Hire Date:</strong> {{ \Carbon\Carbon::parse($user->sanitationPersonnel->hire_date)->format('M j, Y') }}</div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Additional Information Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Additional Information</h3>
                    <i class="fas fa-info-circle text-2xl opacity-70" style="color: var(--warning);"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Gender -->
                    <div>
                        <label for="gender" class="block mb-2 font-medium" style="color: var(--text-primary);">Gender</label>
                        <select class="w-full p-2 border rounded @error('gender') border-red-500 @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                            <option value="other" {{ old('gender', $user->gender) == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('gender')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="dob" class="block mb-2 font-medium" style="color: var(--text-primary);">Date of Birth</label>
                        <input type="date" class="w-full p-2 border rounded @error('dob') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="dob" name="dob" value="{{ old('dob', $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : '') }}">
                        @error('dob')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Region -->
                    <div>
                        <label for="region" class="block mb-2 font-medium" style="color: var(--text-primary);">Region</label>
                        <input type="text" class="w-full p-2 border rounded @error('region') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="region" name="region" value="{{ old('region', $user->region) }}" 
                               placeholder="Enter region">
                        @error('region')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Location -->
                    <div class="md:col-span-2">
                        <label for="location" class="block mb-2 font-medium" style="color: var(--text-primary);">Location</label>
                        <input type="text" class="w-full p-2 border rounded @error('location') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="location" name="location" value="{{ old('location', $user->location) }}" 
                               placeholder="Enter full location address">
                        @error('location')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Digital Address -->
                    <div>
                        <label for="digital_address" class="block mb-2 font-medium" style="color: var(--text-primary);">Digital Address</label>
                        <input type="text" class="w-full p-2 border rounded @error('digital_address') border-red-500 @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               id="digital_address" name="digital_address" value="{{ old('digital_address', $user->digital_address) }}" 
                               placeholder="e.g., GA-123-4567">
                        @error('digital_address')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Phone Verification Options -->
            @if($user->phone)
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Phone Verification</h3>
                    <i class="fas fa-shield-alt text-2xl opacity-70" style="color: {{ $user->is_phone_verified ? 'var(--success)' : 'var(--warning)' }};"></i>
                </div>
                <div class="space-y-4">
                    @if($user->is_phone_verified)
                        <div class="flex items-center p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-check-circle mr-3 text-green-500"></i>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Phone Number Verified</p>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Verified on {{ $user->phone_verified_at ? $user->phone_verified_at->format('M j, Y g:i A') : 'Unknown' }}
                                </p>
                            </div>
                        </div>
                        <div>
                            <label class="flex items-start space-x-3">
                                <input type="checkbox" id="remove_phone_verification" name="remove_phone_verification" value="1" 
                                       class="mt-1 w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">Remove Phone Verification</span>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Mark phone as unverified. User will need to verify again.
                                    </p>
                                </div>
                            </label>
                        </div>
                    @else
                        <div class="flex items-center p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-exclamation-triangle mr-3 text-yellow-500"></i>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Phone Number Not Verified</p>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    User has not verified their phone number yet.
                                </p>
                            </div>
                        </div>
                        <div>
                            <label class="flex items-start space-x-3">
                                <input type="checkbox" id="auto_verify_phone" name="auto_verify_phone" value="1" 
                                       class="mt-1 w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">Mark Phone as Verified</span>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Verify phone number immediately without sending verification code.
                                    </p>
                                </div>
                            </label>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Form Actions -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status">
                        Editing {{ $user->name }} ({{ $user->type_name }})
                        @if($user->has_photo) • Has Photo @endif
                        @if($user->is_security_supervisor) • Security Supervisor (Level {{ $user->security_supervisor_level ?? $user->supervisor_level ?? 0 }}) @endif
                        @if($user->is_sanitation_supervisor) • Sanitation Supervisor @endif
                        @if($user->sanitationPersonnel) • {{ ucfirst($user->sanitationPersonnel->role ?? 'Worker') }} @endif
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary flex items-center">
                            <i class="fas fa-save mr-2"></i> Update User
                        </button>
                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Danger Zone Card -->
    <div class="card p-6 border-l-4" style="border-left-color: var(--danger);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Danger Zone</h3>
            <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--danger);"></i>
        </div>
        <div class="space-y-4">
            <!-- Change Password -->
            <div class="flex justify-between items-center p-4 rounded border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: var(--border-color);">
                <div>
                    <h4 class="font-medium" style="color: var(--text-primary);">Change Password</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Set a new password for this user. You can choose to notify them via SMS.
                    </p>
                </div>
                <button type="button" onclick="openPasswordModal()" class="btn-warning">
                    <i class="fas fa-key mr-2"></i> Change Password
                </button>
            </div>

            <!-- Delete Photo -->
            @if($user->has_photo)
            <div class="flex justify-between items-center p-4 rounded border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: var(--border-color);">
                <div>
                    <h4 class="font-medium" style="color: var(--text-primary);">Delete Profile Photo</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Remove the current profile photo permanently.
                    </p>
                </div>
                <button type="button" onclick="deletePhoto()" class="btn-warning">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Photo
                </button>
            </div>
            @endif

            <!-- Delete User -->
            @if($user->id !== auth()->id())
            <div class="flex justify-between items-center p-4 rounded border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.3);">
                <div>
                    <h4 class="font-medium" style="color: var(--text-primary);">Delete User Account</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Permanently delete this user account. This action cannot be undone.
                    </p>
                </div>
                <button onclick="deleteUser({{ $user->id }})" class="btn-danger">
                    <i class="fas fa-trash mr-2"></i> Delete User
                </button>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="password-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" style="background-color: var(--bg-primary); color: var(--text-primary);">
        <h3 class="text-lg font-semibold mb-4">Change Password for {{ $user->name }}</h3>
        <form id="password-form" action="{{ route('admin.users.update-password', $user->id) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="new_password" class="block mb-2 font-medium">New Password *</label>
                    <input type="password" class="w-full p-2 border rounded" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           id="new_password" name="password" required>
                </div>
                <div>
                    <label for="new_password_confirmation" class="block mb-2 font-medium">Confirm Password *</label>
                    <input type="password" class="w-full p-2 border rounded" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           id="new_password_confirmation" name="password_confirmation" required>
                </div>
                <div>
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" name="notify_user" value="1" 
                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                        <span class="font-medium">Notify user via SMS</span>
                    </label>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Send a notification to the user about the password change.
                    </p>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closePasswordModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Photo upload handling
function handlePhotoUpload(event) {
    const file = event.target.files[0];
    const photoPreviewImage = document.getElementById('photo-preview-image');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    const photoFilename = document.getElementById('photo-filename');
    const removePhotoButton = document.getElementById('remove-photo');
    const photoLoading = document.getElementById('photo-loading');
    
    if (!file) return;

    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    if (!validTypes.includes(file.type)) {
        showPhotoError('Please select a valid image file (JPEG, PNG, GIF).');
        return;
    }

    // Validate file size (5MB)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
        showPhotoError('File size must be less than 5MB.');
        return;
    }

    // Show loading
    photoLoading.classList.remove('hidden');

    // Create file reader
    const reader = new FileReader();
    
    reader.onload = function(e) {
        // Update preview
        photoPreviewImage.src = e.target.result;
        photoPreviewImage.classList.remove('hidden');
        photoPlaceholder.classList.add('hidden');
        
        // Update filename
        photoFilename.textContent = file.name;
        photoFilename.style.color = 'var(--success)';
        
        // Show remove button
        removePhotoButton.classList.remove('hidden');
        
        // Hide loading
        photoLoading.classList.add('hidden');
        
        // Clear any previous errors
        clearPhotoError();
        
        // Uncheck remove photo checkbox if it exists
        const removePhotoCheckbox = document.getElementById('remove_photo_checkbox');
        if (removePhotoCheckbox) {
            removePhotoCheckbox.checked = false;
        }
    };
    
    reader.onerror = function() {
        showPhotoError('Error reading file. Please try again.');
        photoLoading.classList.add('hidden');
    };
    
    reader.readAsDataURL(file);
}

function removePhoto() {
    const photoInput = document.getElementById('photo');
    const photoPreviewImage = document.getElementById('photo-preview-image');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    const photoFilename = document.getElementById('photo-filename');
    const removePhotoButton = document.getElementById('remove-photo');
    
    // Clear file input
    photoInput.value = '';
    
    // Reset preview to current photo or placeholder
    @if($user->has_photo)
        photoPreviewImage.src = '{{ $user->photo_url }}';
        photoPreviewImage.classList.remove('hidden');
        photoPlaceholder.classList.add('hidden');
        photoFilename.textContent = 'Current photo';
        photoFilename.style.color = 'var(--text-secondary)';
    @else
        photoPreviewImage.src = '';
        photoPreviewImage.classList.add('hidden');
        photoPlaceholder.classList.remove('hidden');
        photoFilename.textContent = 'No photo uploaded';
        photoFilename.style.color = 'var(--text-secondary)';
        removePhotoButton.classList.add('hidden');
    @endif
    
    // Clear any errors
    clearPhotoError();
}

function deletePhoto() {
    if (confirm('Are you sure you want to delete the profile photo? This action cannot be undone.')) {
        fetch('{{ route("admin.users.remove-photo", $user->id) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Photo deleted successfully!');
                location.reload();
            } else {
                alert('Error deleting photo: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the photo.');
        });
    }
}

function showPhotoError(message) {
    alert('Photo Error: ' + message);
    removePhoto();
}

function clearPhotoError() {
    const photoError = document.querySelector('.photo-error');
    if (photoError) {
        photoError.remove();
    }
}

// Phone validation
function validatePhoneNumber(phone) {
    const phoneRegex = /^\+\d{1,4}\d{6,}$/;
    return phoneRegex.test(phone);
}

function updatePhoneValidation() {
    const phoneInput = document.getElementById('phone');
    const phoneValidation = document.getElementById('phone-validation');
    const phoneValidIcon = document.getElementById('phone-valid-icon');
    const phoneInvalidIcon = document.getElementById('phone-invalid-icon');
    const phone = phoneInput.value.trim();
    
    if (phone === '') {
        phoneValidation.classList.add('hidden');
        return;
    }
    
    phoneValidation.classList.remove('hidden');
    
    if (validatePhoneNumber(phone)) {
        phoneValidIcon.classList.remove('hidden');
        phoneInvalidIcon.classList.add('hidden');
        phoneInput.classList.remove('border-red-500');
        phoneInput.classList.add('border-green-500');
    } else {
        phoneValidIcon.classList.add('hidden');
        phoneInvalidIcon.classList.remove('hidden');
        phoneInput.classList.remove('border-green-500');
        phoneInput.classList.add('border-red-500');
    }
}

// Password modal functions
function openPasswordModal() {
    document.getElementById('password-modal').classList.remove('hidden');
}

function closePasswordModal() {
    document.getElementById('password-modal').classList.add('hidden');
}

// Delete user function
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch(`/admin/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => {
            if (response.ok) {
                alert('User deleted successfully!');
                window.location.href = '{{ route('admin.users.index') }}';
            } else {
                alert('Error deleting user.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the user.');
        });
    }
}

// ================================================================ //
// ========== SECURITY SUPERVISOR FUNCTIONS ========== //
// ================================================================ //
const securityToggleBtn = document.getElementById('security-supervisor-toggle-btn');
const securityCheckbox = document.getElementById('security_can_be_supervisor');
const securitySettingsContent = document.getElementById('security-supervisor-settings-content');
const securityRoleSection = document.getElementById('security-supervisor-role-section');

// Security score preview
const securityScore = document.getElementById('security_supervisor_score');
const securityScorePreview = document.getElementById('security-score-preview');

if (securityScore && securityScorePreview) {
    securityScore.addEventListener('input', function() {
        securityScorePreview.style.width = this.value + '%';
    });
}

// Security JSON Validation
const securityValidateJsonBtn = document.getElementById('security-validate-json');
const securityJsonValidationResult = document.getElementById('security-json-validation-result');

if (securityValidateJsonBtn) {
    securityValidateJsonBtn.addEventListener('click', function() {
        const jsonInput = document.getElementById('security_supervisor_certifications').value;
        if (!jsonInput.trim()) {
            securityJsonValidationResult.className = 'text-xs mt-1 text-yellow-600';
            securityJsonValidationResult.textContent = 'Empty JSON - certifications will be set to null';
            securityJsonValidationResult.classList.remove('hidden');
            return;
        }
        
        try {
            const parsed = JSON.parse(jsonInput);
            if (Array.isArray(parsed)) {
                securityJsonValidationResult.className = 'text-xs mt-1 text-green-600';
                securityJsonValidationResult.textContent = '✓ Valid JSON array with ' + parsed.length + ' certification(s)';
            } else {
                securityJsonValidationResult.className = 'text-xs mt-1 text-red-600';
                securityJsonValidationResult.textContent = '✗ JSON is valid but must be an array. Example: ["first_aid", "fire_safety"]';
            }
        } catch (e) {
            securityJsonValidationResult.className = 'text-xs mt-1 text-red-600';
            securityJsonValidationResult.textContent = '✗ Invalid JSON: ' + e.message;
        }
        securityJsonValidationResult.classList.remove('hidden');
    });
}

function setSecuritySupervisorToggleState(enabled) {
    if (!securityToggleBtn || !securityCheckbox || !securitySettingsContent) return;
    
    securityCheckbox.checked = enabled;
    
    if (enabled) {
        securityToggleBtn.style.backgroundColor = 'var(--success)';
        securityToggleBtn.style.borderColor = 'var(--success)';
        securityToggleBtn.style.color = 'white';
        securityToggleBtn.style.boxShadow = '0 2px 8px rgba(var(--success-rgb), 0.3)';
        securityToggleBtn.querySelector('i').className = 'fas fa-toggle-on mr-2 text-lg';
        securityToggleBtn.querySelector('.toggle-text').textContent = 'Supervisor Enabled';
        securityToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-white toggle-indicator';
        securitySettingsContent.classList.remove('opacity-50', 'pointer-events-none');
        if (securityRoleSection) securityRoleSection.classList.remove('hidden');
    } else {
        securityToggleBtn.style.backgroundColor = 'var(--bg-secondary)';
        securityToggleBtn.style.borderColor = 'var(--border-color)';
        securityToggleBtn.style.color = 'var(--text-secondary)';
        securityToggleBtn.style.boxShadow = 'none';
        securityToggleBtn.querySelector('i').className = 'fas fa-toggle-off mr-2 text-lg';
        securityToggleBtn.querySelector('.toggle-text').textContent = 'Can be Supervisor';
        securityToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-gray-400 toggle-indicator';
        securitySettingsContent.classList.add('opacity-50', 'pointer-events-none');
        if (securityRoleSection) securityRoleSection.classList.add('hidden');
        
        // Reset security fields when disabled
        document.getElementById('security_supervisor_level').value = '0';
        if (securityScore) securityScore.value = '0';
        if (securityScorePreview) securityScorePreview.style.width = '0%';
    }
}

if (securityToggleBtn && securityCheckbox && securitySettingsContent) {
    securityToggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        setSecuritySupervisorToggleState(!securityCheckbox.checked);
    });
}

// ================================================================ //
// ========== SANITATION SUPERVISOR FUNCTIONS ========== //
// ================================================================ //
const sanitationToggleBtn = document.getElementById('sanitation-supervisor-toggle-btn');
const sanitationCheckbox = document.getElementById('sanitation_can_be_supervisor');
const sanitationSettingsContent = document.getElementById('sanitation-supervisor-settings-content');
const sanitationRoleSection = document.getElementById('sanitation-supervisor-role-section');

function setSanitationSupervisorToggleState(enabled) {
    if (!sanitationToggleBtn || !sanitationCheckbox || !sanitationSettingsContent) return;
    
    sanitationCheckbox.checked = enabled;
    
    if (enabled) {
        sanitationToggleBtn.style.backgroundColor = 'var(--success)';
        sanitationToggleBtn.style.borderColor = 'var(--success)';
        sanitationToggleBtn.style.color = 'white';
        sanitationToggleBtn.style.boxShadow = '0 2px 8px rgba(var(--success-rgb), 0.3)';
        sanitationToggleBtn.querySelector('i').className = 'fas fa-toggle-on mr-2 text-lg';
        sanitationToggleBtn.querySelector('.toggle-text').textContent = 'Enabled';
        sanitationToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-white toggle-indicator';
        sanitationSettingsContent.classList.remove('opacity-50', 'pointer-events-none');
        if (sanitationRoleSection) sanitationRoleSection.classList.remove('hidden');
    } else {
        sanitationToggleBtn.style.backgroundColor = 'var(--bg-secondary)';
        sanitationToggleBtn.style.borderColor = 'var(--border-color)';
        sanitationToggleBtn.style.color = 'var(--text-secondary)';
        sanitationToggleBtn.style.boxShadow = 'none';
        sanitationToggleBtn.querySelector('i').className = 'fas fa-toggle-off mr-2 text-lg';
        sanitationToggleBtn.querySelector('.toggle-text').textContent = 'Disabled';
        sanitationToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-gray-400 toggle-indicator';
        sanitationSettingsContent.classList.add('opacity-50', 'pointer-events-none');
        if (sanitationRoleSection) sanitationRoleSection.classList.add('hidden');

        // ✅ NEW: Reset sanitation personnel fields when disabled
        const sanitationSupervisorSelect = document.getElementById('sanitation_supervisor_id');
        if (sanitationSupervisorSelect) sanitationSupervisorSelect.value = '';

        const sanitationRoleSelect = document.getElementById('sanitation_personnel_role');
        if (sanitationRoleSelect) sanitationRoleSelect.value = 'supervisor';

        const sanitationStatusSelect = document.getElementById('sanitation_personnel_status');
        if (sanitationStatusSelect) sanitationStatusSelect.value = 'active';

        const sanitationVehicleNumber = document.getElementById('sanitation_vehicle_number');
        if (sanitationVehicleNumber) sanitationVehicleNumber.value = '';

        const sanitationVehicleType = document.getElementById('sanitation_vehicle_type');
        if (sanitationVehicleType) sanitationVehicleType.value = '';

        const sanitationEmergencyContact = document.getElementById('sanitation_emergency_contact');
        if (sanitationEmergencyContact) sanitationEmergencyContact.value = '';
    }
}

if (sanitationToggleBtn && sanitationCheckbox && sanitationSettingsContent) {
    sanitationToggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        setSanitationSupervisorToggleState(!sanitationCheckbox.checked);
    });
}

// ================================================================ //
// ========== TOGGLE SECTIONS BASED ON USER TYPE ========== //
// ================================================================ //
const typeSelect = document.getElementById('type');
const securitySection = document.getElementById('security-supervisor-section');
const sanitationSection = document.getElementById('sanitation-supervisor-section');
const securityPersonnelType = {{ App\Models\User::TYPE_SECURITY_PERSONNEL }};
const sanitationPersonnelType = {{ App\Models\User::TYPE_SANITATION_PERSONNEL ?? 2 }};

function toggleSupervisorSections() {
    if (typeSelect) {
        // Security section
        if (securitySection) {
            if (typeSelect.value == securityPersonnelType) {
                securitySection.style.display = 'block';
            } else {
                securitySection.style.display = 'none';
                if (securityToggleBtn && securityCheckbox) {
                    setSecuritySupervisorToggleState(false);
                }
            }
        }
        
        // Sanitation section
        if (sanitationSection) {
            if (typeSelect.value == sanitationPersonnelType) {
                sanitationSection.style.display = 'block';
            } else {
                sanitationSection.style.display = 'none';
                if (sanitationToggleBtn && sanitationCheckbox) {
                    setSanitationSupervisorToggleState(false);
                }
            }
        }
    }
}

if (typeSelect) {
    typeSelect.addEventListener('change', toggleSupervisorSections);
}

// ================================================================ //
// ========== FORM VALIDATION ========== //
// ================================================================ //
document.getElementById('user-form').addEventListener('submit', function(e) {
    const phone = document.getElementById('phone').value.trim();
    
    if (phone && !validatePhoneNumber(phone)) {
        e.preventDefault();
        alert('Please enter a valid phone number with country code (e.g., +233595652410).');
        document.getElementById('phone').focus();
        return;
    }
    
    // Photo validation
    const file = document.getElementById('photo').files[0];
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            e.preventDefault();
            alert('Photo size must be less than 5MB.');
            return;
        }
    }
});

// Event listeners
document.getElementById('phone').addEventListener('input', updatePhoneValidation);
document.getElementById('photo').addEventListener('change', handlePhotoUpload);
document.getElementById('remove-photo').addEventListener('click', removePhoto);

// Drag and drop for photo
const photoPreview = document.getElementById('photo-preview');
photoPreview.addEventListener('dragover', function(e) {
    e.preventDefault();
    photoPreview.style.borderColor = 'var(--primary)';
    photoPreview.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
});

photoPreview.addEventListener('dragleave', function(e) {
    e.preventDefault();
    photoPreview.style.borderColor = 'var(--border-color)';
    photoPreview.style.backgroundColor = 'var(--bg-secondary)';
});

photoPreview.addEventListener('drop', function(e) {
    e.preventDefault();
    photoPreview.style.borderColor = 'var(--border-color)';
    photoPreview.style.backgroundColor = 'var(--bg-secondary)';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('photo').files = files;
        handlePhotoUpload({ target: document.getElementById('photo') });
    }
});

// Click on preview to trigger file input
photoPreview.addEventListener('click', function() {
    document.getElementById('photo').click();
});

// Close modal when clicking outside
document.getElementById('password-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePasswordModal();
    }
});

// Initialize
updatePhoneValidation();
toggleSupervisorSections();
</script>


<style>
/* Custom styles for edit user */
#phone-validation {
    pointer-events: none;
}

/* Photo preview styles */
#photo-preview {
    transition: all 0.3s ease;
    cursor: pointer;
}

#photo-preview:hover {
    border-color: var(--primary);
    transform: scale(1.05);
}

#photo-preview-image {
    transition: opacity 0.3s ease;
}

#photo-loading {
    transition: all 0.3s ease;
}

/* Modal styling */
#password-modal {
    backdrop-filter: blur(4px);
}

/* Danger zone styling */
.border-l-4 {
    border-left-width: 4px;
}

/* Focus styles */
input:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* ========== SUPERVISOR TOGGLE BUTTON STYLES ========== */
#security-supervisor-toggle-btn,
#sanitation-supervisor-toggle-btn {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

#security-supervisor-toggle-btn:hover,
#sanitation-supervisor-toggle-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

#security-supervisor-toggle-btn:active,
#sanitation-supervisor-toggle-btn:active {
    transform: translateY(0);
}

#security-supervisor-toggle-btn .toggle-indicator,
#sanitation-supervisor-toggle-btn .toggle-indicator {
    transition: all 0.3s ease;
}

#security-supervisor-toggle-btn:hover .toggle-indicator,
#sanitation-supervisor-toggle-btn:hover .toggle-indicator {
    transform: scale(1.2);
}

/* Supervisor settings content transition */
#security-supervisor-settings-content,
#sanitation-supervisor-settings-content {
    transition: opacity 0.3s ease;
}

/* ========== MODERN TOGGLE SWITCH STYLES ========== */
.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-modern input {
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
    background-color: #d1d5db;
    border: 2px solid #d1d5db;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-modern input:checked + .toggle-slider {
    background-color: var(--success);
    border-color: var(--success);
}

.toggle-modern input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

/* Dark mode toggle styles */
[data-theme="dark"] .toggle-slider {
    background-color: #4b5563;
    border-color: #4b5563;
}

[data-theme="dark"] .toggle-slider:before {
    background-color: #e5e7eb;
}

/* Dark mode specific toggle styles */
[data-theme="dark"] #security-supervisor-toggle-btn:not([style*="background-color: var(--success)"]),
[data-theme="dark"] #sanitation-supervisor-toggle-btn:not([style*="background-color: var(--success)"]) {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #a0aec0 !important;
}

[data-theme="dark"] #security-supervisor-toggle-btn:not([style*="background-color: var(--success)"]) .toggle-indicator,
[data-theme="dark"] #sanitation-supervisor-toggle-btn:not([style*="background-color: var(--success)"]) .toggle-indicator {
    background-color: #a0aec0 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    #photo-preview {
        width: 120px;
        height: 120px;
    }
    
    #security-supervisor-toggle-btn,
    #sanitation-supervisor-toggle-btn {
        padding: 0.25rem 0.75rem;
        font-size: 12px;
    }
}

/* Checkbox styling */
input[type="checkbox"] {
    accent-color: var(--primary);
}

/* Button variants */
.btn-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
}

.btn-warning:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
}

.btn-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}

.btn-danger:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
}

/* Photo guidelines styling */
.photo-guidelines {
    background-color: rgba(var(--info-rgb), 0.05);
    border-color: var(--border-color);
}

.photo-guidelines li {
    transition: color 0.3s ease;
}

/* File input styling */
input[type="file"] {
    border: none;
}

/* Drag and drop styles */
.drag-over {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.1) !important;
}
</style>
@endsection