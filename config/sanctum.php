<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies.
    |
    | For a Flutter app that uses Bearer tokens, this list MUST BE EMPTY.
    | Any host listed here will be treated as a first-party SPA using cookies
    | and will cause Bearer tokens to be IGNORED, which produces 401 errors.
    |
    | Only add SPA origins here (Vue/React/Inertia), never your API host.
    |
    | ⚠️ KEEP THIS EMPTY for mobile / Flutter Web clients that use Bearer tokens.
    |
    */

    'stateful' => [],

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    | ⚠️ CRITICAL: This MUST only contain 'web' (or other SESSION guards).
    |
    | Do NOT include 'api' or 'sanctum' here. Those names refer to Sanctum's
    | own guard, which creates an infinite recursion when Sanctum tries to
    | authenticate itself: Guard → RequestGuard → Guard → RequestGuard → ...
    | resulting in "Maximum call stack size reached. Infinite recursion?"
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    | Set to null to disable automatic expiration. For mobile, consider
    | setting SANCTUM_TOKEN_EXPIRATION in .env for environment-specific values.
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'flutter_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Token Abilities
    |--------------------------------------------------------------------------
    |
    | Define custom abilities for tokens to implement fine-grained authorization.
    | This is useful for mobile apps to limit what each token can do.
    |
    */

    'abilities' => [
        'mobile:full' => 'Full mobile access',
        'mobile:read' => 'Read-only mobile access',
        'mobile:write' => 'Write mobile access',
        'mobile:devices' => 'Manage devices',
        'mobile:security' => 'Security operations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting for Mobile API
    |--------------------------------------------------------------------------
    |
    | Additional rate limiting configuration specific for mobile endpoints.
    |
    */

    'rate_limits' => [
        'login' => env('SANCTUM_RATE_LIMIT_LOGIN', 10),
        'api' => env('SANCTUM_RATE_LIMIT_API', 60),
        'sensitive' => env('SANCTUM_RATE_LIMIT_SENSITIVE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Revocation on Password Change
    |--------------------------------------------------------------------------
    |
    | When set to true, all tokens will be revoked when the user changes their
    | password. This enhances security for mobile apps.
    |
    */

    'revoke_on_password_change' => env('SANCTUM_REVOKE_ON_PASSWORD_CHANGE', true),

    /*
    |--------------------------------------------------------------------------
    | Maximum Tokens Per User
    |--------------------------------------------------------------------------
    |
    | Limit the maximum number of active tokens a user can have. This helps
    | prevent token bloat and encourages device management.
    |
    */

    'max_tokens_per_user' => env('SANCTUM_MAX_TOKENS_PER_USER', 10),

];