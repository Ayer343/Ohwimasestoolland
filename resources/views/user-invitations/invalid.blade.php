<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Invitation - {{ $systemName }}</title>
    
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
        <meta name="msapplication-TileColor" content="#6b7280">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#6b7280">
    @endif
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-ban text-gray-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Invalid Invitation</h2>
            <p class="mt-2 text-sm text-gray-600">
                This invitation is no longer valid
            </p>
        </div>

        <!-- Content -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex">
                <i class="fas fa-info-circle text-gray-500 mt-1 mr-3"></i>
                <div>
                    <p class="text-gray-800 text-sm">
                        @if($invitation)
                            @if($invitation->isAccepted())
                                This invitation has already been accepted.
                            @elseif($invitation->isCancelled() || $invitation->isRevoked())
                                This invitation has been cancelled or revoked.
                            @else
                                This invitation is no longer active.
                            @endif
                        @else
                            The invitation link you used is invalid or has been used already.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            @if($invitation && $invitation->invitedBy)
                <a href="mailto:{{ $invitation->invitedBy->email }}?subject=Invalid%20Invitation%20Link" 
                   class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-user-tie mr-2"></i>
                    Contact Inviter
                </a>
            @endif

            <a href="mailto:{{ $supportEmail }}?subject=Invalid%20Invitation%20Help" 
               class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition duration-200">
                <i class="fas fa-headset mr-2"></i>
                Contact Support
            </a>

            <a href="{{ url('/') }}" 
               class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition duration-200">
                <i class="fas fa-home mr-2"></i>
                Return to Homepage
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">
                Need immediate assistance? Contact our support team.
            </p>
            <p class="text-xs text-gray-400 mt-2">
                &copy; {{ date('Y') }} {{ $systemName }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>