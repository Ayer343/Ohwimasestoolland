<?php

/**
 * ============================================================================
 * SYSTEM HELPERS
 * ============================================================================
 *
 * Global helper functions extracted from routes/web.php.
 *
 * Declared in the GLOBAL namespace (no `namespace` statement) so existing
 * call sites keep working without modification.
 *
 * WHY THEY WERE MOVED HERE
 * ------------------------
 * Declaring functions inside routes/web.php causes
 * "Cannot redeclare function" fatal errors whenever Laravel re-bootstraps
 * the app in the same PHP process (e.g., when `php artisan config:cache`
 * runs at runtime from the admin UI).
 *
 * Moving them here:
 *  - Stops the fatal errors
 *  - Allows route caching (`php artisan route:cache`)
 *  - Follows Laravel conventions
 *
 * LOADED VIA
 * ----------
 * composer.json → autoload.files
 *
 *     "autoload": {
 *         "files": [
 *             "app/Helpers/SystemHelpers.php"
 *         ]
 *     }
 *
 * Then run: composer dump-autoload
 *
 * Every function is wrapped in `if (!function_exists(...))` to be safe
 * against double-inclusion (opcache, parallel autoloaders, tests, etc.).
 * ============================================================================
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/* ============================================================================
 | 1. SYSTEM USAGE ANALYTICS
 * ========================================================================== */

if (!function_exists('getSystemUsageAnalytics')) {
    function getSystemUsageAnalytics()
    {
        try {
            // Cache the results for 5 minutes to reduce database load
            return Cache::remember('analytics_system_usage', 300, function () {
                // Get real system metrics
                $cpu_usage = function_exists('sys_getloadavg') ? sys_getloadavg()[0] * 10 : rand(10, 80);
                $memory_usage = function_exists('memory_get_usage')
                    ? round(memory_get_usage() / 1024 / 1024, 2)
                    : rand(20, 90);

                // Get disk usage if possible
                $disk_total = function_exists('disk_total_space') ? disk_total_space('/') : null;
                $disk_free  = function_exists('disk_free_space')  ? disk_free_space('/')  : null;
                $disk_usage = ($disk_total && $disk_free)
                    ? round((($disk_total - $disk_free) / $disk_total) * 100, 2)
                    : rand(30, 95);

                // Get database stats
                $total_users        = \App\Models\User::count();
                $active_users       = \App\Models\User::where('last_login_at', '>', now()->subDays(30))->count();
                $total_properties   = \App\Models\Property::count();
                $total_transactions = \App\Models\Transaction::count();

                // Get session stats
                $active_sessions = DB::table('sessions')
                    ->where('last_activity', '>', now()->subMinutes(15)->timestamp)
                    ->count();

                return [
                    // System metrics
                    'cpu_usage'    => round($cpu_usage, 2),
                    'memory_usage' => $memory_usage,
                    'disk_usage'   => $disk_usage,
                    'uptime'       => function_exists('shell_exec')
                        ? trim(shell_exec('uptime -p') ?: 'unknown')
                        : rand(1, 30) . ' days',

                    // User metrics
                    'total_users'     => $total_users,
                    'active_users'    => $active_users,
                    'active_sessions' => $active_sessions,

                    // Property metrics
                    'total_properties'   => $total_properties,
                    'total_transactions' => $total_transactions,

                    // Storage metrics
                    'total_storage' => $disk_total
                        ? round($disk_total / 1024 / 1024 / 1024, 2) . ' GB'
                        : '500 GB',
                    'used_storage'  => $disk_total && $disk_free
                        ? round(($disk_total - $disk_free) / 1024 / 1024 / 1024, 2) . ' GB'
                        : '350 GB',

                    // Timestamp
                    'generated_at' => now()->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get system analytics: ' . $e->getMessage());

            // Return fallback data if something fails
            return [
                'cpu_usage'          => rand(10, 80),
                'memory_usage'       => rand(20, 90),
                'disk_usage'         => rand(30, 95),
                'uptime'             => rand(1, 30) . ' days',
                'total_users'        => \App\Models\User::count(),
                'active_users'       => \App\Models\User::where('last_login_at', '>', now()->subDays(30))->count(),
                'active_sessions'    => DB::table('sessions')
                    ->where('last_activity', '>', now()->subMinutes(15)->timestamp)
                    ->count(),
                'total_properties'   => \App\Models\Property::count(),
                'total_transactions' => \App\Models\Transaction::count(),
                'total_storage'      => '500 GB',
                'used_storage'       => '350 GB',
                'generated_at'       => now()->toIso8601String(),
            ];
        }
    }
}

/* ============================================================================
 | 2. USER ACTIVITY ANALYTICS
 * ========================================================================== */

if (!function_exists('getUserActivityAnalytics')) {
    function getUserActivityAnalytics()
    {
        return [
            'total_users'          => \App\Models\User::count(),
            'active_today'         => \App\Models\User::whereDate('last_login_at', today())->count(),
            'new_this_week'        => \App\Models\User::where('created_at', '>=', now()->subWeek())->count(),
            'avg_session_duration' => '15m 30s', // Placeholder
            'popular_features'     => [
                'Dashboard' => 85,
                'Reports'   => 72,
                'Settings'  => 65,
                'Billing'   => 58,
                'API'       => 42,
            ],
        ];
    }
}

/* ============================================================================
 | 3. API USAGE ANALYTICS
 * ========================================================================== */

if (!function_exists('getApiUsageAnalytics')) {
    function getApiUsageAnalytics()
    {
        return [
            'total_calls'       => \App\Models\ApiLog::count(),
            'calls_today'       => \App\Models\ApiLog::whereDate('created_at', today())->count(),
            'top_endpoint'      => \App\Models\ApiLog::groupBy('endpoint')
                ->selectRaw('endpoint, count(*) as count')
                ->orderByDesc('count')
                ->first()->endpoint ?? 'N/A',
            'error_rate'        => (\App\Models\ApiLog::where('status_code', '>=', 400)->count()
                / max(\App\Models\ApiLog::count(), 1)) * 100,
            'avg_response_time' => \App\Models\ApiLog::avg('response_time') ?? 0,
        ];
    }
}

/* ============================================================================
 | 4. BILLING TRENDS ANALYTICS
 * ========================================================================== */

if (!function_exists('getBillingTrendsAnalytics')) {
    function getBillingTrendsAnalytics()
    {
        return [
            'total_revenue'        => \App\Models\DeveloperBillingRecord::where('status', 'paid')->sum('amount'),
            'pending_invoices'     => \App\Models\DeveloperBillingRecord::where('status', 'pending')->count(),
            'avg_invoice_amount'   => \App\Models\DeveloperBillingRecord::avg('amount') ?? 0,
            'payment_success_rate' => (\App\Models\DeveloperBillingRecord::where('status', 'paid')->count()
                / max(\App\Models\DeveloperBillingRecord::count(), 1)) * 100,
            'monthly_trend'        => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'data'   => [1200, 1900, 3000, 5000, 2000, 3000],
            ],
        ];
    }
}

/* ============================================================================
 | 5. PERFORMANCE METRICS
 * ========================================================================== */

if (!function_exists('getPerformanceMetrics')) {
    function getPerformanceMetrics()
    {
        return [
            'page_load_time'       => rand(100, 500) . 'ms',
            'server_response_time' => rand(50, 200) . 'ms',
            'database_query_time'  => rand(10, 100) . 'ms',
            'uptime'               => '99.9%',
            'error_rate'           => '0.1%',
            'throughput'           => rand(100, 1000) . ' req/min',
        ];
    }
}

/* ============================================================================
 | 6. SYSTEM INFORMATION
 * ========================================================================== */

if (!function_exists('getSystemInfo')) {
    function getSystemInfo()
    {
        return [
            'os'       => php_uname('s'),
            'hostname' => php_uname('n'),
            'release'  => php_uname('r'),
            'version'  => php_uname('v'),
            'machine'  => php_uname('m'),
        ];
    }
}

/* ============================================================================
 | 7. PHP INFO
 * ========================================================================== */

if (!function_exists('getPhpInfo')) {
    function getPhpInfo()
    {
        return [
            'version'          => PHP_VERSION,
            'sapi'             => PHP_SAPI,
            'memory_limit'     => ini_get('memory_limit'),
            'max_execution'    => ini_get('max_execution_time'),
            'upload_max_files' => ini_get('upload_max_filesize'),
            'post_max_size'    => ini_get('post_max_size'),
        ];
    }
}

/* ============================================================================
 | 8. LARAVEL INFO
 * ========================================================================== */

if (!function_exists('getLaravelInfo')) {
    function getLaravelInfo()
    {
        return [
            'cache_driver'     => config('cache.default'),
            'session_driver'   => config('session.driver'),
            'queue_driver'     => config('queue.default'),
            'mail_driver'      => config('mail.default'),
            'storage_link'     => file_exists(public_path('storage')) ? 'Linked' : 'Not Linked',
            'maintenance_mode' => app()->isDownForMaintenance() ? 'Enabled' : 'Disabled',
        ];
    }
}

/* ============================================================================
 | 9. ENVIRONMENT VARIABLES (with sensitive-value masking)
 * ========================================================================== */

if (!function_exists('getEnvironmentVariables')) {
    function getEnvironmentVariables()
    {
        $envVars = [];
        $envFile = base_path('.env');

        if (file_exists($envFile)) {
            $contents = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($contents as $line) {
                if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
                    $parts = explode('=', $line, 2);
                    $key   = trim($parts[0]);
                    $value = isset($parts[1]) ? trim($parts[1]) : '';

                    // Mask sensitive values
                    if (preg_match('/(key|secret|password|token|api|auth)/i', $key)) {
                        $value = '********';
                    }

                    $envVars[$key] = $value;
                }
            }
        }

        return $envVars;
    }
}

/* ============================================================================
 | 10. LOG CONTENT
 * ========================================================================== */

if (!function_exists('getLogContent')) {
    function getLogContent($type)
    {
        $logFile = '';

        switch ($type) {
            case 'laravel':
                $logFile = storage_path('logs/laravel.log');
                break;
            case 'developer':
                $logFile = storage_path('logs/developer.log');
                break;
            case 'error':
                $logFile = storage_path('logs/error.log');
                break;
            case 'access':
                $logFile = storage_path('logs/access.log');
                break;
        }

        if (file_exists($logFile) && is_readable($logFile)) {
            return file_get_contents($logFile);
        }

        return "Log file not found or not readable: " . basename($logFile);
    }
}

/* ============================================================================
 | 11. DATABASE INFO
 * ========================================================================== */

if (!function_exists('getDatabaseInfo')) {
    function getDatabaseInfo()
    {
        try {
            $connection = config('database.default');
            $config     = config("database.connections.{$connection}");

            return [
                'driver'    => $config['driver']    ?? 'Unknown',
                'host'      => $config['host']      ?? 'Unknown',
                'port'      => $config['port']      ?? 'Unknown',
                'database'  => $config['database']  ?? 'Unknown',
                'username'  => $config['username']  ?? 'Unknown',
                'charset'   => $config['charset']   ?? 'Unknown',
                'collation' => $config['collation'] ?? 'Unknown',
                'prefix'    => $config['prefix']    ?? 'None',
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

/* ============================================================================
 | 12. DATABASE TABLES
 * ========================================================================== */

if (!function_exists('getDatabaseTables')) {
    function getDatabaseTables()
    {
        try {
            return DB::select('SHOW TABLES');
        } catch (\Exception $e) {
            return [];
        }
    }
}

/* ============================================================================
 | 13. SYSTEM UPDATES CHECK
 * ========================================================================== */

if (!function_exists('checkForSystemUpdates')) {
    function checkForSystemUpdates()
    {
        return [
            'current_version'  => '1.0.0',
            'latest_version'   => '1.0.0',
            'update_available' => false,
            'security_updates' => [],
            'feature_updates'  => [],
            'last_checked'     => now()->toDateTimeString(),
        ];
    }
}

/* ============================================================================
 | 14. SYSTEM HEALTH CHECK
 * ========================================================================== */

if (!function_exists('performSystemHealthCheck')) {
    function performSystemHealthCheck()
    {
        $checks = [
            'database_connection' => false,
            'cache_connection'    => false,
            'storage_writable'    => false,
            'env_file_exists'     => false,
            'required_extensions' => [],
        ];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $checks['database_connection'] = true;
        } catch (\Exception $e) {
            $checks['database_connection'] = false;
            $checks['database_error']      = $e->getMessage();
        }

        // Check cache connection
        try {
            Cache::put('health_check', 'ok', 10);
            $checks['cache_connection'] = Cache::get('health_check') === 'ok';
        } catch (\Exception $e) {
            $checks['cache_connection'] = false;
        }

        // Check storage writable
        $checks['storage_writable'] = is_writable(storage_path());

        // Check .env file exists
        $checks['env_file_exists'] = file_exists(base_path('.env'));

        // Check required extensions
        $requiredExtensions = ['pdo', 'mbstring', 'xml', 'curl', 'json', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            $checks['required_extensions'][$ext] = extension_loaded($ext);
        }

        // Overall status
        $checks['all_passed'] = $checks['database_connection']
            && $checks['cache_connection']
            && $checks['storage_writable']
            && $checks['env_file_exists']
            && !in_array(false, $checks['required_extensions']);

        return $checks;
    }
}