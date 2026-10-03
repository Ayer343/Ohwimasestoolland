<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\DeveloperMonitoringService;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class DeveloperMonitoringController extends Controller
{
    use AuditLogger;

    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_DEVELOPER = 5;

    protected $monitoringService;

    public function __construct(DeveloperMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Check if user can access monitoring
     */
    private function canAccessMonitoring()
    {
        $user = auth()->user();
        
        // STRICT ENFORCEMENT: Only type 5 (developer) or type 0 (super admin) allowed
        return $user->type === self::USER_TYPE_DEVELOPER || $user->type === self::USER_TYPE_SUPER_ADMIN;
    }

    /**
     * Check if user can view error logs
     */
    private function canViewErrorLogs()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * Check if user can clear logs
     */
    private function canClearLogs()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * Check if user can generate reports
     */
    private function canGenerateReports()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * Check if user can view system metrics
     */
    private function canViewSystemMetrics()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * Check if user can run health checks
     */
    private function canRunHealthChecks()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * Check if user can clear cache
     */
    private function canClearCache()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * Check if user can optimize system
     */
    private function canOptimizeSystem()
    {
        return $this->canAccessMonitoring();
    }

    /**
     * View system monitoring dashboard
     */
    public function monitoringDashboard()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canAccessMonitoring()) {
                Log::warning('Monitoring dashboard access denied - invalid user type', [
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip' => request()->ip()
                ]);
                abort(403, 'Unauthorized to view monitoring dashboard. You must be a developer (type 5) or super admin (type 0).');
            }
            
            $systemMetrics = $this->monitoringService->getDashboardMetrics();
            $performanceData = $this->monitoringService->getPerformanceData();
            $errorLogs = $this->monitoringService->getRecentErrors(50);
            $activeAlerts = $this->monitoringService->getActiveAlerts();
            $usageStatistics = $this->monitoringService->getUsageStatistics();
            
            $this->logAudit('monitoring_viewed', 'Monitoring dashboard viewed', [
                'user_type' => auth()->user()->type,
                'user_type_name' => auth()->user()->type === self::USER_TYPE_DEVELOPER ? 'Developer' : 'Super Admin'
            ]);
            
            return view('developer.monitoring.dashboard', compact(
                'systemMetrics',
                'performanceData',
                'errorLogs',
                'activeAlerts',
                'usageStatistics'
            ));
            
        } catch (\Exception $e) {
            Log::error('Monitoring dashboard error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return redirect()->back()->with('error', 'Error loading monitoring dashboard: ' . $e->getMessage());
        }
    }

    /**
     * View error log
     */
    public function errorLog()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canViewErrorLogs()) {
                Log::warning('Error log access denied - invalid user type', [
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip' => request()->ip()
                ]);
                abort(403, 'Unauthorized to view error logs. You must be a developer (type 5) or super admin (type 0).');
            }
            
            $logPath = storage_path('logs/laravel.log');
            $errorLog = '';
            
            if (file_exists($logPath)) {
                $errorLog = shell_exec('tail -n 1000 ' . escapeshellarg($logPath));
            }
            
            $dbErrors = [];
            if (Schema::hasTable('error_logs')) {
                $dbErrors = DB::table('error_logs')
                    ->where('developer_setting_id', auth()->user()->developerSetting->id ?? null)
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
            }
            
            $this->logAudit('error_log_viewed', 'Error log viewed', [
                'user_type' => auth()->user()->type,
                'log_size' => file_exists($logPath) ? filesize($logPath) : 0
            ]);
            
            return view('developer.monitoring.error-log', compact('errorLog', 'dbErrors'));
            
        } catch (\Exception $e) {
            Log::error('Failed to view error log: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return redirect()->back()->with('error', 'Failed to load error log: ' . $e->getMessage());
        }
    }

    /**
     * Clear error logs
     */
    public function clearLogs()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canClearLogs()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to clear logs. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }
            
            if (Schema::hasTable('error_logs')) {
                DB::table('error_logs')->truncate();
            }
            
            DB::table('activity_log')->where('log_name', 'developer')->delete();
            
            $this->logAudit('logs_cleared', 'Error logs cleared', [
                'user_type' => auth()->user()->type,
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Error logs cleared successfully!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear error logs: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear error logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate system report
     */
    public function generateReport(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:daily,weekly,monthly,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,excel,csv,json'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide valid report parameters');
        }
        
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canGenerateReports()) {
                Log::warning('Report generation denied - invalid user type', [
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip' => request()->ip()
                ]);
                abort(403, 'Unauthorized to generate reports. You must be a developer (type 5) or super admin (type 0).');
            }
            
            $data = $validator->validated();
            
            $report = $this->monitoringService->generateReport(
                $data['report_type'],
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['format']
            );
            
            $this->logAudit('report_generated', 'System report generated', [
                'report_type' => $data['report_type'],
                'format' => $data['format'],
                'user_type' => auth()->user()->type,
                'ip_address' => request()->ip()
            ]);
            
            if ($request->boolean('download', true)) {
                $filename = "developer_report_{$data['report_type']}_" . date('Y-m-d') . ".{$data['format']}";
                
                return response()->streamDownload(function () use ($report) {
                    echo $report['content'];
                }, $filename, [
                    'Content-Type' => $report['content_type'],
                    'Content-Security-Policy' => "default-src 'self'"
                ]);
            }
            
            return redirect()->route('developer.monitoring.dashboard')
                ->with('success', 'Report generated successfully!')
                ->with('report_data', $report);
                
        } catch (\Exception $e) {
            Log::error('Failed to generate report: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to generate report: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get system metrics for API
     */
    public function getMetrics()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canViewSystemMetrics()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view system metrics. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $stats = $this->monitoringService->getSystemStatistics();
            $memoryUsage = $this->monitoringService->getMemoryUsagePercentage();
            $diskUsage = $this->monitoringService->getDiskUsagePercentage();
            $uptime = $this->monitoringService->getSystemUptime();
            
            $this->logAudit('metrics_viewed', 'System metrics viewed', [
                'user_type' => auth()->user()->type,
                'api_request' => true
            ]);
            
            return response()->json([
                'success' => true,
                'memory_usage' => $memoryUsage,
                'disk_usage' => $diskUsage,
                'uptime' => $uptime,
                'system_stats' => $stats,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get metrics: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run system health check
     */
    public function healthCheck()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canRunHealthChecks()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to run health checks. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $checks = [
                [
                    'name' => 'Database Connection',
                    'description' => 'Check if database is accessible',
                    'status' => DB::connection()->getPdo() ? 'healthy' : 'unhealthy'
                ],
                [
                    'name' => 'Cache System',
                    'description' => 'Check if cache is working',
                    'status' => Cache::store(config('cache.default'))->ping() ? 'healthy' : 'unhealthy'
                ],
                [
                    'name' => 'Storage Directory',
                    'description' => 'Check if storage directory is writable',
                    'status' => is_writable(storage_path()) ? 'healthy' : 'unhealthy'
                ],
                [
                    'name' => 'Environment File',
                    'description' => 'Check if .env file exists and is readable',
                    'status' => file_exists(base_path('.env')) && is_readable(base_path('.env')) ? 'healthy' : 'unhealthy'
                ],
                [
                    'name' => 'Queue Connection',
                    'description' => 'Check if queue connection is working',
                    'status' => config('queue.default') !== 'sync' ? 'healthy' : 'info'
                ],
                [
                    'name' => 'Mail Configuration',
                    'description' => 'Check if mail is configured',
                    'status' => config('mail.default') ? 'healthy' : 'warning'
                ],
            ];
            
            $this->logAudit('health_check', 'System health check performed', [
                'user_type' => auth()->user()->type,
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'checks' => $checks,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canClearCache()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to clear cache. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            
            $this->logAudit('cache_cleared', 'Application cache cleared', [
                'user_type' => auth()->user()->type,
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Application cache cleared successfully!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear cache: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimize system
     */
    public function optimize(Request $request)
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canOptimizeSystem()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to optimize system. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            if (!$request->ajax() || !$request->hasValidSignature()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request'
                ], 403);
            }
            
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
            
            $this->logAudit('system_optimized', 'System optimized', [
                'user_type' => auth()->user()->type,
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'System optimized successfully!',
                'output' => [
                    'optimize_clear' => 'Optimization cache cleared',
                    'optimize' => 'System optimized'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Optimization failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Optimization failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get health check route (for API)
     */
    public function getHealthCheck()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canRunHealthChecks()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            return $this->healthCheck();
            
        } catch (\Exception $e) {
            Log::error('Health check API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Health check failed'
            ], 500);
        }
    }
}