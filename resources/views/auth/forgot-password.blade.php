<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.short_name', 'Hilltop')) - Forgot Password</title>

    @php
        use Illuminate\Support\Facades\Cache;
        use Illuminate\Support\Facades\Storage;

        // ⭐ Defensive settings resolution (matches login blade)
        try {
            $settings = $settings
                ?? Cache::remember(
                    'system_settings_login_blade',
                    now()->addMinutes(30),
                    fn () => \App\Models\SystemSetting::getSettings()
                );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug(
                'Forgot-password settings resolution failed: ' . $e->getMessage()
            );
            $settings = null;
        }

        // ⭐ Favicon (hardened, same logic as login blade)
        $faviconUrl     = null;
        $faviconMime    = 'image/x-icon';
        $faviconVersion = null;

        try {
            if ($settings && method_exists($settings, 'hasFavicon') && $settings->hasFavicon()) {
                $candidate = null;

                if (method_exists($settings, 'getFaviconUrl')) {
                    $candidate = $settings->getFaviconUrl();
                }

                if (empty($candidate) && !empty($settings->system_favicon)) {
                    try {
                        $candidate = Storage::disk('public')->url($settings->system_favicon);
                    } catch (\Throwable $e) { }

                    if (!empty($candidate) && !preg_match('~^https?://~i', $candidate)) {
                        $candidate = url($candidate);
                    }
                }

                if (empty($candidate) && !empty($settings->system_favicon)) {
                    $candidate = asset('storage/' . ltrim($settings->system_favicon, '/'));
                }

                if (!empty($candidate)) {
                    $faviconUrl = $candidate;

                    $ext = strtolower(pathinfo(parse_url($faviconUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                    $faviconMime = match ($ext) {
                        'png'  => 'image/png',
                        'svg'  => 'image/svg+xml',
                        'gif'  => 'image/gif',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'webp' => 'image/webp',
                        'ico'  => 'image/x-icon',
                        default => 'image/x-icon',
                    };

                    $faviconVersion = $settings->updated_at?->timestamp ?? time();
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug(
                'Forgot-password favicon resolution failed: ' . $e->getMessage()
            );
            $faviconUrl = null;
        }

        $faviconFallback = asset('favicon.ico');

        // ⭐ LOGIN-PAGE CONFIG — same source as the login blade
        // Ensures the forgot-password page uses the SAME background,
        // overlay opacity, default theme, and system name.
        $loginConfig = [
            'background_image'    => null,
            'background_url'      => null,
            'overlay_opacity'     => 0.85,
            'default_theme'       => 'dark',
            'system_name_override'=> null,
        ];

        $loginBgUrl = null;

        try {
            if ($settings && method_exists($settings, 'getLoginPageConfig')) {
                $resolved = $settings->getLoginPageConfig();
                if (is_array($resolved)) {
                    $loginConfig = array_replace_recursive($loginConfig, $resolved);
                }
            }

            if ($settings && method_exists($settings, 'getLoginPageBackgroundUrl')) {
                $loginBgUrl = $settings->getLoginPageBackgroundUrl();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug(
                'Forgot-password login-config resolution failed: ' . $e->getMessage()
            );
        }

        // ⭐ Background image — same selection logic as login page
        $hasCustomBg   = !empty($loginBgUrl);
        $loginBgImage  = $loginBgUrl
            ?: 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=75';

        // Detect type for preload
        $bgExt = strtolower(pathinfo(parse_url($loginBgImage, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $bgPreloadType = null;
        if (in_array($bgExt, ['jpg','jpeg'], true))      $bgPreloadType = 'image/jpeg';
        elseif ($bgExt === 'png')                        $bgPreloadType = 'image/png';
        elseif ($bgExt === 'webp')                       $bgPreloadType = 'image/webp';
        elseif ($bgExt === 'gif')                        $bgPreloadType = 'image/gif';

        // Effective system name (respects login-page override)
        $loginSystemName = !empty($loginConfig['system_name_override'])
            ? $loginConfig['system_name_override']
            : ($settings->system_name ?? config('app.name', 'Community Portal'));

        // Default theme
        $loginDefaultTheme = in_array($loginConfig['default_theme'] ?? 'dark', ['dark', 'light', 'system'], true)
            ? $loginConfig['default_theme']
            : 'dark';

        // Overlay opacity
        $overlayOpacity = (float) ($loginConfig['overlay_opacity'] ?? 0.85);
        $overlayOpacity = max(0, min(1, $overlayOpacity));
        $overlayColorTop    = 'rgba(15, 23, 42, ' . $overlayOpacity . ')';
        $overlayColorBottom = 'rgba(30, 58, 138, ' . max(0, $overlayOpacity - 0.05) . ')';
        $darkOverlayTop     = 'rgba(0, 0, 0, ' . min(1, $overlayOpacity + 0.05) . ')';
        $darkOverlayBottom  = 'rgba(15, 23, 42, ' . $overlayOpacity . ')';
    @endphp

    {{-- ============ FAVICON ============ --}}
    @if($faviconUrl)
        <link rel="icon" type="{{ $faviconMime }}" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="shortcut icon" type="{{ $faviconMime }}" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="16x16"   href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="32x32"   href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="64x64"   href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="192x192" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        @if($faviconMime === 'image/svg+xml')
            <link rel="mask-icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}" color="#1e3a8a">
        @endif
        <meta name="msapplication-TileImage" content="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @else
        <link rel="icon" type="image/x-icon" href="{{ $faviconFallback }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ $faviconFallback }}">
        <link rel="apple-touch-icon" href="{{ $faviconFallback }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @endif

    {{-- ⭐ Preload background (same as login blade) --}}
    <link rel="preconnect" href="https://images.unsplash.com" crossorigin>
    <link rel="dns-prefetch" href="https://images.unsplash.com">
    @if($bgPreloadType)
        <link
            rel="preload"
            as="image"
            href="{{ $loginBgImage }}"
            type="{{ $bgPreloadType }}"
            fetchpriority="high"
        >
    @else
        <link rel="preload" as="image" href="{{ $loginBgImage }}" fetchpriority="high">
    @endif

    {{-- ⭐ Provide admin default theme to the anti-flash script --}}
    <script>
        window.Laravel = window.Laravel || {};
        window.Laravel.loginDefaultTheme = '{{ $loginDefaultTheme }}';
        window.Laravel.loginSystemName   = '{{ $loginSystemName }}';
    </script>

    <!-- Anti-flash dark theme script -->
    <script>
        (function() {
            let theme = 'dark';

            try {
                const stored = localStorage.getItem('theme');
                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                } else {
                    const adminDefault = (window.Laravel && window.Laravel.loginDefaultTheme) || 'dark';
                    if (adminDefault === 'light') {
                        theme = 'light';
                    } else if (adminDefault === 'system') {
                        theme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)
                            ? 'light' : 'dark';
                    } else {
                        theme = 'dark';
                    }
                }
            } catch(e) { /* fail silently */ }

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);

            const style = document.createElement('style');
            style.textContent = `
                html { background-color: #0a0f1c; }
                html:not(.dark) { background-color: #f8fafc; }
                body { visibility: visible !important; }
            `;
            document.head.appendChild(style);
        })();
    </script>

    <!-- ⭐ Critical inlined CSS so the card paints at first paint -->
    <style>
        html, body { margin: 0; padding: 0; min-height: 100vh; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            padding: 1rem;
            position: relative;
        }
        .login-bg-image {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-color: #0f172a;
            overflow: hidden;
        }
        .login-bg-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        .login-bg-image img.is-loaded { opacity: 1; }

        /* Overlay on top of image — instant paint before image loads */
        .login-bg-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, {{ $overlayColorTop }} 0%, {{ $overlayColorBottom }} 100%);
        }
        html.dark .login-bg-image::after {
            background: linear-gradient(135deg, {{ $darkOverlayTop }} 0%, {{ $darkOverlayBottom }} 100%);
        }

        .glass-card {
            background: var(--card-bg, rgba(15, 23, 42, 0.92));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 2;
            position: relative;
        }

        .content-container {
            z-index: 10;
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
    </style>

    {{-- ⭐ Async-load Tailwind & FontAwesome --}}
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
          rel="stylesheet"
          media="print"
          onload="this.media='all'">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
          rel="stylesheet"
          media="print"
          onload="this.media='all'">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e3a8a;
            --accent: #8b5cf6;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --input-bg: transparent;
            --label-color: #64748b;
            --divider-color: #e2e8f0;
        }

        html.dark {
            --primary: #0f172a;
            --secondary: #3b82f6;
            --accent: #a78bfa;
            --card-bg: rgba(15, 23, 42, 0.92);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-light: #334155;
            --input-bg: rgba(51, 65, 85, 0.3);
            --label-color: #94a3b8;
            --divider-color: #334155;
        }

        .bg-pattern {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.15;
            z-index: 1;
            pointer-events: none;
        }

        html.dark .glass-card {
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .glass-card { transition: all 0.3s ease; }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .btn-primary:focus-visible {
            outline: 2px solid rgba(96, 165, 250, 0.5);
            outline-offset: 2px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 20px;
        }

        .floating-input {
            border: 0;
            border-bottom: 2px solid var(--border-light);
            outline: none;
            transition: all 0.3s ease;
            background: var(--input-bg);
            padding-left: 10px;
            color: var(--text-primary);
            width: 100%;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        .floating-input:focus { border-color: var(--secondary); }
        .floating-input.error {
            border-color: #ef4444;
            animation: shake 0.5s ease-in-out;
        }

        .floating-label label {
            position: absolute;
            top: 12px;
            left: 10px;
            color: var(--label-color);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .floating-input:focus ~ label,
        .floating-input:not(:placeholder-shown) ~ label {
            top: -15px;
            left: 0;
            font-size: 12px;
            color: var(--secondary);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        html.dark .info-box { background: rgba(59, 130, 246, 0.15); }

        .success-box {
            background: rgba(34, 197, 94, 0.1);
            border-left: 4px solid #22c55e;
        }
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
        }

        .back-to-login { transition: all 0.3s ease; }
        .back-to-login:hover { transform: translateX(-4px); }

        @media (max-width: 640px) {
            body { padding: 0.5rem; }
            .glass-card { padding: 1.5rem !important; }
        }

        @media (prefers-contrast: high) {
            .glass-card { border: 2px solid currentColor; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

    {{-- ⭐ Background image layer (same delivery as login blade) --}}
    <div class="login-bg-image" aria-hidden="true">
        <img
            id="loginBgImg"
            src="{{ $loginBgImage }}"
            alt=""
            fetchpriority="high"
            decoding="async"
            onload="this.classList.add('is-loaded');"
            onerror="this.style.display='none';"
        >
    </div>

    <div class="bg-pattern"></div>

    <div class="content-container">
        <div class="glass-card rounded-3xl shadow-2xl p-8">
            <!-- Header with Icon -->
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-3xl text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h2 class="text-2xl font-semibold text-slate-800 dark:text-slate-100">Forgot Password?</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-2">
                    No worries! Enter your email address or phone number below and we'll send you a password reset link.
                </p>
            </div>

            <!-- Info Message -->
            <div class="info-box flex items-start">
                <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
                <div class="text-xs text-slate-600 dark:text-slate-300">
                    <p class="font-semibold mb-1">How it works:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Enter your registered email or phone number</li>
                        <li>We'll send a password reset link to your email</li>
                        <li>Click the link to create a new password</li>
                        <li>The link expires after 60 minutes for security</li>
                    </ul>
                </div>
            </div>

            <!-- Success Message -->
            @if (session('status'))
                <div class="success-box flex items-start rounded-lg p-4 mb-4">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-0.5"></i>
                    <div class="text-sm text-green-700 dark:text-green-300">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="error-box rounded-lg p-4 mb-4">
                    @foreach ($errors->all() as $error)
                        <div class="flex items-start text-sm text-red-700 dark:text-red-300 mb-1">
                            <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Reset Form -->
            <form method="POST" action="{{ route('password.email') }}" id="resetForm">
                @csrf

                <div class="floating-label">
                    <input
                        id="login"
                        type="text"
                        name="login"
                        value="{{ old('login') }}"
                        required
                        autofocus
                        class="floating-input"
                        placeholder=" "
                        autocomplete="email"
                    >
                    <label for="login">
                        <i class="fas fa-envelope text-slate-400 mr-2"></i>Email or Phone Number
                    </label>
                </div>

                <div class="text-xs text-slate-500 dark:text-slate-400 mb-6 -mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    Enter the email address or phone number you used to register
                </div>

                <button
                    type="submit"
                    class="w-full btn-primary text-white py-3 px-4 rounded-xl font-semibold transition-all duration-200 flex items-center justify-center"
                    id="submitBtn"
                >
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Reset Link
                </button>

                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="back-to-login inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Login
                    </a>
                </div>
            </form>

            <!-- Support Contact -->
            <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                <p class="text-xs text-center text-slate-500 dark:text-slate-400">
                    <i class="fas fa-headset mr-1"></i>
                    Still having trouble?
                    <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">Contact Support</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ⭐ Safety net: if the browser cached the bg before JS ran
            const bgImg = document.getElementById('loginBgImg');
            if (bgImg && bgImg.complete && bgImg.naturalWidth > 0) {
                bgImg.classList.add('is-loaded');
            }

            const form       = document.getElementById('resetForm');
            const loginField = document.getElementById('login');
            const submitBtn  = document.getElementById('submitBtn');

            // Auto-detect input type and provide helpful hints
            if (loginField) {
                loginField.addEventListener('input', function() {
                    const value = this.value.trim();

                    this.classList.remove('error');

                    if (value.includes('@')) {
                        this.setAttribute('data-type', 'email');
                    } else if (/^\d+$/.test(value) && value.length > 5) {
                        this.setAttribute('data-type', 'phone');
                    } else {
                        this.removeAttribute('data-type');
                    }
                });
            }

            // Form validation + loading state
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;

                    if (!loginField.value.trim()) {
                        loginField.classList.add('error');
                        valid = false;
                        setTimeout(() => loginField.classList.remove('error'), 500);
                    }

                    if (!valid) {
                        e.preventDefault();
                        return;
                    }

                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 30000);
                });
            }

            if (loginField) {
                loginField.focus();
                loginField.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (form) form.dispatchEvent(new Event('submit'));
                    }
                });
            }
        });
    </script>
</body>
</html>