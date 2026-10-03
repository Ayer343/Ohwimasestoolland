<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Expired - {{ $systemName }}</title>
    
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
        <meta name="msapplication-TileColor" content="#dc2626">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#dc2626">
    @endif
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Invitation Expired</h2>
            <p class="mt-2 text-sm text-gray-600">
                This invitation link has expired
            </p>
        </div>

        <!-- Content -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
                <i class="fas fa-clock text-red-500 mt-1 mr-3"></i>
                <div>
                    <p class="text-red-800 text-sm">
                        @if($invitation)
                            This invitation expired on <strong>{{ $invitation->expires_at->format('F j, Y g:i A') }}</strong>.
                        @else
                            This invitation link is no longer valid.
                        @endif
                    </p>
                    <p class="text-red-700 text-sm mt-2">
                        Invitation links are valid for {{ $expiry_days }} days for security reasons.
                    </p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            @if($invitation && $invitation->invitedBy)
                <a href="mailto:{{ $invitation->invitedBy->email }}?subject=Invitation%20Expired%20-%20Request%20New%20Invitation" 
                   class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-envelope mr-2"></i>
                    Request New Invitation
                </a>
            @endif

            <a href="mailto:{{ $supportEmail }}?subject=Expired%20Invitation%20Help" 
               class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition duration-200">
                <i class="fas fa-headset mr-2"></i>
                Contact Support
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">
                If you believe this is an error, please contact the administrator.
            </p>
            <p class="text-xs text-gray-400 mt-2">
                &copy; {{ date('Y') }} {{ $systemName }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>