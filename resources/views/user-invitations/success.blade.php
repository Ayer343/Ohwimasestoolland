<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome! - {{ $systemName }}</title>
    
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
        <meta name="msapplication-TileColor" content="#16a34a">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#16a34a">
    @endif
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Welcome Aboard!</h2>
            <p class="mt-2 text-sm text-gray-600">
                Your account has been successfully activated
            </p>
        </div>

        <!-- Success Message -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex">
                <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                <div>
                    <p class="text-green-800 text-sm font-medium">
                        Account Setup Complete
                    </p>
                    <p class="text-green-700 text-sm mt-1">
                        You have successfully accepted the invitation and your account is now active.
                    </p>
                </div>
            </div>
        </div>

        @if(session('info'))
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <p class="text-blue-700 text-sm">{{ session('info') }}</p>
                </div>
            </div>
        @endif

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">What's Next?</h3>
            <ul class="text-sm text-blue-800 space-y-2">
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-blue-500 mt-1 mr-2 text-xs"></i>
                    <span>You've been automatically logged in to your account</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-blue-500 mt-1 mr-2 text-xs"></i>
                    <span>Explore your dashboard and get familiar with the system</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-blue-500 mt-1 mr-2 text-xs"></i>
                    <span>Update your profile information if needed</span>
                </li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            <a href="{{ route('dashboard') }}" 
               class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                <i class="fas fa-tachometer-alt mr-2"></i>
                Go to Dashboard
            </a>

            <a href="{{ route('profile.edit') }}" 
               class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition duration-200">
                <i class="fas fa-user-edit mr-2"></i>
                Update Profile
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">
                Need help getting started? Check out our documentation or contact support.
            </p>
            <p class="text-xs text-gray-400 mt-2">
                &copy; {{ date('Y') }} {{ $systemName }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>