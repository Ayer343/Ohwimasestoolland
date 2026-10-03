<?php

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
        //
        // Laravel's CORS middleware (Illuminate\Http\Middleware\HandleCors)
        // reads config/cors.php and attaches the Access-Control-* response
        // headers to every request whose path matches `config('cors.paths')`.
        //
        // In Laravel 11/12, HandleCors is *usually* auto-registered — but
        // when bootstrap/app.php defines a custom `withMiddleware()` closure
        // that calls `prependToGroup()` / `api(prepend:)` etc., the default
        // global stack can get lost. That's exactly what happened here: the
        // route:list output showed only:
        //     ⇂ api
        //     ⇂ Illuminate\Auth\Middleware\Authenticate:sanctum
        // with NO HandleCors anywhere in the stack.
        //
        // Prepend it explicitly to the global middleware stack so every
        // request — including 401s, 404s, and preflight OPTIONS — carries
        // the CORS headers the browser needs.
        $middleware->prepend([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // =============================================
        // TRUST PROXIES
        // =============================================
        // Add TrustProxies to the web middleware group first.
        // This MUST run before other middleware to detect HTTPS correctly.
        $middleware->prependToGroup('web', [
            \App\Http\Middleware\TrustProxies::class,
        ]);

        // Add TrustProxies to the API middleware group
        $middleware->prependToGroup('api', [
            \App\Http\Middleware\TrustProxies::class,
        ]);

        // =============================================
        // SANCTUM STATEFUL
        // =============================================
        // Sanctum's stateful middleware MUST be in the API group so that
        // `auth:sanctum` can properly parse Bearer tokens.
        // Without this, requests fall back to the `web` session guard and
        // throw "Unauthenticated." even with a perfectly valid token.
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // =============================================
        // CUSTOM APPENDED MIDDLEWARE
        // =============================================
        // Let Laravel handle default middleware automatically.
        // Only append your custom middleware to web group.
        $middleware->web(append: [
            \App\Http\Middleware\TrackUserActivity::class,
            \App\Http\Middleware\GlobalEmergencyMode::class,
            \App\Http\Middleware\GlobalMaintenanceMode::class,
        ]);

        // Let Laravel handle API middleware automatically.
        // Only add your custom middleware to API group.
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\GlobalEmergencyMode::class,
            \App\Http\Middleware\GlobalMaintenanceMode::class,
        ]);

        // =============================================
        // MIDDLEWARE ALIASES
        // =============================================
        // Register all custom middleware aliases.
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
        // ROLE-BASED MIDDLEWARE GROUPS
        // =============================================

        // ✅ Web role-based groups
        $middleware->group('super-admin', [
            'web',
            'auth',
            'multi.auth.user:0',
        ]);

        $middleware->group('admin', [
            'web',
            'auth',
            'multi.auth.user:0,1',
        ]);

        $middleware->group('landlord', [
            'web',
            'auth',
            'multi.auth.user:2',
        ]);

        $middleware->group('tenant', [
            'web',
            'auth',
            'multi.auth.user:3',
        ]);

        $middleware->group('field-agent', [
            'web',
            'auth',
            'multi.auth.user:4',
        ]);

        $middleware->group('developer', [
            'web',
            'auth',
            'multi.auth.user:5',
        ]);

        $middleware->group('security', [
            'web',
            'auth',
            'multi.auth.user:6',
        ]);

        // ✅ API role-based groups
        $middleware->group('api-super-admin', [
            'api',
            'auth:sanctum',
            'multi.auth.user:0',
        ]);

        $middleware->group('api-admin', [
            'api',
            'auth:sanctum',
            'multi.auth.user:0,1',
        ]);

        $middleware->group('api-landlord', [
            'api',
            'auth:sanctum',
            'multi.auth.user:2',
        ]);

        $middleware->group('api-tenant', [
            'api',
            'auth:sanctum',
            'multi.auth.user:3',
        ]);

        $middleware->group('api-field-agent', [
            'api',
            'auth:sanctum',
            'multi.auth.user:4',
        ]);

        $middleware->group('api-developer', [
            'api',
            'auth:sanctum',
            'multi.auth.user:5',
        ]);

        $middleware->group('api-security', [
            'api',
            'auth:sanctum',
            'multi.auth.user:6',
        ]);

        // =============================================
        // EMERGENCY MODE MIDDLEWARE GROUPS
        // =============================================

        // ✅ General emergency mode groups
        $middleware->group('emergency-protected-web', [
            'web',
            'auth',
            'check.emergency.mode',
        ]);

        $middleware->group('emergency-protected-api', [
            'api',
            'auth:sanctum',
            'check.emergency.mode',
        ]);

        // ✅ Emergency mode groups for specific modules (Web)
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

        // ✅ Emergency mode groups for specific modules (API)
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

        // ✅ General maintenance mode groups
        $middleware->group('maintenance-protected-web', [
            'web',
            'auth',
            'check.maintenance.mode',
        ]);

        $middleware->group('maintenance-protected-api', [
            'api',
            'auth:sanctum',
            'check.maintenance.mode',
        ]);

        // ✅ Maintenance mode groups for specific modules (Web)
        foreach (array_keys($emergencyModules) as $module) {
            $middleware->group("maintenance-{$module}", [
                'web',
                'auth',
                "check.maintenance.mode:{$module}",
            ]);
        }

        // ✅ Maintenance mode groups for specific modules (API)
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

        // ✅ Combined emergency and maintenance protection
        $middleware->group('fully-protected-web', [
            'web',
            'auth',
            'check.emergency.mode',
            'check.maintenance.mode',
        ]);

        $middleware->group('fully-protected-api', [
            'api',
            'auth:sanctum',
            'check.emergency.mode',
            'check.maintenance.mode',
        ]);

        // ✅ Critical system protection
        $middleware->group('critical-system-web', [
            'web',
            'auth',
            'multi.auth.user:0,5',
            'check.emergency.mode',
            'check.maintenance.mode',
        ]);

        $middleware->group('critical-system-api', [
            'api',
            'auth:sanctum',
            'multi.auth.user:0,5',
            'check.emergency.mode',
            'check.maintenance.mode',
        ]);

        // =============================================
        // MAINTENANCE PAGE GROUPS
        // =============================================

        $middleware->group('maintenance-page-web', [
            'web',
            'maintenance.page',
        ]);

        $middleware->group('maintenance-page-api', [
            'api',
            'maintenance.page',
        ]);

        $middleware->group('emergency-maintenance-web', [
            'web',
            'emergency.maintenance',
        ]);

        $middleware->group('emergency-maintenance-api', [
            'api',
            'emergency.maintenance',
        ]);

        // =============================================
        // API-SPECIFIC MIDDLEWARE GROUPS
        // =============================================

        $middleware->group('api-monitored', [
            'api',
            'log.api.requests',
            'throttle.api:60,1',
        ]);

        $middleware->group('api-secure', [
            'api',
            'validate.api.key',
            'throttle.api:30,1',
            'log.api.requests',
        ]);

        $middleware->group('api-high-traffic', [
            'api',
            'throttle.api:120,1',
        ]);

        $middleware->group('api-dev', [
            'api',
            'auth:sanctum',
            'multi.auth.user:5',
            'log.api.requests',
        ]);

        $middleware->group('api-dev-debug', [
            'api',
            'auth:sanctum',
            'multi.auth.user:5',
            'log.api.requests',
            \App\Http\Middleware\EnableDebugMode::class,
        ]);

        // =============================================
        // EXEMPTED ROUTES GROUPS
        // =============================================

        $middleware->group('exempt-emergency', [
            'web',
            'auth',
            \App\Http\Middleware\ExemptFromEmergency::class,
        ]);

        $middleware->group('exempt-maintenance', [
            'web',
            'auth',
            \App\Http\Middleware\ExemptFromMaintenance::class,
        ]);

        $middleware->group('api-exempt-emergency', [
            'api',
            'auth:sanctum',
            \App\Http\Middleware\ExemptFromEmergency::class,
        ]);

        $middleware->group('api-exempt-maintenance', [
            'api',
            'auth:sanctum',
            \App\Http\Middleware\ExemptFromMaintenance::class,
        ]);

        $middleware->group('public-web', [
            'web',
            'global.emergency.mode',
            'global.maintenance.mode',
        ]);

        $middleware->group('public-api', [
            'api',
            'json.response',
            'global.emergency.mode',
            'global.maintenance.mode',
        ]);

        // =============================================
        // SYSTEM ADMINISTRATION GROUPS
        // =============================================

        $middleware->group('system-admin-web', [
            'web',
            'auth',
            'multi.auth.user:0,1,5',
            'check.emergency.mode',
            'check.maintenance.mode',
        ]);

        $middleware->group('system-admin-api', [
            'api',
            'auth:sanctum',
            'multi.auth.user:0,1,5',
            'check.emergency.mode',
            'check.maintenance.mode',
        ]);

        $middleware->group('emergency-management-web', [
            'web',
            'auth',
            'multi.auth.user:0,5',
            'exempt-emergency',
        ]);

        $middleware->group('emergency-management-api', [
            'api',
            'auth:sanctum',
            'multi.auth.user:0,5',
            'exempt-emergency',
        ]);

        $middleware->group('maintenance-management-web', [
            'web',
            'auth',
            'multi.auth.user:0,5',
            'exempt-maintenance',
        ]);

        $middleware->group('maintenance-management-api', [
            'api',
            'auth:sanctum',
            'multi.auth.user:0,5',
            'exempt-maintenance',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling for API responses
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                // Handle maintenance mode exceptions
                if ($e instanceof \App\Exceptions\MaintenanceModeException) {
                    $statusCode = 503;
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'maintenance' => $e->getMaintenanceData(),
                        'error_type' => get_class($e),
                        'timestamp' => now()->toISOString(),
                    ], $statusCode);
                }

                // Handle emergency mode exceptions
                if ($e instanceof \App\Exceptions\EmergencyModeException) {
                    $statusCode = 423;
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'emergency' => $e->getEmergencyData(),
                        'error_type' => get_class($e),
                        'timestamp' => now()->toISOString(),
                    ], $statusCode);
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

            // Handle web exceptions for maintenance/emergency
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