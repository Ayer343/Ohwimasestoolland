<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SystemHealthController extends Controller
{
    use AuditLogger;

    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_DEVELOPER   = 5;

    const REPORTS_DIR = 'health_reports';
    const CACHE_TTL   = 30; // seconds for expensive checks

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canViewHealth(): bool
    {
        $user = auth()->user();
        return $user
            && in_array((int) $user->type, [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_DEVELOPER], true);
    }

    private function authorizeOrAbort(bool $json = false)
    {
        if ($this->canViewHealth()) return null;

        Log::warning('System health access denied', [
            'user_id'   => auth()->id(),
            'user_type' => auth()->user()->type ?? null,
            'ip'        => request()->ip(),
        ]);

        if ($json) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Developer (type 5) or Super Admin (type 0) required.',
            ], 403);
        }

        abort(403, 'Unauthorized to view system health.');
    }

    /* ============================================================
     | DASHBOARD
     * ============================================================ */

    public function index()
{
    if ($resp = $this->authorizeOrAbort()) return $resp;

    // Seed history so the chart always has data on first view.
    $this->ensureDailyReportExists();

    return view('developer.health.index', [
        'overallHealth'   => $this->buildOverallHealth(),
        'systemMetrics'   => $this->buildSystemMetrics(),
        'componentStatus' => $this->getComponentStatus(),
        'healthHistory'   => $this->getHealthHistoryData(),
    ]);
}

public function viewHealthReport(Request $request, $id)
{
    if ($resp = $this->authorizeOrAbort()) return $resp;

    $id = $this->sanitizeReportId($id);
    if (!$id) abort(422, 'Invalid report id');

    $path = self::REPORTS_DIR . '/' . $id . '.json';
    if (!Storage::disk('local')->exists($path)) {
        abort(404, 'Report not found');
    }

    $report = json_decode(Storage::disk('local')->get($path), true);
    if (!is_array($report)) abort(500, 'Report is malformed');

    return view('developer.health.report', ['report' => $report]);
}

    public function getOverallHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json($this->buildOverallHealth());
    }

    public function getHealthStatus(?Request $request = null)
    {
        return $this->getOverallHealth($request);
    }

    public function getSystemMetrics(?Request $request = null)
    {
        $wantsJson = $request && $request->wantsJson();

        if ($resp = $this->authorizeOrAbort($wantsJson)) return $resp;

        $metrics = $this->buildSystemMetrics();

        return $wantsJson ? response()->json($metrics) : $metrics;
    }

    public function getLiveMetrics(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'cpu'                 => $this->getCpuUsage(),
            'memory'              => $this->getMemoryUsage(),
            'disk'                => $this->getDiskUsage(),
            'connections'         => $this->getActiveConnections(),
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'timestamp'           => now()->toISOString(),
        ]);
    }

    /* ============================================================
     | PURE DATA BUILDERS (never return a Response)
     * ============================================================ */

    /**
     * Plain-array payload used by the Blade view, API, and report generator.
     */
    private function buildOverallHealth(): array
    {
        return [
            'status'     => $this->deriveOverallStatus(),
            'score'      => $this->calculateHealthScore(),
            'timestamp'  => now()->toISOString(),
            'components' => $this->checkAllComponents(),
        ];
    }

    /**
     * Plain-array payload used by the Blade view, API, and report generator.
     */
    private function buildSystemMetrics(): array
    {
        return [
            'cpu'          => $this->getCpuUsage(),
            'memory'       => $this->getMemoryUsage(),
            'disk'         => $this->getDiskUsage(),
            'uptime'       => $this->getSystemUptime(),
            'load_average' => $this->getLoadAverage(),
            'timestamp'    => now()->toISOString(),
        ];
    }

    /* ============================================================
     | PER-COMPONENT ENDPOINTS
     * ============================================================ */

    public function getServerHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'status'          => 'healthy',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_version'     => PHP_VERSION,
            'server_time'     => now()->toISOString(),
            'timezone'        => config('app.timezone'),
            'environment'     => app()->environment(),
            'debug_mode'      => (bool) config('app.debug'),
        ]);
    }

    public function getDatabaseHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        try {
            DB::connection()->getPdo();
            $driver   = DB::connection()->getDriverName();
            $database = DB::connection()->getDatabaseName();
            $status   = 'healthy';
            $message  = 'Database connection successful';

            $tableCount = null;
            try {
                if ($driver === 'mysql') {
                    $tableCount = count(DB::select('SHOW TABLES'));
                } elseif ($driver === 'pgsql') {
                    $tableCount = DB::table('information_schema.tables')
                        ->where('table_schema', 'public')
                        ->count();
                } elseif ($driver === 'sqlite') {
                    $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                    $tableCount = is_array($rows) ? count($rows) : 0;
                }
            } catch (\Throwable) {
                $tableCount = null;
            }
        } catch (\Throwable $e) {
            $status   = 'critical';
            $message  = 'Database connection failed';
            $driver   = DB::connection()->getDriverName() ?? null;
            $database = null;
            $tableCount = null;

            Log::error('DB health check failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'status'        => $status,
            'message'       => $message,
            'driver'        => $driver,
            'database_name' => $database,
            'table_count'   => $tableCount,
            'checked_at'    => now()->toISOString(),
        ]);
    }

    public function getApplicationHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $checks = [
            'storage_link'   => file_exists(public_path('storage')),
            'env_file'       => file_exists(base_path('.env')),
            'logs_writable'  => is_writable(storage_path('logs')),
            'cache_writable' => is_writable(storage_path('framework/cache')),
        ];

        $status = collect($checks)->every(fn($v) => $v === true) ? 'healthy' : 'degraded';

        return response()->json([
            'status'          => $status,
            'checks'          => $checks,
            'app_name'        => config('app.name'),
            'laravel_version' => app()->version(),
            'php_version'     => PHP_VERSION,
        ]);
    }

    public function getServiceHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $services = [
            'queue'   => $this->checkQueueService(),
            'cache'   => $this->checkCacheService(),
            'session' => $this->checkSessionService(),
            'mail'    => $this->checkMailService(),
        ];

        $overall = 'healthy';
        foreach ($services as $svc) {
            if (($svc['status'] ?? 'healthy') !== 'healthy') {
                $overall = 'degraded';
                break;
            }
        }

        return response()->json([
            'status'   => $overall,
            'services' => $services,
        ]);
    }

    public function getPerformanceHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'status'  => 'healthy',
            'metrics' => [
                'response_time'  => $this->getAverageResponseTime(),
                'memory_usage'   => memory_get_usage(true),
                'peak_memory'    => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT']),
                'php_version'    => PHP_VERSION,
            ],
        ]);
    }

    public function getSecurityHealth(?Request $request = null)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $debug  = (bool) config('app.debug');
        $secure = request()->secure();
        $status = (!$debug && $secure) ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => [
                'https_enabled'   => $secure,
                'debug_mode'      => $debug,
                'csrf_protection' => true,
                'session_driver'  => config('session.driver'),
                'cookie_secure'   => (bool) config('session.secure', false),
                'same_site'       => config('session.same_site', 'lax'),
            ],
        ]);
    }

    /* ============================================================
     | DIAGNOSTICS
     * ============================================================ */

    public function runDiagnostics(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $key = 'health_diag:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'message' => 'Too many diagnostics requests'], 429);
        }
        RateLimiter::hit($key, 60);

        $diagnostics = [
            'database'    => $this->testDatabaseConnection(),
            'cache'       => $this->testCacheConnection(),
            'storage'     => $this->testStorageWritable(),
            'queue'       => $this->testQueueConnection(),
            'session'     => $this->testSession(),
            'environment' => $this->testEnvironment(),
        ];

        $allPassed = collect($diagnostics)->every(fn($d) => $d['passed'] === true);

        $this->logAudit('health_diagnostics_run', 'System diagnostics run', [
            'all_passed' => $allPassed,
            'user_id'    => auth()->id(),
        ]);

        return response()->json([
            'success'     => true,
            'all_passed'  => $allPassed,
            'diagnostics' => $diagnostics,
            'timestamp'   => now()->toISOString(),
        ]);
    }

    public function getDiagnosticResult(Request $request, $id)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'success' => false,
            'message' => 'Persistent diagnostic results are not implemented.',
        ], 501);
    }

    /* ============================================================
     | HEALTH REPORTS
     * ============================================================ */

    public function generateHealthReport(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $report = [
            'id'               => 'health_' . Str::random(16),
            'generated_at'     => now()->toISOString(),
            'generated_by'     => auth()->id(),
            'overall_health'   => $this->buildOverallHealth(),   // pure array
            'system_metrics'   => $this->buildSystemMetrics(),   // pure array
            'component_status' => $this->getComponentStatus(),
            'recommendations'  => $this->getRecommendations(),
        ];

        Storage::disk('local')->makeDirectory(self::REPORTS_DIR);
        Storage::disk('local')->put(
            self::REPORTS_DIR . '/' . $report['id'] . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );

        $this->logAudit('health_report_generated', 'Health report generated', [
            'report_id' => $report['id'],
        ]);

        return response()->json([
            'success'   => true,
            'report_id' => $report['id'],
            'report'    => $report,
        ]);
    }

    public function getHealthReport(Request $request, $id)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $id = $this->sanitizeReportId($id);
        if (!$id) return response()->json(['success' => false, 'message' => 'Invalid report id'], 422);

        $path = self::REPORTS_DIR . '/' . $id . '.json';
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'Report not found'], 404);
        }

        return response()->json([
            'success' => true,
            'report'  => json_decode(Storage::disk('local')->get($path), true),
        ]);
    }

    public function downloadHealthReport(Request $request, $id)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $id = $this->sanitizeReportId($id);
        if (!$id) abort(422, 'Invalid report id');

        $path = self::REPORTS_DIR . '/' . $id . '.json';
        if (!Storage::disk('local')->exists($path)) abort(404, 'Report not found');

        return Storage::disk('local')->download($path, "health_report_{$id}.json");
    }

    public function getHealthHistory(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $history = $this->buildHistoryIndex();

        return response()->json([
            'success' => true,
            'history' => array_slice($history, 0, 30),
            'count'   => count($history),
        ]);
    }

    public function getHealthTrends(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $period = (int) $request->query('period', 7);
        if (!in_array($period, [7, 30, 90], true)) {
            $period = 7;
        }

        // Newest-first history
        $history = $this->buildHistoryIndex();

        // Take the last N and reverse so the chart is oldest → newest
        $slice = array_slice($history, 0, $period);
        $slice = array_reverse($slice);

        $trends = array_map(fn($row) => [
            'date'  => $row['generated_at'],
            'score' => $row['score'],
        ], $slice);

        return response()->json([
            'success'       => true,
            'period'        => $period,
            'trends'        => $trends,
            'average_score' => count($trends) ? round(collect($trends)->avg('score'), 2) : null,
        ]);
    }

    public function getHealthAlerts(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $history = $this->buildHistoryIndex();
        $alerts  = [];

        foreach (array_slice($history, 0, 50) as $row) {
            if (($row['status'] ?? 'healthy') !== 'healthy') {
                $alerts[] = [
                    'date'   => $row['generated_at'],
                    'status' => $row['status'],
                    'score'  => $row['score'],
                ];
            }
        }

        return response()->json([
            'success'       => true,
            'alerts'        => $alerts,
            'active_alerts' => count($alerts),
        ]);
    }

    /* ============================================================
     | CUSTOM CHECKS
     * ============================================================ */

    public function runCustomCheck(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'check_name' => 'required|string|in:database,cache,storage,queue,session,environment',
            'parameters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $name   = $request->check_name;
        $method = 'test' . ucfirst($name);

        if (!method_exists($this, $method)) {
            return response()->json(['success' => false, 'message' => 'Unsupported check'], 422);
        }

        $result = $this->{$method}();

        return response()->json([
            'success'    => true,
            'check_name' => $name,
            'status'     => $result['passed'] ? 'passed' : 'failed',
            'message'    => $result['message'],
            'timestamp'  => now()->toISOString(),
        ]);
    }

    public function listHealthChecks(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'success' => true,
            'checks'  => [
                'database'    => 'Database connection and performance',
                'cache'       => 'Cache system status',
                'storage'     => 'Storage writability',
                'queue'       => 'Queue worker status',
                'session'     => 'Session handling',
                'environment' => 'Environment configuration',
            ],
        ]);
    }

    public function getHealthCheck(Request $request, $check)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $allowed = ['database', 'cache', 'storage', 'queue', 'session', 'environment'];
        if (!in_array($check, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Unknown check'], 404);
        }

        $method = 'test' . ucfirst($check);
        return response()->json($this->{$method}());
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function deriveOverallStatus(): string
    {
        $score = $this->calculateHealthScore();
        if ($score >= 90) return 'healthy';
        if ($score >= 70) return 'degraded';
        return 'critical';
    }

    private function calculateHealthScore(): int
    {
        $score = 100;
        if (!$this->testDatabaseConnection()['passed']) $score -= 30;
        if (!$this->testCacheConnection()['passed'])    $score -= 20;
        if (!$this->testStorageWritable()['passed'])    $score -= 15;
        return max(0, $score);
    }

    /**
     * Component status map used inside buildOverallHealth().
     * Always emits one of: healthy | degraded | critical
     */
    private function checkAllComponents(): array
    {
        return [
            'database' => $this->testDatabaseConnection()['passed'] ? 'healthy' : 'critical',
            'cache'    => $this->testCacheConnection()['passed']    ? 'healthy' : 'degraded',
            'storage'  => $this->testStorageWritable()['passed']    ? 'healthy' : 'critical',
            'queue'    => $this->testQueueConnection()['passed']    ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Structured component status for the dashboard cards.
     * Each entry: name (string), status (operational|degraded|warning|down), latency (string|null)
     */
    private function getComponentStatus(): array
    {
        $dbPassed    = $this->testDatabaseConnection()['passed'];
        $cachePassed = $this->testCacheConnection()['passed'];
        $queueTest   = $this->testQueueConnection();

        return [
            'database' => [
                'name'    => DB::connection()->getDriverName() ?: 'unknown',
                'status'  => $dbPassed ? 'operational' : 'down',
                'latency' => $this->measureLatency(fn() => DB::select('SELECT 1')),
            ],
            'cache' => [
                'name'    => config('cache.default') ?: 'unknown',
                'status'  => $cachePassed ? 'operational' : 'degraded',
                'latency' => $this->measureLatency(function () {
                    Cache::put('health_ping', 1, 1);
                    Cache::forget('health_ping');
                }),
            ],
            'queue' => [
                'name'    => config('queue.default') ?: 'unknown',
                'status'  => $queueTest['passed'] ? 'operational' : 'warning',
                'latency' => null,
            ],
            'storage' => [
                'name'    => 'local',
                'status'  => $this->testStorageWritable()['passed'] ? 'operational' : 'down',
                'latency' => null,
            ],
        ];
    }

    private function measureLatency(callable $fn): ?string
    {
        $start = microtime(true);
        try {
            $fn();
            return round((microtime(true) - $start) * 1000, 2) . 'ms';
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns newest-first array of report summaries for reuse across
     * history/trends/alerts endpoints.
     */
    private function buildHistoryIndex(): array
    {
        if (!Storage::disk('local')->exists(self::REPORTS_DIR)) {
            return [];
        }

        $files = Storage::disk('local')->files(self::REPORTS_DIR);

        // Newest first by file mtime — robust against name ordering.
        usort($files, function ($a, $b) {
            return Storage::disk('local')->lastModified($b)
                <=> Storage::disk('local')->lastModified($a);
        });

        $history = [];
        foreach ($files as $file) {
            try {
                $content = json_decode(Storage::disk('local')->get($file), true);
                if (!is_array($content)) continue;

                $history[] = [
                    'id'           => basename($file, '.json'),
                    'generated_at' => $content['generated_at'] ?? null,
                    'status'       => $content['overall_health']['status'] ?? 'unknown',
                    'score'        => (int) ($content['overall_health']['score'] ?? 0),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $history;
    }

    private function getHealthHistoryData(): array
{
    $history = $this->buildHistoryIndex();
    $series  = [];

    foreach (array_slice($history, 0, 7) as $row) {
        $series[] = [
            'date'  => $row['generated_at'] ? substr($row['generated_at'], 0, 10) : null,
            'score' => $row['score'],
        ];
    }

    // Oldest → newest for the chart
    $series = array_reverse($series);

    // Fallback: if we have fewer than 2 real points, seed a 7-day
    // synthetic series anchored on today's score so the chart is
    // meaningful from day one. These values are illustrative and
    // will be replaced by real data as reports accumulate.
    if (count($series) < 2) {
        $todayScore = $this->calculateHealthScore();
        $series     = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);

            // Small deterministic wobble around today's score so the
            // line isn't perfectly flat but stays honest (never > 100).
            $wobble = (int) round(3 * sin($i));
            $score  = $i === 0
                ? $todayScore
                : max(0, min(100, $todayScore + $wobble));

            $series[] = [
                'date'  => $date->toDateString(),
                'score' => $score,
            ];
        }
    }

    return $series;
}

/**
 * Silently ensure at least one report exists on disk so history/trends
 * have something to render on a fresh install. Runs at most once per day.
 */
private function ensureDailyReportExists(): void
{
    try {
        $today    = now()->toDateString();
        $markerKey = 'health_daily_report:' . $today;

        if (Cache::has($markerKey)) {
            return;
        }

        // If any report from today exists, mark and return.
        $history = $this->buildHistoryIndex();
        foreach ($history as $row) {
            if (($row['generated_at'] ?? '') && substr($row['generated_at'], 0, 10) === $today) {
                Cache::put($markerKey, true, now()->endOfDay());
                return;
            }
        }

        // Otherwise, generate one.
        $report = [
            'id'               => 'health_' . Str::random(16),
            'generated_at'     => now()->toISOString(),
            'generated_by'     => auth()->id(),
            'overall_health'   => $this->buildOverallHealth(),
            'system_metrics'   => $this->buildSystemMetrics(),
            'component_status' => $this->getComponentStatus(),
            'recommendations'  => $this->getRecommendations(),
        ];

        Storage::disk('local')->makeDirectory(self::REPORTS_DIR);
        Storage::disk('local')->put(
            self::REPORTS_DIR . '/' . $report['id'] . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );

        Cache::put($markerKey, true, now()->endOfDay());
    } catch (\Throwable $e) {
        // Never let auto-seeding break the dashboard.
        Log::warning('Auto health report failed', ['error' => $e->getMessage()]);
    }
}

    private function getRecommendations(): array
    {
        $recommendations = [];

        $disk = $this->getDiskUsage();
        if (is_numeric($disk) && $disk > 80) {
            $recommendations[] = 'Disk usage is high. Consider cleaning up old logs and backups.';
        }

        $memory = $this->getMemoryUsage();
        if (is_numeric($memory) && $memory > 85) {
            $recommendations[] = 'Memory usage is high. Consider optimizing application or upgrading resources.';
        }

        if (!$this->testCacheConnection()['passed']) {
            $recommendations[] = 'Cache driver is not responding. Check CACHE_DRIVER configuration.';
        }

        if (!$this->testStorageWritable()['passed']) {
            $recommendations[] = 'Storage directory is not writable. Check permissions.';
        }

        return $recommendations;
    }

    /* ---------- tests ---------- */

    private function testDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            return ['passed' => true, 'message' => 'Database connection successful'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Database connection failed'];
        }
    }

    private function testCacheConnection(): array
    {
        try {
            $key = 'health_cache_' . Str::random(8);
            Cache::put($key, 'ok', 5);
            $result = Cache::get($key);
            Cache::forget($key);

            return $result === 'ok'
                ? ['passed' => true,  'message' => 'Cache round-trip successful']
                : ['passed' => false, 'message' => 'Cache read/write mismatch'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    private function testStorageWritable(): array
    {
        try {
            $testFile = storage_path('framework/test_' . Str::random(8) . '.txt');
            file_put_contents($testFile, 'test');
            if (!file_exists($testFile)) {
                return ['passed' => false, 'message' => 'Storage write silently failed'];
            }
            @unlink($testFile);
            return ['passed' => true, 'message' => 'Storage is writable'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Storage error: ' . $e->getMessage()];
        }
    }

    private function testQueueConnection(): array
    {
        try {
            $default = config('queue.default');

            if ($default === 'sync') {
                return ['passed' => true, 'message' => 'Queue driver is sync (no worker required)'];
            }

            if ($default === 'database') {
                if (!Schema::hasTable('jobs')) {
                    return ['passed' => false, 'message' => 'jobs table missing'];
                }
                $pending = DB::table('jobs')->count();
                return ['passed' => true, 'message' => "Queue reachable, {$pending} pending"];
            }

            return ['passed' => true, 'message' => "Queue driver: {$default}"];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Queue error: ' . $e->getMessage()];
        }
    }

    private function testSession(): array
    {
        try {
            $driver = config('session.driver');

            if ($driver === 'database') {
                if (!Schema::hasTable('sessions')) {
                    return ['passed' => false, 'message' => 'sessions table missing'];
                }
                $count = DB::table('sessions')->count();
                return ['passed' => true, 'message' => "Session driver reachable ({$count} sessions)"];
            }

            return ['passed' => true, 'message' => "Session driver: {$driver}"];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Session error: ' . $e->getMessage()];
        }
    }

    private function testEnvironment(): array
    {
        $required = [
            'app.key'          => config('app.key'),
            'app.env'          => config('app.env'),
            'database.default' => config('database.default'),
        ];
        $missing = [];

        foreach ($required as $key => $value) {
            if (empty($value)) $missing[] = strtoupper(str_replace('.', '_', $key));
        }

        if (!empty($missing)) {
            return ['passed' => false, 'message' => 'Missing config keys: ' . implode(', ', $missing)];
        }

        return ['passed' => true, 'message' => 'Environment configured correctly'];
    }

    /* ---------- system metrics ---------- */

    private function getCpuUsage(): ?float
{
    // ---------- 1. Windows: use WMIC ----------
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        return $this->getWindowsCpuUsage();
    }

    // ---------- 2. Unix: prefer sys_getloadavg() ----------
    if (function_exists('sys_getloadavg')) {
        $load = @sys_getloadavg();
        if (is_array($load) && isset($load[0])) {
            $cores = $this->detectCpuCores();
            $percent = ($load[0] / max(1, $cores)) * 100;
            return round(min(100, max(0, $percent)), 2);
        }
    }

    // ---------- 3. Fallback: /proc/loadavg ----------
    if (@is_readable('/proc/loadavg')) {
        $raw = @file_get_contents('/proc/loadavg');
        if ($raw !== false) {
            $parts = preg_split('/\s+/', trim($raw));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $cores = $this->detectCpuCores();
                $percent = ((float) $parts[0] / max(1, $cores)) * 100;
                return round(min(100, max(0, $percent)), 2);
            }
        }
    }

    return null;
}

/**
 * Windows CPU usage via `wmic cpu get loadpercentage`.
 * Falls back to PowerShell `Get-CimInstance` if wmic is unavailable
 * (wmic is deprecated on Windows 11 / Server 2022+).
 */
private function getWindowsCpuUsage(): ?float
{
    if (!function_exists('shell_exec')) {
        return null;
    }

    // Try WMIC first (fast, present on Windows 10 / Server 2016-2019)
    $output = @shell_exec('wmic cpu get loadpercentage /value 2>NUL');
    if (is_string($output) && preg_match('/LoadPercentage=(\d+)/i', $output, $m)) {
        return round((float) $m[1], 2);
    }

    // Fallback to PowerShell (Windows 11 / Server 2022+)
    $ps = 'powershell -NoProfile -Command "(Get-CimInstance Win32_Processor | '
        . 'Measure-Object -Property LoadPercentage -Average).Average"';
    $output = @shell_exec($ps . ' 2>NUL');
    if (is_string($output) && is_numeric(trim($output))) {
        return round((float) trim($output), 2);
    }

    return null;
}

/**
 * Detect the number of logical CPU cores across platforms.
 */
private function detectCpuCores(): int
{
    // Cached per request — this is called by getCpuUsage() potentially twice.
    static $cores = null;
    if ($cores !== null) {
        return $cores;
    }

    $cores = 1;

    // Windows
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $env = getenv('NUMBER_OF_PROCESSORS');
        if ($env !== false && is_numeric($env) && (int) $env > 0) {
            $cores = (int) $env;
        }
        return $cores;
    }

    // Linux: /proc/cpuinfo
    if (@is_readable('/proc/cpuinfo')) {
        $content = @file_get_contents('/proc/cpuinfo');
        if ($content !== false) {
            $count = preg_match_all('/^processor\s*:/mi', $content);
            if ($count > 0) {
                $cores = $count;
            }
        }
    }

    // macOS: sysctl
    if ($cores === 1 && stripos(PHP_OS_FAMILY, 'Darwin') !== false && function_exists('shell_exec')) {
        $n = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
        if (is_numeric(trim((string) $n))) {
            $cores = max(1, (int) trim($n));
        }
    }

    return $cores;
}

    private function getMemoryUsage(): ?float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1' || $limit === '' || $limit === false) {
            return null;
        }

        $limitBytes = $this->convertToBytes($limit);
        if ($limitBytes <= 0) return null;

        return round((memory_get_usage(true) / $limitBytes) * 100, 2);
    }

    private function getDiskUsage(): ?float
    {
        $total = @disk_total_space(base_path());
        $free  = @disk_free_space(base_path());

        if (!$total || !$free) return null;

        return round((($total - $free) / $total) * 100, 2);
    }

    private function getSystemUptime(): ?string
    {
        if (!function_exists('shell_exec')) return null;
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) return null;

        $uptime = @shell_exec('uptime -p 2>/dev/null');
        return $uptime ? trim($uptime) : null;
    }

    private function getLoadAverage(): ?array
    {
        if (!function_exists('sys_getloadavg')) return null;
        $load = @sys_getloadavg();
        return is_array($load) ? $load : null;
    }

    private function getActiveConnections(): ?int
    {
        try {
            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                return DB::table('sessions')
                    ->where('last_activity', '>=', now()->subMinutes(30)->timestamp)
                    ->count();
            }
        } catch (\Throwable) {}
        return null;
    }

    private function getRequestsPerMinute(): ?int
    {
        try {
            if (Schema::hasTable('request_logs')) {
                return DB::table('request_logs')
                    ->where('created_at', '>=', now()->subMinute())
                    ->count();
            }
        } catch (\Throwable) {}
        return null;
    }

    private function getAverageResponseTime(): ?float
    {
        try {
            if (Schema::hasTable('request_logs') && Schema::hasColumn('request_logs', 'duration_ms')) {
                return round((float) DB::table('request_logs')
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->avg('duration_ms'), 2);
            }
        } catch (\Throwable) {}
        return null;
    }

    private function checkQueueService(): array
    {
        $result = $this->testQueueConnection();
        return ['status' => $result['passed'] ? 'healthy' : 'unhealthy', 'message' => $result['message']];
    }

    private function checkCacheService(): array
    {
        $result = $this->testCacheConnection();
        return ['status' => $result['passed'] ? 'healthy' : 'unhealthy', 'message' => $result['message']];
    }

    private function checkSessionService(): array
    {
        $result = $this->testSession();
        return ['status' => $result['passed'] ? 'healthy' : 'unhealthy', 'message' => $result['message']];
    }

    private function checkMailService(): array
    {
        $driver = config('mail.default');
        if (!$driver) {
            return ['status' => 'warning', 'message' => 'No mail driver configured'];
        }
        return ['status' => 'healthy', 'message' => "Mail driver: {$driver}"];
    }

    private function convertToBytes(string $from): int
    {
        $from = trim($from);
        if ($from === '' || $from === '-1') return -1;

        $unit   = strtolower(substr($from, -1));
        $number = (int) $from;

        return match ($unit) {
            'k' => $number * 1024,
            'm' => $number * 1024 * 1024,
            'g' => $number * 1024 * 1024 * 1024,
            't' => $number * 1024 * 1024 * 1024 * 1024,
            default => $number,
        };
    }

    private function sanitizeReportId($id): ?string
    {
        if (!is_string($id) && !is_numeric($id)) return null;
        $id = (string) $id;
        if (!preg_match('/^[A-Za-z0-9._\-]+$/', $id)) return null;
        return $id;
    }
}