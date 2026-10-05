{{-- ===== FIX 419 PAGE EXPIRED ===== --}}
@php
    // Force session initialization
    if (!session()->isStarted()) {
        session()->start();
    }

    // Ensure CSRF token exists
    if (!session()->has('_token')) {
        session()->regenerateToken();
    }

    $csrfToken = csrf_token();
    $sessionId = session()->getId();

    /*
     | ✅ BILLING: normalize $loginBillingState.
     |
     | It may arrive as:
     |   - array{state,overdue,pending}  (from LoginController::showLoginForm)
     |   - string (state name, e.g. 'active_overdue')  (from a legacy provider composer)
     |   - null / not set
     |
     | The blade must not assume one shape, or we get:
     | "Cannot access offset of type string on string".
     */
    $rawBilling = $loginBillingState ?? null;

    if (is_array($rawBilling)) {
        $bs = array_merge(
            ['state' => null, 'overdue' => false, 'pending' => false],
            $rawBilling
        );
    } elseif (is_string($rawBilling) && $rawBilling !== '') {
        $bs = [
            'state'   => $rawBilling,
            'overdue' => in_array($rawBilling, ['active_overdue', 'active_suspended'], true),
            'pending' => $rawBilling === 'pending_signature',
        ];
    } else {
        $bs = ['state' => null, 'overdue' => false, 'pending' => false];
    }

    // Fold in the legacy boolean if it was set
    if (!empty($loginBillingOverdue)) {
        $bs['overdue'] = true;
    }

    $billingBannerVisible = !empty($bs['overdue']) || !empty($bs['pending']);

    /* ============================================================
     | ✅ FAVICON + SETTINGS — resolved directly in the blade
     | ------------------------------------------------------------
     | This mirrors the forgot-password blade's pattern: resolve
     | SystemSetting directly here so the favicon works regardless
     | of whether the controller injected $systemSettings.
     |
     | Wrapped in try/catch + a short cache so a DB hiccup never
     | breaks the login page.
     ============================================================ */
    try {
        $systemSettings = $systemSettings
            ?? \Illuminate\Support\Facades\Cache::remember(
                'system_settings_login_blade',
                now()->addMinutes(30),
                fn () => \App\Models\SystemSetting::getSettings()
            );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('Login blade settings resolution failed: ' . $e->getMessage());
        $systemSettings = null;
    }

    $faviconUrl     = null;
    $faviconMime    = 'image/x-icon';
    $faviconVersion = null;

    try {
        if ($systemSettings && method_exists($systemSettings, 'hasFavicon') && $systemSettings->hasFavicon()) {
            // Primary: ask the model
            $candidate = null;
            if (method_exists($systemSettings, 'getFaviconUrl')) {
                $candidate = $systemSettings->getFaviconUrl();
            }

            // Secondary: resolve from the public disk
            if (empty($candidate) && !empty($systemSettings->system_favicon)) {
                try {
                    $candidate = \Illuminate\Support\Facades\Storage::disk('public')
                        ->url($systemSettings->system_favicon);
                } catch (\Throwable $e) { /* fall through */ }

                if (!empty($candidate) && !preg_match('~^https?://~i', $candidate)) {
                    $candidate = url($candidate);
                }
            }

            // Tertiary: assume the storage symlink convention
            if (empty($candidate) && !empty($systemSettings->system_favicon)) {
                $candidate = asset('storage/' . ltrim($systemSettings->system_favicon, '/'));
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

                $faviconVersion = $systemSettings->updated_at?->timestamp ?? time();
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('Login blade favicon resolution failed: ' . $e->getMessage());
        $faviconUrl = null;
    }

    $faviconFallback = asset('favicon.ico');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    {{-- ===== FIX 419: CRITICAL CSRF AND SESSION META TAGS ===== --}}
    <meta name="csrf-token" content="{{ $csrfToken }}">
    <meta name="session-id" content="{{ $sessionId }}">
    <meta name="session-status" content="{{ session()->isStarted() ? 'active' : 'inactive' }}">
    <meta name="session-lifetime" content="{{ config('session.lifetime', 120) }}">

    <meta name="description" content="Secure login to {{ $systemSettings->system_name ?? 'Community Portal' }} - Property & Estate Management System">
    <meta name="color-scheme" content="dark light">
    <title>Login | {{ $systemSettings->system_name ?? 'Community Portal' }}</title>

    {{-- ============ FAVICON (hardened) ============ --}}
    @if($faviconUrl)
        {{-- Primary dynamic favicon --}}
        <link rel="icon" type="{{ $faviconMime }}" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="shortcut icon" type="{{ $faviconMime }}" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">

        {{-- Multi-size variants --}}
        <link rel="icon" type="{{ $faviconMime }}" sizes="16x16"   href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="32x32"   href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="64x64"   href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="icon" type="{{ $faviconMime }}" sizes="192x192" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">

        {{-- Apple touch icons --}}
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">

        {{-- Safari pinned tab --}}
        @if($faviconMime === 'image/svg+xml')
            <link rel="mask-icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}" color="#1e3a8a">
        @endif

        {{-- Microsoft Edge Tile --}}
        <meta name="msapplication-TileImage" content="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @else
        {{-- Static fallback chain --}}
        <link rel="icon" type="image/x-icon" href="{{ $faviconFallback }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ $faviconFallback }}">
        <link rel="apple-touch-icon" href="{{ $faviconFallback }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @endif

    {{-- Absolute last-resort: root /favicon.ico --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    {{-- ===== FIX 419: Inline CSRF token for JavaScript ===== --}}
    <script>
        window.Laravel = {
            csrfToken: '{{ $csrfToken }}',
            sessionId: '{{ $sessionId }}',
            sessionLifetime: {{ config('session.lifetime', 120) }},
            routes: {
                login: '{{ route("login") }}',
                csrfRefresh: '{{ route("csrf.refresh") }}',
                sessionCheck: '{{ route("session.check") }}',
                sessionContinue: '{{ route("session.continue") }}',
                sessionInvalidate: '{{ route("session.invalidate") }}'
            }
        };
    </script>

    <!-- CRITICAL: Anti-flash dark theme script - runs immediately -->
    <script>
        (function() {
            let theme = 'dark';
            try {
                const stored = localStorage.getItem('theme');
                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    theme = 'dark';
                }
            } catch(e) { /* fail silently */ }

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);

            const style = document.createElement('style');
            style.textContent = `
                html.dark { background-color: #0a0f1c !important; }
                html:not(.dark) { background-color: #f8fafc !important; }
                body { visibility: visible !important; }
                .no-js-warning { display: none; }
            `;
            document.head.appendChild(style);
        })();
    </script>

    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* CSS Custom Properties - Optimized */
        :root {
            --primary: #0f172a;
            --secondary: #1e3a8a;
            --accent: #8b5cf6;
            --light: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --input-bg: transparent;
            --label-color: #64748b;
            --divider-color: #e2e8f0;
            --social-btn-bg: white;
            --social-btn-border: #e2e8f0;
            --social-btn-text: #334155;
            --password-strength-weak: #ef4444;
            --password-strength-medium: #f59e0b;
            --password-strength-strong: #10b981;
            --focus-ring: rgba(59, 130, 246, 0.5);
            --transition-default: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);

            /* ✅ BILLING banner colors */
            --billing-overdue-bg: rgba(239, 68, 68, 0.12);
            --billing-overdue-border: #ef4444;
            --billing-overdue-text: #991b1b;
            --billing-pending-bg: rgba(245, 158, 11, 0.12);
            --billing-pending-border: #f59e0b;
            --billing-pending-text: #92400e;
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
            --social-btn-bg: #1e293b;
            --social-btn-border: #475569;
            --social-btn-text: #e2e8f0;
            --focus-ring: rgba(96, 165, 250, 0.5);

            /* ✅ BILLING banner colors (dark mode) */
            --billing-overdue-bg: rgba(239, 68, 68, 0.18);
            --billing-overdue-border: #f87171;
            --billing-overdue-text: #fecaca;
            --billing-pending-bg: rgba(245, 158, 11, 0.18);
            --billing-pending-border: #fbbf24;
            --billing-pending-text: #fde68a;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: 1rem;
            margin: 0;
            transition: background-color 0.2s ease;
            background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2076&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 58, 138, 0.8) 100%);
            z-index: 0;
            pointer-events: none;
        }

        html.dark body::before {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.9) 0%, rgba(15, 23, 42, 0.85) 100%);
        }

        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.3;
            z-index: 0;
            pointer-events: none;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 2;
            position: relative;
            transition: var(--transition-default);
            width: 100%;
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }

        .glass-card:hover {
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.3);
        }

        .content-container {
            z-index: 10;
            position: relative;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            padding: 1rem;
        }

        .login-form-container {
            flex: 0 0 450px;
            width: 100%;
            max-width: 450px;
            min-width: 320px;
        }

        .welcome-text {
            flex: 1;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            min-width: 250px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            transition: var(--transition-default);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            border: none;
            font-weight: 600;
            min-height: 48px;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-primary:active::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.4);
        }

        .btn-primary:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-input {
            border: 0;
            border-bottom: 2px solid var(--border-light);
            outline: none;
            transition: var(--transition-default);
            background: var(--input-bg);
            padding: 0.75rem 2.5rem 0.75rem 0.75rem;
            color: var(--text-primary);
            width: 100%;
            font-size: 1rem;
            min-height: 50px;
        }

        .floating-input:focus {
            border-color: var(--secondary);
        }

        .floating-input.error {
            border-color: #ef4444;
            animation: shake 0.5s ease-in-out;
        }

        .floating-label label {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            color: var(--label-color);
            transition: var(--transition-default);
            pointer-events: none;
            font-size: 0.875rem;
        }

        .floating-input:focus ~ label,
        .floating-input:not(:placeholder-shown) ~ label {
            top: -1.25rem;
            left: 0;
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--label-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: var(--transition-default);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: var(--secondary);
            background: rgba(0, 0, 0, 0.05);
        }

        .password-toggle:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }

        .password-strength {
            margin-top: 0.5rem;
            height: 0.25rem;
            border-radius: 0.125rem;
            overflow: hidden;
            background: var(--border-light);
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 0.125rem;
        }

        .social-buttons-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .social-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--social-btn-bg);
            border: 1px solid var(--social-btn-border);
            border-radius: 50%;
            transition: var(--transition-default);
            cursor: pointer;
            text-decoration: none;
            position: relative;
            flex-shrink: 0;
        }

        .social-icon-btn:hover {
            transform: translateY(-2px);
        }

        .social-icon-btn:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }

        .social-icon-btn.google:hover {
            background: #db4437;
            border-color: #db4437;
            box-shadow: 0 4px 12px rgba(219, 68, 55, 0.3);
        }

        .social-icon-btn.google:hover i,
        .social-icon-btn.google:hover img {
            filter: brightness(0) invert(1);
        }

        .social-icon-btn.microsoft:hover {
            background: #00a4ef;
            border-color: #00a4ef;
            box-shadow: 0 4px 12px rgba(0, 164, 239, 0.3);
        }

        .remember-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .remember-checkbox-wrapper input[type="checkbox"] {
            width: 1.125rem;
            height: 1.125rem;
            cursor: pointer;
            accent-color: var(--secondary);
            margin: 0;
            flex-shrink: 0;
        }

        .remember-checkbox-wrapper label {
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.875rem;
            user-select: none;
        }

        .toast-notification {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            padding: 0.875rem 1.125rem;
            border-radius: 0.5rem;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-left: 4px solid;
            z-index: 1000;
            animation: slideInRight 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 350px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .csrf-error-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            padding: 1rem;
            background: #dc2626;
            color: white;
            text-align: center;
            font-weight: 500;
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .csrf-error-banner.show {
            transform: translateY(0);
        }

        .csrf-error-banner a {
            color: white;
            text-decoration: underline;
            cursor: pointer;
            font-weight: 600;
        }

        .csrf-error-banner .close-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            margin-left: 1rem;
            font-size: 1.25rem;
            opacity: 0.8;
        }

        .csrf-error-banner .close-btn:hover {
            opacity: 1;
        }

        /* ✅ BILLING: banner styles */
        .billing-banner {
            border-radius: 0.75rem;
            padding: 1rem 1.125rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            border-left: 4px solid transparent;
            font-size: 0.875rem;
            line-height: 1.45;
        }

        .billing-banner.billing-banner-overdue {
            background: var(--billing-overdue-bg);
            border-left-color: var(--billing-overdue-border);
            color: var(--billing-overdue-text);
        }

        .billing-banner.billing-banner-pending {
            background: var(--billing-pending-bg);
            border-left-color: var(--billing-pending-border);
            color: var(--billing-pending-text);
        }

        .billing-banner i.billing-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-top: 0.0625rem;
        }

        .billing-banner .billing-title {
            font-weight: 700;
            display: block;
            margin-bottom: 0.25rem;
        }

        .billing-banner .billing-body {
            display: block;
        }

        .billing-banner .billing-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            margin-top: 0.5rem;
            padding: 0.375rem 0.75rem;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 0.375rem;
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8125rem;
            transition: var(--transition-default);
        }

        .billing-banner .billing-link:hover {
            background: rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        html.dark .billing-banner .billing-link {
            background: rgba(255, 255, 255, 0.1);
        }

        html.dark .billing-banner .billing-link:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        .skeleton-item {
            background: linear-gradient(90deg, var(--border-light) 25%, var(--text-secondary) 50%, var(--border-light) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 4px;
        }

        @media (max-width: 968px) {
            .content-container {
                flex-direction: column;
                padding: 0.5rem;
            }
            .welcome-text {
                padding-right: 0;
                padding-bottom: 1.5rem;
                text-align: center;
                width: 100%;
            }
            .login-form-container {
                flex: 1 1 auto;
                max-width: 450px;
                min-width: 280px;
                width: 100%;
            }
            .glass-card {
                padding: 1.5rem;
                min-height: 450px;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 1rem;
            }
            .welcome-text {
                display: none;
            }
            .glass-card {
                padding: 1.25rem;
                border-radius: 1.5rem;
                min-height: 400px;
            }
            .login-form-container {
                flex: 1 1 auto;
                min-width: 260px;
                max-width: 100%;
            }
            .toast-notification {
                top: 0.75rem;
                right: 0.75rem;
                left: 0.75rem;
                max-width: none;
            }
        }

        @media (max-width: 400px) {
            .glass-card {
                padding: 1rem;
                min-height: 380px;
            }
            .floating-input {
                font-size: 0.875rem;
                min-height: 44px;
            }
            .btn-primary {
                min-height: 44px;
                font-size: 0.875rem;
            }
        }

        @media (prefers-contrast: high) {
            .glass-card {
                border: 2px solid currentColor;
            }
            .btn-primary {
                border: 1px solid currentColor;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            transform: translate(-50%, -50%);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--divider-color);
        }

        .divider::before { margin-right: 0.75rem; }
        .divider::after { margin-left: 0.75rem; }

        .form-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .session-status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .session-status-badge.active {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .session-status-badge.expired {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
    </style>
</head>
<body>
    <noscript>
        <div class="fixed top-0 left-0 right-0 bg-red-500 text-white p-4 text-center z-50">
            <strong>JavaScript Required:</strong> Please enable JavaScript to use all features of this login page.
        </div>
    </noscript>

    {{-- ===== FIX 419: CSRF Error Banner ===== --}}
    <div id="csrfErrorBanner" class="csrf-error-banner">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <span id="csrfErrorMessage">Your session has expired or the security token is invalid.</span>
        <a onclick="refreshCsrfToken()" class="ml-2">Refresh Now</a>
        <button class="close-btn" onclick="closeCsrfError()" aria-label="Close error banner">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="bg-pattern"></div>

    <div class="content-container">
        <!-- Welcome Section -->
        <div class="welcome-text">
            <h1 class="text-4xl md:text-5xl font-bold mb-6 animate-float">Experience Luxury Living</h1>
            <p class="text-lg md:text-xl mb-8 opacity-90">Hilltop Executive Estate offers premium amenities and breathtaking views in an exclusive community setting.</p>

            <div class="feature-list" style="margin-top: 2rem;">
                <div class="feature-item" style="display: flex; align-items: center; margin-bottom: 1rem; font-weight: 500; animation: slideIn 0.5s ease forwards; opacity: 0; transform: translateX(-20px);">
                    <div class="feature-icon" style="background: rgba(255, 255, 255, 0.2); width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; flex-shrink: 0;">
                        <i class="fas fa-home"></i>
                    </div>
                    <span>Luxury Residences</span>
                </div>
                <div class="feature-item" style="display: flex; align-items: center; margin-bottom: 1rem; font-weight: 500; animation: slideIn 0.5s ease forwards 0.1s; opacity: 0; transform: translateX(-20px);">
                    <div class="feature-icon" style="background: rgba(255, 255, 255, 0.2); width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; flex-shrink: 0;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <span>24/7 Security</span>
                </div>
                <div class="feature-item" style="display: flex; align-items: center; margin-bottom: 1rem; font-weight: 500; animation: slideIn 0.5s ease forwards 0.2s; opacity: 0; transform: translateX(-20px);">
                    <div class="feature-icon" style="background: rgba(255, 255, 255, 0.2); width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; flex-shrink: 0;">
                        <i class="fas fa-swimming-pool"></i>
                    </div>
                    <span>Resort-style Amenities</span>
                </div>
                <div class="feature-item" style="display: flex; align-items: center; margin-bottom: 1rem; font-weight: 500; animation: slideIn 0.5s ease forwards 0.3s; opacity: 0; transform: translateX(-20px);">
                    <div class="feature-icon" style="background: rgba(255, 255, 255, 0.2); width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; flex-shrink: 0;">
                        <i class="fas fa-tree"></i>
                    </div>
                    <span>Beautiful Landscaping</span>
                </div>
            </div>
        </div>

        <!-- Login Form Container -->
        <div class="login-form-container">
            <div class="glass-card rounded-3xl shadow-2xl p-8">
                <!-- Skeleton Loading State -->
                <div id="skeletonLoader" style="display: none; flex: 1;">
                    <div style="width: 60%; height: 28px; margin: 0 auto 8px;">
                        <div class="skeleton-item" style="width: 100%; height: 100%;"></div>
                    </div>
                    <div style="width: 40%; height: 16px; margin: 0 auto 16px;">
                        <div class="skeleton-item" style="width: 100%; height: 100%;"></div>
                    </div>
                    <div style="height: 50px; margin-bottom: 24px;">
                        <div class="skeleton-item" style="width: 100%; height: 100%;"></div>
                    </div>
                    <div style="height: 50px; margin-bottom: 24px;">
                        <div class="skeleton-item" style="width: 100%; height: 100%;"></div>
                    </div>
                    <div style="height: 20px; margin-bottom: 24px;">
                        <div class="skeleton-item" style="width: 40%; height: 100%;"></div>
                    </div>
                    <div style="height: 48px; margin-bottom: 16px;">
                        <div class="skeleton-item" style="width: 100%; height: 100%;"></div>
                    </div>
                    <div style="height: 20px; margin: 16px auto 0; width: 60%;">
                        <div class="skeleton-item" style="width: 100%; height: 100%;"></div>
                    </div>
                </div>

                <!-- Actual Content -->
                <div id="loginContent" style="display: flex; flex-direction: column; flex: 1;">
                    <h2 class="text-2xl font-semibold mb-2" style="color: var(--text-primary); text-align: center;">Welcome Back</h2>
                    <p class="text-sm mb-6" style="color: var(--text-secondary); text-align: center;">Sign in to access your account</p>

                    {{-- ===== ✅ BILLING: System billing banner ===== --}}
                    @if($billingBannerVisible)
                        <div class="billing-banner {{ !empty($bs['overdue']) ? 'billing-banner-overdue' : 'billing-banner-pending' }}"
                             role="alert"
                             aria-live="polite">

                            <i class="fas {{ !empty($bs['overdue']) ? 'fa-exclamation-triangle' : 'fa-signature' }} billing-icon"></i>

                            <div>
                                <span class="billing-title">
                                    @if(!empty($bs['overdue']))
                                        System Billing Overdue
                                    @else
                                        Billing Agreement Awaiting Signature
                                    @endif
                                </span>

                                <span class="billing-body">
                                    @if(!empty($bs['overdue']))
                                        The system billing invoice is past due. Super admins can settle it
                                        from the billing dashboard. Administrative write actions are
                                        temporarily disabled until payment is recorded. Landlords, tenants,
                                        and other system users are unaffected.
                                    @else
                                        The system billing agreement requires a signature before invoicing
                                        and payments can begin. Super admins can review and sign it from
                                        the billing dashboard.
                                    @endif
                                </span>

                                @auth
                                    @if(
                                        auth()->user()->type === \App\Models\User::TYPE_SUPER_ADMIN
                                        && \Route::has('superadmin.billing.dashboard')
                                    )
                                        <a href="{{ route('superadmin.billing.dashboard') }}" class="billing-link">
                                            <i class="fas fa-credit-card"></i>
                                            Go to Billing Dashboard
                                        </a>
                                    @endif
                                @endauth
                            </div>
                        </div>
                    @endif

                    {{-- ===== FIX 419: Session Status Indicator ===== --}}
                    <div id="sessionIndicator" style="display: none; font-size: 0.75rem; padding: 0.5rem; border-radius: 0.375rem; background: rgba(0, 0, 0, 0.05); text-align: center; margin-bottom: 1rem;">
                        <i class="fas fa-desktop mr-1"></i>
                        <span id="sessionStatus">Active session detected</span>
                        <button type="button" id="continueSessionBtn" class="ml-2 text-xs font-medium" style="color: var(--secondary); background: none; border: none; cursor: pointer;">Continue</button>
                        <button type="button" id="newSessionBtn" class="ml-1 text-xs font-medium" style="color: #ef4444; background: none; border: none; cursor: pointer;">New login</button>
                    </div>

                    {{-- ===== FIX 419: CSRF Token Status Indicator ===== --}}
                    <div id="csrfStatusIndicator" style="display: none; font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; text-align: center; margin-bottom: 0.5rem;">
                        <span id="csrfStatusText">CSRF token: active</span>
                    </div>

                    <!-- Saved Credentials Indicator -->
                    <div id="savedCredentialsIndicator" style="display: none; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 0.5rem; padding: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem;">
                        <i class="fas fa-key mr-2" style="color: #10b981;"></i>
                        <span id="savedEmailDisplay"></span>
                        <button type="button" id="clearSavedCredentials" class="float-right text-xs hover:text-red-500" style="background: none; border: none; cursor: pointer; transition: var(--transition-default);">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Error Messages -->
                    @if ($errors->any())
                        <div class="mb-4 p-4 rounded" style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444;">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-circle mr-2" style="color: #ef4444; margin-top: 0.125rem;"></i>
                                <div>
                                    @foreach ($errors->all() as $error)
                                        <p class="text-sm" style="color: var(--text-primary);">{{ $error }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="mb-4 p-4 rounded" style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981;">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2" style="color: #10b981; margin-top: 0.125rem;"></i>
                                <p class="text-sm" style="color: var(--text-primary);">{{ session('status') }}</p>
                            </div>
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="mb-4 p-4 rounded" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6;">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2" style="color: #3b82f6; margin-top: 0.125rem;"></i>
                                <div>
                                    <p class="text-sm" style="color: var(--text-primary);">{{ session('info') }}</p>
                                    @if (session('social_email'))
                                        <p class="text-xs mt-1 opacity-75">Email: {{ session('social_email') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (session('archived_account'))
                        <div class="mb-6 p-5 rounded" style="background: rgba(220, 38, 38, 0.15); border-left: 4px solid #ef4444;">
                            <div class="flex items-start">
                                <i class="fas fa-archive text-2xl mr-3 mt-1" style="color: #ef4444;"></i>
                                <div>
                                    <h3 class="font-bold mb-2" style="color: #ef4444;">Account Archived</h3>
                                    <p class="text-sm mb-3">{{ session('archived_account') }}</p>
                                    <div class="rounded-lg p-3 mt-3" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
                                        <p class="text-xs font-semibold mb-2 flex items-center">
                                            <i class="fas fa-headset mr-2" style="color: #3b82f6;"></i> Need Assistance?
                                        </p>
                                        <div class="space-y-2 text-sm">
                                            @php
                                                $contactEmail = session('contact_email') ?? config('mail.from.address', 'support@hilltop.com');
                                                $contactPhone = session('contact_phone') ?? config('app.support_phone', '+233 123 456 789');
                                                $systemName = session('system_name') ?? config('app.name', 'Hilltop Estate Management');
                                            @endphp
                                            <div class="flex items-center">
                                                <i class="fas fa-envelope w-5" style="color: #60a5fa;"></i>
                                                <span class="ml-2 text-xs">{{ $contactEmail }}</span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-phone-alt w-5" style="color: #4ade80;"></i>
                                                <span class="ml-2 text-xs">{{ $contactPhone }}</span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-building w-5" style="color: #c084fc;"></i>
                                                <span class="ml-2 text-xs">{{ $systemName }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs mt-3" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Your account has been archived because you no longer own any properties.
                                        Please contact support to restore your account or acquire a new property.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ===== Login Form with proper CSRF ===== --}}
                    <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate style="flex: 1; display: flex; flex-direction: column;">
                        @csrf
                        <input type="hidden" id="csrfTokenInput" value="{{ $csrfToken }}">

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
                                autocomplete="username"
                                aria-describedby="loginHint"
                            >
                            <label for="login">
                                <i class="fas fa-envelope mr-2" style="color: var(--label-color);"></i>Email or Phone Number
                            </label>
                        </div>
                        <div id="loginHint" class="text-xs mb-3" style="color: var(--label-color);">
                            <i class="fas fa-info-circle mr-1"></i> Use your email address or phone number (e.g., 0595652410)
                        </div>

                        <div class="floating-label">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="floating-input"
                                placeholder=" "
                                aria-describedby="passwordStrength"
                            >
                            <label for="password">
                                <i class="fas fa-lock mr-2" style="color: var(--label-color);"></i>Password
                            </label>
                            <button type="button" class="password-toggle" id="password-toggle" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="password-strength" id="passwordStrength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div id="passwordStrengthText" class="text-xs mt-1" style="display: none;"></div>

                        <div id="twoFactorSection" style="display: none; margin-top: 1rem;">
                            <div class="floating-label">
                                <input
                                    id="two_factor_code"
                                    type="text"
                                    name="two_factor_code"
                                    class="floating-input"
                                    placeholder=" "
                                    maxlength="6"
                                    autocomplete="off"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                >
                                <label for="two_factor_code">
                                    <i class="fas fa-shield-alt mr-2" style="color: var(--label-color);"></i>Two-Factor Authentication Code
                                </label>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-clock mr-1"></i>
                                    Code expires in <span id="codeTimer">10:00</span>
                                </div>
                                <button type="button" id="resendCodeBtn" class="text-xs font-medium" style="color: var(--secondary); background: none; border: none; cursor: pointer; transition: var(--transition-default);">
                                    <i class="fas fa-redo-alt mr-1"></i>Resend Code
                                </button>
                            </div>
                            <div id="twoFactorError" class="text-xs mt-2 hidden" style="color: #ef4444;"></div>
                        </div>

                        @if(config('auth.captcha_enabled', false))
                        <div class="mb-4 p-3 rounded" style="background: var(--card-bg); border: 1px solid var(--border-light);">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold">Verification</span>
                                <i class="fas fa-sync-alt refresh-captcha" id="refreshCaptcha" style="cursor: pointer; transition: transform 0.3s ease;"></i>
                            </div>
                            <div id="captchaCode" class="text-center py-2 rounded font-mono text-xl tracking-wider" style="background: rgba(0, 0, 0, 0.05); font-weight: bold; letter-spacing: 0.5rem;">ABCD12</div>
                            <input type="text" name="captcha" placeholder="Enter code above" class="floating-input mt-3" style="padding-right: 0.75rem;" required>
                            <input type="hidden" name="captcha_hash" id="captchaHash">
                        </div>
                        @endif

                        <div class="flex items-center justify-between mb-6 mt-2">
                            <div class="remember-checkbox-wrapper">
                                <input
                                    id="remember"
                                    name="remember"
                                    type="checkbox"
                                    value="1"
                                    {{ old('remember') ? 'checked' : (Cookie::get('remember_me') === 'true' ? 'checked' : '') }}
                                >
                                <label for="remember">
                                    <i class="fas fa-memory mr-1"></i> Remember Me
                                </label>
                            </div>

                            <div class="flex items-center gap-3">
                                @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm font-medium transition" style="color: var(--secondary); text-decoration: none;">
                                    <i class="fas fa-question-circle mr-1"></i> Forgot Password?
                                </a>
                                @endif

                                <button type="button" id="biometricLoginBtn" class="biometric-btn" style="display: none; background: none; border: 1px solid var(--border-light); border-radius: 50%; width: 2.5rem; height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition-default);" aria-label="Use biometric login">
                                    <i class="fas fa-fingerprint" style="font-size: 1.25rem;"></i>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full btn-primary text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 mt-auto"
                            id="submitBtn"
                        >
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Sign In</span>
                        </button>

                        @php
                            $hasGoogle = config('services.google.client_id') && config('services.google.client_id') !== null;
                            $hasMicrosoft = config('services.microsoft.client_id') && config('services.microsoft.client_id') !== null;
                            $hasFacebook = config('services.facebook.client_id') && config('services.facebook.client_id') !== null;
                            $hasApple = config('services.apple.client_id') && config('services.apple.client_id') !== null;
                            $hasGithub = config('services.github.client_id') && config('services.github.client_id') !== null;
                            $hasSocialLogin = $hasGoogle || $hasMicrosoft || $hasFacebook || $hasApple || $hasGithub;
                        @endphp

                        @if($hasSocialLogin)
                            <div class="divider my-6">or continue with</div>

                            <div class="social-buttons-wrapper">
                                @if($hasGoogle)
                                <a href="{{ route('login.social', 'google') }}" class="social-icon-btn google" aria-label="Sign in with Google">
                                    <img src="https://www.google.com/favicon.ico" alt="Google" style="width: 22px; height: 22px;">
                                </a>
                                @endif

                                @if($hasMicrosoft)
                                <a href="{{ route('login.social', 'microsoft') }}" class="social-icon-btn microsoft" aria-label="Sign in with Microsoft">
                                    <svg viewBox="0 0 23 23" width="22" height="22" aria-hidden="true">
                                        <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
                                        <rect x="12" y="1" width="10" height="10" fill="#7FBA00"/>
                                        <rect x="1" y="12" width="10" height="10" fill="#00A4EF"/>
                                        <rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
                                    </svg>
                                </a>
                                @endif

                                @if($hasFacebook)
                                <a href="{{ route('login.social', 'facebook') }}" class="social-icon-btn facebook" aria-label="Sign in with Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                @endif

                                @if($hasGithub)
                                <a href="{{ route('login.social', 'github') }}" class="social-icon-btn github" aria-label="Sign in with GitHub">
                                    <i class="fab fa-github"></i>
                                </a>
                                @endif
                            </div>
                        @endif
                    </form>

                    <div class="mt-6 text-center pt-4" style="border-top: 1px solid var(--divider-color);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Don't have an account?
                            <a href="#" class="font-medium transition" style="color: var(--secondary); text-decoration: none;">
                                Contact Administration
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-6">
                <div class="flex justify-center gap-4 mt-2">
                    <a href="#" class="transition" style="color: white; text-decoration: none;" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="transition" style="color: white; text-decoration: none;" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="transition" style="color: white; text-decoration: none;" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="transition" style="color: white; text-decoration: none;" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Biometric Authentication Modal -->
    <div id="biometricModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 1rem; padding: 2rem; text-align: center; max-width: 300px; backdrop-filter: blur(20px);">
            <div style="font-size: 4rem; animation: pulse 2s infinite; color: var(--secondary);">
                <i class="fas fa-fingerprint"></i>
            </div>
            <h3 class="text-lg font-semibold mt-4 mb-2">Biometric Authentication</h3>
            <p class="text-sm mb-4">Place your finger on the sensor or use face recognition</p>
            <button type="button" id="cancelBiometric" class="text-sm" style="color: var(--text-secondary); background: none; border: none; cursor: pointer;">Cancel</button>
        </div>
    </div>

    <!-- Cookie Consent Banner -->
    <div id="cookieConsent" style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: var(--card-bg); backdrop-filter: blur(20px); border-top: 1px solid var(--border-light); padding: 1rem; z-index: 1000; transform: translateY(100%); transition: transform 0.3s ease;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem;">
            <div class="text-sm">
                <i class="fas fa-cookie-bite mr-2"></i>
                We use a persistent cookie to remember your login preference when you check "Remember Me". This cookie expires after 5 years.
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button id="acceptCookies" class="px-4 py-2 rounded-lg text-sm transition" style="background: var(--secondary); color: white; border: none; cursor: pointer;">Got it</button>
                <button id="declineCookies" class="px-4 py-2 rounded-lg text-sm transition" style="background: transparent; border: 1px solid var(--border-light); cursor: pointer; color: var(--text-secondary);">Decline</button>
            </div>
        </div>
    </div>

    <script>
        // ===== FIX 419: Complete JavaScript with CSRF Protection =====
        (function() {
            'use strict';

            const CSRF_CONFIG = {
                token: document.querySelector('meta[name="csrf-token"]')?.content || '',
                sessionId: document.querySelector('meta[name="session-id"]')?.content || '',
                lifetime: parseInt(document.querySelector('meta[name="session-lifetime"]')?.content || '120'),
                refreshEndpoint: '{{ route("csrf.refresh") }}',
                sessionCheckEndpoint: '{{ route("session.check") }}',
                sessionContinueEndpoint: '{{ route("session.continue") }}',
                sessionInvalidateEndpoint: '{{ route("session.invalidate") }}'
            };

            let currentCsrfToken = CSRF_CONFIG.token;
            let csrfRefreshInProgress = false;

            window.refreshCsrfToken = async function() {
                if (csrfRefreshInProgress) return;
                csrfRefreshInProgress = true;

                const messageEl = document.getElementById('csrfErrorMessage');

                if (messageEl) {
                    messageEl.textContent = 'Refreshing security token...';
                }

                try {
                    const response = await fetch(CSRF_CONFIG.refreshEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': currentCsrfToken || CSRF_CONFIG.token,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (data.success) {
                        currentCsrfToken = data.csrf_token;

                        const metaTag = document.querySelector('meta[name="csrf-token"]');
                        if (metaTag) metaTag.content = currentCsrfToken;

                        document.querySelectorAll('form input[name="_token"]').forEach(input => {
                            input.value = currentCsrfToken;
                        });

                        const hiddenInput = document.getElementById('csrfTokenInput');
                        if (hiddenInput) hiddenInput.value = currentCsrfToken;

                        if (window.Laravel) {
                            window.Laravel.csrfToken = currentCsrfToken;
                        }

                        closeCsrfError();

                        showToast('Session refreshed. You can now try again.', 'success');
                        updateCsrfStatus('active');
                    } else {
                        if (messageEl) {
                            messageEl.textContent = data.message || 'Failed to refresh token. Please reload the page.';
                        }
                        showToast('Failed to refresh session. Please reload the page.', 'error');
                    }
                } catch (error) {
                    console.error('CSRF refresh error:', error);
                    if (messageEl) {
                        messageEl.textContent = 'Network error. Please reload the page.';
                    }
                    showToast('Network error. Please reload the page.', 'error');
                } finally {
                    csrfRefreshInProgress = false;
                }
            };

            window.closeCsrfError = function() {
                const banner = document.getElementById('csrfErrorBanner');
                if (banner) {
                    banner.classList.remove('show');
                }
            };

            function showCsrfError(message) {
                const banner = document.getElementById('csrfErrorBanner');
                const messageEl = document.getElementById('csrfErrorMessage');

                if (banner) {
                    banner.classList.add('show');
                }
                if (messageEl) {
                    messageEl.textContent = message || 'Your session has expired. Please refresh the page.';
                }
            }

            function updateCsrfStatus(status) {
                const indicator = document.getElementById('csrfStatusIndicator');
                const textEl = document.getElementById('csrfStatusText');

                if (indicator && textEl) {
                    indicator.style.display = 'block';

                    if (status === 'active') {
                        indicator.style.background = 'rgba(16, 185, 129, 0.1)';
                        textEl.textContent = '✓ CSRF token: active';
                        textEl.style.color = '#10b981';
                    } else {
                        indicator.style.background = 'rgba(239, 68, 68, 0.1)';
                        textEl.textContent = '✗ CSRF token: expired';
                        textEl.style.color = '#ef4444';
                    }
                }
            }

            function handle419Response(response) {
                console.warn('419 Page Expired detected', {
                    url: response.url,
                    status: response.status,
                    statusText: response.statusText
                });

                showCsrfError('Your session has expired. Please refresh the security token.');
                updateCsrfStatus('expired');

                setTimeout(() => {
                    refreshCsrfToken();
                }, 1000);
            }

            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                return originalFetch.apply(this, args)
                    .then(response => {
                        if (response.status === 419) {
                            handle419Response(response);
                        }
                        return response;
                    });
            };

            const originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function() {
                const xhr = this;
                const onreadystatechange = xhr.onreadystatechange;

                xhr.onreadystatechange = function() {
                    if (this.readyState === 4 && this.status === 419) {
                        handle419Response(this);
                    }
                    if (onreadystatechange) {
                        onreadystatechange.apply(this, arguments);
                    }
                };

                return originalOpen.apply(this, arguments);
            };

            document.addEventListener('submit', function(e) {
                const form = e.target;
                if (form && form.tagName === 'FORM') {
                    const tokenInput = form.querySelector('input[name="_token"]');
                    const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;

                    if (tokenInput && metaToken) {
                        if (tokenInput.value !== metaToken) {
                            tokenInput.value = metaToken;
                            console.log('CSRF token updated before form submission');
                        }

                        if (tokenInput.value.length !== 40) {
                            e.preventDefault();
                            showToast('Invalid CSRF token. Refreshing...', 'warning');
                            refreshCsrfToken().then(() => {
                                setTimeout(() => {
                                    form.submit();
                                }, 500);
                            });
                            return;
                        }
                    }
                }
            });

            let sessionCheckInterval = null;

            function checkSessionHealth() {
                if (sessionCheckInterval) {
                    clearInterval(sessionCheckInterval);
                }

                sessionCheckInterval = setInterval(() => {
                    fetch(CSRF_CONFIG.sessionCheckEndpoint, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        if (response.status === 419) {
                            handle419Response(response);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.authenticated) {
                            const indicator = document.getElementById('sessionIndicator');
                            const statusEl = document.getElementById('sessionStatus');
                            if (indicator && statusEl) {
                                indicator.style.display = 'block';
                                statusEl.textContent = `You are already logged in as ${data.user?.email || 'user'}`;
                            }
                        }

                        if (data && data.csrf_token) {
                            const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            if (metaToken && metaToken === data.csrf_token) {
                                updateCsrfStatus('active');
                            }
                        }
                    })
                    .catch(() => {
                        // Silent fail for network errors
                    });
                }, 60000);
            }

            document.addEventListener('DOMContentLoaded', function() {
                const skeletonLoader = document.getElementById('skeletonLoader');
                const loginContent = document.getElementById('loginContent');

                if (skeletonLoader && loginContent) {
                    skeletonLoader.style.display = 'flex';
                    loginContent.style.display = 'none';

                    setTimeout(function() {
                        skeletonLoader.style.display = 'none';
                        loginContent.style.display = 'flex';
                    }, 300);
                }

                checkSessionHealth();
            });

            const form = document.getElementById('loginForm');
            const loginField = document.getElementById('login');
            const passwordField = document.getElementById('password');
            const passwordToggle = document.getElementById('password-toggle');
            const submitBtn = document.getElementById('submitBtn');
            const twoFactorSection = document.getElementById('twoFactorSection');
            const twoFactorCode = document.getElementById('two_factor_code');
            const resendCodeBtn = document.getElementById('resendCodeBtn');
            const twoFactorError = document.getElementById('twoFactorError');
            const rememberCheckbox = document.getElementById('remember');
            const biometricBtn = document.getElementById('biometricLoginBtn');
            const biometricModal = document.getElementById('biometricModal');
            const cancelBiometric = document.getElementById('cancelBiometric');
            const sessionIndicator = document.getElementById('sessionIndicator');
            const continueSessionBtn = document.getElementById('continueSessionBtn');
            const newSessionBtn = document.getElementById('newSessionBtn');

            let codeTimerInterval = null;
            let twoFactorRequired = false;

            function setSecureItem(key, value, persistent = false) {
                const storage = persistent ? localStorage : sessionStorage;
                try {
                    const encoded = btoa(encodeURIComponent(value));
                    storage.setItem(key, encoded);
                } catch(e) {
                    console.error('Storage error:', e);
                }
            }

            function getSecureItem(key, persistent = false) {
                const storage = persistent ? localStorage : sessionStorage;
                try {
                    const encoded = storage.getItem(key);
                    if (!encoded) return null;
                    return decodeURIComponent(atob(encoded));
                } catch(e) {
                    return null;
                }
            }

            function removeSecureItem(key, persistent = false) {
                const storage = persistent ? localStorage : sessionStorage;
                storage.removeItem(key);
            }

            function setRememberMeCookie(value) {
                if (value) {
                    localStorage.setItem('remember_me_preference', 'true');
                    const expiryDate = new Date();
                    expiryDate.setFullYear(expiryDate.getFullYear() + 5);
                    document.cookie = `remember_me_client=true; expires=${expiryDate.toUTCString()}; path=/; SameSite=Strict; Secure`;
                    setSecureItem('saved_login', loginField?.value || '', true);
                    setSecureItem('saved_password', passwordField?.value || '', true);
                } else {
                    localStorage.removeItem('remember_me_preference');
                    document.cookie = `remember_me_client=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
                    removeSecureItem('saved_login', true);
                    removeSecureItem('saved_password', true);
                }
            }

            function loadSavedCredentials() {
                const rememberMePreference = localStorage.getItem('remember_me_preference') === 'true';

                if (rememberMePreference && rememberCheckbox) {
                    rememberCheckbox.checked = true;

                    const savedLogin = getSecureItem('saved_login', true);
                    const savedPassword = getSecureItem('saved_password', true);

                    if (savedLogin && loginField && !loginField.value) {
                        loginField.value = savedLogin;
                    }
                    if (savedPassword && passwordField && !passwordField.value) {
                        passwordField.value = savedPassword;
                        if (typeof checkPasswordStrength === 'function') {
                            checkPasswordStrength(savedPassword);
                        }
                    }

                    const savedIndicator = document.getElementById('savedCredentialsIndicator');
                    const savedEmailDisplay = document.getElementById('savedEmailDisplay');
                    if (savedLogin && savedIndicator && savedEmailDisplay) {
                        let displayLogin = savedLogin;
                        if (savedLogin.includes('@')) {
                            const [local, domain] = savedLogin.split('@');
                            displayLogin = local.substring(0, 2) + '***@' + domain;
                        } else if (savedLogin.length > 6) {
                            displayLogin = savedLogin.substring(0, 3) + '***' + savedLogin.substring(savedLogin.length - 3);
                        }
                        savedEmailDisplay.textContent = `Saved: ${displayLogin}`;
                        savedIndicator.style.display = 'block';
                    }
                }
            }

            loadSavedCredentials();

            if (rememberCheckbox) {
                rememberCheckbox.addEventListener('change', function(e) {
                    if (this.checked) {
                        const cookieAccepted = localStorage.getItem('cookie_consent_accepted');
                        if (!cookieAccepted) {
                            const cookieConsent = document.getElementById('cookieConsent');
                            if (cookieConsent) cookieConsent.style.display = 'block';
                            setTimeout(() => {
                                if (cookieConsent) cookieConsent.style.transform = 'translateY(0)';
                            }, 10);
                        }
                    }
                });
            }

            const acceptCookies = document.getElementById('acceptCookies');
            const declineCookies = document.getElementById('declineCookies');
            const cookieConsent = document.getElementById('cookieConsent');

            if (acceptCookies) {
                acceptCookies.addEventListener('click', function() {
                    localStorage.setItem('cookie_consent_accepted', 'true');
                    if (cookieConsent) {
                        cookieConsent.style.transform = 'translateY(100%)';
                        setTimeout(() => {
                            cookieConsent.style.display = 'none';
                        }, 300);
                    }
                    showToast('Cookie preferences saved', 'success');

                    if (rememberCheckbox && rememberCheckbox.checked && loginField && passwordField) {
                        setRememberMeCookie(true);
                    }
                });
            }

            if (declineCookies) {
                declineCookies.addEventListener('click', function() {
                    localStorage.setItem('cookie_consent_accepted', 'false');
                    if (cookieConsent) {
                        cookieConsent.style.transform = 'translateY(100%)';
                        setTimeout(() => {
                            cookieConsent.style.display = 'none';
                        }, 300);
                    }

                    if (rememberCheckbox) {
                        rememberCheckbox.checked = false;
                        setRememberMeCookie(false);
                    }

                    showToast('You can still login, but we won\'t remember you', 'info');
                });
            }

            if (localStorage.getItem('cookie_consent_accepted') === null && cookieConsent) {
                setTimeout(() => {
                    cookieConsent.style.display = 'block';
                    setTimeout(() => {
                        cookieConsent.style.transform = 'translateY(0)';
                    }, 10);
                }, 1000);
            }

            window.checkPasswordStrength = function(password) {
                const strengthBar = document.getElementById('passwordStrengthBar');
                const strengthText = document.getElementById('passwordStrengthText');

                if (!strengthBar || !strengthText) return;

                if (!password) {
                    strengthBar.style.width = '0%';
                    strengthText.style.display = 'none';
                    return;
                }

                let strength = 0;
                let message = '';
                let color = '';

                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;

                if (strength <= 2) {
                    message = 'Weak password';
                    color = 'var(--password-strength-weak)';
                    strengthBar.style.width = '33%';
                } else if (strength <= 4) {
                    message = 'Medium password';
                    color = 'var(--password-strength-medium)';
                    strengthBar.style.width = '66%';
                } else {
                    message = 'Strong password';
                    color = 'var(--password-strength-strong)';
                    strengthBar.style.width = '100%';
                }

                strengthBar.style.backgroundColor = color;
                strengthText.textContent = message;
                strengthText.style.display = 'block';
                strengthText.style.color = color;
            };

            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                });
            }

            if (passwordToggle && passwordField) {
                passwordToggle.addEventListener('click', function() {
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-eye');
                        icon.classList.toggle('fa-eye-slash');
                    }
                    this.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
                });
            }

            window.handleTwoFactorRequired = function(userId) {
                twoFactorRequired = true;
                if (twoFactorSection) twoFactorSection.style.display = 'block';
                if (passwordField) passwordField.disabled = true;
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-shield-alt"></i><span>Verify Code</span>';
                }
                if (twoFactorCode) twoFactorCode.focus();
                startCodeTimer(600);
                if (sessionStorage) sessionStorage.setItem('2fa_user_id', userId);
                showToast('Two-factor authentication required. Please enter your verification code.', 'info');
            };

            function startCodeTimer(seconds) {
                if (codeTimerInterval) clearInterval(codeTimerInterval);

                const timerElement = document.getElementById('codeTimer');
                if (!timerElement) return;

                let remaining = seconds;

                function updateTimer() {
                    const minutes = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

                    if (remaining <= 0 && codeTimerInterval) {
                        clearInterval(codeTimerInterval);
                        timerElement.textContent = 'Expired';
                        if (resendCodeBtn) resendCodeBtn.disabled = false;
                    }
                }

                updateTimer();
                codeTimerInterval = setInterval(() => {
                    remaining--;
                    updateTimer();
                    if (remaining <= 0 && codeTimerInterval) {
                        clearInterval(codeTimerInterval);
                        codeTimerInterval = null;
                    }
                }, 1000);
            }

            async function resendTwoFactorCode() {
                const userId = sessionStorage?.getItem('2fa_user_id');
                if (!userId || !resendCodeBtn) return;

                resendCodeBtn.disabled = true;
                const originalHtml = resendCodeBtn.innerHTML;
                resendCodeBtn.innerHTML = '<i class="fas fa-spinner"></i><span>Sending...</span>';

                try {
                    const response = await fetch('{{ route("2fa.resend") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ user_id: userId })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        showToast('Verification code resent successfully!', 'success');
                        if (codeTimerInterval) clearInterval(codeTimerInterval);
                        startCodeTimer(600);
                        setTimeout(() => {
                            if (resendCodeBtn) resendCodeBtn.disabled = false;
                        }, 60000);
                    } else {
                        showToast(data.error || 'Failed to resend code. Please try again.', 'error');
                        resendCodeBtn.disabled = false;
                    }
                } catch (error) {
                    showToast('Network error. Please try again.', 'error');
                    resendCodeBtn.disabled = false;
                } finally {
                    if (resendCodeBtn) resendCodeBtn.innerHTML = originalHtml;
                }
            }

            if (resendCodeBtn) {
                resendCodeBtn.addEventListener('click', resendTwoFactorCode);
            }

            @if(config('auth.captcha_enabled', false))
            function generateCaptcha() {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
                let captcha = '';
                for (let i = 0; i < 6; i++) {
                    captcha += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                const captchaCodeEl = document.getElementById('captchaCode');
                const captchaHashEl = document.getElementById('captchaHash');
                if (captchaCodeEl) captchaCodeEl.textContent = captcha;
                if (captchaHashEl) captchaHashEl.value = btoa(captcha);
            }

            const refreshBtn = document.getElementById('refreshCaptcha');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', generateCaptcha);
            }
            generateCaptcha();
            @endif

            if (biometricBtn && window.PublicKeyCredential && window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
                window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then(available => {
                    if (available && localStorage.getItem('biometric_enabled') === 'true') {
                        biometricBtn.style.display = 'inline-flex';
                    }
                });
            }

            if (biometricBtn) {
                biometricBtn.addEventListener('click', async function() {
                    if (biometricModal) biometricModal.style.display = 'flex';

                    try {
                        setTimeout(() => {
                            if (biometricModal) biometricModal.style.display = 'none';

                            const savedLogin = getSecureItem('saved_login', true);
                            const savedPassword = getSecureItem('saved_password', true);

                            if (savedLogin && loginField && savedPassword && passwordField) {
                                loginField.value = savedLogin;
                                passwordField.value = savedPassword;
                                if (form) form.submit();
                            } else {
                                showToast('No saved credentials found. Please login manually first.', 'warning');
                            }
                        }, 2000);
                    } catch (error) {
                        console.error('Biometric auth failed:', error);
                        if (biometricModal) biometricModal.style.display = 'none';
                        showToast('Biometric authentication failed. Please use password.', 'error');
                    }
                });
            }

            if (cancelBiometric) {
                cancelBiometric.addEventListener('click', function() {
                    if (biometricModal) biometricModal.style.display = 'none';
                });
            }

            window.showToast = function(message, type = 'info') {
                const existingToasts = document.querySelectorAll('.toast-notification');
                existingToasts.forEach(toast => toast.remove());

                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;

                let icon = '';
                switch(type) {
                    case 'success': icon = 'fa-check-circle'; break;
                    case 'error': icon = 'fa-exclamation-circle'; break;
                    case 'warning': icon = 'fa-exclamation-triangle'; break;
                    default: icon = 'fa-info-circle';
                }

                toast.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHtml(message)}</span>`;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            };

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function checkExistingSession() {
                fetch(CSRF_CONFIG.sessionCheckEndpoint, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.authenticated && sessionIndicator) {
                        sessionIndicator.style.display = 'block';
                        const sessionStatus = document.getElementById('sessionStatus');
                        if (sessionStatus) {
                            sessionStatus.textContent = `You are already logged in as ${data.user?.email || 'user'}`;
                        }
                    }
                })
                .catch(() => {});
            }

            if (continueSessionBtn) {
                continueSessionBtn.addEventListener('click', function() {
                    window.location.href = CSRF_CONFIG.sessionContinueEndpoint;
                });
            }

            if (newSessionBtn) {
                newSessionBtn.addEventListener('click', function() {
                    fetch(CSRF_CONFIG.sessionInvalidateEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(() => {
                        if (sessionIndicator) sessionIndicator.style.display = 'none';
                        showToast('Previous session cleared. You can now login again.', 'info');
                    })
                    .catch(() => {});
                });
            }

            checkExistingSession();

            if (loginField) {
                loginField.addEventListener('input', function(e) {
                    const value = e.target.value.trim();
                    const hintEl = document.getElementById('loginHint');

                    if (hintEl) {
                        if (value.includes('@')) {
                            hintEl.innerHTML = '<i class="fas fa-envelope mr-1"></i> Signing in with email address';
                        } else if (/^\d+$/.test(value) && value.length > 5) {
                            hintEl.innerHTML = '<i class="fas fa-phone-alt mr-1"></i> Signing in with phone number';
                        } else {
                            hintEl.innerHTML = '<i class="fas fa-info-circle mr-1"></i> Use your email address or phone number (e.g., 0595652410)';
                        }
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;

                    if (!loginField?.value.trim()) {
                        loginField?.classList.add('error');
                        valid = false;
                        setTimeout(() => loginField?.classList.remove('error'), 500);
                        showToast('Please enter your email or phone number', 'error');
                    }

                    if (!twoFactorRequired && !passwordField?.value) {
                        passwordField?.classList.add('error');
                        valid = false;
                        setTimeout(() => passwordField?.classList.remove('error'), 500);
                        showToast('Please enter your password', 'error');
                    }

                    if (twoFactorRequired && twoFactorCode && !twoFactorCode.value) {
                        twoFactorCode.classList.add('error');
                        valid = false;
                        setTimeout(() => twoFactorCode.classList.remove('error'), 500);
                        showToast('Please enter your 2FA code', 'error');
                    }

                    if (!valid) {
                        e.preventDefault();
                        return;
                    }

                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('loading');
                    }

                    form.classList.add('form-loading');

                    const remember = rememberCheckbox ? rememberCheckbox.checked : false;
                    if (remember && loginField?.value && passwordField?.value) {
                        setSecureItem('saved_login', loginField.value, true);
                        setSecureItem('saved_password', passwordField.value, true);
                        localStorage.setItem('remember_me_preference', 'true');
                    } else if (!remember) {
                        removeSecureItem('saved_login', true);
                        removeSecureItem('saved_password', true);
                        localStorage.removeItem('remember_me_preference');
                    }
                });
            }

            if (loginField && !loginField.value) {
                loginField.focus();
            } else if (passwordField && !passwordField.value) {
                passwordField.focus();
            }

            const clearSavedBtn = document.getElementById('clearSavedCredentials');
            if (clearSavedBtn) {
                clearSavedBtn.addEventListener('click', function() {
                    removeSecureItem('saved_login', true);
                    removeSecureItem('saved_password', true);
                    localStorage.removeItem('remember_me_preference');
                    setRememberMeCookie(false);
                    const savedIndicator = document.getElementById('savedCredentialsIndicator');
                    if (savedIndicator) savedIndicator.style.display = 'none';
                    if (rememberCheckbox) rememberCheckbox.checked = false;
                    showToast('Saved credentials cleared', 'success');
                });
            }

            const socialBtns = document.querySelectorAll('.social-icon-btn');
            socialBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner"></i>';
                    this.style.opacity = '0.7';
                });
            });

            const styleSheet = document.createElement('style');
            styleSheet.textContent = `
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
                @keyframes slideIn {
                    from { opacity: 0; transform: translateX(-20px); }
                    to { opacity: 1; transform: translateX(0); }
                }
            `;
            document.head.appendChild(styleSheet);

            console.log('Login page loaded with CSRF protection', {
                csrfToken: currentCsrfToken ? 'Present' : 'Missing',
                sessionId: CSRF_CONFIG.sessionId,
                sessionLifetime: CSRF_CONFIG.lifetime
            });

        })();
    </script>
</body>
</html>