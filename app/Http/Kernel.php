<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // Laravel default
        \App\Http\Middleware\TrustProxies::class,

        // ❌ COMMENT OUT Laravel's default CORS - We're using custom middleware
        // \Illuminate\Http\Middleware\HandleCors::class,

        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\CorsForStorage::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            // Session and CSRF middleware in correct order
            \App\Http\Middleware\HandleRailwaySession::class,
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\RefreshCsrfToken::class,

            // ✅ BILLING: share billing banner with every web view.
            // Runs LAST in the group so it sees the fully booted
            // session + auth state. Its output is a shared view
            // variable, not a redirect, so order is not critical for
            // correctness — but placing it last avoids recomputation
            // if an earlier middleware short-circuits.
            \App\Http\Middleware\ExposeBillingBanner::class,
        ],

        'api' => [
            // ✅ CORS MUST COME FIRST in the API group
            \App\Http\Middleware\CorsMiddleware::class,

            // ✅ Force JSON responses (critical for API)
            \App\Http\Middleware\ForceJsonResponse::class,

            // ✅ Rate limiting for API protection
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',

            // ✅ Route binding
            \Illuminate\Routing\Middleware\SubstituteBindings::class,

            // ✅ Sanctum for API token authentication
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

            // ✅ API logging for debugging (only in development)
            \App\Http\Middleware\LogApiRequests::class,

            // ✅ Debug database queries (only in development)
            \App\Http\Middleware\DebugDatabaseQueries::class,

            // ✅ BILLING: API clients need the same enforcement as web.
            // ExposeBillingBanner is a no-op on JSON responses, but the
            // enforcement middlewares below run for authenticated API
            // requests. They detect `expectsJson()` and return JSON
            // instead of redirects.
            \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        // Laravel default middleware
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Sanctum middleware for API
        'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

        // ✅ Rate limiting variations
        'api.rate.limit' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60,1',
        'api.rate.limit.strict' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':30,1',
        'api.rate.limit.login' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':5,1',

        // ✅ API-specific middleware
        'api.json' => \App\Http\Middleware\ForceJsonResponse::class,
        'api.cors' => \App\Http\Middleware\CorsMiddleware::class,

        // Your existing custom middleware
        'multi.auth.user' => \App\Http\Middleware\MultiAuthUser::class,
        'developer' => \App\Http\Middleware\DeveloperOnly::class,
        'log.api' => \App\Http\Middleware\LogApiRequests::class,

        'csrf.exempt' => \App\Http\Middleware\BypassCsrfForTesting::class,

        'security.supervisor' => \App\Http\Middleware\SecuritySupervisorMiddleware::class,
        'cache.headers' => \App\Http\Middleware\CacheHeaders::class,

        // ✅ Root sanitation supervisor only (admins bypass inside the middleware)
        //    Applied on /sanitation/settings/* routes.
        'root.sanitation' => \App\Http\Middleware\EnsureRootSanitationAccess::class,

        // ================================================================
        // ✅ BILLING: enforcement middleware
        // ================================================================
        //
        //  billing.superadmin  → restricts super admin (pay-only) and
        //                        admin staff (read-only) when the system
        //                        billing is overdue.
        //
        //  billing.passthrough → runs on landlord/tenant groups. Default
        //                        is passthrough (they must keep paying);
        //                        hard-lock is opt-in per agreement.
        //
        //  billing.banner      → shares the billing banner view variable.
        //                        Already in the 'web' group; exposed as a
        //                        named alias for edge cases where a route
        //                        needs to run outside the web group.

        'billing.superadmin'  => \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
        'billing.passthrough' => \App\Http\Middleware\EnsureLandlordTenantBillingPassthrough::class,
        'billing.banner'      => \App\Http\Middleware\ExposeBillingBanner::class,

        // ================================================================
        // ✅ BILLING: composite groups
        // ================================================================
        //
        // These aliases let routes stay readable:
        //
        //   Route::middleware('billing.admin')->group(...)
        //   Route::middleware('billing.landlord')->group(...)
        //   Route::middleware('billing.tenant')->group(...)
        //
        // Each group layers the correct enforcement middleware on top of
        // whatever the route already has. Register them here rather than
        // in RouteServiceProvider so they travel with the Kernel.

        'billing.admin'    => [
            'auth',
            'billing.superadmin',
        ],

        'billing.landlord' => [
            'auth',
            'billing.passthrough',
        ],

        'billing.tenant'   => [
            'auth',
            'billing.passthrough',
        ],

        'billing.developer' => [
            // Developer routes are never restricted by billing state —
            // the developer is the creditor. This group is a marker so
            // routes remain self-documenting and can grow later (e.g.
            // IP allowlisting on top).
            'auth',
            'developer',
        'family.link.active' => \App\Http\Middleware\VerifyFamilyLinkStillActive::class,
        ],
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var array<int, class-string|string>
     */
    protected $middlewarePriority = [
        // ✅ CORS MUST BE FIRST in priority
        \App\Http\Middleware\CorsMiddleware::class,

        // Laravel core priority middleware
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,

        // Sanctum priority
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

        // Custom middleware priority (ordered by execution)
        \App\Http\Middleware\HandleRailwaySession::class,
        \App\Http\Middleware\ForceJsonResponse::class,
        \App\Http\Middleware\LogApiRequests::class,
        \App\Http\Middleware\RefreshCsrfToken::class,
        \App\Http\Middleware\SecuritySupervisorMiddleware::class,
        \App\Http\Middleware\DebugDatabaseQueries::class,

        // ✅ NEW: runs after auth but before the controller
        \App\Http\Middleware\EnsureRootSanitationAccess::class,

        // ================================================================
        // ✅ BILLING: priority ordering
        // ================================================================
        //
        // Rules:
        //   - ExposeBillingBanner runs AFTER auth is resolved (so it can
        //     inspect the current user) but BEFORE enforcement (so views
        //     that are allowed to render still see the banner).
        //
        //   - EnsureSuperAdminBillingAccess runs AFTER SubstituteBindings
        //     and Authorize so route-level policies have already had a
        //     chance to run. If a policy already denied the request, we
        //     don't need to redirect to billing.
        //
        //   - EnsureLandlordTenantBillingPassthrough runs in the same
        //     slot — it is a passthrough by default and only acts when
        //     the hard-lock opt-in is active.

        \App\Http\Middleware\ExposeBillingBanner::class,
        \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
        \App\Http\Middleware\EnsureLandlordTenantBillingPassthrough::class,
    ];
}