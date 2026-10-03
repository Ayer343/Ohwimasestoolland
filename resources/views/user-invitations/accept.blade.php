<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept User Invitation - {{ $systemName }}</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#2563eb">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#2563eb">
    @endif
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
        <!-- Header -->
        <div class="text-center">
            @if($systemSettings->logo_path ?? false)
                <img src="{{ asset('storage/' . $systemSettings->logo_path) }}" alt="{{ $systemName }}" class="mx-auto h-12 w-auto mb-4">
            @else
                <div class="mx-auto h-12 w-12 bg-blue-600 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-user-plus text-white text-xl"></i>
                </div>
            @endif
            <h2 class="text-3xl font-bold text-gray-900">Welcome to {{ $systemName }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                You've been invited by <strong>{{ $invitation->invitedBy->name ?? 'Administrator' }}</strong>
            </p>
            
            @if($expirationWarning)
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                        <p class="text-yellow-800 text-sm">{{ $expirationWarning }}</p>
                    </div>
                </div>
            @endif

            @if($wasRepaired)
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-info-circle text-blue-500 mt-1 mr-2"></i>
                        <p class="text-blue-800 text-sm">Your invitation expiration has been automatically extended for security.</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Invitation Details -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-blue-600"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $invitation->user->name }}</p>
                    <p class="text-sm text-gray-500">{{ $invitation->user->email }}</p>
                    @if($invitation->user->phone)
                        <p class="text-sm text-gray-500">{{ $invitation->user->phone }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Acceptance Form -->
        <form class="mt-8 space-y-6" method="POST" action="{{ route('invitation.process-acceptance', $invitation->token) }}">
            @csrf
            
            <!-- Password Field -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Create Password</label>
                <div class="mt-1 relative">
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        required 
                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-300 @enderror"
                        placeholder="Enter your password"
                        minlength="8"
                    >
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('password')">
                        <i class="far fa-eye text-gray-400 hover:text-gray-600"></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">
                    Must be at least 8 characters with uppercase, lowercase, number, and special character
                </p>
            </div>

            <!-- Confirm Password Field -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <div class="mt-1 relative">
                    <input 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        type="password" 
                        required 
                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="Confirm your password"
                    >
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('password_confirmation')">
                        <i class="far fa-eye text-gray-400 hover:text-gray-600"></i>
                    </button>
                </div>
            </div>

            <!-- Terms and Conditions -->
            <div class="space-y-3">
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input 
                            id="agree_terms" 
                            name="agree_terms" 
                            type="checkbox" 
                            required
                            class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded"
                        >
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agree_terms" class="font-medium text-gray-700">
                            I agree to the <a href="{{ route('terms') }}" class="text-blue-600 hover:text-blue-500">Terms and Conditions</a>
                        </label>
                    </div>
                </div>
                @error('agree_terms')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input 
                            id="agree_privacy" 
                            name="agree_privacy" 
                            type="checkbox" 
                            required
                            class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded"
                        >
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agree_privacy" class="font-medium text-gray-700">
                            I agree to the <a href="{{ route('privacy') }}" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                @error('agree_privacy')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <div>
                <button 
                    type="submit" 
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                >
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-user-check text-blue-300 group-hover:text-blue-200"></i>
                    </span>
                    Accept Invitation & Continue
                </button>
            </div>
        </form>

        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">
                Need help? Contact <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:text-blue-500">{{ $supportEmail }}</a>
            </p>
            <p class="text-xs text-gray-400 mt-2">
                &copy; {{ date('Y') }} {{ $systemName }}. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentNode.querySelector('i');
            
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

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strength = checkPasswordStrength(password);
            updatePasswordStrength(strength);
        });

        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }

        function updatePasswordStrength(strength) {
            // You can implement a visual strength indicator here
            console.log('Password strength:', strength);
        }
    </script>
</body>
</html>