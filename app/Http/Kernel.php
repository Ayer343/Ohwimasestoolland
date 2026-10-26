<?php
// app/Http/Kernel.php
//
// ⚠️  LEGACY FILE — READ THIS BEFORE EDITING.
//
// On Laravel 11+, middleware configuration moved into bootstrap/app.php.
// This file is kept ONLY for the framework's backward-compatibility shim,
// which still reads TWO arrays from it:
//
//     ✅ $middlewareGroups   → merged into the runtime groups
//     ✅ $routeMiddleware    → merged into the runtime alias list
//
// But this file's priority array is NOT read at runtime:
//
//     ❌ $middlewarePriority → IGNORED. The framework reads priority
//                              exclusively from bootstrap/app.php.
//
// Concretely:
//
//   • If you register a NEW alias here, it will resolve at runtime. ✅
//   • If you register a NEW middleware GROUP here, it will resolve. ✅
//   • If you add an entry to $middlewarePriority here, it will be
//     silently discarded. ❌
//
// ⇒ To change middleware ORDERING, edit bootstrap/app.php:
//       ->withMiddleware(function (Middleware $middleware) {
//           $middleware->appendToPriorityList(...);
//       })
//
// The family-link middleware pair (VerifyFamilyLinkStillActive +
// EnsureFamilyLinkPermission) is authoritative in bootstrap/app.php.
// The aliases are ALSO listed here so the framework's compat shim
// resolves them, but their priority lives in bootstrap/app.php.
//
// ✅ NEW (2026-10-13): the two-factor setup middleware pair
//    (EnsureTwoFactorSetupCompleted + RedirectIfTwoFactorSetupRequired)
//    follows the SAME pattern. Aliases and group membership live here,
//    priority lives in bootstrap/app.php.
//
// ✅ NEW (2026-10-18): the AD SPACE middleware trio
//    (RequireAdSpaceEnabled + EnsureAdCampaignOwnership +
//    TrackAdCampaignView) follows the SAME pattern. Aliases live here
//    as INDIVIDUAL aliases — there are NO composite groups for AD.
//    Priority lives in bootstrap/app.php.
//
// ⚠️  DO NOT add `'ad.landlord' => [...]` or `'ad.public' => [...]`
//    to $routeMiddleware. Composite aliases in $routeMiddleware are
//    NOT supported on Laravel 11+ — the resolver tries to instantiate
//    the alias name as a PHP class and fails with:
//        Target class [ad.landlord] does not exist
//    Inline the individual aliases in routes/web.php instead.
//
// If you're debugging why a middleware isn't running in the order you
// expect, check bootstrap/app.php FIRST — this file is not the source
// of truth for ordering on your Laravel version.

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * NOTE: this array IS read by Laravel's Kernel constructor and merged
     *       into the global middleware stack.
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
     * Merged into the runtime groups by Laravel's Kernel shim.
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

            // ✅ NEW (2026-10-13): mandatory 2FA setup confinement.
            //
            // Runs after the session is started and after SubstituteBindings
            // so it can inspect route names. When the session carries the
            // `2fa_setup_required:user_id` handoff key, it confines the
            // user to the 2FA setup routes; otherwise it is a no-op.
            //
            // Deliberately placed AFTER RefreshCsrfToken so a stale CSRF
            // token never traps the user inside the setup flow.
            //
            // Deliberately placed BEFORE ExposeBillingBanner: if the user
            // is in mandatory setup, we never render the app chrome
            // (which includes the billing banner).
            \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,

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

            // ✅ NEW (2026-10-13): 2FA setup confinement for API clients.
            //
            // When an API caller is in a mandatory-setup state, this
            // middleware returns a 428 JSON response with a
            // `two_factor_setup_required: true` flag rather than
            // attempting to redirect (which API clients can't follow).
            //
            // Placed after Sanctum so the middleware can read the
            // authenticated user, and before the billing middlewares
            // so a 2FA-blocked request never reaches billing enforcement.
            \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,

            // ✅ BILLING: API clients need the same enforcement as web.
            // ExposeBillingBanner is a no-op on JSON responses, but the
            // enforcement middlewares below run for authenticated API
            // requests. They detect `expectsJson()` and return JSON
            // instead of redirects.
            \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
        ],

        // ============================================================
        // ✅ AD SPACE
        // ------------------------------------------------------------
        // There is NO composite `ad.landlord` or `ad.public` group.
        // The three individual aliases (`ad.enabled`, `ad.campaign.owner`,
        // `ad.track.view`) are applied explicitly per route in
        // routes/web.php. This avoids the Laravel 11+ alias resolver's
        // inability to handle composite aliases in $routeMiddleware.
        // ============================================================
    ];

    /**
     * The application's route middleware (aliases).
     *
     * Merged into the runtime alias list by Laravel's Kernel shim.
     *
     * If an alias here isn't resolving, verify that the class exists
     * at the given namespace and that the file hasn't been moved.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        // Laravel default middleware
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // ✅ Custom cache headers (overrides Laravel's default SetCacheHeaders).
        //    NOTE: previously declared twice — the duplicate Laravel default
        //    `\Illuminate\Http\Middleware\SetCacheHeaders::class` was removed
        //    because it was being silently overwritten by this entry. If you
        //    need the Laravel default too, register it under a different alias
        //    (e.g. 'cache.headers.default').
        'cache.headers' => \App\Http\Middleware\CacheHeaders::class,

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

        // ✅ Root sanitation supervisor only (admins bypass inside the middleware)
        //    Applied on /sanitation/settings/* routes.
        'root.sanitation' => \App\Http\Middleware\EnsureRootSanitationAccess::class,

        // ================================================================
        // ✅ FAMILY LINK — landlord property routes that also grant access
        //    to linked family members.
        // ================================================================
        'family.link.active' => \App\Http\Middleware\VerifyFamilyLinkStillActive::class,
        'family.link.permission' => \App\Http\Middleware\EnsureFamilyLinkPermission::class,

        // ================================================================
        // ✅ BILLING: enforcement middleware
        // ================================================================
        'billing.superadmin'  => \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
        'billing.passthrough' => \App\Http\Middleware\EnsureLandlordTenantBillingPassthrough::class,
        'billing.banner'      => \App\Http\Middleware\ExposeBillingBanner::class,

        // ================================================================
        // ✅ BILLING: composite groups
        // ================================================================
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
            'auth',
            'developer',
        ],

        // ================================================================
        // ✅ NEW (2026-10-13): TWO-FACTOR SETUP — middleware aliases
        // ================================================================
        //
        //  The setup flow uses TWO middleware that work as a pair:
        //
        //    two_factor.setup.required
        //      → Guards the setup routes. Requires the session to carry
        //        `2fa_setup_required:user_id` (set by LoginController
        //        when the developer policy mandates 2FA and the user
        //        hasn't enrolled yet). Rejects everyone else with a
        //        redirect back to /login. This is the *gate*.
        //
        //    two_factor.setup.completed
        //      → Runs on every web request (already in the 'web' group
        //        above). If the session still has the handoff key, it
        //        confines the user to the setup routes. This is the
        //        *confinement*.
        //
        //  Together they implement the "you may not use the app until
        //  you've enrolled" contract without a full middleware swap.

        'two_factor.setup.required' => \App\Http\Middleware\RequireTwoFactorSetup::class,
        'two_factor.setup.completed' => \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,

        // ================================================================
        // ✅ NEW (2026-10-13): TWO-FACTOR SETUP — composite group
        // ================================================================
        //
        //  Convenience alias for the setup-only route group. Layers the
        //  gate on top of the web group so setup routes are:
        //
        //    - session-aware (web group)
        //    - gated on the handoff key (two_factor.setup.required)
        //
        //  NOT wrapped in `auth` or `guest`: the user is intentionally
        //  logged OUT of the app during setup (LoginController logs them
        //  out before placing them here) but has a valid handoff token.
        //
        //  Usage:
        //
        //    Route::middleware('two_factor.setup')->group(function () {
        //        Route::get ('/2fa/setup-required', [TwoFactorController::class, 'showRequiredSetupForm'])->name('2fa.setup.required');
        //        Route::post('/2fa/setup-required/enable', [TwoFactorController::class, 'enableFromSetupRequired'])->name('2fa.setup.required.enable');
        //        Route::post('/2fa/setup-required/verify', [TwoFactorController::class, 'verifySetupRequired'])->name('2fa.setup.required.verify');
        //        Route::post('/2fa/setup-required/cancel', [TwoFactorController::class, 'cancelRequiredSetup'])->name('2fa.setup.required.cancel');
        //    });

        'two_factor.setup' => [
            'web',
            'two_factor.setup.required',
        ],

        // ================================================================
        // ✅ FAMILY LINK: composite group (commented out, documented)
        // ================================================================
        //
        // A convenience alias that layers the pair in the correct order.
        // Uncomment to enable it.
        //
        // 'family.link' => [
        //     'family.link.active',
        //     'family.link.permission',
        // ],

        // ================================================================
        // ✅ NEW (2026-10-18): AD SPACE — middleware aliases
        // ================================================================
        //
        //  Three focused middlewares that gate the landlord + public ad
        //  routes. They are NOT added to the global 'web' group — they
        //  are opt-in per route, so the rest of the app pays zero cost.
        //
        //    ad.enabled
        //      → 404s if `SystemSetting::isAdSpaceEnabled()` is false.
        //        Applied to every landlord ad route so that turning the
        //        feature off in admin settings instantly makes the
        //        landlord dashboard unreachable without a code change.
        //
        //    ad.campaign.owner
        //      → Reads {campaign} or {uuid} from the route, resolves the
        //        AdCampaign, and 403s if the authenticated landlord
        //        doesn't own it. Prevents UUID enumeration.
        //
        //    ad.track.view
        //      → Increments `views_count` on the campaign whose `uuid`
        //        matches the `?campaign=` query param OR the campaign
        //        that referred this visit (via `referer`). Applied to
        //        `property-units.public-show` so ad-driven views are
        //        tracked without polluting the click-tracker endpoint.
        //
        //  ⚠️  These are the ONLY AD aliases. Do NOT register composite
        //     groups (`ad.landlord`, `ad.public`) in $routeMiddleware —
        //     the Laravel 11+ alias resolver tries to instantiate the
        //     alias name as a class and fails with:
        //         Target class [ad.landlord] does not exist
        //     Inline the individual aliases in routes/web.php instead.

        'ad.enabled'          => \App\Http\Middleware\RequireAdSpaceEnabled::class,
        'ad.campaign.owner'   => \App\Http\Middleware\EnsureAdCampaignOwnership::class,
        'ad.track.view'       => \App\Http\Middleware\TrackAdCampaignView::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * ⚠️  ON LARAVEL 11+: THIS ARRAY IS NOT READ AT RUNTIME.
     *
     *     Priority ordering is authoritative in bootstrap/app.php:
     *
     *         ->withMiddleware(function (Middleware $middleware) {
     *             $middleware->appendToPriorityList(...);
     *             $middleware->prependToPriorityList(...);
     *         })
     *
     *     This array is kept ONLY so the legacy Kernel shim has
     *     something to merge during boot. Its contents are ignored for
     *     ordering purposes on modern Laravel — if you add an entry here
     *     and the middleware still runs in the wrong order, that's why.
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

        // ✅ Root sanitation access — runs after auth but before the controller
        \App\Http\Middleware\EnsureRootSanitationAccess::class,

        // ================================================================
        // ✅ FAMILY LINK: ordering (documentation only on Laravel 11+)
        // ================================================================
        \App\Http\Middleware\VerifyFamilyLinkStillActive::class,
        \App\Http\Middleware\EnsureFamilyLinkPermission::class,

        // ================================================================
        // ✅ NEW (2026-10-13): TWO-FACTOR SETUP — ordering
        // ================================================================
        //
        // ⚠️  Documentation only on Laravel 11+. Authoritative ordering
        //     lives in bootstrap/app.php.
        \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,
        \App\Http\Middleware\RequireTwoFactorSetup::class,

        // ================================================================
        // ✅ BILLING: priority ordering
        // ================================================================
        \App\Http\Middleware\ExposeBillingBanner::class,
        \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
        \App\Http\Middleware\EnsureLandlordTenantBillingPassthrough::class,

        // ================================================================
        // ✅ NEW (2026-10-18): AD SPACE — priority ordering
        // ================================================================
        //
        // ⚠️  Documentation only on Laravel 11+. Authoritative ordering
        //     lives in bootstrap/app.php.
        //
        //   RequireAdSpaceEnabled     → 404 short-circuit if disabled.
        //   EnsureAdCampaignOwnership → resolves {uuid}, 403s if not owner.
        //   TrackAdCampaignView       → increments views_count. Runs LAST
        //                               so a 403/404 never touches analytics.
        \App\Http\Middleware\RequireAdSpaceEnabled::class,
        \App\Http\Middleware\EnsureAdCampaignOwnership::class,
        \App\Http\Middleware\TrackAdCampaignView::class,
    ];
}