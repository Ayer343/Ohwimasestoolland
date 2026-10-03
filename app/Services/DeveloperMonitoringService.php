<?php

namespace App\Services;

use App\Models\DeveloperSetting;
use App\Models\SystemMetric;
use App\Models\ErrorLog;
use App\Models\PerformanceLog;
use App\Models\BackupLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use ZipArchive;

class DeveloperMonitoringService
{
    protected $settings;
    protected $alertThresholds;
    protected $backupConfig;

    public function __construct()
    {
        $this->settings = DeveloperSetting::first();
        $this->initializeAlertThresholds();
        $this->initializeBackupConfig();
    }

    /**
     * Initialize alert thresholds with defaults if settings don't exist
     */
    protected function initializeAlertThresholds()
    {
        $this->alertThresholds = [
            'cpu_usage' => $this->settings->cpu_alert_threshold ?? 80,
            'memory_usage' => $this->settings->memory_alert_threshold ?? 85,
            'disk_usage' => $this->settings->disk_alert_threshold ?? 90,
            'response_time' => $this->settings->response_time_threshold ?? 2000, // ms
            'error_rate' => $this->settings->error_rate_threshold ?? 5, // percentage
            'queue_size' => $this->settings->queue_alert_threshold ?? 100,
        ];
    }

    /**
     * Initialize backup configuration with defaults if settings don't exist
     */
    protected function initializeBackupConfig()
    {
        if (!$this->settings) {
            $this->backupConfig = [
                'enabled' => true,
                'frequency' => 'daily',
                'retention_days' => 30,
                'storage_locations' => ['local'],
                'include_databases' => ['mysql'],
                'include_directories' => [
                    'app',
                    'config',
                    'database',
                    'public/uploads',
                    'resources/views',
                    'routes',
                ],
                'exclude_patterns' => [
                    '*.log',
                    '*.tmp',
                    'cache/*',
                    'tests/*',
                    'vendor/*',
                    'node_modules/*',
                ],
            ];
            return;
        }

        $this->backupConfig = [
            'enabled' => $this->settings->enable_auto_backup ?? true,
            'frequency' => $this->settings->backup_frequency ?? 'daily',
            'retention_days' => $this->settings->backup_retention_days ?? 30,
            'storage_locations' => $this->settings->backup_storage_locations 
                ? json_decode($this->settings->backup_storage_locations, true)
                : ['local'],
            'include_databases' => ['mysql'],
            'include_directories' => [
                'app',
                'config',
                'database',
                'public/uploads',
                'resources/views',
                'routes',
            ],
            'exclude_patterns' => [
                '*.log',
                '*.tmp',
                'cache/*',
                'tests/*',
                'vendor/*',
                'node_modules/*',
            ],
        ];
    }

    /**
     * Get system metrics with simplified format for dashboard
     */
    public function getSystemMetrics()
    {
        try {
            $metrics = [
                'cpu_usage' => $this->getCpuUsage(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_usage' => $this->getDiskUsage(),
                'load_average' => $this->getLoadAverage(),
                'uptime' => $this->getUptime(),
                'process_count' => $this->getProcessCount(),
                'network_stats' => $this->getNetworkStats(),
                'database_connections' => $this->getDatabaseConnections(),
                'queue_status' => $this->getQueueStatus(),
                'cache_status' => $this->getCacheStatus(),
            ];

            // Check for alerts only if settings exist
            if ($this->settings) {
                $metrics['alerts'] = $this->checkMetricsForAlerts($metrics);
            } else {
                $metrics['alerts'] = [];
            }

            // Store metrics for historical tracking
            $this->storeSystemMetrics($metrics);

            // Return both full data and simplified format for blade templates
            return [
                'full' => $metrics,
                'simplified' => [
                    'cpu_usage' => $metrics['cpu_usage']['current'] ?? 0,
                    'memory_usage' => $metrics['memory_usage']['current'] ?? 0,
                    'disk_usage' => $metrics['disk_usage']['percentage'] ?? 0,
                ],
                'alerts' => $metrics['alerts'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get system metrics: ' . $e->getMessage());
            return [
                'full' => [],
                'simplified' => [
                    'cpu_usage' => 0,
                    'memory_usage' => 0,
                    'disk_usage' => 0,
                ],
                'alerts' => [],
            ];
        }
    }

    /**
     * Get simplified metrics for dashboard (compatible with blade template)
     */
    public function getDashboardMetrics()
    {
        $metrics = $this->getSystemMetrics();
        return $metrics['simplified'];
    }

    /**
     * Get CPU usage
     */
    protected function getCpuUsage()
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cpuCount = $this->getCpuCount();
                
                if ($cpuCount > 0) {
                    // Calculate percentage for each load average
                    $usage = [
                        '1min' => round(($load[0] / $cpuCount) * 100, 2),
                        '5min' => round(($load[1] / $cpuCount) * 100, 2),
                        '15min' => round(($load[2] / $cpuCount) * 100, 2),
                        'current' => round(($load[0] / $cpuCount) * 100, 2),
                    ];
                    
                    return $usage;
                }
            }
            
            // Fallback for Windows or when sys_getloadavg is not available
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $process = new Process(['top', '-bn1']);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    if (preg_match('/Cpu\(s\):\s+([\d\.]+)%us/', $output, $matches)) {
                        return ['current' => (float) $matches[1]];
                    }
                }
            }
            
            return ['current' => 0];
        } catch (\Exception $e) {
            Log::warning('Failed to get CPU usage: ' . $e->getMessage());
            return ['current' => 0];
        }
    }

    /**
     * Get CPU count
     */
    protected function getCpuCount()
    {
        try {
            if (function_exists('sysconf') && defined('_SC_NPROCESSORS_ONLN')) {
                return (int) sysconf(_SC_NPROCESSORS_ONLN);
            }
            
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $process = new Process(['nproc']);
                $process->run();
                if ($process->isSuccessful()) {
                    return (int) trim($process->getOutput());
                }
            }
            
            return 1; // Default fallback
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * Get memory usage
     */
    protected function getMemoryUsage()
    {
        try {
            if (function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
                $memoryLimit = ini_get('memory_limit');
                $memoryUsage = memory_get_usage(true);
                $peakUsage = memory_get_peak_usage(true);
                
                // Convert memory limit to bytes
                $limitBytes = $this->convertToBytes($memoryLimit);
                
                if ($limitBytes > 0) {
                    $currentUsage = round(($memoryUsage / $limitBytes) * 100, 2);
                    $peakUsagePercent = round(($peakUsage / $limitBytes) * 100, 2);
                } else {
                    $currentUsage = 0;
                    $peakUsagePercent = 0;
                }
                
                return [
                    'current' => $currentUsage,
                    'peak' => $peakUsagePercent,
                    'used' => $this->formatBytes($memoryUsage),
                    'peak_used' => $this->formatBytes($peakUsage),
                    'limit' => $memoryLimit,
                    'limit_bytes' => $limitBytes,
                ];
            }
            
            return ['current' => 0, 'peak' => 0];
        } catch (\Exception $e) {
            Log::warning('Failed to get memory usage: ' . $e->getMessage());
            return ['current' => 0, 'peak' => 0];
        }
    }

    /**
     * Get disk usage
     */
    protected function getDiskUsage()
    {
        try {
            $total = disk_total_space(base_path());
            $free = disk_free_space(base_path());
            
            if ($total > 0) {
                $used = $total - $free;
                $percentage = round(($used / $total) * 100, 2);
            } else {
                $used = 0;
                $percentage = 0;
            }
            
            return [
                'total' => $this->formatBytes($total),
                'used' => $this->formatBytes($used),
                'free' => $this->formatBytes($free),
                'percentage' => $percentage,
                'inodes' => $this->getInodeUsage(),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get disk usage: ' . $e->getMessage());
            return ['percentage' => 0];
        }
    }

    /**
     * Get inode usage
     */
    protected function getInodeUsage()
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $process = new Process(['df', '-i', base_path()]);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $lines = explode("\n", $output);
                    if (count($lines) > 1) {
                        $parts = preg_split('/\s+/', $lines[1]);
                        if (count($parts) >= 5) {
                            return [
                                'total' => (int) $parts[1],
                                'used' => (int) $parts[2],
                                'free' => (int) $parts[3],
                                'percentage' => (int) rtrim($parts[4], '%'),
                            ];
                        }
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get load average
     */
    protected function getLoadAverage()
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return [
                    '1min' => $load[0],
                    '5min' => $load[1],
                    '15min' => $load[2],
                ];
            }
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        } catch (\Exception $e) {
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        }
    }

    /**
     * Get system uptime
     */
    protected function getUptime()
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $process = new Process(['uptime', '-p']);
                $process->run();
                
                if ($process->isSuccessful()) {
                    return trim($process->getOutput());
                }
                
                // Alternative method
                $uptimeFile = '/proc/uptime';
                if (file_exists($uptimeFile)) {
                    $uptime = file_get_contents($uptimeFile);
                    $uptime = floatval(explode(' ', $uptime)[0]);
                    
                    $days = floor($uptime / 86400);
                    $hours = floor(($uptime % 86400) / 3600);
                    $minutes = floor(($uptime % 3600) / 60);
                    
                    $parts = [];
                    if ($days > 0) $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
                    if ($hours > 0) $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
                    if ($minutes > 0) $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                    
                    return implode(', ', $parts);
                }
            }
            
            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get process count
     */
    protected function getProcessCount()
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $process = new Process(['ps', 'aux']);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $lines = explode("\n", $output);
                    return count($lines) - 1; // Subtract header line
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get network statistics
     */
    protected function getNetworkStats()
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $interfaces = ['eth0', 'wlan0', 'lo'];
                $stats = [];
                
                foreach ($interfaces as $interface) {
                    $rxFile = "/sys/class/net/{$interface}/statistics/rx_bytes";
                    $txFile = "/sys/class/net/{$interface}/statistics/tx_bytes";
                    
                    if (file_exists($rxFile) && file_exists($txFile)) {
                        $rxBytes = file_get_contents($rxFile);
                        $txBytes = file_get_contents($txFile);
                        
                        $stats[$interface] = [
                            'rx_bytes' => (int) $rxBytes,
                            'tx_bytes' => (int) $txBytes,
                            'rx_formatted' => $this->formatBytes((int) $rxBytes),
                            'tx_formatted' => $this->formatBytes((int) $txBytes),
                        ];
                    }
                }
                
                return $stats;
            }
            
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get database connections
     */
    protected function getDatabaseConnections()
    {
        try {
            $connections = DB::select('SHOW PROCESSLIST');
            $maxConnections = DB::select('SHOW VARIABLES LIKE "max_connections"');
            
            $maxConnectionsValue = $maxConnections[0]->Value ?? 100;
            $totalConnections = count($connections);
            
            if ($maxConnectionsValue > 0) {
                $connectionUsage = round(($totalConnections / $maxConnectionsValue) * 100, 2);
            } else {
                $connectionUsage = 0;
            }
            
            return [
                'total' => $totalConnections,
                'sleeping' => count(array_filter($connections, function($conn) {
                    return isset($conn->Command) && $conn->Command === 'Sleep';
                })),
                'active' => count(array_filter($connections, function($conn) {
                    return isset($conn->Command) && $conn->Command !== 'Sleep';
                })),
                'max_connections' => (int) $maxConnectionsValue,
                'connection_usage' => $connectionUsage,
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'sleeping' => 0, 'connection_usage' => 0];
        }
    }

    /**
     * Get queue status with table existence check
     */
    protected function getQueueStatus()
    {
        try {
            $queues = ['default', 'high', 'low'];
            $status = [];
            
            // Check if jobs table exists
            if (!Schema::hasTable('jobs')) {
                return ['default' => ['pending' => 0, 'failed' => 0, 'total' => 0]];
            }
            
            foreach ($queues as $queue) {
                $count = DB::table('jobs')->where('queue', $queue)->count();
                
                // Check if failed_jobs table exists
                $failed = 0;
                if (Schema::hasTable('failed_jobs')) {
                    $failed = DB::table('failed_jobs')->where('queue', $queue)->count();
                }
                
                $status[$queue] = [
                    'pending' => $count,
                    'failed' => $failed,
                    'total' => $count + $failed,
                ];
            }
            
            return $status;
        } catch (\Exception $e) {
            return ['default' => ['pending' => 0, 'failed' => 0, 'total' => 0]];
        }
    }

    /**
     * Get cache status
     */
    protected function getCacheStatus()
    {
        try {
            $driver = config('cache.default');
            $stats = [];
            
            switch ($driver) {
                case 'redis':
                    try {
                        $redis = app('redis')->connection();
                        $info = $redis->info();
                        
                        $stats = [
                            'driver' => 'redis',
                            'used_memory' => $this->formatBytes($info['used_memory'] ?? 0),
                            'used_memory_peak' => $this->formatBytes($info['used_memory_peak'] ?? 0),
                            'connected_clients' => $info['connected_clients'] ?? 0,
                            'keys' => $redis->dbsize(),
                            'hit_rate' => isset($info['keyspace_hits'], $info['keyspace_misses']) 
                                ? round($info['keyspace_hits'] / max(1, ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100, 2)
                                : 0,
                        ];
                    } catch (\Exception $e) {
                        $stats = ['driver' => 'redis', 'status' => 'disconnected', 'error' => $e->getMessage()];
                    }
                    break;
                    
                case 'file':
                    $cachePath = storage_path('framework/cache');
                    $size = 0;
                    $fileCount = 0;
                    
                    if (file_exists($cachePath)) {
                        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cachePath)) as $file) {
                            if ($file->isFile()) {
                                $size += $file->getSize();
                                $fileCount++;
                            }
                        }
                    }
                    
                    $stats = [
                        'driver' => 'file',
                        'cache_size' => $this->formatBytes($size),
                        'files_count' => $fileCount,
                    ];
                    break;
                    
                default:
                    $stats = ['driver' => $driver, 'status' => 'active'];
            }
            
            return $stats;
        } catch (\Exception $e) {
            return ['driver' => 'unknown', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check metrics for alerts
     */
    protected function checkMetricsForAlerts($metrics)
    {
        $alerts = [];
        
        if (!$this->settings) {
            return $alerts;
        }
        
        // Check CPU usage
        if (isset($metrics['cpu_usage']['current']) && 
            $metrics['cpu_usage']['current'] > $this->alertThresholds['cpu_usage']) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'cpu_usage',
                'message' => "High CPU usage: {$metrics['cpu_usage']['current']}%",
                'threshold' => $this->alertThresholds['cpu_usage'],
                'value' => $metrics['cpu_usage']['current'],
            ];
        }
        
        // Check memory usage
        if (isset($metrics['memory_usage']['current']) && 
            $metrics['memory_usage']['current'] > $this->alertThresholds['memory_usage']) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'memory_usage',
                'message' => "High memory usage: {$metrics['memory_usage']['current']}%",
                'threshold' => $this->alertThresholds['memory_usage'],
                'value' => $metrics['memory_usage']['current'],
            ];
        }
        
        // Check disk usage
        if (isset($metrics['disk_usage']['percentage']) && 
            $metrics['disk_usage']['percentage'] > $this->alertThresholds['disk_usage']) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'disk_usage',
                'message' => "High disk usage: {$metrics['disk_usage']['percentage']}%",
                'threshold' => $this->alertThresholds['disk_usage'],
                'value' => $metrics['disk_usage']['percentage'],
            ];
        }
        
        // Check database connections
        if (isset($metrics['database_connections']['connection_usage']) && 
            $metrics['database_connections']['connection_usage'] > 80) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'database_connections',
                'message' => "High database connection usage: {$metrics['database_connections']['connection_usage']}%",
                'threshold' => 80,
                'value' => $metrics['database_connections']['connection_usage'],
            ];
        }
        
        // Check queue size
        if (isset($metrics['queue_status']['default']['pending']) && 
            $metrics['queue_status']['default']['pending'] > $this->alertThresholds['queue_size']) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'queue_size',
                'message' => "Large queue size: {$metrics['queue_status']['default']['pending']} pending jobs",
                'threshold' => $this->alertThresholds['queue_size'],
                'value' => $metrics['queue_status']['default']['pending'],
            ];
        }
        
        return $alerts;
    }

    /**
     * Store system metrics for historical data
     */
    protected function storeSystemMetrics($metrics)
    {
        try {
            SystemMetric::create([
                'cpu_usage' => $metrics['cpu_usage']['current'] ?? 0,
                'memory_usage' => $metrics['memory_usage']['current'] ?? 0,
                'disk_usage' => $metrics['disk_usage']['percentage'] ?? 0,
                'load_average_1min' => $metrics['load_average']['1min'] ?? 0,
                'load_average_5min' => $metrics['load_average']['5min'] ?? 0,
                'load_average_15min' => $metrics['load_average']['15min'] ?? 0,
                'database_connections' => $metrics['database_connections']['total'] ?? 0,
                'queue_size' => $metrics['queue_status']['default']['pending'] ?? 0,
                'alerts_count' => count($metrics['alerts'] ?? []),
                'recorded_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to store system metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get performance data
     */
    public function getPerformanceData($timeRange = '24h')
    {
        try {
            $startTime = $this->getStartTimeForRange($timeRange);
            
            $metrics = SystemMetric::where('recorded_at', '>=', $startTime)
                ->orderBy('recorded_at', 'asc')
                ->get();
            
            if ($metrics->isEmpty()) {
                return $this->getDefaultPerformanceData();
            }
            
            $data = [
                'timestamps' => $metrics->pluck('recorded_at')->map(function($date) {
                    return $date->format('H:i');
                })->toArray(),
                'cpu_usage' => $metrics->pluck('cpu_usage')->toArray(),
                'memory_usage' => $metrics->pluck('memory_usage')->toArray(),
                'disk_usage' => $metrics->pluck('disk_usage')->toArray(),
                'load_average' => $metrics->pluck('load_average_1min')->toArray(),
                'database_connections' => $metrics->pluck('database_connections')->toArray(),
                'queue_size' => $metrics->pluck('queue_size')->toArray(),
                'summary' => [
                    'avg_cpu_usage' => round($metrics->avg('cpu_usage') ?? 0, 2),
                    'avg_memory_usage' => round($metrics->avg('memory_usage') ?? 0, 2),
                    'avg_disk_usage' => round($metrics->avg('disk_usage') ?? 0, 2),
                    'peak_cpu_usage' => round($metrics->max('cpu_usage') ?? 0, 2),
                    'peak_memory_usage' => round($metrics->max('memory_usage') ?? 0, 2),
                    'total_alerts' => $metrics->sum('alerts_count') ?? 0,
                ],
            ];
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to get performance data: ' . $e->getMessage());
            return $this->getDefaultPerformanceData();
        }
    }

    /**
     * Get recent errors
     */
    public function getRecentErrors($limit = 50)
    {
        try {
            $errors = ErrorLog::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($error) {
                    return [
                        'id' => $error->id,
                        'level' => $error->level,
                        'message' => $error->message ?? 'No message',
                        'file' => $error->file ?? 'Unknown',
                        'line' => $error->line ?? 0,
                        'code' => $error->code ?? 0,
                        'user_id' => $error->user_id,
                        'url' => $error->url ?? 'Unknown',
                        'method' => $error->method ?? 'Unknown',
                        'ip' => $error->ip ?? 'Unknown',
                        'user_agent' => $error->user_agent ?? 'Unknown',
                        'time' => $error->created_at->diffForHumans(),
                        'timestamp' => $error->created_at->format('Y-m-d H:i:s'),
                    ];
                });
            
            return $errors;
        } catch (\Exception $e) {
            Log::error('Failed to get recent errors: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts()
    {
        try {
            // Get recent metrics
            $metrics = $this->getSystemMetrics();
            $alerts = $metrics['alerts'] ?? [];
            
            // Get recent error-based alerts
            $errorAlerts = $this->getErrorBasedAlerts();
            
            // Get backup alerts
            $backupAlerts = $this->getBackupAlerts();
            
            // Combine all alerts
            $allAlerts = array_merge($alerts, $errorAlerts, $backupAlerts);
            
            // Sort by severity (critical > warning > info)
            usort($allAlerts, function($a, $b) {
                $severityOrder = ['critical' => 3, 'warning' => 2, 'info' => 1];
                $aSeverity = $severityOrder[$a['level']] ?? 0;
                $bSeverity = $severityOrder[$b['level']] ?? 0;
                
                return $bSeverity - $aSeverity;
            });
            
            return array_slice($allAlerts, 0, 10); // Return top 10 alerts
        } catch (\Exception $e) {
            Log::error('Failed to get active alerts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get error-based alerts
     */
    protected function getErrorBasedAlerts()
    {
        try {
            $alerts = [];
            $errorThresholds = [
                'critical' => 10, // Errors in last hour
                'warning' => 5,   // Errors in last hour
            ];
            
            // Check for critical errors in last hour
            $criticalErrors = ErrorLog::where('level', 'error')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            
            if ($criticalErrors >= $errorThresholds['critical']) {
                $alerts[] = [
                    'level' => 'critical',
                    'type' => 'error_rate',
                    'message' => "High error rate: {$criticalErrors} errors in last hour",
                    'threshold' => $errorThresholds['critical'],
                    'value' => $criticalErrors,
                ];
            } elseif ($criticalErrors >= $errorThresholds['warning']) {
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'error_rate',
                    'message' => "Elevated error rate: {$criticalErrors} errors in last hour",
                    'threshold' => $errorThresholds['warning'],
                    'value' => $criticalErrors,
                ];
            }
            
            // Check for recent fatal errors
            $fatalErrors = ErrorLog::where('level', 'fatal')
                ->where('created_at', '>=', now()->subDay())
                ->count();
            
            if ($fatalErrors > 0) {
                $alerts[] = [
                    'level' => 'critical',
                    'type' => 'fatal_errors',
                    'message' => "{$fatalErrors} fatal error(s) in last 24 hours",
                    'threshold' => 0,
                    'value' => $fatalErrors,
                ];
            }
            
            return $alerts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get backup alerts
     */
    protected function getBackupAlerts()
    {
        try {
            $alerts = [];
            
            // Check last backup
            $lastBackup = BackupLog::where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($lastBackup) {
                $backupAge = now()->diffInHours($lastBackup->created_at);
                
                if ($backupAge > 48) { // No backup in 48 hours
                    $alerts[] = [
                        'level' => 'warning',
                        'type' => 'backup_stale',
                        'message' => "Last backup was {$backupAge} hours ago",
                        'threshold' => 48,
                        'value' => $backupAge,
                    ];
                }
            } else {
                // No backups found
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'backup_missing',
                    'message' => 'No backups have been created',
                    'threshold' => 1,
                    'value' => 0,
                ];
            }
            
            // Check backup success rate
            $totalBackups = BackupLog::count();
            $failedBackups = BackupLog::where('status', 'failed')->count();
            
            if ($totalBackups > 0) {
                $failureRate = ($failedBackups / $totalBackups) * 100;
                
                if ($failureRate > 20) { // More than 20% failure rate
                    $alerts[] = [
                        'level' => 'warning',
                        'type' => 'backup_failure_rate',
                        'message' => "High backup failure rate: " . round($failureRate, 1) . "%",
                        'threshold' => 20,
                        'value' => round($failureRate, 1),
                    ];
                }
            }
            
            return $alerts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get usage statistics with table existence checks
     */
    public function getUsageStatistics()
    {
        try {
            $today = now()->startOfDay();
            
            return [
                'api_calls_today' => $this->getApiCallsCount($today),
                'emails_sent_today' => $this->getEmailsCount($today),
                'payments_processed_today' => $this->getPaymentsCount($today),
                'disk_usage_percentage' => $this->getDashboardMetrics()['disk_usage'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get usage statistics: ' . $e->getMessage());
            
            return [
                'api_calls_today' => 0,
                'emails_sent_today' => 0,
                'payments_processed_today' => 0,
                'disk_usage_percentage' => 0,
            ];
        }
    }

    /**
     * Get API calls count with table existence check
     */
    protected function getApiCallsCount($date)
    {
        try {
            // Check if table exists
            $tables = ['api_logs', 'api_requests', 'request_logs'];
            
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    return DB::table($table)
                        ->where('created_at', '>=', $date)
                        ->count();
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::debug('Failed to get API calls count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get emails count with table existence check
     */
    protected function getEmailsCount($date)
    {
        try {
            // Check which email log table might exist
            $tables = ['email_logs', 'sent_emails', 'mail_logs', 'emails'];
            
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    return DB::table($table)
                        ->where('created_at', '>=', $date)
                        ->count();
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::debug('Failed to get emails count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get payments count with table existence check
     */
    protected function getPaymentsCount($date)
    {
        try {
            // Check which payment table might exist
            $tables = ['payments', 'transactions', 'payment_logs', 'orders'];
            
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    return DB::table($table)
                        ->where('created_at', '>=', $date)
                        ->where('status', 'completed')
                        ->count();
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::debug('Failed to get payments count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate system report
     */
    public function generateReport($reportType, $startDate = null, $endDate = null, $format = 'pdf')
    {
        try {
            // Determine date range
            $dateRange = $this->getDateRange($reportType, $startDate, $endDate);
            
            // Gather report data
            $reportData = $this->gatherReportData($dateRange['start'], $dateRange['end']);
            
            // Generate report based on format
            $report = $this->generateFormattedReport($reportData, $format);
            
            // Log report generation
            $this->logReportGeneration($reportType, $dateRange, $format);
            
            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate report: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get date range for report
     */
    protected function getDateRange($reportType, $startDate, $endDate)
    {
        $endDate = $endDate ? Carbon::parse($endDate) : now();
        
        switch ($reportType) {
            case 'daily':
                $startDate = $endDate->copy()->subDay();
                break;
            case 'weekly':
                $startDate = $endDate->copy()->subWeek();
                break;
            case 'monthly':
                $startDate = $endDate->copy()->subMonth();
                break;
            case 'custom':
                $startDate = $startDate ? Carbon::parse($startDate) : now()->subMonth();
                break;
            default:
                $startDate = $endDate->copy()->subDay();
        }
        
        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Gather report data
     */
    protected function gatherReportData($startDate, $endDate)
    {
        // System metrics
        $metrics = SystemMetric::whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'asc')
            ->get();
        
        // Error logs
        $errors = ErrorLog::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Performance logs
        $performance = collect();
        if (Schema::hasTable('performance_logs')) {
            $performance = PerformanceLog::whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'asc')
                ->get();
        }
        
        // Backup logs
        $backups = BackupLog::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Calculate statistics
        $stats = [
            'metrics' => [
                'total_records' => $metrics->count(),
                'avg_cpu_usage' => $metrics->avg('cpu_usage') ?? 0,
                'avg_memory_usage' => $metrics->avg('memory_usage') ?? 0,
                'avg_disk_usage' => $metrics->avg('disk_usage') ?? 0,
                'peak_cpu_usage' => $metrics->max('cpu_usage') ?? 0,
                'total_alerts' => $metrics->sum('alerts_count') ?? 0,
            ],
            'errors' => [
                'total' => $errors->count(),
                'by_level' => $errors->groupBy('level')->map->count()->toArray(),
                'by_hour' => $errors->groupBy(function($error) {
                    return $error->created_at->format('Y-m-d H');
                })->map->count()->toArray(),
            ],
            'performance' => [
                'avg_response_time' => $performance->avg('response_time') ?? 0,
                'total_requests' => $performance->count(),
                'slow_requests' => $performance->where('response_time', '>', 1000)->count(),
            ],
            'backups' => [
                'total' => $backups->count(),
                'successful' => $backups->where('status', 'completed')->count(),
                'failed' => $backups->where('status', 'failed')->count(),
                'total_size' => $backups->sum('size') ?? 0,
            ],
        ];
        
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s'),
                'duration' => $startDate->diffForHumans($endDate, true),
            ],
            'summary' => $stats,
            'detailed_data' => [
                'metrics_sample' => $metrics->take(100),
                'recent_errors' => $errors->take(50),
                'performance_sample' => $performance->take(100),
                'backup_history' => $backups,
            ],
        ];
    }

    /**
     * Generate formatted report
     */
    protected function generateFormattedReport($data, $format)
    {
        switch ($format) {
            case 'pdf':
                return $this->generatePdfReport($data);
            case 'excel':
                return $this->generateExcelReport($data);
            case 'csv':
                return $this->generateCsvReport($data);
            case 'json':
                return $this->generateJsonReport($data);
            default:
                return $this->generateHtmlReport($data);
        }
    }

    /**
     * Generate HTML report
     */
    protected function generateHtmlReport($data)
    {
        $html = view('reports.system-monitoring', $data)->render();
        
        return [
            'content' => $html,
            'content_type' => 'text/html',
            'filename' => 'system_report_' . date('Y-m-d') . '.html',
        ];
    }

    /**
     * Generate PDF report
     */
    protected function generatePdfReport($data)
    {
        // This would use a PDF library like Dompdf or TCPDF
        // For now, return HTML that can be converted to PDF
        $html = view('reports.system-monitoring', $data)->render();
        
        return [
            'content' => $html,
            'content_type' => 'text/html', // Would be 'application/pdf' with proper library
            'filename' => 'system_report_' . date('Y-m-d') . '.pdf',
        ];
    }

    /**
     * Generate Excel report
     */
    protected function generateExcelReport($data)
    {
        // This would use PhpSpreadsheet or similar
        // For now, return CSV format
        return $this->generateCsvReport($data);
    }

    /**
     * Generate CSV report
     */
    protected function generateCsvReport($data)
    {
        $csv = "System Monitoring Report\n";
        $csv .= "Period: {$data['period']['start']} to {$data['period']['end']}\n\n";
        
        // Summary section
        $csv .= "SUMMARY\n";
        $csv .= "Metrics Records: {$data['summary']['metrics']['total_records']}\n";
        $csv .= "Average CPU Usage: " . round($data['summary']['metrics']['avg_cpu_usage'], 2) . "%\n";
        $csv .= "Average Memory Usage: " . round($data['summary']['metrics']['avg_memory_usage'], 2) . "%\n";
        $csv .= "Total Errors: {$data['summary']['errors']['total']}\n";
        $csv .= "Total Backups: {$data['summary']['backups']['total']}\n\n";
        
        // Recent errors
        $csv .= "RECENT ERRORS\n";
        $csv .= "Level,Message,Time\n";
        foreach ($data['detailed_data']['recent_errors'] as $error) {
            $csv .= "{$error->level},\"{$error->message}\",{$error->created_at}\n";
        }
        
        return [
            'content' => $csv,
            'content_type' => 'text/csv',
            'filename' => 'system_report_' . date('Y-m-d') . '.csv',
        ];
    }

    /**
     * Generate JSON report
     */
    protected function generateJsonReport($data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        return [
            'content' => $json,
            'content_type' => 'application/json',
            'filename' => 'system_report_' . date('Y-m-d') . '.json',
        ];
    }

    /**
     * Log report generation
     */
    protected function logReportGeneration($reportType, $dateRange, $format)
    {
        try {
            if (Schema::hasTable('report_logs')) {
                DB::table('report_logs')->insert([
                    'report_type' => $reportType,
                    'period_start' => $dateRange['start'],
                    'period_end' => $dateRange['end'],
                    'format' => $format,
                    'generated_by' => auth()->id() ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log report generation: ' . $e->getMessage());
        }
    }

    // =====================================================
    // UPDATED BACKUP METHODS (FIXED VERSION)
    // =====================================================

    /**
     * Create backup - UPDATED IMPLEMENTATION
     */
    public function createBackup($type = 'manual')
    {
        DB::beginTransaction();
        
        try {
            // Create backup record
            $backupLog = BackupLog::create([
                'backup_type' => $type,
                'filename' => null,
                'size' => 0,
                'status' => 'started',
                'started_at' => now(),
                'notes' => "{$type} backup initiated" . (auth()->check() ? " by " . auth()->user()->email : ''),
            ]);
            
            // Create backup directory if it doesn't exist
            $backupDir = storage_path('app/backups');
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            // Generate filename
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$type}_{$timestamp}.zip";
            $filepath = $backupDir . '/' . $filename;
            
            // Create backup using the new implementation
            $result = $this->executeBackupNew($filepath);
            
            if ($result['success']) {
                // Update backup log
                $backupLog->update([
                    'filename' => $filename,
                    'size' => $result['size'],
                    'status' => 'completed',
                    'completed_at' => now(),
                    'duration' => now()->diffInSeconds($backupLog->started_at),
                ]);
                
                DB::commit();
                
                // Cleanup old backups
                $this->cleanupOldBackups();
                
                return [
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'filename' => $filename,
                    'size' => $this->formatBytes($result['size']),
                    'size_bytes' => $result['size'],
                    'path' => $filepath,
                    'backup_id' => $backupLog->id,
                ];
            } else {
                throw new \Exception($result['message']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Update backup log with failure
            if (isset($backupLog)) {
                $backupLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }
            
            Log::error('Backup creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Execute backup process - NEW IMPLEMENTATION
     */
    protected function executeBackupNew($filepath)
    {
        try {
            // Create zip archive
            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('Cannot create zip file');
            }
            
            // Backup database
            $this->backupDatabaseNew($zip);
            
            // Backup important files
            $this->backupImportantFilesNew($zip);
            
            $zip->close();
            
            $size = filesize($filepath);
            
            return [
                'success' => true,
                'size' => $size,
                'message' => 'Backup completed successfully',
            ];
        } catch (\Exception $e) {
            // Clean up failed backup file
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            
            throw $e;
        }
    }

    /**
     * Backup database - NEW IMPLEMENTATION
     */
    protected function backupDatabaseNew(ZipArchive $zip)
    {
        try {
            // Get database name from config
            $databaseName = config('database.connections.mysql.database');
            
            if (empty($databaseName)) {
                throw new \Exception('Database configuration not found');
            }
            
            // Create SQL dump
            $sqlContent = '';
            
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            
            foreach ($tables as $table) {
                $tableName = $table->{'Tables_in_' . $databaseName};
                
                // Get table structure
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                if (!empty($createTable)) {
                    $sqlContent .= $createTable[0]->{'Create Table'} . ";\n\n";
                    
                    // Get table data
                    $rows = DB::table($tableName)->get();
                    if ($rows->count() > 0) {
                        $sqlContent .= "INSERT INTO `{$tableName}` VALUES \n";
                        
                        $rowValues = [];
                        foreach ($rows as $row) {
                            $values = array_map(function($value) {
                                if (is_null($value)) {
                                    return 'NULL';
                                } elseif (is_numeric($value)) {
                                    return $value;
                                } else {
                                    return "'" . addslashes($value) . "'";
                                }
                            }, (array)$row);
                            
                            $rowValues[] = '(' . implode(', ', $values) . ')';
                        }
                        
                        $sqlContent .= implode(",\n", $rowValues) . ";\n\n";
                    }
                }
            }
            
            // Add SQL dump to zip
            $zip->addFromString('database_dump.sql', $sqlContent);
            
        } catch (\Exception $e) {
            Log::warning('Database backup failed: ' . $e->getMessage());
            // Continue with file backup even if database backup fails
            // Add error to zip for reference
            $zip->addFromString('database_error.txt', 'Database backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Backup important files - NEW IMPLEMENTATION
     */
    protected function backupImportantFilesNew(ZipArchive $zip)
    {
        $importantFiles = [
            '.env' => base_path('.env'),
            'app' => app_path(),
            'config' => config_path(),
            'database/migrations' => database_path('migrations'),
            'database/seeders' => database_path('seeders'),
            'routes' => base_path('routes'),
            'public/uploads' => public_path('uploads'),
            'resources/views' => resource_path('views'),
        ];
        
        foreach ($importantFiles as $folderName => $path) {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    $this->addDirectoryToZipNew($zip, $path, $folderName);
                } else {
                    $zip->addFile($path, $folderName . '/' . basename($path));
                }
            }
        }
    }

    /**
     * Add directory to zip recursively - NEW IMPLEMENTATION
     */
    protected function addDirectoryToZipNew(ZipArchive $zip, $directory, $zipPath = '')
    {
        $files = scandir($directory);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $directory . '/' . $file;
            $localPath = ($zipPath ? $zipPath . '/' : '') . $file;
            
            // Check if file should be excluded
            if ($this->shouldExcludeFileNew($filePath)) {
                continue;
            }
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($localPath);
                $this->addDirectoryToZipNew($zip, $filePath, $localPath);
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }

    /**
     * Check if file should be excluded - NEW IMPLEMENTATION
     */
    protected function shouldExcludeFileNew($filePath)
    {
        $excludePatterns = [
            '*.log',
            '*.tmp',
            'cache/*',
            'tests/*',
            'vendor/*',
            'node_modules/*',
            '*.zip',
            '*.tar',
            '*.gz',
        ];
        
        $fileName = basename($filePath);
        $relativePath = str_replace(base_path() . '/', '', $filePath);
        
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $fileName) || fnmatch($pattern, $relativePath)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * List available backups - UPDATED IMPLEMENTATION
     */
    public function listBackups()
    {
        try {
            $backupDir = storage_path('app/backups');
            
            if (!file_exists($backupDir)) {
                return [];
            }
            
            $files = glob($backupDir . '/*.zip');
            $backups = [];
            
            foreach ($files as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $modified = filemtime($file);
                
                // Extract info from filename
                if (preg_match('/backup_(\w+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.zip/', $filename, $matches)) {
                    $type = $matches[1];
                    $timestamp = $matches[2];
                    
                    $backups[] = [
                        'filename' => $filename,
                        'path' => $file,
                        'size' => $size,
                        'size_formatted' => $this->formatBytes($size),
                        'modified' => date('Y-m-d H:i:s', $modified),
                        'timestamp' => $timestamp,
                        'type' => $type,
                        'age' => now()->diffForHumans(Carbon::createFromFormat('Y-m-d_H-i-s', $timestamp)),
                    ];
                } else {
                    // Handle old format or manual backups
                    $backups[] = [
                        'filename' => $filename,
                        'path' => $file,
                        'size' => $size,
                        'size_formatted' => $this->formatBytes($size),
                        'modified' => date('Y-m-d H:i:s', $modified),
                        'timestamp' => date('Y-m-d_H-i-s', $modified),
                        'type' => 'unknown',
                        'age' => now()->diffForHumans(Carbon::createFromTimestamp($modified)),
                    ];
                }
            }
            
            // Sort by modified time (newest first)
            usort($backups, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
            
            return $backups;
        } catch (\Exception $e) {
            Log::error('Failed to list backups: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get backup path
     */
    public function getBackupPath($filename)
    {
        return storage_path('app/backups/' . $filename);
    }

    /**
     * Restore from backup - UPDATED IMPLEMENTATION
     */
    public function restoreBackup($filename)
    {
        try {
            $backupPath = $this->getBackupPath($filename);
            
            if (!file_exists($backupPath)) {
                throw new \Exception("Backup file not found: {$filename}");
            }
            
            // Create restore directory
            $restoreDir = storage_path('app/restore/' . date('Y-m-d_H-i-s'));
            if (!file_exists($restoreDir)) {
                mkdir($restoreDir, 0755, true);
            }
            
            // Extract backup
            $zip = new ZipArchive();
            if ($zip->open($backupPath) !== TRUE) {
                throw new \Exception('Cannot open backup file');
            }
            
            $zip->extractTo($restoreDir);
            $zip->close();
            
            // Check if database dump exists and restore it
            $sqlFile = $restoreDir . '/database_dump.sql';
            if (file_exists($sqlFile)) {
                $this->restoreDatabaseNew($sqlFile);
            }
            
            // Log restore
            Log::critical('System restore initiated', [
                'backup_file' => $filename,
                'restore_dir' => $restoreDir,
                'initiated_by' => auth()->user()->email ?? 'system',
                'timestamp' => now()->toISOString(),
            ]);
            
            return [
                'success' => true,
                'message' => 'Backup restored successfully',
                'restore_dir' => $restoreDir,
                'extracted_files' => $this->listExtractedFiles($restoreDir),
            ];
        } catch (\Exception $e) {
            Log::error('Backup restore failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restore database from SQL dump - NEW IMPLEMENTATION
     */
    protected function restoreDatabaseNew($sqlFile)
    {
        try {
            $sql = file_get_contents($sqlFile);
            
            // Split by semicolon but handle semicolons inside strings
            $queries = [];
            $currentQuery = '';
            $inString = false;
            $stringChar = '';
            
            for ($i = 0; $i < strlen($sql); $i++) {
                $char = $sql[$i];
                
                if ($char === "'" || $char === '"') {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($stringChar === $char && $sql[$i-1] !== '\\') {
                        $inString = false;
                    }
                }
                
                $currentQuery .= $char;
                
                if ($char === ';' && !$inString) {
                    $queries[] = trim($currentQuery);
                    $currentQuery = '';
                }
            }
            
            // Add any remaining query
            if (!empty(trim($currentQuery))) {
                $queries[] = trim($currentQuery);
            }
            
            // Execute queries
            foreach ($queries as $query) {
                if (!empty(trim($query))) {
                    DB::statement($query);
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            throw new \Exception('Database restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete backup - UPDATED IMPLEMENTATION
     */
    public function deleteBackup($filename)
    {
        try {
            $backupPath = $this->getBackupPath($filename);
            
            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }
            
            if (unlink($backupPath)) {
                // Also delete from backup logs
                BackupLog::where('filename', $filename)->update([
                    'cleaned_up' => true,
                    'cleaned_up_at' => now(),
                ]);
                
                Log::info('Backup deleted', ['filename' => $filename]);
                
                return [
                    'success' => true,
                    'message' => 'Backup deleted successfully'
                ];
            } else {
                throw new \Exception('Failed to delete backup file');
            }
            
        } catch (\Exception $e) {
            Log::error('Backup deletion failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Backup deletion failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify backup integrity - UPDATED IMPLEMENTATION
     */
    public function verifyBackup($filename)
    {
        try {
            $backupPath = $this->getBackupPath($filename);
            
            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }
            
            $zip = new ZipArchive();
            if ($zip->open($backupPath) !== TRUE) {
                throw new \Exception('Backup file is corrupted or not a valid zip');
            }
            
            $hasDatabase = false;
            $hasImportantFiles = false;
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (strpos($entry, 'database_dump.sql') !== false) {
                    $hasDatabase = true;
                }
                if (strpos($entry, '.env') !== false || strpos($entry, 'app/') !== false) {
                    $hasImportantFiles = true;
                }
            }
            
            $zip->close();
            
            return [
                'success' => true,
                'message' => 'Backup verification passed',
                'has_database' => $hasDatabase,
                'has_important_files' => $hasImportantFiles,
                'is_valid_zip' => true,
                'file_size' => filesize($backupPath),
                'file_size_formatted' => $this->formatBytes(filesize($backupPath)),
            ];
            
        } catch (\Exception $e) {
            Log::error('Backup verification failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Backup verification failed: ' . $e->getMessage(),
                'has_database' => false,
                'has_important_files' => false,
                'is_valid_zip' => false,
                'file_size' => 0,
            ];
        }
    }

    /**
     * Get disk usage percentage - UPDATED IMPLEMENTATION
     */
    public function getDiskUsagePercentage()
    {
        try {
            $totalSpace = disk_total_space(storage_path());
            $freeSpace = disk_free_space(storage_path());
            
            if ($totalSpace > 0) {
                $usedSpace = $totalSpace - $freeSpace;
                $percentage = round(($usedSpace / $totalSpace) * 100, 2);
                return $percentage;
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::warning('Failed to get disk usage: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check backup configuration - UPDATED IMPLEMENTATION
     */
    public function checkBackupConfiguration()
    {
        $backupDir = storage_path('app/backups');
        
        return [
            'backup_directory_exists' => file_exists($backupDir),
            'backup_directory_writable' => is_writable($backupDir),
            'backup_count' => count($this->listBackups()),
            'disk_usage_percentage' => $this->getDiskUsagePercentage(),
            'backup_config' => [
                'enabled' => $this->backupConfig['enabled'],
                'retention_days' => $this->backupConfig['retention_days'],
            ],
        ];
    }

    /**
     * Cleanup old backups - UPDATED IMPLEMENTATION
     */
    protected function cleanupOldBackups()
    {
        try {
            $retentionDays = $this->backupConfig['retention_days'] ?? 30;
            $backupDir = storage_path('app/backups');
            
            if (!file_exists($backupDir)) {
                return;
            }
            
            $files = glob($backupDir . '/*.zip');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileTime = filemtime($file);
                    $ageInDays = ($now - $fileTime) / (60 * 60 * 24);
                    
                    if ($ageInDays > $retentionDays) {
                        unlink($file);
                        
                        // Also update backup log
                        $filename = basename($file);
                        BackupLog::where('filename', $filename)
                            ->update(['cleaned_up' => true, 'cleaned_up_at' => now()]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup old backups: ' . $e->getMessage());
        }
    }

    /**
     * List extracted files - UPDATED IMPLEMENTATION
     */
    protected function listExtractedFiles($directory)
    {
        $files = [];
        
        if (!file_exists($directory)) {
            return $files;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'size_formatted' => $this->formatBytes($file->getSize()),
                    'relative_path' => str_replace($directory . '/', '', $file->getPathname()),
                ];
            }
        }
        
        return $files;
    }

    /**
     * Get backup job status (for compatibility with backup controller)
     */
    public function getJobStatus($jobId)
    {
        return [
            'job_id' => $jobId,
            'status' => 'completed', // Simplified for now
            'created_at' => now()->subMinutes(5),
            'updated_at' => now(),
        ];
    }

    // =====================================================
    // HELPER METHODS
    // =====================================================

    /**
     * Helper: Convert to bytes
     */
    protected function convertToBytes($value)
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Helper: Format bytes
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        
        if ($bytes === 0) {
            return '0 B';
        }
        
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Helper: Get start time for range
     */
    protected function getStartTimeForRange($range)
    {
        switch ($range) {
            case '1h':
                return now()->subHour();
            case '6h':
                return now()->subHours(6);
            case '12h':
                return now()->subHours(12);
            case '24h':
                return now()->subDay();
            case '7d':
                return now()->subDays(7);
            case '30d':
                return now()->subDays(30);
            default:
                return now()->subDay();
        }
    }

    /**
     * Get default performance data
     */
    protected function getDefaultPerformanceData()
    {
        return [
            'timestamps' => [],
            'cpu_usage' => [],
            'memory_usage' => [],
            'disk_usage' => [],
            'load_average' => [],
            'database_connections' => [],
            'queue_size' => [],
            'summary' => [
                'avg_cpu_usage' => 0,
                'avg_memory_usage' => 0,
                'avg_disk_usage' => 0,
                'peak_cpu_usage' => 0,
                'peak_memory_usage' => 0,
                'total_alerts' => 0,
            ],
        ];
    }

    /**
     * Get default dashboard performance metrics
     */
    public function getDefaultPerformanceMetrics()
    {
        return [
            [
                'name' => 'CPU Usage',
                'value' => 25,
                'status' => 'good',
                'description' => 'Current CPU utilization'
            ],
            [
                'name' => 'Memory Usage',
                'value' => 45,
                'status' => 'good',
                'description' => 'Current memory utilization'
            ],
            [
                'name' => 'Disk Usage',
                'value' => 65,
                'status' => 'good',
                'description' => 'Current disk space usage'
            ],
            [
                'name' => 'Response Time',
                'value' => 15,
                'status' => 'good',
                'description' => 'Average API response time'
            ],
        ];
    }

    /**
     * Get default metrics
     */
    protected function getDefaultMetrics()
    {
        return [
            'cpu_usage' => ['current' => 0],
            'memory_usage' => ['current' => 0],
            'disk_usage' => ['percentage' => 0],
            'load_average' => ['1min' => 0, '5min' => 0, '15min' => 0],
            'uptime' => 'Unknown',
            'alerts' => [],
        ];
    }
}