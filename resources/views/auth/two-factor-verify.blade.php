{{-- resources/views/auth/two-factor-verify.blade.php --}}
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

    // Method + trust-device flag from the controller.
    $verificationMethod = $method ?? 'email';
    $trustDeviceEnabled = (bool) ($trust_device_enabled ?? config('auth.two_factor.trust_device', true));
    $remaining          = $remaining_attempts ?? null;

    /*
     | ✅ NEW: delivery-failed flag from the controller.
     |
     | Set by TwoFactorController::resendCode() when the service
     | reports the code could not be delivered (e.g. SMS provider
     | unreachable, or email transport failed). Surfaces a visible
     | banner so the user isn't left wondering why no code arrived.
     */
    $deliveryFailed = (bool) ($delivery_failed ?? false);
    $failureMessage = session('2fa_delivery_error')
        ?? ($verificationMethod === 'sms'
            ? 'We could not send your code via SMS. Please try again, or contact support if the problem persists.'
            : 'We could not send your code via email. Please try again, or contact support if the problem persists.');

    // Human-readable description of where the code was sent.
    $methodDescription = match ($verificationMethod) {
        'sms'   => 'We sent a 6-digit code to your registered phone number.',
        default => 'We sent a 6-digit code to your registered email address.',
    };

    // If delivery failed, override the subtitle with the failure reason.
    if ($deliveryFailed) {
        $methodDescription = match ($verificationMethod) {
            'sms'   => 'We tried to send a 6-digit code to your registered phone number.',
            default => 'We tried to send a 6-digit code to your registered email address.',
        };
    }

    /*
     | ✅ FAVICON + SETTINGS — same resolution logic as the login blade
     */
    try {
        $systemSettings = $systemSettings
            ?? \Illuminate\Support\Facades\Cache::remember(
                'system_settings_login_blade',
                now()->addMinutes(30),
                fn () => \App\Models\SystemSetting::getSettings()
            );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('2FA blade settings resolution failed: ' . $e->getMessage());
        $systemSettings = null;
    }

    $faviconUrl     = null;
    $faviconMime    = 'image/x-icon';
    $faviconVersion = null;

    try {
        if ($systemSettings && method_exists($systemSettings, 'hasFavicon') && $systemSettings->hasFavicon()) {
            $candidate = null;
            if (method_exists($systemSettings, 'getFaviconUrl')) {
                $candidate = $systemSettings->getFaviconUrl();
            }

            if (empty($candidate) && !empty($systemSettings->system_favicon)) {
                try {
                    $candidate = \Illuminate\Support\Facades\Storage::disk('public')
                        ->url($systemSettings->system_favicon);
                } catch (\Throwable $e) { }

                if (!empty($candidate) && !preg_match('~^https?://~i', $candidate)) {
                    $candidate = url($candidate);
                }
            }

            if (empty($candidate) && !empty($systemSettings->system_favicon)) {
                $candidate = asset('storage/' . ltrim($systemSettings->system_favicon, '/'));
            }

            if (!empty($candidate)) {
                $faviconUrl = $candidate;

                $ext = strtolower(pathinfo(parse_url($faviconUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                $faviconMime = match ($ext) {
                    'png'         => 'image/png',
                    'svg'         => 'image/svg+xml',
                    'gif'         => 'image/gif',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp'        => 'image/webp',
                    'ico'         => 'image/x-icon',
                    default       => 'image/x-icon',
                };

                $faviconVersion = $systemSettings->updated_at?->timestamp ?? time();
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('2FA blade favicon resolution failed: ' . $e->getMessage());
        $faviconUrl = null;
    }

    $faviconFallback = asset('favicon.ico');

    $systemName = $systemSettings->system_name
        ?? config('app.name', 'Hilltop Estate Management');

    // Whether the user has an active error on the `code` field.
    $hasCodeError = $errors->has('code');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    {{-- ===== CRITICAL CSRF AND SESSION META TAGS ===== --}}
    <meta name="csrf-token" content="{{ $csrfToken }}">
    <meta name="session-id" content="{{ $sessionId }}">
    <meta name="session-status" content="{{ session()->isStarted() ? 'active' : 'inactive' }}">
    <meta name="session-lifetime" content="{{ config('session.lifetime', 120) }}">

    <meta name="description" content="Two-factor authentication for {{ $systemName }}">
    <meta name="color-scheme" content="dark light">
    <meta name="robots" content="noindex, nofollow">
    <title>Two-Factor Authentication | {{ $systemName }}</title>

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
        <meta name="msapplication-TileImage" content="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @else
        <link rel="icon" type="image/x-icon" href="{{ $faviconFallback }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ $faviconFallback }}">
        <link rel="apple-touch-icon" href="{{ $faviconFallback }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @endif

    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    {{-- ===== Inline CSRF token for JavaScript ===== --}}
    <script>
        window.Laravel = {
            csrfToken: '{{ $csrfToken }}',
            sessionId: '{{ $sessionId }}',
            sessionLifetime: {{ config('session.lifetime', 120) }},
            routes: {
                login: '{{ route("login") }}',
                resend: '{{ route("2fa.resend") }}',
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
            } catch(e) { }

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);

            const style = document.createElement('style');
            style.textContent = `
                html.dark { background-color: #0a0f1c !important; }
                html:not(.dark) { background-color: #f8fafc !important; }
                body { visibility: visible !important; }
            `;
            document.head.appendChild(style);
        })();
    </script>

    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
         | CSS Custom Properties — identical tokens to the login blade
         * ============================================================ */
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
            --focus-ring: rgba(59, 130, 246, 0.5);
            --transition-default: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
            --focus-ring: rgba(96, 165, 250, 0.5);
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
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 58, 138, 0.8) 100%);
            z-index: 0;
            pointer-events: none;
        }

        html.dark body::before {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.9) 0%, rgba(15, 23, 42, 0.85) 100%);
        }

        .bg-pattern {
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.3;
            z-index: 0;
            pointer-events: none;
        }

        .content-container {
            z-index: 10;
            position: relative;
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* ============================================================
         | GLASS CARD — same treatment as the login blade
         * ============================================================ */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border-radius: 1.5rem;
            padding: 2rem;
            transition: var(--transition-default);
            width: 100%;
        }

        .glass-card:hover {
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.3);
        }

        /* ============================================================
         | HEADER ICON — 2FA shield badge above the title
         * ============================================================ */
        .shield-badge {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            color: white;
            font-size: 1.75rem;
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.4);
        }

        /* Danger variant when delivery failed — subtle red tint */
        .shield-badge.delivery-failed {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.4);
        }

        /* ============================================================
         | FLOATING LABEL INPUTS — same pattern as the login blade
         * ============================================================ */
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
            border-radius: 0;
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

        /* ============================================================
         | THE 6-DIGIT CODE INPUT — visually emphasized
         * ============================================================ */
        .code-input {
            font-size: 1.75rem !important;
            font-weight: 600 !important;
            letter-spacing: 0.75rem !important;
            text-align: center !important;
            font-family: 'SF Mono', 'Monaco', 'Consolas', 'Courier New', monospace !important;
            padding: 1rem 0.75rem !important;
            min-height: 68px !important;
            padding-right: 0.75rem !important;
        }

        .code-input::placeholder {
            letter-spacing: 0.5rem;
            color: var(--border-light);
        }

        /* ============================================================
         | PRIMARY BUTTON — gradient + ripple, matches login
         * ============================================================ */
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            transition: var(--transition-default);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            border: none;
            font-weight: 600;
            min-height: 48px;
            color: white;
            width: 100%;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
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

        /* ============================================================
         | SECONDARY TEXT BUTTON — resend link
         * ============================================================ */
        .btn-link {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            color: var(--secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition-default);
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .btn-link:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.08);
        }

        .btn-link:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }

        .btn-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ============================================================
         | REMEMBER-STYLE CHECKBOX
         * ============================================================ */
        .checkbox-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            cursor: pointer;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 0.75rem;
            transition: var(--transition-default);
            background: var(--input-bg);
        }

        .checkbox-wrapper:hover {
            border-color: var(--secondary);
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 1.125rem;
            height: 1.125rem;
            cursor: pointer;
            accent-color: var(--secondary);
            margin: 0;
            flex-shrink: 0;
            margin-top: 0.125rem;
        }

        .checkbox-wrapper label {
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.875rem;
            user-select: none;
            line-height: 1.4;
        }

        .checkbox-wrapper label strong {
            color: var(--text-primary);
            display: block;
            font-weight: 600;
            margin-bottom: 0.125rem;
        }

        /* ============================================================
         | ALERT BOXES — same tokens as login's error/status blocks
         * ============================================================ */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
        }

        .alert-danger i {
            color: #ef4444;
            margin-top: 0.125rem;
            flex-shrink: 0;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
        }

        .alert-warning i {
            color: #f59e0b;
            margin-top: 0.125rem;
            flex-shrink: 0;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.08);
            border-left: 4px solid #3b82f6;
        }

        .alert-info i {
            color: #3b82f6;
            margin-top: 0.125rem;
            flex-shrink: 0;
        }

        .alert p {
            margin: 0;
            color: var(--text-primary);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .alert p + p {
            margin-top: 0.5rem;
        }

        .alert a {
            color: var(--secondary);
            font-weight: 600;
            text-decoration: underline;
        }

        /* ============================================================
         | ATTEMPT PILL — bottom of the form
         * ============================================================ */
        .attempt-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: rgba(59, 130, 246, 0.1);
            color: var(--secondary);
        }

        .attempt-pill.low {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
        }

        html.dark .attempt-pill.low {
            color: #fbbf24;
        }

        .attempt-pill.critical {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
        }

        html.dark .attempt-pill.critical {
            color: #f87171;
        }

        /* ============================================================
         | TOAST NOTIFICATIONS
         * ============================================================ */
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
            color: var(--text-primary);
        }

        /* ============================================================
         | CSRF ERROR BANNER
         * ============================================================ */
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

        /* ============================================================
         | LOADING STATE
         * ============================================================ */
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

        /* ============================================================
         | TEXT LINK
         * ============================================================ */
        .text-link {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition-default);
        }

        .text-link:hover {
            text-decoration: underline;
        }

        .text-link:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
            border-radius: 0.25rem;
        }

        /* ============================================================
         | ANIMATIONS
         * ============================================================ */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        /* ============================================================
         | FOCUS RING
         * ============================================================ */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }

        /* ============================================================
         | RESPONSIVE
         * ============================================================ */
        @media (max-width: 640px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 1.5rem;
            }
            .content-container {
                padding: 0.5rem;
            }
            .glass-card {
                padding: 1.5rem;
                border-radius: 1.25rem;
            }
            .code-input {
                font-size: 1.5rem !important;
                letter-spacing: 0.5rem !important;
            }
            .shield-badge {
                width: 3.25rem;
                height: 3.25rem;
                font-size: 1.5rem;
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
                padding: 1.25rem;
            }
            .code-input {
                font-size: 1.35rem !important;
                letter-spacing: 0.4rem !important;
                min-height: 58px !important;
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
    </style>
</head>
<body>
    <noscript>
        <div style="position: fixed; top: 0; left: 0; right: 0; background: #dc2626; color: white; padding: 1rem; text-align: center; z-index: 9999;">
            <strong>JavaScript Required:</strong> Please enable JavaScript to complete two-factor authentication.
        </div>
    </noscript>

    {{-- ===== CSRF Error Banner ===== --}}
    <div id="csrfErrorBanner" class="csrf-error-banner">
        <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
        <span id="csrfErrorMessage">Your session has expired or the security token is invalid.</span>
        <a onclick="refreshCsrfToken()" style="margin-left: 0.5rem;">Refresh Now</a>
        <button class="close-btn" onclick="closeCsrfError()" aria-label="Close error banner">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="bg-pattern"></div>

    <div class="content-container">
        <div class="glass-card">

            {{-- ============ HEADER ============ --}}
            <div class="shield-badge {{ $deliveryFailed ? 'delivery-failed' : '' }}" aria-hidden="true">
                <i class="fas {{ $deliveryFailed ? 'fa-exclamation-triangle' : 'fa-shield-alt' }}"></i>
            </div>

            <h1 style="font-size: 1.5rem; font-weight: 600; text-align: center; margin: 0 0 0.5rem; color: var(--text-primary);">
                {{ $deliveryFailed ? 'Code Delivery Failed' : 'Two-Factor Authentication' }}
            </h1>
            <p style="font-size: 0.875rem; text-align: center; margin: 0 0 1.5rem; color: var(--text-secondary);">
                {{ $methodDescription }}
            </p>

            {{-- ============ DELIVERY-FAILED ALERT ============ --}}
            @if($deliveryFailed)
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <p>{{ $failureMessage }}</p>
                        <p style="font-size: 0.8125rem; opacity: 0.85;">
                            You can try the
                            <a href="#" onclick="document.getElementById('resendCodeBtn')?.click(); return false;">
                                Resend code
                            </a>
                            button below, or
                            <a href="{{ route('login') }}">return to sign in</a>.
                        </p>
                    </div>
                </div>
            @endif

            {{-- ============ METHOD BADGE ============ --}}
            @if(!$deliveryFailed)
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <span class="attempt-pill">
                        @if($verificationMethod === 'sms')
                            <i class="fas fa-sms"></i>
                            <span>Code sent via SMS</span>
                        @else
                            <i class="fas fa-envelope"></i>
                            <span>Code sent via email</span>
                        @endif
                    </span>
                </div>
            @endif

            {{-- ============ ERRORS ============ --}}
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ============ SESSION STATUS ============ --}}
            @if(session('status'))
                <div class="alert alert-info" role="status">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p>{{ session('status') }}</p>
                    </div>
                </div>
            @endif

            {{-- ============ THE FORM ============ --}}
            <form method="POST" action="{{ route('2fa.verify.submit') }}" id="twoFactorForm" novalidate>
                @csrf
                <input type="hidden" id="csrfTokenInput" value="{{ $csrfToken }}">

                <div class="floating-label">
                    <input
                        type="text"
                        id="code"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        required
                        autofocus
                        placeholder=" "
                        class="floating-input code-input @error('code') error @enderror"
                        aria-describedby="codeHint"
                        aria-invalid="{{ $hasCodeError ? 'true' : 'false' }}"
                        style="padding-right: 0.75rem;"
                    >
                    <label for="code" style="left: 50%; transform: translateX(-50%); pointer-events: none;">
                        <span style="display: none;">Verification code</span>
                    </label>
                </div>

                <p id="codeHint" style="font-size: 0.75rem; text-align: center; margin: -0.75rem 0 1.25rem; color: var(--label-color);">
                    <i class="fas fa-info-circle" style="margin-right: 0.25rem;"></i>
                    Enter the 6-digit code from your {{ $verificationMethod === 'sms' ? 'SMS' : 'email' }}
                </p>

                @if($trustDeviceEnabled)
                    <label class="checkbox-wrapper" for="trust_device">
                        <input
                            type="checkbox"
                            id="trust_device"
                            name="trust_device"
                            value="1"
                            {{ old('trust_device') ? 'checked' : '' }}
                        >
                        <span>
                            <strong>Trust this device for 30 days</strong>
                            You won't be asked for a code on this browser for the next 30 days.
                            Only use this on a personal device.
                        </span>
                    </label>
                @endif

                <button
                    type="submit"
                    class="btn-primary"
                    id="submitBtn"
                    style="margin-top: 1.25rem;"
                >
                    <i class="fas fa-shield-alt"></i>
                    <span>Verify &amp; Sign In</span>
                </button>
            </form>

            {{-- ============ RESEND + ATTEMPT INFO ============ --}}
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--divider-color); flex-wrap: wrap;">
                <div>
                    @if($remaining !== null)
                        @php
                            $pillClass = '';
                            if ($remaining <= 1) $pillClass = 'critical';
                            elseif ($remaining <= 3) $pillClass = 'low';
                        @endphp
                        <span class="attempt-pill {{ $pillClass }}">
                            <i class="fas fa-redo-alt"></i>
                            <span>
                                {{ $remaining }}
                                {{ \Illuminate\Support\Str::plural('attempt', $remaining) }}
                                remaining
                            </span>
                        </span>
                    @endif
                </div>

                <button
                    type="button"
                    id="resendCodeBtn"
                    class="btn-link"
                    aria-label="Resend verification code"
                    autofocus="{{ $deliveryFailed ? 'autofocus' : 'false' }}"
                >
                    <i class="fas fa-paper-plane"></i>
                    <span>{{ $deliveryFailed ? 'Try resending the code' : 'Resend code' }}</span>
                </button>
            </div>

            {{-- ============ BACK TO LOGIN ============ --}}
            <div style="text-align: center; margin-top: 1.25rem;">
                <a href="{{ route('login') }}" class="text-link" style="font-size: 0.875rem;">
                    <i class="fas fa-arrow-left" style="margin-right: 0.25rem;"></i>
                    Back to sign in
                </a>
            </div>
        </div>

        {{-- ============ SUPPORT FOOTER ============ --}}
        <p style="text-align: center; font-size: 0.75rem; color: rgba(255, 255, 255, 0.75); margin-top: 1.5rem; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);">
            <i class="fas fa-lock" style="margin-right: 0.25rem;"></i>
            Protected by two-factor authentication
        </p>
    </div>

    <script>
        // ============================================================
        // CSRF protection + session health — mirrors the login blade
        // ============================================================
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

                        if (window.Laravel) window.Laravel.csrfToken = currentCsrfToken;

                        closeCsrfError();
                        showToast('Session refreshed. You can now try again.', 'success');
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
                if (banner) banner.classList.remove('show');
            };

            function showCsrfError(message) {
                const banner = document.getElementById('csrfErrorBanner');
                const messageEl = document.getElementById('csrfErrorMessage');
                if (banner) banner.classList.add('show');
                if (messageEl) {
                    messageEl.textContent = message || 'Your session has expired. Please refresh the page.';
                }
            }

            function handle419Response(response) {
                console.warn('419 Page Expired detected', {
                    url: response.url,
                    status: response.status,
                    statusText: response.statusText
                });
                showCsrfError('Your session has expired. Please refresh the security token.');
                setTimeout(() => refreshCsrfToken(), 1000);
            }

            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                return originalFetch.apply(this, args).then(response => {
                    if (response.status === 419) handle419Response(response);
                    return response;
                });
            };

            const originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function() {
                const xhr = this;
                const onreadystatechange = xhr.onreadystatechange;
                xhr.onreadystatechange = function() {
                    if (this.readyState === 4 && this.status === 419) handle419Response(this);
                    if (onreadystatechange) onreadystatechange.apply(this, arguments);
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
                        }
                        if (tokenInput.value.length !== 40) {
                            e.preventDefault();
                            showToast('Invalid CSRF token. Refreshing...', 'warning');
                            refreshCsrfToken().then(() => {
                                setTimeout(() => form.submit(), 500);
                            });
                        }
                    }
                }
            });

            window.showToast = function(message, type = 'info') {
                document.querySelectorAll('.toast-notification').forEach(t => t.remove());

                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;

                let icon = 'fa-info-circle';
                let borderColor = '#3b82f6';

                switch (type) {
                    case 'success':
                        icon = 'fa-check-circle';
                        borderColor = '#10b981';
                        break;
                    case 'error':
                        icon = 'fa-exclamation-circle';
                        borderColor = '#ef4444';
                        break;
                    case 'warning':
                        icon = 'fa-exclamation-triangle';
                        borderColor = '#f59e0b';
                        break;
                }

                toast.style.borderLeftColor = borderColor;
                toast.innerHTML = `<i class="fas ${icon}" style="color: ${borderColor};"></i><span>${escapeHtml(message)}</span>`;
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

            // ============================================================
            // 6-digit code input — auto-format + AUTO-SUBMIT on completion
            // ------------------------------------------------------------
            // When the field reaches exactly 6 digits, we schedule a form
            // submission after a short grace window (180ms). If the user
            // keeps typing or deletes a digit during that window, the
            // timer is cleared and auto-submit is cancelled.
            //
            // Why a grace window?
            //   - On mobile, a fast typist can briefly hit 6 digits while
            //     correcting a mistake (e.g., typed 6 then backspaces to
            //     5 and retypes). The window gives them time to fix it.
            //   - requestSubmit() fires the submit event so our existing
            //     submit handler (validation + button-disable) still runs.
            // ============================================================
            const form = document.getElementById('twoFactorForm');
            const codeInput = document.getElementById('code');
            const submitBtn = document.getElementById('submitBtn');
            const resendBtn = document.getElementById('resendCodeBtn');

            function scheduleAutoSubmit() {
                if (!form) return;

                clearTimeout(codeInput._autoSubmitTimer);

                codeInput._autoSubmitTimer = setTimeout(() => {
                    const value = codeInput.value || '';
                    if (value.length !== 6) return;
                    if (submitBtn && submitBtn.disabled) return;

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }, 180);
            }

            function cancelAutoSubmit() {
                if (codeInput && codeInput._autoSubmitTimer) {
                    clearTimeout(codeInput._autoSubmitTimer);
                    codeInput._autoSubmitTimer = null;
                }
            }

            if (codeInput) {
                codeInput.addEventListener('input', function() {
                    // Strip non-digits and cap at 6
                    this.value = this.value.replace(/\D/g, '').slice(0, 6);

                    cancelAutoSubmit();

                    if (this.value.length === 6 && form) {
                        scheduleAutoSubmit();
                    }
                });

                // Cancel any pending auto-submit if the user interacts again.
                // Covers: backspace, arrow keys, tab, mouse click into field,
                // and any keydown that isn't part of the code entry itself.
                codeInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' || e.key === 'Delete' ||
                        e.key === 'ArrowLeft' || e.key === 'ArrowRight' ||
                        e.key === 'Tab' || e.key === 'Escape') {
                        cancelAutoSubmit();
                    }
                });

                codeInput.addEventListener('click', cancelAutoSubmit);

                codeInput.addEventListener('paste', function(e) {
                    const pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
                    const digits = pasted.replace(/\D/g, '').slice(0, 6);

                    if (digits) {
                        e.preventDefault();
                        this.value = digits;
                        this.dispatchEvent(new Event('input'));
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    const code = codeInput?.value || '';
                    if (code.length !== 6) {
                        e.preventDefault();
                        codeInput?.classList.add('error');
                        setTimeout(() => codeInput?.classList.remove('error'), 500);
                        showToast('Please enter all 6 digits.', 'error');
                        codeInput?.focus();
                        return;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('loading');
                    }
                });
            }

            // ============================================================
            // Resend code — AJAX with rate-limit feedback
            // ============================================================
            if (resendBtn) {
                let resendCooldownUntil = 0;

                resendBtn.addEventListener('click', async function() {
                    const now = Date.now();
                    if (now < resendCooldownUntil) {
                        const seconds = Math.ceil((resendCooldownUntil - now) / 1000);
                        showToast(`Please wait ${seconds}s before requesting another code.`, 'warning');
                        return;
                    }

                    const originalHtml = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner"></i><span>Sending...</span>';

                    try {
                        const response = await fetch('{{ route("2fa.resend") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': currentCsrfToken || CSRF_CONFIG.token,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({})
                        });

                        const data = await response.json().catch(() => ({}));

                        if (response.ok && data.success) {
                            showToast(data.message || 'A new code has been sent.', 'success');
                            resendCooldownUntil = Date.now() + 60000;
                            const startCooldownLabel = () => {
                                const update = () => {
                                    const remaining = Math.ceil((resendCooldownUntil - Date.now()) / 1000);
                                    if (remaining > 0) {
                                        resendBtn.innerHTML = `<i class="fas fa-clock"></i><span>Resend in ${remaining}s</span>`;
                                        setTimeout(update, 1000);
                                    } else {
                                        resendBtn.innerHTML = originalHtml;
                                        resendBtn.disabled = false;
                                    }
                                };
                                update();
                            };
                            startCooldownLabel();
                        } else {
                            const message = data.message
                                || data.error
                                || `Could not resend (${response.status}). Please try again.`;
                            showToast(message, response.status === 429 ? 'warning' : 'error');
                            this.innerHTML = originalHtml;
                            this.disabled = false;
                        }
                    } catch (error) {
                        console.error('Resend error:', error);
                        showToast('Network error. Please try again.', 'error');
                        this.innerHTML = originalHtml;
                        this.disabled = false;
                    }
                });
            }

            // ============================================================
            // Session health check — periodic
            // ============================================================
            let sessionCheckInterval = null;

            function checkSessionHealth() {
                if (sessionCheckInterval) clearInterval(sessionCheckInterval);

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
                        if (response.status === 419) handle419Response(response);
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.authenticated === false) {
                            window.location.href = '{{ route("login") }}';
                        }
                    })
                    .catch(() => {});
                }, 60000);
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Focus the code input on load for faster UX
                if (codeInput) {
                    codeInput.focus({ preventScroll: true });
                }

                checkSessionHealth();

                console.log('2FA verify page loaded', {
                    csrfToken: currentCsrfToken ? 'Present' : 'Missing',
                    sessionId: CSRF_CONFIG.sessionId,
                    method: '{{ $verificationMethod }}',
                    deliveryFailed: {{ $deliveryFailed ? 'true' : 'false' }},
                    autoSubmit: 'enabled (180ms grace window)'
                });
            });

        })();
    </script>
</body>
</html>