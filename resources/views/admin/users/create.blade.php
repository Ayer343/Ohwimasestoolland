@extends('layouts.app')

@section('title', 'Create User')

@php
    /*
     |--------------------------------------------------------------------------
     | Communication-channel availability
     |--------------------------------------------------------------------------
     */
    $smsReady      = (bool) ($smsStatus['system_ready'] ?? false);
    $whatsappReady = (bool) ($whatsappStatus['system_ready'] ?? false);
    $emailReady    = (bool) ($emailStatus['system_ready'] ?? false);

    $anyChannelReady = $smsReady || $whatsappReady || $emailReady;

    $channelAvailability = [
        'sms'      => ['ready' => $smsReady,      'label' => $smsReady      ? 'Available' : 'Not Available'],
        'whatsapp' => ['ready' => $whatsappReady, 'label' => $whatsappReady ? 'Available' : 'Not Available'],
        'email'    => ['ready' => $emailReady,    'label' => $emailReady    ? 'Available' : 'Not Available'],
    ];

    $oldChannels = old('invitation_channels', []);
    if (!is_array($oldChannels)) {
        $oldChannels = [];
    }
    $oldChannels = array_values(array_filter($oldChannels, function ($c) use ($channelAvailability) {
        return isset($channelAvailability[$c]) && $channelAvailability[$c]['ready'];
    }));

    /*
     |--------------------------------------------------------------------------
     | Role auto-mapping
     |--------------------------------------------------------------------------
     | Auto-injects a hidden `roles[]` input on every user-type change. The
     | controller still skips Spatie sync for security/sanitation types —
     | that's intentional; the injected input guarantees that every other
     | type carries its role slug.
     */
    $roleSlugByType = [
        \App\Models\User::TYPE_ADMIN                => 'admin',
        \App\Models\User::TYPE_LANDLORD             => 'landlord',
        \App\Models\User::TYPE_TENANT               => 'tenant',
        \App\Models\User::TYPE_FIELD_AGENT          => 'field-agent',
        \App\Models\User::TYPE_SECURITY_PERSONNEL   => 'security-personnel',
        \App\Models\User::TYPE_CONTRACTOR           => 'contractor',
        \App\Models\User::TYPE_SANITATION_PERSONNEL => 'sanitation-personnel',
    ];

    $roleLabelBySlug = [
        'admin'                => 'Admin',
        'landlord'             => 'Landlord',
        'tenant'               => 'Tenant',
        'field-agent'          => 'Field Agent',
        'security-personnel'   => 'Security Personnel',
        'contractor'           => 'Contractor',
        'sanitation-personnel' => 'Sanitation Personnel',
    ];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create New User</h2>
            <div class="flex items-center space-x-3">
                <!-- Communication Service Status Indicators -->
                <div class="flex items-center space-x-2">
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $whatsappReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $whatsappReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fab fa-whatsapp mr-1"></i>
                        WhatsApp: {{ $whatsappReady ? 'Ready' : 'Limited' }}
                    </div>

                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $smsReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $smsReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas fa-comment-alt mr-1"></i>
                        SMS: {{ $smsReady ? 'Ready' : 'Limited' }}
                    </div>

                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $emailReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $emailReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas {{ $emailReady ? 'fa-envelope' : 'fa-envelope-open' }} mr-1"></i>
                        Email: {{ $emailReady ? 'Ready' : 'Limited' }}
                    </div>

                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-redo-alt mr-1"></i>
                        Fallback: Active
                    </div>
                </div>

                <a href="{{ route('admin.users.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Users
                </a>
            </div>
        </div>
    </div>

    <!-- ========== SUCCESS MESSAGE SECTION ========== -->
    @if(session('success'))
    <div class="card p-0 overflow-hidden border-l-4" style="border-left-color: var(--success);">
        <div class="p-4" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-2xl" style="color: var(--success);"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold" style="color: var(--success);">User Created Successfully!</h3>
                        <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>

                        @if(session('invitation_result') && session('invitation_result')['success'])
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <div class="flex items-start">
                                <i class="fas fa-paper-plane mt-0.5 mr-2" style="color: var(--info);"></i>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Invitation Status:</p>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        Invitation sent successfully via {{ implode(', ', session('invitation_result')['channels_successful'] ?? []) }}
                                        @if(!empty(session('invitation_result')['failed_channels']))
                                        <br><span class="text-yellow-600">⚠️ Failed channels: {{ implode(', ', session('invitation_result')['failed_channels']) }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(session('last_created_user'))
                        <div class="mt-3 flex flex-wrap gap-3">
                            <a href="{{ route('admin.users.show', session('last_created_user')['id']) }}" class="btn-primary btn-sm flex items-center">
                                <i class="fas fa-eye mr-2"></i> View Created User
                            </a>
                            <button type="button" onclick="resetForm()" class="btn-secondary btn-sm flex items-center">
                                <i class="fas fa-plus mr-2"></i> Create Another User
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
                <button type="button" onclick="this.closest('.card').remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- ========== ERROR MESSAGE SECTION ========== -->
    @if(session('error'))
    <div class="card p-0 overflow-hidden border-l-4" style="border-left-color: var(--danger);">
        <div class="p-4" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-2xl" style="color: var(--danger);"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold" style="color: var(--danger);">Error!</h3>
                        <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
                    </div>
                </div>
                <button type="button" onclick="this.closest('.card').remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('admin.users.store') }}" method="POST" id="user-form" enctype="multipart/form-data">
        @csrf

        {{-- AUTO-ROLE SUBMISSION CONTAINER --}}
        <div id="auto-role-container"></div>

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

            <!-- User Type Selection Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">User Type & Basic Info</h3>
                    <i class="fas fa-user-plus text-2xl opacity-70" style="color: var(--primary);"></i>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- User Type -->
                    <div>
                        <label for="type" class="block mb-2 font-medium" style="color: var(--text-primary);">User Type *</label>
                        <select class="w-full p-2 border rounded @error('type') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                id="type" name="type" required>
                            <option value="">Select User Type</option>
                            @foreach($userTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror

                        <div id="auto-role-indicator" class="mt-2 text-xs hidden" style="color: var(--text-secondary);">
                            <i class="fas fa-user-tag mr-1" style="color: var(--primary);"></i>
                            <span>Role <strong id="auto-role-label"></strong> will be auto-assigned on create.</span>
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">Status *</label>
                        <select class="w-full p-2 border rounded @error('status') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                id="status" name="status" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', 'active') == $value ? 'selected' : '' }}>
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
                               id="name" name="name" value="{{ old('name') }}" required
                               placeholder="Enter full name">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email (Optional) -->
                    <div>
                        <label for="email" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Email Address
                            <span class="ml-1 text-xs font-normal px-2 py-0.5 rounded-full"
                                  style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info);">
                                Optional
                            </span>
                        </label>
                        <div class="relative">
                            <input type="email" class="w-full p-2 border rounded @error('email') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="email" name="email" value="{{ old('email') }}"
                                   placeholder="Optional — user will log in via phone">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="email-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="email-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);" id="email-fallback-info">
                            <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                            <span>Phone number is now the primary identifier. Add an email only if the user needs email notifications or fallback delivery.</span>
                        </div>
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
                                   id="phone" name="phone" value="{{ old('phone') }}" required
                                   placeholder="+233595652410 or 0595652410">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="phone-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="phone-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Format: +233XXXXXXXXX or 0XXXXXXXXX (Automatically standardized to +233 format)
                            <span class="block mt-1 text-amber-600" id="sms-fallback-warning">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                SMS/WhatsApp will fallback to email if delivery fails
                            </span>
                        </div>
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Username -->
                    <div id="username-field">
                        <label for="username" class="block mb-2 font-medium" style="color: var(--text-primary);">Username</label>
                        <input type="text" class="w-full p-2 border rounded @error('username') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               id="username" name="username" value="{{ old('username') }}"
                               placeholder="Optional username">
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="username-help-text">Leave empty to auto-generate for field agents</span>
                        </div>
                        @error('username')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Security Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Security & Access</h3>
                    <i class="fas fa-shield-alt text-2xl opacity-70" style="color: var(--success);"></i>
                </div>

                <!-- Password Section -->
                <div id="password-section">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block mb-2 font-medium" style="color: var(--text-primary);">Password *</label>
                            <input type="password" class="w-full p-2 border rounded @error('password') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="password" name="password"
                                   placeholder="Enter secure password">
                            @error('password')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block mb-2 font-medium" style="color: var(--text-primary);">Confirm Password *</label>
                            <input type="password" class="w-full p-2 border rounded"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="password_confirmation" name="password_confirmation"
                                   placeholder="Confirm password">
                        </div>
                    </div>
                    <div class="mt-4 text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Password must be at least 8 characters long and contain uppercase, lowercase, numbers, and special characters.
                    </div>
                </div>

                <!-- Password Notice for Invitation Users -->
                <div id="password-notice" class="mt-4 p-4 rounded border hidden"
                     style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                        <div>
                            <h4 class="font-semibold mb-1" style="color: var(--text-primary);">Password Setup via Invitation</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                When sending an invitation, the user will set their own password using a secure invitation link.
                                The password fields above will be ignored and a temporary password will be generated automatically.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Auto Verification Options -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-start space-x-3" id="auto-verify-email-wrapper">
                        <input type="checkbox" id="auto_verify_email" name="auto_verify_email" value="1"
                               class="w-4 h-4 mt-1 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);"
                               {{ old('auto_verify_email') ? 'checked' : '' }}>
                        <label for="auto_verify_email" class="font-medium cursor-pointer" style="color: var(--text-primary);">
                            Auto-Verify Email Address
                            <span class="block text-xs font-normal mt-1" id="auto-verify-email-hint" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Requires an email address to be entered above
                            </span>
                        </label>
                    </div>
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" id="auto_verify_phone" name="auto_verify_phone" value="1"
                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);"
                               {{ old('auto_verify_phone') ? 'checked' : '' }}>
                        <label for="auto_verify_phone" class="font-medium" style="color: var(--text-primary);">
                            Auto-Verify Phone Number
                        </label>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- ========== SECURITY PERSONNEL SUPERVISOR SECTION ========== -->
            <!-- ============================================================ -->
            <div class="card p-6" id="security-supervisor-section" style="display: none;">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold mr-3" style="color: var(--text-primary);">
                            <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                            Security Supervisor Eligibility
                        </h3>
                        <button type="button" id="security-supervisor-toggle-btn"
                                class="relative inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-300 border-2 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style="background-color: {{ old('security_can_be_supervisor') ? 'var(--success)' : 'var(--bg-secondary)' }};
                                       border-color: {{ old('security_can_be_supervisor') ? 'var(--success)' : 'var(--border-color)' }};
                                       color: {{ old('security_can_be_supervisor') ? 'white' : 'var(--text-secondary)' }};
                                       box-shadow: {{ old('security_can_be_supervisor') ? '0 2px 8px rgba(var(--success-rgb), 0.3)' : 'none' }};">
                            <i class="fas {{ old('security_can_be_supervisor') ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-2 text-lg"></i>
                            <span class="toggle-text">{{ old('security_can_be_supervisor') ? 'Eligible' : 'Not Eligible' }}</span>
                            <span class="ml-2 w-2 h-2 rounded-full {{ old('security_can_be_supervisor') ? 'bg-white' : 'bg-gray-400' }} toggle-indicator"></span>
                        </button>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium"
                          style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        Security Personnel Only
                    </span>
                </div>

                <input type="checkbox" name="security_can_be_supervisor" id="security_can_be_supervisor" value="1"
                       class="hidden" {{ old('security_can_be_supervisor') ? 'checked' : '' }}>

                <div id="security-supervisor-settings-content" class="{{ old('security_can_be_supervisor') ? '' : 'opacity-50 pointer-events-none' }} transition-all duration-300">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 mb-4 rounded-r-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Head of Security Eligibility</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    Enable this to make the officer eligible for appointment as the
                                    <strong>Head of Security</strong> of the community.
                                    The actual appointment (start date, replacement of the previous head)
                                    is done in <strong>Security → Supervisor Assignments</strong>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Can be appointed Head of Security</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary); max-width: 600px;">
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                When enabled, this officer appears in the "Eligible Personnel" list in the
                                Supervisor Assignments module.
                                <br>
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                Only one officer can hold the Head of Security appointment at a time.
                            </p>
                        </div>
                        <div class="toggle-modern flex-shrink-0 ml-4">
                            <input type="checkbox" name="security_can_be_supervisor_toggle" id="security_can_be_supervisor_toggle" value="1"
                                   {{ old('security_can_be_supervisor') ? 'checked' : '' }} class="sr-only">
                            <label for="security_can_be_supervisor_toggle" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="security_supervisor_certifications" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Certifications / Notes
                            <span class="text-xs font-normal" style="color: var(--text-secondary);">(optional)</span>
                        </label>
                        <textarea class="w-full p-2 border rounded @error('security_supervisor_certifications') border-red-500 @enderror"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  id="security_supervisor_certifications" name="security_supervisor_certifications"
                                  rows="3"
                                  placeholder="e.g. Security certification, first aid training, previous supervisory experience">{{ old('security_supervisor_certifications') }}</textarea>
                        @error('security_supervisor_certifications')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Free-form. Recorded on the user's profile. Not used for permission decisions.
                        </div>
                    </div>

                    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">What's next?</p>
                                <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                    <li>• This officer becomes eligible for Head-of-Security appointment.</li>
                                    <li>• In <strong>Supervisor Assignments</strong>, appoint this officer as head.</li>
                                    <li>• Only one active Head of Security at a time.</li>
                                    <li>• The head gets the full security management permission set automatically.</li>
                                    <li>• Revoking eligibility later will deactivate their active appointment automatically.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================ -->
            <!-- ========== SANITATION PERSONNEL SECTION ========== -->
            <!-- ================================================================ -->
            <div class="card p-6" id="sanitation-supervisor-section" style="display: none;">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold mr-3" style="color: var(--text-primary);">
                            <i class="fas fa-trash-alt mr-2" style="color: var(--primary);"></i>
                            Sanitation Personnel
                        </h3>
                        <button type="button" id="sanitation-supervisor-toggle-btn"
                                class="relative inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-300 border-2 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style="background-color: {{ old('sanitation_can_be_supervisor') ? 'var(--success)' : 'var(--bg-secondary)' }};
                                       border-color: {{ old('sanitation_can_be_supervisor') ? 'var(--success)' : 'var(--border-color)' }};
                                       color: {{ old('sanitation_can_be_supervisor') ? 'white' : 'var(--text-secondary)' }};
                                       box-shadow: {{ old('sanitation_can_be_supervisor') ? '0 2px 8px rgba(var(--success-rgb), 0.3)' : 'none' }};">
                            <i class="fas {{ old('sanitation_can_be_supervisor') ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-2 text-lg"></i>
                            <span class="toggle-text">{{ old('sanitation_can_be_supervisor') ? 'Eligible' : 'Not Eligible' }}</span>
                            <span class="ml-2 w-2 h-2 rounded-full {{ old('sanitation_can_be_supervisor') ? 'bg-white' : 'bg-gray-400' }} toggle-indicator"></span>
                        </button>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium"
                          style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        Sanitation Personnel Only
                    </span>
                </div>

                <input type="checkbox" name="sanitation_can_be_supervisor" id="sanitation_can_be_supervisor" value="1"
                       class="hidden" {{ old('sanitation_can_be_supervisor') ? 'checked' : '' }}>

                <div id="sanitation-supervisor-settings-content" class="{{ old('sanitation_can_be_supervisor') ? '' : 'opacity-50 pointer-events-none' }} transition-all duration-300">
                    <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-4 mb-4 rounded-r-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-green-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm text-green-700 dark:text-green-300 font-medium">Sanitation Personnel Configuration</p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                    Enable and configure sanitation personnel settings for this user.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-lg mb-4" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable Sanitation Personnel</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary); max-width: 600px;">
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                Enable this to create a sanitation personnel record for this user.
                                <br>
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                The user will be able to access sanitation-related features.
                            </p>
                        </div>
                        <div class="toggle-modern flex-shrink-0 ml-4">
                            <input type="checkbox" name="sanitation_can_be_supervisor_toggle" id="sanitation_can_be_supervisor_toggle" value="1"
                                   {{ old('sanitation_can_be_supervisor') ? 'checked' : '' }} class="sr-only">
                            <label for="sanitation_can_be_supervisor_toggle" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div id="sanitation-supervisor-role-section" class="{{ old('sanitation_can_be_supervisor') ? '' : 'hidden' }} transition-all duration-300">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sanitation_personnel_role" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Personnel Role *
                                </label>
                                <select class="w-full p-2 border rounded @error('sanitation_personnel_role') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="sanitation_personnel_role" name="sanitation_personnel_role">
                                    <option value="supervisor" {{ old('sanitation_personnel_role', 'supervisor') == 'supervisor' ? 'selected' : '' }}>
                                        Supervisor
                                    </option>
                                    <option value="driver" {{ old('sanitation_personnel_role') == 'driver' ? 'selected' : '' }}>
                                        Driver
                                    </option>
                                </select>
                                @error('sanitation_personnel_role')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Select the personnel role for this user
                                </div>
                            </div>

                            <div>
                                <label for="sanitation_personnel_status" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Personnel Status *
                                </label>
                                <select class="w-full p-2 border rounded @error('sanitation_personnel_status') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="sanitation_personnel_status" name="sanitation_personnel_status">
                                    <option value="active" {{ old('sanitation_personnel_status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('sanitation_personnel_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="on_leave" {{ old('sanitation_personnel_status') == 'on_leave' ? 'selected' : '' }}>On Leave</option>
                                    <option value="suspended" {{ old('sanitation_personnel_status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                </select>
                                @error('sanitation_personnel_status')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Personnel status determines availability
                                </div>
                            </div>
                        </div>

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
                                    <option value="{{ $supervisorId }}" {{ old('sanitation_supervisor_id') == $supervisorId ? 'selected' : '' }}>
                                        {{ $supervisorLabel }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sanitation_supervisor_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Optional. Only personnel with a supervisor role appear here.
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sanitation_vehicle_number" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Vehicle Number
                                </label>
                                <input type="text" class="w-full p-2 border rounded @error('sanitation_vehicle_number') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="sanitation_vehicle_number" name="sanitation_vehicle_number"
                                       value="{{ old('sanitation_vehicle_number') }}"
                                       placeholder="e.g., GS-1234-23">
                                @error('sanitation_vehicle_number')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sanitation_vehicle_type" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Vehicle Type
                                </label>
                                <select class="w-full p-2 border rounded @error('sanitation_vehicle_type') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="sanitation_vehicle_type" name="sanitation_vehicle_type">
                                    <option value="">Select Vehicle Type</option>
                                    <option value="truck" {{ old('sanitation_vehicle_type') == 'truck' ? 'selected' : '' }}>Truck</option>
                                    <option value="van" {{ old('sanitation_vehicle_type') == 'van' ? 'selected' : '' }}>Van</option>
                                    <option value="pickup" {{ old('sanitation_vehicle_type') == 'pickup' ? 'selected' : '' }}>Pickup</option>
                                    <option value="compactor" {{ old('sanitation_vehicle_type') == 'compactor' ? 'selected' : '' }}>Compactor</option>
                                </select>
                                @error('sanitation_vehicle_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="sanitation_emergency_contact" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Emergency Contact
                            </label>
                            <input type="text" class="w-full p-2 border rounded @error('sanitation_emergency_contact') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="sanitation_emergency_contact" name="sanitation_emergency_contact"
                                   value="{{ old('sanitation_emergency_contact') }}"
                                   placeholder="Name: +233XXXXXXXXX">
                            @error('sanitation_emergency_contact')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <label for="sanitation_hire_date" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Hire Date
                            </label>
                            <input type="date" class="w-full p-2 border rounded @error('sanitation_hire_date') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="sanitation_hire_date" name="sanitation_hire_date"
                                   value="{{ old('sanitation_hire_date', date('Y-m-d')) }}">
                            @error('sanitation_hire_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">What's next?</p>
                                <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                    <li>• A sanitation personnel record will be created with the selected role</li>
                                    <li>• The user will have access to sanitation features based on their role</li>
                                    <li>• Supervisors can manage workers and collection requests</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Invitation Options Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">User Invitation & Notification</h3>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-shield text-lg" style="color: var(--info);" title="Secure invitation system"></i>
                        <i class="fas fa-paper-plane text-2xl opacity-70" style="color: var(--info);"></i>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="rounded p-3" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-shield-alt mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div class="flex-1">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    Secure Invitation System: Users will receive secure links to set up their accounts
                                </span>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mt-2 text-xs" style="color: var(--text-secondary);">
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>No passwords sent via SMS</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>Users set their own secure passwords</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>7-day invitation expiry</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        <span>Fallback when email available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(!$anyChannelReady)
                    <div class="rounded p-4 border-l-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left-color: var(--danger);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--danger);">No Delivery Channels Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    None of the communication services (SMS, WhatsApp, Email) are configured.
                                    You can still create the user without an invitation.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Send Invitation to User
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="modern-radio-option {{ !$anyChannelReady ? 'modern-radio-option--disabled' : '' }}">
                                <input type="radio" name="send_invitation" id="send_invitation_yes" value="1"
                                       class="modern-radio-input"
                                       {{ $anyChannelReady ? '' : 'disabled' }}
                                       {{ old('send_invitation') ? 'checked' : '' }}>
                                <label for="send_invitation_yes" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Send Invitation</div>
                                            <div class="modern-radio-description">
                                                @if($anyChannelReady)
                                                    Send secure invitation with password setup link
                                                @else
                                                    <span class="text-red-500">No delivery channels available</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="modern-radio-option">
                                <input type="radio" name="send_invitation" id="send_invitation_no" value="0"
                                       class="modern-radio-input"
                                       {{ !old('send_invitation') ? 'checked' : '' }}>
                                <label for="send_invitation_no" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-ban"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Don't Send</div>
                                            <div class="modern-radio-description">Skip invitation for now</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="invitation-method-container" class="hidden space-y-6">
                        <div id="invitation-channels-section" class="hidden">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Channels *</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="invitation-channels-options">
                                <label class="flex items-center p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option {{ !$smsReady ? 'channel-disabled' : '' }}"
                                       style="background-color: {{ $smsReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }}; border-color: var(--border-color);"
                                       data-channel="sms"
                                       @if(!$smsReady) aria-disabled="true" title="SMS service not configured" @endif>
                                    <input type="checkbox" name="invitation_channels[]" value="sms" class="mr-2" style="color: var(--primary);"
                                           {{ $smsReady ? '' : 'disabled' }}
                                           {{ in_array('sms', $oldChannels, true) ? 'checked' : '' }}>
                                    <div class="flex items-center">
                                        <i class="fas fa-comment-alt mr-2 text-lg" style="color: var(--info);"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">SMS</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Send invitation via SMS</div>
                                            <div class="text-xs mt-1 {{ $smsReady ? 'text-green-600' : 'text-red-600' }}">
                                                <i class="fas {{ $smsReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                {{ $channelAvailability['sms']['label'] }}
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option {{ !$whatsappReady ? 'channel-disabled' : '' }}"
                                       style="background-color: {{ $whatsappReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }}; border-color: var(--border-color);"
                                       data-channel="whatsapp"
                                       @if(!$whatsappReady) aria-disabled="true" title="WhatsApp service not configured" @endif>
                                    <input type="checkbox" name="invitation_channels[]" value="whatsapp" class="mr-2" style="color: var(--primary);"
                                           {{ $whatsappReady ? '' : 'disabled' }}
                                           {{ in_array('whatsapp', $oldChannels, true) ? 'checked' : '' }}>
                                    <div class="flex items-center">
                                        <i class="fab fa-whatsapp mr-2 text-lg" style="color: #25D366;"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">WhatsApp</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Send via WhatsApp</div>
                                            <div class="text-xs mt-1 {{ $whatsappReady ? 'text-green-600' : 'text-red-600' }}">
                                                <i class="fas {{ $whatsappReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                {{ $channelAvailability['whatsapp']['label'] }}
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option {{ !$emailReady ? 'channel-disabled' : '' }}"
                                       style="background-color: {{ $emailReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }}; border-color: var(--border-color);"
                                       data-channel="email"
                                       @if(!$emailReady) aria-disabled="true" title="Email service not configured" @endif>
                                    <input type="checkbox" name="invitation_channels[]" value="email" class="mr-2" style="color: var(--primary);"
                                           {{ $emailReady ? '' : 'disabled' }}
                                           {{ in_array('email', $oldChannels, true) ? 'checked' : '' }}>
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2 text-lg" style="color: var(--primary);"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">Email</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">Send invitation via email</div>
                                            <div class="text-xs mt-1 {{ $emailReady ? 'text-green-600' : 'text-red-600' }}">
                                                <i class="fas {{ $emailReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                {{ $channelAvailability['email']['label'] }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="invitation_type" class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Type *</label>
                                <select class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="invitation_type" name="invitation_type" required>
                                    <option value="welcome" {{ old('invitation_type') == 'welcome' ? 'selected' : '' }}>Welcome Invitation</option>
                                    <option value="registration" {{ old('invitation_type') == 'registration' ? 'selected' : '' }}>Registration Invitation</option>
                                    <option value="account_setup" {{ old('invitation_type') == 'account_setup' ? 'selected' : '' }}>Account Setup</option>
                                    <option value="password_setup" {{ old('invitation_type') == 'password_setup' ? 'selected' : '' }}>Password Setup</option>
                                </select>
                            </div>

                            <div>
                                <label for="expires_in_days" class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Expiration</label>
                                <select class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="expires_in_days" name="expires_in_days">
                                    <option value="1" {{ old('expires_in_days') == '1' ? 'selected' : '' }}>1 Day</option>
                                    <option value="3" {{ old('expires_in_days') == '3' ? 'selected' : '' }}>3 Days</option>
                                    <option value="7" {{ old('expires_in_days', '7') == '7' ? 'selected' : '' }}>7 Days (Default)</option>
                                    <option value="14" {{ old('expires_in_days') == '14' ? 'selected' : '' }}>14 Days</option>
                                    <option value="30" {{ old('expires_in_days') == '30' ? 'selected' : '' }}>30 Days</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="invitation_message" class="block mb-2 font-medium" style="color: var(--text-primary);">Custom Invitation Message</label>
                            <textarea class="w-full p-2 border rounded"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="invitation_message" name="invitation_message"
                                      rows="4"
                                      placeholder="Add a custom welcome message to the invitation (optional)."
                                      maxlength="1000">{{ old('invitation_message') }}</textarea>
                            <div class="flex justify-between items-center mt-1">
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Optional custom message. Max 1000 characters.
                                </div>
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    <span id="char-count">0</span>/1000 characters
                                </div>
                            </div>
                        </div>

                        <div id="invitation-preview-container" class="rounded p-4 hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-semibold" style="color: var(--text-primary);">Invitation Preview</h4>
                                <div class="flex items-center space-x-2">
                                    <span id="invitation-channel-badge" class="px-2 py-1 rounded text-xs font-medium"
                                          style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                        Select Channels
                                    </span>
                                    <i class="fas fa-envelope" style="color: var(--info);"></i>
                                </div>
                            </div>
                            <div class="rounded p-3 text-sm font-mono whitespace-pre-wrap" id="invitation-preview-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"></div>
                            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span id="invitation-preview-note">This preview shows the invitation that will be sent to the user</span>
                            </div>

                            <div id="fallback-delivery-info" class="mt-3 p-2 rounded border hidden" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-redo-alt mr-2" style="color: var(--info);"></i>
                                    <span style="color: var(--text-primary);">System will automatically fallback to email if SMS/WhatsApp channels fail</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
                        <h4 class="font-semibold mb-2 text-sm" style="color: var(--text-primary);">Benefits of Token-Based Invitations:</h4>
                        <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                            <li class="flex items-center">
                                <i class="fas fa-shield-alt mr-2 text-xs" style="color: var(--success);"></i>
                                <strong>Enhanced Security:</strong> Users set their own secure passwords
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-user-check mr-2 text-xs" style="color: var(--success);"></i>
                                <strong>Better User Experience:</strong> Self-service account activation
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-clock mr-2 text-xs" style="color: var(--success);"></i>
                                <strong>Automatic Expiration:</strong> Secure tokens expire after set period
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-redo-alt mr-2 text-xs" style="color: var(--info);"></i>
                                <strong>Fallback Delivery:</strong> SMS/WhatsApp failures fall back to email when the user has one
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Fallback Information Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Fallback & Retry System</h3>
                    <i class="fas fa-redo-alt text-2xl opacity-70" style="color: var(--info);"></i>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 rounded border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-comment-alt mr-2" style="color: var(--info);"></i>
                                <h4 class="font-semibold text-sm" style="color: var(--text-primary);">SMS Fallback</h4>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                If SMS fails, system automatically attempts email delivery as fallback (requires user email).
                            </p>
                        </div>

                        <div class="p-4 rounded border" style="background-color: rgba(37, 211, 102, 0.05); border-color: rgba(37, 211, 102, 0.2);">
                            <div class="flex items-center mb-2">
                                <i class="fab fa-whatsapp mr-2" style="color: #25D366;"></i>
                                <h4 class="font-semibold text-sm" style="color: var(--text-primary);">WhatsApp Fallback</h4>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                If WhatsApp fails, system automatically attempts email delivery as fallback (requires user email).
                            </p>
                        </div>

                        <div class="p-4 rounded border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-envelope mr-2" style="color: var(--success);"></i>
                                <h4 class="font-semibold text-sm" style="color: var(--text-primary);">Email Retry Logic</h4>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Email includes automatic retry (3 attempts) with exponential backoff.
                            </p>
                        </div>
                    </div>

                    <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <h4 class="font-semibold text-sm mb-1" style="color: var(--text-primary);">Fallback System Requirements:</h4>
                                <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2 text-xs" style="color: var(--success);"></i>
                                        For SMS/WhatsApp fallback to work, user must have a valid email address
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2 text-xs" style="color: var(--success);"></i>
                                        Email service must be operational for fallback to succeed
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2 text-xs" style="color: var(--success);"></i>
                                        All fallback attempts are logged for monitoring and debugging
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check mr-2 text-xs" style="color: var(--success);"></i>
                                        Users receive only one invitation regardless of channel count
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status"></div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Create User
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const ROLE_SLUG_BY_TYPE  = @json($roleSlugByType);
const ROLE_LABEL_BY_SLUG = @json($roleLabelBySlug);

/**
 * Inject the correct Spatie role slug for the selected user type.
 */
function syncAutoRoleInput() {
    const typeSelect = document.getElementById('type');
    const container  = document.getElementById('auto-role-container');
    const indicator  = document.getElementById('auto-role-indicator');
    const labelSpan  = document.getElementById('auto-role-label');

    if (!typeSelect || !container) return;

    container.innerHTML = '';

    const selectedType = typeSelect.value;

    if (!selectedType || !ROLE_SLUG_BY_TYPE[selectedType]) {
        if (indicator) indicator.classList.add('hidden');
        return;
    }

    const slug = ROLE_SLUG_BY_TYPE[selectedType];

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'roles[]';
    input.value = slug;
    input.dataset.autoAssigned = '1';
    container.appendChild(input);

    if (indicator && labelSpan) {
        labelSpan.textContent = ROLE_LABEL_BY_SLUG[slug] || slug;
        indicator.classList.remove('hidden');
    }

    console.log('[auto-role] type=' + selectedType + ' → roles[]=' + slug);
}

function resetForm() {
    const form = document.getElementById('user-form');
    if (!form) return;

    form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(field => {
        if (field.disabled) return;
        if (field.type !== 'checkbox' && field.type !== 'radio') {
            field.value = '';
        } else {
            field.checked = false;
        }
    });

    const statusSelect = document.getElementById('status');
    if (statusSelect) statusSelect.value = 'active';

    const sendInvitationNo = document.getElementById('send_invitation_no');
    if (sendInvitationNo) sendInvitationNo.checked = true;

    const typeSelect = document.getElementById('type');
    if (typeSelect) {
        typeSelect.dispatchEvent(new Event('change'));
        syncAutoRoleInput();
    }

    if (sendInvitationNo) sendInvitationNo.dispatchEvent(new Event('change'));

    // Reset security supervisor toggle
    const securityCheckbox       = document.getElementById('security_can_be_supervisor');
    const securityToggleCheckbox = document.getElementById('security_can_be_supervisor_toggle');
    if (securityCheckbox && securityToggleCheckbox) {
        securityCheckbox.checked       = false;
        securityToggleCheckbox.checked = false;
        const toggleBtn = document.getElementById('security-supervisor-toggle-btn');
        if (toggleBtn) toggleBtn.click();
    }

    const securityCerts = document.getElementById('security_supervisor_certifications');
    if (securityCerts) securityCerts.value = '';

    // Reset sanitation supervisor toggle + fields
    const sanitationCheckbox       = document.getElementById('sanitation_can_be_supervisor');
    const sanitationToggleCheckbox = document.getElementById('sanitation_can_be_supervisor_toggle');
    if (sanitationCheckbox && sanitationToggleCheckbox) {
        sanitationCheckbox.checked       = false;
        sanitationToggleCheckbox.checked = false;
        const toggleBtn = document.getElementById('sanitation-supervisor-toggle-btn');
        if (toggleBtn) toggleBtn.click();
    }

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

    const sanitationSupervisorSelect = document.getElementById('sanitation_supervisor_id');
    if (sanitationSupervisorSelect) sanitationSupervisorSelect.value = '';

    const successMessage = document.querySelector('[style*="border-left-color: var(--success);"]');
    if (successMessage) successMessage.remove();

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing user creation form (simplified supervisor model)...');

    const smsSystemReady      = {{ $smsReady ? 'true' : 'false' }};
    const whatsappSystemReady = {{ $whatsappReady ? 'true' : 'false' }};
    const emailSystemReady    = {{ $emailReady ? 'true' : 'false' }};
    const anyChannelReady     = smsSystemReady || whatsappSystemReady || emailSystemReady;

    const sendInvitationYes         = document.getElementById('send_invitation_yes');
    const sendInvitationNo          = document.getElementById('send_invitation_no');
    const invitationMethodContainer = document.getElementById('invitation-method-container');
    const invitationChannelsSection = document.getElementById('invitation-channels-section');
    const passwordSection           = document.getElementById('password-section');
    const passwordNotice            = document.getElementById('password-notice');
    const passwordInput             = document.getElementById('password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const statusSelect              = document.getElementById('status');
    const formStatus                = document.getElementById('form-status');
    const emailInput                = document.getElementById('email');
    const phoneInput                = document.getElementById('phone');
    const emailFallbackInfo         = document.getElementById('email-fallback-info');
    const smsFallbackWarning        = document.getElementById('sms-fallback-warning');
    const typeSelect                = document.getElementById('type');
    const autoVerifyEmail           = document.getElementById('auto_verify_email');

    // Security elements
    const securitySection         = document.getElementById('security-supervisor-section');
    const securityToggleBtn       = document.getElementById('security-supervisor-toggle-btn');
    const securityCheckbox        = document.getElementById('security_can_be_supervisor');
    const securityToggleCheckbox  = document.getElementById('security_can_be_supervisor_toggle');
    const securitySettingsContent = document.getElementById('security-supervisor-settings-content');
    const securityPersonnelType   = {{ \App\Models\User::TYPE_SECURITY_PERSONNEL ?? 6 }};

    // Sanitation elements
    const sanitationSection         = document.getElementById('sanitation-supervisor-section');
    const sanitationToggleBtn       = document.getElementById('sanitation-supervisor-toggle-btn');
    const sanitationCheckbox        = document.getElementById('sanitation_can_be_supervisor');
    const sanitationToggleCheckbox  = document.getElementById('sanitation_can_be_supervisor_toggle');
    const sanitationSettingsContent = document.getElementById('sanitation-supervisor-settings-content');
    const sanitationRoleSection     = document.getElementById('sanitation-supervisor-role-section');
    const sanitationPersonnelType   = {{ \App\Models\User::TYPE_SANITATION_PERSONNEL ?? 9 }};

    // Auto-role sync
    if (typeSelect) {
        syncAutoRoleInput();
        typeSelect.addEventListener('change', syncAutoRoleInput);
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function syncAutoVerifyEmailState() {
        if (!autoVerifyEmail) return;
        const hasEmail = (emailInput?.value || '').trim().length > 0;
        if (!hasEmail) {
            autoVerifyEmail.checked = false;
            autoVerifyEmail.setAttribute('disabled', 'disabled');
            const hint = document.getElementById('auto-verify-email-hint');
            if (hint) hint.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Enter an email above to enable';
        } else {
            autoVerifyEmail.removeAttribute('disabled');
            const hint = document.getElementById('auto-verify-email-hint');
            if (hint) hint.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Email will be marked as verified immediately';
        }
    }

    function syncEmailChannelAvailability() {
        const emailCheckbox = document.querySelector('input[name="invitation_channels[]"][value="email"]');
        if (!emailCheckbox) return;

        const emailOption = emailCheckbox.closest('.invitation-channel-option');
        if (!emailSystemReady) return;

        const userEmail    = (emailInput?.value || '').trim();
        const emailIsValid = userEmail && validateEmail(userEmail);

        if (!emailIsValid) {
            emailCheckbox.disabled = true;
            emailCheckbox.checked  = false;
            if (emailOption) {
                emailOption.classList.add('channel-disabled');
                emailOption.setAttribute('aria-disabled', 'true');
                emailOption.style.opacity = '0.55';
                emailOption.style.cursor  = 'not-allowed';
            }
        } else {
            emailCheckbox.disabled = false;
            if (emailOption) {
                emailOption.classList.remove('channel-disabled');
                emailOption.removeAttribute('aria-disabled');
                emailOption.style.opacity = '1';
                emailOption.style.cursor  = 'pointer';
            }
        }
    }

    // ===== Security supervisor toggle =====
    function toggleSecuritySupervisorSection() {
        if (!typeSelect || !securitySection) return;
        if (typeSelect.value == securityPersonnelType) {
            securitySection.style.display = 'block';
        } else {
            securitySection.style.display = 'none';
            if (securityToggleBtn && securityCheckbox) {
                setSecuritySupervisorToggleState(false);
            }
        }
    }

    function setSecuritySupervisorToggleState(enabled) {
        if (securityCheckbox)        securityCheckbox.checked       = enabled;
        if (securityToggleCheckbox)  securityToggleCheckbox.checked = enabled;

        if (!securityToggleBtn || !securitySettingsContent) return;

        if (enabled) {
            securityToggleBtn.style.backgroundColor = 'var(--success)';
            securityToggleBtn.style.borderColor     = 'var(--success)';
            securityToggleBtn.style.color           = 'white';
            securityToggleBtn.style.boxShadow       = '0 2px 8px rgba(var(--success-rgb), 0.3)';
            securityToggleBtn.querySelector('i').className = 'fas fa-toggle-on mr-2 text-lg';
            securityToggleBtn.querySelector('.toggle-text').textContent = 'Eligible';
            securityToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-white toggle-indicator';
            securitySettingsContent.classList.remove('opacity-50', 'pointer-events-none');
        } else {
            securityToggleBtn.style.backgroundColor = 'var(--bg-secondary)';
            securityToggleBtn.style.borderColor     = 'var(--border-color)';
            securityToggleBtn.style.color           = 'var(--text-secondary)';
            securityToggleBtn.style.boxShadow       = 'none';
            securityToggleBtn.querySelector('i').className = 'fas fa-toggle-off mr-2 text-lg';
            securityToggleBtn.querySelector('.toggle-text').textContent = 'Not Eligible';
            securityToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-gray-400 toggle-indicator';
            securitySettingsContent.classList.add('opacity-50', 'pointer-events-none');
        }
    }

    if (securityToggleBtn && securityCheckbox && securitySettingsContent) {
        setSecuritySupervisorToggleState(securityCheckbox.checked);
        securityToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            setSecuritySupervisorToggleState(!securityCheckbox.checked);
        });
    }
    if (securityToggleCheckbox) {
        securityToggleCheckbox.addEventListener('change', function() {
            setSecuritySupervisorToggleState(this.checked);
        });
    }

    // ===== Sanitation toggle =====
    function toggleSanitationSection() {
        if (!typeSelect || !sanitationSection) return;
        if (typeSelect.value == sanitationPersonnelType) {
            sanitationSection.style.display = 'block';
        } else {
            sanitationSection.style.display = 'none';
            if (sanitationToggleBtn && sanitationCheckbox) {
                setSanitationToggleState(false);
            }
        }
    }

    function setSanitationToggleState(enabled) {
        if (sanitationCheckbox)       sanitationCheckbox.checked       = enabled;
        if (sanitationToggleCheckbox) sanitationToggleCheckbox.checked = enabled;

        if (!sanitationToggleBtn || !sanitationSettingsContent) return;

        if (enabled) {
            sanitationToggleBtn.style.backgroundColor = 'var(--success)';
            sanitationToggleBtn.style.borderColor     = 'var(--success)';
            sanitationToggleBtn.style.color           = 'white';
            sanitationToggleBtn.style.boxShadow       = '0 2px 8px rgba(var(--success-rgb), 0.3)';
            sanitationToggleBtn.querySelector('i').className = 'fas fa-toggle-on mr-2 text-lg';
            sanitationToggleBtn.querySelector('.toggle-text').textContent = 'Enabled';
            sanitationToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-white toggle-indicator';
            sanitationSettingsContent.classList.remove('opacity-50', 'pointer-events-none');
            sanitationRoleSection.classList.remove('hidden');
        } else {
            sanitationToggleBtn.style.backgroundColor = 'var(--bg-secondary)';
            sanitationToggleBtn.style.borderColor     = 'var(--border-color)';
            sanitationToggleBtn.style.color           = 'var(--text-secondary)';
            sanitationToggleBtn.style.boxShadow       = 'none';
            sanitationToggleBtn.querySelector('i').className = 'fas fa-toggle-off mr-2 text-lg';
            sanitationToggleBtn.querySelector('.toggle-text').textContent = 'Disabled';
            sanitationToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-gray-400 toggle-indicator';
            sanitationSettingsContent.classList.add('opacity-50', 'pointer-events-none');
            sanitationRoleSection.classList.add('hidden');
        }
    }

    if (sanitationToggleBtn && sanitationCheckbox && sanitationSettingsContent) {
        setSanitationToggleState(sanitationCheckbox.checked);
        sanitationToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            setSanitationToggleState(!sanitationCheckbox.checked);
        });
    }
    if (sanitationToggleCheckbox) {
        sanitationToggleCheckbox.addEventListener('change', function() {
            setSanitationToggleState(this.checked);
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            toggleSecuritySupervisorSection();
            toggleSanitationSection();
        });
        toggleSecuritySupervisorSection();
        toggleSanitationSection();
    }

    // ===== Invitation system =====
    function toggleInvitationOptions() {
        if (sendInvitationYes && sendInvitationYes.checked) {
            invitationMethodContainer.classList.remove('hidden');
            invitationChannelsSection.classList.remove('hidden');
            passwordSection.classList.add('hidden');
            passwordNotice.classList.remove('hidden');
            if (passwordInput) passwordInput.removeAttribute('required');
            if (passwordConfirmationInput) passwordConfirmationInput.removeAttribute('required');
            if (statusSelect) statusSelect.value = 'pending';
        } else {
            invitationMethodContainer.classList.add('hidden');
            invitationChannelsSection.classList.add('hidden');
            passwordSection.classList.remove('hidden');
            passwordNotice.classList.add('hidden');
            if (passwordInput) passwordInput.setAttribute('required', 'required');
            if (passwordConfirmationInput) passwordConfirmationInput.setAttribute('required', 'required');
            if (statusSelect) statusSelect.value = 'active';
        }
        updateRadioVisualState(sendInvitationYes);
        updateRadioVisualState(sendInvitationNo);
    }

    function updateRadioVisualState(radio) {
        if (!radio) return;
        const option = radio.closest('.modern-radio-option');
        if (option) option.classList.toggle('modern-radio-option--checked', radio.checked);
    }

    function getSelectedChannels() {
        return Array.from(document.querySelectorAll('input[name="invitation_channels[]"]:checked'))
            .filter(cb => !cb.disabled)
            .map(cb => cb.value);
    }

    function validateForm() {
        if (sendInvitationYes && sendInvitationYes.checked) {
            const selectedChannels = getSelectedChannels();
            const userPhone = phoneInput?.value.trim() || '';
            const userEmail = emailInput?.value.trim() || '';
            const isEmailValid = userEmail && validateEmail(userEmail);

            if (selectedChannels.length === 0) {
                alert('Please select at least one invitation channel.');
                return false;
            }
            if (selectedChannels.includes('email') && (!userEmail || !isEmailValid)) {
                alert('Please enter a valid email address for the email invitation channel.');
                emailInput.focus();
                return false;
            }
            if ((selectedChannels.includes('whatsapp') || selectedChannels.includes('sms')) && !userPhone) {
                alert('Phone number is required for SMS/WhatsApp invitations.');
                phoneInput.focus();
                return false;
            }
            for (const ch of selectedChannels) {
                if (ch === 'sms'      && !smsSystemReady)      { alert('SMS service is not available.');      return false; }
                if (ch === 'whatsapp' && !whatsappSystemReady) { alert('WhatsApp service is not available.'); return false; }
                if (ch === 'email'    && !emailSystemReady)    { alert('Email service is not available.');    return false; }
            }
        }
        return true;
    }

    if (sendInvitationYes && sendInvitationNo) {
        sendInvitationYes.addEventListener('change', toggleInvitationOptions);
        sendInvitationNo.addEventListener('change', toggleInvitationOptions);
        toggleInvitationOptions();
    }

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            syncAutoVerifyEmailState();
            syncEmailChannelAvailability();
        });
    }

    syncAutoVerifyEmailState();
    syncEmailChannelAvailability();

    const form = document.getElementById('user-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const typeSelectEl = document.getElementById('type');
            const container    = document.getElementById('auto-role-container');
            const selectedType = typeSelectEl?.value;

            if (selectedType && ROLE_SLUG_BY_TYPE[selectedType]) {
                const roleInputs = container?.querySelectorAll('input[name="roles[]"]') || [];
                if (roleInputs.length === 0) {
                    console.warn('[auto-role] roles[] missing at submit — re-injecting');
                    syncAutoRoleInput();
                }
            }
            if (!validateForm()) e.preventDefault();
        });
    }

    console.log('User creation form ready (simplified supervisor model).');
});
</script>
@endpush

@push('styles')
<style>
/* Scoped .hidden override (does not leak into layout header) */
#invitation-method-container.hidden,
#invitation-channels-section.hidden,
#password-section.hidden,
#password-notice.hidden,
#invitation-preview-container.hidden,
#fallback-delivery-info.hidden,
#security-supervisor-settings-content.hidden,
#sanitation-supervisor-role-section.hidden,
#email-valid-icon.hidden,
#email-invalid-icon.hidden,
#phone-valid-icon.hidden,
#phone-invalid-icon.hidden,
#auto-role-indicator.hidden {
    display: none !important;
}

.invitation-channel-option.channel-disabled {
    cursor: not-allowed !important;
    opacity: 0.55;
    background-color: rgba(var(--secondary-rgb), 0.08) !important;
    border-style: dashed !important;
}

.modern-radio-option--disabled {
    opacity: 0.55;
    cursor: not-allowed !important;
}

.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.toggle-modern input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #d1d5db;
    border: 2px solid #d1d5db;
    transition: .4s;
    border-radius: 34px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 2px; bottom: 2px;
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

#security-supervisor-toggle-btn,
#sanitation-supervisor-toggle-btn {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
#security-supervisor-toggle-btn:hover,
#sanitation-supervisor-toggle-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

#auto-role-indicator {
    padding: 6px 10px;
    border-radius: 6px;
    background-color: rgba(var(--primary-rgb), 0.08);
    border-left: 3px solid var(--primary);
    animation: role-fade-in 0.3s ease-out;
}
@keyframes role-fade-in {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

#user-form input:focus,
#user-form select:focus,
#user-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

@media (prefers-reduced-motion: reduce) {
    * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}
</style>
@endpush