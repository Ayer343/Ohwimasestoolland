{{-- developer/super-admins/change-password.blade.php --}}
@php
    $layout = 'layouts.dev';
    $routePrefix = 'developer.super-admins';
    $pageTitle = 'Change Password - Developer Portal';
    
    $user = $user ?? null;
    if (!$user) {
        abort(404, 'Super Admin not found');
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="max-w-md mx-auto">
    <div class="card">
        <div class="p-6">
            <div class="flex items-center mb-6">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-key text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--primary);"></i> 
                        Change Password
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Super Admin: {{ $user->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1"></i>
                        <span>{{ $user->email }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t pt-6" style="border-color: var(--border-color);">
                <form method="POST" action="{{ route('developer.super-admins.change-password.store', $user->id) }}" id="changePasswordForm">
                    @csrf
                    
                    <div class="space-y-6">
                        <!-- Password Requirements -->
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <h4 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-shield-alt mr-2" style="color: var(--info);"></i> 
                                Password Requirements
                            </h4>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>Minimum 8 characters</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>At least one uppercase letter</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>At least one lowercase letter</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>At least one number</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>At least one special character</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- New Password -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <span class="text-red-500">*</span> New Password
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="new_password" 
                                       id="newPassword"
                                       class="index-custom-input w-full @error('new_password') border-red-500 @enderror"
                                       placeholder="Enter new password"
                                       required
                                       oninput="checkPasswordStrength(this.value)">
                                <button type="button" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2"
                                        onclick="togglePasswordVisibility('newPassword', this)">
                                    <i class="fas fa-eye" style="color: var(--text-secondary);"></i>
                                </button>
                            </div>
                            @error('new_password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            
                            <!-- Password Strength Meter -->
                            <div class="mt-2">
                                <div class="flex justify-between text-xs mb-1">
                                    <span style="color: var(--text-secondary);">Password Strength:</span>
                                    <span id="passwordStrengthText" style="color: var(--text-secondary);">Weak</span>
                                </div>
                                <div class="h-2 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                                    <div id="passwordStrengthBar" class="h-full transition-all duration-300" 
                                         style="width: 0%; background-color: var(--danger);"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <span class="text-red-500">*</span> Confirm New Password
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="new_password_confirmation" 
                                       id="confirmPassword"
                                       class="index-custom-input w-full"
                                       placeholder="Confirm new password"
                                       required
                                       oninput="checkPasswordMatch()">
                                <button type="button" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2"
                                        onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <i class="fas fa-eye" style="color: var(--text-secondary);"></i>
                                </button>
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);" id="passwordMatchMessage">
                                Passwords must match
                            </p>
                        </div>
                        
                        <!-- Additional Options -->
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="force_password_change" 
                                       name="force_password_change" 
                                       value="1" 
                                       class="index-custom-checkbox">
                                <label for="force_password_change" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Require password change on next login
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="generate_password" 
                                       class="index-custom-checkbox"
                                       onchange="togglePasswordGeneration()">
                                <label for="generate_password" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Generate strong password
                                </label>
                            </div>
                            
                            <div id="generatedPasswordContainer" class="hidden">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Generated Password
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="text" 
                                           id="generatedPassword" 
                                           readonly
                                           class="index-custom-input flex-1 font-mono">
                                    <button type="button" 
                                            onclick="copyGeneratedPassword()"
                                            class="px-3 py-2 rounded-lg text-sm"
                                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" 
                                            onclick="generateNewPassword()"
                                            class="px-3 py-2 rounded-lg text-sm"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Click copy to use this password
                                </p>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-between items-center pt-6 border-t" style="border-color: var(--border-color);">
                            <div>
                                <a href="{{ route('developer.super-admins.show', $user->id) }}" 
                                   class="inline-flex items-center text-sm font-medium" 
                                   style="color: var(--text-secondary);">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Details
                                </a>
                            </div>
                            <div class="flex items-center space-x-3">
                                <button type="reset" 
                                        class="px-4 py-2 rounded-lg font-medium btn-secondary">
                                    <i class="fas fa-redo mr-2"></i> Reset
                                </button>
                                <button type="submit" 
                                        id="submitBtn"
                                        class="px-4 py-2 rounded-lg font-medium text-white btn-primary opacity-50 cursor-not-allowed"
                                        disabled>
                                    <i class="fas fa-save mr-2"></i> Update Password
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Security Warning -->
    <div class="card mt-6 border-l-4" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold mb-2" style="color: var(--text-primary);">
                        Important Security Notice
                    </h4>
                    <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-0.5 text-xs" style="color: var(--warning);"></i>
                            <span>Changing a user's password will log them out of all active sessions</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-0.5 text-xs" style="color: var(--warning);"></i>
                            <span>The user will need to use the new password on their next login</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-0.5 text-xs" style="color: var(--warning);"></i>
                            <span>Consider notifying the user about the password change</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-0.5 text-xs" style="color: var(--warning);"></i>
                            <span>For security reasons, previous passwords cannot be retrieved</span>
                        </li>
                    </ul>
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
    
    // Generate initial password if checkbox is checked
    if (document.getElementById('generate_password').checked) {
        togglePasswordGeneration();
    }
});

function initFormValidation() {
    const form = document.getElementById('changePasswordForm');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('input', function() {
        validateForm();
    });
    
    form.addEventListener('submit', function(event) {
        if (!validateForm()) {
            event.preventDefault();
            return false;
        }
        
        // Confirm action
        if (!confirm('Are you sure you want to change this Super Admin\'s password?')) {
            event.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Initial validation
    validateForm();
}

function validateForm() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const submitBtn = document.getElementById('submitBtn');
    
    // Check password strength
    const strength = checkPasswordStrength(newPassword);
    
    // Check password match
    const match = newPassword === confirmPassword && newPassword.length > 0;
    
    // Update match message
    const matchMessage = document.getElementById('passwordMatchMessage');
    if (matchMessage) {
        if (confirmPassword.length === 0) {
            matchMessage.textContent = 'Passwords must match';
            matchMessage.style.color = 'var(--text-secondary)';
        } else if (match) {
            matchMessage.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Passwords match';
            matchMessage.style.color = 'var(--success)';
        } else {
            matchMessage.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Passwords do not match';
            matchMessage.style.color = 'var(--danger)';
        }
    }
    
    // Enable/disable submit button
    if (strength >= 3 && match && newPassword.length >= 8) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        return true;
    } else {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        return false;
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    
    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Character type checks
    if (/[A-Z]/.test(password)) strength++; // Uppercase
    if (/[a-z]/.test(password)) strength++; // Lowercase
    if (/[0-9]/.test(password)) strength++; // Numbers
    if (/[^A-Za-z0-9]/.test(password)) strength++; // Special characters
    
    // Update strength bar
    const percentage = (strength / 5) * 100;
    strengthBar.style.width = percentage + '%';
    
    // Update colors and text
    if (strength <= 2) {
        strengthBar.style.backgroundColor = 'var(--danger)';
        strengthText.textContent = 'Weak';
        strengthText.style.color = 'var(--danger)';
    } else if (strength <= 3) {
        strengthBar.style.backgroundColor = 'var(--warning)';
        strengthText.textContent = 'Fair';
        strengthText.style.color = 'var(--warning)';
    } else if (strength <= 4) {
        strengthBar.style.backgroundColor = 'var(--info)';
        strengthText.textContent = 'Good';
        strengthText.style.color = 'var(--info)';
    } else {
        strengthBar.style.backgroundColor = 'var(--success)';
        strengthText.textContent = 'Strong';
        strengthText.style.color = 'var(--success)';
    }
    
    return strength;
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const matchMessage = document.getElementById('passwordMatchMessage');
    
    if (confirmPassword.length === 0) {
        matchMessage.textContent = 'Passwords must match';
        matchMessage.style.color = 'var(--text-secondary)';
    } else if (newPassword === confirmPassword) {
        matchMessage.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Passwords match';
        matchMessage.style.color = 'var(--success)';
    } else {
        matchMessage.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Passwords do not match';
        matchMessage.style.color = 'var(--danger)';
    }
    
    validateForm();
}

function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function togglePasswordGeneration() {
    const generateCheckbox = document.getElementById('generate_password');
    const generatedContainer = document.getElementById('generatedPasswordContainer');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    if (generateCheckbox.checked) {
        generatedContainer.classList.remove('hidden');
        generateNewPassword();
        
        // Auto-fill passwords
        const generatedPassword = document.getElementById('generatedPassword').value;
        newPasswordInput.value = generatedPassword;
        confirmPasswordInput.value = generatedPassword;
        
        // Trigger validation
        checkPasswordStrength(generatedPassword);
        checkPasswordMatch();
        validateForm();
    } else {
        generatedContainer.classList.add('hidden');
        newPasswordInput.value = '';
        confirmPasswordInput.value = '';
        validateForm();
    }
}

function generateNewPassword() {
    const length = 12;
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const numbers = '0123456789';
    const special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    let password = '';
    
    // Ensure at least one of each type
    password += uppercase.charAt(Math.floor(Math.random() * uppercase.length));
    password += lowercase.charAt(Math.floor(Math.random() * lowercase.length));
    password += numbers.charAt(Math.floor(Math.random() * numbers.length));
    password += special.charAt(Math.floor(Math.random() * special.length));
    
    // Fill remaining length
    const allChars = uppercase + lowercase + numbers + special;
    for (let i = password.length; i < length; i++) {
        password += allChars.charAt(Math.floor(Math.random() * allChars.length));
    }
    
    // Shuffle the password
    password = password.split('').sort(() => 0.5 - Math.random()).join('');
    
    // Update the generated password field
    document.getElementById('generatedPassword').value = password;
    
    // Update the password fields
    document.getElementById('newPassword').value = password;
    document.getElementById('confirmPassword').value = password;
    
    // Trigger validation
    checkPasswordStrength(password);
    checkPasswordMatch();
    validateForm();
}

function copyGeneratedPassword() {
    const generatedPassword = document.getElementById('generatedPassword');
    generatedPassword.select();
    generatedPassword.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        navigator.clipboard.writeText(generatedPassword.value).then(() => {
            // Show success feedback
            const copyBtn = document.querySelector('button[onclick="copyGeneratedPassword()"]');
            const originalHtml = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i>';
            copyBtn.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            copyBtn.style.color = 'var(--success)';
            copyBtn.style.borderColor = 'rgba(var(--success-rgb), 0.3)';
            
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.style.backgroundColor = '';
                copyBtn.style.color = '';
                copyBtn.style.borderColor = '';
            }, 2000);
        });
    } catch (err) {
        console.error('Failed to copy:', err);
        alert('Failed to copy password to clipboard.');
    }
}
</script>

<style>
/* Change password specific styles */
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

.index-custom-input.error {
    border-color: var(--danger);
}

/* Password strength bar */
#passwordStrengthBar {
    transition: all 0.3s ease;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
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

/* Checkbox styling */
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

/* Generated password input */
#generatedPassword {
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .max-w-md {
        width: 95%;
    }
    
    .flex.items-center.space-x-2 {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .flex.items-center.space-x-2 > * {
        width: 100%;
    }
}
</style>
@endsection