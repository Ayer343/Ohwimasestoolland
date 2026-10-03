{{-- developer/monitoring/dashboard.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'System Monitoring Dashboard';
    
    // Ensure metrics are properly formatted (handle both array and scalar values)
    $systemMetrics = $systemMetrics ?? [];
    $performanceData = $performanceData ?? [];
    $errorLogs = $errorLogs ?? collect();
    $activeAlerts = $activeAlerts ?? [];
    $usageStatistics = $usageStatistics ?? [];
    
    // Extract and format metric values safely
    $cpuUsage = is_array($systemMetrics['cpu_usage'] ?? null) 
        ? ($systemMetrics['cpu_usage']['current'] ?? 0) 
        : ($systemMetrics['cpu_usage'] ?? 0);
    $cpuUsage = is_numeric($cpuUsage) ? round(floatval($cpuUsage), 2) : 0;
    
    $memoryUsage = is_array($systemMetrics['memory_usage'] ?? null) 
        ? ($systemMetrics['memory_usage']['current'] ?? 0) 
        : ($systemMetrics['memory_usage'] ?? 0);
    $memoryUsage = is_numeric($memoryUsage) ? round(floatval($memoryUsage), 2) : 0;
    
    $diskUsage = is_array($systemMetrics['disk_usage'] ?? null) 
        ? ($systemMetrics['disk_usage']['percentage'] ?? 0) 
        : ($systemMetrics['disk_usage'] ?? 0);
    $diskUsage = is_numeric($diskUsage) ? round(floatval($diskUsage), 2) : 0;
    
    // Set color classes based on thresholds
    $cpuClass = $cpuUsage > 80 ? 'danger' : ($cpuUsage > 60 ? 'warning' : 'success');
    $memClass = $memoryUsage > 80 ? 'danger' : ($memoryUsage > 60 ? 'warning' : 'success');
    $diskClass = $diskUsage > 90 ? 'danger' : ($diskUsage > 70 ? 'warning' : 'success');
    
    // Handle performance data structure
    $performanceDataArray = [];
    if (isset($performanceData['timestamps']) && is_array($performanceData['timestamps'])) {
        // Performance data is in the historical format with charts
        $performanceDataArray = [
            [
                'name' => 'CPU Usage',
                'value' => $performanceData['summary']['avg_cpu_usage'] ?? 0,
                'status' => ($performanceData['summary']['avg_cpu_usage'] ?? 0) > 80 ? 'danger' : (($performanceData['summary']['avg_cpu_usage'] ?? 0) > 60 ? 'warning' : 'good'),
                'description' => 'Average CPU usage over selected period'
            ],
            [
                'name' => 'Memory Usage',
                'value' => $performanceData['summary']['avg_memory_usage'] ?? 0,
                'status' => ($performanceData['summary']['avg_memory_usage'] ?? 0) > 80 ? 'danger' : (($performanceData['summary']['avg_memory_usage'] ?? 0) > 60 ? 'warning' : 'good'),
                'description' => 'Average memory usage over selected period'
            ],
            [
                'name' => 'Disk Usage',
                'value' => $performanceData['summary']['avg_disk_usage'] ?? 0,
                'status' => ($performanceData['summary']['avg_disk_usage'] ?? 0) > 90 ? 'danger' : (($performanceData['summary']['avg_disk_usage'] ?? 0) > 70 ? 'warning' : 'good'),
                'description' => 'Average disk usage over selected period'
            ],
            [
                'name' => 'Database Connections',
                'value' => min(100, ($performanceData['summary']['avg_database_connections'] ?? 0) * 10),
                'status' => ($performanceData['summary']['avg_database_connections'] ?? 0) > 80 ? 'warning' : 'good',
                'description' => 'Average database connections'
            ]
        ];
    } elseif (is_array($performanceData) && count($performanceData) > 0 && isset($performanceData[0]['name'])) {
        // Performance data is already in the display format
        $performanceDataArray = $performanceData;
    } else {
        // Fallback to default performance metrics
        $performanceDataArray = [
            [
                'name' => 'CPU Usage',
                'value' => $cpuUsage,
                'status' => $cpuUsage > 80 ? 'danger' : ($cpuUsage > 60 ? 'warning' : 'good'),
                'description' => 'Current CPU utilization'
            ],
            [
                'name' => 'Memory Usage',
                'value' => $memoryUsage,
                'status' => $memoryUsage > 80 ? 'danger' : ($memoryUsage > 60 ? 'warning' : 'good'),
                'description' => 'Current memory utilization'
            ],
            [
                'name' => 'Disk Usage',
                'value' => $diskUsage,
                'status' => $diskUsage > 90 ? 'danger' : ($diskUsage > 70 ? 'warning' : 'good'),
                'description' => 'Current disk space usage'
            ],
            [
                'name' => 'System Load',
                'value' => min(100, ($systemMetrics['load_average']['1min'] ?? 0) * 20),
                'status' => ($systemMetrics['load_average']['1min'] ?? 0) > 4 ? 'warning' : 'good',
                'description' => '1-minute load average'
            ]
        ];
    }
    
    // Format usage statistics
    $apiCalls = $usageStatistics['api_calls_today'] ?? 0;
    $emailsSent = $usageStatistics['emails_sent_today'] ?? 0;
    $paymentsProcessed = $usageStatistics['payments_processed_today'] ?? 0;
    $diskUsagePercentage = $usageStatistics['disk_usage_percentage'] ?? $diskUsage;
    
    // Check if there are any active alerts
    $hasCriticalAlerts = collect($activeAlerts)->contains('level', 'critical');
    $hasWarningAlerts = collect($activeAlerts)->contains('level', 'warning');
    $totalAlerts = count($activeAlerts);
    
    // Get route URLs for AJAX calls
    $clearLogsRoute = route('developer.monitoring.clear-logs');
    $clearCacheRoute = route('developer.tools.clear-cache');
    $optimizeRoute = route('developer.tools.optimize');
    $healthCheckRoute = route('developer.monitoring.health-check');
    $generateReportRoute = route('developer.monitoring.report.generate');
    $errorLogRoute = route('developer.monitoring.error-log');
    $settingsIndexRoute = route('developer.settings.index');
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-start md:items-center gap-4">
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i> 
                        System Monitoring Dashboard
                    </h2>
                    <div class="text-sm flex flex-wrap items-center gap-2 mt-1" style="color: var(--text-secondary);">
                        <span class="flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Real-time system performance and health monitoring
                        </span>
                        @if($totalAlerts > 0)
                        <span class="flex items-center ml-2 px-2 py-1 rounded text-xs font-medium"
                              style="background-color: {{ $hasCriticalAlerts ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; 
                                     color: {{ $hasCriticalAlerts ? 'var(--danger)' : 'var(--warning)' }};
                                     border: 1px solid {{ $hasCriticalAlerts ? 'rgba(var(--danger-rgb), 0.3)' : 'rgba(var(--warning-rgb), 0.3)' }};">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            {{ $totalAlerts }} alert{{ $totalAlerts > 1 ? 's' : '' }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm flex flex-wrap items-center gap-3" style="color: var(--text-secondary);">
                <span class="flex items-center">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </span>
                <span class="flex items-center">
                    <i class="fas fa-clock mr-1"></i> {{ now()->format('h:i A') }}
                </span>
                <button onclick="generateReport()"
                       class="btn-primary px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center">
                    <i class="fas fa-file-export mr-1"></i> Generate Report
                </button>
                <button onclick="refreshMetrics()"
                       class="btn-secondary px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center">
                    <i class="fas fa-redo mr-1"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    @if($totalAlerts > 0)
    <!-- Alerts Banner -->
    <div class="alert alert-{{ $hasCriticalAlerts ? 'danger' : 'warning' }}" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span class="font-bold">{{ $hasCriticalAlerts ? 'Critical' : 'Warning' }} Alerts Detected</span>
            <span class="ml-2">{{ $totalAlerts }} alert{{ $totalAlerts > 1 ? 's' : '' }} found</span>
        </div>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- System Health Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- CPU Usage -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-microchip text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">CPU Usage</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">
                        {{ $cpuUsage }}%
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="badge badge-{{ $cpuClass }} inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-chart-bar mr-1 text-xs"></i>
                        Usage
                    </span>
                </div>
            </div>
            <!-- CPU Usage Bar -->
            <div class="mt-3">
                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                    <div class="h-2 rounded-full transition-all duration-500" 
                         style="width: {{ min(100, $cpuUsage) }}%; background-color: var(--{{ $cpuClass }});"></div>
                </div>
                <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                    <span>0%</span>
                    <span class="font-medium">{{ $cpuUsage }}%</span>
                    <span>100%</span>
                </div>
            </div>
        </div>
        
        <!-- Memory Usage -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-memory text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Memory Usage</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">
                        {{ $memoryUsage }}%
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="badge badge-{{ $memClass }} inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-server mr-1 text-xs"></i>
                        RAM
                    </span>
                </div>
            </div>
            <!-- Memory Usage Bar -->
            <div class="mt-3">
                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                    <div class="h-2 rounded-full transition-all duration-500" 
                         style="width: {{ min(100, $memoryUsage) }}%; background-color: var(--{{ $memClass }});"></div>
                </div>
                <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                    <span>0%</span>
                    <span class="font-medium">{{ $memoryUsage }}%</span>
                    <span>100%</span>
                </div>
            </div>
        </div>
        
        <!-- Disk Usage -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-hdd text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Disk Usage</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">
                        {{ $diskUsage }}%
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="badge badge-{{ $diskClass }} inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-database mr-1 text-xs"></i>
                        Storage
                    </span>
                </div>
            </div>
            <!-- Disk Usage Bar -->
            <div class="mt-3">
                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                    <div class="h-2 rounded-full transition-all duration-500" 
                         style="width: {{ min(100, $diskUsage) }}%; background-color: var(--{{ $diskClass }});"></div>
                </div>
                <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                    <span>0%</span>
                    <span class="font-medium">{{ $diskUsage }}%</span>
                    <span>100%</span>
                </div>
            </div>
        </div>
        
        <!-- Active Alerts -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--{{ $totalAlerts > 0 ? 'danger' : 'success' }}-rgb), 0.1); 
                                color: var(--{{ $totalAlerts > 0 ? 'danger' : 'success' }});">
                        <i class="fas {{ $totalAlerts > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' }} text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Active Alerts</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">
                        {{ $totalAlerts }}
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    @if($totalAlerts > 0)
                    <span class="badge badge-{{ $hasCriticalAlerts ? 'danger' : 'warning' }} inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-bell mr-1 text-xs"></i>
                        {{ $hasCriticalAlerts ? 'Critical' : 'Warning' }}
                    </span>
                    @else
                    <span class="badge badge-success inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-check mr-1 text-xs"></i>
                        Clear
                    </span>
                    @endif
                </div>
            </div>
            @if($totalAlerts > 0)
            <div class="mt-3">
                <div class="text-xs" style="color: var(--text-secondary);">
                    @php
                        $criticalCount = collect($activeAlerts)->where('level', 'critical')->count();
                        $warningCount = collect($activeAlerts)->where('level', 'warning')->count();
                    @endphp
                    <div class="flex justify-between mb-1">
                        <span>Critical: {{ $criticalCount }}</span>
                        <span>Warning: {{ $warningCount }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Performance Metrics Chart -->
        <div class="card">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-tachometer-alt mr-2" style="color: var(--primary);"></i> Performance Metrics
                    </h3>
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-info inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-clock mr-1 text-xs"></i>
                            Last 24 hours
                        </span>
                    </div>
                </div>
                
                <div class="space-y-4">
                    @foreach($performanceDataArray as $metric)
                    <div class="p-3 rounded-lg border hover:border-{{ $metric['status'] == 'danger' ? 'danger' : ($metric['status'] == 'warning' ? 'warning' : 'success') }} transition-colors duration-200" 
                         style="border-color: var(--border-color);">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-{{ $metric['name'] == 'CPU Usage' ? 'microchip' : ($metric['name'] == 'Memory Usage' ? 'memory' : ($metric['name'] == 'Disk Usage' ? 'hdd' : 'database')) }} mr-2 text-sm"></i>
                                {{ $metric['name'] }}
                            </span>
                            <span class="badge badge-{{ $metric['status'] == 'good' ? 'success' : ($metric['status'] == 'warning' ? 'warning' : 'danger') }} px-2 py-1 text-xs">
                                {{ ucfirst($metric['status']) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                                    <div class="h-2 rounded-full transition-all duration-700" 
                                         style="width: {{ min(100, $metric['value']) }}%; background-color: var(--{{ $metric['status'] == 'danger' ? 'danger' : ($metric['status'] == 'warning' ? 'warning' : 'success') }});"></div>
                                </div>
                            </div>
                            <span class="text-sm font-medium min-w-10 text-right" style="color: var(--text-primary);">
                                {{ number_format($metric['value'], 1) }}%
                            </span>
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>{{ $metric['description'] }}
                        </p>
                    </div>
                    @endforeach
                </div>
                
                <!-- Health Check Button -->
                <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <span class="text-sm" style="color: var(--text-secondary);">Run system health check:</span>
                        <button onclick="runHealthCheck()" 
                               class="btn btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                            <i class="fas fa-heartbeat mr-2"></i> Run Health Check
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Logs -->
        <div class="card">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i> Recent Error Logs
                    </h3>
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-info inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-list mr-1 text-xs"></i>
                            Last 50 errors
                        </span>
                        <a href="{{ $errorLogRoute }}" class="btn btn-info px-2 py-1 rounded-lg text-xs">
                            <i class="fas fa-external-link-alt mr-1"></i> Full Log
                        </a>
                        @if($errorLogs->count() > 0)
                        <button onclick="clearErrorLogs()" class="btn btn-danger px-2 py-1 rounded-lg text-xs">
                            <i class="fas fa-trash-alt mr-1"></i> Clear
                        </button>
                        @endif
                    </div>
                </div>
                
                @if($errorLogs->count() > 0)
                <div class="overflow-y-auto max-h-96 pr-2">
                    <div class="space-y-3">
                        @foreach($errorLogs as $error)
                        <div class="p-3 rounded-lg border-l-4 hover:shadow-sm transition-shadow duration-200" 
                             style="border-left-color: var(--{{ $error['level'] == 'error' || $error['level'] == 'critical' ? 'danger' : ($error['level'] == 'warning' ? 'warning' : 'info') }}); 
                                    background-color: rgba(var(--{{ $error['level'] == 'error' || $error['level'] == 'critical' ? 'danger' : ($error['level'] == 'warning' ? 'warning' : 'info') }}-rgb), 0.05);">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-medium text-sm mb-1" style="color: var(--text-primary);">
                                        <i class="fas fa-{{ $error['level'] == 'error' || $error['level'] == 'critical' ? 'exclamation-circle' : ($error['level'] == 'warning' ? 'exclamation-triangle' : 'info-circle') }} mr-2" 
                                           style="color: var(--{{ $error['level'] == 'error' || $error['level'] == 'critical' ? 'danger' : ($error['level'] == 'warning' ? 'warning' : 'info') }});"></i>
                                        {{ Str::limit($error['message'] ?? 'No message', 100) }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 text-xs mt-2" style="color: var(--text-secondary);">
                                        @if(isset($error['file']) && $error['file'] != 'Unknown')
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-file mr-1"></i> 
                                            {{ basename($error['file']) }}:{{ $error['line'] ?? '0' }}
                                        </span>
                                        @endif
                                        @if(isset($error['url']) && $error['url'] != 'Unknown')
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-link mr-1"></i> 
                                            {{ Str::limit($error['url'], 30) }}
                                        </span>
                                        @endif
                                        @if(isset($error['method']) && $error['method'] != 'Unknown')
                                        <span class="badge badge-secondary px-1 py-0.5">
                                            {{ $error['method'] }}
                                        </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="ml-2 flex flex-col items-end">
                                    <span class="text-xs whitespace-nowrap mb-1" style="color: var(--text-secondary);">
                                        {{ $error['time'] ?? now()->format('H:i') }}
                                    </span>
                                    <button onclick="viewErrorDetails('{{ $error['id'] ?? '0' }}')" 
                                            class="btn btn-info px-2 py-0.5 rounded-lg text-xs">
                                        <i class="fas fa-eye mr-1"></i> Details
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Error Statistics -->
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div>
                            <div class="text-lg font-bold" style="color: var(--danger);">
                                {{ $errorLogs->whereIn('level', ['error', 'critical'])->count() }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">Errors</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold" style="color: var(--warning);">
                                {{ $errorLogs->where('level', 'warning')->count() }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">Warnings</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold" style="color: var(--info);">
                                {{ $errorLogs->where('level', 'info')->count() }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">Info</div>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-3xl mb-3" style="color: var(--success);"></i>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">No recent errors</p>
                    <p class="text-xs" style="color: var(--text-secondary);">The system is running smoothly</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Usage Statistics -->
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-bar mr-2" style="color: var(--info);"></i> Usage Statistics (Today)
            </h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- API Calls -->
                <div class="text-center p-4 rounded-lg border hover:border-primary transition-colors duration-200" style="border-color: var(--border-color); background-color: var(--card-bg);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-exchange-alt text-lg"></i>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($apiCalls) }}
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">API Calls</p>
                    @if($apiCalls > 0)
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        ≈ {{ number_format($apiCalls / max(1, date('H'))) }}/hour
                    </p>
                    @endif
                </div>
                
                <!-- Emails Sent -->
                <div class="text-center p-4 rounded-lg border hover:border-info transition-colors duration-200" style="border-color: var(--border-color); background-color: var(--card-bg);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-envelope text-lg"></i>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($emailsSent) }}
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Emails Sent</p>
                    @if($emailsSent > 0)
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        ≈ {{ number_format($emailsSent / max(1, date('H'))) }}/hour
                    </p>
                    @endif
                </div>
                
                <!-- Payments Processed -->
                <div class="text-center p-4 rounded-lg border hover:border-success transition-colors duration-200" style="border-color: var(--border-color); background-color: var(--card-bg);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-money-check-alt text-lg"></i>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($paymentsProcessed) }}
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Payments</p>
                    @if($paymentsProcessed > 0)
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        ≈ {{ number_format($paymentsProcessed / max(1, date('H'))) }}/hour
                    </p>
                    @endif
                </div>
                
                <!-- Disk Usage -->
                <div class="text-center p-4 rounded-lg border hover:border-warning transition-colors duration-200" style="border-color: var(--border-color); background-color: var(--card-bg);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-hdd text-lg"></i>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $diskUsagePercentage }}%
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Disk Used</p>
                    <div class="w-full rounded-full h-1 mt-2" style="background-color: var(--bg-secondary);">
                        <div class="h-1 rounded-full" 
                             style="width: {{ $diskUsagePercentage }}%; background-color: var(--{{ $diskClass }});"></div>
                    </div>
                </div>
            </div>
            
            <!-- Additional System Info -->
            <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-sm font-medium" style="color: var(--text-secondary);">Uptime</div>
                        <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                            {{ $systemMetrics['uptime'] ?? 'Unknown' }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium" style="color: var(--text-secondary);">Processes</div>
                        <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                            {{ $systemMetrics['process_count'] ?? '0' }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium" style="color: var(--text-secondary);">DB Connections</div>
                        <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                            {{ $systemMetrics['database_connections']['total'] ?? '0' }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium" style="color: var(--text-secondary);">Load Avg (1min)</div>
                        <div class="text-lg font-semibold mt-1" style="color: var(--text-primary);">
                            {{ $systemMetrics['load_average']['1min'] ?? '0.00' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Quick Actions
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('developer.backup.create') }}" 
                   class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all duration-200 hover:scale-105 text-center"
                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); text-decoration: none;">
                    <i class="fas fa-save text-xl mb-2" style="color: var(--primary);"></i>
                    <span class="text-sm font-medium">Backup System</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Create manual backup</span>
                </a>
                
                <button onclick="clearSystemCache()" 
                        class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all duration-200 hover:scale-105 cursor-pointer"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <i class="fas fa-broom text-xl mb-2" style="color: var(--info);"></i>
                    <span class="text-sm font-medium">Clear Cache</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Refresh application cache</span>
                </button>
                
                <button onclick="optimizeSystem()" 
                        class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all duration-200 hover:scale-105 cursor-pointer"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <i class="fas fa-cogs text-xl mb-2" style="color: var(--success);"></i>
                    <span class="text-sm font-medium">Optimize</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Optimize system performance</span>
                </button>
                
                <a href="{{ $settingsIndexRoute }}?section=monitoring" 
                   class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all duration-200 hover:scale-105 text-center"
                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); text-decoration: none;">
                    <i class="fas fa-cog text-xl mb-2" style="color: var(--warning);"></i>
                    <span class="text-sm font-medium">Settings</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Monitoring settings</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
        <p class="mt-4 text-center" style="color: var(--text-primary);">Processing...</p>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

@endsection

@push('styles')
<style>
.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Button styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    line-height: 1;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary);
    border-color: var(--secondary);
}

.btn-info {
    background-color: var(--info);
    color: white;
    border-color: var(--info);
}

.btn-info:hover:not(:disabled) {
    background-color: #17a2b8;
    border-color: #17a2b8;
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    border-color: var(--danger);
}

.btn-danger:hover:not(:disabled) {
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-warning {
    background-color: var(--warning);
    color: white;
    border-color: var(--warning);
}

.btn-warning:hover:not(:disabled) {
    background-color: #ffc107;
    border-color: #ffc107;
}

.btn-success {
    background-color: var(--success);
    color: white;
    border-color: var(--success);
}

.btn-success:hover:not(:disabled) {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border-color: rgba(var(--secondary-rgb), 0.3);
}

.btn-secondary:hover:not(:disabled) {
    background-color: rgba(var(--secondary-rgb), 0.2);
}

/* Badge styles */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1;
}

.badge-danger { 
    background-color: rgba(var(--danger-rgb), 0.1); 
    color: var(--danger); 
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}

.badge-warning { 
    background-color: rgba(var(--warning-rgb), 0.1); 
    color: var(--warning); 
    border: 1px solid rgba(var(--warning-rgb), 0.3);
}

.badge-success { 
    background-color: rgba(var(--success-rgb), 0.1); 
    color: var(--success); 
    border: 1px solid rgba(var(--success-rgb), 0.3);
}

.badge-info { 
    background-color: rgba(var(--info-rgb), 0.1); 
    color: var(--info); 
    border: 1px solid rgba(var(--info-rgb), 0.3);
}

.badge-primary { 
    background-color: rgba(var(--primary-rgb), 0.1); 
    color: var(--primary); 
    border: 1px solid rgba(var(--primary-rgb), 0.3);
}

.badge-secondary { 
    background-color: rgba(var(--secondary-rgb), 0.1); 
    color: var(--secondary); 
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background-color: rgba(var(--success-rgb), 0.1);
    border: 1px solid rgba(var(--success-rgb), 0.3);
    color: var(--success);
}

.alert-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
    color: var(--danger);
}

.alert-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
    color: var(--warning);
}

.alert-info {
    background-color: rgba(var(--info-rgb), 0.1);
    border: 1px solid rgba(var(--info-rgb), 0.3);
    color: var(--info);
}

.alert-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.25rem;
    margin-left: 0.5rem;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.alert-close:hover {
    opacity: 1;
}

/* Animations */
@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.critical-alert {
    animation: pulse 2s infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-2.md\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .badge {
        font-size: 0.625rem;
        padding: 0.125rem 0.375rem;
    }
}
</style>
@endpush

@push('scripts')
<script>
// CSRF token for AJAX requests
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function refreshMetrics() {
    showLoading();
    setTimeout(() => {
        location.reload();
    }, 500);
}

function generateReport() {
    const reportType = prompt('Select report type:\n1. Daily\n2. Weekly\n3. Monthly\n4. Custom', 'daily');
    if (!reportType) return;
    
    const format = prompt('Select format:\n1. PDF\n2. Excel\n3. CSV\n4. JSON', 'pdf');
    if (!format) return;
    
    showToast('Generating report...', 'info');
    
    // Create a form to submit the report request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ $generateReportRoute }}';
    form.style.display = 'none';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    
    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'report_type';
    typeInput.value = reportType;
    form.appendChild(typeInput);
    
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function runHealthCheck() {
    showLoading();
    
    fetch('{{ $healthCheckRoute }}', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            // Display health check results in a modal or alert
            let message = 'Health Check Results:\n\n';
            data.checks.forEach(check => {
                message += `✓ ${check.name}: ${check.status}\n`;
            });
            alert(message);
            showToast('Health check completed successfully!', 'success');
        } else {
            showToast('Health check failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Health check failed', 'error');
    });
}

function viewErrorDetails(errorId) {
    // In a real implementation, this would open a modal with error details
    showToast('Viewing error details for ID: ' + errorId, 'info');
}

function clearErrorLogs() {
    if (confirm('Are you sure you want to clear all error logs?\nThis action cannot be undone.')) {
        showLoading();
        
        fetch('{{ $clearLogsRoute }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('Error logs cleared successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed to clear error logs: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Failed to clear error logs', 'error');
        });
    }
}

function clearSystemCache() {
    if (confirm('Clear system cache?\nThis will refresh cached data.')) {
        showLoading();
        
        fetch('{{ $clearCacheRoute }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('System cache cleared successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed to clear cache: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Failed to clear cache', 'error');
        });
    }
}

function optimizeSystem() {
    if (confirm('Optimize system performance?\nThis may take a few moments.')) {
        showLoading();
        
        fetch('{{ $optimizeRoute }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('System optimized successfully!', 'success');
            } else {
                showToast('Optimization failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Optimization failed', 'error');
        });
    }
}

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Auto-refresh every 5 minutes
setInterval(() => {
    const refreshButton = document.querySelector('[onclick="refreshMetrics()"]');
    if (refreshButton && !document.querySelector('#loadingOverlay')) {
        console.log('Auto-refreshing metrics...');
        // refreshMetrics(); // Uncomment to enable auto-refresh
    }
}, 300000); // 5 minutes = 300000 ms

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Any initialization code here
});
</script>
@endpush