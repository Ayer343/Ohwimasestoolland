<?php
// bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // =============================================
        // ✅ CRITICAL FIX: Register HandleCors globally
        // =============================================
        $middleware->prepend([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // =============================================
        // TRUST PROXIES
        // =============================================
        $middleware->prependToGroup('web', [
            \App\Http\Middleware\TrustProxies::class,
        ]);

        $middleware->prependToGroup('api', [
            \App\Http\Middleware\TrustProxies::class,
        ]);

        // =============================================
        // SANCTUM STATEFUL
        // =============================================
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // =============================================
        // CUSTOM APPENDED MIDDLEWARE
        // =============================================

        // ✅ NEW (2026-10-13): mandatory 2FA setup confinement.
        //
        // Placed at the FRONT of the `web` appended set (before
        // TrackUserActivity / emergency / maintenance) so a user who is
        // confined to 2FA setup never triggers activity tracking, never
        // hits emergency/maintenance short-circuits, and never sees the
        // billing banner. The middleware is a no-op when the session
        // has no `2fa_setup_required:*` handoff key.
        //
        // Deliberately NOT gated by billing: a user in overdue-billing
        // state must still be able to complete 2FA setup. That is a
        // self-protection action, not an administrative write.
        //
        // ⚠️ Only applied to the `web` group. See the `api` group below
        //    for why it is deliberately NOT appended there.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,
            \App\Http\Middleware\TrackUserActivity::class,
            \App\Http\Middleware\GlobalEmergencyMode::class,
            \App\Http\Middleware\GlobalMaintenanceMode::class,
        ]);

        // ✅ UPDATED (2026-10-15): `EnsureTwoFactorSetupCompleted` is
        //    REMOVED from the global `api` group.
        //
        // Why: the middleware was 401-ing every authenticated API
        // request for any user whose role is in
        // `config('auth.two_factor.force_for_types')` (0,1) but whose
        // `two_factor_enabled` column is still `false`. Because it ran
        // in the `api` middleware group, it fired BEFORE `auth:sanctum`
        // could even validate the Bearer token — so every protected
        // route returned 401, even with a perfectly valid token.
        //
        // 2FA enrollment enforcement is now opt-in: apply it to the
        // specific routes that need it, via the
        // `two_factor.setup.completed` alias or the
        // `two_factor.setup` middleware group (both declared below).
        //
        // This restores correct behavior for both mobile clients and
        // first-party SPA sessions: requests reach the route's own auth
        // middleware first, and 2FA setup prompts happen at the app
        // layer (via `/auth/check` and dashboard redirects) instead of
        // by refusing authenticated traffic.
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
            // \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,  // ← intentionally disabled
            \App\Http\Middleware\GlobalEmergencyMode::class,
            \App\Http\Middleware\GlobalMaintenanceMode::class,
        ]);

        // =============================================
        // MIDDLEWARE ALIASES
        // =============================================
        $middleware->alias([
            // Authentication & Authorization
            'multi.auth.user' => \App\Http\Middleware\MultiAuthUser::class,
            'json.response' => \App\Http\Middleware\ForceJsonResponse::class,
            'api.auth' => \App\Http\Middleware\ApiAuthentication::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,

            // Emergency Mode Middleware
            'check.emergency.mode' => \App\Http\Middleware\CheckEmergencyMode::class,
            'global.emergency.mode' => \App\Http\Middleware\GlobalEmergencyMode::class,
            'emergency.maintenance' => \App\Http\Middleware\EmergencyMaintenancePage::class,

            // Maintenance Mode Middleware
            'check.maintenance.mode' => \App\Http\Middleware\CheckMaintenanceMode::class,
            'global.maintenance.mode' => \App\Http\Middleware\GlobalMaintenanceMode::class,
            'maintenance.page' => \App\Http\Middleware\MaintenancePage::class,

            // Additional Middleware
            'throttle.api' => \App\Http\Middleware\ThrottleApiRequests::class,
            'validate.api.key' => \App\Http\Middleware\ValidateApiKey::class,
            'log.api.requests' => \App\Http\Middleware\LogApiRequests::class,

            // =============================================
            // ✅ FAMILY LINK — landlord property routes
            // =============================================
            // (unchanged — see comment in original file about priority
            //  being established in FamilyLinkMiddlewareServiceProvider)
            'family.link.active'     => \App\Http\Middleware\VerifyFamilyLinkStillActive::class,
            'family.link.permission' => \App\Http\Middleware\EnsureFamilyLinkPermission::class,

            // =============================================
            // ✅ NEW (2026-10-13): TWO-FACTOR SETUP
            // =============================================
            //
            //  Two middleware work as a pair:
            //
            //    two_factor.setup.completed
            //      → Confines the user to setup routes if the session
            //        carries `2fa_setup_required:user_id`. No-op
            //        otherwise. Apply to route groups that should
            //        enforce enrollment.
            //
            //    two_factor.setup.required
            //      → Gate for the setup routes themselves. Rejects any
            //        request that doesn't carry a valid handoff key.
            //        This is what stops direct URL access to
            //        `/2fa/setup-required`.
            //
            //  The `web` group applies `.completed` automatically (see
            //  above). The `api` group does NOT — apply it per-route
            //  where needed.
            'two_factor.setup.completed' => \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,
            'two_factor.setup.required'  => \App\Http\Middleware\RequireTwoFactorSetup::class,

            // =============================================
            // ✅ NEW (2026-10-18): AD SPACE
            // =============================================
            //
            //  Three INDIVIDUAL middleware. Deliberately NOT wrapped in
            //  composite groups (`ad.landlord`, `ad.public`): Laravel 11's
            //  alias resolver maps names to classes, not arrays, so a
            //  composite alias would throw
            //  `Target class [ad.landlord] does not exist`.
            //
            //  Apply them explicitly in routes/web.php:
            //
            //    // Landlord group:
            //    Route::middleware(['auth', 'multi.auth.user:2', 'ad.enabled'])
            //        ->prefix('landlord')->name('landlord.')->group(...)
            //
            //    // {uuid} routes:
            //    Route::middleware('ad.campaign.owner')->group(...)
            //
            //    // Public unit page:
            //    Route::middleware(['ad.enabled', 'ad.track.view'])
            //        ->get('/properties/units/{id}', ...)
            //
            //  Ordering is enforced via $middleware->priority() below.
            'ad.enabled'         => \App\Http\Middleware\RequireAdSpaceEnabled::class,
            'ad.campaign.owner'  => \App\Http\Middleware\EnsureAdCampaignOwnership::class,
            'ad.track.view'      => \App\Http\Middleware\TrackAdCampaignView::class,
        ]);

        // =============================================
        // ✅ EXPLICIT PRIORITY ORDERING
        // =============================================
        //
        // ⚠️  This is the supported way to declare middleware ordering on
        //     Laravel 11/12. `appendToPriorityList()` inside this closure
        //     is silently dropped on some framework patch versions (see
        //     the header comment in app/Http/Kernel.php). The
        //     `priority([...])` call below is authoritative.
        //
        // Rules that matter here:
        //
        //   1. StartSession runs BEFORE EnsureTwoFactorSetupCompleted,
        //      because the latter reads `2fa_setup_required:*` from the
        //      session.
        //
        //   2. SubstituteBindings runs BEFORE EnsureTwoFactorSetupCompleted,
        //      because the latter calls `$request->route()->getName()`.
        //
        //   3. EnsureTwoFactorSetupCompleted runs BEFORE any billing
        //      middleware, so a user in overdue-billing state is pushed
        //      into 2FA setup rather than into the billing dashboard.
        //      Billing restricts administrative writes; 2FA setup is
        //      self-protection and must not be blocked.
        //
        //   4. RequireTwoFactorSetup runs AFTER StartSession and
        //      SubstituteBindings (same reasons as #1 and #2). It is
        //      otherwise independent — it only runs on the
        //      `two_factor.setup` route group, which is not part of
        //      `web`.
        //
        //   5. AD Space middleware runs AFTER auth and AFTER session
        //      init. `RequireAdSpaceEnabled` reads SystemSetting (needs
        //      DB, so after bindings is fine). `EnsureAdCampaignOwnership`
        //      reads $request->user() (needs auth). `TrackAdCampaignView`
        //      runs LAST because it mutates DB state and should only fire
        //      if everything else passed.
        //
        // The full list below is the Laravel 11 default priority list
        // with our custom middleware spliced into the correct slots. If
        // you later add a middleware that must run before or after one
        // of ours, edit this array — do not edit Kernel.php.
        $middleware->priority([
            // --- Framework bootstrap ---
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,

            // --- Framework routing & rate limiting ---
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,

            // --- Custom: session & CSRF shims ---
            \App\Http\Middleware\HandleRailwaySession::class,
            \App\Http\Middleware\RefreshCsrfToken::class,

            // --- Sanctum ---
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

            // ✅ 2FA setup confinement — after session & bindings, before
            //    any custom enforcement middleware.
            \App\Http\Middleware\EnsureTwoFactorSetupCompleted::class,

            // ✅ 2FA setup gate — only runs on the `two_factor.setup`
            //    route group. Same ordering constraints as its sibling.
            \App\Http\Middleware\RequireTwoFactorSetup::class,

            // --- Custom: diagnostics & logging ---
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\LogApiRequests::class,
            \App\Http\Middleware\SecuritySupervisorMiddleware::class,
            \App\Http\Middleware\DebugDatabaseQueries::class,

            // --- Custom: feature gates ---
            \App\Http\Middleware\EnsureRootSanitationAccess::class,
            \App\Http\Middleware\VerifyFamilyLinkStillActive::class,
            \App\Http\Middleware\EnsureFamilyLinkPermission::class,

            // ✅ AD Space — after auth, before billing.
            //    Order within the trio matters:
            //      1. RequireAdSpaceEnabled     (feature flag)
            //      2. EnsureAdCampaignOwnership (ownership check)
            //      3. TrackAdCampaignView       (analytics increment)
            \App\Http\Middleware\RequireAdSpaceEnabled::class,
            \App\Http\Middleware\EnsureAdCampaignOwnership::class,
            \App\Http\Middleware\TrackAdCampaignView::class,

            // --- Custom: billing enforcement (MUST run after 2FA & AD) ---
            \App\Http\Middleware\ExposeBillingBanner::class,
            \App\Http\Middleware\EnsureSuperAdminBillingAccess::class,
            \App\Http\Middleware\EnsureLandlordTenantBillingPassthrough::class,
        ]);

        // =============================================
        // CUSTOM MIDDLEWARE GROUPS
        // =============================================

        // ✅ Basic middleware groups
        $middleware->group('web-with-auth', [
            'web',
            'auth',
        ]);

        $middleware->group('api-with-auth', [
            'api',
            'auth:sanctum',
        ]);

        $middleware->group('api-multi-auth', [
            'api',
            'auth:sanctum',
            'multi.auth.user',
        ]);

        // =============================================
        // ✅ TWO-FACTOR SETUP GROUP
        // =============================================
        //
        //  Wraps the session-aware web group and adds the setup gate.
        //  NOT wrapped in `auth`: during mandatory setup the user is
        //  logged OUT of the application (LoginController calls
        //  Auth::logout() before writing the handoff keys). If we added
        //  `auth`, the Authenticate middleware would bounce the user to
        //  /login before the setup form ever rendered.
        //
        //  NOT wrapped in billing middleware: 2FA setup is self-
        //  protection and must not be blocked by overdue billing.
        //
        //  Usage:
        //
        //    Route::middleware('two_factor.setup')->group(function () {
        //        Route::get ('/2fa/setup-required',        [TwoFactorController::class, 'showRequiredSetupForm'])->name('2fa.setup.required');
        //        Route::post('/2fa/setup-required/enable', [TwoFactorController::class, 'enableFromSetupRequired'])->name('2fa.setup.required.enable');
        //        Route::post('/2fa/setup-required/verify', [TwoFactorController::class, 'verifySetupRequired'])->name('2fa.setup.required.verify');
        //        Route::post('/2fa/setup-required/cancel', [TwoFactorController::class, 'cancelRequiredSetup'])->name('2fa.setup.required.cancel');
        //    });
        $middleware->group('two_factor.setup', [
            'web',
            'two_factor.setup.required',
        ]);

        // =============================================
        // ROLE-BASED MIDDLEWARE GROUPS
        // =============================================
        $middleware->group('super-admin', ['web', 'auth', 'multi.auth.user:0']);
        $middleware->group('admin', ['web', 'auth', 'multi.auth.user:0,1']);
        $middleware->group('landlord', ['web', 'auth', 'multi.auth.user:2']);
        $middleware->group('tenant', ['web', 'auth', 'multi.auth.user:3']);
        $middleware->group('field-agent', ['web', 'auth', 'multi.auth.user:4']);
        $middleware->group('developer', ['web', 'auth', 'multi.auth.user:5']);
        $middleware->group('security', ['web', 'auth', 'multi.auth.user:6']);

        $middleware->group('api-super-admin', ['api', 'auth:sanctum', 'multi.auth.user:0']);
        $middleware->group('api-admin', ['api', 'auth:sanctum', 'multi.auth.user:0,1']);
        $middleware->group('api-landlord', ['api', 'auth:sanctum', 'multi.auth.user:2']);
        $middleware->group('api-tenant', ['api', 'auth:sanctum', 'multi.auth.user:3']);
        $middleware->group('api-field-agent', ['api', 'auth:sanctum', 'multi.auth.user:4']);
        $middleware->group('api-developer', ['api', 'auth:sanctum', 'multi.auth.user:5']);
        $middleware->group('api-security', ['api', 'auth:sanctum', 'multi.auth.user:6']);

        // =============================================
        // EMERGENCY MODE MIDDLEWARE GROUPS
        // =============================================
        $middleware->group('emergency-protected-web', ['web', 'auth', 'check.emergency.mode']);
        $middleware->group('emergency-protected-api', ['api', 'auth:sanctum', 'check.emergency.mode']);

        $emergencyModules = [
            'user_management' => 'User Management',
            'property_management' => 'Property Management',
            'payment_processing' => 'Payment Processing',
            'communication' => 'Communication',
            'reporting' => 'Reporting',
            'api' => 'API Services',
            'dashboard' => 'Dashboard',
            'authentication' => 'Authentication',
            'database' => 'Database',
            'queue' => 'Queue System',
            'storage' => 'File Storage',
            'cache' => 'Cache System',
            'email' => 'Email Service',
            'sms' => 'SMS Service',
            'whatsapp' => 'WhatsApp Service',
        ];

        foreach (array_keys($emergencyModules) as $module) {
            $middleware->group("emergency-{$module}", [
                'web',
                'auth',
                "check.emergency.mode:{$module}",
            ]);
        }

        foreach (array_keys($emergencyModules) as $module) {
            $middleware->group("api-emergency-{$module}", [
                'api',
                'auth:sanctum',
                "check.emergency.mode:{$module}",
            ]);
        }

        // =============================================
        // MAINTENANCE MODE MIDDLEWARE GROUPS
        // =============================================
        $middleware->group('maintenance-protected-web', ['web', 'auth', 'check.maintenance.mode']);
        $middleware->group('maintenance-protected-api', ['api', 'auth:sanctum', 'check.maintenance.mode']);

        foreach (array_keys($emergencyModules) as $module) {
            $middleware->group("maintenance-{$module}", [
                'web',
                'auth',
                "check.maintenance.mode:{$module}",
            ]);
        }

        foreach (array_keys($emergencyModules) as $module) {
            $middleware->group("api-maintenance-{$module}", [
                'api',
                'auth:sanctum',
                "check.maintenance.mode:{$module}",
            ]);
        }

        // =============================================
        // COMBINED PROTECTION GROUPS
        // =============================================
        $middleware->group('fully-protected-web', [
            'web', 'auth', 'check.emergency.mode', 'check.maintenance.mode',
        ]);

        $middleware->group('fully-protected-api', [
            'api', 'auth:sanctum', 'check.emergency.mode', 'check.maintenance.mode',
        ]);

        $middleware->group('critical-system-web', [
            'web', 'auth', 'multi.auth.user:0,5', 'check.emergency.mode', 'check.maintenance.mode',
        ]);

        $middleware->group('critical-system-api', [
            'api', 'auth:sanctum', 'multi.auth.user:0,5', 'check.emergency.mode', 'check.maintenance.mode',
        ]);

        // =============================================
        // MAINTENANCE PAGE GROUPS
        // =============================================
        $middleware->group('maintenance-page-web', ['web', 'maintenance.page']);
        $middleware->group('maintenance-page-api', ['api', 'maintenance.page']);
        $middleware->group('emergency-maintenance-web', ['web', 'emergency.maintenance']);
        $middleware->group('emergency-maintenance-api', ['api', 'emergency.maintenance']);

        // =============================================
        // API-SPECIFIC MIDDLEWARE GROUPS
        // =============================================
        $middleware->group('api-monitored', ['api', 'log.api.requests', 'throttle.api:60,1']);
        $middleware->group('api-secure', ['api', 'validate.api.key', 'throttle.api:30,1', 'log.api.requests']);
        $middleware->group('api-high-traffic', ['api', 'throttle.api:120,1']);
        $middleware->group('api-dev', ['api', 'auth:sanctum', 'multi.auth.user:5', 'log.api.requests']);
        $middleware->group('api-dev-debug', [
            'api', 'auth:sanctum', 'multi.auth.user:5', 'log.api.requests',
            \App\Http\Middleware\EnableDebugMode::class,
        ]);

        // =============================================
        // EXEMPTED ROUTES GROUPS
        // =============================================
        $middleware->group('exempt-emergency', ['web', 'auth', \App\Http\Middleware\ExemptFromEmergency::class]);
        $middleware->group('exempt-maintenance', ['web', 'auth', \App\Http\Middleware\ExemptFromMaintenance::class]);
        $middleware->group('api-exempt-emergency', ['api', 'auth:sanctum', \App\Http\Middleware\ExemptFromEmergency::class]);
        $middleware->group('api-exempt-maintenance', ['api', 'auth:sanctum', \App\Http\Middleware\ExemptFromMaintenance::class]);
        $middleware->group('public-web', ['web', 'global.emergency.mode', 'global.maintenance.mode']);
        $middleware->group('public-api', ['api', 'json.response', 'global.emergency.mode', 'global.maintenance.mode']);

        // =============================================
        // SYSTEM ADMINISTRATION GROUPS
        // =============================================
        $middleware->group('system-admin-web', [
            'web', 'auth', 'multi.auth.user:0,1,5', 'check.emergency.mode', 'check.maintenance.mode',
        ]);

        $middleware->group('system-admin-api', [
            'api', 'auth:sanctum', 'multi.auth.user:0,1,5', 'check.emergency.mode', 'check.maintenance.mode',
        ]);

        $middleware->group('emergency-management-web', [
            'web', 'auth', 'multi.auth.user:0,5', 'exempt-emergency',
        ]);

        $middleware->group('emergency-management-api', [
            'api', 'auth:sanctum', 'multi.auth.user:0,5', 'exempt-emergency',
        ]);

        $middleware->group('maintenance-management-web', [
            'web', 'auth', 'multi.auth.user:0,5', 'exempt-maintenance',
        ]);

        $middleware->group('maintenance-management-api', [
            'api', 'auth:sanctum', 'multi.auth.user:0,5', 'exempt-maintenance',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                if ($e instanceof \App\Exceptions\MaintenanceModeException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'maintenance' => $e->getMaintenanceData(),
                        'error_type' => get_class($e),
                        'timestamp' => now()->toISOString(),
                    ], 503);
                }

                if ($e instanceof \App\Exceptions\EmergencyModeException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'emergency' => $e->getEmergencyData(),
                        'error_type' => get_class($e),
                        'timestamp' => now()->toISOString(),
                    ], 423);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $statusCode = 401;
                } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    $statusCode = 403;
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $statusCode = 404;
                } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                    $statusCode = 422;
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_type' => get_class($e),
                    'timestamp' => now()->toISOString(),
                ], $statusCode);
            }

            if ($e instanceof \App\Exceptions\MaintenanceModeException) {
                return response()->view('errors.maintenance', [
                    'maintenance' => $e->getMaintenanceData(),
                ], 503);
            }

            if ($e instanceof \App\Exceptions\EmergencyModeException) {
                return response()->view('errors.emergency', [
                    'emergency' => $e->getEmergencyData(),
                ], 423);
            }
        });
    })->create();