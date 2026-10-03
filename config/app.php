<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Hilltop Resident Management System'),
    
    /*
    |--------------------------------------------------------------------------
    | Application Short Name
    |--------------------------------------------------------------------------
    |
    | This value is the shortened name of your application, which will be used 
    | in places where the full application name is too long (e.g., page titles).
    |
    */
    
    'short_name' => env('APP_SHORT_NAME', 'Hilltop RMS'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Asset URL
    |--------------------------------------------------------------------------
    |
    | This URL is used to generate asset URLs. If not set, it will fall back
    | to the APP_URL. This is particularly useful in production environments
    | where assets might be served from a CDN or different domain.
    |
    */

    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Invitation Configuration
    |--------------------------------------------------------------------------
    |
    | These configuration options control the behavior of agent invitations
    | including expiration periods and warning thresholds.
    |
    */

    'invitation_expiry_days' => env('INVITATION_EXPIRY_DAYS', 7),
    
    'invitation_warning_days' => env('INVITATION_WARNING_DAYS', 2),
    
    'invitation_auto_expiry' => env('INVITATION_AUTO_EXPIRY', true),
    
    'invitation_resend_extends_expiry' => env('INVITATION_RESEND_EXTENDS_EXPIRY', true),

    /*
    |--------------------------------------------------------------------------
    | ✅ APP DEEP LINK CONFIGURATION (For Flutter Mobile App)
    |--------------------------------------------------------------------------
    |
    | This configuration defines the deep link scheme used by the Flutter mobile
    | application to handle password reset and other deep links.
    |
    | The scheme should match the one configured in the Flutter app:
    | - Android: android/app/src/main/AndroidManifest.xml
    | - iOS: ios/Runner/Info.plist
    |
    | Examples: myapp, hilltop, com.hilltop.estate
    |
    */

    'deep_link_scheme' => env('APP_DEEP_LINK_SCHEME', 'myapp'),

    /*
    |--------------------------------------------------------------------------
    | Deep Link Host (Optional)
    |--------------------------------------------------------------------------
    |
    | If you want to use host-based deep linking instead of just scheme-based,
    | you can specify the host here. This is useful for universal links.
    |
    | Example: 'hilltopestate.com'
    |
    */

    'deep_link_host' => env('APP_DEEP_LINK_HOST', null),

    /*
    |--------------------------------------------------------------------------
    | Deep Link Paths
    |--------------------------------------------------------------------------
    |
    | Define the paths that should be handled by the Flutter app.
    | These will be used to generate deep links in emails and notifications.
    |
    */

    'deep_link_paths' => [
        'reset-password' => 'reset-password',
        'verify-email' => 'verify-email',
        'invitation' => 'invitation',
        'dashboard' => 'dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Aliases
    |--------------------------------------------------------------------------
    |
    | These aliases are used by Laravel to provide convenient access to
    | various packages and services throughout the application.
    |
    */

    'Image' => Intervention\Image\Facades\Image::class,

    /*
    |--------------------------------------------------------------------------
    | Trusted IPs for CSRF Bypass
    |--------------------------------------------------------------------------
    */
    'trusted_ips' => env('TRUSTED_IPS', []),
    
    /*
    |--------------------------------------------------------------------------
    | Internal API Secret Key
    |--------------------------------------------------------------------------
    */
    'internal_secret' => env('INTERNAL_API_SECRET'),
    
    /*
    |--------------------------------------------------------------------------
    | Valid API Tokens for External Services
    |--------------------------------------------------------------------------
    */
    'api_tokens' => explode(',', env('API_TOKENS', '')),
    
    /*
    |--------------------------------------------------------------------------
    | Log CSRF Mismatch Attempts
    |--------------------------------------------------------------------------
    */
    'log_csrf_attempts' => env('LOG_CSRF_ATTEMPTS', false),

    /*
    |--------------------------------------------------------------------------
    | ✅ Mobile App Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of the mobile application integration.
    |
    */

    'mobile' => [
        // App version for API headers
        'app_version' => env('MOBILE_APP_VERSION', '1.0.0'),
        
        // Minimum supported app version (force update if below)
        'min_app_version' => env('MOBILE_MIN_APP_VERSION', '1.0.0'),
        
        // Enable mobile API features
        'api_enabled' => env('MOBILE_API_ENABLED', true),
        
        // Token expiry in days for mobile devices
        'token_expiry_days' => env('MOBILE_TOKEN_EXPIRY_DAYS', 180),
        
        // Enable biometric login
        'biometric_enabled' => env('MOBILE_BIOMETRIC_ENABLED', true),
        
        // Enable social login
        'social_login_enabled' => env('MOBILE_SOCIAL_LOGIN_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 🎨 THEME CONFIGURATION
    |--------------------------------------------------------------------------
    |
    | This configuration controls the theme system behavior, including
    | available options, default settings, and caching.
    |
    */

    'theme' => [

        /*
        |--------------------------------------------------------------------------
        | Theme Options
        |--------------------------------------------------------------------------
        |
        | These are the available options for each theme setting.
        |
        */
        'options' => [
            'appearance' => ['light', 'dark', 'system'],
            'sidebar_themes' => ['default', 'dark', 'light', 'blue', 'green', 'custom'],
            'font_sizes' => ['small', 'medium', 'large'],
            'layout' => ['compact', 'comfortable'],
            'animations' => ['enabled', 'disabled'],
            'sidebar_position' => ['left', 'right'],
            'header_style' => ['default', 'glass', 'solid'],
            'density' => ['comfortable', 'compact', 'spacious']
        ],

        /*
        |--------------------------------------------------------------------------
        | Default Theme Settings
        |--------------------------------------------------------------------------
        |
        | These are the default settings applied to new users or when
        | theme settings are reset.
        |
        */
        'defaults' => [
            'appearance' => env('THEME_DEFAULT_APPEARANCE', 'system'),
            'sidebar_theme' => env('THEME_DEFAULT_SIDEBAR_THEME', 'default'),
            'font_size' => env('THEME_DEFAULT_FONT_SIZE', 'medium'),
            'layout' => env('THEME_DEFAULT_LAYOUT', 'comfortable'),
            'animations' => env('THEME_DEFAULT_ANIMATIONS', 'enabled'),
            'sidebar_position' => env('THEME_DEFAULT_SIDEBAR_POSITION', 'left'),
            'header_style' => env('THEME_DEFAULT_HEADER_STYLE', 'default'),
            'density' => env('THEME_DEFAULT_DENSITY', 'comfortable')
        ],

        /*
        |--------------------------------------------------------------------------
        | Default Colors
        |--------------------------------------------------------------------------
        |
        | These are the default color values used for the theme.
        | Can be overridden by environment variables.
        |
        */
        'default_colors' => [
            // Sidebar colors
            'sidebar_bg_start' => env('THEME_SIDEBAR_BG_START', '#7267f0'),
            'sidebar_bg_end' => env('THEME_SIDEBAR_BG_END', '#6258e0'),
            'sidebar_text' => env('THEME_SIDEBAR_TEXT', '#ffffff'),
            'sidebar_active_bg' => env('THEME_SIDEBAR_ACTIVE_BG', 'rgba(255, 255, 255, 0.2)'),
            'sidebar_hover_bg' => env('THEME_SIDEBAR_HOVER_BG', 'rgba(255, 255, 255, 0.12)'),
            'sidebar_border' => env('THEME_SIDEBAR_BORDER', 'transparent'),
            'sidebar_shadow' => env('THEME_SIDEBAR_SHADOW', '0 0 20px rgba(114, 103, 240, 0.3)'),
            
            // Global colors
            'primary_color' => env('THEME_PRIMARY_COLOR', '#7267f0'),
            'secondary_color' => env('THEME_SECONDARY_COLOR', '#6258e0'),
            'accent_color' => env('THEME_ACCENT_COLOR', '#64FFDA'),
            'success_color' => env('THEME_SUCCESS_COLOR', '#10b981'),
            'warning_color' => env('THEME_WARNING_COLOR', '#f59e0b'),
            'danger_color' => env('THEME_DANGER_COLOR', '#ef4444'),
            'info_color' => env('THEME_INFO_COLOR', '#06b6d4'),
            
            // Header colors
            'header_bg' => env('THEME_HEADER_BG', '#ffffff'),
            'header_text' => env('THEME_HEADER_TEXT', '#4b4b4b'),
            
            // Card colors
            'card_bg' => env('THEME_CARD_BG', '#ffffff'),
            'card_border' => env('THEME_CARD_BORDER', '#e5e7eb'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Sidebar Themes
        |--------------------------------------------------------------------------
        |
        | Predefined sidebar theme configurations.
        |
        */
        'sidebar_themes' => [
            'default' => [
                'bg' => 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)',
                'text' => '#ffffff',
                'hover' => 'rgba(255, 255, 255, 0.12)',
                'active' => 'rgba(255, 255, 255, 0.2)',
                'border' => 'transparent',
                'shadow' => '0 0 20px rgba(114, 103, 240, 0.3)'
            ],
            'dark' => [
                'bg' => 'linear-gradient(180deg, #232933 0%, #1e2229 100%)',
                'text' => '#ecf0f1',
                'hover' => 'rgba(236, 240, 241, 0.08)',
                'active' => 'rgba(52, 152, 219, 0.2)',
                'border' => '#3498db',
                'shadow' => '0 0 20px rgba(0, 0, 0, 0.2)'
            ],
            'light' => [
                'bg' => 'linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%)',
                'text' => '#4a5568',
                'hover' => 'rgba(114, 103, 240, 0.1)',
                'active' => 'rgba(114, 103, 240, 0.15)',
                'border' => '#7267f0',
                'shadow' => '0 0 15px rgba(0, 0, 0, 0.05)'
            ],
            'blue' => [
                'bg' => 'linear-gradient(180deg, #1a56db 0%, #1e429f 100%)',
                'text' => '#ffffff',
                'hover' => 'rgba(255, 255, 255, 0.12)',
                'active' => 'rgba(100, 255, 218, 0.2)',
                'border' => '#64FFDA',
                'shadow' => '0 0 20px rgba(26, 86, 219, 0.3)'
            ],
            'green' => [
                'bg' => 'linear-gradient(180deg, #057a55 0%, #0a5c36 100%)',
                'text' => '#ffffff',
                'hover' => 'rgba(255, 255, 255, 0.12)',
                'active' => 'rgba(105, 240, 174, 0.2)',
                'border' => '#69F0AE',
                'shadow' => '0 0 20px rgba(5, 122, 85, 0.3)'
            ]
        ],

        /*
        |--------------------------------------------------------------------------
        | Theme Cache Configuration
        |--------------------------------------------------------------------------
        |
        | These settings control how theme data is cached.
        |
        */
        'cache' => [
            'enabled' => env('THEME_CACHE_ENABLED', true),
            'ttl' => env('THEME_CACHE_TTL', 3600), // 1 hour default
            'prefix' => env('THEME_CACHE_PREFIX', 'theme_'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Theme Storage
        |--------------------------------------------------------------------------
        |
        | Configuration for where theme settings are stored.
        |
        */
        'storage' => [
            // Storage drivers: 'session', 'database', 'cache'
            'driver' => env('THEME_STORAGE_DRIVER', 'session'),
            
            // User theme table name (if using database)
            'table' => env('THEME_TABLE', 'user_themes'),
            
            // User model column for theme preference
            'user_column' => env('THEME_USER_COLUMN', 'theme_preference'),
            
            // User model column for custom colors
            'colors_column' => env('THEME_COLORS_COLUMN', 'custom_colors'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Theme Features
        |--------------------------------------------------------------------------
        |
        | Enable/disable specific theme features.
        |
        */
        'features' => [
            'custom_colors' => env('THEME_FEATURE_CUSTOM_COLORS', true),
            'sidebar_themes' => env('THEME_FEATURE_SIDEBAR_THEMES', true),
            'density_control' => env('THEME_FEATURE_DENSITY_CONTROL', true),
            'header_styles' => env('THEME_FEATURE_HEADER_STYLES', true),
            'glass_effect' => env('THEME_FEATURE_GLASS_EFFECT', true),
            'animations' => env('THEME_FEATURE_ANIMATIONS', true),
            'system_preference' => env('THEME_FEATURE_SYSTEM_PREFERENCE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Theme Assets
        |--------------------------------------------------------------------------
        |
        | Configuration for theme assets like CSS and JavaScript.
        |
        */
        'assets' => [
            // CSS file generation
            'generate_css' => env('THEME_GENERATE_CSS', true),
            
            // CSS file path (for static file generation)
            'css_path' => env('THEME_CSS_PATH', 'public/css/theme.css'),
            
            // Minify generated CSS
            'minify_css' => env('THEME_MINIFY_CSS', true),
            
            // Version hash for cache busting
            'version' => env('THEME_VERSION', '1.0.0'),
        ],
    ],

];