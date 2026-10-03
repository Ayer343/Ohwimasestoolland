@extends('layouts.auth')

@section('title', 'Accept Invitation - ' . config('app.name'))

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-green-100">
                <i class="fas fa-user-plus text-green-600 text-xl"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Complete Your Registration
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Welcome, <strong>{{ $user->name }}</strong>! Set up your password to activate your account.
            </p>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-md bg-red-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Account Information -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
            <h3 class="text-sm font-medium text-blue-800 mb-2">Account Details</h3>
            <div class="space-y-1 text-sm text-blue-700">
                <div class="flex justify-between">
                    <span>Name:</span>
                    <span class="font-medium">{{ $user->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Email:</span>
                    <span class="font-medium">{{ $user->email }}</span>
                </div>
                @if($user->phone)
                <div class="flex justify-between">
                    <span>Phone:</span>
                    <span class="font-medium">{{ $user->phone }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span>Role:</span>
                    <span class="font-medium">{{ $user->type_name }}</span>
                </div>
            </div>
        </div>

        <!-- Invitation Expiry Notice -->
        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Invitation Expires</h3>
                    <div class="mt-1 text-sm text-yellow-700">
                        <p>This invitation link expires in <strong>{{ $user->invitation_token_expires_at->diffForHumans() }}</strong></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Password Setup Form -->
        <form class="mt-8 space-y-6" method="POST" action="{{ route('user.invitations.accept.process', $token) }}">
            @csrf

            <!-- Password Field -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                <div class="mt-1 relative">
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        autocomplete="new-password"
                        required
                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-300 text-red-900 @enderror"
                        placeholder="Enter your new password">
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePasswordVisibility('password')">
                        <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-1 text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Must be at least 8 characters with uppercase, lowercase, number, and special character
                </div>
            </div>

            <!-- Confirm Password Field -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <div class="mt-1 relative">
                    <input 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        type="password" 
                        autocomplete="new-password"
                        required
                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="Confirm your new password">
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePasswordVisibility('password_confirmation')">
                        <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                    </button>
                </div>
            </div>

            <!-- Password Strength Meter -->
            <div class="bg-gray-50 rounded-lg p-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Password Strength</label>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <div id="strength-meter" class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div id="strength-bar" class="h-full bg-red-500 transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <span id="strength-text" class="ml-2 text-xs font-medium text-gray-500">Weak</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500">
                        <div class="flex items-center" id="length-check">
                            <i class="fas fa-times text-red-400 mr-1"></i>
                            <span>8+ characters</span>
                        </div>
                        <div class="flex items-center" id="uppercase-check">
                            <i class="fas fa-times text-red-400 mr-1"></i>
                            <span>Uppercase</span>
                        </div>
                        <div class="flex items-center" id="lowercase-check">
                            <i class="fas fa-times text-red-400 mr-1"></i>
                            <span>Lowercase</span>
                        </div>
                        <div class="flex items-center" id="number-check">
                            <i class="fas fa-times text-red-400 mr-1"></i>
                            <span>Number</span>
                        </div>
                        <div class="flex items-center" id="special-check">
                            <i class="fas fa-times text-red-400 mr-1"></i>
                            <span>Special character</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms and Conditions -->
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input 
                            id="agree_terms" 
                            name="agree_terms" 
                            type="checkbox" 
                            required
                            class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded @error('agree_terms') border-red-300 @enderror">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agree_terms" class="font-medium text-gray-700">I agree to the <a href="{{ route('terms') }}" target="_blank" class="text-blue-600 hover:text-blue-500">Terms and Conditions</a></label>
                        @error('agree_terms')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input 
                            id="agree_privacy" 
                            name="agree_privacy" 
                            type="checkbox" 
                            required
                            class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded @error('agree_privacy') border-red-300 @enderror">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agree_privacy" class="font-medium text-gray-700">I agree to the <a href="{{ route('privacy') }}" target="_blank" class="text-blue-600 hover:text-blue-500">Privacy Policy</a></label>
                        @error('agree_privacy')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div>
                <button 
                    type="submit" 
                    id="submit-btn"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-lock text-blue-500 group-hover:text-blue-400"></i>
                    </span>
                    Activate My Account
                </button>
            </div>

            <!-- Security Notice -->
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shield-alt text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Secure Account Setup</h3>
                        <div class="mt-1 text-sm text-green-700">
                            <p>Your password is encrypted and stored securely. After activation, you'll be automatically logged into your account.</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Support Information -->
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Need help? 
                <a href="mailto:support@example.com" class="font-medium text-blue-600 hover:text-blue-500">
                    Contact Support
                </a>
            </p>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
    };

    // Update check icons
    Object.keys(checks).forEach(key => {
        const checkElement = document.getElementById(`${key}-check`);
        const icon = checkElement.querySelector('i');
        if (checks[key]) {
            icon.classList.remove('fa-times', 'text-red-400');
            icon.classList.add('fa-check', 'text-green-400');
            strength++;
        } else {
            icon.classList.remove('fa-check', 'text-green-400');
            icon.classList.add('fa-times', 'text-red-400');
        }
    });

    // Update strength meter
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    const percentage = (strength / 5) * 100;

    strengthBar.style.width = `${percentage}%`;

    if (strength <= 2) {
        strengthBar.className = 'h-full bg-red-500 transition-all duration-300';
        strengthText.textContent = 'Weak';
        strengthText.className = 'ml-2 text-xs font-medium text-red-500';
    } else if (strength <= 3) {
        strengthBar.className = 'h-full bg-yellow-500 transition-all duration-300';
        strengthText.textContent = 'Fair';
        strengthText.className = 'ml-2 text-xs font-medium text-yellow-500';
    } else if (strength <= 4) {
        strengthBar.className = 'h-full bg-blue-500 transition-all duration-300';
        strengthText.textContent = 'Good';
        strengthText.className = 'ml-2 text-xs font-medium text-blue-500';
    } else {
        strengthBar.className = 'h-full bg-green-500 transition-all duration-300';
        strengthText.textContent = 'Strong';
        strengthText.className = 'ml-2 text-xs font-medium text-green-500';
    }

    // Enable/disable submit button
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = strength < 3; // Require at least "Fair" strength
}

// Event listeners
document.getElementById('password').addEventListener('input', function(e) {
    checkPasswordStrength(e.target.value);
});

// Form submission enhancement
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const passwordConfirmation = document.getElementById('password_confirmation').value;
    
    if (password !== passwordConfirmation) {
        e.preventDefault();
        alert('Passwords do not match. Please confirm your password.');
        return;
    }

    // Show loading state
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Activating Account...';
    submitBtn.disabled = true;
});
</script>

<style>
/* Custom styles for better UX */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Focus styles */
input:focus {
    outline: none;
    ring: 2px;
    ring-color: rgb(59 130 246);
}

/* Checkbox styles */
input[type="checkbox"]:focus {
    outline: 2px solid rgb(59 130 246);
    outline-offset: 2px;
}

/* Transition for smooth interactions */
* {
    transition: all 0.2s ease-in-out;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .max-w-md {
        margin: 1rem;
    }
}
</style>
@endsection