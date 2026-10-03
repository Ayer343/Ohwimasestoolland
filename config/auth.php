<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
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
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        
        // API guard for Sanctum
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
        
        // ⭐ ADDED: Admin guard for admin-specific routes
        'admin' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        // ⭐ ADDED: Alternative provider for archived users
        'archived_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
            'with_trashed' => true, // Allow authentication of soft-deleted users
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => env('AUTH_PASSWORD_RESET_EXPIRE', 60),
            'throttle' => env('AUTH_PASSWORD_RESET_THROTTLE', 60),
        ],
        
        // ⭐ ADDED: Longer expiry for admin password resets
        'admins' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 120, // 2 hours for admins
            'throttle' => 300, // 5 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Two-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    */

    'two_factor' => [
        // Enable/disable 2FA globally
        'enabled' => env('TWO_FACTOR_ENABLED', true),
        
        // Default method for sending 2FA codes
        'default_method' => env('TWO_FACTOR_DEFAULT_METHOD', 'email'), // email, sms, authenticator
        
        // Length of verification code
        'code_length' => env('TWO_FACTOR_CODE_LENGTH', 6),
        
        // Code expiry time in minutes
        'code_expiry' => env('TWO_FACTOR_CODE_EXPIRY', 10),
        
        // Maximum attempts before lockout
        'max_attempts' => env('TWO_FACTOR_MAX_ATTEMPTS', 5),
        
        // Lockout duration in minutes
        'lockout_duration' => env('TWO_FACTOR_LOCKOUT_DURATION', 30),
        
        // Backup code count
        'backup_code_count' => env('TWO_FACTOR_BACKUP_COUNT', 10),
        
        // Trusted device expiry in days
        'trusted_device_expiry' => env('TWO_FACTOR_TRUSTED_DEVICE_EXPIRY', 30),
        
        // Force 2FA for specific user types
        'force_for_types' => env('TWO_FACTOR_FORCE_TYPES', '0,1'), // Super Admin, Admin
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Login Attempt Configuration
    |--------------------------------------------------------------------------
    */

    'login_attempts' => [
        // Maximum number of login attempts
        'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
        
        // Decay minutes for login attempts
        'decay_minutes' => env('LOGIN_DECAY_MINUTES', 1),
        
        // Lockout duration in minutes
        'lockout_duration' => env('LOGIN_LOCKOUT_DURATION', 15),
        
        // Enable IP-based rate limiting
        'ip_rate_limiting' => env('LOGIN_IP_RATE_LIMITING', true),
        
        // Maximum attempts per IP
        'max_ip_attempts' => env('LOGIN_MAX_IP_ATTEMPTS', 50),
        
        // IP block duration in minutes
        'ip_block_duration' => env('LOGIN_IP_BLOCK_DURATION', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Session Configuration Overrides
    |--------------------------------------------------------------------------
    */

    'session' => [
        // Single session mode (only one active session per user)
        'single_session' => env('SINGLE_SESSION_MODE', false),
        
        // Session hijacking prevention
        'prevent_hijacking' => env('PREVENT_SESSION_HIJACKING', true),
        
        // Session IP validation
        'validate_ip' => env('SESSION_VALIDATE_IP', true),
        
        // Session user agent validation
        'validate_user_agent' => env('SESSION_VALIDATE_USER_AGENT', true),
        
        // Session timeout warning in minutes
        'timeout_warning' => env('SESSION_TIMEOUT_WARNING', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Password Policy Configuration
    |--------------------------------------------------------------------------
    */

    'password_policy' => [
        // Minimum password length
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        
        // Require at least one uppercase letter
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        
        // Require at least one lowercase letter
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        
        // Require at least one number
        'require_number' => env('PASSWORD_REQUIRE_NUMBER', true),
        
        // Require at least one special character
        'require_special' => env('PASSWORD_REQUIRE_SPECIAL', true),
        
        // Password expiry in days (0 = never)
        'expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),
        
        // Prevent reuse of previous passwords
        'prevent_reuse' => env('PASSWORD_PREVENT_REUSE', true),
        
        // Number of previous passwords to remember
        'reuse_history' => env('PASSWORD_REUSE_HISTORY', 5),
        
        // Password change required on first login
        'change_on_first_login' => env('PASSWORD_CHANGE_ON_FIRST_LOGIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Social Login Configuration
    |--------------------------------------------------------------------------
    */

    'social' => [
        // Enable/disable social login
        'enabled' => env('SOCIAL_LOGIN_ENABLED', true),
        
        // Allowed providers
        'providers' => ['google', 'microsoft', 'facebook', 'apple', 'github'],
        
        // Auto-registration for new users
        'auto_register' => env('SOCIAL_AUTO_REGISTER', true),
        
        // Require email verification for social accounts
        'require_email_verification' => env('SOCIAL_REQUIRE_EMAIL_VERIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Account Recovery Configuration
    |--------------------------------------------------------------------------
    */

    'recovery' => [
        // Enable/disable account recovery
        'enabled' => env('ACCOUNT_RECOVERY_ENABLED', true),
        
        // Recovery token expiry in hours
        'token_expiry' => env('RECOVERY_TOKEN_EXPIRY', 24),
        
        // Security questions required
        'require_identity_verification' => env('RECOVERY_REQUIRE_IDENTITY', true),
        
        // Maximum recovery attempts
        'max_attempts' => env('RECOVERY_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Captcha Configuration
    |--------------------------------------------------------------------------
    */

    'captcha' => [
        // Enable/disable captcha
        'enabled' => env('CAPTCHA_ENABLED', false),
        
        // Captcha type (recaptcha, hcaptcha, turnstile)
        'type' => env('CAPTCHA_TYPE', 'recaptcha'),
        
        // Site key
        'site_key' => env('CAPTCHA_SITE_KEY'),
        
        // Secret key
        'secret_key' => env('CAPTCHA_SECRET_KEY'),
        
        // Verify on login
        'verify_on_login' => env('CAPTCHA_ON_LOGIN', true),
        
        // Verify on registration
        'verify_on_registration' => env('CAPTCHA_ON_REGISTRATION', true),
        
        // Verify on password reset
        'verify_on_password_reset' => env('CAPTCHA_ON_PASSWORD_RESET', true),
        
        // Maximum failed attempts before captcha is required
        'max_failed_attempts' => env('CAPTCHA_MAX_FAILED_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Registration Configuration
    |--------------------------------------------------------------------------
    */

    'registration' => [
        // Enable/disable registration
        'enabled' => env('REGISTRATION_ENABLED', true),
        
        // Default user type for new registrations
        'default_type' => env('REGISTRATION_DEFAULT_TYPE', 3), // Tenant
        
        // Require email verification
        'require_verification' => env('REGISTRATION_REQUIRE_VERIFICATION', true),
        
        // Require phone verification
        'require_phone_verification' => env('REGISTRATION_REQUIRE_PHONE', false),
        
        // IP logging
        'log_ip' => env('REGISTRATION_LOG_IP', true),
        
        // User agent logging
        'log_user_agent' => env('REGISTRATION_LOG_USER_AGENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Device Tracking Configuration
    |--------------------------------------------------------------------------
    */

    'devices' => [
        // Enable/disable device tracking
        'enabled' => env('DEVICE_TRACKING_ENABLED', true),
        
        // Maximum devices per user
        'max_devices' => env('DEVICE_MAX_DEVICES', 10),
        
        // Track login history
        'track_logins' => env('DEVICE_TRACK_LOGINS', true),
        
        // Require device trust for 2FA
        'require_trust_for_2fa' => env('DEVICE_REQUIRE_TRUST', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: CSRF Configuration
    |--------------------------------------------------------------------------
    */

    'csrf' => [
        // Enable CSRF protection
        'enabled' => env('CSRF_ENABLED', true),
        
        // CSRF token refresh interval in seconds
        'refresh_interval' => env('CSRF_REFRESH_INTERVAL', 600), // 10 minutes
        
        // Exclude routes from CSRF protection
        'exclude' => [
            // Webhooks
            'webhooks/*',
            // API endpoints (if using token-based auth)
            'api/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Impersonation Configuration
    |--------------------------------------------------------------------------
    */

    'impersonate' => [
        // Enable/disable impersonation
        'enabled' => env('IMPERSONATION_ENABLED', true),
        
        // Allowed user types to impersonate
        'allowed_types' => explode(',', env('IMPERSONATE_ALLOWED_TYPES', '0,1')),
        
        // Require password confirmation
        'require_confirmation' => env('IMPERSONATE_REQUIRE_CONFIRMATION', true),
        
        // Log impersonation activity
        'log_activity' => env('IMPERSONATE_LOG_ACTIVITY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Activity Logging Configuration
    |--------------------------------------------------------------------------
    */

    'activity_log' => [
        // Enable/disable activity logging
        'enabled' => env('ACTIVITY_LOG_ENABLED', true),
        
        // Log login attempts
        'log_login_attempts' => env('LOG_LOGIN_ATTEMPTS', true),
        
        // Log failed logins
        'log_failed_logins' => env('LOG_FAILED_LOGINS', true),
        
        // Log successful logins
        'log_successful_logins' => env('LOG_SUCCESSFUL_LOGINS', true),
        
        // Log password changes
        'log_password_changes' => env('LOG_PASSWORD_CHANGES', true),
        
        // Log session activities
        'log_session_activities' => env('LOG_SESSION_ACTIVITIES', true),
        
        // Log device changes
        'log_device_changes' => env('LOG_DEVICE_CHANGES', true),
        
        // Retention period in days
        'retention_days' => env('ACTIVITY_LOG_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Email Verification Configuration
    |--------------------------------------------------------------------------
    */

    'verification' => [
        // Verification link expiry in minutes
        'link_expiry' => env('VERIFICATION_LINK_EXPIRY', 60),
        
        // Resend cooldown in seconds
        'resend_cooldown' => env('VERIFICATION_RESEND_COOLDOWN', 60),
        
        // Maximum resend attempts
        'max_resend_attempts' => env('VERIFICATION_MAX_RESEND', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        // Login attempts per minute
        'login_attempts' => env('RATE_LIMIT_LOGIN', 5),
        
        // Password reset attempts per minute
        'reset_attempts' => env('RATE_LIMIT_RESET', 3),
        
        // 2FA attempts per minute
        'two_factor_attempts' => env('RATE_LIMIT_2FA', 5),
        
        // Registration attempts per minute
        'registration_attempts' => env('RATE_LIMIT_REGISTRATION', 3),
        
        // API requests per minute
        'api_requests' => env('RATE_LIMIT_API', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Security Headers Configuration
    |--------------------------------------------------------------------------
    */

    'security_headers' => [
        // Enable security headers
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        
        // HSTS max age
        'hsts_max_age' => env('HSTS_MAX_AGE', 31536000),
        
        // Frame options
        'frame_options' => env('FRAME_OPTIONS', 'DENY'),
        
        // Content security policy
        'csp' => env('CONTENT_SECURITY_POLICY', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Custom User Types Configuration
    |--------------------------------------------------------------------------
    */

    'user_types' => [
        // Define user types with their labels
        'types' => [
            0 => 'Super Admin',
            1 => 'Admin',
            2 => 'Landlord',
            3 => 'Tenant',
            4 => 'Field Agent',
            5 => 'Developer',
            6 => 'Security Personnel',
        ],
        
        // Define which user types can access the admin panel
        'admin_panel_types' => [0, 1],
        
        // Define which user types require 2FA
        'require_2fa_types' => [0, 1],
        
        // Define which user types can impersonate others
        'impersonate_types' => [0, 1],
        
        // Define which user types have access to developer features
        'developer_types' => [5],
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        // Enable API authentication
        'enabled' => env('API_AUTH_ENABLED', true),
        
        // API token expiry in days
        'token_expiry' => env('API_TOKEN_EXPIRY', 180), // 6 months
        
        // Maximum tokens per user
        'max_tokens' => env('API_MAX_TOKENS', 10),
        
        // Rate limit for API
        'rate_limit' => env('API_RATE_LIMIT', 60),
        
        // Rate limit per minute
        'rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),
        
        // JWT configuration (if using JWT)
        'jwt' => [
            'ttl' => env('JWT_TTL', 60), // minutes
            'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // minutes (14 days)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ⭐ ADDED: Notification Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        // Enable login notifications
        'login_notifications' => env('LOGIN_NOTIFICATIONS', true),
        
        // Enable failed login notifications
        'failed_login_notifications' => env('FAILED_LOGIN_NOTIFICATIONS', true),
        
        // Enable 2FA code notifications
        'two_factor_notifications' => env('TWO_FACTOR_NOTIFICATIONS', true),
        
        // Enable password change notifications
        'password_change_notifications' => env('PASSWORD_CHANGE_NOTIFICATIONS', true),
        
        // Enable new device notifications
        'new_device_notifications' => env('NEW_DEVICE_NOTIFICATIONS', true),
    ],
];