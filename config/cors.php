<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This config serves TWO types of clients with different needs:
    |
    |   1. Admin panel (session-based, same-origin at 127.0.0.1:8000)
    |      - Needs cookies to be sent (SESSION + CSRF)
    |      - Needs supports_credentials = true
    |      - Explicit origins (not wildcard — credentials forbid wildcards)
    |
    |   2. Flutter mobile / Flutter Web (Bearer token auth)
    |      - Does NOT need cookies
    |      - Can use wildcard origin
    |      - Relies on Authorization header
    |
    | Because a single CORS middleware instance can only have one settings
    | object, we use an EXPLICIT ORIGIN LIST plus supports_credentials = true.
    | This satisfies the admin panel and still works for Flutter clients
    | hitting /api/* (bearer tokens don't need credentials to be sent via
    | CORS — the client adds the Authorization header itself).
    |
    */

    'paths' => [
        // ── Admin panel (session-based) ──────────────────────
        'admin/*',
        'super-admin/*',

        // ── API + auth ──────────────────────────────────────
        'api/*',
        'api/v1/*',
        'api/v2/*',
        'v1/*',
        'auth/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
        'email/verification-notification',
        'oauth/*',
        'broadcasting/auth',
        'graphql',
        'webhooks/*',

        // ── Public static assets ────────────────────────────
        'files/*',
        'uploads/*',
    ],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins — explicit list, NOT wildcard
    |--------------------------------------------------------------------------
    |
    | Wildcards are incompatible with supports_credentials = true.
    | So we list every origin explicitly.
    |
    | Read from CORS_ALLOWED_ORIGINS in .env, falling back to a
    | sensible default list that includes both localhost and 127.0.0.1.
    |
    */

    'allowed_origins' => array_values(array_filter(
        array_map(
            'trim',
            explode(',', (string) env(
                'CORS_ALLOWED_ORIGINS',
                'http://127.0.0.1:8000,http://localhost:8000,'
                . 'http://127.0.0.1:3000,http://localhost:3000,'
                . 'http://127.0.0.1:5173,http://localhost:5173,'
                . 'http://127.0.0.1:52758,http://localhost:52758,'
                . 'http://127.0.0.1:8080,http://localhost:8080'
            ))
        )
    )),

    'allowed_origins_patterns' => [
        // Flutter Web dev servers often run on random ports.
        // Allow any localhost / 127.0.0.1 port for dev convenience.
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-API-Version',
        'X-Rate-Limit-Limit',
        'X-Rate-Limit-Remaining',
        'X-Rate-Limit-Reset',
        'X-Token-Expires-At',
        'Content-Disposition',
        'Content-Length',
        'X-Request-ID',
        'X-Response-Time',
    ],

    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    /*
    |--------------------------------------------------------------------------
    | Credentials — MUST be true for admin panel session cookies
    |--------------------------------------------------------------------------
    |
    | With explicit origins above, this is spec-compliant.
    | Flutter Bearer-token clients ignore this setting.
    |
    */

    'supports_credentials' => true,

];