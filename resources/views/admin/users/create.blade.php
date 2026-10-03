@extends('layouts.app')

@section('title', 'Create User')

@php
    /*
     |--------------------------------------------------------------------------
     | Communication-channel availability
     |--------------------------------------------------------------------------
     | Resolved once, up-front, with safe fallbacks. These flags drive the
     | server-rendered disabled state of the invitation channel checkboxes so
     | that the UI is correct even before JavaScript runs.
     |
     | NOTE: Avoid `//` inline comments inside @php blocks — they can confuse
     | the Blade compiler on some Laravel versions and produce the parse error
     | "unexpected token ':', expecting '('".
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
                    <!-- WhatsApp Status Indicator -->
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $whatsappReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $whatsappReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fab fa-whatsapp mr-1"></i>
                        WhatsApp: {{ $whatsappReady ? 'Ready' : 'Limited' }}
                    </div>

                    <!-- SMS Status Indicator -->
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $smsReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $smsReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas fa-comment-alt mr-1"></i>
                        SMS: {{ $smsReady ? 'Ready' : 'Limited' }}
                    </div>

                    <!-- Email Status Indicator -->
                    <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                         style="background-color: {{ $emailReady ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $emailReady ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas {{ $emailReady ? 'fa-envelope' : 'fa-envelope-open' }} mr-1"></i>
                        Email: {{ $emailReady ? 'Ready' : 'Limited' }}
                    </div>

                    <!-- Fallback Status Indicator -->
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

                        @if(session('created_user'))
                        <div class="mt-3 flex flex-wrap gap-3">
                            <a href="{{ route('admin.users.show', session('created_user')['id']) }}" class="btn-primary btn-sm flex items-center">
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

                    <!-- Email -->
                    <div>
                        <label for="email" class="block mb-2 font-medium" style="color: var(--text-primary);">Email Address *</label>
                        <div class="relative">
                            <input type="email" class="w-full p-2 border rounded @error('email') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   id="email" name="email" value="{{ old('email') }}" required
                                   placeholder="Enter email address">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="email-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="email-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);" id="email-fallback-info">
                            <i class="fas fa-shield-alt mr-1 text-green-500"></i>
                            <span>Email required for fallback delivery</span>
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
                        <!-- Password -->
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

                        <!-- Password Confirmation -->
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
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" id="auto_verify_email" name="auto_verify_email" value="1"
                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);"
                               {{ old('auto_verify_email') ? 'checked' : '' }}>
                        <label for="auto_verify_email" class="font-medium" style="color: var(--text-primary);">
                            Auto-Verify Email Address
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
            <!-- ========== 🔐 SECURITY PERSONNEL SUPERVISOR SECTION ========== -->
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
                                <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Security Supervisor Eligibility</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    Enable this to make the user eligible for security supervisor assignments.
                                    <strong>Specific supervisor roles (Team Lead, Section Lead, Post Commander) will be assigned separately</strong>
                                    through the Supervisor Assignment section.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Can be assigned as a Security Supervisor</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary); max-width: 600px;">
                                <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                                When enabled, this user will appear in the "Available Security Supervisors" list for supervisor assignments.
                            </p>
                        </div>
                        <div class="toggle-modern flex-shrink-0 ml-4">
                            <input type="checkbox" name="security_can_be_supervisor_toggle" id="security_can_be_supervisor_toggle" value="1"
                                   {{ old('security_can_be_supervisor') ? 'checked' : '' }} class="sr-only">
                            <label for="security_can_be_supervisor_toggle" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div id="security-supervisor-role-section" class="{{ old('security_can_be_supervisor') ? '' : 'hidden' }} transition-all duration-300">
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="security_supervisor_role" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Security Supervisor Level *
                                </label>
                                <select class="w-full p-2 border rounded @error('security_supervisor_role') border-red-500 @enderror"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="security_supervisor_role" name="security_supervisor_role">
                                    <option value="">Select Supervisor Level</option>
                                    <option value="team_lead" {{ old('security_supervisor_role') == 'team_lead' ? 'selected' : '' }}>
                                        Team Lead (Level 1)
                                    </option>
                                    <option value="section_lead" {{ old('security_supervisor_role') == 'section_lead' ? 'selected' : '' }}>
                                        Section Lead (Level 2)
                                    </option>
                                    <option value="post_commander" {{ old('security_supervisor_role') == 'post_commander' ? 'selected' : '' }}>
                                        Post Commander (Level 3)
                                    </option>
                                </select>
                                @error('security_supervisor_role')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="security_supervisor_score" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Supervisor Score (1-100)
                                </label>
                                <input type="number" class="w-full p-2 border rounded @error('security_supervisor_score') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       id="security_supervisor_score" name="security_supervisor_score"
                                       value="{{ old('security_supervisor_score', 50) }}"
                                       min="1" max="100" placeholder="Enter score 1-100">
                                @error('security_supervisor_score')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">Team Lead</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">Manages a small security team. Basic supervisor permissions.</p>
                            </div>
                            <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">Section Lead</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">Oversees multiple security teams. Can assign officers to posts.</p>
                            </div>
                            <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-flag mr-2" style="color: var(--primary);"></i>
                                    <span class="font-semibold text-sm" style="color: var(--text-primary);">Post Commander</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">Highest security supervisor level. Full access to all security features.</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="security_supervisor_certifications" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Certifications (JSON)
                            </label>
                            <textarea class="w-full p-2 border rounded @error('security_supervisor_certifications') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); font-family: monospace;"
                                      id="security_supervisor_certifications" name="security_supervisor_certifications"
                                      rows="3" placeholder='["Security Certification", "First Aid Training", "Team Leadership"]'>{{ old('security_supervisor_certifications') }}</textarea>
                            @error('security_supervisor_certifications')
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
                                    <li>• User becomes eligible for security supervisor assignments</li>
                                    <li>• Go to <strong>Security Supervisor Assignments</strong> to assign specific roles</li>
                                    <li>• Assign: Team Lead (Level 1), Section Lead (Level 2), or Post Commander (Level 3)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================ -->
            <!-- ========== 🧹 SANITATION PERSONNEL SECTION ========== -->
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

                    <!-- Eligibility toggle -->
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

                    <!-- ========== 🔥 SANITATION PERSONNEL ROLE SELECTION ========== -->
                    <div id="sanitation-supervisor-role-section" class="{{ old('sanitation_can_be_supervisor') ? '' : 'hidden' }} transition-all duration-300">
                        <!-- Personnel Role -->
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
                                Optional. Only personnel with a supervisor role appear here. The selected supervisor will be notified about this person's collection requests.
                            </div>
                        </div>

                        <!-- Additional Personnel Fields -->
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
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Required for drivers
                                </div>
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
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Required for drivers
                                </div>
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
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Format: Name: Phone Number (e.g., John Doe: +233241234567)
                            </div>
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
                                    <li>• A sanitation personnel record will be created with the selected role</li>
                                    <li>• The user will have access to sanitation features based on their role</li>
                                    <li>• Supervisors can manage workers and collection requests</li>
                                    <li>• Workers and drivers can perform collection tasks and update request statuses</li>
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
                                        <span>Automatic fallback to email</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ============================================================ --}}
                    {{-- 🚨 ALL CHANNELS UNAVAILABLE BANNER --}}
                    {{-- ============================================================ --}}
                    @if(!$anyChannelReady)
                    <div class="rounded p-4 border-l-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left-color: var(--danger);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--danger);">No Delivery Channels Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    None of the communication services (SMS, WhatsApp, Email) are configured.
                                    Invitations cannot be sent until at least one channel is set up. You can still create the user
                                    without an invitation.
                                </p>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <a href="{{ route('admin.system-settings.index') }}#whatsapp-configuration" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure WhatsApp
                                    </a>
                                    <a href="{{ route('admin.system-settings.index') }}#sms-configuration" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure SMS
                                    </a>
                                    <a href="{{ route('admin.email-config.index') }}" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure Email
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(!$whatsappReady && $anyChannelReady)
                    <div class="rounded p-3 border-l-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                        <div class="flex items-start">
                            <i class="fab fa-whatsapp mr-2 mt-0.5" style="color: #25D366;"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--text-primary);">WhatsApp Service Not Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    WhatsApp invitations cannot be sent. The system will automatically fall back to email if configured.
                                </p>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <strong>Current Status:</strong> {{ $whatsappStatus['health'] ?? 'Not Configured' }} - {{ $whatsappStatus['message'] ?? 'WhatsApp service not configured in system settings' }}
                                </div>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2">
                                    <a href="{{ route('admin.system-settings.index') }}#whatsapp-configuration" class="btn-primary btn-sm flex items-center w-fit">
                                        <i class="fas fa-cog mr-2"></i> Configure WhatsApp Service
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(!$smsReady && $anyChannelReady)
                    <div class="rounded p-3 border-l-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                        <div class="flex items-start">
                            <i class="fas fa-comment-alt mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--text-primary);">SMS Service Not Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    SMS invitations cannot be sent. The system will automatically fall back to email if configured.
                                </p>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <strong>Current Status:</strong> {{ $smsStatus['health'] ?? 'Not Configured' }} - {{ $smsStatus['message'] ?? 'SMS service not configured in system settings' }}
                                </div>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2">
                                    <a href="{{ route('admin.system-settings.index') }}#sms-configuration" class="btn-primary btn-sm flex items-center w-fit">
                                        <i class="fas fa-cog mr-2"></i> Configure SMS Service
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(!$emailReady && $anyChannelReady)
                    <div class="rounded p-3 border-l-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--text-primary);">Email Service Limited</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    Email invitations may have limited delivery. Ensure user has a valid email address.
                                </p>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <strong>Current Status:</strong> {{ $emailStatus['health'] ?? 'unknown' }} - {{ $emailStatus['message'] ?? 'Email service not configured' }}
                                </div>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2">
                                    <a href="{{ route('admin.email-config.index') }}" class="btn-primary btn-sm flex items-center w-fit">
                                        <i class="fas fa-cog mr-2"></i> Configure Email Service
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Invitation Toggle -->
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

                    <!-- Invitation Configuration -->
                    <div id="invitation-method-container" class="hidden space-y-6">
                        <div id="invitation-channels-section" class="hidden">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Channels *</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="invitation-channels-options">
                                {{-- ============================================================ --}}
                                {{-- SMS CHANNEL --}}
                                {{-- ============================================================ --}}
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

                                {{-- ============================================================ --}}
                                {{-- WHATSAPP CHANNEL --}}
                                {{-- ============================================================ --}}
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

                                {{-- ============================================================ --}}
                                {{-- EMAIL CHANNEL --}}
                                {{-- ============================================================ --}}
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

                            <div class="mt-3 p-3 rounded border" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                                    <span>Select one or more channels. SMS/WhatsApp will automatically fallback to email if needed.</span>
                                </div>
                            </div>

                            <div id="email-service-details" class="mt-3 p-3 rounded border" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div class="flex items-center">
                                        <i class="fas fa-server mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Provider:</span>
                                            <span class="ml-1" style="color: var(--text-primary);">{{ $emailStatus['provider'] ?? 'Unknown' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-heartbeat mr-2" style="color: {{ ($emailStatus['health'] ?? '') === 'healthy' ? 'var(--success)' : (($emailStatus['health'] ?? '') === 'degraded' ? 'var(--warning)' : 'var(--danger)') }};"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Status:</span>
                                            <span class="ml-1 capitalize {{ ($emailStatus['health'] ?? '') === 'healthy' ? 'text-green-600' : (($emailStatus['health'] ?? '') === 'degraded' ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ $emailStatus['health'] ?? 'unknown' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Success Rate:</span>
                                            <span class="ml-1" style="color: var(--text-primary);">{{ $emailStatus['statistics']['success_rate'] ?? 0 }}%</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope-open mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Today's Usage:</span>
                                            <span class="ml-1" style="color: var(--text-primary);">{{ $emailStatus['limits']['daily_limit']['used_today'] ?? 0 }}/{{ $emailStatus['limits']['daily_limit']['max_emails_per_day'] ?? 0 }}</span>
                                        </div>
                                    </div>
                                </div>
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
                                      placeholder="Add a custom welcome message to the invitation (optional). You can include instructions, contact information, or specific details about the user's role."
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
                            <div class="rounded p-3 text-sm font-mono whitespace-pre-wrap" id="invitation-preview-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            </div>
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
                                <strong>Automatic Fallback:</strong> SMS/WhatsApp failures fallback to email
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
                                If SMS fails, system automatically attempts email delivery as fallback.
                            </p>
                        </div>

                        <div class="p-4 rounded border" style="background-color: rgba(37, 211, 102, 0.05); border-color: rgba(37, 211, 102, 0.2);">
                            <div class="flex items-center mb-2">
                                <i class="fab fa-whatsapp mr-2" style="color: #25D366;"></i>
                                <h4 class="font-semibold text-sm" style="color: var(--text-primary);">WhatsApp Fallback</h4>
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                If WhatsApp fails, system automatically attempts email delivery as fallback.
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
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status">
                        <!-- Form status messages will appear here -->
                    </div>
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

<!-- ============================================================ -->
<!-- ========== JAVASCRIPT ========== -->
<!-- ============================================================ -->
<script>
// Reset form function for "Create Another User" button
function resetForm() {
    const form = document.getElementById('user-form');
    if (form) {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(field => {
            // Never touch disabled fields (channel checkboxes for unavailable services)
            if (field.disabled) return;
            if (field.type !== 'checkbox' && field.type !== 'radio') {
                field.value = '';
            } else if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = false;
            }
        });

        const statusSelect = document.getElementById('status');
        if (statusSelect) statusSelect.value = 'active';

        const sendInvitationNo = document.getElementById('send_invitation_no');
        if (sendInvitationNo) sendInvitationNo.checked = true;

        const typeSelect = document.getElementById('type');
        if (typeSelect) typeSelect.dispatchEvent(new Event('change'));

        const sendInvitationNoEvent = document.getElementById('send_invitation_no');
        if (sendInvitationNoEvent) sendInvitationNoEvent.dispatchEvent(new Event('change'));

        // Reset security supervisor toggle
        const securityCheckbox = document.getElementById('security_can_be_supervisor');
        const securityToggleCheckbox = document.getElementById('security_can_be_supervisor_toggle');
        if (securityCheckbox && securityToggleCheckbox) {
            securityCheckbox.checked = false;
            securityToggleCheckbox.checked = false;
            const toggleBtn = document.getElementById('security-supervisor-toggle-btn');
            if (toggleBtn) toggleBtn.click();
        }

        // Reset sanitation supervisor toggle
        const sanitationCheckbox = document.getElementById('sanitation_can_be_supervisor');
        const sanitationToggleCheckbox = document.getElementById('sanitation_can_be_supervisor_toggle');
        if (sanitationCheckbox && sanitationToggleCheckbox) {
            sanitationCheckbox.checked = false;
            sanitationToggleCheckbox.checked = false;
            const toggleBtn = document.getElementById('sanitation-supervisor-toggle-btn');
            if (toggleBtn) toggleBtn.click();
        }

        // Reset sanitation personnel fields
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
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing user creation form with channel-availability gating...');

    // Service status from backend
    const smsSystemReady = {{ $smsReady ? 'true' : 'false' }};
    const whatsappSystemReady = {{ $whatsappReady ? 'true' : 'false' }};
    const emailSystemReady = {{ $emailReady ? 'true' : 'false' }};
    const anyChannelReady = smsSystemReady || whatsappSystemReady || emailSystemReady;

    // Elements - Invitation & Password
    const sendInvitationYes = document.getElementById('send_invitation_yes');
    const sendInvitationNo = document.getElementById('send_invitation_no');
    const invitationMethodContainer = document.getElementById('invitation-method-container');
    const invitationChannelsSection = document.getElementById('invitation-channels-section');
    const passwordSection = document.getElementById('password-section');
    const passwordNotice = document.getElementById('password-notice');
    const passwordInput = document.getElementById('password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const statusSelect = document.getElementById('status');
    const formStatus = document.getElementById('form-status');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const emailFallbackInfo = document.getElementById('email-fallback-info');
    const smsFallbackWarning = document.getElementById('sms-fallback-warning');
    const typeSelect = document.getElementById('type');

    // Security personnel elements
    const securitySection = document.getElementById('security-supervisor-section');
    const securityToggleBtn = document.getElementById('security-supervisor-toggle-btn');
    const securityCheckbox = document.getElementById('security_can_be_supervisor');
    const securityToggleCheckbox = document.getElementById('security_can_be_supervisor_toggle');
    const securitySettingsContent = document.getElementById('security-supervisor-settings-content');
    const securityRoleSection = document.getElementById('security-supervisor-role-section');
    const securityPersonnelType = {{ \App\Models\User::TYPE_SECURITY_PERSONNEL ?? 3 }};

    // Sanitation personnel elements
    const sanitationSection = document.getElementById('sanitation-supervisor-section');
    const sanitationToggleBtn = document.getElementById('sanitation-supervisor-toggle-btn');
    const sanitationCheckbox = document.getElementById('sanitation_can_be_supervisor');
    const sanitationToggleCheckbox = document.getElementById('sanitation_can_be_supervisor_toggle');
    const sanitationSettingsContent = document.getElementById('sanitation-supervisor-settings-content');
    const sanitationRoleSection = document.getElementById('sanitation-supervisor-role-section');
    const sanitationPersonnelType = {{ \App\Models\User::TYPE_SANITATION_PERSONNEL ?? 2 }};

    // ================================================================ //
    // ========== SECURITY SUPERVISOR FUNCTIONS ========== //
    // ================================================================ //
    function toggleSecuritySupervisorSection() {
        if (typeSelect && securitySection) {
            if (typeSelect.value == securityPersonnelType) {
                securitySection.style.display = 'block';
            } else {
                securitySection.style.display = 'none';
                if (securityToggleBtn && securityCheckbox) {
                    setSecuritySupervisorToggleState(false);
                }
            }
        }
    }

    function setSecuritySupervisorToggleState(enabled) {
        if (securityCheckbox) securityCheckbox.checked = enabled;
        if (securityToggleCheckbox) securityToggleCheckbox.checked = enabled;

        if (enabled) {
            securityToggleBtn.style.backgroundColor = 'var(--success)';
            securityToggleBtn.style.borderColor = 'var(--success)';
            securityToggleBtn.style.color = 'white';
            securityToggleBtn.style.boxShadow = '0 2px 8px rgba(var(--success-rgb), 0.3)';
            securityToggleBtn.querySelector('i').className = 'fas fa-toggle-on mr-2 text-lg';
            securityToggleBtn.querySelector('.toggle-text').textContent = 'Eligible';
            securityToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-white toggle-indicator';
            securitySettingsContent.classList.remove('opacity-50', 'pointer-events-none');
            securityRoleSection.classList.remove('hidden');
        } else {
            securityToggleBtn.style.backgroundColor = 'var(--bg-secondary)';
            securityToggleBtn.style.borderColor = 'var(--border-color)';
            securityToggleBtn.style.color = 'var(--text-secondary)';
            securityToggleBtn.style.boxShadow = 'none';
            securityToggleBtn.querySelector('i').className = 'fas fa-toggle-off mr-2 text-lg';
            securityToggleBtn.querySelector('.toggle-text').textContent = 'Not Eligible';
            securityToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-gray-400 toggle-indicator';
            securitySettingsContent.classList.add('opacity-50', 'pointer-events-none');
            securityRoleSection.classList.add('hidden');
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

    // ================================================================ //
    // ========== SANITATION FUNCTIONS ========== //
    // ================================================================ //
    function toggleSanitationSection() {
        if (typeSelect && sanitationSection) {
            if (typeSelect.value == sanitationPersonnelType) {
                sanitationSection.style.display = 'block';
            } else {
                sanitationSection.style.display = 'none';
                if (sanitationToggleBtn && sanitationCheckbox) {
                    setSanitationToggleState(false);
                }
            }
        }
    }

    function setSanitationToggleState(enabled) {
        if (sanitationCheckbox) sanitationCheckbox.checked = enabled;
        if (sanitationToggleCheckbox) sanitationToggleCheckbox.checked = enabled;

        if (enabled) {
            sanitationToggleBtn.style.backgroundColor = 'var(--success)';
            sanitationToggleBtn.style.borderColor = 'var(--success)';
            sanitationToggleBtn.style.color = 'white';
            sanitationToggleBtn.style.boxShadow = '0 2px 8px rgba(var(--success-rgb), 0.3)';
            sanitationToggleBtn.querySelector('i').className = 'fas fa-toggle-on mr-2 text-lg';
            sanitationToggleBtn.querySelector('.toggle-text').textContent = 'Enabled';
            sanitationToggleBtn.querySelector('.toggle-indicator').className = 'ml-2 w-2 h-2 rounded-full bg-white toggle-indicator';
            sanitationSettingsContent.classList.remove('opacity-50', 'pointer-events-none');
            sanitationRoleSection.classList.remove('hidden');
        } else {
            sanitationToggleBtn.style.backgroundColor = 'var(--bg-secondary)';
            sanitationToggleBtn.style.borderColor = 'var(--border-color)';
            sanitationToggleBtn.style.color = 'var(--text-secondary)';
            sanitationToggleBtn.style.boxShadow = 'none';
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

    // ================================================================ //
    // ========== TYPE SELECT CHANGE HANDLER ========== //
    // ================================================================ //
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            toggleSecuritySupervisorSection();
            toggleSanitationSection();
        });
        toggleSecuritySupervisorSection();
        toggleSanitationSection();
    }

    // ================================================================ //
    // ========== INVITATION SYSTEM ========== //
    // ================================================================ //
    initializeServiceStatus();
    initializeInvitationSystem();
    initializeFallbackSystem();

    function initializeServiceStatus() {
        updateInvitationChannelAvailability();
    }

    /**
     * Enforce channel availability purely from the *server-rendered* state.
     *
     * IMPORTANT: We do NOT re-enable a checkbox that the server disabled
     * (that would let an admin select a channel whose service is down).
     * We only *tighten* the UI: if the server disabled it, we ensure it's
     * unchecked and its parent card is visually marked as disabled.
     */
    function updateInvitationChannelAvailability() {
        const channelCheckboxes = document.querySelectorAll('input[name="invitation_channels[]"]');

        channelCheckboxes.forEach(checkbox => {
            const channel = checkbox.value;
            let isAvailable = false;

            switch (channel) {
                case 'sms':      isAvailable = smsSystemReady;      break;
                case 'whatsapp': isAvailable = whatsappSystemReady; break;
                case 'email':    isAvailable = emailSystemReady;    break;
            }

            const option = checkbox.closest('.invitation-channel-option');
            if (!option) return;

            if (!isAvailable) {
                // Force-disable & uncheck — the server likely already did,
                // but keep this as a belt-and-braces measure.
                checkbox.disabled = true;
                checkbox.checked = false;
                option.classList.add('channel-disabled');
                option.setAttribute('aria-disabled', 'true');
                option.style.opacity = '0.5';
                option.style.cursor = 'not-allowed';
                option.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
            } else {
                // Only re-enable if the server rendered it as enabled.
                // (Server-side disabled attribute is the source of truth.)
                if (!option.hasAttribute('data-server-disabled')) {
                    checkbox.disabled = false;
                    option.classList.remove('channel-disabled');
                    option.removeAttribute('aria-disabled');
                    option.style.opacity = '1';
                    option.style.cursor = 'pointer';
                    option.style.backgroundColor = 'var(--bg-secondary)';
                }
            }
        });
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function initializeInvitationSystem() {
        if (sendInvitationYes && sendInvitationNo) {
            sendInvitationYes.addEventListener('change', toggleInvitationOptions);
            sendInvitationNo.addEventListener('change', toggleInvitationOptions);
            toggleInvitationOptions();
        }
        setupInvitationChannels();
        setupInvitationEvents();
    }

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
        updateInvitationPreview();
        updateFallbackSystem();
    }

    function setupInvitationChannels() {
        const channelCheckboxes = document.querySelectorAll('input[name="invitation_channels[]"]');
        channelCheckboxes.forEach(checkbox => {
            updateChannelVisualState(checkbox);
            checkbox.addEventListener('change', function() {
                updateChannelVisualState(this);
                updateInvitationPreview();
                updateFallbackSystem();
            });
        });

        document.querySelectorAll('.invitation-channel-option').forEach(option => {
            option.addEventListener('click', function(e) {
                // Ignore clicks on already-disabled cards
                if (this.classList.contains('channel-disabled') ||
                    this.style.opacity === '0.5') {
                    return;
                }
                if (e.target.type === 'checkbox') return;
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    }

    function updateChannelVisualState(checkbox) {
        const option = checkbox.closest('.invitation-channel-option');
        if (!option) return;
        if (checkbox.disabled) return; // never style disabled cards as "selected"
        if (checkbox.checked) {
            option.style.borderColor = 'var(--primary)';
            option.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            option.classList.add('channel-selected');
        } else {
            option.style.borderColor = 'var(--border-color)';
            option.style.backgroundColor = 'var(--bg-secondary)';
            option.classList.remove('channel-selected');
        }
    }

    function getSelectedChannels() {
        return Array.from(document.querySelectorAll('input[name="invitation_channels[]"]:checked'))
            .filter(cb => !cb.disabled)
            .map(cb => cb.value);
    }

    function setupInvitationEvents() {
        const nameInput = document.getElementById('name');
        const invitationTypeSelect = document.getElementById('invitation_type');
        const invitationMessage = document.getElementById('invitation_message');

        if (nameInput) nameInput.addEventListener('input', updateInvitationPreview);
        if (emailInput) emailInput.addEventListener('input', function() {
            updateInvitationPreview();
            updateFallbackSystem();
        });
        if (phoneInput) phoneInput.addEventListener('input', function() {
            updateInvitationPreview();
            updateFallbackSystem();
        });
        if (invitationTypeSelect) invitationTypeSelect.addEventListener('change', updateInvitationPreview);
        if (invitationMessage) {
            invitationMessage.addEventListener('input', function() {
                updateCharCount();
                updateInvitationPreview();
            });
        }
    }

    function updateCharCount() {
        const invitationMessage = document.getElementById('invitation_message');
        const charCount = document.getElementById('char-count');
        if (invitationMessage && charCount) {
            const length = invitationMessage.value.length;
            charCount.textContent = length;
            charCount.style.color = length > 950 ? 'var(--danger)'
                : length > 900 ? 'var(--warning)'
                : 'var(--text-secondary)';
        }
    }

    function updateInvitationPreview() {
        const invitationPreviewContainer = document.getElementById('invitation-preview-container');
        const invitationPreviewContent = document.getElementById('invitation-preview-content');
        const invitationChannelBadge = document.getElementById('invitation-channel-badge');
        const invitationPreviewNote = document.getElementById('invitation-preview-note');

        if (!sendInvitationYes || !sendInvitationYes.checked) {
            if (invitationPreviewContainer) invitationPreviewContainer.classList.add('hidden');
            return;
        }

        const selectedChannels = getSelectedChannels();
        if (selectedChannels.length === 0) {
            if (invitationPreviewContainer) invitationPreviewContainer.classList.add('hidden');
            return;
        }

        const name = document.getElementById('name')?.value || 'User';
        const email = emailInput?.value || 'user@example.com';
        const phone = phoneInput?.value || '';
        const invitationType = document.getElementById('invitation_type')?.value || 'welcome';
        const customMessage = document.getElementById('invitation_message')?.value || '';

        let invitationContent = '';
        switch (invitationType) {
            case 'welcome':
                invitationContent = `👋 Hello ${name}!\n\n🎉 You've been invited to join our platform!\n\nYour account has been created. Please set up your password to activate your account.\n\n`;
                break;
            case 'registration':
                invitationContent = `👋 Hello ${name}!\n\n📋 Account Registration\n\nYour registration is complete. Please set your password to finalize your account setup.\n\n`;
                break;
            case 'account_setup':
                invitationContent = `👋 Hello ${name}!\n\n⚙️ Account Setup Required\n\nPlease complete your account setup by creating your password.\n\n`;
                break;
            case 'password_setup':
                invitationContent = `👋 Hello ${name}!\n\n🔐 Password Setup Required\n\nPlease set your password to activate your account.\n\n`;
                break;
        }

        invitationContent += `📧 Email: ${email}\n`;
        if (phone) invitationContent += `📱 Phone: ${phone}\n`;
        invitationContent += `📨 Delivery Channel(s): ${selectedChannels.map(c => c.toUpperCase()).join(', ')}\n\n`;
        if (customMessage) invitationContent += `💬 Message:\n${customMessage}\n\n`;
        invitationContent += `🔐 SECURE INVITATION LINK WILL BE SENT VIA SELECTED CHANNELS`;

        if (invitationPreviewContent) invitationPreviewContent.textContent = invitationContent;
        if (invitationPreviewContainer) invitationPreviewContainer.classList.remove('hidden');
        if (invitationChannelBadge) invitationChannelBadge.textContent = selectedChannels.map(c => c.toUpperCase()).join(' + ');
        if (invitationPreviewNote) {
            invitationPreviewNote.textContent = selectedChannels.includes('sms') || selectedChannels.includes('whatsapp')
                ? 'SMS/WhatsApp invitations will automatically fallback to email if delivery fails.'
                : 'This invitation will be sent via the selected channels.';
        }
        updateFormStatus();
    }

    function updateFormStatus() {
        if (!formStatus) return;

        const selectedChannels = getSelectedChannels();
        const userEmail = emailInput?.value.trim() || '';
        const isEmailValid = userEmail && validateEmail(userEmail);

        let statusMessage = '';
        if (selectedChannels.length > 0) {
            statusMessage = `User will be created with invitation sent via ${selectedChannels.map(c => c.toUpperCase()).join(', ')}.`;
        } else {
            statusMessage = anyChannelReady
                ? 'Select invitation channels to send invitation.'
                : 'No delivery channels available. User will be created without invitation.';
        }

        const serviceWarnings = [];
        if (selectedChannels.includes('sms') && !smsSystemReady) serviceWarnings.push('SMS service not available');
        if (selectedChannels.includes('whatsapp') && !whatsappSystemReady) serviceWarnings.push('WhatsApp service not available');
        if (selectedChannels.includes('email') && !emailSystemReady) serviceWarnings.push('Email service limited');

        if (selectedChannels.some(c => ['sms', 'whatsapp'].includes(c)) && isEmailValid && emailSystemReady) {
            statusMessage += ` ┄ Will fallback to email if needed.`;
        }

        if (serviceWarnings.length > 0) statusMessage += ` ⚠️ ${serviceWarnings.join(', ')}`;

        formStatus.innerHTML = `<div class="flex items-center"><i class="fas ${selectedChannels.length > 0 ? 'fa-user-check text-green-600' : 'fa-exclamation-triangle text-yellow-600'} mr-2"></i><span>${statusMessage}</span></div>`;
    }

    function updateRadioVisualState(radio) {
        if (!radio) return;
        const option = radio.closest('.modern-radio-option');
        if (option) {
            if (radio.checked) option.classList.add('modern-radio-option--checked');
            else option.classList.remove('modern-radio-option--checked');
        }
    }

    function initializeFallbackSystem() {
        if (emailInput) emailInput.addEventListener('input', function() { validateEmailForFallback(this.value); updateFallbackSystem(); });
        if (phoneInput) phoneInput.addEventListener('input', function() { validatePhoneForFallback(this.value); updateFallbackSystem(); });
        updateFallbackSystem();
    }

    function validateEmailForFallback(email) {
        const isValid = validateEmail(email);
        const emailValidIcon = document.getElementById('email-valid-icon');
        const emailInvalidIcon = document.getElementById('email-invalid-icon');
        if (emailValidIcon && emailInvalidIcon) {
            if (isValid) { emailValidIcon.classList.remove('hidden'); emailInvalidIcon.classList.add('hidden'); }
            else if (email.length > 0) { emailValidIcon.classList.add('hidden'); emailInvalidIcon.classList.remove('hidden'); }
            else { emailValidIcon.classList.add('hidden'); emailInvalidIcon.classList.add('hidden'); }
        }
        return isValid;
    }

    function validatePhoneForFallback(phone) {
        const phoneValidIcon = document.getElementById('phone-valid-icon');
        const phoneInvalidIcon = document.getElementById('phone-invalid-icon');
        const isValid = phone.length >= 10;
        if (phoneValidIcon && phoneInvalidIcon) {
            if (isValid) { phoneValidIcon.classList.remove('hidden'); phoneInvalidIcon.classList.add('hidden'); }
            else if (phone.length > 0) { phoneValidIcon.classList.add('hidden'); phoneInvalidIcon.classList.remove('hidden'); }
            else { phoneValidIcon.classList.add('hidden'); phoneInvalidIcon.classList.add('hidden'); }
        }
        return isValid;
    }

    function updateFallbackSystem() {
        const userEmail = emailInput?.value.trim() || '';
        const userPhone = phoneInput?.value.trim() || '';
        const isEmailValid = validateEmail(userEmail);
        const isPhoneValid = userPhone.length >= 10;
        const selectedChannels = getSelectedChannels();

        if (emailFallbackInfo) {
            emailFallbackInfo.innerHTML = isEmailValid && emailSystemReady
                ? '<i class="fas fa-shield-alt mr-1 text-green-500"></i><span>Email ready for fallback delivery</span>'
                : '<i class="fas fa-exclamation-triangle mr-1 text-amber-500"></i><span>Valid email required for fallback delivery</span>';
        }

        const smsOrWhatsappSelected = selectedChannels.some(c => ['sms', 'whatsapp'].includes(c));
        if (smsFallbackWarning) {
            if (smsOrWhatsappSelected && isPhoneValid && (!isEmailValid || !emailSystemReady)) {
                smsFallbackWarning.classList.remove('hidden');
                smsFallbackWarning.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> ${!isEmailValid ? 'No valid email for fallback' : 'Email service unavailable for fallback'}`;
            } else {
                smsFallbackWarning.classList.add('hidden');
            }
        }

        const fallbackDeliveryInfo = document.getElementById('fallback-delivery-info');
        if (fallbackDeliveryInfo) {
            if (smsOrWhatsappSelected && isEmailValid && emailSystemReady) fallbackDeliveryInfo.classList.remove('hidden');
            else fallbackDeliveryInfo.classList.add('hidden');
        }

        const emailServiceDetails = document.getElementById('email-service-details');
        if (emailServiceDetails) {
            if (selectedChannels.includes('email')) emailServiceDetails.classList.remove('hidden');
            else emailServiceDetails.classList.add('hidden');
        }
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

            if (selectedChannels.includes('email') && !isEmailValid) {
                alert('Valid email address is required for email invitations.');
                emailInput.focus();
                return false;
            }

            if ((selectedChannels.includes('whatsapp') || selectedChannels.includes('sms')) && !userPhone) {
                alert('Phone number is required for SMS/WhatsApp invitations.');
                phoneInput.focus();
                return false;
            }

            // Defence in depth: even if a disabled channel slipped through,
            // reject it here.
            for (const ch of selectedChannels) {
                if (ch === 'sms' && !smsSystemReady) { alert('SMS service is not available.'); return false; }
                if (ch === 'whatsapp' && !whatsappSystemReady) { alert('WhatsApp service is not available.'); return false; }
                if (ch === 'email' && !emailSystemReady) { alert('Email service is not available.'); return false; }
            }
        }
        return true;
    }

    const form = document.getElementById('user-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) e.preventDefault();
        });
    }

    updateCharCount();
    console.log('User creation form initialization complete (channel gating active)');
});
</script>

<style>
/* ============================================================ */
/* CHANNEL AVAILABILITY STYLING                                  */
/* ============================================================ */

.invitation-channel-option {
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.invitation-channel-option:hover:not(.channel-disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

/* Disabled/unavailable channel — greyed out, non-interactive */
.invitation-channel-option.channel-disabled {
    cursor: not-allowed !important;
    opacity: 0.55;
    background-color: rgba(var(--secondary-rgb), 0.08) !important;
    border-style: dashed !important;
    position: relative;
}

.invitation-channel-option.channel-disabled::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: repeating-linear-gradient(
        45deg,
        rgba(0, 0, 0, 0.03),
        rgba(0, 0, 0, 0.03) 10px,
        transparent 10px,
        transparent 20px
    );
    pointer-events: none;
    border-radius: inherit;
}

.invitation-channel-option.channel-disabled input {
    cursor: not-allowed;
}

.invitation-channel-option.channel-disabled .text-green-600,
.invitation-channel-option.channel-disabled .text-red-600 {
    font-weight: 600;
}

/* Disabled send-invitation radio card */
.modern-radio-option--disabled {
    opacity: 0.55;
    cursor: not-allowed !important;
}

.modern-radio-option--disabled .modern-radio-label {
    cursor: not-allowed;
}

.channel-selected {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}

/* WhatsApp specific styling */
.fa-whatsapp {
    color: #25D366;
}

/* Channel availability colors */
.text-green-600 { color: #16a34a; }
.text-red-600   { color: #dc2626; }
.text-yellow-600{ color: #ca8a04; }
.text-blue-600  { color: #2563eb; }

/* Email service details styling */
#email-service-details {
    border-left: 4px solid var(--info);
}

/* Fallback delivery info styling */
#fallback-delivery-info {
    border-left: 4px solid var(--info);
}

/* Invitation preview styling */
#invitation-preview-content {
    font-family: 'Courier New', monospace;
    line-height: 1.4;
    max-height: 200px;
    overflow-y: auto;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

/* Modern Radio Button Styles */
.modern-radio-option {
    position: relative;
    transition: all 0.3s ease;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background-color: var(--bg-secondary);
    cursor: pointer;
    overflow: hidden;
}

.modern-radio-option:hover:not(.modern-radio-option--disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

.modern-radio-option--checked {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(114, 103, 240, 0.1), rgba(114, 103, 240, 0.05)) !important;
    box-shadow: 0 8px 25px rgba(114, 103, 240, 0.15) !important;
    transform: translateY(-2px);
}

.modern-radio-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.modern-radio-label {
    display: block;
    cursor: pointer;
    padding: 0;
    margin: 0;
}

.modern-radio-content {
    display: flex;
    align-items: center;
    padding: 20px;
    position: relative;
}

.modern-radio-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    font-size: 20px;
    transition: all 0.3s ease;
    background: var(--bg-tertiary);
}

.modern-radio-option--checked .modern-radio-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
    color: white !important;
    transform: scale(1.1);
}

.modern-radio-text { flex: 1; }

.modern-radio-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.modern-radio-description {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.modern-radio-check {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    background: white;
}

.modern-radio-option--checked .modern-radio-check {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: white !important;
    transform: scale(1.1);
}

.modern-radio-check i {
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modern-radio-option--checked .modern-radio-check i {
    opacity: 1 !important;
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

.toggle-modern input:focus + .toggle-slider {
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.3);
}

/* Dark mode toggle styles */
[data-theme="dark"] .toggle-slider {
    background-color: #4b5563;
    border-color: #4b5563;
}

[data-theme="dark"] .toggle-slider:before {
    background-color: #e5e7eb;
}

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

[data-theme="dark"] .invitation-channel-option.channel-disabled {
    background-color: rgba(255, 255, 255, 0.03) !important;
    border-color: #374151 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-2.md\:grid-cols-3 { grid-template-columns: 1fr; }

    .modern-radio-content { padding: 16px; }
    .modern-radio-icon { width: 40px; height: 40px; font-size: 18px; margin-right: 12px; }
    .modern-radio-title { font-size: 14px; }
    .modern-radio-description { font-size: 12px; }

    #security-supervisor-toggle-btn,
    #sanitation-supervisor-toggle-btn {
        padding: 0.25rem 0.75rem;
        font-size: 12px;
    }

    .toggle-modern { width: 40px; height: 20px; }
    .toggle-slider:before { height: 14px; width: 14px; left: 1px; bottom: 1px; }
    .toggle-modern input:checked + .toggle-slider:before { transform: translateX(20px); }
}

/* Focus styles */
input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }

.hidden { display: none !important; }

/* Loading state */
#submit-button.loading { opacity: 0.7; cursor: not-allowed; }
#submit-button.loading i { animation: spin 1s linear infinite; }

@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>