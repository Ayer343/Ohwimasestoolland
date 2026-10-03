<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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

    <title>@yield('title', config('app.name', 'Laravel'))</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Styles -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-50">
        <!-- Page Content -->
        @yield('content')
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success') || session()->has('error') || session()->has('warning'))
    <div id="flash-messages" class="fixed top-4 right-4 space-y-2 z-50">
        @if(session()->has('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 shadow-lg max-w-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto pl-3">
                    <i class="fas fa-times text-green-400 hover:text-green-600"></i>
                </button>
            </div>
        </div>
        @endif

        @if(session()->has('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 shadow-lg max-w-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto pl-3">
                    <i class="fas fa-times text-red-400 hover:text-red-600"></i>
                </button>
            </div>
        </div>
        @endif

        @if(session()->has('warning'))
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 shadow-lg max-w-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-800">{{ session('warning') }}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto pl-3">
                    <i class="fas fa-times text-yellow-400 hover:text-yellow-600"></i>
                </button>
            </div>
        </div>
        @endif
    </div>

    <script>
        // Auto-hide flash messages after 5 seconds
        setTimeout(() => {
            const flashMessages = document.getElementById('flash-messages');
            if (flashMessages) {
                flashMessages.remove();
            }
        }, 5000);
    </script>
    @endif

    @stack('scripts')
</body>
</html>