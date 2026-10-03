<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    */
    'version' => 'v1',
    'latest_version' => 'v1',
    'supported_versions' => ['v1'],
    
    /*
    |--------------------------------------------------------------------------
    | API Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        '2fa_enabled' => env('API_2FA_ENABLED', true),
        'device_management' => env('API_DEVICE_MANAGEMENT', true),
        'login_history' => env('API_LOGIN_HISTORY', true),
        'password_expiry' => env('API_PASSWORD_EXPIRY', true),
        'push_notifications' => env('API_PUSH_NOTIFICATIONS', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'default' => env('API_RATE_LIMIT_DEFAULT', 60),
        'auth' => env('API_RATE_LIMIT_AUTH', 10),
        'password_reset' => env('API_RATE_LIMIT_PASSWORD_RESET', 5),
        '2fa' => env('API_RATE_LIMIT_2FA', 5),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    */
    'response' => [
        'wrap_data' => true,
        'include_meta' => true,
        'debug_mode' => env('API_DEBUG_MODE', false),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration for Mobile
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allowed_origins' => explode(',', env('API_ALLOWED_ORIGINS', '*')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Device-ID', 'X-API-Version'],
        'max_age' => 86400, // 24 hours
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Device & Session
    |--------------------------------------------------------------------------
    */
    'device' => [
        'verification_required' => env('API_DEVICE_VERIFICATION', false),
        'max_devices_per_user' => env('API_MAX_DEVICES', 5),
        'session_timeout' => env('API_SESSION_TIMEOUT', 43200), // 30 days in minutes
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'password_expiry_days' => env('API_PASSWORD_EXPIRY_DAYS', 90),
        '2fa_required_roles' => explode(',', env('API_2FA_REQUIRED_ROLES', 'admin,supervisor')),
        'login_attempts' => env('API_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => env('API_LOCKOUT_MINUTES', 15),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    */
    'docs_url' => env('API_DOCS_URL', '/api/documentation'),
    'postman_collection' => env('API_POSTMAN_COLLECTION', null),
];