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

    if (!empty($loginBillingOverdue)) {
        $bs['overdue'] = true;
    }

    $billingBannerVisible = !empty($bs['overdue']) || !empty($bs['pending']);

    /*
     | ✅ FAVICON + SETTINGS
     */
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

    /*
     | ✅ ARCHIVAL NOTICES — normalize into one array so the modal
     |    renders uniformly regardless of which session key the
     |    controller used.
     */
    $archivalNotice = null;

    $archivedLandlordMsg = session('archived_account') ?? session('archived_landlord_account');
    $archivedTenantMsg   = session('archived_tenant_account');

    if (!empty($archivedTenantMsg)) {
        $archivalNotice = [
            'kind'    => 'tenant',
            'title'   => 'Tenant Account Archived',
            'icon'    => 'fa-user-clock',
            'accent'  => 'tenant',
            'message' => $archivedTenantMsg,
            'reason'  => 'Your tenant account has been archived because it remained unassigned to any property unit for 30+ days.',
            'next'    => 'Please contact the administration to have your account restored or to be assigned to a property unit.',
        ];
    } elseif (!empty($archivedLandlordMsg)) {
        $archivalNotice = [
            'kind'    => 'landlord',
            'title'   => 'Landlord Account Archived',
            'icon'    => 'fa-archive',
            'accent'  => 'landlord',
            'message' => $archivedLandlordMsg,
            'reason'  => 'Your account has been archived because you no longer own any properties.',
            'next'    => 'Please contact the administration to have your account restored.',
        ];
    }

    $archivalContactEmail = session('contact_email')
        ?? config('mail.from.address', 'support@hilltop.com');
    $archivalContactPhone = session('contact_phone')
        ?? config('app.support_phone', '+233 123 456 789');
    $archivalSystemName = session('system_name')
        ?? ($systemSettings->system_name ?? config('app.name', 'Hilltop Estate Management'));

    /*
     | ✅ BILLING MODAL — build the payload for the super-admin billing modal.
     */
    $billingModal = null;

    if (!$archivalNotice && $billingBannerVisible) {
        $currentUserType = auth()->check() ? auth()->user()->type : null;
        $isSuperAdmin = ((int) $currentUserType === (int) \App\Models\User::TYPE_SUPER_ADMIN);

        if ($isSuperAdmin) {
            $billingState = $bs['state'];

            if (!empty($bs['overdue'])) {
                $billingModal = [
                    'kind'    => 'billing-overdue',
                    'title'   => 'System Billing Overdue',
                    'icon'    => 'fa-exclamation-triangle',
                    'accent'  => 'billing-overdue',
                    'message' => 'Your system billing invoice is past due. Administrative write actions are temporarily disabled until payment is recorded.',
                    'reason'  => match ($billingState) {
                        'active_suspended' =>
                            'Your system billing is currently suspended. Please settle the outstanding invoice to restore full service.',
                        'active_overdue' =>
                            'The invoice has passed its due date. Please settle it as soon as possible.',
                        default =>
                            'Your account has an outstanding balance that needs to be cleared.',
                    },
                    'next'    => 'Landlords, tenants, and other system users are unaffected. Only administrative write actions are restricted until payment is recorded.',
                    'cta_url' => \Route::has('superadmin.billing.dashboard')
                        ? route('superadmin.billing.dashboard')
                        : null,
                    'cta_label' => 'Go to Billing Dashboard',
                    'cta_icon'  => 'fa-credit-card',
                ];
            } elseif (!empty($bs['pending'])) {
                $billingModal = [
                    'kind'    => 'billing-pending',
                    'title'   => 'Billing Agreement Awaiting Signature',
                    'icon'    => 'fa-signature',
                    'accent'  => 'billing-pending',
                    'message' => 'Your system billing agreement requires a signature before invoicing and payments can begin.',
                    'reason'  => 'The billing agreement has not yet been signed. Sign it to enable monthly invoicing and payment processing.',
                    'next'    => 'Once signed, invoices will be generated automatically on the billing cycle. Until then, no charges will be made.',
                    'cta_url' => \Route::has('superadmin.billing.dashboard')
                        ? route('superadmin.billing.dashboard')
                        : null,
                    'cta_label' => 'Review & Sign Agreement',
                    'cta_icon'  => 'fa-pen-fancy',
                ];
            }
        }
    }

    /*
     | ============================================================
     | ⭐ LOGIN PAGE CONFIG
     | ============================================================
     */
    $loginConfig = [
        'background_image'    => null,
        'background_url'      => null,
        'overlay_opacity'     => 0.85,
        'default_theme'       => 'dark',
        'show_welcome_panel'  => true,
        'welcome_title'       => 'Experience Luxury Living',
        'welcome_subtitle'    => 'Hilltop Executive Estate offers premium amenities and breathtaking views in an exclusive community setting.',
        'welcome_features'    => [
            ['icon' => 'fas fa-home',          'text' => 'Luxury Residences'],
            ['icon' => 'fas fa-shield-alt',    'text' => '24/7 Security'],
            ['icon' => 'fas fa-swimming-pool', 'text' => 'Resort-style Amenities'],
            ['icon' => 'fas fa-tree',          'text' => 'Beautiful Landscaping'],
        ],
        'show_social_icons'   => true,
        'show_cookie_banner'  => true,
        'footer_socials'      => [],
        'system_name_override'=> null,
    ];

    $loginBgUrl = null;

    try {
        if ($systemSettings && method_exists($systemSettings, 'getLoginPageConfig')) {
            $resolved = $systemSettings->getLoginPageConfig();
            if (is_array($resolved)) {
                $loginConfig = array_replace_recursive($loginConfig, $resolved);
            }
        }

        if ($systemSettings && method_exists($systemSettings, 'getLoginPageBackgroundUrl')) {
            $loginBgUrl = $systemSettings->getLoginPageBackgroundUrl();
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('Login page config resolution failed: ' . $e->getMessage());
    }

    /*
     | ============================================================
     | ⭐ LOGIN THEME — LIGHT + DARK RESOLUTION
     | ============================================================
     | The admin can customize the login theme. We produce TWO
     | sets of CSS variables:
     |
     |   $loginCssVarsLight  →  used for :root
     |   $loginCssVarsDark   →  used for html.dark
     |
     | Rules:
     |   - If a color is EXPLICITLY set by the admin, it wins in
     |     BOTH light and dark (respects their choice).
     |   - If a color is at its default, we pick a sensible value
     |     per mode:
     |       light page → white form, dark text
     |       dark  page → dark  form, light text
     |
     | This is what prevents the "form invisible in one mode" bug.
     */

    // ---------- 1. Admin overrides ----------
    $loginThemeOverrides = [];
    try {
        if ($systemSettings && method_exists($systemSettings, 'getLoginThemeOverrides')) {
            $raw = $systemSettings->getLoginThemeOverrides();
            if (is_array($raw)) {
                $loginThemeOverrides = $raw;
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('Login theme overrides resolution failed: ' . $e->getMessage());
    }

    $overridesColors = $loginThemeOverrides['colors'] ?? [];

    // ---------- 2. Base theme defaults ----------
    $loginTheme = [
        'preset' => 'default',
        'colors' => [
            'primary'    => '#2563eb',
            'secondary'  => '#1e40af',
            'accent'     => '#f59e0b',
            'panel_bg'   => '#0f172a',
            'panel_text' => '#f8fafc',
            'form_bg'    => '#ffffff',
            'form_text'  => '#0f172a',
        ],
        'fonts' => [
            'family'         => 'system',
            'base_size'      => 16,
            'heading_weight' => '700',
        ],
        'layout' => [
            'split'        => 'panel-left',
            'panel_width'  => 50,
            'radius_style' => 'rounded',
            'card_shadow'  => true,
        ],
        'buttons' => [
            'shape'  => 'rounded',
            'shadow' => true,
        ],
    ];

    try {
        if ($systemSettings && method_exists($systemSettings, 'getLoginPageTheme')) {
            $resolvedTheme = $systemSettings->getLoginPageTheme();
            if (is_array($resolvedTheme)) {
                $loginTheme = array_replace_recursive($loginTheme, $resolvedTheme);
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug('Login theme resolution failed: ' . $e->getMessage());
    }

    // ---------- 3. Resolve radius / shadow / font helpers ----------
    $radiusMap = [
        'sharp'   => '0',
        'rounded' => '0.75rem',
        'pill'    => '999px',
    ];
    $shapeMap = [
        'square'  => '0',
        'rounded' => '0.5rem',
        'pill'    => '999px',
    ];

    $loginRadiusStyle = $loginTheme['layout']['radius_style'] ?? 'rounded';
    $loginBtnShape    = $loginTheme['buttons']['shape']      ?? 'rounded';
    $loginCardShadow  = ($loginTheme['layout']['card_shadow'] ?? true)
        ? '0 20px 40px rgba(0,0,0,.15)'
        : 'none';
    $loginBtnShadow   = ($loginTheme['buttons']['shadow'] ?? true)
        ? '0 6px 16px rgba(0,0,0,.15)'
        : 'none';

    $loginFontFam   = $loginTheme['fonts']['family']           ?? 'system';
    $loginFontSize  = (int) ($loginTheme['fonts']['base_size'] ?? 16);
    $loginHeadWeight= $loginTheme['fonts']['heading_weight']   ?? '700';
    $loginSplit     = $loginTheme['layout']['split']           ?? 'panel-left';
    $loginPanelWidth= (int) ($loginTheme['layout']['panel_width'] ?? 50);

    // ---------- 4. Build light + dark variable sets ----------
    /**
     * Resolve a login color for both modes.
     * If the admin explicitly set it, it wins in both modes.
     * Otherwise the fallback pair (light, dark) is used.
     */
    $pickColor = function (string $key, string $lightFallback, string $darkFallback) use ($loginTheme, $overridesColors) {
        if (!empty($overridesColors[$key])) {
            $val = (string) ($loginTheme['colors'][$key] ?? $lightFallback);
            return ['light' => $val, 'dark' => $val];
        }
        return ['light' => $lightFallback, 'dark' => $darkFallback];
    };

    // Brand colors — same in both modes (admins pick one brand palette)
    $primaryColor   = (string) ($loginTheme['colors']['primary']   ?? '#2563eb');
    $secondaryColor = (string) ($loginTheme['colors']['secondary'] ?? '#1e40af');
    $accentColor    = (string) ($loginTheme['colors']['accent']    ?? '#f59e0b');

    // Panel surface — usually stays dark (brand welcome panel), but respects admin override
    $panelBgPair   = $pickColor('panel_bg',   '#0f172a', '#0f172a');
    $panelTextPair = $pickColor('panel_text', '#f8fafc', '#f8fafc');

    // Form surface — MUST flip, because it's the readable content area.
    // This is the actual bug fix: default = white in light, dark in dark.
    $formBgPair    = $pickColor('form_bg',    '#ffffff', '#0f172a');
    $formTextPair  = $pickColor('form_text',  '#0f172a', '#f1f5f9');

    $loginCssVarsLight = [
        '--login-primary'        => $primaryColor,
        '--login-secondary'      => $secondaryColor,
        '--login-accent'         => $accentColor,
        '--login-panel-bg'       => $panelBgPair['light'],
        '--login-panel-text'     => $panelTextPair['light'],
        '--login-form-bg'        => $formBgPair['light'],
        '--login-form-text'      => $formTextPair['light'],
        '--login-radius'         => $radiusMap[$loginRadiusStyle] ?? '0.75rem',
        '--login-btn-radius'     => $shapeMap[$loginBtnShape]     ?? '0.5rem',
        '--login-font-family'    => $loginFontFam,
        '--login-base-size'      => $loginFontSize . 'px',
        '--login-heading-weight' => $loginHeadWeight,
        '--login-panel-width'    => $loginPanelWidth . '%',
        '--login-card-shadow'    => $loginCardShadow,
        '--login-btn-shadow'     => $loginBtnShadow,
    ];

    $loginCssVarsDark = [
        '--login-primary'        => $primaryColor,
        '--login-secondary'      => $secondaryColor,
        '--login-accent'         => $accentColor,
        '--login-panel-bg'       => $panelBgPair['dark'],
        '--login-panel-text'     => $panelTextPair['dark'],
        '--login-form-bg'        => $formBgPair['dark'],
        '--login-form-text'      => $formTextPair['dark'],
        '--login-radius'         => $radiusMap[$loginRadiusStyle] ?? '0.75rem',
        '--login-btn-radius'     => $shapeMap[$loginBtnShape]     ?? '0.5rem',
        '--login-font-family'    => $loginFontFam,
        '--login-base-size'      => $loginFontSize . 'px',
        '--login-heading-weight' => $loginHeadWeight,
        '--login-panel-width'    => $loginPanelWidth . '%',
        '--login-card-shadow'    => $loginCardShadow,
        '--login-btn-shadow'     => $loginBtnShadow,
    ];

    // Convenience mirrors — use the LIGHT set (identical shape)
    $loginCssVars   = $loginCssVarsLight;

    // Google Fonts family — map the theme family to the real font name.
    $loginGoogleFontHref = null;
    switch ($loginFontFam) {
        case 'inter':
            $loginGoogleFontHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap';
            break;
        case 'poppins':
            $loginGoogleFontHref = 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap';
            break;
        case 'roboto':
            $loginGoogleFontHref = 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap';
            break;
        case 'playfair':
            $loginGoogleFontHref = 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&display=swap';
            break;
        default:
            $loginGoogleFontHref = null;
    }

    // Real font stack for `system` (avoids a network request)
    $loginFontStack = match ($loginFontFam) {
        'inter'    => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'poppins'  => "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'roboto'   => "'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'playfair' => "'Playfair Display', Georgia, 'Times New Roman', serif",
        default    => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    };

    /*
     | ⭐ FIX — background image selection + preload metadata
     */
    $hasCustomBg = !empty($loginBgUrl);
    $loginBgImage = $loginBgUrl
        ?: 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=75';

    // For the preload link
    $bgPreloadHref  = $loginBgImage;
    $bgPreloadType  = null;

    // Detect type for proper preload `as=image` header
    $bgExt = strtolower(pathinfo(parse_url($loginBgImage, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    if (in_array($bgExt, ['jpg','jpeg'], true))      $bgPreloadType = 'image/jpeg';
    elseif ($bgExt === 'png')                        $bgPreloadType = 'image/png';
    elseif ($bgExt === 'webp')                       $bgPreloadType = 'image/webp';
    elseif ($bgExt === 'gif')                        $bgPreloadType = 'image/gif';

    // Effective system name (respects login-page override)
    $loginSystemName = !empty($loginConfig['system_name_override'])
        ? $loginConfig['system_name_override']
        : ($systemSettings->system_name ?? 'Community Portal');

    // Overlay opacity → color stops
    $overlayOpacity = (float) ($loginConfig['overlay_opacity'] ?? 0.85);
    $overlayOpacity = max(0, min(1, $overlayOpacity));
    $overlayColorTop    = 'rgba(15, 23, 42, ' . $overlayOpacity . ')';
    $overlayColorBottom = 'rgba(30, 58, 138, ' . max(0, $overlayOpacity - 0.05) . ')';
    $darkOverlayTop    = 'rgba(0, 0, 0, ' . min(1, $overlayOpacity + 0.05) . ')';
    $darkOverlayBottom = 'rgba(15, 23, 42, ' . $overlayOpacity . ')';

    // Default theme for login
    $loginDefaultTheme = in_array($loginConfig['default_theme'] ?? 'dark', ['dark', 'light', 'system'], true)
        ? $loginConfig['default_theme']
        : 'dark';

    // Feature list (defensive)
    $loginFeatures = [];
    if (!empty($loginConfig['welcome_features']) && is_array($loginConfig['welcome_features'])) {
        foreach ($loginConfig['welcome_features'] as $feat) {
            if (!is_array($feat)) continue;
            $icon = trim((string) ($feat['icon'] ?? ''));
            $text = trim((string) ($feat['text'] ?? ''));
            if ($icon === '' && $text === '') continue;
            $loginFeatures[] = [
                'icon' => $icon !== '' ? $icon : 'fas fa-check',
                'text' => $text !== '' ? $text : '—',
            ];
        }
    }

    // Footer socials
    $loginSocialIconMap = [
        'facebook'  => 'fab fa-facebook',
        'instagram' => 'fab fa-instagram',
        'linkedin'  => 'fab fa-linkedin',
        'twitter'   => 'fab fa-twitter',
        'whatsapp'  => 'fab fa-whatsapp',
        'youtube'   => 'fab fa-youtube',
        'tiktok'    => 'fab fa-tiktok',
    ];

    $loginSocials = [];
    if (!empty($loginConfig['footer_socials']) && is_array($loginConfig['footer_socials'])) {
        foreach ($loginConfig['footer_socials'] as $social) {
            if (!is_array($social)) continue;
            $platform = $social['platform'] ?? '';
            $url      = trim((string) ($social['url'] ?? ''));
            if ($url === '' || !isset($loginSocialIconMap[$platform])) continue;
            $loginSocials[] = [
                'platform' => $platform,
                'url'      => $url,
                'icon'     => $loginSocialIconMap[$platform],
            ];
        }
    }
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

    <meta name="description" content="Secure login to {{ $loginSystemName }} - Property & Estate Management System">
    <meta name="color-scheme" content="dark light">
    <title>Login | {{ $loginSystemName }}</title>

    {{-- ⭐ FIX: Preload the background image so it starts downloading in parallel with CSS --}}
    <link rel="preconnect" href="https://images.unsplash.com" crossorigin>
    <link rel="dns-prefetch" href="https://images.unsplash.com">
    @if($bgPreloadType)
        <link
            rel="preload"
            as="image"
            href="{{ $bgPreloadHref }}"
            type="{{ $bgPreloadType }}"
            fetchpriority="high"
        >
    @else
        <link rel="preload" as="image" href="{{ $bgPreloadHref }}" fetchpriority="high">
    @endif

    {{-- ============ FAVICON (hardened) ============ --}}
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

    {{-- ===== FIX 419: Inline CSRF token for JavaScript ===== --}}
    <script>
        window.Laravel = {
            csrfToken: '{{ $csrfToken }}',
            sessionId: '{{ $sessionId }}',
            sessionLifetime: {{ config('session.lifetime', 120) }},
            loginDefaultTheme: '{{ $loginDefaultTheme }}',
            routes: {
                login: '{{ route("login") }}',
                csrfRefresh: '{{ route("csrf.refresh") }}',
                sessionCheck: '{{ route("session.check") }}',
                sessionContinue: '{{ route("session.continue") }}',
                sessionInvalidate: '{{ route("session.invalidate") }}'
            }
        };
    </script>

    <!-- ⭐ Anti-flash dark theme script — runs first -->
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
            } catch(e) { }

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);

            const style = document.createElement('style');
            style.textContent = `
                html { background-color: #0a0f1c; }
                html:not(.dark) { background-color: #f8fafc; }
                body { visibility: visible !important; }
                .no-js-warning { display: none; }
            `;
            document.head.appendChild(style);
        })();
    </script>

    <!-- ⭐ Critical CSS — inlined so the login card paints at first paint -->
    <style>
        /* ============================================================
           LOGIN THEME — LIGHT (default) + DARK (html.dark)
           ============================================================
           The two variable sets are resolved server-side. When the
           admin has NOT customized a given color, the two sets carry
           different values (white form in light, dark form in dark)
           so text is always readable. When the admin HAS customized
           a color, both sets carry the same value — their choice is
           respected in both modes.
           ============================================================ */
        :root {
            @foreach($loginCssVarsLight as $var => $val)
                {{ $var }}: {{ $val }};
            @endforeach

            /* Legacy aliases — keep existing rules below working without
               a rewrite. Anything still referencing var(--primary) etc.
               now follows the admin theme. */
            --primary:        var(--login-panel-bg);
            --secondary:      var(--login-primary);
            --accent:         var(--login-accent);
            --light:          #f8fafc;
            --card-bg:        var(--login-form-bg);
            --text-primary:   var(--login-form-text);
            --text-secondary: #475569;
            --border-light:   #e2e8f0;
            --input-bg:       transparent;
            --label-color:    #64748b;
            --divider-color:  #e2e8f0;
            --social-btn-bg:  white;
            --social-btn-border: #e2e8f0;
            --social-btn-text: #334155;
            --password-strength-weak: #ef4444;
            --password-strength-medium: #f59e0b;
            --password-strength-strong: #10b981;
            --focus-ring:     rgba(59, 130, 246, 0.5);
            --transition-default: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html.dark {
            /* 🆕 Dark-mode overrides — this is the actual fix.
               Form bg → dark, form text → light, so text stays
               readable even when the page flips to dark mode. */
            @foreach($loginCssVarsDark as $var => $val)
                {{ $var }}: {{ $val }};
            @endforeach

            /* Legacy aliases for dark mode */
            --text-secondary: #cbd5e1;
            --border-light:   #334155;
            --input-bg:       rgba(51, 65, 85, 0.3);
            --label-color:    #94a3b8;
            --divider-color:  #334155;
            --social-btn-bg:  #1e293b;
            --social-btn-border: #475569;
            --social-btn-text: #e2e8f0;
            --focus-ring:     rgba(96, 165, 250, 0.5);
            --card-bg:        var(--login-form-bg);
            --text-primary:   var(--login-form-text);
        }

        /* ------- Base ------- */
        html, body { margin: 0; padding: 0; min-height: 100vh; }
        body {
            font-family: {{ $loginFontStack }};
            font-size: var(--login-base-size);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            padding: 1rem;
            position: relative;
            color: var(--login-form-text);
        }

        /* ------- Themed glass card ------- */
        .glass-card {
            background: var(--login-form-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            /* Border + shadow derived from the text color so the card
               is always visible against its own background. */
            border: 1px solid color-mix(in srgb, var(--login-form-text) 18%, transparent);
            box-shadow: var(--login-card-shadow);
            border-radius: var(--login-radius);
            z-index: 2;
            position: relative;
            width: 100%;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            color: var(--login-form-text);
            transition: var(--transition-default);
        }

        .glass-card h1, .glass-card h2, .glass-card h3 {
            font-weight: var(--login-heading-weight);
            color: var(--login-form-text);
        }

        /* ------- Themed primary button ------- */
        .btn-primary {
            background: linear-gradient(135deg, var(--login-primary) 0%, var(--login-secondary) 100%);
            border-radius: var(--login-btn-radius);
            box-shadow: var(--login-btn-shadow);
            transition: var(--transition-default);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            border: none;
            font-weight: 600;
            min-height: 48px;
            color: #fff;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35);
        }
        .btn-primary:active::before { width: 300px; height: 300px; }
        .btn-primary:focus-visible {
            outline: 2px solid var(--focus-ring);
            outline-offset: 2px;
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 0; height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        /* ------- Themed inputs ------- */
        .floating-input {
            border: 0;
            /* Border + placeholder derived from text color — always visible */
            border-bottom: 2px solid color-mix(in srgb, var(--login-form-text) 40%, transparent);
            outline: none;
            transition: var(--transition-default);
            background: transparent;
            padding: 0.75rem 2.5rem 0.75rem 0.75rem;
            color: var(--login-form-text);
            width: 100%;
            font-size: 1rem;
            min-height: 50px;
        }
        .floating-input:focus { border-color: var(--login-primary); }
        .floating-input::placeholder {
            color: color-mix(in srgb, var(--login-form-text) 55%, transparent);
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
            color: var(--login-primary);
        }

        /* ------- Welcome panel: themed via --login-panel-* ------- */
        .welcome-text {
            flex: 1;
            color: var(--login-panel-text);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
            min-width: 250px;
        }
        .welcome-text h1 { color: var(--login-panel-text); }

        /* ------- Layout split variants ------- */
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
        .content-container.layout-panel-right {
            flex-direction: row-reverse;
        }
        .content-container.layout-single-column .welcome-text {
            display: none;
        }
        .content-container.layout-single-column .login-form-container {
            max-width: 480px;
            flex: 0 1 auto;
        }
        .content-container .welcome-text {
            flex: 0 0 calc(var(--login-panel-width) - 2rem);
            max-width: calc(var(--login-panel-width) - 2rem);
        }
        .content-container .login-form-container {
            flex: 0 0 450px;
            width: 100%;
            max-width: 450px;
            min-width: 320px;
        }

        @media (max-width: 968px) {
            .content-container { flex-direction: column !important; padding: 0.5rem; }
            .content-container .welcome-text {
                padding-right: 0; padding-bottom: 1.5rem; text-align: center;
                width: 100%; flex: 1 1 auto; max-width: 100%;
            }
            .content-container .login-form-container {
                flex: 1 1 auto; max-width: 450px; min-width: 280px; width: 100%;
            }
        }
        @media (max-width: 640px) {
            body { padding: 0.5rem; align-items: flex-start; padding-top: 1rem; }
            .welcome-text { display: none !important; }
            .content-container .login-form-container {
                flex: 1 1 auto; min-width: 260px; max-width: 100%;
            }
        }

        /* ------- Form controls themed via CSS vars ------- */
        .social-icon-btn {
            background: var(--login-form-bg);
            border: 1px solid color-mix(in srgb, var(--login-form-text) 20%, transparent);
            border-radius: var(--login-btn-radius);
            color: var(--login-form-text);
        }
        .social-icon-btn:hover { transform: translateY(-2px); }
        .social-icon-btn:focus-visible { outline: 2px solid var(--focus-ring); outline-offset: 2px; }

        .password-toggle {
            color: var(--label-color);
        }
        .password-toggle:hover { color: var(--login-primary); }

        .notice-btn-primary {
            background: linear-gradient(135deg, var(--login-primary) 0%, var(--login-secondary) 100%);
            border-radius: var(--login-btn-radius);
        }
        .notice-btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            border-radius: var(--login-btn-radius);
        }
        .notice-modal { border-radius: var(--login-radius); }

        /* ---------------- Background image layer ---------------- */
        .login-bg-image {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-color: var(--login-panel-bg);
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
        .login-bg-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, {{ $overlayColorTop }} 0%, {{ $overlayColorBottom }} 100%);
        }
        html.dark .login-bg-image::after {
            background: linear-gradient(135deg, {{ $darkOverlayTop }} 0%, {{ $darkOverlayBottom }} 100%);
        }

        /* ============================================================
           SHARED COMPONENT STYLES
           ============================================================ */
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.15;
            z-index: 1;
            pointer-events: none;
        }

        .glass-card:hover { box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.3); }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .password-strength {
            margin-top: 0.5rem;
            height: 0.25rem;
            border-radius: 0.125rem;
            overflow: hidden;
            background: var(--border-light);
        }
        .password-strength-bar {
            height: 100%; width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 0.125rem;
        }

        .social-buttons-wrapper {
            display: flex; justify-content: center; align-items: center;
            gap: 1rem; flex-wrap: wrap;
        }
        .social-icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 48px; height: 48px;
            transition: var(--transition-default);
            cursor: pointer; text-decoration: none;
            position: relative; flex-shrink: 0;
        }
        .social-icon-btn.google:hover {
            background: #db4437; border-color: #db4437;
            box-shadow: 0 4px 12px rgba(219, 68, 55, 0.3);
        }
        .social-icon-btn.google:hover i,
        .social-icon-btn.google:hover img { filter: brightness(0) invert(1); }
        .social-icon-btn.microsoft:hover {
            background: #00a4ef; border-color: #00a4ef;
            box-shadow: 0 4px 12px rgba(0, 164, 239, 0.3);
        }

        .remember-checkbox-wrapper { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .remember-checkbox-wrapper input[type="checkbox"] {
            width: 1.125rem; height: 1.125rem;
            cursor: pointer; accent-color: var(--login-primary);
            margin: 0; flex-shrink: 0;
        }
        .remember-checkbox-wrapper label {
            cursor: pointer; color: var(--text-secondary);
            font-size: 0.875rem; user-select: none;
        }

        .toast-notification {
            position: fixed; top: 1.25rem; right: 1.25rem;
            padding: 0.875rem 1.125rem; border-radius: var(--login-radius);
            background: var(--card-bg); backdrop-filter: blur(10px);
            border-left: 4px solid; z-index: 1000;
            animation: slideInRight 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 350px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            font-size: 0.875rem; display: flex; align-items: center; gap: 0.75rem;
            color: var(--text-primary);
        }

        .csrf-error-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
            padding: 1rem; background: #dc2626; color: white; text-align: center;
            font-weight: 500; transform: translateY(-100%);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .csrf-error-banner.show { transform: translateY(0); }
        .csrf-error-banner a { color: white; text-decoration: underline; cursor: pointer; font-weight: 600; }
        .csrf-error-banner .close-btn {
            background: none; border: none; color: white; cursor: pointer;
            margin-left: 1rem; font-size: 1.25rem; opacity: 0.8;
        }
        .csrf-error-banner .close-btn:hover { opacity: 1; }

        /* UNIFIED MODAL SYSTEM */
        .notice-modal-overlay {
            position: fixed; inset: 0; z-index: 9998;
            display: none; align-items: center; justify-content: center;
            padding: 1rem; background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            opacity: 0; transition: opacity 0.25s ease;
        }
        .notice-modal-overlay.is-open { display: flex; opacity: 1; }
        .notice-modal {
            position: relative; width: 100%; max-width: 540px;
            max-height: calc(100vh - 2rem); overflow-y: auto;
            background: var(--card-bg);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid color-mix(in srgb, var(--login-form-text) 15%, transparent);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                        0 0 0 1px color-mix(in srgb, var(--login-form-text) 5%, transparent) inset;
            transform: scale(0.94) translateY(12px); opacity: 0;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease;
            color: var(--text-primary);
        }
        .notice-modal-overlay.is-open .notice-modal { transform: scale(1) translateY(0); opacity: 1; }
        .notice-modal.accent-tenant        { border-top: 4px solid #f59e0b; }
        .notice-modal.accent-landlord      { border-top: 4px solid #ef4444; }
        .notice-modal.accent-billing-overdue { border-top: 4px solid #dc2626; }
        .notice-modal.accent-billing-pending { border-top: 4px solid #f59e0b; }
        .notice-modal-header {
            display: flex; align-items: flex-start; gap: 1rem;
            padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--divider-color);
        }
        .notice-modal-header .icon-wrap {
            flex-shrink: 0; width: 3.25rem; height: 3.25rem;
            border-radius: var(--login-radius); display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .notice-modal.accent-tenant .icon-wrap          { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .notice-modal.accent-landlord .icon-wrap        { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .notice-modal.accent-billing-overdue .icon-wrap { background: rgba(220, 38, 38, 0.15); color: #dc2626; }
        .notice-modal.accent-billing-pending .icon-wrap { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .notice-modal-header .title-wrap { flex: 1; min-width: 0; }
        .notice-modal-title {
            font-size: 1.125rem; font-weight: var(--login-heading-weight);
            color: var(--text-primary); margin: 0 0 0.25rem 0; line-height: 1.3;
        }
        .notice-modal-subtitle { font-size: 0.8125rem; color: var(--text-secondary); margin: 0; line-height: 1.4; }
        .notice-modal-close {
            flex-shrink: 0; background: transparent; border: none;
            width: 2rem; height: 2rem; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-secondary);
            transition: var(--transition-default);
        }
        .notice-modal-close:hover { background: rgba(0, 0, 0, 0.08); color: var(--text-primary); }
        html.dark .notice-modal-close:hover { background: rgba(255, 255, 255, 0.08); }
        .notice-modal-body { padding: 1.25rem 1.5rem; }
        .notice-modal-message { font-size: 0.9375rem; line-height: 1.6; color: var(--text-primary); margin: 0 0 1rem 0; }
        .notice-modal-reason {
            display: flex; align-items: flex-start; gap: 0.625rem;
            padding: 0.875rem 1rem; background: rgba(59, 130, 246, 0.08);
            border-left: 3px solid var(--login-primary);
            border-radius: 0.5rem; font-size: 0.8125rem;
            line-height: 1.55; color: var(--text-secondary); margin-bottom: 1rem;
        }
        .notice-modal-reason i { color: var(--login-primary); margin-top: 0.125rem; flex-shrink: 0; }
        .notice-modal-reason strong { color: var(--text-primary); display: block; margin-bottom: 0.25rem; font-size: 0.8125rem; }
        .notice-modal-next {
            display: flex; align-items: flex-start; gap: 0.625rem;
            padding: 0.75rem 1rem; background: rgba(16, 185, 129, 0.06);
            border-left: 3px solid #10b981; border-radius: 0.5rem;
            font-size: 0.8125rem; line-height: 1.55; color: var(--text-secondary); margin-bottom: 1rem;
        }
        .notice-modal-next i { color: #10b981; margin-top: 0.125rem; flex-shrink: 0; }
        .notice-modal-support {
            background: rgba(59, 130, 246, 0.06);
            border: 1px solid rgba(59, 130, 246, 0.18);
            border-radius: 0.625rem; padding: 0.875rem 1rem;
        }
        html.dark .notice-modal-support {
            background: rgba(96, 165, 250, 0.08);
            border-color: rgba(96, 165, 250, 0.24);
        }
        .notice-modal-support-title {
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--login-primary); margin: 0 0 0.625rem 0;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .notice-support-grid { display: flex; flex-direction: column; gap: 0.5rem; }
        .notice-support-row {
            display: flex; align-items: center; gap: 0.625rem;
            font-size: 0.8125rem; color: var(--text-primary);
        }
        .notice-support-row i { width: 1.125rem; text-align: center; flex-shrink: 0; }
        .notice-modal-footer {
            padding: 1rem 1.5rem 1.5rem; display: flex;
            justify-content: flex-end; gap: 0.75rem;
            border-top: 1px solid var(--divider-color); flex-wrap: wrap;
        }
        .notice-btn {
            padding: 0.625rem 1.25rem; font-weight: 600;
            font-size: 0.875rem; cursor: pointer;
            transition: var(--transition-default);
            border: 1px solid transparent;
            display: inline-flex; align-items: center; gap: 0.5rem;
            text-decoration: none; white-space: nowrap;
        }
        .notice-btn-secondary {
            background: transparent; border-color: var(--border-light);
            color: var(--text-secondary); border-radius: var(--login-btn-radius);
        }
        .notice-btn-secondary:hover { background: rgba(0, 0, 0, 0.05); color: var(--text-primary); }
        html.dark .notice-btn-secondary:hover { background: rgba(255, 255, 255, 0.05); }
        .notice-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.35);
        }
        .notice-btn-primary { color: white; }
        .notice-btn-danger { color: white; border: none; }
        .notice-btn-danger:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4); }

        body.notice-modal-open { overflow: hidden; }

        @media (max-width: 640px) {
            .notice-modal-header { padding: 1.25rem 1.25rem 0.875rem; }
            .notice-modal-body   { padding: 1rem 1.25rem; }
            .notice-modal-footer { padding: 0.875rem 1.25rem 1.25rem; flex-direction: column-reverse; }
            .notice-modal-header .icon-wrap { width: 2.75rem; height: 2.75rem; font-size: 1.25rem; }
            .notice-modal-title { font-size: 1rem; }
            .notice-btn { width: 100%; justify-content: center; }
        }

        a:focus-visible, button:focus-visible, input:focus-visible {
            outline: 2px solid var(--focus-ring); outline-offset: 2px;
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
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeInContent {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-float { animation: float 6s ease-in-out infinite; }
        .fa-spinner { animation: spin 1s linear infinite; }

        .skeleton-item {
            background: linear-gradient(90deg, var(--border-light) 25%, var(--text-secondary) 50%, var(--border-light) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite; border-radius: 4px;
        }

        @media (max-width: 400px) {
            .glass-card { padding: 1rem; min-height: 380px; }
            .floating-input { font-size: 0.875rem; min-height: 44px; }
            .btn-primary { min-height: 44px; font-size: 0.875rem; }
        }

        @media (prefers-contrast: high) {
            .glass-card { border: 2px solid currentColor; }
            .btn-primary { border: 1px solid currentColor; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .loading { position: relative; pointer-events: none; opacity: 0.7; }
        .loading::after {
            content: ''; position: absolute;
            top: 50%; left: 50%;
            width: 1.25rem; height: 1.25rem;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%; animation: spin 0.6s linear infinite;
            transform: translate(-50%, -50%);
        }

        .divider {
            display: flex; align-items: center; text-align: center;
            color: var(--text-secondary); font-size: 0.75rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; border-bottom: 1px solid var(--divider-color);
        }
        .divider::before { margin-right: 0.75rem; }
        .divider::after { margin-left: 0.75rem; }

        .form-loading { opacity: 0.6; pointer-events: none; }

        .session-status-badge {
            display: inline-block; padding: 0.25rem 0.75rem;
            border-radius: 9999px; font-size: 0.75rem; font-weight: 500;
        }
        .session-status-badge.active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .session-status-badge.expired { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        #loginContent { animation: fadeInContent 0.35s ease-out 0.05s both; }
    </style>

    <!-- ⭐ Preload third-party assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    {{-- ⭐ Async-load Tailwind & FontAwesome — no render blocking --}}
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
          rel="stylesheet"
          media="print"
          onload="this.media='all'">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
          rel="stylesheet"
          media="print"
          onload="this.media='all'">

    {{-- Font family driven by admin theme --}}
    @if($loginGoogleFontHref)
        <link rel="preload" as="style" href="{{ $loginGoogleFontHref }}">
        <link href="{{ $loginGoogleFontHref }}" rel="stylesheet">
    @endif
</head>
<body class="{{ ($archivalNotice || $billingModal) ? 'notice-modal-open' : '' }}">

    {{-- ⭐ FIX: Image element background layer --}}
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

    {{-- ============================================================ --}}
    {{-- ✅ ARCHIVAL MODAL                                            --}}
    {{-- ============================================================ --}}
    @if($archivalNotice)
        <div id="archiveNoticeModal"
             class="notice-modal-overlay is-open"
             role="dialog"
             aria-modal="true"
             aria-labelledby="archiveNoticeModalTitle"
             aria-describedby="archiveNoticeModalMessage"
             data-kind="{{ $archivalNotice['kind'] }}">

            <div class="notice-modal accent-{{ $archivalNotice['accent'] }}" role="document">
                <div class="notice-modal-header">
                    <div class="icon-wrap" aria-hidden="true">
                        <i class="fas {{ $archivalNotice['icon'] }}"></i>
                    </div>
                    <div class="title-wrap">
                        <h3 id="archiveNoticeModalTitle" class="notice-modal-title">
                            {{ $archivalNotice['title'] }}
                        </h3>
                        <p class="notice-modal-subtitle">
                            Your account is currently inactive and cannot sign in.
                        </p>
                    </div>
                    <button type="button"
                            class="notice-modal-close"
                            onclick="closeNoticeModal('archiveNoticeModal')"
                            aria-label="Close notice">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="notice-modal-body">
                    <p id="archiveNoticeModalMessage" class="notice-modal-message">
                        {{ $archivalNotice['message'] }}
                    </p>

                    <div class="notice-modal-reason">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Why was my account archived?</strong>
                            {{ $archivalNotice['reason'] }}
                        </div>
                    </div>

                    @if(!empty($archivalNotice['next']))
                    <div class="notice-modal-next">
                        <i class="fas fa-arrow-right"></i>
                        <div>{{ $archivalNotice['next'] }}</div>
                    </div>
                    @endif

                    <div class="notice-modal-support">
                        <p class="notice-modal-support-title">
                            <i class="fas fa-headset"></i>
                            <span>Need Assistance?</span>
                        </p>
                        <div class="notice-support-grid">
                            <div class="notice-support-row">
                                <i class="fas fa-envelope" style="color: #60a5fa;"></i>
                                <span>{{ $archivalContactEmail }}</span>
                            </div>
                            <div class="notice-support-row">
                                <i class="fas fa-phone-alt" style="color: #4ade80;"></i>
                                <span>{{ $archivalContactPhone }}</span>
                            </div>
                            <div class="notice-support-row">
                                <i class="fas fa-building" style="color: #c084fc;"></i>
                                <span>{{ $archivalSystemName }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="notice-modal-footer">
                    <button type="button"
                            class="notice-btn notice-btn-secondary"
                            onclick="dismissNoticeModal('archiveNoticeModal')">
                        <i class="fas fa-eye-slash"></i>
                        <span>View login form anyway</span>
                    </button>
                    <button type="button"
                            class="notice-btn notice-btn-primary"
                            onclick="closeNoticeModal('archiveNoticeModal')"
                            autofocus>
                        <i class="fas fa-check"></i>
                        <span>I Understand</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- ✅ BILLING MODAL                                             --}}
    {{-- ============================================================ --}}
    @if($billingModal)
        <div id="billingNoticeModal"
             class="notice-modal-overlay is-open"
             role="dialog"
             aria-modal="true"
             aria-labelledby="billingNoticeModalTitle"
             aria-describedby="billingNoticeModalMessage"
             data-kind="{{ $billingModal['kind'] }}">

            <div class="notice-modal accent-{{ $billingModal['accent'] }}" role="document">
                <div class="notice-modal-header">
                    <div class="icon-wrap" aria-hidden="true">
                        <i class="fas {{ $billingModal['icon'] }}"></i>
                    </div>
                    <div class="title-wrap">
                        <h3 id="billingNoticeModalTitle" class="notice-modal-title">
                            {{ $billingModal['title'] }}
                        </h3>
                        <p class="notice-modal-subtitle">
                            Action is required on your billing account.
                        </p>
                    </div>
                    <button type="button"
                            class="notice-modal-close"
                            onclick="closeNoticeModal('billingNoticeModal')"
                            aria-label="Close notice">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="notice-modal-body">
                    <p id="billingNoticeModalMessage" class="notice-modal-message">
                        {{ $billingModal['message'] }}
                    </p>

                    <div class="notice-modal-reason">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>What's the issue?</strong>
                            {{ $billingModal['reason'] }}
                        </div>
                    </div>

                    @if(!empty($billingModal['next']))
                    <div class="notice-modal-next">
                        <i class="fas fa-arrow-right"></i>
                        <div>{{ $billingModal['next'] }}</div>
                    </div>
                    @endif

                    <div class="notice-modal-support">
                        <p class="notice-modal-support-title">
                            <i class="fas fa-headset"></i>
                            <span>Support Contact</span>
                        </p>
                        <div class="notice-support-grid">
                            <div class="notice-support-row">
                                <i class="fas fa-envelope" style="color: #60a5fa;"></i>
                                <span>{{ $archivalContactEmail }}</span>
                            </div>
                            <div class="notice-support-row">
                                <i class="fas fa-phone-alt" style="color: #4ade80;"></i>
                                <span>{{ $archivalContactPhone }}</span>
                            </div>
                            <div class="notice-support-row">
                                <i class="fas fa-building" style="color: #c084fc;"></i>
                                <span>{{ $archivalSystemName }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="notice-modal-footer">
                    <button type="button"
                            class="notice-btn notice-btn-secondary"
                            onclick="dismissNoticeModal('billingNoticeModal')">
                        <i class="fas fa-clock"></i>
                        <span>Remind me later</span>
                    </button>

                    @if(!empty($billingModal['cta_url']))
                        <a href="{{ $billingModal['cta_url'] }}"
                           class="notice-btn {{ $billingModal['accent'] === 'billing-overdue' ? 'notice-btn-danger' : 'notice-btn-primary' }}">
                            <i class="fas {{ $billingModal['cta_icon'] }}"></i>
                            <span>{{ $billingModal['cta_label'] }}</span>
                        </a>
                    @else
                        <button type="button"
                                class="notice-btn notice-btn-primary"
                                onclick="closeNoticeModal('billingNoticeModal')"
                                autofocus>
                            <i class="fas fa-check"></i>
                            <span>I Understand</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Layout split class is driven by admin theme --}}
    <div class="content-container layout-{{ $loginSplit }}">
        <!-- ⭐ Welcome Section — dynamic from admin config -->
        @if(!empty($loginConfig['show_welcome_panel']))
        <div class="welcome-text">
            <h1 class="text-4xl md:text-5xl font-bold mb-6 animate-float">{{ $loginConfig['welcome_title'] }}</h1>
            <p class="text-lg md:text-xl mb-8 opacity-90">{{ $loginConfig['welcome_subtitle'] }}</p>

            @if(!empty($loginFeatures))
            <div class="feature-list" style="margin-top: 2rem;">
                @foreach($loginFeatures as $i => $feat)
                <div class="feature-item"
                     style="display: flex; align-items: center; margin-bottom: 1rem; font-weight: 500;
                            animation: slideIn 0.5s ease forwards {{ 0.05 * $i }}s;
                            opacity: 0; transform: translateX(-20px);">
                    <div class="feature-icon"
                         style="background: rgba(255, 255, 255, 0.2); width: 2rem; height: 2rem;
                                border-radius: 50%; display: flex; align-items: center;
                                justify-content: center; margin-right: 0.75rem; flex-shrink: 0;">
                        <i class="{{ $feat['icon'] }}"></i>
                    </div>
                    <span>{{ $feat['text'] }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        <!-- Login Form Container -->
        <div class="login-form-container">
            <div class="glass-card rounded-3xl shadow-2xl p-8">
                <div id="loginContent" style="display: flex; flex-direction: column; flex: 1;">
                    <h2 class="text-2xl font-semibold mb-2" style="color: var(--login-form-text); text-align: center;">Welcome Back</h2>
                    <p class="text-sm mb-6" style="color: var(--text-secondary); text-align: center;">Sign in to access your account</p>

                    <div id="sessionIndicator" style="display: none; font-size: 0.75rem; padding: 0.5rem; border-radius: 0.375rem; background: rgba(0, 0, 0, 0.05); text-align: center; margin-bottom: 1rem;">
                        <i class="fas fa-desktop mr-1"></i>
                        <span id="sessionStatus">Active session detected</span>
                        <button type="button" id="continueSessionBtn" class="ml-2 text-xs font-medium" style="color: var(--login-primary); background: none; border: none; cursor: pointer;">Continue</button>
                        <button type="button" id="newSessionBtn" class="ml-1 text-xs font-medium" style="color: #ef4444; background: none; border: none; cursor: pointer;">New login</button>
                    </div>

                    <div id="csrfStatusIndicator" style="display: none; font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; text-align: center; margin-bottom: 0.5rem;">
                        <span id="csrfStatusText">CSRF token: active</span>
                    </div>

                    @if ($errors->any())
                        <div class="mb-4 p-4 rounded" style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; border-radius: var(--login-radius);">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-circle mr-2" style="color: #ef4444; margin-top: 0.125rem;"></i>
                                <div>
                                    @foreach ($errors->all() as $error)
                                        <p class="text-sm" style="color: var(--login-form-text);">{{ $error }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="mb-4 p-4 rounded" style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; border-radius: var(--login-radius);">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2" style="color: #10b981; margin-top: 0.125rem;"></i>
                                <p class="text-sm" style="color: var(--login-form-text);">{{ session('status') }}</p>
                            </div>
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="mb-4 p-4 rounded" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid var(--login-primary); border-radius: var(--login-radius);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2" style="color: var(--login-primary); margin-top: 0.125rem;"></i>
                                <div>
                                    <p class="text-sm" style="color: var(--login-form-text);">{{ session('info') }}</p>
                                    @if (session('social_email'))
                                        <p class="text-xs mt-1 opacity-75">Email: {{ session('social_email') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

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
                                <button type="button" id="resendCodeBtn" class="text-xs font-medium" style="color: var(--login-primary); background: none; border: none; cursor: pointer; transition: var(--transition-default);">
                                    <i class="fas fa-redo-alt mr-1"></i>Resend Code
                                </button>
                            </div>
                            <div id="twoFactorError" class="text-xs mt-2 hidden" style="color: #ef4444;"></div>
                        </div>

                        @if(config('auth.captcha_enabled', false))
                        <div class="mb-4 p-3 rounded" style="background: var(--card-bg); border: 1px solid var(--border-light); border-radius: var(--login-radius);">
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
                                    {{ old('remember') ? 'checked' : '' }}
                                >
                                <label for="remember">
                                    <i class="fas fa-memory mr-1"></i> Remember Me
                                </label>
                            </div>

                            <div class="flex items-center gap-3">
                                @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm font-medium transition" style="color: var(--login-primary); text-decoration: none;">
                                    <i class="fas fa-question-circle mr-1"></i> Forgot Password?
                                </a>
                                @endif
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full btn-primary text-white py-3 px-4 font-semibold flex items-center justify-center gap-2 mt-auto"
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
                            $showSocialIcons = !empty($loginConfig['show_social_icons']) && $hasSocialLogin;
                        @endphp

                        @if($showSocialIcons)
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
                            <a href="#" class="font-medium transition" style="color: var(--login-primary); text-decoration: none;">
                                Contact Administration
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            {{-- ⭐ Footer socials --}}
            @if(!empty($loginSocials))
            <div class="text-center mt-6">
                <div class="flex justify-center gap-4 mt-2">
                    @foreach($loginSocials as $social)
                        <a href="{{ $social['url'] }}"
                           target="_blank" rel="noopener noreferrer"
                           class="transition"
                           style="color: var(--login-panel-text); text-decoration: none;"
                           aria-label="{{ ucfirst($social['platform']) }}">
                            <i class="{{ $social['icon'] }}"></i>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- ⭐ Cookie Consent Banner -->
    @if(!empty($loginConfig['show_cookie_banner']))
    <div id="cookieConsent" style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: var(--card-bg); backdrop-filter: blur(20px); border-top: 1px solid var(--border-light); padding: 1rem; z-index: 1000; transform: translateY(100%); transition: transform 0.3s ease;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem;">
            <div class="text-sm" style="color: var(--text-primary);">
                <i class="fas fa-cookie-bite mr-2"></i>
                We use a secure "remember me" cookie that lets you stay signed in on this device. It contains a randomly generated token — never your password. You can clear it anytime by logging out.
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button id="acceptCookies" class="px-4 py-2 text-sm transition" style="background: var(--login-primary); color: white; border: none; cursor: pointer; border-radius: var(--login-btn-radius);">Got it</button>
                <button id="declineCookies" class="px-4 py-2 text-sm transition" style="background: transparent; border: 1px solid var(--border-light); cursor: pointer; color: var(--text-secondary); border-radius: var(--login-btn-radius);">Decline</button>
            </div>
        </div>
    </div>
    @endif

    <script>
        // ===== FIX 419 + background load & skeleton race =====
        (function() {
            'use strict';

            // ---------- ⭐ Background image fade-in ----------
            (function setupBgImage() {
                const img = document.getElementById('loginBgImg');
                if (!img) return;
                if (img.complete && img.naturalWidth > 0) {
                    img.classList.add('is-loaded');
                }
            })();

            // ---------- CSRF / session constants ----------
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
                if (messageEl) messageEl.textContent = 'Refreshing security token...';

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
                        updateCsrfStatus('active');
                    } else {
                        if (messageEl) {
                            messageEl.textContent = data.message || 'Failed to refresh token. Please reload the page.';
                        }
                        showToast('Failed to refresh session. Please reload the page.', 'error');
                    }
                } catch (error) {
                    console.error('CSRF refresh error:', error);
                    if (messageEl) messageEl.textContent = 'Network error. Please reload the page.';
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
                if (messageEl) messageEl.textContent = message || 'Your session has expired. Please refresh the page.';
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
                console.warn('419 Page Expired detected', { url: response.url, status: response.status });
                showCsrfError('Your session has expired. Please refresh the security token.');
                updateCsrfStatus('expired');
                setTimeout(() => { refreshCsrfToken(); }, 1000);
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
                            console.log('CSRF token updated before form submission');
                        }
                        if (tokenInput.value.length !== 40) {
                            e.preventDefault();
                            showToast('Invalid CSRF token. Refreshing...', 'warning');
                            refreshCsrfToken().then(() => {
                                setTimeout(() => { form.submit(); }, 500);
                            });
                            return;
                        }
                    }
                }
            });

            let sessionCheckInterval = null;
            function checkSessionHealth() {
                if (sessionCheckInterval) clearInterval(sessionCheckInterval);
                sessionCheckInterval = setInterval(() => {
                    fetch(CSRF_CONFIG.sessionCheckEndpoint, {
                        method: 'GET',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        if (response.status === 419) handle419Response(response);
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
                            if (metaToken && metaToken === data.csrf_token) updateCsrfStatus('active');
                        }
                    })
                    .catch(() => {});
                }, 60000);
            }

            // ---------- ⭐ Skeleton race-fix ----------
            (function skeletonRace() {
                const content = document.getElementById('loginContent');
                if (!content) return;

                content.style.visibility = 'visible';
                content.style.opacity = '0';

                let revealed = false;
                const reveal = () => {
                    if (revealed) return;
                    revealed = true;
                    content.style.opacity = '1';
                };

                if (document.readyState === 'interactive' || document.readyState === 'complete') {
                    requestAnimationFrame(reveal);
                } else {
                    document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(reveal), { once: true });
                }

                setTimeout(reveal, 1200);
            })();

            document.addEventListener('DOMContentLoaded', function() {
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
            const sessionIndicator = document.getElementById('sessionIndicator');
            const continueSessionBtn = document.getElementById('continueSessionBtn');
            const newSessionBtn = document.getElementById('newSessionBtn');

            let codeTimerInterval = null;
            let twoFactorRequired = false;

            const acceptCookies = document.getElementById('acceptCookies');
            const declineCookies = document.getElementById('declineCookies');
            const cookieConsent = document.getElementById('cookieConsent');

            if (acceptCookies) {
                acceptCookies.addEventListener('click', function() {
                    localStorage.setItem('cookie_consent_accepted', 'true');
                    if (cookieConsent) {
                        cookieConsent.style.transform = 'translateY(100%)';
                        setTimeout(() => { cookieConsent.style.display = 'none'; }, 300);
                    }
                    showToast('Cookie preferences saved', 'success');
                });
            }

            if (declineCookies) {
                declineCookies.addEventListener('click', function() {
                    localStorage.setItem('cookie_consent_accepted', 'false');
                    if (cookieConsent) {
                        cookieConsent.style.transform = 'translateY(100%)';
                        setTimeout(() => { cookieConsent.style.display = 'none'; }, 300);
                    }
                    if (rememberCheckbox) rememberCheckbox.checked = false;
                    showToast('You can still login, but we won\'t remember you', 'info');
                });
            }

            if (localStorage.getItem('cookie_consent_accepted') === null && cookieConsent) {
                setTimeout(() => {
                    cookieConsent.style.display = 'block';
                    setTimeout(() => { cookieConsent.style.transform = 'translateY(0)'; }, 10);
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
                passwordField.addEventListener('input', function() { checkPasswordStrength(this.value); });
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
                if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-shield-alt"></i><span>Verify Code</span>';
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
                        setTimeout(() => { if (resendCodeBtn) resendCodeBtn.disabled = false; }, 60000);
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

            if (resendCodeBtn) resendCodeBtn.addEventListener('click', resendTwoFactorCode);

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
            if (refreshBtn) refreshBtn.addEventListener('click', generateCaptcha);
            generateCaptcha();
            @endif

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
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
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
                    if (!valid) { e.preventDefault(); return; }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('loading');
                    }
                    form.classList.add('form-loading');
                });
            }

            if (loginField && !loginField.value) {
                loginField.focus();
            } else if (passwordField && !passwordField.value) {
                passwordField.focus();
            }

            const socialBtns = document.querySelectorAll('.social-icon-btn');
            socialBtns.forEach(btn => {
                btn.addEventListener('click', function() {
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
            `;
            document.head.appendChild(styleSheet);

            console.log('Login page loaded', {
                csrfToken: currentCsrfToken ? 'Present' : 'Missing',
                sessionId: CSRF_CONFIG.sessionId,
                sessionLifetime: CSRF_CONFIG.lifetime
            });

        })();

        // ===== ✅ UNIFIED NOTICE MODAL — scoped by id =====
        (function() {
            'use strict';

            const overlayIds = ['archiveNoticeModal', 'billingNoticeModal'];
            let previouslyFocusedElement = null;

            function getVisibleOverlay() {
                for (const id of overlayIds) {
                    const el = document.getElementById(id);
                    if (el && el.classList.contains('is-open')) return el;
                }
                return null;
            }

            function getFocusableElements(overlay) {
                return overlay.querySelectorAll(
                    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );
            }

            function openNoticeModal(id) {
                const overlay = document.getElementById(id);
                if (!overlay) return;
                previouslyFocusedElement = document.activeElement;
                overlay.classList.add('is-open');
                document.body.classList.add('notice-modal-open');
                setTimeout(() => {
                    const focusTarget =
                        overlay.querySelector('.notice-btn-primary') ||
                        overlay.querySelector('.notice-btn-danger') ||
                        overlay.querySelector('button, a.notice-btn');
                    if (focusTarget) focusTarget.focus();
                }, 100);
            }

            function closeNoticeModal(id) {
                const overlay = id ? document.getElementById(id) : getVisibleOverlay();
                if (!overlay) return;
                overlay.classList.remove('is-open');
                if (!getVisibleOverlay()) {
                    document.body.classList.remove('notice-modal-open');
                }
                if (previouslyFocusedElement && typeof previouslyFocusedElement.focus === 'function') {
                    previouslyFocusedElement.focus();
                }
            }

            function dismissNoticeModal(id) { closeNoticeModal(id); }

            window.closeNoticeModal = closeNoticeModal;
            window.dismissNoticeModal = dismissNoticeModal;
            window.openNoticeModal = openNoticeModal;

            overlayIds.forEach(id => {
                const overlay = document.getElementById(id);
                if (!overlay) return;

                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeNoticeModal(id);
                });

                if (overlay.classList.contains('is-open')) {
                    document.body.classList.add('notice-modal-open');
                }
            });

            document.addEventListener('keydown', function(e) {
                const overlay = getVisibleOverlay();
                if (!overlay) return;

                if (e.key === 'Escape') { closeNoticeModal(overlay.id); return; }

                if (e.key === 'Tab') {
                    const focusables = Array.from(getFocusableElements(overlay));
                    if (focusables.length === 0) return;
                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault(); last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault(); first.focus();
                    }
                }
            });
        })();
    </script>
</body>
</html>