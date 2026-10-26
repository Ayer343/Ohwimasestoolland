@extends('layouts.secu')

@section('title', 'Submit Security Personnel')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
    ============================================================ --}}
    <div class="card">
        <div class="flex flex-wrap justify-between items-center gap-4 p-6">
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
                        Submit New Security Personnel
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span class="inline-flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Submission requires administrator approval before activation
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-xs"
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Head of Security
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Communication Service Status --}}
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center px-3 py-1 rounded-full text-xs font-medium"
                         title="WhatsApp service status"
                         style="background-color: {{ ($whatsappStatus['system_ready'] ?? false) ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }};
                                color: {{ ($whatsappStatus['system_ready'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fab fa-whatsapp mr-1"></i>
                        {{ ($whatsappStatus['system_ready'] ?? false) ? 'WhatsApp Ready' : 'WhatsApp Limited' }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-xs font-medium"
                         title="SMS service status"
                         style="background-color: {{ ($smsStatus['system_ready'] ?? false) ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }};">
                        <i class="fas fa-comment-alt mr-1"></i>
                        {{ ($smsStatus['system_ready'] ?? false) ? 'SMS Ready' : 'SMS Limited' }}
                    </div>
                    <div class="flex items-center px-3 py-1 rounded-full text-xs font-medium"
                         title="Email service status"
                         style="background-color: {{ ($emailStatus['system_ready'] ?? false) ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }};
                                color: {{ ($emailStatus['system_ready'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                        <i class="fas {{ ($emailStatus['system_ready'] ?? false) ? 'fa-envelope' : 'fa-envelope-open' }} mr-1"></i>
                        {{ ($emailStatus['system_ready'] ?? false) ? 'Email Ready' : 'Email Limited' }}
                    </div>
                </div>

                <a href="{{ route('security.personnel.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Personnel
                </a>
            </div>
        </div>
    </div>

    {{-- FLASH: SUCCESS --}}
    @if(session('success'))
        <div class="card p-4 border-l-4"
             style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
            <div class="flex items-center flex-wrap gap-2">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
                <a href="{{ route('security.personnel.index', ['tab' => 'pending']) }}"
                   class="ml-2 text-sm px-3 py-1 rounded"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-clock mr-1"></i> View Pending Submissions
                </a>
            </div>
        </div>
    @endif

    {{-- FLASH: ERROR --}}
    @if(session('error'))
        <div class="card p-4 border-l-4"
             style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- VALIDATION ERRORS --}}
    @if($errors->any())
        <div class="card p-4 border-l-4"
             style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
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

    {{-- APPROVAL WORKFLOW BANNER --}}
    <div class="card p-4"
         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-lg mr-3 mt-0.5" style="color: var(--info);"></i>
            <div>
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">What happens when you submit?</h4>
                <ul class="text-xs mt-2 space-y-1" style="color: var(--text-secondary);">
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        The officer is created in <strong>pending</strong> status with an <em>awaiting approval</em> flag.
                    </li>
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        An administrator reviews the submission.
                    </li>
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        On approval, the officer becomes active. Only then can you send an invitation.
                    </li>
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        If you tick <strong>"Queue invitation"</strong> below, the invitation is dispatched automatically the moment admin approval lands.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FORM
    ============================================================ --}}
    <form action="{{ route('security.personnel.store') }}" method="POST" id="personnel-form" novalidate>
        @csrf
        <div class="grid grid-cols-1 gap-6">

            {{-- PERSONAL INFORMATION --}}
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-id-card mr-2" style="color: var(--primary);"></i>
                        Personal Information
                    </h3>
                    <span class="px-2 py-1 text-xs rounded-full badge-info">Required</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Name --}}
                    <div>
                        <label for="name" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="w-full p-2 border rounded @error('name') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Enter full name" required>
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email (OPTIONAL) --}}
                    <div>
                        <label for="email" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Email Address
                        </label>
                        <div class="relative">
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="w-full p-2 border rounded pr-10 @error('email') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Optional email address">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="email-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="email-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Optional. Required only if you plan to queue an <strong>Email</strong> invitation.
                        </div>
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="phone" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                                   class="w-full p-2 border rounded pr-10 @error('phone') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="+233XXXXXXXXX or 0XXXXXXXXX" required>
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <i class="fas fa-check text-green-500 hidden" id="phone-valid-icon"></i>
                                <i class="fas fa-times text-red-500 hidden" id="phone-invalid-icon"></i>
                            </div>
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Format: +233XXXXXXXXX or 0XXXXXXXXX (auto-standardized server-side)
                        </div>
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Username --}}
                    <div>
                        <label for="username" class="block mb-2 font-medium" style="color: var(--text-primary);">Username</label>
                        <input type="text" id="username" name="username" value="{{ old('username') }}"
                               class="w-full p-2 border rounded @error('username') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Optional username">
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Leave blank to auto-generate a unique username
                        </div>
                        @error('username')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- QUEUE INVITATION (optional) --}}
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i>
                        Invitation
                    </h3>
                    <span class="px-2 py-1 text-xs rounded-full badge-secondary">Optional</span>
                </div>

                <div class="space-y-4">
                    <div class="p-3 rounded"
                         style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 3px solid var(--warning);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                            <strong>Invitations are only sent after admin approval.</strong>
                            Ticking the box below <em>queues</em> the invitation — nothing is sent now.
                            It is dispatched automatically the moment admin approves the submission.
                        </p>
                    </div>

                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" id="queue_invitation" name="queue_invitation" value="1"
                               class="w-4 h-4 mt-1 border-gray-300 rounded focus:ring-blue-500"
                               style="color: var(--primary);"
                               {{ old('queue_invitation') ? 'checked' : '' }}>
                        <div>
                            <span class="font-medium" style="color: var(--text-primary);">Queue an invitation</span>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Sent automatically once an administrator approves the submission.
                            </p>
                        </div>
                    </label>

                    <div id="invitation-method-container"
                         class="{{ old('queue_invitation') ? '' : 'hidden' }} space-y-6">

                        {{-- Channels --}}
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Invitation Channels <span class="text-red-500">*</span>
                            </label>
                            @php
                                $oldChannels = (array) old('invitation_channels', []);
                            @endphp
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="invitation-channels-options">

                                {{-- SMS --}}
                                @php $smsReady = (bool) ($smsStatus['system_ready'] ?? false); @endphp
                                <label class="invitation-channel-option flex items-start p-3 border rounded cursor-pointer transition-all duration-200 {{ $smsReady ? '' : 'channel-disabled' }}"
                                       style="background-color: {{ $smsReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }};
                                              border-color: {{ in_array('sms', $oldChannels, true) ? 'var(--primary)' : 'var(--border-color)' }};"
                                       data-channel="sms">
                                    <input type="checkbox" name="invitation_channels[]" value="sms" class="mr-2 mt-1"
                                           style="color: var(--primary);"
                                           {{ $smsReady ? '' : 'disabled' }}
                                           {{ in_array('sms', $oldChannels, true) ? 'checked' : '' }}>
                                    <div>
                                        <div class="flex items-center">
                                            <i class="fas fa-comment-alt mr-2 text-lg" style="color: var(--info);"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">SMS</div>
                                                <div class="text-xs mt-1 {{ $smsReady ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ $smsReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $smsReady ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- WhatsApp --}}
                                @php $waReady = (bool) ($whatsappStatus['system_ready'] ?? false); @endphp
                                <label class="invitation-channel-option flex items-start p-3 border rounded cursor-pointer transition-all duration-200 {{ $waReady ? '' : 'channel-disabled' }}"
                                       style="background-color: {{ $waReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }};
                                              border-color: {{ in_array('whatsapp', $oldChannels, true) ? 'var(--primary)' : 'var(--border-color)' }};"
                                       data-channel="whatsapp">
                                    <input type="checkbox" name="invitation_channels[]" value="whatsapp" class="mr-2 mt-1"
                                           style="color: var(--primary);"
                                           {{ $waReady ? '' : 'disabled' }}
                                           {{ in_array('whatsapp', $oldChannels, true) ? 'checked' : '' }}>
                                    <div>
                                        <div class="flex items-center">
                                            <i class="fab fa-whatsapp mr-2 text-lg" style="color: #25D366;"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">WhatsApp</div>
                                                <div class="text-xs mt-1 {{ $waReady ? 'text-green-600' : 'text-red-600' }}">
                                                    <i class="fas {{ $waReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $waReady ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- Email --}}
                                @php $emailReady = (bool) ($emailStatus['system_ready'] ?? false); @endphp
                                <label class="invitation-channel-option flex items-start p-3 border rounded cursor-pointer transition-all duration-200 {{ $emailReady ? '' : 'channel-disabled' }}"
                                       style="background-color: {{ $emailReady ? 'var(--bg-secondary)' : 'rgba(var(--secondary-rgb), 0.1)' }};
                                              border-color: {{ in_array('email', $oldChannels, true) ? 'var(--primary)' : 'var(--border-color)' }};"
                                       data-channel="email">
                                    <input type="checkbox" name="invitation_channels[]" value="email" class="mr-2 mt-1"
                                           style="color: var(--primary);"
                                           {{ $emailReady ? '' : 'disabled' }}
                                           {{ in_array('email', $oldChannels, true) ? 'checked' : '' }}>
                                    <div>
                                        <div class="flex items-center">
                                            <i class="fas fa-envelope mr-2 text-lg" style="color: var(--primary);"></i>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">Email</div>
                                                <div class="text-xs mt-1 {{ $emailReady ? 'text-green-600' : 'text-red-600' }}"
                                                     id="email-channel-hint">
                                                    <i class="fas {{ $emailReady ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                    {{ $emailReady ? 'Available' : 'Not Available' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <p id="email-required-hint"
                               class="text-xs mt-2 hidden"
                               style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                You selected <strong>Email</strong>, but no email address is provided. Add one below or deselect Email.
                            </p>

                            @error('invitation_channels')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            @error('invitation_channels.*')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Type + Expiry --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="invitation_type" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Invitation Type <span class="text-red-500">*</span>
                                </label>
                                <select class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="invitation_type" name="invitation_type">
                                    @php
                                        $oldType = old('invitation_type', 'security_welcome');
                                        $types = [
                                            'security_welcome'     => 'Security Welcome',
                                            'security_orientation' => 'Security Orientation',
                                            'security_training'    => 'Security Training',
                                            'password_setup'       => 'Password Setup',
                                            'welcome'              => 'Welcome',
                                            'registration'         => 'Registration',
                                            'account_setup'        => 'Account Setup',
                                        ];
                                    @endphp
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}" {{ $oldType === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('invitation_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expires_in_days" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Invitation Expiration
                                </label>
                                <select class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="expires_in_days" name="expires_in_days">
                                    @php $oldExpiry = old('expires_in_days', '7'); @endphp
                                    @foreach([1 => '1 Day', 3 => '3 Days', 7 => '7 Days (Default)', 14 => '14 Days', 30 => '30 Days'] as $value => $label)
                                        <option value="{{ $value }}" {{ (string) $oldExpiry === (string) $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('expires_in_days')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Custom message --}}
                        <div>
                            <label for="invitation_message" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Custom Message
                            </label>
                            <textarea class="w-full p-2 border rounded"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="invitation_message" name="invitation_message"
                                      rows="3"
                                      placeholder="Optional custom message for the invitation"
                                      maxlength="1000">{{ old('invitation_message') }}</textarea>
                            <div class="flex justify-between items-center mt-1">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Optional. Max 1000 characters.
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <span id="char-count">0</span>/1000
                                </div>
                            </div>
                            @error('invitation_message')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="p-3 rounded"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-shield-alt mr-1" style="color: var(--success);"></i>
                                Users set their own password via a secure link. Tokens expire automatically.
                                SMS/WhatsApp failures fall back to email when available.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FORM ACTIONS --}}
            <div class="card p-6">
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-asterisk text-red-500 mr-1"></i> Required
                        <span class="mx-2">•</span>
                        <span id="form-status" class="text-xs"></span>
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit"
                                class="btn-success px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center"
                                id="submit-button">
                            <i class="fas fa-paper-plane mr-2"></i> Submit for Approval
                        </button>
                        <a href="{{ route('security.personnel.index') }}"
                           class="btn-secondary px-6 py-2.5 rounded-lg inline-flex items-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- STYLES (unchanged) --}}
<style>
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-success:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3); }
.btn-success:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-secondary:hover { background-color: var(--border-color); }
.badge-success   { background-color: rgba(var(--success-rgb),   0.1); color: var(--success);   border: 1px solid rgba(var(--success-rgb),   0.3); }
.badge-warning   { background-color: rgba(var(--warning-rgb),   0.1); color: var(--warning);   border: 1px solid rgba(var(--warning-rgb),   0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.badge-info      { background-color: rgba(var(--info-rgb),      0.1); color: var(--info);      border: 1px solid rgba(var(--info-rgb),      0.3); }
.hidden { display: none !important; }

.invitation-channel-option { transition: all 0.3s ease; cursor: pointer; }
.invitation-channel-option:hover:not(.channel-disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: var(--primary);
}
.invitation-channel-option.channel-disabled {
    cursor: not-allowed !important;
    opacity: 0.55;
    border-style: dashed !important;
}
.channel-selected {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}
.text-green-600 { color: #16a34a; }
.text-red-600   { color: #dc2626; }
</style>

{{-- JAVASCRIPT --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- server-provided flags ----
    const smsSystemReady      = {{ ($smsStatus['system_ready'] ?? false) ? 'true' : 'false' }};
    const whatsappSystemReady = {{ ($whatsappStatus['system_ready'] ?? false) ? 'true' : 'false' }};
    const emailSystemReady    = {{ ($emailStatus['system_ready'] ?? false) ? 'true' : 'false' }};

    const queueInvitationCheckbox   = document.getElementById('queue_invitation');
    const invitationMethodContainer = document.getElementById('invitation-method-container');
    const emailInput                = document.getElementById('email');
    const phoneInput                = document.getElementById('phone');
    const customMessage             = document.getElementById('invitation_message');
    const charCount                 = document.getElementById('char-count');
    const formStatus                = document.getElementById('form-status');
    const personnelForm             = document.getElementById('personnel-form');
    const submitButton              = document.getElementById('submit-button');
    const emailRequiredHint         = document.getElementById('email-required-hint');

    // ================================================================
    // Queue Invitation toggle
    // ================================================================
    function toggleInvitationSection() {
        if (!queueInvitationCheckbox || !invitationMethodContainer) return;
        invitationMethodContainer.classList.toggle('hidden', !queueInvitationCheckbox.checked);
        updateFormStatus();
    }
    if (queueInvitationCheckbox) {
        queueInvitationCheckbox.addEventListener('change', toggleInvitationSection);
    }

    // ================================================================
    // Channel visual state
    // ================================================================
    document.querySelectorAll('input[name="invitation_channels[]"]').forEach(cb => {
        updateChannelVisual(cb);
        cb.addEventListener('change', function () {
            updateChannelVisual(this);
            updateFormStatus();
        });
    });

    document.querySelectorAll('.invitation-channel-option').forEach(option => {
        option.addEventListener('click', function (e) {
            if (this.classList.contains('channel-disabled')) return;
            if (e.target.type === 'checkbox') return;
            const cb = this.querySelector('input[type="checkbox"]');
            if (cb && !cb.disabled) {
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    function updateChannelVisual(checkbox) {
        const option = checkbox.closest('.invitation-channel-option');
        if (!option) return;
        if (checkbox.checked) {
            option.classList.add('channel-selected');
            option.style.borderColor = 'var(--primary)';
            option.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        } else {
            option.classList.remove('channel-selected');
            const disabled = option.classList.contains('channel-disabled');
            option.style.borderColor = 'var(--border-color)';
            option.style.backgroundColor = disabled ? 'rgba(var(--secondary-rgb), 0.1)' : 'var(--bg-secondary)';
        }
    }

    function getSelectedChannels() {
        return Array.from(
            document.querySelectorAll('input[name="invitation_channels[]"]:checked')
        ).filter(cb => !cb.disabled).map(cb => cb.value);
    }

    // ================================================================
    // Email / phone validation icons
    // ================================================================
    function isValidEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((v || '').trim());
    }

    if (emailInput) {
        emailInput.addEventListener('input', function () {
            const hasValue = this.value.length > 0;
            const valid = isValidEmail(this.value);
            const ok = document.getElementById('email-valid-icon');
            const no = document.getElementById('email-invalid-icon');
            if (ok && no) {
                // Only show icons when the user actually typed something — empty is valid now.
                ok.classList.toggle('hidden', !(hasValue && valid));
                no.classList.toggle('hidden', !(hasValue && !valid));
            }
            updateFormStatus();
        });
    }
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            const isValid = this.value.trim().length >= 10;
            const ok = document.getElementById('phone-valid-icon');
            const no = document.getElementById('phone-invalid-icon');
            if (ok && no) {
                ok.classList.toggle('hidden', !(this.value.length > 0 && isValid));
                no.classList.toggle('hidden', !(this.value.length > 0 && !isValid));
            }
            updateFormStatus();
        });
    }

    // ================================================================
    // Character count
    // ================================================================
    function updateCharCount() {
        if (customMessage && charCount) {
            const length = customMessage.value.length;
            charCount.textContent = length;
            charCount.style.color = length > 950 ? 'var(--danger)'
                : length > 900 ? 'var(--warning)'
                : 'var(--text-secondary)';
        }
    }
    if (customMessage) customMessage.addEventListener('input', updateCharCount);
    updateCharCount();

    // ================================================================
    // Form status feedback
    // ================================================================
    function updateFormStatus() {
        if (!formStatus) return;

        if (!queueInvitationCheckbox || !queueInvitationCheckbox.checked) {
            formStatus.textContent = 'Submission will be sent to admin for approval. No invitation is sent until then.';
            formStatus.style.color = 'var(--text-secondary)';
            showEmailHint(false);
            return;
        }

        const channels = getSelectedChannels();
        if (channels.length === 0) {
            formStatus.textContent = '⚠️ Select at least one invitation channel to queue the invitation.';
            formStatus.style.color = 'var(--warning)';
            showEmailHint(false);
            return;
        }

        const emailSelected = channels.includes('email');
        const hasEmail = (emailInput?.value || '').trim() !== '';

        showEmailHint(emailSelected && !hasEmail);

        if (emailSelected && !hasEmail) {
            formStatus.textContent = '⚠️ Email channel selected but no email address provided. Add one or deselect Email.';
            formStatus.style.color = 'var(--warning)';
        } else {
            formStatus.textContent =
                `Invitation will be dispatched automatically via ${channels.map(c => c.toUpperCase()).join(', ')} on admin approval.`;
            formStatus.style.color = 'var(--text-secondary)';
        }
    }

    function showEmailHint(show) {
        if (!emailRequiredHint) return;
        emailRequiredHint.classList.toggle('hidden', !show);
    }

    // ================================================================
    // Client-side validation — mirrors server rules
    //   Required: name, phone (unchanged)
    //   Optional: email — but REQUIRED IF the Email channel is selected
    //             while queue_invitation is ticked.
    // ================================================================
    function validateForm() {
        if (!queueInvitationCheckbox || !queueInvitationCheckbox.checked) {
            // Email optional when not queueing — but if provided, it must still be valid.
            const email = (emailInput?.value || '').trim();
            if (email !== '' && !isValidEmail(email)) {
                alert('The email address you entered is not valid.');
                emailInput.focus();
                return false;
            }
            return true;
        }

        const channels = getSelectedChannels();
        if (channels.length === 0) {
            alert('Please select at least one invitation channel, or untick "Queue an invitation".');
            return false;
        }

        const email = (emailInput?.value || '').trim();
        const phone = (phoneInput?.value || '').trim();

        // If email is provided, it must be valid (even if the Email channel isn't selected).
        if (email !== '' && !isValidEmail(email)) {
            alert('The email address you entered is not valid.');
            emailInput.focus();
            return false;
        }

        // Email channel requires a valid email.
        if (channels.includes('email') && email === '') {
            alert('An email address is required to queue an Email invitation. Add one, or deselect Email.');
            emailInput.focus();
            return false;
        }

        // SMS / WhatsApp require a phone.
        if ((channels.includes('sms') || channels.includes('whatsapp')) && !phone) {
            alert('A phone number is required to queue SMS/WhatsApp invitations.');
            phoneInput.focus();
            return false;
        }

        return true;
    }

    if (personnelForm) {
        personnelForm.addEventListener('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
            }
        });
    }

    // ================================================================
    // Initialise
    // ================================================================
    toggleInvitationSection();
    updateFormStatus();
});
</script>
@endsection