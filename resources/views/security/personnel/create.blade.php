@extends('layouts.secu')

@section('title', 'Add Security Personnel')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%); color: white; border-color: var(--success);">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--success);"></i>
                        Add Security Personnel
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Create a new security personnel account</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Area Supervisor
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Communication Service Status Indicators -->
                <div class="flex items-center space-x-2 mr-4">
                    <div class="flex items-center px-3 py-1 rounded-full text-xs font-medium" 
                         style="background-color: {{ ($whatsappStatus['system_ready'] ?? false) ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ ($whatsappStatus['system_ready'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fab fa-whatsapp mr-1"></i>
                        {{ ($whatsappStatus['system_ready'] ?? false) ? 'WhatsApp Ready' : 'WhatsApp Limited' }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-xs font-medium" 
                         style="background-color: {{ ($smsStatus['system_ready'] ?? false) ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ ($smsStatus['system_ready'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas fa-comment-alt mr-1"></i>
                        {{ ($smsStatus['system_ready'] ?? false) ? 'SMS Ready' : 'SMS Limited' }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-xs font-medium" 
                         style="background-color: {{ $emailStatus['system_ready'] ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $emailStatus['system_ready'] ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas {{ $emailStatus['system_ready'] ? 'fa-envelope' : 'fa-envelope-open' }} mr-1"></i>
                        {{ $emailStatus['system_ready'] ? 'Email Ready' : 'Email Limited' }}
                    </div>
                </div>
                <a href="{{ route('security.personnel.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Personnel
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
            <span style="color: var(--text-primary);">{{ session('success') }}</span>
            @if(session('created_user_id'))
            <a href="{{ route('security.personnel.show', session('created_user_id')) }}"
               class="ml-4 text-sm px-3 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                <i class="fas fa-eye mr-1"></i> View User
            </a>
            @endif
            @if(session('invitation_result') && session('invitation_result')['success'] ?? false)
            <span class="ml-4 px-3 py-1 rounded text-sm" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                <i class="fas fa-paper-plane mr-1"></i> 
                Invitation sent via {{ implode(', ', session('invitation_result')['channels_successful'] ?? []) }}
            </span>
            @endif
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
            <span style="color: var(--text-primary);">{{ session('error') }}</span>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="card p-4 border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
            <div>
                <span class="font-medium" style="color: var(--danger);">Validation Errors:</span>
                <ul class="mt-1 text-sm" style="color: var(--text-secondary);">
                    @foreach($errors->all() as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Info Alert -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-lg mr-3 mt-0.5" style="color: var(--info);"></i>
            <div>
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Area Supervisor Authority</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    As an Area Supervisor, you can create security personnel and assign them to your accessible posts.
                    You can also mark them as <strong>eligible for supervisor roles</strong> immediately.
                    Actual supervisor roles (Team Lead, Section Lead, etc.) are assigned separately.
                </p>
            </div>
        </div>
    </div>

    <!-- Create Form -->
    <form action="{{ route('security.personnel.store') }}" method="POST" id="personnel-form" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-cols-1 gap-6">

            <!-- Personal Information -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-id-card mr-2" style="color: var(--primary);"></i>
                        Personal Information
                    </h3>
                    <span class="px-2 py-1 text-xs rounded-full badge-info">Required</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block mb-2 font-medium" style="color: var(--text-primary);">Full Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="w-full p-2 border rounded @error('name') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Enter full name" required>
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block mb-2 font-medium" style="color: var(--text-primary);">Email Address *</label>
                        <div class="relative">
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="w-full p-2 border rounded @error('email') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Enter email address" required>
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="email-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="email-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Required for email notifications and fallback delivery
                        </div>
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block mb-2 font-medium" style="color: var(--text-primary);">Phone Number *</label>
                        <div class="relative">
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                                   class="w-full p-2 border rounded @error('phone') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="+233XXXXXXXXX or 0XXXXXXXXX" required>
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="phone-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="phone-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Format: +233XXXXXXXXX or 0XXXXXXXXX (Auto-standardized to +233 format)
                        </div>
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="username" class="block mb-2 font-medium" style="color: var(--text-primary);">Username</label>
                        <input type="text" id="username" name="username" value="{{ old('username') }}"
                               class="w-full p-2 border rounded @error('username') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Optional username - auto-generated if empty">
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Leave empty to auto-generate a unique username
                        </div>
                        @error('username')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Security & Access -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                        Security & Access
                    </h3>
                    <span class="px-2 py-1 text-xs rounded-full badge-warning">Required</span>
                </div>
                
                <!-- Password Section -->
                <div id="password-section">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block mb-2 font-medium" style="color: var(--text-primary);">Password *</label>
                            <div class="relative">
                                <input type="password" id="password" name="password"
                                       class="w-full p-2 border rounded @error('password') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Min 8 characters" required>
                                <button type="button" onclick="togglePasswordVisibility('password')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="password-toggle-icon"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block mb-2 font-medium" style="color: var(--text-primary);">Confirm Password *</label>
                            <div class="relative">
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       class="w-full p-2 border rounded"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Confirm password" required>
                                <button type="button" onclick="togglePasswordVisibility('password_confirmation')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="password-confirm-toggle-icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Password must be at least 8 characters and contain uppercase, lowercase, numbers, and special characters.
                    </div>
                    
                    <!-- Password Strength Indicator -->
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                            <div class="h-1.5 rounded-full transition-all duration-300" id="password-strength-bar" style="width: 0%;"></div>
                        </div>
                        <div class="flex justify-between mt-1 text-xs" style="color: var(--text-secondary);">
                            <span>Weak</span>
                            <span id="password-strength-text">Enter password</span>
                            <span>Strong</span>
                        </div>
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

            <!-- Personnel Settings -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>
                        Personnel Settings
                    </h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">Status *</label>
                        <select id="status" name="status"
                                class="w-full p-2 border rounded @error('status') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', 'pending') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="security_post_id" class="block mb-2 font-medium" style="color: var(--text-primary);">Security Post</label>
                        <select id="security_post_id" name="security_post_id"
                                class="w-full p-2 border rounded @error('security_post_id') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">Select Post</option>
                            @foreach($posts as $post)
                                <option value="{{ $post->id }}" {{ old('security_post_id') == $post->id ? 'selected' : '' }}>
                                    {{ $post->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Assign to a post you have access to
                        </div>
                        @error('security_post_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- ✅ UPDATED: SUPERVISOR ELIGIBILITY (SIMPLIFIED)               -->
            <!-- ============================================================ -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-user-tie mr-2" style="color: var(--success);"></i>
                            Supervisor Eligibility
                        </h3>
                        <span class="ml-3 px-2 py-1 text-xs rounded-full badge-secondary">Optional</span>
                    </div>
                    <button type="button" id="supervisor-toggle"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center transition-all duration-300"
                            style="background-color: {{ old('can_be_supervisor') ? 'var(--success)' : 'var(--bg-secondary)' }};
                                   color: {{ old('can_be_supervisor') ? 'white' : 'var(--text-primary)' }};
                                   border: 1px solid {{ old('can_be_supervisor') ? 'var(--success)' : 'var(--border-color)' }};">
                        <i class="fas {{ old('can_be_supervisor') ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-2"></i>
                        <span id="supervisor-toggle-text">{{ old('can_be_supervisor') ? 'Eligible' : 'Not Eligible' }}</span>
                    </button>
                </div>

                <input type="hidden" name="can_be_supervisor" id="can_be_supervisor" value="{{ old('can_be_supervisor', 0) }}">

                <div id="supervisor-fields" class="{{ old('can_be_supervisor') ? '' : 'hidden' }}">
                    <!-- Supervisor Info Alert -->
                    <div class="mb-4 p-3 rounded border" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start text-sm">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <span style="color: var(--text-secondary);">
                                    Enabling this makes the user <strong>eligible for supervisor assignments</strong>.
                                </span>
                                <ul class="text-xs mt-2 space-y-1" style="color: var(--text-secondary);">
                                    <li>• <strong>Team Lead (Level 1)</strong> and <strong>Section Lead (Level 2)</strong> roles are assigned separately</li>
                                    <li>• <strong>Post Commander (Level 3)</strong> and <strong>Area Supervisor</strong> can only be assigned by Administrators</li>
                                    <li>• You can manage supervisor assignments from the <a href="{{ route('security.supervisor-assignments.index') }}" class="text-primary hover:underline">Supervisor Assignments</a> page</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <strong>Note:</strong> Making a user eligible for supervisor does not automatically assign a supervisor role.
                                You must assign the specific role (Team Lead, Section Lead) separately.
                            </span>
                        </div>
                    </div>

                    <!-- ✅ REMOVED: supervisor_level dropdown -->
                    <!-- ✅ REMOVED: supervisor_score input -->
                    <!-- ✅ REMOVED: start_date -->
                    <!-- ✅ REMOVED: end_date -->
                    <!-- ✅ REMOVED: is_primary_supervisor checkbox -->
                </div>
            </div>
            <!-- ============================================================ -->
            <!-- END SUPERVISOR ELIGIBILITY SECTION                           -->
            <!-- ============================================================ -->

            <!-- Invitation & Notification Section -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i>
                        Invitation & Notification
                    </h3>
                    <span class="px-2 py-1 text-xs rounded-full badge-secondary">Optional</span>
                </div>

                <div class="space-y-6">
                    <!-- Invitation Toggle -->
                    <div>
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Send Invitation to User
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="modern-radio-option {{ old('send_invitation') ? 'modern-radio-option--checked' : '' }}">
                                <input type="radio" name="send_invitation" id="send_invitation_yes" value="1" 
                                       class="modern-radio-input" 
                                       {{ old('send_invitation') ? 'checked' : '' }}>
                                <label for="send_invitation_yes" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Send Invitation</div>
                                            <div class="modern-radio-description">Send secure invitation with password setup link</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="modern-radio-option {{ !old('send_invitation') ? 'modern-radio-option--checked' : '' }}">
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
                    <div id="invitation-method-container" class="{{ old('send_invitation') ? '' : 'hidden' }} space-y-6">
                        <div id="invitation-channels-section" class="{{ old('send_invitation') ? '' : 'hidden' }}">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Channels *</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="invitation-channels-options">
                                <label class="flex items-start p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option" 
                                       style="background-color: {{ in_array('sms', old('invitation_channels', [])) ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-secondary)' }}; 
                                              border-color: {{ in_array('sms', old('invitation_channels', [])) ? 'var(--primary)' : 'var(--border-color)' }};"
                                       data-channel="sms">
                                    <input type="checkbox" name="invitation_channels[]" value="sms" class="mr-2 mt-1" style="color: var(--primary);" 
                                           {{ ($smsStatus['system_ready'] ?? false) ? '' : 'disabled' }}
                                           {{ (is_array(old('invitation_channels')) && in_array('sms', old('invitation_channels'))) ? 'checked' : '' }}>
                                    <div>
                                        <div class="flex items-center">
                                            <i class="fas fa-comment-alt mr-2 text-lg" style="color: var(--info);"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">SMS</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">Send invitation via SMS</div>
                                                <div class="text-xs mt-1 {{ ($smsStatus['system_ready'] ?? false) ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ ($smsStatus['system_ready'] ?? false) ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ ($smsStatus['system_ready'] ?? false) ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-start p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option" 
                                       style="background-color: {{ in_array('whatsapp', old('invitation_channels', [])) ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-secondary)' }}; 
                                              border-color: {{ in_array('whatsapp', old('invitation_channels', [])) ? 'var(--primary)' : 'var(--border-color)' }};"
                                       data-channel="whatsapp">
                                    <input type="checkbox" name="invitation_channels[]" value="whatsapp" class="mr-2 mt-1" style="color: var(--primary);"
                                           {{ ($whatsappStatus['system_ready'] ?? false) ? '' : 'disabled' }}
                                           {{ (is_array(old('invitation_channels')) && in_array('whatsapp', old('invitation_channels'))) ? 'checked' : '' }}>
                                    <div>
                                        <div class="flex items-center">
                                            <i class="fab fa-whatsapp mr-2 text-lg" style="color: #25D366;"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">WhatsApp</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">Send via WhatsApp</div>
                                                <div class="text-xs mt-1 {{ ($whatsappStatus['system_ready'] ?? false) ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ ($whatsappStatus['system_ready'] ?? false) ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ ($whatsappStatus['system_ready'] ?? false) ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-start p-3 border rounded cursor-pointer transition-all duration-200 invitation-channel-option" 
                                       style="background-color: {{ in_array('email', old('invitation_channels', [])) ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-secondary)' }}; 
                                              border-color: {{ in_array('email', old('invitation_channels', [])) ? 'var(--primary)' : 'var(--border-color)' }};"
                                       data-channel="email">
                                    <input type="checkbox" name="invitation_channels[]" value="email" class="mr-2 mt-1" style="color: var(--primary);"
                                           {{ $emailStatus['system_ready'] ? '' : 'disabled' }}
                                           {{ (is_array(old('invitation_channels')) && in_array('email', old('invitation_channels'))) ? 'checked' : '' }}>
                                    <div>
                                        <div class="flex items-center">
                                            <i class="fas fa-envelope mr-2 text-lg" style="color: var(--primary);"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">Email</div>
                                                <div class="text-xs" style="color: var(--text-secondary);">Send invitation via email</div>
                                                <div class="text-xs mt-1 {{ $emailStatus['system_ready'] ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ $emailStatus['system_ready'] ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $emailStatus['system_ready'] ? 'Available' : 'Not Available' }}
                                                </div>
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
                            
                            @error('invitation_channels')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="invitation_type" class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Type *</label>
                                <select class="w-full p-2 border rounded @error('invitation_type') border-red-500 @enderror" 
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                        id="invitation_type" name="invitation_type">
                                    <option value="welcome" {{ old('invitation_type', 'welcome') == 'welcome' ? 'selected' : '' }}>Welcome Invitation</option>
                                    <option value="registration" {{ old('invitation_type') == 'registration' ? 'selected' : '' }}>Registration Invitation</option>
                                    <option value="account_setup" {{ old('invitation_type') == 'account_setup' ? 'selected' : '' }}>Account Setup</option>
                                    <option value="password_setup" {{ old('invitation_type') == 'password_setup' ? 'selected' : '' }}>Password Setup</option>
                                    <option value="security_orientation" {{ old('invitation_type') == 'security_orientation' ? 'selected' : '' }}>Security Orientation</option>
                                    <option value="security_training" {{ old('invitation_type') == 'security_training' ? 'selected' : '' }}>Security Training</option>
                                </select>
                                @error('invitation_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
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
                                @error('expires_in_days')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="custom_message" class="block mb-2 font-medium" style="color: var(--text-primary);">Custom Invitation Message</label>
                            <textarea class="w-full p-2 border rounded @error('custom_message') border-red-500 @enderror" 
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                      id="custom_message" name="custom_message" 
                                      rows="3" 
                                      placeholder="Add a custom welcome message to the invitation (optional)."
                                      maxlength="1000">{{ old('custom_message') }}</textarea>
                            <div class="flex justify-between items-center mt-1">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Optional custom message. Max 1000 characters.
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <span id="char-count">0</span>/1000 characters
                                </div>
                            </div>
                            @error('custom_message')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Invitation Preview -->
                        <div id="invitation-preview-container" class="rounded p-4 {{ old('send_invitation') && is_array(old('invitation_channels')) && count(old('invitation_channels')) > 0 ? '' : 'hidden' }}" 
                             style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-semibold" style="color: var(--text-primary);">Invitation Preview</h4>
                                <div class="flex items-center space-x-2">
                                    <span id="invitation-channel-badge" class="px-2 py-1 rounded text-xs font-medium" 
                                          style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                        {{ implode(' + ', array_map('strtoupper', old('invitation_channels', []))) ?: 'Select Channels' }}
                                    </span>
                                    <i class="fas fa-envelope" style="color: var(--info);"></i>
                                </div>
                            </div>
                            <div class="rounded p-3 text-sm font-mono whitespace-pre-wrap" id="invitation-preview-content" 
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                                @php
                                    $previewName = old('name', 'User');
                                    $previewEmail = old('email', 'user@example.com');
                                    $previewPhone = old('phone', '');
                                    $previewType = old('invitation_type', 'welcome');
                                    $previewMessage = old('custom_message', '');
                                    $previewChannels = old('invitation_channels', []);
                                    $previewContent = '';
                                    switch($previewType) {
                                        case 'welcome': $previewContent = "👋 Hello {$previewName}!\n\n🎉 You've been invited to join our security team!\n\nYour account has been created. Please set up your password to activate your account.\n\n"; break;
                                        case 'registration': $previewContent = "👋 Hello {$previewName}!\n\n📋 Security Team Registration\n\nYour registration is complete. Please set your password to finalize your account setup.\n\n"; break;
                                        case 'account_setup': $previewContent = "👋 Hello {$previewName}!\n\n⚙️ Security Account Setup Required\n\nPlease complete your account setup by creating your password.\n\n"; break;
                                        case 'password_setup': $previewContent = "👋 Hello {$previewName}!\n\n🔐 Security Account Password Setup\n\nPlease set your password to activate your security account.\n\n"; break;
                                        case 'security_orientation': $previewContent = "👋 Hello {$previewName}!\n\n📚 Security Orientation\n\nWelcome to the security team! Please complete your orientation by setting up your account.\n\n"; break;
                                        case 'security_training': $previewContent = "👋 Hello {$previewName}!\n\n🎓 Security Training\n\nYou've been scheduled for security training. Please set up your account to access training materials.\n\n"; break;
                                    }
                                    $previewContent .= "📧 Email: {$previewEmail}\n";
                                    if($previewPhone) $previewContent .= "📱 Phone: {$previewPhone}\n";
                                    $previewContent .= "📨 Delivery Channel(s): " . implode(', ', array_map('strtoupper', $previewChannels)) . "\n\n";
                                    if($previewMessage) $previewContent .= "💬 Message:\n{$previewMessage}\n\n";
                                    $previewContent .= "🔐 SECURE INVITATION LINK WILL BE SENT VIA SELECTED CHANNELS";
                                @endphp
                                {{ $previewContent }}
                            </div>
                            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span id="invitation-preview-note">
                                    @if(in_array('sms', old('invitation_channels', [])) || in_array('whatsapp', old('invitation_channels', [])))
                                        SMS/WhatsApp invitations will automatically fallback to email if delivery fails.
                                    @else
                                        This invitation will be sent via the selected channels.
                                    @endif
                                </span>
                            </div>
                            
                            <div id="fallback-delivery-info" class="mt-3 p-2 rounded border {{ (in_array('sms', old('invitation_channels', [])) || in_array('whatsapp', old('invitation_channels', []))) && old('email') && $emailStatus['system_ready'] ? '' : 'hidden' }}" 
                                 style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-redo-alt mr-2" style="color: var(--info);"></i>
                                    <span style="color: var(--text-primary);">System will automatically fallback to email if SMS/WhatsApp channels fail</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
                            <h4 class="font-semibold mb-2 text-sm" style="color: var(--text-primary);">Benefits of Token-Based Invitations:</h4>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
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
                                <li class="flex items-center">
                                    <i class="fas fa-phone mr-2 text-xs" style="color: var(--info);"></i>
                                    <strong>Multi-Channel Delivery:</strong> Reach users via their preferred channels
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fallback Information Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-redo-alt mr-2" style="color: var(--info);"></i>
                        Fallback & Retry System
                    </h3>
                </div>
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
            </div>

            <!-- Form Actions -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-asterisk text-red-500 mr-1"></i> Required fields
                        <span class="mx-2">•</span>
                        <span id="form-status" class="text-xs"></span>
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-success px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Create Personnel
                        </button>
                        <a href="{{ route('security.personnel.index') }}" class="btn-secondary px-6 py-2.5 rounded-lg">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ========== STYLES ========== -->
<style>
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.hidden { display: none !important; }

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
.modern-radio-option:hover {
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
    padding: 16px 20px;
    position: relative;
}
.modern-radio-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 14px;
    font-size: 18px;
    transition: all 0.3s ease;
    background: var(--bg-tertiary);
}
.modern-radio-option--checked .modern-radio-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
    color: white !important;
    transform: scale(1.1);
}
.modern-radio-text {
    flex: 1;
}
.modern-radio-title {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
    margin-bottom: 2px;
}
.modern-radio-description {
    font-size: 12px;
    color: var(--text-secondary);
    line-height: 1.4;
}
.modern-radio-check {
    width: 22px;
    height: 22px;
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
    font-size: 11px;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.modern-radio-option--checked .modern-radio-check i {
    opacity: 1 !important;
}

/* Invitation channel styling */
.invitation-channel-option {
    transition: all 0.3s ease;
    cursor: pointer;
}
.invitation-channel-option:hover:not([style*="opacity: 0.5"]) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}
.channel-selected {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}
.text-green-600 { color: #16a34a; }
.text-red-600 { color: #dc2626; }
.text-yellow-600 { color: #ca8a04; }
.text-blue-600 { color: #2563eb; }

/* Invitation preview */
#invitation-preview-content {
    font-family: 'Courier New', monospace;
    line-height: 1.4;
    max-height: 180px;
    overflow-y: auto;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

/* Supervisor toggle animations */
#supervisor-toggle {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
#supervisor-toggle:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
#supervisor-toggle:active {
    transform: translateY(0);
}

#supervisor-fields {
    transition: all 0.3s ease;
}

/* Password strength bar */
#password-strength-bar {
    transition: width 0.3s ease;
}
</style>

<!-- ========== JAVASCRIPT ========== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Security Personnel Create Form Initialized');

    // Service status from backend
    const smsSystemReady = {{ ($smsStatus['system_ready'] ?? false) ? 'true' : 'false' }};
    const whatsappSystemReady = {{ ($whatsappStatus['system_ready'] ?? false) ? 'true' : 'false' }};
    const emailSystemReady = {{ $emailStatus['system_ready'] ? 'true' : 'false' }};

    // DOM Elements
    const sendInvitationYes = document.getElementById('send_invitation_yes');
    const sendInvitationNo = document.getElementById('send_invitation_no');
    const invitationMethodContainer = document.getElementById('invitation-method-container');
    const invitationChannelsSection = document.getElementById('invitation-channels-section');
    const passwordSection = document.getElementById('password-section');
    const passwordNotice = document.getElementById('password-notice');
    const passwordInput = document.getElementById('password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const statusSelect = document.getElementById('status');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const supervisorToggle = document.getElementById('supervisor-toggle');
    const canBeSupervisor = document.getElementById('can_be_supervisor');
    const supervisorFields = document.getElementById('supervisor-fields');
    const supervisorToggleText = document.getElementById('supervisor-toggle-text');
    const formStatus = document.getElementById('form-status');
    const customMessage = document.getElementById('custom_message');
    const charCount = document.getElementById('char-count');
    const invitationPreviewContainer = document.getElementById('invitation-preview-container');
    const invitationPreviewContent = document.getElementById('invitation-preview-content');
    const invitationChannelBadge = document.getElementById('invitation-channel-badge');
    const invitationPreviewNote = document.getElementById('invitation-preview-note');
    const fallbackDeliveryInfo = document.getElementById('fallback-delivery-info');

    // ==================== PASSWORD STRENGTH ====================
    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/\d/)) strength++;
        if (password.match(/[^a-zA-Z\d]/)) strength++;
        return strength;
    }

    function updatePasswordStrength() {
        const password = passwordInput ? passwordInput.value : '';
        const strength = checkPasswordStrength(password);
        const bar = document.getElementById('password-strength-bar');
        const text = document.getElementById('password-strength-text');
        
        if (!bar || !text) return;
        
        const percentages = [0, 25, 50, 75, 100];
        const colors = ['#dc2626', '#f59e0b', '#f59e0b', '#22c55e', '#22c55e'];
        const labels = ['Enter password', 'Weak', 'Fair', 'Good', 'Strong'];
        
        bar.style.width = percentages[strength] + '%';
        bar.style.backgroundColor = colors[strength];
        text.textContent = labels[strength];
        text.style.color = colors[strength];
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', updatePasswordStrength);
    }

    // ==================== TOGGLE PASSWORD VISIBILITY ====================
    window.togglePasswordVisibility = function(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '-toggle-icon') || 
                     document.querySelector(`#${fieldId} + button i`);
        if (field && icon) {
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    };

    // ==================== SUPERVISOR TOGGLE (SIMPLIFIED) ====================
    if (supervisorToggle) {
        supervisorToggle.addEventListener('click', function() {
            const isEnabled = canBeSupervisor.value == '1';
            const newValue = isEnabled ? '0' : '1';
            
            canBeSupervisor.value = newValue;
            
            if (newValue == '1') {
                supervisorFields.classList.remove('hidden');
                supervisorToggle.style.backgroundColor = 'var(--success)';
                supervisorToggle.style.color = 'white';
                supervisorToggle.style.borderColor = 'var(--success)';
                supervisorToggle.querySelector('i').className = 'fas fa-toggle-on mr-2';
                supervisorToggleText.textContent = 'Eligible';
            } else {
                supervisorFields.classList.add('hidden');
                supervisorToggle.style.backgroundColor = 'var(--bg-secondary)';
                supervisorToggle.style.color = 'var(--text-primary)';
                supervisorToggle.style.borderColor = 'var(--border-color)';
                supervisorToggle.querySelector('i').className = 'fas fa-toggle-off mr-2';
                supervisorToggleText.textContent = 'Not Eligible';
            }
        });
    }

    // ==================== INVITATION SYSTEM ====================
    if (sendInvitationYes && sendInvitationNo) {
        sendInvitationYes.addEventListener('change', toggleInvitationOptions);
        sendInvitationNo.addEventListener('change', toggleInvitationOptions);
        toggleInvitationOptions();
    }

    function toggleInvitationOptions() {
        const isSending = sendInvitationYes.checked;
        
        if (isSending) {
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
        updateFormStatus();
    }

    function updateRadioVisualState(radio) {
        const option = radio.closest('.modern-radio-option');
        if (option) {
            if (radio.checked) option.classList.add('modern-radio-option--checked');
            else option.classList.remove('modern-radio-option--checked');
        }
    }

    // ==================== INVITATION CHANNELS ====================
    const channelCheckboxes = document.querySelectorAll('input[name="invitation_channels[]"]');
    channelCheckboxes.forEach(checkbox => {
        updateChannelVisualState(checkbox);
        checkbox.addEventListener('change', function() {
            updateChannelVisualState(this);
            updateInvitationPreview();
            updateFallbackSystem();
            updateFormStatus();
        });
    });

    document.querySelectorAll('.invitation-channel-option').forEach(option => {
        option.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox' || this.style.opacity === '0.5') return;
            const checkbox = this.querySelector('input[type="checkbox"]');
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    function updateChannelVisualState(checkbox) {
        const option = checkbox.closest('.invitation-channel-option');
        if (!option) return;
        if (checkbox.checked) {
            option.style.borderColor = 'var(--primary)';
            option.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            option.classList.add('channel-selected');
        } else {
            const isDisabled = option.style.opacity === '0.5';
            option.style.borderColor = 'var(--border-color)';
            option.style.backgroundColor = isDisabled ? 'rgba(var(--secondary-rgb), 0.1)' : 'var(--bg-secondary)';
            option.classList.remove('channel-selected');
        }
    }

    function getSelectedChannels() {
        return Array.from(document.querySelectorAll('input[name="invitation_channels[]"]:checked')).map(cb => cb.value);
    }

    // ==================== INVITATION PREVIEW ====================
    const nameInput = document.getElementById('name');
    const invitationTypeSelect = document.getElementById('invitation_type');
    
    if (nameInput) nameInput.addEventListener('input', updateInvitationPreview);
    if (emailInput) emailInput.addEventListener('input', function() { updateInvitationPreview(); updateFallbackSystem(); });
    if (phoneInput) phoneInput.addEventListener('input', function() { updateInvitationPreview(); updateFallbackSystem(); });
    if (invitationTypeSelect) invitationTypeSelect.addEventListener('change', updateInvitationPreview);
    if (customMessage) {
        customMessage.addEventListener('input', function() {
            updateCharCount();
            updateInvitationPreview();
        });
    }

    function updateCharCount() {
        if (customMessage && charCount) {
            const length = customMessage.value.length;
            charCount.textContent = length;
            charCount.style.color = length > 900 ? 'var(--warning)' : length > 950 ? 'var(--danger)' : 'var(--text-secondary)';
        }
    }

    function updateInvitationPreview() {
        if (!sendInvitationYes.checked) {
            if (invitationPreviewContainer) invitationPreviewContainer.classList.add('hidden');
            return;
        }

        const selectedChannels = getSelectedChannels();
        if (selectedChannels.length === 0) {
            if (invitationPreviewContainer) invitationPreviewContainer.classList.add('hidden');
            return;
        }

        const name = nameInput?.value || 'User';
        const email = emailInput?.value || 'user@example.com';
        const phone = phoneInput?.value || '';
        const invitationType = invitationTypeSelect?.value || 'welcome';
        const customMsg = customMessage?.value || '';
        
        let invitationContent = '';
        switch (invitationType) {
            case 'welcome':
                invitationContent = `👋 Hello ${name}!\n\n🎉 You've been invited to join our security team!\n\nYour account has been created. Please set up your password to activate your account.\n\n`;
                break;
            case 'registration':
                invitationContent = `👋 Hello ${name}!\n\n📋 Security Team Registration\n\nYour registration is complete. Please set your password to finalize your account setup.\n\n`;
                break;
            case 'account_setup':
                invitationContent = `👋 Hello ${name}!\n\n⚙️ Security Account Setup Required\n\nPlease complete your account setup by creating your password.\n\n`;
                break;
            case 'password_setup':
                invitationContent = `👋 Hello ${name}!\n\n🔐 Security Account Password Setup\n\nPlease set your password to activate your security account.\n\n`;
                break;
            case 'security_orientation':
                invitationContent = `👋 Hello ${name}!\n\n📚 Security Orientation\n\nWelcome to the security team! Please complete your orientation by setting up your account.\n\n`;
                break;
            case 'security_training':
                invitationContent = `👋 Hello ${name}!\n\n🎓 Security Training\n\nYou've been scheduled for security training. Please set up your account to access training materials.\n\n`;
                break;
        }
        
        invitationContent += `📧 Email: ${email}\n`;
        if (phone) invitationContent += `📱 Phone: ${phone}\n`;
        invitationContent += `📨 Delivery Channel(s): ${selectedChannels.map(c => c.toUpperCase()).join(', ')}\n\n`;
        if (customMsg) invitationContent += `💬 Message:\n${customMsg}\n\n`;
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

    // ==================== FALLBACK SYSTEM ====================
    function updateFallbackSystem() {
        const userEmail = emailInput?.value.trim() || '';
        const userPhone = phoneInput?.value.trim() || '';
        const selectedChannels = getSelectedChannels();
        const smsOrWhatsappSelected = selectedChannels.some(c => ['sms', 'whatsapp'].includes(c));
        
        if (fallbackDeliveryInfo) {
            if (smsOrWhatsappSelected && userEmail && emailSystemReady) {
                fallbackDeliveryInfo.classList.remove('hidden');
            } else {
                fallbackDeliveryInfo.classList.add('hidden');
            }
        }
    }

    // ==================== FORM STATUS ====================
    function updateFormStatus() {
        if (!formStatus) return;
        
        const selectedChannels = getSelectedChannels();
        const userEmail = emailInput?.value.trim() || '';
        const isEmailValid = userEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userEmail);
        
        let statusMessage = '';
        if (sendInvitationYes.checked) {
            if (selectedChannels.length > 0) {
                statusMessage = `Invitation will be sent via ${selectedChannels.map(c => c.toUpperCase()).join(', ')}.`;
                if (selectedChannels.some(c => ['sms', 'whatsapp'].includes(c)) && isEmailValid && emailSystemReady) {
                    statusMessage += ' ┄ Fallback to email if needed.';
                }
            } else {
                statusMessage = '⚠️ Select at least one invitation channel.';
            }
        } else {
            statusMessage = 'User will be created without invitation.';
        }
        
        formStatus.textContent = statusMessage;
        formStatus.style.color = selectedChannels.length > 0 ? 'var(--text-secondary)' : 'var(--warning)';
    }

    // ==================== EMAIL/PHONE VALIDATION ====================
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
            const validIcon = document.getElementById('email-valid-icon');
            const invalidIcon = document.getElementById('email-invalid-icon');
            if (validIcon && invalidIcon) {
                if (isValid) { validIcon.classList.remove('hidden'); invalidIcon.classList.add('hidden'); }
                else if (this.value.length > 0) { validIcon.classList.add('hidden'); invalidIcon.classList.remove('hidden'); }
                else { validIcon.classList.add('hidden'); invalidIcon.classList.add('hidden'); }
            }
            updateFallbackSystem();
            updateFormStatus();
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            const isValid = this.value.length >= 10;
            const validIcon = document.getElementById('phone-valid-icon');
            const invalidIcon = document.getElementById('phone-invalid-icon');
            if (validIcon && invalidIcon) {
                if (isValid) { validIcon.classList.remove('hidden'); invalidIcon.classList.add('hidden'); }
                else if (this.value.length > 0) { validIcon.classList.add('hidden'); invalidIcon.classList.remove('hidden'); }
                else { validIcon.classList.add('hidden'); invalidIcon.classList.add('hidden'); }
            }
            updateFallbackSystem();
        });
    }

    // ==================== FORM VALIDATION ====================
    function validateForm() {
        if (sendInvitationYes.checked) {
            const selectedChannels = getSelectedChannels();
            const userPhone = phoneInput?.value.trim() || '';
            const userEmail = emailInput?.value.trim() || '';
            const isEmailValid = userEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userEmail);
            
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
            
            if ((selectedChannels.includes('sms') && !smsSystemReady) || 
                (selectedChannels.includes('whatsapp') && !whatsappSystemReady)) {
                if (!isEmailValid || !emailSystemReady) {
                    alert('Selected channels are unavailable and no email fallback is available. Please provide a valid email address or select available channels.');
                    return false;
                }
                if (!confirm('Some selected channels are unavailable. The invitation will be sent via email only. Continue?')) {
                    return false;
                }
            }
        }
        return true;
    }

    const form = document.getElementById('personnel-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }

    // ==================== INITIALIZATION ====================
    updateCharCount();
    updateInvitationPreview();
    updateFallbackSystem();
    updateFormStatus();
    updatePasswordStrength();

    console.log('Security Personnel Create Form initialization complete');
});
</script>
@endsection