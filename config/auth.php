<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | ⚠️ CRITICAL for Flutter / Bearer-token clients:
    |
    |   • The `sanctum` guard below is what `auth:sanctum` middleware uses.
    |     Without it, every protected route falls back to the `web`
    |     (session) guard and returns 401 for Bearer requests — even
    |     when the token is perfectly valid.
    |
    |   • Do NOT put a `sanctum` driver inside `config/sanctum.php`'s
    |     `guard` array; that's the list of *session* guards Sanctum
    |     checks first. It must stay `['web']`.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // ✅ REQUIRED: sanctum bearer-token guard.
        //    Routes using `auth:sanctum` resolve to this.
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // Kept for backward compatibility — any legacy route still
        // using `auth:api` will also work. Both guards now delegate
        // to Sanctum's bearer-token authentication.
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // Web admin guard (session-based). Only used by Blade / browser
        // routes, never by the Flutter app.
        'admin' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        'archived_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
            'with_trashed' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => env('AUTH_PASSWORD_RESET_EXPIRE', 60),
            'throttle' => env('AUTH_PASSWORD_RESET_THROTTLE', 60),
        ],

        'admins' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 120,
            'throttle' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Two-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    */

    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', true),
        'default_method' => env('TWO_FACTOR_DEFAULT_METHOD', 'email'),
        'code_length' => env('TWO_FACTOR_CODE_LENGTH', 6),
        'code_expiry' => env('TWO_FACTOR_CODE_EXPIRY', 10),
        'max_attempts' => env('TWO_FACTOR_MAX_ATTEMPTS', 5),
        'lockout_duration' => env('TWO_FACTOR_LOCKOUT_DURATION', 30),
        'backup_code_count' => env('TWO_FACTOR_BACKUP_COUNT', 10),
        'trusted_device_expiry' => env('TWO_FACTOR_TRUSTED_DEVICE_EXPIRY', 30),
        'force_for_types' => env('TWO_FACTOR_FORCE_TYPES', '0,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Login Attempt Configuration
    |--------------------------------------------------------------------------
    */

    'login_attempts' => [
        'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => env('LOGIN_DECAY_MINUTES', 1),
        'lockout_duration' => env('LOGIN_LOCKOUT_DURATION', 15),
        'ip_rate_limiting' => env('LOGIN_IP_RATE_LIMITING', true),
        'max_ip_attempts' => env('LOGIN_MAX_IP_ATTEMPTS', 50),
        'ip_block_duration' => env('LOGIN_IP_BLOCK_DURATION', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Session Configuration Overrides
    |--------------------------------------------------------------------------
    */

    'session' => [
        'single_session' => env('SINGLE_SESSION_MODE', false),
        'prevent_hijacking' => env('PREVENT_SESSION_HIJACKING', true),
        'validate_ip' => env('SESSION_VALIDATE_IP', true),
        'validate_user_agent' => env('SESSION_VALIDATE_USER_AGENT', true),
        'timeout_warning' => env('SESSION_TIMEOUT_WARNING', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Password Policy Configuration
    |--------------------------------------------------------------------------
    */

    'password_policy' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_number' => env('PASSWORD_REQUIRE_NUMBER', true),
        'require_special' => env('PASSWORD_REQUIRE_SPECIAL', true),
        'expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),
        'prevent_reuse' => env('PASSWORD_PREVENT_REUSE', true),
        'reuse_history' => env('PASSWORD_REUSE_HISTORY', 5),
        'change_on_first_login' => env('PASSWORD_CHANGE_ON_FIRST_LOGIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Social Login Configuration
    |--------------------------------------------------------------------------
    */

    'social' => [
        'enabled' => env('SOCIAL_LOGIN_ENABLED', true),
        'providers' => ['google', 'microsoft', 'facebook', 'apple', 'github'],
        'auto_register' => env('SOCIAL_AUTO_REGISTER', true),
        'require_email_verification' => env('SOCIAL_REQUIRE_EMAIL_VERIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Account Recovery Configuration
    |--------------------------------------------------------------------------
    */

    'recovery' => [
        'enabled' => env('ACCOUNT_RECOVERY_ENABLED', true),
        'token_expiry' => env('RECOVERY_TOKEN_EXPIRY', 24),
        'require_identity_verification' => env('RECOVERY_REQUIRE_IDENTITY', true),
        'max_attempts' => env('RECOVERY_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Captcha Configuration
    |--------------------------------------------------------------------------
    */

    'captcha' => [
        'enabled' => env('CAPTCHA_ENABLED', false),
        'type' => env('CAPTCHA_TYPE', 'recaptcha'),
        'site_key' => env('CAPTCHA_SITE_KEY'),
        'secret_key' => env('CAPTCHA_SECRET_KEY'),
        'verify_on_login' => env('CAPTCHA_ON_LOGIN', true),
        'verify_on_registration' => env('CAPTCHA_ON_REGISTRATION', true),
        'verify_on_password_reset' => env('CAPTCHA_ON_PASSWORD_RESET', true),
        'max_failed_attempts' => env('CAPTCHA_MAX_FAILED_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Registration Configuration
    |--------------------------------------------------------------------------
    */

    'registration' => [
        'enabled' => env('REGISTRATION_ENABLED', true),
        'default_type' => env('REGISTRATION_DEFAULT_TYPE', 3),
        'require_verification' => env('REGISTRATION_REQUIRE_VERIFICATION', true),
        'require_phone_verification' => env('REGISTRATION_REQUIRE_PHONE', false),
        'log_ip' => env('REGISTRATION_LOG_IP', true),
        'log_user_agent' => env('REGISTRATION_LOG_USER_AGENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Device Tracking Configuration
    |--------------------------------------------------------------------------
    */

    'devices' => [
        'enabled' => env('DEVICE_TRACKING_ENABLED', true),
        'max_devices' => env('DEVICE_MAX_DEVICES', 10),
        'track_logins' => env('DEVICE_TRACK_LOGINS', true),
        'require_trust_for_2fa' => env('DEVICE_REQUIRE_TRUST', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: CSRF Configuration
    |--------------------------------------------------------------------------
    */

    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'refresh_interval' => env('CSRF_REFRESH_INTERVAL', 600),
        'exclude' => [
            'webhooks/*',
            'api/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Impersonation Configuration
    |--------------------------------------------------------------------------
    */

    'impersonate' => [
        'enabled' => env('IMPERSONATION_ENABLED', true),
        'allowed_types' => explode(',', env('IMPERSONATE_ALLOWED_TYPES', '0,1')),
        'require_confirmation' => env('IMPERSONATE_REQUIRE_CONFIRMATION', true),
        'log_activity' => env('IMPERSONATE_LOG_ACTIVITY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Activity Logging Configuration
    |--------------------------------------------------------------------------
    */

    'activity_log' => [
        'enabled' => env('ACTIVITY_LOG_ENABLED', true),
        'log_login_attempts' => env('LOG_LOGIN_ATTEMPTS', true),
        'log_failed_logins' => env('LOG_FAILED_LOGINS', true),
        'log_successful_logins' => env('LOG_SUCCESSFUL_LOGINS', true),
        'log_password_changes' => env('LOG_PASSWORD_CHANGES', true),
        'log_session_activities' => env('LOG_SESSION_ACTIVITIES', true),
        'log_device_changes' => env('LOG_DEVICE_CHANGES', true),
        'retention_days' => env('ACTIVITY_LOG_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Email Verification Configuration
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'link_expiry' => env('VERIFICATION_LINK_EXPIRY', 60),
        'resend_cooldown' => env('VERIFICATION_RESEND_COOLDOWN', 60),
        'max_resend_attempts' => env('VERIFICATION_MAX_RESEND', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'login_attempts' => env('RATE_LIMIT_LOGIN', 5),
        'reset_attempts' => env('RATE_LIMIT_RESET', 3),
        'two_factor_attempts' => env('RATE_LIMIT_2FA', 5),
        'registration_attempts' => env('RATE_LIMIT_REGISTRATION', 3),
        'api_requests' => env('RATE_LIMIT_API', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Security Headers Configuration
    |--------------------------------------------------------------------------
    */

    'security_headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'hsts_max_age' => env('HSTS_MAX_AGE', 31536000),
        'frame_options' => env('FRAME_OPTIONS', 'DENY'),
        'csp' => env('CONTENT_SECURITY_POLICY', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Custom User Types Configuration
    |--------------------------------------------------------------------------
    */

    'user_types' => [
        'types' => [
            0 => 'Super Admin',
            1 => 'Admin',
            2 => 'Landlord',
            3 => 'Tenant',
            4 => 'Field Agent',
            5 => 'Developer',
            6 => 'Security Personnel',
        ],
        'admin_panel_types' => [0, 1],
        'require_2fa_types' => [0, 1],
        'impersonate_types' => [0, 1],
        'developer_types' => [5],
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        'enabled' => env('API_AUTH_ENABLED', true),
        'token_expiry' => env('API_TOKEN_EXPIRY', 180),
        'max_tokens' => env('API_MAX_TOKENS', 10),
        'rate_limit' => env('API_RATE_LIMIT', 60),
        'rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),
        'jwt' => [
            'ttl' => env('JWT_TTL', 60),
            'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Notification Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'login_notifications' => env('LOGIN_NOTIFICATIONS', true),
        'failed_login_notifications' => env('FAILED_LOGIN_NOTIFICATIONS', true),
        'two_factor_notifications' => env('TWO_FACTOR_NOTIFICATIONS', true),
        'password_change_notifications' => env('PASSWORD_CHANGE_NOTIFICATIONS', true),
        'new_device_notifications' => env('NEW_DEVICE_NOTIFICATIONS', true),
    ],
];